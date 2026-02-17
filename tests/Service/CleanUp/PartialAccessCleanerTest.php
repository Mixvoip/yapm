<?php

/**
 * @author bsteffan
 * @since 2026-01-29
 */

namespace App\Tests\Service\CleanUp;

use App\Service\CleanUp\PartialAccessCleaner;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PartialAccessCleanerTest extends KernelTestCase
{
    private PartialAccessCleaner $cleaner;
    private Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->cleaner = $container->get(PartialAccessCleaner::class);

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $this->db = $em->getConnection();
    }

    /**
     * Test that promote() upgrades a folder from partial to full access
     * when all its children (passwords) have group access.
     *
     * @throws Exception
     */
    public function testPromoteFolderWhenAllPasswordsHaveAccess(): void
    {
        // Use the 'main' folder which has passwords and subfolders
        $folderId = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000000'; // main folder
        $adminGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000000'; // admin group

        // First, set the folder to partial for admin group
        $this->db->executeStatement(
            "UPDATE folders_groups SET partial = 1 WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $folderId, 'gid' => $adminGroupId]
        );

        // Also set child folders (prod, staging) to partial=0 so they don't block promotion
        $this->db->executeStatement(
            "UPDATE folders_groups SET partial = 0
             WHERE folder_id IN ('aaaaaaaa-bbbb-cccc-dddd-fd0000000001', 'aaaaaaaa-bbbb-cccc-dddd-fd0000000002')
             AND group_id = :gid",
            ['gid' => $adminGroupId]
        );

        // Verify the folder is marked as partial
        $partialBefore = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $folderId, 'gid' => $adminGroupId]
        );
        $this->assertEquals(1, $partialBefore, 'Folder should be partial before promote');

        // Run promote
        $promoted = $this->cleaner->promote();

        // Verify the folder is no longer partial
        $partialAfter = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $folderId, 'gid' => $adminGroupId]
        );
        $this->assertEquals(0, $partialAfter, 'Folder should not be partial after promote');
        $this->assertGreaterThanOrEqual(1, $promoted, 'At least one record should be promoted');
    }

    /**
     * Test that promote() does NOT upgrade a folder when a child password lacks group access.
     *
     * @throws Exception
     */
    public function testDoesNotPromoteFolderWhenPasswordMissingAccess(): void
    {
        // Use the 'staging' folder
        $folderId = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000002'; // staging folder
        $adminGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000000'; // admin group

        // Get a password in staging folder
        $passwordId = $this->db->fetchOne(
            "SELECT id FROM passwords WHERE folder_id = :fid LIMIT 1",
            ['fid' => $folderId]
        );
        $this->assertNotFalse($passwordId, 'Should have a password in staging folder');

        // Remove admin group's access to that password
        $this->db->executeStatement(
            "DELETE FROM groups_passwords WHERE password_id = :pid AND group_id = :gid",
            ['pid' => $passwordId, 'gid' => $adminGroupId]
        );

        // Set the folder to partial
        $this->db->executeStatement(
            "UPDATE folders_groups SET partial = 1 WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $folderId, 'gid' => $adminGroupId]
        );

        // Run promote
        $this->cleaner->promote();

        // Verify the folder is still partial (should NOT be promoted)
        $partialAfter = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $folderId, 'gid' => $adminGroupId]
        );
        $this->assertEquals(1, $partialAfter, 'Folder should remain partial when a password lacks access');
    }

    /**
     * Test that promote() does NOT upgrade a folder when a child folder cannot be promoted.
     * A child folder cannot be promoted if it has a password without group access.
     *
     * @throws Exception
     */
    public function testDoesNotPromoteFolderWhenChildFolderCannotBePromoted(): void
    {
        // Use the 'main' folder which has child folders (prod, staging)
        $parentFolderId = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000000'; // main folder
        $childFolderId = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000001'; // prod folder (child of main)
        $adminGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000000';

        // Get a password in the child folder
        $passwordId = $this->db->fetchOne(
            "SELECT id FROM passwords WHERE folder_id = :fid LIMIT 1",
            ['fid' => $childFolderId]
        );
        $this->assertNotFalse($passwordId, 'Should have a password in prod folder');

        // Remove admin's access to that password (this will block child folder from being promoted)
        $this->db->executeStatement(
            "DELETE FROM groups_passwords WHERE password_id = :pid AND group_id = :gid",
            ['pid' => $passwordId, 'gid' => $adminGroupId]
        );

        // Set parent folder to partial
        $this->db->executeStatement(
            "UPDATE folders_groups SET partial = 1 WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $parentFolderId, 'gid' => $adminGroupId]
        );

        // Set child folder to partial as well
        $this->db->executeStatement(
            "UPDATE folders_groups SET partial = 1 WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $childFolderId, 'gid' => $adminGroupId]
        );

        // Run promote
        $this->cleaner->promote();

        // Verify child folder is still partial (password missing access)
        $childPartialAfter = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $childFolderId, 'gid' => $adminGroupId]
        );
        $this->assertEquals(1, $childPartialAfter, 'Child folder should remain partial when a password lacks access');

        // Verify the parent folder is still partial (child is blocking)
        $partialAfter = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $parentFolderId, 'gid' => $adminGroupId]
        );
        $this->assertEquals(1, $partialAfter, 'Parent folder should remain partial when child folder cannot be promoted');
    }

    /**
     * Test that promote() upgrades a vault when all root-level items have full access.
     *
     * @throws Exception
     */
    public function testPromoteVaultWhenAllRootItemsHaveAccess(): void
    {
        $vaultId = '1aaaaaaa-bbbb-cccc-dddd-000000000000'; // Development vault
        $adminGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000000';

        // Set all root folders to partial=0
        $this->db->executeStatement(
            "UPDATE folders_groups fg
             JOIN folders f ON f.id = fg.folder_id
             SET fg.partial = 0
             WHERE f.vault_id = :vid AND f.parent_folder_id IS NULL AND fg.group_id = :gid",
            ['vid' => $vaultId, 'gid' => $adminGroupId]
        );

        // Set vault to partial
        $this->db->executeStatement(
            "UPDATE groups_vaults SET partial = 1 WHERE vault_id = :vid AND group_id = :gid",
            ['vid' => $vaultId, 'gid' => $adminGroupId]
        );

        // Run promote
        $promoted = $this->cleaner->promote();

        // Verify vault is no longer partial
        $partialAfter = $this->db->fetchOne(
            "SELECT partial FROM groups_vaults WHERE vault_id = :vid AND group_id = :gid",
            ['vid' => $vaultId, 'gid' => $adminGroupId]
        );
        $this->assertEquals(0, $partialAfter, 'Vault should not be partial after promote');
        $this->assertGreaterThanOrEqual(1, $promoted, 'At least one record should be promoted');
    }

    /**
     * Test that promote() does NOT upgrade a vault when a root folder cannot be promoted.
     *
     * @throws Exception
     */
    public function testDoesNotPromoteVaultWhenRootFolderCannotBePromoted(): void
    {
        $vaultId = '1aaaaaaa-bbbb-cccc-dddd-000000000000'; // Development vault
        $rootFolderId = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000000'; // main (root folder)
        $adminGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000000';

        // Get a password directly in the root folder
        $passwordId = $this->db->fetchOne(
            "SELECT id FROM passwords WHERE folder_id = :fid LIMIT 1",
            ['fid' => $rootFolderId]
        );
        $this->assertNotFalse($passwordId, 'Should have a password in main folder');

        // Remove admin's access to that password (this will block root folder from being promoted)
        $this->db->executeStatement(
            "DELETE FROM groups_passwords WHERE password_id = :pid AND group_id = :gid",
            ['pid' => $passwordId, 'gid' => $adminGroupId]
        );

        // Set root folder to partial
        $this->db->executeStatement(
            "UPDATE folders_groups SET partial = 1 WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $rootFolderId, 'gid' => $adminGroupId]
        );

        // Set vault to partial
        $this->db->executeStatement(
            "UPDATE groups_vaults SET partial = 1 WHERE vault_id = :vid AND group_id = :gid",
            ['vid' => $vaultId, 'gid' => $adminGroupId]
        );

        // Run promote
        $this->cleaner->promote();

        // Verify root folder is still partial (password missing access)
        $rootFolderPartial = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $rootFolderId, 'gid' => $adminGroupId]
        );
        $this->assertEquals(1, $rootFolderPartial, 'Root folder should remain partial when a password lacks access');

        // Verify vault is still partial (root folder blocking)
        $partialAfter = $this->db->fetchOne(
            "SELECT partial FROM groups_vaults WHERE vault_id = :vid AND group_id = :gid",
            ['vid' => $vaultId, 'gid' => $adminGroupId]
        );
        $this->assertEquals(1, $partialAfter, 'Vault should remain partial when root folder cannot be promoted');
    }

    /**
     * Test that promote() processes folders bottom-up (deepest first).
     *
     * @throws Exception
     */
    public function testPromoteProcessesBottomUp(): void
    {
        // Use nexus (parent) and yapm (child of nexus)
        $parentFolderId = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000003'; // nexus
        $childFolderId = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000004'; // yapm (child of nexus)
        $adminGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000000';

        // Set both folders to partial
        $this->db->executeStatement(
            "UPDATE folders_groups SET partial = 1 WHERE folder_id IN (:fids) AND group_id = :gid",
            ['fids' => [$parentFolderId, $childFolderId], 'gid' => $adminGroupId],
            ['fids' => ArrayParameterType::STRING]
        );

        // Run promote - should process child first, then parent can be promoted
        $promoted = $this->cleaner->promote();

        // Both should be promoted (child first allows parent to be promoted)
        $childPartial = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $childFolderId, 'gid' => $adminGroupId]
        );
        $parentPartial = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $parentFolderId, 'gid' => $adminGroupId]
        );

        $this->assertEquals(0, $childPartial, 'Child folder should be promoted');
        $this->assertEquals(0, $parentPartial, 'Parent folder should be promoted after child');
        $this->assertGreaterThanOrEqual(2, $promoted, 'Both folders should be promoted');
    }

    /**
     * Test that promote() excludes soft-deleted items.
     *
     * @throws Exception
     */
    public function testPromoteExcludesSoftDeletedItems(): void
    {
        $folderId = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000005'; // cdrs folder
        $adminGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000000';

        // Set folder to partial
        $this->db->executeStatement(
            "UPDATE folders_groups SET partial = 1 WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $folderId, 'gid' => $adminGroupId]
        );

        // Get a password in this folder
        $passwordId = $this->db->fetchOne(
            "SELECT id FROM passwords WHERE folder_id = :fid LIMIT 1",
            ['fid' => $folderId]
        );

        if ($passwordId) {
            // Remove admin's access to this password but soft-delete the password
            $this->db->executeStatement(
                "DELETE FROM groups_passwords WHERE password_id = :pid AND group_id = :gid",
                ['pid' => $passwordId, 'gid' => $adminGroupId]
            );
            $this->db->executeStatement(
                "UPDATE passwords SET deleted_at = NOW() WHERE id = :pid",
                ['pid' => $passwordId]
            );

            // Run promote - should succeed because the blocking password is soft-deleted
            $this->cleaner->promote();

            // Verify folder is promoted (soft-deleted password doesn't block)
            $partialAfter = $this->db->fetchOne(
                "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
                ['fid' => $folderId, 'gid' => $adminGroupId]
            );
            $this->assertEquals(0, $partialAfter, 'Folder should be promoted when blocking password is soft-deleted');
        } else {
            $this->markTestSkipped('No password found in cdrs folder');
        }
    }

    /**
     * Test that cleanUp() excludes soft-deleted folders.
     *
     * @throws Exception
     */
    public function testCleanUpExcludesSoftDeletedFolders(): void
    {
        $vaultId = '1aaaaaaa-bbbb-cccc-dddd-000000000000';
        $adminGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000000';

        // Create a test folder that is soft-deleted
        $testFolderId = 'test0000-bbbb-cccc-dddd-000000000001';
        $this->db->executeStatement(
            "INSERT INTO folders (id, vault_id, name, created_by, created_at, deleted_at)
             VALUES (:id, :vid, 'TestDeleted', 'test', NOW(), NOW())",
            ['id' => $testFolderId, 'vid' => $vaultId]
        );

        // Add a partial folder_group entry for this deleted folder
        $this->db->executeStatement(
            "INSERT INTO folders_groups (folder_id, group_id, can_write, partial, created_by, created_at)
             VALUES (:fid, :gid, 0, 1, 'test', NOW())",
            ['fid' => $testFolderId, 'gid' => $adminGroupId]
        );

        // Run cleanUp for this vault
        $deleted = $this->cleaner->cleanUp($vaultId, [$adminGroupId]);

        // The soft-deleted folder should not be processed (it's filtered by deleted_at IS NULL)
        // Verify the folders_groups entry still exists
        $exists = $this->db->fetchOne(
            "SELECT 1 FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $testFolderId, 'gid' => $adminGroupId]
        );

        // Clean up test data
        $this->db->executeStatement(
            "DELETE FROM folders_groups WHERE folder_id = :fid",
            ['fid' => $testFolderId]
        );
        $this->db->executeStatement(
            "DELETE FROM folders WHERE id = :fid",
            ['fid' => $testFolderId]
        );

        // The entry should still exist because the folder is soft-deleted and was not processed
        $this->assertNotFalse($exists, 'Soft-deleted folder should not be processed by cleanUp');
    }

    /**
     * Test promote() with specific vault and group IDs.
     *
     * @throws Exception
     */
    public function testPromoteWithSpecificVaultAndGroups(): void
    {
        $vaultId = '1aaaaaaa-bbbb-cccc-dddd-000000000000';
        $adminGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000000';
        $developerGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000004'; // Developers (index 4 in fixtures)

        // Set a folder to partial for both groups
        $folderId = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000002'; // staging

        $this->db->executeStatement(
            "UPDATE folders_groups SET partial = 1 WHERE folder_id = :fid AND group_id IN (:gids)",
            ['fid' => $folderId, 'gids' => [$adminGroupId, $developerGroupId]],
            ['gids' => ArrayParameterType::STRING]
        );

        // Run promote only for admin group
        $this->cleaner->promote($vaultId, [$adminGroupId]);

        // Admin should be promoted, developer should remain partial
        $adminPartial = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $folderId, 'gid' => $adminGroupId]
        );
        $devPartial = $this->db->fetchOne(
            "SELECT partial FROM folders_groups WHERE folder_id = :fid AND group_id = :gid",
            ['fid' => $folderId, 'gid' => $developerGroupId]
        );

        $this->assertEquals(0, $adminPartial, 'Admin group should be promoted');
        $this->assertEquals(1, $devPartial, 'Developer group should remain partial (not in filter)');
    }
}
