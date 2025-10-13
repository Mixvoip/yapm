<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Tests\Controller\Password;

use App\Entity\Password;
use App\Normalizer\PasswordNormalizer;
use App\Repository\PasswordRepository;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;

class GetControllerTest extends WebTestCase
{
    #[DataProvider('providePasswordNotFoundCases')]
    public function testPasswordNotFound(string $passwordId, string $userEmail): void
    {
        $this->getAsUser("/passwords/$passwordId", [], $userEmail);
        $this->assertResponse(
            404,
            [
                'error' => "Resource not found",
                'message' => 'Password with id: ' . $passwordId . ' not found.',
            ]
        );
    }

    #[DataProvider('provideSuccessfulGetCases')]
    public function testSuccessfulGet(string $passwordId, string $userEmail): void
    {
        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get("doctrine")->getRepository(Password::class);
        $password = $passwordRepository->find($passwordId);
        $expectedResponse = $this->container->get('serializer')->normalize(
            $password,
            context: [
                PasswordNormalizer::WITH_FOLDER,
                PasswordNormalizer::WITH_GROUPS,
            ]
        );
        $this->getAsUser("/passwords/$passwordId", [], $userEmail);
        $this->assertResponse(200, $expectedResponse);
    }

    #[ArrayShape([
        'password' => "string[]",
        'another password' => "string[]",
    ])]
    public static function provideSuccessfulGetCases(): array
    {
        return [
            'password' => [
                "aaacdaaa-bbbb-cccc-dddd-000000000041",
                "admin@example.com",
            ],
            'another password' => [
                "aaaccaaa-bbbb-cccc-dddd-000000000000",
                "user0@example.com",
            ],
        ];
    }

    #[ArrayShape([
        'password doesnt exist' => "string[]",
        'user has no access' => "string[]",
    ])]
    public static function providePasswordNotFoundCases(): array
    {
        return [
            'password doesnt exist' => [
                "aaacdaaa-bbbb-cccc-dddd-999999999999",
                "admin@example.com",
            ],
            'user has no access' => [
                "aaacdaaa-bbbb-cccc-dddd-000000000041",
                "user0@example.com",
            ],
        ];
    }
}
