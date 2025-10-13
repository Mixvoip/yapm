<?php

/**
 * @author bsteffan
 * @since 2025-07-22
 */

namespace App\Controller;

use App\Entity\Folder;
use App\Entity\FoldersGroup;
use App\Entity\Group;
use App\Entity\GroupsVault;
use App\Entity\Vault;
use Doctrine\ORM\EntityManagerInterface;

trait GroupInheritanceTrait
{
    /**
     * Propagate groups to the parent folder.
     *
     * @param  Folder  $folder
     * @param  Group[]  $groups
     * @param  string  $userIdentifier
     * @param  EntityManagerInterface  $entityManager
     *
     * @return void
     */
    private function addMissingGroupsToParentFolder(
        Folder $folder,
        array $groups,
        string $userIdentifier,
        EntityManagerInterface $entityManager
    ): void {
        $existingGroupIds = $folder->getGroupIds();
        $groupIds = array_map(fn($g) => $g->getId(), $groups);
        $extraGroupIds = array_diff($groupIds, $existingGroupIds);

        foreach ($groups as $group) {
            if (in_array($group->getId(), $extraGroupIds)) {
                $folderGroup = new FoldersGroup()->setFolder($folder)
                                                 ->setGroup($group)
                                                 ->setPartial(true)
                                                 ->setCreatedBy($userIdentifier);

                $folder->addFolderGroup($folderGroup);
                $entityManager->persist($folderGroup);
            }
        }

        if (!is_null($folder->getParent())) {
            $this->addMissingGroupsToParentFolder(
                $folder->getParent(),
                $groups,
                $userIdentifier,
                $entityManager
            );
        }
    }

    /**
     * Propagate groups to the vault.
     *
     * @param  Vault  $vault
     * @param  Group[]  $groups
     * @param  string  $userIdentifier
     * @param  EntityManagerInterface  $entityManager
     *
     * @return void
     */
    private function addMissingGroupsToVault(
        Vault $vault,
        array $groups,
        string $userIdentifier,
        EntityManagerInterface $entityManager
    ): void {
        $vaultGroupIds = $vault->getGroupIds();
        $groupIds = array_map(fn($g) => $g->getId(), $groups);
        $extraGroupIds = array_diff($groupIds, $vaultGroupIds);

        foreach ($groups as $group) {
            if (in_array($group->getId(), $extraGroupIds)) {
                $groupVault = new GroupsVault()->setVault($vault)
                                               ->setGroup($group)
                                               ->setPartial(true)
                                               ->setCreatedBy($userIdentifier);

                $entityManager->persist($groupVault);
            }
        }
    }

    /**
     * Mark parent folder groups as partial if the child excludes the groups.
     *
     * @param  Folder  $folder
     * @param  string[]  $excludedGroupIds
     * @param  string  $userIdentifier
     * @param  EntityManagerInterface  $entityManager
     *
     * @return void
     */
    private function markParentFolderAsPartial(
        Folder $folder,
        array $excludedGroupIds,
        string $userIdentifier,
        EntityManagerInterface $entityManager
    ): void {
        if (empty($excludedGroupIds)) {
            return;
        }

        $folderGroups = $folder->getFolderGroups();
        foreach ($folderGroups as $fg) {
            $gid = $fg->getGroup()->getId();
            if (in_array($gid, $excludedGroupIds, true) && !$fg->isPartial()) {
                $fg->setPartial(true)
                   ->setUpdatedBy($userIdentifier);
                $entityManager->persist($fg);
            }
        }

        if (!is_null($folder->getParent())) {
            $this->markParentFolderAsPartial(
                $folder->getParent(),
                $excludedGroupIds,
                $userIdentifier,
                $entityManager
            );
        }
    }

    /**
     * Mark vault groups as partial if the child excludes the groups.
     *
     * @param  Vault  $vault
     * @param  string[]  $excludedGroupIds
     * @param  string  $userIdentifier
     * @param  EntityManagerInterface  $entityManager
     *
     * @return void
     */
    private function markVaultAsPartial(
        Vault $vault,
        array $excludedGroupIds,
        string $userIdentifier,
        EntityManagerInterface $entityManager
    ): void {
        if (empty($excludedGroupIds)) {
            return;
        }

        $groupVaults = $vault->getGroupVaults();
        foreach ($groupVaults as $groupVault) {
            $gid = $groupVault->getGroup()->getId();
            if (in_array($gid, $excludedGroupIds, true) && !$groupVault->isPartial()) {
                $groupVault->setPartial(true)
                           ->setUpdatedBy($userIdentifier);
                $entityManager->persist($groupVault);
            }
        }
    }
}
