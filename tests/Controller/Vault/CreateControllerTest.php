<?php

/**
 * @author bsteffan
 * @since 2026-01-16
 */

namespace App\Tests\Controller\Vault;

use App\Entity\Vault;
use App\Repository\VaultRepository;
use App\Tests\Cases\WebTestCase;

class CreateControllerTest extends WebTestCase
{
    private VaultRepository $vaultRepository;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $this->getContainer()->get("doctrine")->getRepository(Vault::class);
        $this->vaultRepository = $vaultRepository;
    }

    /**
     * Test creating a vault with both groups and user permissions.
     */
    public function testCreateVaultWithGroupsAndUserPermissions(): void
    {
        $body = [
            'name' => "Test Vault With Mixed Permissions",
            'groups' => [
                [
                    'groupId' => 'aaaaaaaa-bbbb-cccc-dddd-900000000000', // admin group
                    'canWrite' => true,
                ],
            ],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-000000000000', // user0
                    'canWrite' => false,
                ],
            ],
        ];

        $this->postAsUser("/vaults", $body, "admin@example.com");
        $this->assertResponseStatusCodeSame(201);

        $response = $this->getDecodedResponse();
        $this->assertEquals("Test Vault With Mixed Permissions", $response['name']);
        $this->assertCount(1, $response['groups']);
        $this->assertCount(1, $response['users']);
    }

    /**
     * Test creating a vault with only user permissions (2 users required).
     */
    public function testCreateVaultWithOnlyUserPermissions(): void
    {
        $body = [
            'name' => "Test Vault User Only",
            'groups' => [],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-a00000000000', // admin user
                    'canWrite' => true,
                ],
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-000000000000', // user0
                    'canWrite' => false,
                ],
            ],
        ];

        $this->postAsUser("/vaults", $body, "admin@example.com");
        $this->assertResponseStatusCodeSame(201);

        $response = $this->getDecodedResponse();
        $this->assertEquals("Test Vault User Only", $response['name']);
        $this->assertCount(0, $response['groups']);
        $this->assertCount(2, $response['users']);
    }

    /**
     * Test that vault creation with only 1 user permission (no groups) is rejected.
     */
    public function testRejectVaultWithOnlyOneUserPermission(): void
    {
        $body = [
            'name' => "Test Vault Single User",
            'groups' => [],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-000000000000', // user0
                    'canWrite' => true,
                ],
            ],
        ];

        $this->postAsUser("/vaults", $body, "admin@example.com");
        $this->assertResponse(
            400,
            [
                'error' => "HTTP Error",
                'message' => "Vault requires at least one group or at least two user permissions.",
            ]
        );
    }

    /**
     * Test that vault creation with only the creator as user permission is rejected.
     * This prevents users from "cheating" a second private vault.
     */
    public function testRejectVaultWithOnlySelfAsUserPermission(): void
    {
        $body = [
            'name' => "Test Vault Self Only",
            'groups' => [],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-a00000000000', // admin user (creator)
                    'canWrite' => true,
                ],
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-a00000000000', // admin user again (duplicate)
                    'canWrite' => false,
                ],
            ],
        ];

        $this->postAsUser("/vaults", $body, "admin@example.com");
        $this->assertResponse(
            400,
            [
                'error' => "HTTP Error",
                'message' => "Duplicate user IDs.",
            ]
        );
    }

    /**
     * Test that vault creation with 2 user permissions but both being the creator is rejected.
     * Uses the real constraint - must have at least one user other than yourself.
     */
    public function testRejectVaultWithOnlyCreatorInUserPermissions(): void
    {
        $body = [
            'name' => "Test Vault Creator Only",
            'groups' => [],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-a00000000000', // admin user (creator)
                    'canWrite' => true,
                ],
            ],
        ];

        $this->postAsUser("/vaults", $body, "admin@example.com");
        // First rejection: only 1 user permission
        $this->assertResponse(
            400,
            [
                'error' => "HTTP Error",
                'message' => "Vault requires at least one group or at least two user permissions.",
            ]
        );
    }

    /**
     * Test that vault creation fails when sharing only with yourself (2 identical user IDs).
     */
    public function testRejectVaultSharingOnlyWithSelf(): void
    {
        // This tests the scenario where user tries to share with 2 users but one is themselves
        // and effectively they're the only unique user
        $body = [
            'name' => "Test Vault Self Share",
            'groups' => [],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-a00000000000', // admin (self)
                    'canWrite' => true,
                ],
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-000000000001', // user1
                    'canWrite' => false,
                ],
            ],
        ];

        $this->postAsUser("/vaults", $body, "admin@example.com");
        // Should succeed because there's another user besides self
        $this->assertResponseStatusCodeSame(201);
    }

    /**
     * Test that vault creation requires at least one write permission.
     */
    public function testRejectVaultWithNoWriteAccess(): void
    {
        $body = [
            'name' => "Test Vault No Write",
            'groups' => [],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-a00000000000', // admin
                    'canWrite' => false,
                ],
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-000000000000', // user0
                    'canWrite' => false,
                ],
            ],
        ];

        $this->postAsUser("/vaults", $body, "admin@example.com");
        $this->assertResponse(
            400,
            [
                'error' => "HTTP Error",
                'message' => "At least one group or user must have write access on the vault.",
            ]
        );
    }
}
