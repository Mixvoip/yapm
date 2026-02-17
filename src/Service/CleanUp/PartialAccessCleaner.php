<?php

/**
 * @author bsteffan
 * @since 2025-09-24
 */

namespace App\Service\CleanUp;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

readonly class PartialAccessCleaner
{
    private Connection $db;

    /**
     * @param  EntityManagerInterface  $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->db = $entityManager->getConnection();
    }

    /**
     * Clean up partial access data.
     *  - No params → clean up all vaults and folders.
     *  - $vaultId only → clean up this vault and all its folders.
     *  - $vaultId + $groupIds → cleanup only for these groups in this vault/folders.
     *
     * @param  string|null  $vaultId
     * @param  string[]  $groupIds
     *
     * @return int
     * @throws Exception
     */
    public function cleanUp(?string $vaultId = null, array $groupIds = []): int
    {
        if (!is_null($vaultId)) {
            return $this->cleanUpVault($vaultId, $groupIds);
        }

        return $this->cleanUpAll();
    }

    /**
     * Clean up all vaults and folders.
     *
     * @return int
     * @throws Exception
     */
    private function cleanUpAll(): int
    {
        $deleted = 0;
        $deleted += $this->cleanUpFolders();
        $deleted += $this->cleanUpVaults();
        return $deleted;
    }

    /**
     * Clean up only a specific vault (and optionally specific groups).
     *
     * @param  string  $vaultId
     * @param  string[]  $groupIds
     *
     * @return int
     * @throws Exception
     */
    private function cleanUpVault(string $vaultId, array $groupIds = []): int
    {
        $deleted = 0;

        // cleanup vaults_groups for this vault
        $deleted += $this->cleanUpVaultGroups($vaultId, $groupIds);

        // cleanup folders_groups for all folders in this vault
        $folderIds = $this->db->executeQuery(
            'SELECT id FROM folders WHERE vault_id = :vid AND deleted_at IS NULL',
            ['vid' => $vaultId]
        )->fetchFirstColumn();

        foreach ($folderIds as $fid) {
            $deleted += $this->cleanUpFolderGroups($fid, $groupIds);
        }

        return $deleted;
    }

    /**
     * Clean up all folders.
     *
     * @return int
     * @throws Exception
     */
    private function cleanUpFolders(): int
    {
        $candidates = $this->db->executeQuery(
            "SELECT folder_id, group_id
               FROM folders_groups
              WHERE partial = 1 AND can_write = 0"
        )->fetchAllAssociative();

        $deleted = 0;
        foreach ($candidates as $row) {
            if (!$this->hasFolderJustification($row['folder_id'], $row['group_id'])) {
                $this->db->executeStatement(
                    "DELETE FROM folders_groups
                      WHERE folder_id = :fid AND group_id = :gid
                        AND partial = 1 AND can_write = 0",
                    ['fid' => $row['folder_id'], 'gid' => $row['group_id']]
                );
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Clean up all vaults.
     *
     * @return int
     * @throws Exception
     */
    private function cleanUpVaults(): int
    {
        $candidates = $this->db->executeQuery(
            "SELECT vault_id, group_id
               FROM groups_vaults
              WHERE partial = 1 AND can_write = 0"
        )->fetchAllAssociative();

        $deleted = 0;
        foreach ($candidates as $row) {
            if (!$this->hasVaultJustification($row['vault_id'], $row['group_id'])) {
                $this->db->executeStatement(
                    'DELETE FROM groups_vaults
                      WHERE vault_id = :vid AND group_id = :gid
                        AND partial = 1 AND can_write = 0',
                    ['vid' => $row['vault_id'], 'gid' => $row['group_id']]
                );
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Clean up specific groups in a vault.
     *
     * @param  string  $vaultId
     * @param  string[]  $groupIds
     *
     * @return int
     * @throws Exception
     */
    private function cleanUpVaultGroups(string $vaultId, array $groupIds = []): int
    {
        $params = ['vid' => $vaultId];
        $types = [];

        $sql = "SELECT vault_id, group_id
                  FROM groups_vaults
                 WHERE vault_id = :vid
                   AND partial = 1 AND can_write = 0";

        if (!empty($groupIds)) {
            $sql .= " AND group_id IN (:gids)";
            $params['gids'] = $groupIds;
            $types['gids'] = ArrayParameterType::STRING;
        }

        $candidates = $this->db->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $deleted = 0;
        foreach ($candidates as $row) {
            if (!$this->hasVaultJustification($row['vault_id'], $row['group_id'])) {
                $this->db->executeStatement(
                    "DELETE FROM groups_vaults
                      WHERE vault_id = :vid AND group_id = :gid
                        AND partial = 1 AND can_write = 0",
                    ['vid' => $row['vault_id'], 'gid' => $row['group_id']]
                );
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Clean up specific groups in a folder.
     *
     * @param  string  $folderId
     * @param  string[]  $groupIds
     *
     * @return int
     * @throws Exception
     */
    private function cleanUpFolderGroups(string $folderId, array $groupIds = []): int
    {
        $params = ['fid' => $folderId];
        $types = [];

        $sql = "SELECT folder_id, group_id
                  FROM folders_groups
                 WHERE folder_id = :fid
                   AND partial = 1 AND can_write = 0";

        if (!empty($groupIds)) {
            $sql .= " AND group_id IN (:gids)";
            $params['gids'] = $groupIds;
            $types['gids'] = ArrayParameterType::STRING;
        }

        $candidates = $this->db->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $deleted = 0;
        foreach ($candidates as $row) {
            if ($this->hasFolderJustification($row['folder_id'], $row['group_id']) === false) {
                $this->db->executeStatement(
                    "DELETE FROM folders_groups
                      WHERE folder_id = :fid AND group_id = :gid
                        AND partial = 1 AND can_write = 0",
                    ['fid' => $row['folder_id'], 'gid' => $row['group_id']]
                );
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Determine whether a folder justifies the partial access.
     *
     * @param  string  $folderId
     * @param  string  $groupId
     *
     * @return bool
     * @throws Exception
     */
    private function hasFolderJustification(string $folderId, string $groupId): bool
    {
        $folders = array_merge([$folderId], $this->getDescendantFolders($folderId));

        $hasPassword = (bool)$this->db->fetchOne(
            "SELECT 1
               FROM groups_passwords gp
               JOIN passwords p ON p.id = gp.password_id
              WHERE gp.group_id = :gid AND p.folder_id IN (:fids)
                AND p.deleted_at IS NULL
              LIMIT 1",
            ['gid' => $groupId, 'fids' => $folders],
            ['fids' => ArrayParameterType::STRING]
        );

        if ($hasPassword) {
            return true;
        }

        return (bool)$this->db->fetchOne(
            "SELECT 1
               FROM folders_groups fg
               JOIN folders f ON f.id = fg.folder_id
               JOIN groups g ON g.id = fg.group_id
              WHERE fg.folder_id IN (:fids) AND fg.group_id = :gid
                AND NOT (fg.partial = 1 AND fg.can_write = 0)
                AND f.deleted_at IS NULL
              LIMIT 1",
            ['gid' => $groupId, 'fids' => $folders],
            ['fids' => ArrayParameterType::STRING]
        );
    }

    /**
     * Determine whether a vault justifies the partial access.
     *
     * @param  string  $vaultId
     * @param  string  $groupId
     *
     * @return bool
     * @throws Exception
     */
    private function hasVaultJustification(string $vaultId, string $groupId): bool
    {
        $hasPassword = (bool)$this->db->fetchOne(
            "SELECT 1
               FROM groups_passwords gp
               JOIN passwords p ON p.id = gp.password_id
              WHERE gp.group_id = :gid AND p.vault_id = :vid
                AND p.deleted_at IS NULL
              LIMIT 1",
            ['gid' => $groupId, 'vid' => $vaultId]
        );

        if ($hasPassword) {
            return true;
        }

        return (bool)$this->db->fetchOne(
            "SELECT 1
                FROM folders_groups fg
                JOIN folders f ON f.id = fg.folder_id
                WHERE fg.group_id = :gid AND f.vault_id = :vid
                AND NOT (fg.partial = 1 AND fg.can_write = 0)
                AND f.deleted_at IS NULL
            LIMIT 1",
            ['gid' => $groupId, 'vid' => $vaultId]
        );
    }

    /**
     * Get all descendant folders of a folder.
     *
     * @param  string  $folderId
     *
     * @return array
     * @throws Exception
     */
    private function getDescendantFolders(string $folderId): array
    {
        $all = [];
        $front = [$folderId];

        while (!empty($front)) {
            $children = $this->db->executeQuery(
                "SELECT id FROM folders WHERE parent_folder_id IN (:ids) AND deleted_at IS NULL",
                ['ids' => $front],
                ['ids' => ArrayParameterType::STRING]
            )->fetchFirstColumn();

            $children = array_diff($children, $all);
            if ($children === []) {
                break;
            }

            $all = array_merge($all, $children);
            $front = $children;
        }

        return $all;
    }

    /**
     * Promote partial access records to full access when justified.
     *  - No params → promote all vaults and folders.
     *  - $vaultId only → promote this vault and all its folders.
     *  - $vaultId + $groupIds → promote only for these groups in this vault/folders.
     *
     * @param  string|null  $vaultId
     * @param  string[]  $groupIds
     *
     * @return int Number of records promoted
     * @throws Exception
     */
    public function promote(?string $vaultId = null, array $groupIds = []): int
    {
        if (!is_null($vaultId)) {
            return $this->promoteVault($vaultId, $groupIds);
        }

        return $this->promoteAll();
    }

    /**
     * Promote all vaults and folders.
     *
     * @return int
     * @throws Exception
     */
    private function promoteAll(): int
    {
        $promoted = 0;

        // Process folders bottom-up across all vaults
        $foldersByDepth = $this->getAllFoldersBottomUp();
        $promoted += $this->promoteFolders($foldersByDepth);

        // Then process vaults
        $promoted += $this->promoteAllVaults();

        return $promoted;
    }

    /**
     * Promote only a specific vault (and optionally specific groups).
     *
     * @param  string  $vaultId
     * @param  string[]  $groupIds
     *
     * @return int
     * @throws Exception
     */
    private function promoteVault(string $vaultId, array $groupIds = []): int
    {
        $promoted = 0;

        // Process folders bottom-up within this vault
        $foldersByDepth = $this->getFoldersBottomUp($vaultId);
        $promoted += $this->promoteFolders($foldersByDepth, $groupIds);

        // Then process the vault itself
        $promoted += $this->promoteVaultGroups($vaultId, $groupIds);

        return $promoted;
    }

    /**
     * Promote folders from bottom to top (deepest first).
     *
     * @param  string[]  $folderIds  Folder IDs sorted bottom-up (deepest first)
     * @param  string[]  $groupIds  Optional filter for specific groups
     *
     * @return int
     * @throws Exception
     */
    private function promoteFolders(array $folderIds, array $groupIds = []): int
    {
        if (empty($folderIds)) {
            return 0;
        }

        $promoted = 0;

        foreach ($folderIds as $folderId) {
            $params = ['fid' => $folderId];
            $types = [];

            $sql = "SELECT folder_id, group_id
                      FROM folders_groups
                     WHERE folder_id = :fid
                       AND partial = 1";

            if (!empty($groupIds)) {
                $sql .= " AND group_id IN (:gids)";
                $params['gids'] = $groupIds;
                $types['gids'] = ArrayParameterType::STRING;
            }

            $candidates = $this->db->executeQuery($sql, $params, $types)->fetchAllAssociative();

            foreach ($candidates as $row) {
                if ($this->canPromoteFolder($row['folder_id'], $row['group_id'])) {
                    $this->db->executeStatement(
                        "UPDATE folders_groups
                            SET partial = 0
                          WHERE folder_id = :fid AND group_id = :gid",
                        ['fid' => $row['folder_id'], 'gid' => $row['group_id']]
                    );
                    $promoted++;
                }
            }
        }

        return $promoted;
    }

    /**
     * Promote all vaults.
     *
     * @return int
     * @throws Exception
     */
    private function promoteAllVaults(): int
    {
        $candidates = $this->db->executeQuery(
            "SELECT vault_id, group_id
               FROM groups_vaults
              WHERE partial = 1"
        )->fetchAllAssociative();

        $promoted = 0;
        foreach ($candidates as $row) {
            if ($this->canPromoteVault($row['vault_id'], $row['group_id'])) {
                $this->db->executeStatement(
                    "UPDATE groups_vaults
                        SET partial = 0
                      WHERE vault_id = :vid AND group_id = :gid",
                    ['vid' => $row['vault_id'], 'gid' => $row['group_id']]
                );
                $promoted++;
            }
        }

        return $promoted;
    }

    /**
     * Promote specific groups in a vault.
     *
     * @param  string  $vaultId
     * @param  string[]  $groupIds
     *
     * @return int
     * @throws Exception
     */
    private function promoteVaultGroups(string $vaultId, array $groupIds = []): int
    {
        $params = ['vid' => $vaultId];
        $types = [];

        $sql = "SELECT vault_id, group_id
                  FROM groups_vaults
                 WHERE vault_id = :vid
                   AND partial = 1";

        if (!empty($groupIds)) {
            $sql .= " AND group_id IN (:gids)";
            $params['gids'] = $groupIds;
            $types['gids'] = ArrayParameterType::STRING;
        }

        $candidates = $this->db->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $promoted = 0;
        foreach ($candidates as $row) {
            if ($this->canPromoteVault($row['vault_id'], $row['group_id'])) {
                $this->db->executeStatement(
                    "UPDATE groups_vaults
                        SET partial = 0
                      WHERE vault_id = :vid AND group_id = :gid",
                    ['vid' => $row['vault_id'], 'gid' => $row['group_id']]
                );
                $promoted++;
            }
        }

        return $promoted;
    }

    /**
     * Check if a folder can be promoted from partial to full access.
     * A folder can be promoted if the group has access to ALL passwords
     * directly in the folder AND all child folders have partial=false.
     *
     * @param  string  $folderId
     * @param  string  $groupId
     *
     * @return bool
     * @throws Exception
     */
    private function canPromoteFolder(string $folderId, string $groupId): bool
    {
        // Check for blocking items:
        // 1. Passwords without group access
        // 2. Child folders without full (non-partial) access
        $blockingItem = $this->db->fetchOne(
            "SELECT 1 FROM (
                -- Passwords without group access
                SELECT 1 AS blocker
                FROM passwords p
                WHERE p.folder_id = :fid
                  AND p.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM groups_passwords gp
                      WHERE gp.password_id = p.id AND gp.group_id = :gid
                  )

                UNION ALL

                -- Child folders without full (non-partial) access
                SELECT 1 AS blocker
                FROM folders f
                LEFT JOIN folders_groups fg ON fg.folder_id = f.id AND fg.group_id = :gid
                WHERE f.parent_folder_id = :fid
                  AND f.deleted_at IS NULL
                  AND (fg.folder_id IS NULL OR fg.partial = 1)
            ) AS blockers
            LIMIT 1",
            ['fid' => $folderId, 'gid' => $groupId]
        );

        return !$blockingItem;
    }

    /**
     * Check if a vault can be promoted from partial to full access.
     * A vault can be promoted if the group has access to ALL root-level
     * passwords AND all root-level folders have partial=false.
     *
     * @param  string  $vaultId
     * @param  string  $groupId
     *
     * @return bool
     * @throws Exception
     */
    private function canPromoteVault(string $vaultId, string $groupId): bool
    {
        // Check for blocking items:
        // 1. Root-level passwords (folder_id IS NULL) without group access
        // 2. Root-level folders without full (non-partial) access
        $blockingItem = $this->db->fetchOne(
            "SELECT 1 FROM (
                -- Root-level passwords without group access
                SELECT 1 AS blocker
                FROM passwords p
                WHERE p.vault_id = :vid
                  AND p.folder_id IS NULL
                  AND p.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM groups_passwords gp
                      WHERE gp.password_id = p.id AND gp.group_id = :gid
                  )

                UNION ALL

                -- Root-level folders without full (non-partial) access
                SELECT 1 AS blocker
                FROM folders f
                LEFT JOIN folders_groups fg ON fg.folder_id = f.id AND fg.group_id = :gid
                WHERE f.vault_id = :vid
                  AND f.parent_folder_id IS NULL
                  AND f.deleted_at IS NULL
                  AND (fg.folder_id IS NULL OR fg.partial = 1)
            ) AS blockers
            LIMIT 1",
            ['vid' => $vaultId, 'gid' => $groupId]
        );

        return !$blockingItem;
    }

    /**
     * Get all folders in a vault sorted bottom-up (deepest first).
     *
     * @param  string  $vaultId
     *
     * @return string[]
     * @throws Exception
     */
    private function getFoldersBottomUp(string $vaultId): array
    {
        $folders = $this->db->executeQuery(
            "SELECT id, parent_folder_id FROM folders
             WHERE vault_id = :vid AND deleted_at IS NULL",
            ['vid' => $vaultId]
        )->fetchAllAssociative();

        return $this->sortFoldersByDepthDescending($folders);
    }

    /**
     * Get all folders across all vaults sorted bottom-up (deepest first).
     *
     * @return string[]
     * @throws Exception
     */
    private function getAllFoldersBottomUp(): array
    {
        $folders = $this->db->executeQuery(
            "SELECT id, parent_folder_id FROM folders WHERE deleted_at IS NULL"
        )->fetchAllAssociative();

        return $this->sortFoldersByDepthDescending($folders);
    }

    /**
     * Sort folders by depth descending (deepest first) for bottom-up processing.
     *
     * @param  array  $folders  Array of ['id' => string, 'parent_folder_id' => string|null]
     *
     * @return string[]  Folder IDs sorted deepest first
     */
    private function sortFoldersByDepthDescending(array $folders): array
    {
        if (empty($folders)) {
            return [];
        }

        // Build parent lookup
        $parentMap = [];
        foreach ($folders as $folder) {
            $parentMap[$folder['id']] = $folder['parent_folder_id'];
        }

        // Calculate depth for each folder
        $depths = [];
        foreach ($folders as $folder) {
            $depth = 0;
            $currentId = $folder['id'];

            while (isset($parentMap[$currentId]) && !is_null($parentMap[$currentId])) {
                $depth++;
                $currentId = $parentMap[$currentId];

                // Prevent infinite loops in case of circular references
                if ($depth > 1000) {
                    break;
                }
            }

            $depths[$folder['id']] = $depth;
        }

        // Sort by depth descending (deepest first)
        arsort($depths);

        return array_keys($depths);
    }
}
