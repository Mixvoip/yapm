<?php

/**
 * @author bsteffan
 * @since 2026-01-16
 */

namespace App\Controller;

use App\Controller\Dto\GroupPermissionDto;
use App\Controller\Dto\GroupPermissionWithPartialDto;
use App\Controller\Dto\UserPermissionDto;
use App\Controller\Dto\UserPermissionWithPartialDto;
use App\Entity\Group;
use App\Repository\GroupRepository;

/**
 * Trait for processing group and user permission DTOs.
 * Use this trait in controllers that handle permission assignment.
 */
trait PermissionProcessingTrait
{
    /**
     * Process group permission DTOs and return groups with permissions map.
     *
     * @param  GroupPermissionDto[]|GroupPermissionWithPartialDto[]  $groupDtos
     * @param  GroupRepository  $groupRepository
     * @param  string[]|null  $partialFields  Fields to select for partial loading (default: id, name, private)
     *
     * @return array{groups: Group[], groupPermissions: array<string, bool>}
     */
    protected function processGroupPermissions(
        array $groupDtos,
        GroupRepository $groupRepository,
        ?array $partialFields = null
    ): array {
        if (empty($groupDtos)) {
            return ['groups' => [], 'groupPermissions' => []];
        }

        $groupIds = array_map(fn($g) => $g->groupId, $groupDtos);
        $this->assertNoDuplicateGroupIds($groupIds);

        $partialFields ??= ["PARTIAL g.{id, name, private}"];
        $groups = $groupRepository->findByIds($groupIds, $partialFields);
        $this->assertGroupsExist($groupIds, $groups);
        $this->assertNoPrivateGroups($groups);

        $groupPermissions = [];
        foreach ($groupDtos as $groupDto) {
            $groupPermissions[$groupDto->groupId] = $groupDto->canWrite;
        }

        return ['groups' => $groups, 'groupPermissions' => $groupPermissions];
    }

    /**
     * Process user permission DTOs and return private groups with permissions map.
     *
     * @param  UserPermissionDto[]|UserPermissionWithPartialDto[]  $userDtos
     * @param  GroupRepository  $groupRepository
     *
     * @return array{privateGroups: Group[], userPermissions: array<string, bool>, userIdToGroupId: array<string, string>}
     */
    protected function processUserPermissions(
        array $userDtos,
        GroupRepository $groupRepository
    ): array {
        if (empty($userDtos)) {
            return ['privateGroups' => [], 'userPermissions' => [], 'userIdToGroupId' => []];
        }

        $requestedUserIds = array_map(fn($u) => $u->userId, $userDtos);
        $this->assertNoDuplicateUserIds($requestedUserIds);

        $privateGroups = $groupRepository->findPrivateGroupsByUserIds($requestedUserIds);
        $this->assertUsersExist($requestedUserIds, $privateGroups);

        // Build userId -> groupId map
        $userIdToGroupId = [];
        foreach ($privateGroups as $group) {
            foreach ($group->getGroupUsers() as $gu) {
                $userIdToGroupId[$gu->getUser()->getId()] = $group->getId();
            }
        }

        // Build user permissions map (groupId -> canWrite)
        $userPermissions = [];
        foreach ($userDtos as $userDto) {
            $groupId = $userIdToGroupId[$userDto->userId];
            $userPermissions[$groupId] = $userDto->canWrite;
        }

        return [
            'privateGroups' => $privateGroups,
            'userPermissions' => $userPermissions,
            'userIdToGroupId' => $userIdToGroupId,
        ];
    }
}
