<?php

/**
 * @author bsteffan
 * @since 2025-08-22
 */

namespace App\Controller\Vault;

use App\Controller\AbstractPatchPermissionsController;
use App\Controller\Dto\PatchPermissionsWithPartialDto;
use App\Entity\AuditLog;
use App\Entity\Enums\AuditAction;
use App\Entity\Enums\ShareProcess\TargetType as ProcessTargetType;
use App\Entity\Group;
use App\Entity\GroupsVault;
use App\Entity\User;
use App\Entity\Vault;
use App\Message\ShareProcessMessage;
use App\Normalizer\ShareProcessNormalizer;
use App\Repository\VaultRepository;
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
    /**
     * Patch permissions for a vault by managing its group access list.
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
        "/vaults/{id}/permissions",
        name: "api_patch_vaults_id_permissions",
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

        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $entityManager->getRepository(Vault::class);
        $vault = $vaultRepository->findByIds(
            [$id],
            [
                "PARTIAL v.{id, name, updatedAt, updatedBy}",
                "PARTIAL gv.{vault, group, canWrite, partial}",
                "PARTIAL g.{id, name, publicKey}",
            ],
            groupAlias: 'g',
            groupVaultAlias: 'gv'
        )[$id] ?? null;

        if (is_null($vault) || !$vault->hasReadPermission($groupIds)) {
            throw $this->createNotFoundException("Vault with id: $id not found.");
        }

        if ($vault->isPrivate()) {
            throw new BadRequestHttpException("You can't update permissions for a private vault.");
        }

        if (!$vault->hasWritePermission($groupIds)) {
            throw $this->createAccessDeniedException("You don't have permission to update this vault.");
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
        $fullWrite = $vault->hasFullWritePermission($groupIds);
        $groupNames = $this->buildMergedGroupNameMap($vault, $groups);
        [$current, $target] = $this->buildSnapShots($vault, $requested, $fullWrite);
        $isNoOp = $current === $target;

        if ($isNoOp && !$dto->cascade) {
            // No-op, but not cascading, so return early.
            return $this->json([]);
        }

        $diff = [];
        if (!$isNoOp) {
            // Update GroupVault explicit relations for this vault.
            $this->updateGroupVaultRelations(
                $vault,
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
                                   ->setEntityType(Vault::class)
                                   ->setEntityId($vault->getId())
                                   ->setMetadata($vault)
                                   ->setNewValues($diff)
                                   ->setUserId($loggedInUser->getId())
                                   ->setUserEmail($loggedInUser->getEmail())
                                   ->setIpAddress($clientIp)
                                   ->setUserAgent($userAgent);
            $entityManager->persist($audit);
        }

        // Create ShareProcess and dispatch message
        $process = $this->buildShareProcess(ProcessTargetType::Vault, $vault, $dto, $groups, $userIdentifier);
        $entityManager->persist($process);
        $entityManager->flush();

        $bus->dispatch(new ShareProcessMessage($process->getId(), $dto->encryptedPassword, $clientIp, $userAgent));

        return $this->json($process, Response::HTTP_ACCEPTED, context: [ShareProcessNormalizer::MINIMISED]);
    }

    /**
     * Update GroupVault explicit relations to match request, preserving partials not explicitly provided.
     *
     * @param  Vault  $vault
     * @param  Group[]  $groups
     * @param  array  $requestedGroupIds
     * @param  array  $requested
     * @param  string  $userIdentifier
     * @param  EntityManagerInterface  $entityManager
     * @param  bool  $hasFullWrite
     *
     * @return void
     */
    private function updateGroupVaultRelations(
        Vault $vault,
        array $groups,
        array $requestedGroupIds,
        array $requested,
        string $userIdentifier,
        EntityManagerInterface $entityManager,
        bool $hasFullWrite
    ): void {
        // Map current
        $currentMap = [];
        foreach ($vault->getGroupVaults() as $gv) {
            /** @var GroupsVault $gv */
            $currentMap[$gv->getGroup()->getId()] = $gv;
        }
        $currentIds = array_keys($currentMap);

        $toRemove = array_diff($currentIds, $requestedGroupIds);
        $toAdd = array_diff($requestedGroupIds, $currentIds);
        $toKeep = array_intersect($currentIds, $requestedGroupIds);

        $groupsById = [];
        foreach ($groups as $group) {
            $groupsById[$group->getId()] = $group;
        }

        // Add new relations
        foreach ($toAdd as $gid) {
            $group = $groupsById[$gid];
            $groupPermission = $requested[$gid];
            $requestedPartial = $groupPermission['partial'];
            if ($requestedPartial) {
                continue;
            }
            $partial = $groupPermission['partial'] || !$hasFullWrite;

            $gv = new GroupsVault()->setVault($vault)
                                   ->setGroup($group)
                                   ->setCanWrite($groupPermission['canWrite'])
                                   ->setPartial($partial)
                                   ->setCreatedBy($userIdentifier);

            $vault->getGroupVaults()->add($gv);
            $entityManager->persist($gv);
        }

        // Update canWrite for kept groups and ensure explicit (partial=false)
        foreach ($toKeep as $gid) {
            /** @var GroupsVault $gv */
            $gv = $currentMap[$gid];
            $groupPermission = $requested[$gid];
            $newCanWrite = $groupPermission['canWrite'];
            $requestedPartial = $groupPermission['partial'];
            if ($requestedPartial) {
                continue;
            }
            $newPartial = $groupPermission['partial'] || !$hasFullWrite;

            if ($gv->canWrite() !== $newCanWrite || $gv->isPartial() !== $newPartial) {
                $gv->setCanWrite($newCanWrite)
                   ->setPartial($newPartial)
                   ->setUpdatedBy($userIdentifier);
            }
        }

        // Remove relations (but keep partial groups if not explicitly provided)
        foreach ($toRemove as $gid) {
            /** @var GroupsVault $gv */
            $gv = $currentMap[$gid];
            if ($gv->isPartial() && !$gv->canWrite()) {
                continue;
            }

            $gv->setPartial(true)
               ->setCanWrite(false)
               ->setUpdatedBy($userIdentifier);
            $entityManager->persist($gv);
        }
    }
}
