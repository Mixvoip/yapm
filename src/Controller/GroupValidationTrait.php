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
}
