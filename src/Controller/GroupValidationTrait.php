<?php

/**
 * @author bsteffan
 * @since 2025-09-10
 */

namespace App\Controller;

use App\Entity\Group;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Only use this trait in controllers that are related to groups.
 */
trait GroupValidationTrait
{
    /**
     * Assert that no duplicate group IDs are provided.
     *
     * @param  string[]  $groupIds
     *
     * @return void
     * @throws BadRequestHttpException
     */
    protected function assertNoDuplicateGroupIds(array $groupIds): void
    {
        if (count($groupIds) !== count(array_unique($groupIds))) {
            throw new BadRequestHttpException("Duplicate group IDs.");
        }
    }

    /**
     * Assert that all group IDs are valid.
     *
     * @param  string[]  $groupIds
     * @param  Group[]  $groups
     *
     * @return void
     * @throws BadRequestHttpException
     */
    protected function assertGroupsExist(array $groupIds, array $groups): void
    {
        if (count($groups) !== count($groupIds)) {
            throw new BadRequestHttpException("Invalid group IDs.");
        }
    }

    /**
     * Assert that no private groups are provided.
     *
     * @param  Group[]  $groups
     *
     * @return void
     */
    protected function assertNoPrivateGroups(array $groups): void
    {
        if (array_any($groups, fn(Group $group) => $group->isPrivate())) {
            throw new BadRequestHttpException("Private groups are not allowed.");
        }
    }

    /**
     * Assert that no duplicate user IDs are provided.
     *
     * @param  string[]  $userIds
     *
     * @return void
     * @throws BadRequestHttpException
     */
    protected function assertNoDuplicateUserIds(array $userIds): void
    {
        if (count($userIds) !== count(array_unique($userIds))) {
            throw new BadRequestHttpException("Duplicate user IDs.");
        }
    }

    /**
     * Assert that all users exist by checking if their private groups were found.
     *
     * @param  string[]  $requestedUserIds
     * @param  Group[]  $privateGroups
     *
     * @return void
     * @throws BadRequestHttpException
     */
    protected function assertUsersExist(array $requestedUserIds, array $privateGroups): void
    {
        $foundUserIds = [];
        foreach ($privateGroups as $group) {
            foreach ($group->getGroupUsers() as $groupUser) {
                $foundUserIds[] = $groupUser->getUser()->getId();
            }
        }

        $missing = array_diff($requestedUserIds, $foundUserIds);
        if (!empty($missing)) {
            throw new BadRequestHttpException("Users not found: " . implode(', ', $missing));
        }
    }

    /**
     * Assert that at least one group or user permission is provided with write access.
     *
     * @param  array  $groupPermissions  Array of ['canWrite' => bool]
     * @param  array  $userPermissions  Array of ['canWrite' => bool]
     *
     * @return void
     * @throws BadRequestHttpException
     */
    protected function assertAtLeastOneWriteAccess(array $groupPermissions, array $userPermissions): void
    {
        $hasWriteAccess = array_any($groupPermissions, fn($p) => $p['canWrite'] === true)
                          || array_any($userPermissions, fn($p) => $p['canWrite'] === true);

        if (!$hasWriteAccess) {
            throw new BadRequestHttpException("At least one group or user must have write access.");
        }
    }

    /**
     * Assert that at least one group or user permission is provided.
     *
     * @param  array  $groups
     * @param  array  $userPermissions
     *
     * @return void
     * @throws BadRequestHttpException
     */
    protected function assertAtLeastOnePermission(array $groups, array $userPermissions): void
    {
        if (empty($groups) && empty($userPermissions)) {
            throw new BadRequestHttpException("At least one group or user permission must be provided.");
        }
    }
}
