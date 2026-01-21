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
            'SELECT id FROM folders WHERE vault_id = :vid',
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
               JOIN groups g ON g.id = fg.group_id
              WHERE fg.folder_id IN (:fids) AND fg.group_id = :gid
                AND NOT (fg.partial = 1 AND fg.can_write = 0)
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
                "SELECT id FROM folders WHERE parent_folder_id IN (:ids)",
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
}
