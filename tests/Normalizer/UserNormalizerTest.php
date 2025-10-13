<?php

/**
 * @author bsteffan
 * @since 2025-04-23
 */

namespace App\Tests\Normalizer;

use App\Entity\User;
use App\Normalizer\UserNormalizer;
use App\Repository\UserRepository;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserNormalizerTest extends KernelTestCase
{
    #[DataProvider('provideNormalisationCases')]
    public function testNormalisation(array $context, array $expected): void
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->getContainer()
                               ->get("doctrine.orm.entity_manager")
                               ->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => "admin@example.com"]);
        if (!in_array(UserNormalizer::MINIMISED, $context)) {
            $expected["createdAt"] = $user->getCreatedAt()->format("Y-m-d H:i:s");
        }
        $normalized = $this->getContainer()->get("serializer")->normalize($user, context: $context);
        $this->assertEquals($expected, $normalized);
    }

    #[ArrayShape([
        "no context" => "array",
        "minimised" => "array",
        "with groups" => "array",
        "with group count" => "array",
    ])]
    public static function provideNormalisationCases(): array
    {
        return [
            "no context" => [
                [],
                [
                    "id" => "aaaaaaaa-bbbb-cccc-dddd-a00000000000",
                    "email" => "admin@example.com",
                    "username" => "admin",
                    "managedGroupIds" => [
                        "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                        "aaaaaaaa-bbbb-cccc-dddd-900000000003",
                        "aaaaaaaa-bbbb-cccc-dddd-900000000004",
                    ],
                    "admin" => true,
                    "verified" => true,
                    "createdAt" => "2025-04-23 14:27:02",
                    "createdBy" => "fixtures",
                    "updatedAt" => null,
                    "updatedBy" => null,
                    "active" => true,
                ],
            ],
            "minimised" => [
                [UserNormalizer::MINIMISED],
                [
                    "id" => "aaaaaaaa-bbbb-cccc-dddd-a00000000000",
                    "email" => "admin@example.com",
                    "username" => "admin",
                ],
            ],
            "with groups" => [
                [UserNormalizer::WITH_GROUPS],
                [
                    "id" => "aaaaaaaa-bbbb-cccc-dddd-a00000000000",
                    "email" => "admin@example.com",
                    "username" => "admin",
                    "managedGroupIds" => [
                        "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                        "aaaaaaaa-bbbb-cccc-dddd-900000000003",
                        "aaaaaaaa-bbbb-cccc-dddd-900000000004",
                    ],
                    "admin" => true,
                    "verified" => true,
                    "createdAt" => "2025-04-23 14:27:02",
                    "createdBy" => "fixtures",
                    "updatedAt" => null,
                    "updatedBy" => null,
                    "active" => true,
                    "groups" => [
                        [
                            'id' => '11111111-bbbb-cccc-dddd-a00000000000',
                            'name' => 'user-aaaaaaaa-bbbb-cccc-dddd-a00000000000',
                            'private' => true,
                        ],
                        [
                            "id" => "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                            "name" => "Administrators",
                            "private" => false,
                        ],
                        [
                            "id" => "aaaaaaaa-bbbb-cccc-dddd-900000000003",
                            "name" => "Guests",
                            "private" => false,
                        ],
                        [
                            "id" => "aaaaaaaa-bbbb-cccc-dddd-900000000004",
                            "name" => "Developers",
                            "private" => false,
                        ],
                    ],
                ],
            ],
        ];
    }
}
