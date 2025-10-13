<?php

namespace App\Tests\Controller\User;

use App\Entity\User;
use App\Normalizer\UserNormalizer;
use App\Repository\UserRepository;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;

class GetListControllerTest extends WebTestCase
{
    #[DataProvider('provideAccessDeniedCases')]
    public function testAccessDenied(array $params, string $userEmail): void
    {
        $this->getAsUser("/users", $params, $userEmail);
        $this->assertAccessDenied();
    }

    public function testBadRequest(): void
    {
        $this->getAsUser("/users", ['displayType' => "whatever"], "admin@example.com");
        $this->assertResponse(
            404,
            [
                'error' => "Resource not found",
                'message' => 'Invalid query parameter "displayType".',
            ]
        );
    }

    #[DataProvider('provideSearchCases')]
    public function testSuccessfulGetWithSearchAndActiveOnly(string $search, array $expectedEmails): void
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get('doctrine')->getRepository(User::class);
        $users = $userRepository->findBy(['email' => $expectedEmails]);
        usort($users, function (User $a, User $b) {
            return strcmp($a->getUsername(), $b->getUsername());
        });
        $expectedResponse = [];
        $normalizer = $this->container->get('serializer');
        foreach ($users as $user) {
            $expectedResponse[] = $normalizer->normalize($user, context: [UserNormalizer::MINIMISED]);
        }

        $this->getAsUser("/users", ['search' => $search, 'activeOnly' => true], "admin@example.com");
        $this->assertResponse(200, $expectedResponse);
    }

    #[DataProvider('provideVariantsCases')]
    public function testSuccessfulGetWithVariants(array $params, array $context): void
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get('doctrine')->getRepository(User::class);
        $users = $userRepository->findAll();
        usort($users, function (User $a, User $b) {
            return strcmp($a->getUsername(), $b->getUsername());
        });
        $expectedResponse = [];
        $normalizer = $this->container->get('serializer');
        foreach ($users as $user) {
            $expectedResponse[] = $normalizer->normalize($user, context: $context);
        }

        $this->getAsUser("/users", $params, "admin@example.com");
        $this->assertResponse(200, $expectedResponse);
    }

    #[ArrayShape([
        "search by email" => "array",
        "search by username" => "array",
    ])]
    public static function provideSearchCases(): array
    {
        return [
            "search by email" => [
                "user",
                [
                    "user0@example.com",
                    "user1@example.com",
                    "user4@example.com",
                ],
            ],
            "search by username" => [
                "admin",
                ["admin@example.com"],
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
                ['displayType' => "list"],
                [UserNormalizer::MINIMISED],
            ],
            "table" => [
                ['displayType' => "table"],
                [UserNormalizer::WITH_GROUPS],
            ],
            "no params" => [
                [],
                [UserNormalizer::MINIMISED],
            ],
        ];
    }

    #[ArrayShape([
        'not admin, not groupManager' => "array",
        'not admin, display not list' => "array",
    ])]
    public static function provideAccessDeniedCases(): array
    {
        return [
            'not admin, not groupManager' => [
                ['displayType' => "list"],
                "user0@example.com",
            ],
            'not admin, display not list' => [
                ['displayType' => "table"],
                "manager@example.com",
            ],
        ];
    }
}
