<?php

/**
 * @author bsteffan
 * @since 2025-04-28
 */

namespace App\Tests\Normalizer;

use App\Entity\Group;
use App\Normalizer\GroupNormalizer;
use App\Repository\GroupRepository;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GroupNormalizerTest extends KernelTestCase
{
    #[DataProvider('provideNormalisationCases')]
    public function testNormalize(array $context, array $expected)
    {
        /** @var GroupRepository $groupRepository */
        $groupRepository = $this->getContainer()
                                ->get("doctrine.orm.entity_manager")
                                ->getRepository(Group::class);
        $group = $groupRepository->findOneBy(['name' => 'Administrators']);
        if (!in_array(GroupNormalizer::MINIMISED, $context)) {
            $expected['createdAt'] = $group->getCreatedAt()->format("Y-m-d H:i:s");
            $expected['updatedAt'] = $group->getUpdatedAt()->format("Y-m-d H:i:s");
        }
        $normalized = $this->getContainer()->get("serializer")->normalize($group, context: $context);
        $this->assertEquals($expected, $normalized);
    }

    #[ArrayShape([
        "no context" => "array",
        "minimised" => "array",
        "with users" => "array",
        "with user count" => "array",
        "with managers" => "array",
    ])]
    public static function provideNormalisationCases(): array
    {
        return [
            "no context" => [
                [],
                [
                    "id" => "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                    "name" => "Administrators",
                    "private" => false,
                    "createdAt" => "2025-04-28 11:05:24",
                    "createdBy" => "fixtures",
                    "updatedAt" => null,
                    "updatedBy" => null,
                ],
            ],
            "minimised" => [
                [GroupNormalizer::MINIMISED],
                [
                    "id" => "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                    "name" => "Administrators",
                    "private" => false,
                ],
            ],
            "with users" => [
                [GroupNormalizer::WITH_USERS],
                [
                    "id" => "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                    "name" => "Administrators",
                    "private" => false,
                    "users" => [
                        [
                            "id" => "aaaaaaaa-bbbb-cccc-dddd-a00000000000",
                            "email" => "admin@example.com",
                            "username" => "admin",
                        ],
                    ],
                    "createdAt" => "2025-04-28 11:05:24",
                    "createdBy" => "fixtures",
                    "updatedAt" => null,
                    "updatedBy" => null,
                ],
            ],
            "with user count" => [
                [GroupNormalizer::WITH_USER_COUNT],
                [
                    "id" => "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                    "name" => "Administrators",
                    "private" => false,
                    "userCount" => 1,
                    "createdAt" => "2025-04-28 11:05:24",
                    "createdBy" => "fixtures",
                    "updatedAt" => null,
                    "updatedBy" => null,
                ],
            ],
            "with managers" => [
                [GroupNormalizer::WITH_MANAGERS],
                [
                    "id" => "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                    "name" => "Administrators",
                    "private" => false,
                    "managers" => [
                        [
                            "id" => "aaaaaaaa-bbbb-cccc-dddd-a00000000000",
                            "email" => "admin@example.com",
                            "username" => "admin",
                        ],
                    ],
                    "createdAt" => "2025-04-28 11:05:24",
                    "createdBy" => "fixtures",
                    "updatedAt" => null,
                    "updatedBy" => null,
                ],
            ],
        ];
    }
}
