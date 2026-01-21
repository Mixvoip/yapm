<?php

/**
 * @author bsteffan
 * @since 2025-09-10
 */

namespace App\Controller;

use App\Controller\Dto\GroupPermissionWithPartialDto;
use App\Controller\Dto\PatchPermissionsWithPartialDto;
use App\Controller\Dto\UserPermissionWithPartialDto;
use App\Entity\Enums\ShareProcess\TargetType;
use App\Entity\Folder;
use App\Entity\Group;
use App\Entity\ShareProcess;
use App\Entity\Vault;
use App\Repository\GroupRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractPatchPermissionsController extends AbstractController
{
    use EncryptionAwareTrait;
    use GroupValidationTrait;

    /**
     * Check if the request is a no-op.
     * Excludes private groups from the current state - they are managed via userPermissions.
     *
     * @param  object  $resource
     * @param  array  $requested
     * @param  bool  $fullWrite
     * @param  bool  $hasChildren  Whether the resource has children (only relevant when cascade=false)
     *
     * @return array
     * @throws InvalidArgumentException
     */
    protected function buildSnapShots(
        object $resource,
        array $requested,
        bool $fullWrite,
        bool $hasChildren = false
    ): array {
        $current = [];

        if ($resource instanceof Folder) {
            foreach ($resource->getFolderGroups() as $fg) {
                if ($fg->getGroup()->isPrivate()) {
                    continue; // Skip private groups - managed via userPermissions
                }
                $current[$fg->getGroup()->getId()] = [
                    'canWrite' => $fg->canWrite(),
                    'partial' => $fg->isPartial(),
                ];
            }
        } elseif ($resource instanceof Vault) {
            foreach ($resource->getGroupVaults() as $gv) {
                if ($gv->getGroup()->isPrivate()) {
                    continue; // Skip private groups - managed via userPermissions
                }
                $current[$gv->getGroup()->getId()] = [
                    'canWrite' => $gv->canWrite(),
                    'partial' => $gv->isPartial(),
                ];
            }
        } else {
            throw new InvalidArgumentException("Unsupported resource type.");
        }

        $target = array_map(
            function ($groupPermissions) use ($fullWrite, $hasChildren) {
                return [
                    'canWrite' => $groupPermissions['canWrite'],
                    'partial' => $groupPermissions['partial'] || !$fullWrite || $hasChildren,
                ];
            },
            $requested
        );

        return [$current, $target];
    }

    /**
     * Show the differences between the old and new permissions.
     *
     * @param  array  $oldPermissions
     * @param  array  $newPermissions
     * @param  array  $groupNames
     *
     * @return array
     */
    protected function diffPermissions(array $oldPermissions, array $newPermissions, array $groupNames): array
    {
        $diff = [
            'permissions' => [
                'add' => [],
                'remove' => [],
                'update' => [],
            ],
        ];

        $all = array_unique(array_merge(array_keys($oldPermissions), array_keys($newPermissions)));
        foreach ($all as $gid) {
            $oldPermission = $oldPermissions[$gid] ?? null;
            $newPermission = $newPermissions[$gid] ?? null;

            $groupName = $groupNames[$gid];

            $oldAccess = is_null($oldPermission) ? null : ($oldPermission['canWrite'] ? 'write' : 'read');
            $newAccess = is_null($newPermission) ? null : ($newPermission['canWrite'] ? 'write' : 'read');
            $oldPartial = $oldPermission['partial'] ?? null;
            $newPartial = $newPermission['partial'] ?? null;

            if (is_null($oldPermission) && !is_null($newPermission)) {
                $diff['permissions']['add'][] = [
                    'groupId' => $gid,
                    'groupName' => $groupName,
                    'access' => $newAccess,
                    'partial' => $newPartial,
                ];
            } elseif (!is_null($oldPermission) && is_null($newPermission)) {
                $diff['permissions']['remove'][] = [
                    'groupId' => $gid,
                    'groupName' => $groupName,
                    'access' => $oldAccess,
                    'partial' => $oldPartial,
                ];
            } elseif (
                !is_null($oldPermission)
                && !is_null($newPermission)
                && ($oldAccess !== $newAccess || $oldPartial !== $newPartial)
            ) {
                $diff['permissions']['update'][] = [
                    'groupId' => $gid,
                    'groupName' => $groupName,
                    'from' => ['access' => $oldAccess, 'partial' => $oldPartial],
                    'to' => ['access' => $newAccess, 'partial' => $newPartial],
                ];
            }
        }

        if (
            empty($diff['permissions']['add'])
            && empty($diff['permissions']['remove'])
            && empty($diff['permissions']['update'])
        ) {
            return [];
        }

        return $diff;
    }

    /**
     * Build a share process.
     *
     * @param  TargetType  $targetType
     * @param  AuditableEntityInterface  $scope
     * @param  PatchPermissionsWithPartialDto  $dto
     * @param  Group[]  $groups
     * @param  Group[]  $privateGroups
     * @param  array  $userIdToGroupId
     * @param  string  $userIdentifier
     *
     * @return ShareProcess
     */
    protected function buildShareProcess(
        TargetType $targetType,
        AuditableEntityInterface $scope,
        PatchPermissionsWithPartialDto $dto,
        array $groups,
        array $privateGroups,
        array $userIdToGroupId,
        string $userIdentifier
    ): ShareProcess {
        $shareProcess = new ShareProcess();
        $shareProcess->setTargetType($targetType)
                     ->setScopeId($scope->getId())
                     ->setMetadata($scope)
                     ->setCascade($dto->cascade)
                     ->setRequestedGroups(
                         array_map(
                             fn(GroupPermissionWithPartialDto $g) => [
                                 'groupId' => $g->groupId,
                                 'name' => $groups[$g->groupId]->getName(),
                                 'canWrite' => $g->canWrite,
                                 'partial' => $g->partial,
                             ],
                             $dto->groups
                         )
                     )
                     ->setCreatedBy($userIdentifier);

        // Add user permissions if any
        if (!empty($dto->userPermissions)) {
            // Build userId -> user info map from private groups
            $userInfoMap = [];
            foreach ($privateGroups as $group) {
                foreach ($group->getGroupUsers() as $gu) {
                    $user = $gu->getUser();
                    $userInfoMap[$user->getId()] = [
                        'username' => $user->getUsername(),
                        'email' => $user->getEmail(),
                    ];
                }
            }

            $shareProcess->setRequestedUsers(
                array_map(
                    fn(UserPermissionWithPartialDto $u) => [
                        'userId' => $u->userId,
                        'username' => $userInfoMap[$u->userId]['username'],
                        'email' => $userInfoMap[$u->userId]['email'],
                        'groupId' => $userIdToGroupId[$u->userId],
                        'canWrite' => $u->canWrite,
                        'partial' => $u->partial,
                    ],
                    $dto->userPermissions
                )
            );
        }

        return $shareProcess;
    }

    /**
     * Resolve the dto and validate the groups.
     * Note: Does not enforce write access - caller should check both groups and users.
     *
     * @param  PatchPermissionsWithPartialDto  $dto
     * @param  EntityManagerInterface  $entityManager
     *
     * @return array
     * @throws BadRequestHttpException
     */
    protected function resolveAndValidateGroups(
        PatchPermissionsWithPartialDto $dto,
        EntityManagerInterface $entityManager
    ): array {
        $requested = [];
        foreach ($dto->groups as $groupDto) {
            $requested[$groupDto->groupId] = [
                'canWrite' => $groupDto->canWrite,
                'partial' => $groupDto->partial,
            ];
        }
        $requestedGroupIds = array_keys($requested);

        if (empty($requestedGroupIds)) {
            return [[], [], []];
        }

        $this->assertNoDuplicateGroupIds($requestedGroupIds);

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $groups = $groupRepository->findByIds(
            $requestedGroupIds,
            ["PARTIAL g.{id, name, publicKey, private}"]
        );
        $this->assertGroupsExist($requestedGroupIds, $groups);
        $this->assertNoPrivateGroups($groups);

        return [$requested, $requestedGroupIds, $groups];
    }

    /**
     * Resolve and validate user permissions.
     * Returns [requestedUsers (keyed by groupId), privateGroups, userIdToGroupId map]
     *
     * @param  PatchPermissionsWithPartialDto  $dto
     * @param  EntityManagerInterface  $entityManager
     *
     * @return array
     * @throws BadRequestHttpException
     */
    protected function resolveAndValidateUserPermissions(
        PatchPermissionsWithPartialDto $dto,
        EntityManagerInterface $entityManager
    ): array {
        if (empty($dto->userPermissions)) {
            return [[], [], []];
        }

        $requestedUserIds = array_map(fn($u) => $u->userId, $dto->userPermissions);
        $this->assertNoDuplicateUserIds($requestedUserIds);

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $privateGroups = $groupRepository->findPrivateGroupsByUserIds($requestedUserIds);

        $this->assertUsersExist($requestedUserIds, $privateGroups);

        // Build userId -> groupId map
        $userIdToGroupId = [];
        foreach ($privateGroups as $group) {
            foreach ($group->getGroupUsers() as $gu) {
                $userIdToGroupId[$gu->getUser()->getId()] = $group->getId();
            }
        }

        // Build requested permissions keyed by group ID
        $requested = [];
        foreach ($dto->userPermissions as $userDto) {
            $groupId = $userIdToGroupId[$userDto->userId];
            $requested[$groupId] = [
                'userId' => $userDto->userId,
                'canWrite' => $userDto->canWrite,
                'partial' => $userDto->partial ?? false,
            ];
        }

        return [$requested, $privateGroups, $userIdToGroupId];
    }

    /**
     * Build a group name map from the requested groups and the current relations.
     * Excludes private groups - they are managed via userPermissions.
     *
     * @param  object  $resource
     * @param  array  $requestedGroups
     *
     * @return array
     */
    protected function buildMergedGroupNameMap(object $resource, array $requestedGroups): array
    {
        // start with names from the requested Group entities
        $names = [];
        foreach ($requestedGroups as $g) {
            /** @var Group $g */
            $names[$g->getId()] = $g->getName();
        }

        // add names from current relations on the resource (covers removed groups)
        if ($resource instanceof Folder) {
            foreach ($resource->getFolderGroups() as $rel) {
                if ($rel->getGroup()->isPrivate()) {
                    continue; // Skip private groups
                }
                $gid = $rel->getGroup()->getId();
                $names[$gid] ??= $rel->getGroup()->getName();
            }
        } elseif ($resource instanceof Vault) {
            foreach ($resource->getGroupVaults() as $rel) {
                if ($rel->getGroup()->isPrivate()) {
                    continue; // Skip private groups
                }
                $gid = $rel->getGroup()->getId();
                $names[$gid] ??= $rel->getGroup()->getName();
            }
        } else {
            throw new InvalidArgumentException('Unsupported resource.');
        }

        return $names;
    }
}
