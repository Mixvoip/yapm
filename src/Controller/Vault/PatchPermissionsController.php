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
                "PARTIAL g.{id, name, publicKey, private}",
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
        $fullWrite = $vault->hasFullWritePermission($groupIds);
        $groupNames = $this->buildMergedGroupNameMap($vault, $groups);

        // Calculate hasChildren efficiently - only relevant when not cascading
        $hasChildren = false;
        if (!$dto->cascade) {
            $hasChildren = $this->vaultHasChildren($vault->getId(), $entityManager);
        }

        [$current, $target] = $this->buildSnapShots($vault, $requested, $fullWrite, $hasChildren);
        $isNoOp = $current === $target;

        if ($isNoOp && !$dto->cascade) {
            // No-op, but not cascading, so return early.
            return $this->json([]);
        }

        $diff = [];
        if (!$isNoOp) {
            // Update GroupVault explicit relations for this vault (regular groups only).
            $this->updateVaultGroupPermissions(
                $vault,
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

        // Update private group relations (user permissions) for this vault
        // Always process - even if requestedUserPerms is empty, we need to demote existing private groups
        $this->updateVaultGroupPermissions(
            $vault,
            $privateGroups,
            $requestedUserPerms,
            $userIdentifier,
            $entityManager,
            $fullWrite,
            forPrivateGroups: true,
            hasChildren: $hasChildren
        );

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
        $process = $this->buildShareProcess(
            ProcessTargetType::Vault,
            $vault,
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
     * Update GroupVault relations for a vault.
     * Handles both regular groups (isPrivate=false) and private groups (user permissions, isPrivate=true).
     *
     * @param  Vault  $vault
     * @param  Group[]  $groups  Groups to update (keyed by ID for $requested lookup)
     * @param  array  $requested  Requested permissions keyed by group ID ['canWrite' => bool, 'partial' => bool]
     * @param  string  $userIdentifier
     * @param  EntityManagerInterface  $entityManager
     * @param  bool  $hasFullWrite
     * @param  bool  $forPrivateGroups  True to process private groups, false to process regular groups
     * @param  bool  $hasChildren  Whether vault has children (cascade=false + hasChildren = partial)
     *
     * @return void
     */
    private function updateVaultGroupPermissions(
        Vault $vault,
        array $groups,
        array $requested,
        string $userIdentifier,
        EntityManagerInterface $entityManager,
        bool $hasFullWrite,
        bool $forPrivateGroups,
        bool $hasChildren = false
    ): void {
        // Map current groups (filter by private flag)
        $currentMap = [];
        foreach ($vault->getGroupVaults() as $gv) {
            /** @var GroupsVault $gv */
            $isPrivate = $gv->getGroup()->isPrivate();
            if ($forPrivateGroups !== $isPrivate) {
                continue;
            }
            $currentMap[$gv->getGroup()->getId()] = $gv;
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
            $partial = $requestedPartial || !$hasFullWrite || $hasChildren;

            $gv = new GroupsVault();
            $gv->setVault($vault)
               ->setGroup($group)
               ->setCanWrite($groupPermission['canWrite'])
               ->setPartial($partial)
               ->setCreatedBy($userIdentifier);

            $vault->getGroupVaults()->add($gv);
            $entityManager->persist($gv);
        }

        // Update kept relations
        foreach ($toKeep as $gid) {
            /** @var GroupsVault $gv */
            $gv = $currentMap[$gid];
            $groupPermission = $requested[$gid];
            $newCanWrite = $groupPermission['canWrite'];
            $requestedPartial = $groupPermission['partial'] ?? false;
            if ($requestedPartial) {
                continue;
            }
            $newPartial = $requestedPartial || !$hasFullWrite || $hasChildren;

            if ($gv->canWrite() !== $newCanWrite || $gv->isPartial() !== $newPartial) {
                $gv->setCanWrite($newCanWrite)
                   ->setPartial($newPartial)
                   ->setUpdatedBy($userIdentifier);
            }
        }

        // Demote removed relations
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

    /**
     * Check if a vault has children (folders or root-level passwords).
     * Uses direct SQL queries for efficiency to avoid loading collections.
     *
     * @param  string  $vaultId
     * @param  EntityManagerInterface  $entityManager
     *
     * @return bool
     */
    private function vaultHasChildren(string $vaultId, EntityManagerInterface $entityManager): bool
    {
        $conn = $entityManager->getConnection();

        // Check for folders
        $hasFolders = $conn->fetchOne(
            "SELECT 1 FROM folders WHERE vault_id = :vid AND deleted_at IS NULL LIMIT 1",
            ['vid' => $vaultId]
        );
        if ($hasFolders) {
            return true;
        }

        // Check for passwords directly in vault (not in folders)
        return (bool)$conn->fetchOne(
            "SELECT 1 FROM passwords WHERE vault_id = :vid AND folder_id IS NULL AND deleted_at IS NULL LIMIT 1",
            ['vid' => $vaultId]
        );
    }
}
