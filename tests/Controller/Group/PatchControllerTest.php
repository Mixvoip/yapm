<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Tests\Controller\Group;

use App\Entity\Group;
use App\Normalizer\GroupNormalizer;
use App\Repository\GroupRepository;
use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;

class PatchControllerTest extends WebTestCase
{
    private readonly GroupRepository $groupRepository;
    private readonly EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var GroupRepository $groupRepository */
        $groupRepository = $this->getContainer()->get("doctrine")->getRepository(Group::class);
        $this->groupRepository = $groupRepository;

        /** @var EncryptionService $encryptionService */
        $encryptionService = $this->getContainer()->get(EncryptionService::class);
        $this->encryptionService = $encryptionService;
    }

    #[DataProvider('provideGroupNotFoundCases')]
    public function testGroupNotFound(string $groupId, string $userEmail, string $userPassword): void
    {
        $encryptedPassword = $this->encryptionService->encryptForServer($userPassword);
        $body = [
            'encryptedPassword' => $encryptedPassword,
            'users' => [
                "aaaaaaaa-bbbb-cccc-dddd-a00000000000",
                "aaaaaaaa-bbbb-cccc-dddd-000000000001",
            ],
        ];
        $this->patchAsUser("/groups/$groupId", $body, $userEmail);
        $this->assertResponse(
            404,
            [
                "message" => "Group with id: $groupId not found.",
                "error" => "Resource not found",
            ]
        );
    }

    #[DataProvider('provideSuccessfulPatchCases')]
    public function testSuccessfulPatch(
        array $body,
        string $userEmail,
        string $userPassword,
        int $expectedUserCount,
        array $updatedUserIds,
        array $nonUpdatedUserIds
    ): void {
        $encryptedPassword = $this->encryptionService->encryptForServer($userPassword);
        $body['encryptedPassword'] = $encryptedPassword;
        $this->patchAsUser("/groups/aaaaaaaa-bbbb-cccc-dddd-900000000002", $body, $userEmail);
        $group = $this->groupRepository->findOneBy(['name' => "Users"]);
        $expectedResponse = $this->container->get("serializer")->normalize(
            $group,
            context: [
                GroupNormalizer::WITH_USERS,
                GroupNormalizer::WITH_MANAGERS,
            ]
        );
        $this->assertResponse(200, $expectedResponse);
        $this->assertEquals($userEmail, $group->getUpdatedBy());
        $this->assertNotNull($group->getUpdatedAt());
        $this->assertEquals($expectedUserCount, $group->getGroupUsers()->count());

        $users = $this->userRepository->findBy(['id' => array_merge($nonUpdatedUserIds, $updatedUserIds)]);

        foreach ($users as $user) {
            $this->assertNull($user->getUpdatedBy());
            $this->assertNull($user->getUpdatedAt());
        }
    }

    public function testSuccessfulPatchNoUpdate(): void
    {
        $encryptedPassword = $this->encryptionService->encryptForServer("InThePassw0rdManager");
        $body = [
            'encryptedPassword' => $encryptedPassword,
            'users' => ["aaaaaaaa-bbbb-cccc-dddd-a00000000000"],
            'managers' => ["aaaaaaaa-bbbb-cccc-dddd-a00000000000"],
        ];
        $this->patchAsUser("/groups/aaaaaaaa-bbbb-cccc-dddd-900000000000", $body, "admin@example.com");
        $group = $this->groupRepository->findOneBy(['name' => "Administrators"]);
        $expectedResponse = $this->container->get("serializer")->normalize(
            $group,
            context: [
                GroupNormalizer::WITH_USERS,
                GroupNormalizer::WITH_MANAGERS,
            ]
        );
        $this->assertResponse(200, $expectedResponse);

        $this->assertNull($group->getUpdatedBy());
        $this->assertEquals(1, $group->getGroupUsers()->count());
    }

    #[DataProvider('provideInvalidData')]
    public function testInvalidData(array $body, string $userEmail, string $userPassword, string $errorMessage): void
    {
        $encryptedPassword = $this->encryptionService->encryptForServer($userPassword);
        $body['encryptedPassword'] = $encryptedPassword;
        $this->patchAsUser("/groups/aaaaaaaa-bbbb-cccc-dddd-900000000002", $body, $userEmail);
        $this->assertResponse(
            400,
            [
                'error' => "HTTP Error",
                'message' => $errorMessage,
            ]
        );
    }

    #[DataProvider('provideInvalidDtoCases')]
    public function testInvalidDto(array $body, array $expectedResponse): void
    {
        $encryptedPassword = $this->encryptionService->encryptForServer("InThePassw0rdManager");
        $body['encryptedPassword'] = $encryptedPassword;
        $this->patchAsUser("/groups/aaaaaaaa-bbbb-cccc-dddd-900000000000", $body, "admin@example.com");
        $this->assertResponse(422, $expectedResponse);
    }

    #[ArrayShape([
        'users wrong format' => "array[]",
        'users empty' => "array",
        'managers wrong format' => "array[]",
        'managers empty' => "array",
    ])]
    public static function provideInvalidDtoCases(): array
    {
        return [
            'users wrong format' => [
                [
                    'users' => "Whatever",
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "users",
                            'message' => "This value should be of type array|null.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'users empty' => [
                [
                    'users' => [""],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "users[0]",
                            'message' => "This value should not be blank.",
                            'code' => "c1051bb4-d103-4f74-8988-acbcafc7fdc3",
                        ],
                    ],
                ],
            ],
            'managers wrong format' => [
                [
                    'managers' => "Whatever",
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "managers",
                            'message' => "This value should be of type array|null.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'managers empty' => [
                [
                    'managers' => [""],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "managers[0]",
                            'message' => "This value should not be blank.",
                            'code' => "c1051bb4-d103-4f74-8988-acbcafc7fdc3",
                        ],
                    ],
                ],
            ],
        ];
    }

    #[ArrayShape([
        'group doesnt exist' => "string[]",
        'user has no access' => "string[]",
    ])]
    public static function provideGroupNotFoundCases(): array
    {
        return [
            'group doesnt exist' => [
                "aaaaaaaa-bbbb-cccc-dddd-999999999999",
                "admin@example.com",
                "InThePassw0rdManager",
            ],
            'user has no access' => [
                "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                "user0@example.com",
                "password123",
            ],
        ];
    }

    #[ArrayShape([
        'users wrong id' => "array",
        'managers wrong id' => "array",
        'manager trying to add manager' => "array",
        'manager trying to remove manager' => "array",
        'admin trying to add user' => "array",
        'deleting all users' => "array",
    ])]
    public static function provideInvalidData(): array
    {
        return [
            'users wrong id' => [
                [
                    'users' => ["aaaaaa"],
                ],
                "admin@example.com",
                "InThePassw0rdManager",
                "Invalid user IDs.",
            ],
            'managers wrong id' => [
                [
                    'managers' => ["aaaaaa"],
                ],
                "admin@example.com",
                "InThePassw0rdManager",
                "Invalid user IDs.",
            ],
            'manager trying to add manager' => [
                [
                    'managers' => ["aaaaaaaa-bbbb-cccc-dddd-a00000000000"],
                ],
                "manager@example.com",
                "password123",
                "Only admins can add managers to a group.",
            ],
            'manager trying to remove manager' => [
                [
                    'managers' => [],
                ],
                "manager@example.com",
                "password123",
                "Only admins can remove managers from a group.",
            ],
            'admin trying to add user' => [
                [
                    'users' => [
                        "aaaaaaaa-bbbb-cccc-dddd-a00000000000",
                        "aaaaaaaa-bbbb-cccc-dddd-000000000001",
                    ],
                ],
                "admin@example.com",
                "InThePassw0rdManager",
                "Only group managers can add users to a group.",
            ],
            'deleting all users' => [
                [
                    'users' => [],
                ],
                "manager@example.com",
                "password123",
                "Cannot remove the last user from a group.",
            ],
        ];
    }

    #[ArrayShape([
        'admin patch' => "array",
        'manager patch' => "array",
    ])]
    public static function provideSuccessfulPatchCases(): array
    {
        return [
            'admin patch' => [
                [
                    'users' => [
                        "aaaaaaaa-bbbb-cccc-dddd-000000000000",
                        "aaaaaaaa-bbbb-cccc-dddd-000000000001",
                        "aaaaaaaa-bbbb-cccc-dddd-000000000002",
                    ],
                    'managers' => [
                        "aaaaaaaa-bbbb-cccc-dddd-000000000001",
                    ],
                ],
                "admin@example.com",
                "InThePassw0rdManager",
                3,
                [
                    "aaaaaaaa-bbbb-cccc-dddd-000000000010",
                    "aaaaaaaa-bbbb-cccc-dddd-000000000004",
                ],
                [
                    "aaaaaaaa-bbbb-cccc-dddd-000000000000",
                    "aaaaaaaa-bbbb-cccc-dddd-000000000001",
                    "aaaaaaaa-bbbb-cccc-dddd-000000000002",
                ],
            ],
            'manager patch' => [
                [
                    'users' => [
                        "aaaaaaaa-bbbb-cccc-dddd-a00000000000",
                        "aaaaaaaa-bbbb-cccc-dddd-000000000000",
                        "aaaaaaaa-bbbb-cccc-dddd-000000000001",
                        "aaaaaaaa-bbbb-cccc-dddd-000000000002",
                        "aaaaaaaa-bbbb-cccc-dddd-000000000010",
                    ],
                ],
                "manager@example.com",
                "password123",
                5,
                [
                    "aaaaaaaa-bbbb-cccc-dddd-a00000000000",
                    "aaaaaaaa-bbbb-cccc-dddd-000000000004",
                ],
                [
                    "aaaaaaaa-bbbb-cccc-dddd-000000000000",
                    "aaaaaaaa-bbbb-cccc-dddd-000000000001",
                    "aaaaaaaa-bbbb-cccc-dddd-000000000002",
                    "aaaaaaaa-bbbb-cccc-dddd-000000000010",
                ],
            ],
        ];
    }
}
