<?php

/**
 * @author bsteffan
 * @since 2025-05-26
 */

namespace App\Tests\Controller\Group;

use App\Entity\Group;
use App\Normalizer\GroupNormalizer;
use App\Repository\GroupRepository;
use App\Tests\Cases\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateControllerTest extends WebTestCase
{
    private GroupRepository $groupRepository;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var GroupRepository $groupRepository */
        $groupRepository = $this->getContainer()->get("doctrine")->getRepository(Group::class);
        $this->groupRepository = $groupRepository;
    }

    public function testAccessDenied(): void
    {
        $body = $this->getValidBody();
        $this->postAsUser("/groups", $body, "user0@example.com");
        $this->assertAccessDenied();
    }

    public function testSuccessfulCreate(): void
    {
        $body = $this->getValidBody();
        $this->postAsUser("/groups", $body, "admin@example.com");
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManager->clear();
        $group = $this->groupRepository->findOneBy(['name' => "Test Group"]);
        $expectedResponse = $this->container->get("serializer")->normalize(
            $group,
            context: [
                GroupNormalizer::WITH_USERS,
                GroupNormalizer::WITH_MANAGERS,
            ]
        );
        $this->assertResponse(201, $expectedResponse);
    }

    #[DataProvider('provideInvalidDtoCases')]
    public function testInvalidDto(array $body, array $expectedResponse): void
    {
        $this->postAsUser("/groups", $body, "admin@example.com");
        $this->assertResponse(422, $expectedResponse);
    }

    public function testDuplicateName(): void
    {
        $body = $this->getValidBody();
        $body['name'] = "Developers";
        $this->postAsUser("/groups", $body, "admin@example.com");
        $this->assertResponse(
            409,
            [
                'id' => "aaaaaaaa-bbbb-cccc-dddd-900000000004",
            ]
        );
    }

    #[DataProvider("provideInvalidData")]
    public function testInvalidData(array $body): void
    {
        $this->postAsUser("/groups", $body, "admin@example.com");
        $this->assertResponse(
            400,
            [
                'error' => "HTTP Error",
                'message' => 'Invalid user IDs.',
            ]
        );
    }

    #[ArrayShape([
        'invalid managers' => "array[]",
        'invalid users' => "array[]",
    ])]
    public static function provideInvalidData(): array
    {
        return [
            'invalid managers' => [
                [
                    'name' => "Test Group",
                    'managers' => ["Whatever"],
                    'users' => [
                        "aaaaaaaa-bbbb-cccc-dddd-000000000000",
                        "aaaaaaaa-bbbb-cccc-dddd-000000000001",
                    ],
                ],
            ],
            'invalid users' => [
                [
                    'name' => "Test Group",
                    'managers' => ["aaaaaaaa-bbbb-cccc-dddd-000000000000"],
                    'users' => ["Whatever"],
                ],
            ],
        ];
    }

    #[ArrayShape([
        'empty name' => "array",
        'invalid name' => "array",
        'users wrong format' => "array[]",
        'users empty' => "array",
        'managers wrong format' => "array",
        'managers empty' => "array",
        'managers not provided' => "array",
    ])]
    public static function provideInvalidDtoCases(): array
    {
        return [
            'empty name' => [
                [
                    'name' => "",
                    'managers' => ["aaaaaaaa-bbbb-cccc-dddd-000000000000"],
                    'users' => [],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "name",
                            'message' => "This value should not be blank.",
                            'code' => "c1051bb4-d103-4f74-8988-acbcafc7fdc3",
                        ],
                    ],
                ],
            ],
            'invalid name' => [
                [
                    'name' => 1337,
                    'managers' => ["aaaaaaaa-bbbb-cccc-dddd-000000000000"],
                    'users' => [],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "name",
                            'message' => "This value should be of type string.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'users wrong format' => [
                [
                    'name' => "name",
                    'managers' => ["aaaaaaaa-bbbb-cccc-dddd-000000000000"],
                    'users' => "Whatever",
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "users",
                            'message' => "This value should be of type array.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'users empty' => [
                [
                    'name' => "name",
                    'managers' => ["aaaaaaaa-bbbb-cccc-dddd-000000000000"],
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
                    'name' => "name",
                    'managers' => "Whatever",
                    'users' => [],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "managers",
                            'message' => "This value should be of type array.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'managers empty' => [
                [
                    'name' => "name",
                    'managers' => [""],
                    'users' => [],
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
            'managers not provided' => [
                [
                    'name' => "name",
                    'users' => [],
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "managers",
                            'message' => "This value should be of type array.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    #[ArrayShape([
        'name' => "string",
        'managers' => "string[]",
        'users' => "string[]",
    ])]
    private function getValidBody(): array
    {
        return [
            'name' => "Test Group",
            'managers' => ["aaaaaaaa-bbbb-cccc-dddd-000000000000"],
            'users' => [
                "aaaaaaaa-bbbb-cccc-dddd-000000000000",
                "aaaaaaaa-bbbb-cccc-dddd-000000000001",
            ],
        ];
    }
}
