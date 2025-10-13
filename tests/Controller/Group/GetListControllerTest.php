<?php

/**
 * @author bsteffan
 * @since 2025-04-29
 */

namespace App\Tests\Controller\Group;

use App\Entity\Group;
use App\Normalizer\GroupNormalizer;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;

class GetListControllerTest extends WebTestCase
{
    public function testBadRequest(): void
    {
        $this->getAsUser("/groups", ['displayType' => "whatever"], "admin@example.com");
        $this->assertResponse(
            404,
            [
                'error' => "Resource not found",
                'message' => 'Invalid query parameter "displayType".',
            ]
        );
    }

    #[DataProvider('provideSearchCases')]
    public function testSuccessfulGetWithSearch(string $userEmail, string $search, array $expectedNames): void
    {
        $groupRepository = $this->container->get("doctrine")->getRepository(Group::class);
        $groups = $groupRepository->findBy(['name' => $expectedNames]);
        usort($groups, function (Group $a, Group $b) {
            return strcmp($a->getName(), $b->getName());
        });
        $expectedResponse = [];
        $normalizer = $this->container->get("serializer");
        foreach ($groups as $group) {
            $expectedResponse[] = $normalizer->normalize($group, context: [GroupNormalizer::MINIMISED]);
        }

        $this->getAsUser("/groups", ['search' => $search], $userEmail);
        $this->assertResponse(200, $expectedResponse);
    }

    #[DataProvider('provideVariantsCases')]
    public function testSuccessfulGetWithVariants(array $params, array $context): void
    {
        $groupRepository = $this->container->get("doctrine")->getRepository(Group::class);
        $groups = $groupRepository->findBy(['private' => false]);
        usort($groups, function (Group $a, Group $b) {
            return strcmp($a->getName(), $b->getName());
        });
        $expectedResponse = [];
        $normalizer = $this->container->get("serializer");
        foreach ($groups as $group) {
            $expectedResponse[] = $normalizer->normalize($group, context: $context);
        }

        $this->getAsUser("/groups", $params, "admin@example.com");
        $this->assertResponse(200, $expectedResponse);
    }

    #[ArrayShape([
        "search by name admin" => "array",
        "search by name shorter match admin" => "array",
        "non admin user no result" => "array",
        "non admin user result" => "array",
    ])]
    public static function provideSearchCases(): array
    {
        return [
            "search by name admin" => [
                "admin@example.com",
                "Administrators",
                ["Administrators"],
            ],
            "search by name shorter match admin" => [
                "admin@example.com",
                "Manager",
                ["Managers"],
            ],
            "non admin user no result" => [
                "user0@example.com",
                "",
                ["Administrators", "Managers", "Users", "Guests", "Developers"],
            ],
            "non admin user result" => [
                "manager@example.com",
                "Managers",
                ["Managers"],
            ],
        ];
    }

    #[ArrayShape([
        "list" => "array",
        "table" => "array",
        "no params" => "array",
    ])]
    public static function provideVariantsCases(): array
    {
        return [
            "list" => [
                ['displayType' => 'list'],
                [GroupNormalizer::MINIMISED],
            ],
            "table" => [
                ['displayType' => 'table'],
                [
                    GroupNormalizer::WITH_USER_COUNT,
                    GroupNormalizer::WITH_MANAGERS,
                ],
            ],
            "no params" => [
                [],
                [GroupNormalizer::MINIMISED],
            ],
        ];
    }
}
