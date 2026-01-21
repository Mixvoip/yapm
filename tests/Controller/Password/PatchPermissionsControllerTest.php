<?php

/**
 * @author bsteffan
 * @since 2025-08-12
 */

namespace App\Tests\Controller\Password;

use App\Entity\Password;
use App\Repository\PasswordRepository;
use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\RandomException;

class PatchPermissionsControllerTest extends WebTestCase
{
    /**
     * @throws RandomException
     */
    #[DataProvider('provideBadDtoCases')]
    public function testInvalidDto(array $body, array $expected, int $expectedStatusCode = 422): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        // Inject real encryptedPassword payload if placeholder is present
        if (isset($body['encryptedPassword']) && ($body['encryptedPassword']['encryptedData'] ?? null) === 'to-be-replaced') {
            $body['encryptedPassword'] = $this->makePwdPayload();
        }

        $this->patchAsUser("/passwords/$passwordId/permissions", $body, 'admin@example.com');
        $this->assertResponse($expectedStatusCode, $expected);
    }

    /**
     * @throws RandomException
     */
    public function testRejectsNoWriter(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041'; // PROD folder dev passwords -> admin group only

        $body = [
            'encryptedPassword' => $this->makePwdPayload(),
            'groups' => [
                // Keep only admin group but set canWrite=false -> should be rejected
                [
                    'groupId' => 'aaaaaaaa-bbbb-cccc-dddd-900000000000', // admin
                    'canWrite' => false,
                ],
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/permissions", $body, 'admin@example.com');

        $this->assertResponse(
            400,
            [
                'error' => 'HTTP Error',
                'message' => 'At least one group or user must have write access to the password.',
            ]
        );
    }

    /**
     * @throws RandomException
     */
    #[DataProvider('providePermissionChangeCases')]
    public function testPatchPermissions(
        array $body,
        bool $expectManager,
        ?bool $expectManagerCanWrite = null
    ): void {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041'; // Initially admin group only

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);

        $passwordBefore = $passwordRepository->find($passwordId);
        $this->assertNotNull($passwordBefore, 'Password should exist in fixtures');
        $updatedAtBefore = $passwordBefore->getUpdatedAt();
        $updatedByBefore = $passwordBefore->getUpdatedBy();

        // Prepare encrypted password for this run
        $body['encryptedPassword'] = $this->makePwdPayload();

        // Perform single API call for this scenario
        $this->patchAsUser("/passwords/$passwordId/permissions", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        // Reload entities
        $passwordAfter = $passwordRepository->find($passwordId);
        $this->assertSame($updatedAtBefore, $passwordAfter->getUpdatedAt(), 'Password.updatedAt must not change');
        $this->assertSame($updatedByBefore, $passwordAfter->getUpdatedBy(), 'Password.updatedBy must not change');

        $adminGp = null;
        $managerGp = null;
        foreach ($passwordAfter->getGroupPasswords() as $gp) {
            $gid = $gp->getGroup()->getId();
            if ($gid === 'aaaaaaaa-bbbb-cccc-dddd-900000000000') {
                $adminGp = $gp;
            }
            if ($gid === 'aaaaaaaa-bbbb-cccc-dddd-900000000001') {
                $managerGp = $gp;
            }
        }
        $this->assertNotNull($adminGp, 'Admin group should have access');
        $this->assertTrue($adminGp->canWrite(), 'Admin should have write access');

        if ($expectManager) {
            $this->assertNotNull($managerGp, 'Manager group should exist per scenario');
            if ($expectManagerCanWrite !== null) {
                $this->assertSame($expectManagerCanWrite, $managerGp->canWrite(), 'Manager canWrite mismatch');
            }
        } else {
            $this->assertNull($managerGp, 'Manager group should not exist per scenario');
        }
    }

    /**
     * @throws RandomException
     */
    public function testDeletesPermission(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000000';
        $removedGroupId = 'aaaaaaaa-bbbb-cccc-dddd-900000000002'; // developer

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $passwordBefore = $passwordRepository->find($passwordId);
        $this->assertNotNull($passwordBefore, 'Password should exist in fixtures');

        $body = [
            'encryptedPassword' => $this->makePwdPayload(),
            'groups' => [
                [
                    'groupId' => 'aaaaaaaa-bbbb-cccc-dddd-900000000000', // admin only
                    'canWrite' => true,
                ],
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/permissions", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        $passwordAfter = $passwordRepository->find($passwordId);
        $found = false;
        foreach ($passwordAfter->getGroupPasswords() as $gp) {
            if ($gp->getGroup()->getId() === $removedGroupId) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Removed group relation should not exist after PATCH.');
    }

    /**
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedData' => "mixed|string",
        'clientPublicKey' => "mixed|string",
        'nonce' => "mixed|string",
    ])]
    private function makePwdPayload(): array
    {
        /** @var EncryptionService $encryptionService */
        $encryptionService = $this->container->get(EncryptionService::class);
        $encrypted = $encryptionService->encryptForServer("InThePassw0rdManager");

        return [
            'encryptedData' => $encrypted['encryptedData'],
            'clientPublicKey' => $encrypted['clientPublicKey'],
            'nonce' => $encrypted['nonce'],
        ];
    }

    #[ArrayShape([
        'missing encryptedPassword' => 'array',
        'empty groups and users' => 'array',
        'invalid group element type' => 'array',
    ])]
    public static function provideBadDtoCases(): array
    {
        return [
            'missing encryptedPassword' => [
                [
                    'groups' => [
                        [
                            'groupId' => 'aaaaaaaa-bbbb-cccc-dddd-900000000000',
                            'canWrite' => true,
                        ],
                    ],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "encryptedPassword",
                            'message' => "This value should be of type App\Controller\Dto\EncryptedClientDataDto.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'empty groups and users' => [
                [
                    'encryptedPassword' => [
                        // filled at runtime in test method
                        'encryptedData' => 'to-be-replaced',
                        'clientPublicKey' => 'to-be-replaced',
                        'nonce' => 'to-be-replaced',
                    ],
                    'groups' => [],
                    'userPermissions' => [],
                ],
                [
                    'error' => "HTTP Error",
                    'message' => "At least one group or user permission must be provided.",
                ],
                400,
            ],
            'invalid group element type' => [
                [
                    'encryptedPassword' => [
                        'encryptedData' => 'to-be-replaced',
                        'clientPublicKey' => 'to-be-replaced',
                        'nonce' => 'to-be-replaced',
                    ],
                    'groups' => [123],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "groups[0].groupId",
                            'message' => "This value should be of type string.",
                            'code' => null,
                        ],
                        [
                            'parameter' => "groups[0].canWrite",
                            "message" => 'This value should be of type bool.',
                            'code' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    #[ArrayShape([
        'add manager read-only' => "array",
        'admin only no-change' => "array",
    ])]
    public static function providePermissionChangeCases(): array
    {
        return [
            'add manager read-only' => [
                'body' => [
                    'encryptedPassword' => [
                        // Placeholder; will be replaced in test with a real encrypted payload
                        'encryptedData' => 'to-be-replaced',
                        'clientPublicKey' => 'to-be-replaced',
                        'nonce' => 'to-be-replaced',
                    ],
                    'groups' => [
                        [
                            'groupId' => 'aaaaaaaa-bbbb-cccc-dddd-900000000000', // admin
                            'canWrite' => true,
                        ],
                        [
                            'groupId' => 'aaaaaaaa-bbbb-cccc-dddd-900000000001', // manager
                            'canWrite' => false,
                        ],
                    ],
                ],
                'expectManager' => true,
                'expectManagerCanWrite' => false,
            ],
            'admin only no-change' => [
                'body' => [
                    'encryptedPassword' => [
                        'encryptedData' => 'to-be-replaced',
                        'clientPublicKey' => 'to-be-replaced',
                        'nonce' => 'to-be-replaced',
                    ],
                    'groups' => [
                        [
                            'groupId' => 'aaaaaaaa-bbbb-cccc-dddd-900000000000', // admin
                            'canWrite' => true,
                        ],
                    ],
                ],
                'expectManager' => false,
            ],
        ];
    }

    /**
     * Test patching password permissions with user permissions (private groups).
     *
     * @throws RandomException
     */
    public function testPatchWithUserPermissions(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);

        $body = [
            'encryptedPassword' => $this->makePwdPayload(),
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

        $this->patchAsUser("/passwords/$passwordId/permissions", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        // Verify user's private group was added
        $passwordAfter = $passwordRepository->find($passwordId);
        $user0PrivateGroupId = '11111111-bbbb-cccc-dddd-000000000000';
        $found = false;
        foreach ($passwordAfter->getGroupPasswords() as $gp) {
            if ($gp->getGroup()->getId() === $user0PrivateGroupId) {
                $found = true;
                $this->assertFalse($gp->canWrite(), 'User0 should have read-only access');
                break;
            }
        }
        $this->assertTrue($found, 'User0 private group should have access after PATCH');
    }

    /**
     * Test patching password permissions with only user permissions (no groups).
     *
     * @throws RandomException
     */
    public function testPatchWithOnlyUserPermissions(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        $body = [
            'encryptedPassword' => $this->makePwdPayload(),
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

        $this->patchAsUser("/passwords/$passwordId/permissions", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $passwordAfter = $passwordRepository->find($passwordId);

        // Verify both private groups have access
        $adminPrivateGroupId = '11111111-bbbb-cccc-dddd-a00000000000';
        $user0PrivateGroupId = '11111111-bbbb-cccc-dddd-000000000000';
        $foundAdmin = false;
        $foundUser0 = false;

        foreach ($passwordAfter->getGroupPasswords() as $gp) {
            $groupId = $gp->getGroup()->getId();
            if ($groupId === $adminPrivateGroupId) {
                $foundAdmin = true;
                $this->assertTrue($gp->canWrite(), 'Admin should have write access');
            }
            if ($groupId === $user0PrivateGroupId) {
                $foundUser0 = true;
                $this->assertFalse($gp->canWrite(), 'User0 should have read-only access');
            }
        }
        $this->assertTrue($foundAdmin, 'Admin private group should have access');
        $this->assertTrue($foundUser0, 'User0 private group should have access');
    }

    /**
     * Test that non-existent user ID returns error.
     *
     * @throws RandomException
     */
    public function testRejectsNonExistentUserId(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        $body = [
            'encryptedPassword' => $this->makePwdPayload(),
            'groups' => [
                [
                    'groupId' => 'aaaaaaaa-bbbb-cccc-dddd-900000000000',
                    'canWrite' => true,
                ],
            ],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-nonexistent0', // non-existent
                    'canWrite' => false,
                ],
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/permissions", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * Test that duplicate user IDs are rejected.
     *
     * @throws RandomException
     */
    public function testRejectsDuplicateUserIds(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        $body = [
            'encryptedPassword' => $this->makePwdPayload(),
            'groups' => [
                [
                    'groupId' => 'aaaaaaaa-bbbb-cccc-dddd-900000000000',
                    'canWrite' => true,
                ],
            ],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-000000000000', // user0
                    'canWrite' => false,
                ],
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-000000000000', // user0 again - duplicate
                    'canWrite' => true,
                ],
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/permissions", $body, 'admin@example.com');
        $this->assertResponse(
            400,
            [
                'error' => 'HTTP Error',
                'message' => 'Duplicate user IDs.',
            ]
        );
    }

    /**
     * Test that user permissions can be write-only (with no regular groups).
     *
     * @throws RandomException
     */
    public function testUserPermissionWithWriteAccess(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        $body = [
            'encryptedPassword' => $this->makePwdPayload(),
            'groups' => [],
            'userPermissions' => [
                [
                    'userId' => 'aaaaaaaa-bbbb-cccc-dddd-a00000000000', // admin user - write
                    'canWrite' => true,
                ],
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/permissions", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $passwordAfter = $passwordRepository->find($passwordId);

        // Only admin private group should remain
        $adminPrivateGroupId = '11111111-bbbb-cccc-dddd-a00000000000';
        $foundAdmin = false;
        foreach ($passwordAfter->getGroupPasswords() as $gp) {
            if ($gp->getGroup()->getId() === $adminPrivateGroupId) {
                $foundAdmin = true;
                $this->assertTrue($gp->canWrite(), 'Admin should have write access');
            }
        }
        $this->assertTrue($foundAdmin, 'Admin private group should have access');
    }
}
