<?php

/**
 * @author bsteffan
 * @since 2025-07-23
 */

namespace App\Entity;

interface PermissionAwareEntityInterface
{
    /**
     * Checks if the user has read access to this entity.
     *
     * @param  string[]  $groupIds
     *
     * @return bool
     */
    public function hasReadPermission(array $groupIds): bool;

    /**
     * Checks if the user has write access to this entity.
     *
     * @param  string[]  $groupIds
     *
     * @return bool
     */
    public function hasWritePermission(array $groupIds): bool;
}
