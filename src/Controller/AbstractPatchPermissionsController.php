<?php

/**
 * @author bsteffan
 * @since 2025-09-10
 */

namespace App\Controller;

use App\Controller\Dto\GroupPermissionWithPartialDto;
use App\Controller\Dto\PatchPermissionsWithPartialDto;
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
     * Enforce at least one group to have write access.
     *
     * @param  array  $requested
     *
     * @return void
     * @throws BadRequestHttpException
     */
    protected function enforceWriteAccess(array $requested): void
    {
        if (array_any($requested, fn($groupPermissions) => $groupPermissions['canWrite'] === true)) {
            return;
        }

        throw new BadRequestHttpException("At least one group must have write access.");
    }

    /**
     * Check if the request is a no-op.
     *
     * @param  object  $resource
     * @param  array  $requested
     * @param  bool  $fullWrite
     *
     * @return array
     * @throws InvalidArgumentException
     */
    protected function buildSnapShots(object $resource, array $requested, bool $fullWrite): array
    {
        $current = [];

        if ($resource instanceof Folder) {
            foreach ($resource->getFolderGroups() as $fg) {
                $current[$fg->getGroup()->getId()] = [
                    'canWrite' => $fg->canWrite(),
                    'partial' => $fg->isPartial(),
                ];
            }
        } elseif ($resource instanceof Vault) {
            foreach ($resource->getGroupVaults() as $gv) {
                $current[$gv->getGroup()->getId()] = [
                    'canWrite' => $gv->canWrite(),
                    'partial' => $gv->isPartial(),
                ];
            }
        } else {
            throw new InvalidArgumentException("Unsupported resource type.");
        }

        $target = array_map(
            function ($groupPermissions) use ($fullWrite) {
                return [
                    'canWrite' => $groupPermissions['canWrite'],
                    'partial' => $groupPermissions['partial'] || !$fullWrite,
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
     * @param  string  $userIdentifier
     *
     * @return ShareProcess
     */
    protected function buildShareProcess(
        TargetType $targetType,
        AuditableEntityInterface $scope,
        PatchPermissionsWithPartialDto $dto,
        array $groups,
        string $userIdentifier
    ): ShareProcess {
        return new ShareProcess()->setTargetType($targetType)
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
    }

    /**
     * Resolve the dto and validate the groups.
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
        $this->assertNoDuplicateGroupIds($requestedGroupIds);

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $groups = $groupRepository->findByIds(
            $requestedGroupIds,
            ["PARTIAL g.{id, name, publicKey}"]
        );
        $this->assertGroupsExist($requestedGroupIds, $groups);
        $this->assertNoPrivateGroups($groups);

        $this->enforceWriteAccess($requested);

        return [$requested, $requestedGroupIds, $groups];
    }

    /**
     * Build a group name map from the requested groups and the current relations.
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
                $gid = $rel->getGroup()->getId();
                $names[$gid] ??= $rel->getGroup()->getName();
            }
        } elseif ($resource instanceof Vault) {
            foreach ($resource->getGroupVaults() as $rel) {
                $gid = $rel->getGroup()->getId();
                $names[$gid] ??= $rel->getGroup()->getName();
            }
        } else {
            throw new InvalidArgumentException('Unsupported resource.');
        }

        return $names;
    }
}
