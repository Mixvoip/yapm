<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Tests\Controller\Group;

use App\Entity\Group;
use App\Normalizer\GroupNormalizer;
use App\Repository\GroupRepository;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;

class GetControllerTest extends WebTestCase
{
    #[DataProvider('provideGroupNotFoundCases')]
    public function testGroupNotFound(string $groupId, string $userEmail): void
    {
        $this->getAsUser("/groups/$groupId", [], $userEmail);
        $this->assertResponse(
            404,
            [
                "message" => "Group with id: $groupId not found.",
                "error" => "Resource not found",
            ]
        );
    }

    #[DataProvider('provideSuccessfulGetCases')]
    public function testSuccessfulGet(string $groupId, string $userEmail): void
    {
        /** @var GroupRepository $groupRepository */
        $groupRepository = $this->container->get("doctrine")->getRepository(Group::class);
        $group = $groupRepository->find($groupId);

        $expectedResponse = $this->container->get("serializer")->normalize(
            $group,
            context: [
                GroupNormalizer::WITH_USERS,
                GroupNormalizer::WITH_MANAGERS,
            ]
        );
        $this->getAsUser("/groups/$groupId", [], $userEmail);;
        $this->assertResponse(200, $expectedResponse);
    }

    #[ArrayShape([
        'admin access' => "string[]",
        'manager access' => "string[]",
    ])]
    public static function provideSuccessfulGetCases(): array
    {
        return [
            'admin access' => [
                "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                "admin@example.com",
            ],
            'manager access' => [
                "aaaaaaaa-bbbb-cccc-dddd-900000000002",
                "manager@example.com",
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
            ],
            'user has no access' => [
                "aaaaaaaa-bbbb-cccc-dddd-900000000000",
                "user0@example.com",
            ],
        ];
    }
}
