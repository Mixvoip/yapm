<?php

namespace App\Tests\Controller\User;

use App\Entity\Group;
use App\Normalizer\UserNormalizer;
use App\Repository\GroupRepository;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;

class PatchControllerTest extends WebTestCase
{
    public function testAccessDenied(): void
    {
        $body = $this->getValidBody();
        $this->patchAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-000000000000", $body, "user0@example.com");
        $this->assertAccessDenied();
    }

    public function testUserNotFound(): void
    {
        $body = $this->getValidBody();
        $userId = "aaaaaaaa-bbbb-cccc-dddd-999999999999";
        $this->patchAsUser("/users/$userId", $body, "admin@example.com");
        $this->assertUserNotFound($userId);
    }

    public function testSuccessfulPatch(): void
    {
        $body = $this->getValidBody();
        $this->patchAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-000000000000", $body, "admin@example.com");
        $user = $this->userRepository->findOneBy(['email' => "user0@example.com"]);
        $expectedResponse = $this->container->get("serializer")->normalize(
            $user,
            context: [UserNormalizer::WITH_GROUPS]
        );
        $this->assertResponse(200, $expectedResponse);
        $this->assertEquals("admin@example.com", $user->getUpdatedBy());
        $this->assertTrue($user->isAdmin());
        $this->assertEquals(1, $user->getGroupUsers()->count());

        /** @var GroupRepository $groupRepository */
        $groupRepository = $this->container->get('doctrine')->getRepository(Group::class);
        $group = $groupRepository->findOneBy(['name' => ["Users"]]);
        $this->assertNull($group->getUpdatedBy());
        $this->assertNull($group->getUpdatedAt());
    }

    public function testSuccessfulPatchNoUpdate(): void
    {
        $this->patchAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-000000000000", [], "admin@example.com");
        $user = $this->userRepository->findOneBy(['email' => "user0@example.com"]);
        $expectedResponse = $this->container->get("serializer")->normalize(
            $user,
            context: [UserNormalizer::WITH_GROUPS]
        );
        $this->assertResponse(200, $expectedResponse);

        $this->assertNull($user->getUpdatedBy());
        $this->assertNull($user->getUpdatedAt());
        $this->assertFalse($user->isAdmin());
        $this->assertEquals(2, $user->getGroupUsers()->count());
    }

    #[DataProvider('provideInvalidData')]
    public function testInvalidData(array $body, string $expectedErrorMessage): void
    {
        $this->patchAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-000000000000", $body, "admin@example.com");
        $this->assertResponse(
            400,
            [
                'error' => 'HTTP Error',
                'message' => $expectedErrorMessage,
            ]
        );
    }

    #[DataProvider('provideBadDtoCases')]
    public function testBadDto(array $body, array $expectedResponse): void
    {
        $userId = "aaaaaaaa-bbbb-cccc-dddd-999999999999";
        $this->patchAsUser("/users/$userId", $body, "admin@example.com");
        $this->assertResponse(422, $expectedResponse);
    }

    #[ArrayShape([
        'wrong group id' => "array",
        'trying to add groups' => "array",
    ])]
    public static function provideInvalidData(): array
    {
        return [
            'wrong group id' => [
                [
                    'groups' => ["aaaaa"],
                ],
                "Invalid group IDs.",
            ],
            'trying to add groups' => [
                [
                    'groups' => ["aaaaaaaa-bbbb-cccc-dddd-900000000000"],
                ],
                "Adding groups is not allowed.",
            ],
        ];
    }

    #[ArrayShape([
        'admin wrong format' => "array",
        'group wrong format' => "array",
        'empty group' => "array",
        'group not string' => "array",
        'group not array' => "array",
    ])]
    public static function provideBadDtoCases(): array
    {
        return [
            'admin wrong format' => [
                [
                    'admin' => "ROLE_ADMIN",
                    'groups' => [],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "admin",
                            'message' => "This value should be of type bool|null.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'group wrong format' => [
                [
                    'groups' => "aaaaaaaa-bbbb-cccc-dddd-g00000000001",
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "groups",
                            'message' => "This value should be of type array|null.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'empty group' => [
                [
                    'groups' => [""],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "groups[0]",
                            'message' => "This value should not be blank.",
                            'code' => "c1051bb4-d103-4f74-8988-acbcafc7fdc3",
                        ],
                    ],
                ],
            ],
            'group not string' => [
                [
                    'groups' => [1],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "groups[0]",
                            'message' => "This value should be of type string.",
                            'code' => "ba785a8c-82cb-4283-967c-3cf342181b40",
                        ],
                    ],
                ],
            ],
            'group not array' => [
                [
                    'groups' => 1,
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "groups",
                            'message' => "This value should be of type array|null.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    #[ArrayShape([
        'admin' => "true",
        'groups' => "string[]",
    ])]
    private function getValidBody(): array
    {
        return [
            'admin' => true,
            'groups' => [],
        ];
    }
}
