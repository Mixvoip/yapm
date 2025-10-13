<?php

namespace App\Tests\Controller\User;

use App\Normalizer\UserNormalizer;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;

class GetControllerTest extends WebTestCase
{
    #[DataProvider('provideUserNotFoundCases')]
    public function testUserNotFound(string $userId, string $userEmail): void
    {
        $this->getAsUser("/users/$userId", [], $userEmail);
        $this->assertUserNotFound($userId);
    }

    #[DataProvider('provideSuccessfulGetCases')]
    public function testSuccessfulGet(string $userEmail): void
    {
        $user = $this->userRepository->find("aaaaaaaa-bbbb-cccc-dddd-000000000000");
        $expectedResponse = $this->container->get('serializer')->normalize(
            $user,
            context: [
                UserNormalizer::WITH_GROUPS,
            ]
        );
        $this->getAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-000000000000", [], $userEmail);
        $this->assertResponse(200, $expectedResponse);
    }

    #[ArrayShape([
        'admin access' => "string[]",
        'user accesses himself' => "string[]",
    ])]
    public static function provideSuccessfulGetCases(): array
    {
        return [
            'admin access' => [
                "admin@example.com",
            ],
            'user accesses himself' => [
                "user0@example.com",
            ],
        ];
    }

    #[ArrayShape([
        'user doesnt exist' => "string[]",
        'user has no access' => "string[]",
    ])]
    public static function provideUserNotFoundCases(): array
    {
        return [
            'user doesnt exist' => [
                "aaaaaaaa-bbbb-cccc-dddd-999999999999",
                "admin@example.com",
            ],
            'user has no access' => [
                "aaaaaaaa-bbbb-cccc-dddd-000000000001",
                "user0@example.com",
            ],
        ];
    }
}
