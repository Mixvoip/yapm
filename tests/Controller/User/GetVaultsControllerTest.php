<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Tests\Controller\User;

use App\Entity\Vault;
use App\Normalizer\VaultNormalizer;
use App\Repository\VaultRepository;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;

class GetVaultsControllerTest extends WebTestCase
{
    #[DataProvider('provideUserNotFoundCases')]
    public function testUserNotFound(string $userId, string $userEmail): void
    {
        $this->getAsUser("/users/$userId/vaults", [], $userEmail);
        $this->assertUserNotFound($userId);
    }

    #[DataProvider('provideSuccessfulGetCases')]
    public function testSuccessfulGet(array $vaultIds, string $userId, string $userEmail): void
    {
        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $this->container->get("doctrine")->getRepository(Vault::class);
        $vaults = $vaultRepository->findBy(['id' => $vaultIds]);
        usort($vaults, function (Vault $a, Vault $b) {
            return strcmp($a->getName(), $b->getName());
        });
        $expectedResponse = [];
        $normalizer = $this->container->get("serializer");
        foreach ($vaults as $vault) {
            $expectedResponse[] = $normalizer->normalize(
                $vault,
                context: [
                    VaultNormalizer::MINIMISED,
                    VaultNormalizer::WITH_GROUPS,
                    VaultNormalizer::WITH_MANDATORY_FIELDS,
                ]
            );
        }
        $this->getAsUser("/users/$userId/vaults", [], $userEmail);
        $this->assertResponse(200, $expectedResponse);
    }

    #[ArrayShape([
        'admin access' => "array",
        'user accesses himself' => "array",
        'different user' => "array",
    ])]
    public static function provideSuccessfulGetCases(): array
    {
        return [
            'admin access' => [
                [
                    "0aaaaaaa-bbbb-cccc-dddd-000000000000",
                    "22222222-bbbb-cccc-dddd-000000000000",
                ],
                "aaaaaaaa-bbbb-cccc-dddd-000000000000",
                "admin@example.com",
            ],
            'user accesses himself' => [
                [
                    "0aaaaaaa-bbbb-cccc-dddd-000000000000",
                    "22222222-bbbb-cccc-dddd-000000000000",
                ],
                "aaaaaaaa-bbbb-cccc-dddd-000000000000",
                "user0@example.com",
            ],
            'different user' => [
                [
                    "0aaaaaaa-bbbb-cccc-dddd-000000000000",
                    "1aaaaaaa-bbbb-cccc-dddd-000000000000",
                    "22222222-bbbb-cccc-dddd-000000000020",
                ],
                "aaaaaaaa-bbbb-cccc-dddd-000000000020",
                "dev@example.com",
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
