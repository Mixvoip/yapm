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
                "PARTIAL g.{id, name, publicKey, private}",
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

        // Validate at least one permission is provided
        $this->assertAtLeastOnePermission($dto->groups, $dto->userPermissions);

        [$requested, $requestedGroupIds, $groups] = $this->resolveAndValidateGroups($dto, $entityManager);
        [$requestedUserPerms, $privateGroups, $userIdToGroupId] = $this->resolveAndValidateUserPermissions(
            $dto,
            $entityManager
        );

        // Enforce at least one group or user with write access
        $this->assertAtLeastOneWriteAccess($requested, $requestedUserPerms);

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

        // Calculate hasChildren efficiently - only relevant when not cascading
        $hasChildren = false;
        if (!$dto->cascade) {
            $hasChildren = $this->folderHasChildren($folder->getId(), $entityManager);
        }

        [$current, $target] = $this->buildSnapShots($folder, $requested, $fullWrite, $hasChildren);
        $isNoOp = $current === $target;

        if ($isNoOp && !$dto->cascade) {
            // No-op, but not cascading, so return early.
            return $this->json([]);
        }

        $diff = [];
        if (!$isNoOp) {
            // Update FolderGroup relations for this folder (regular groups only).
            $this->updateFolderGroupPermissions(
                $folder,
                $groups,
                $requested,
                $userIdentifier,
                $entityManager,
                $fullWrite,
                forPrivateGroups: false,
                hasChildren: $hasChildren
            );

            $diff = $this->diffPermissions($current, $target, $groupNames);
        }

        // Update private group relations (user permissions) for this folder
        if (!empty($requestedUserPerms)) {
            $this->updateFolderGroupPermissions(
                $folder,
                $privateGroups,
                $requestedUserPerms,
                $userIdentifier,
                $entityManager,
                $fullWrite,
                forPrivateGroups: true,
                hasChildren: $hasChildren
            );
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
        $process = $this->buildShareProcess(
            ProcessTargetType::Folder,
            $folder,
            $dto,
            $groups,
            $privateGroups,
            $userIdToGroupId,
            $userIdentifier
        );
        $entityManager->persist($process);
        $entityManager->flush();
        $bus->dispatch(new ShareProcessMessage($process->getId(), $dto->encryptedPassword, $clientIp, $userAgent));

        return $this->json($process, Response::HTTP_ACCEPTED, context: [ShareProcessNormalizer::MINIMISED]);
    }

    /**
     * Update FolderGroup relations for a folder.
     * Handles both regular groups (isPrivate=false) and private groups (user permissions, isPrivate=true).
     *
     * @param  Folder  $folder
     * @param  Group[]  $groups  Groups to update (keyed by ID for $requested lookup)
     * @param  array  $requested  Requested permissions keyed by group ID ['canWrite' => bool, 'partial' => bool]
     * @param  string  $userIdentifier
     * @param  EntityManagerInterface  $entityManager
     * @param  bool  $fullWrite
     * @param  bool  $forPrivateGroups  True to process private groups, false to process regular groups
     * @param  bool  $hasChildren  Whether folder has children (cascade=false + hasChildren = partial)
     *
     * @return void
     */
    private function updateFolderGroupPermissions(
        Folder $folder,
        array $groups,
        array $requested,
        string $userIdentifier,
        EntityManagerInterface $entityManager,
        bool $fullWrite,
        bool $forPrivateGroups,
        bool $hasChildren = false
    ): void {
        // Map current groups (filter by private flag)
        $currentMap = [];
        foreach ($folder->getFolderGroups() as $fg) {
            $isPrivate = $fg->getGroup()->isPrivate();
            if ($forPrivateGroups !== $isPrivate) {
                continue;
            }
            $currentMap[$fg->getGroup()->getId()] = $fg;
        }
        $currentIds = array_keys($currentMap);
        $requestedIds = array_keys($requested);

        $toRemove = array_diff($currentIds, $requestedIds);
        $toAdd = array_diff($requestedIds, $currentIds);
        $toKeep = array_intersect($currentIds, $requestedIds);

        $groupsById = [];
        foreach ($groups as $group) {
            $groupsById[$group->getId()] = $group;
        }

        // Add new relations
        $addedGroups = [];
        foreach ($toAdd as $gid) {
            if (!isset($groupsById[$gid])) {
                continue;
            }
            $group = $groupsById[$gid];
            $groupPermission = $requested[$gid];
            $requestedPartial = $groupPermission['partial'] ?? false;
            if ($requestedPartial) {
                continue;
            }
            $partial = $requestedPartial || !$fullWrite || $hasChildren;

            $fg = new FoldersGroup();
            $fg->setFolder($folder)
               ->setGroup($group)
               ->setCanWrite($groupPermission['canWrite'])
               ->setPartial($partial)
               ->setCreatedBy($userIdentifier);

            $folder->addFolderGroup($fg);
            $entityManager->persist($fg);
            $addedGroups[] = $group;
        }

        // Propagate added groups to parent folder and vault
        if (!empty($addedGroups)) {
            $parent = $folder->getParent();
            if (!is_null($parent)) {
                $this->addMissingGroupsToParentFolder($parent, $addedGroups, $userIdentifier, $entityManager);
            }

            $this->addMissingGroupsToVault($folder->getVault(), $addedGroups, $userIdentifier, $entityManager);
        }

        // Update kept relations
        foreach ($toKeep as $gid) {
            /** @var FoldersGroup $fg */
            $fg = $currentMap[$gid];
            $groupPermission = $requested[$gid];
            $newCanWrite = $groupPermission['canWrite'];
            $requestedPartial = $groupPermission['partial'] ?? false;
            if ($requestedPartial) {
                continue;
            }
            $newPartial = $requestedPartial || !$fullWrite || $hasChildren;

            if ($fg->canWrite() !== $newCanWrite || $fg->isPartial() !== $newPartial) {
                $fg->setCanWrite($newCanWrite)
                   ->setPartial($newPartial)
                   ->setUpdatedBy($userIdentifier);
            }
        }

        // Demote removed relations
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

        // Mark parent as partial for demoted groups
        if (!empty($demotedGroupIds)) {
            $parent = $folder->getParent();
            if (!is_null($parent)) {
                $this->markParentFolderAsPartial($parent, $demotedGroupIds, $userIdentifier, $entityManager);
            }

            $this->markVaultAsPartial($folder->getVault(), $demotedGroupIds, $userIdentifier, $entityManager);
        }
    }

    /**
     * Check if a folder has children (child folders or passwords).
     * Uses direct SQL queries for efficiency to avoid loading collections.
     *
     * @param  string  $folderId
     * @param  EntityManagerInterface  $entityManager
     *
     * @return bool
     */
    private function folderHasChildren(string $folderId, EntityManagerInterface $entityManager): bool
    {
        $conn = $entityManager->getConnection();

        // Check for child folders
        $hasChildFolders = $conn->fetchOne(
            "SELECT 1 FROM folders WHERE parent_folder_id = :fid AND deleted_at IS NULL LIMIT 1",
            ['fid' => $folderId]
        );
        if ($hasChildFolders) {
            return true;
        }

        // Check for passwords
        return (bool)$conn->fetchOne(
            "SELECT 1 FROM passwords WHERE folder_id = :fid AND deleted_at IS NULL LIMIT 1",
            ['fid' => $folderId]
        );
    }
}
