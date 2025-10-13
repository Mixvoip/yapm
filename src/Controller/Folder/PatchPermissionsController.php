<?php

/**
 * @author bsteffan
 * @since 2025-08-21
 */

namespace App\Controller\Folder;

use App\Controller\AbstractPatchPermissionsController;
use App\Controller\Dto\PatchPermissionsWithPartialDto;
use App\Controller\GroupInheritanceTrait;
use App\Entity\AuditLog;
use App\Entity\Enums\AuditAction;
use App\Entity\Enums\ShareProcess\TargetType as ProcessTargetType;
use App\Entity\Folder;
use App\Entity\FoldersGroup;
use App\Entity\Group;
use App\Entity\User;
use App\Message\ShareProcessMessage;
use App\Normalizer\ShareProcessNormalizer;
use App\Repository\FolderRepository;
use App\Service\Audit\AuditService;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class PatchPermissionsController extends AbstractPatchPermissionsController
{
    use GroupInheritanceTrait;

    /**
     * Patch permissions for a folder by managing its group access list.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     * @param  PatchPermissionsWithPartialDto  $dto
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     * @param  MessageBusInterface  $bus
     * @param  Request  $request
     *
     * @return JsonResponse
     * @throws ExceptionInterface
     */
    #[Route(
        "/folders/{id}/permissions",
        name: "api_patch_folders_id_permissions",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["PATCH"]
    )]
    public function index(
        string $id,
        EntityManagerInterface $entityManager,
        #[MapRequestPayload] PatchPermissionsWithPartialDto $dto,
        UserPasswordHasherInterface $passwordHasher,
        EncryptionService $encryptionService,
        MessageBusInterface $bus,
        Request $request
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();
        $groupIds = $loggedInUser->getGroupIds();
        $this->passwordHasher = $passwordHasher;
        $this->encryptionService = $encryptionService;

        /** @var FolderRepository $folderRepository */
        $folderRepository = $entityManager->getRepository(Folder::class);
        $folder = $folderRepository->findByIds(
            [$id],
            [
                "PARTIAL f.{id, name, externalId, updatedAt, updatedBy}",
                "PARTIAL fg.{folder, group, canWrite, partial}",
                "PARTIAL g.{id, name, publicKey}",
                "PARTIAL v.{id, name}",
            ],
            groupAlias: 'g',
            folderGroupAlias: 'fg',
            vaultAlias: 'v'
        )[$id] ?? null;

        if (is_null($folder) || !$folder->hasReadPermission($groupIds)) {
            throw $this->createNotFoundException("Folder with id: $id not found.");
        }

        if ($folder->getVault()->isPrivate()) {
            throw new BadRequestHttpException("You can't update permissions for a private vault.");
        }

        if (!$folder->hasWritePermission($groupIds)) {
            throw $this->createAccessDeniedException("You don't have permission to update this folder.");
        }

        [$requested, $requestedGroupIds, $groups] = $this->resolveAndValidateGroups($dto, $entityManager);

        try {
            $this->decryptUserPrivateKey($dto->encryptedPassword);
        } catch (Exception $e) {
            return $this->json(
                [
                    'error' => "Authentication Error",
                    'message' => $e->getMessage(),
                ],
                401
            );
        }

        $userIdentifier = $loggedInUser->getUserIdentifier();
        $fullWrite = $folder->hasFullWritePermission($groupIds);
        $groupNames = $this->buildMergedGroupNameMap($folder, $groups);
        [$current, $target] = $this->buildSnapShots($folder, $requested, $fullWrite);
        $isNoOp = $current === $target;

        if ($isNoOp && !$dto->cascade) {
            // No-op, but not cascading, so return early.
            return $this->json([]);
        }

        $diff = [];
        if (!$isNoOp) {
            // Update FolderGroup relations for this folder.
            $this->updateFolderGroupRelations(
                $folder,
                $groups,
                $requestedGroupIds,
                $requested,
                $userIdentifier,
                $entityManager,
                $fullWrite
            );

            $diff = $this->diffPermissions($current, $target, $groupNames);
        }

        $clientIp = AuditService::getClientIp($request);
        $userAgent = $request->headers->get('User-Agent');
        if (!empty($diff)) {
            $audit = new AuditLog()->setActionType(AuditAction::Updated)
                                   ->setEntityType(Folder::class)
                                   ->setEntityId($folder->getId())
                                   ->setMetadata($folder)
                                   ->setNewValues($diff)
                                   ->setUserId($loggedInUser->getId())
                                   ->setUserEmail($loggedInUser->getEmail())
                                   ->setIpAddress($clientIp)
                                   ->setUserAgent($userAgent);
            $entityManager->persist($audit);
        }

        // Create ShareProcess and dispatch message
        $process = $this->buildShareProcess(ProcessTargetType::Folder, $folder, $dto, $groups, $userIdentifier);
        $entityManager->persist($process);
        $entityManager->flush();
        $bus->dispatch(new ShareProcessMessage($process->getId(), $dto->encryptedPassword, $clientIp, $userAgent));

        return $this->json($process, Response::HTTP_ACCEPTED, context: [ShareProcessNormalizer::MINIMISED]);
    }

    /**
     * Update FolderGroup explicit relations for a folder to match the requested group ids.
     *
     * @param  Folder  $folder
     * @param  Group[]  $groups
     * @param  array  $requestedGroupIds
     * @param  array  $requested
     * @param  string  $userIdentifier
     * @param  EntityManagerInterface  $entityManager
     * @param  bool  $fullWrite
     *
     * @return void
     */
    private function updateFolderGroupRelations(
        Folder $folder,
        array $groups,
        array $requestedGroupIds,
        array $requested,
        string $userIdentifier,
        EntityManagerInterface $entityManager,
        bool $fullWrite
    ): void {
        // Map current
        $currentMap = [];
        foreach ($folder->getFolderGroups() as $fg) {
            $currentMap[$fg->getGroup()->getId()] = $fg;
        }
        $currentIds = array_keys($currentMap);

        $toRemove = array_diff($currentIds, $requestedGroupIds);
        $toAdd = array_diff($requestedGroupIds, $currentIds);
        $toKeep = array_intersect($currentIds, $requestedGroupIds);

        $groupsById = [];
        foreach ($groups as $group) {
            $groupsById[$group->getId()] = $group;
        }

        $addedGroups = [];
        // Add new relations
        foreach ($toAdd as $gid) {
            $group = $groupsById[$gid];
            $groupPermission = $requested[$gid];
            $requestedPartial = $groupPermission['partial'];
            if ($requestedPartial) {
                continue;
            }
            $partial = $groupPermission['partial'] || !$fullWrite;

            $fg = new FoldersGroup()->setFolder($folder)
                                    ->setGroup($group)
                                    ->setCanWrite($groupPermission['canWrite'])
                                    ->setPartial($partial)
                                    ->setCreatedBy($userIdentifier);

            $folder->addFolderGroup($fg);
            $entityManager->persist($fg);
            $addedGroups[] = $group;
        }

        if (!empty($addedGroups)) {
            $parent = $folder->getParent();
            if (!is_null($parent)) {
                $this->addMissingGroupsToParentFolder($parent, $addedGroups, $userIdentifier, $entityManager);
            }

            $this->addMissingGroupsToVault($folder->getVault(), $addedGroups, $userIdentifier, $entityManager);
        }

        foreach ($toKeep as $gid) {
            /** @var FoldersGroup $fg */
            $fg = $currentMap[$gid];
            $groupPermission = $requested[$gid];
            $newCanWrite = $groupPermission['canWrite'];
            $requestedPartial = $groupPermission['partial'];
            if ($requestedPartial) {
                continue;
            }
            $newPartial = $groupPermission['partial'] || !$fullWrite;

            if ($fg->canWrite() !== $newCanWrite || $fg->isPartial() !== $newPartial) {
                $fg->setCanWrite($newCanWrite)
                   ->setPartial($newPartial)
                   ->setUpdatedBy($userIdentifier);
            }
        }

        // Demote omitted relations
        $demotedGroupIds = [];
        foreach ($toRemove as $gid) {
            /** @var FoldersGroup $fg */
            $fg = $currentMap[$gid];
            if ($fg->isPartial() && !$fg->canWrite()) {
                continue;
            }

            $fg->setPartial(true)
               ->setCanWrite(false)
               ->setUpdatedBy($userIdentifier);
            $entityManager->persist($fg);
            $demotedGroupIds[] = $gid;
        }

        if (!empty($demotedGroupIds)) {
            $parent = $folder->getParent();
            if (!is_null($parent)) {
                $this->markParentFolderAsPartial($parent, $demotedGroupIds, $userIdentifier, $entityManager);
            }

            $this->markVaultAsPartial($folder->getVault(), $demotedGroupIds, $userIdentifier, $entityManager);
        }
    }
}
