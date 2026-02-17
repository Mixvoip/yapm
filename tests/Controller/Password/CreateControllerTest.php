<?php

/**
 * @author bsteffan
 * @since 2026-01-29
 */

namespace App\Tests\Controller\Password;

use App\Entity\Password;
use App\Repository\PasswordRepository;
use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use Random\RandomException;

class CreateControllerTest extends WebTestCase
{
    /**
     * Test creating a password without TOTP.
     *
     * @throws RandomException
     */
    public function testCreatePasswordWithoutTotp(): void
    {
        $body = [
            'title' => 'Test Password Without TOTP',
            'vaultId' => '1aaaaaaa-bbbb-cccc-dddd-000000000000', // Development vault
            'encryptedPassword' => $this->makeEncryptedData('testpassword123'),
            'encryptedUsername' => $this->makeEncryptedData('testuser'),
            'target' => 'https://example.com',
            'description' => 'A test password',
        ];

        $this->postAsUser('/passwords', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(201);

        $response = $this->getDecodedResponse();
        $this->assertEquals('Test Password Without TOTP', $response['title']);
        $this->assertFalse($response['hasTotp']);
        $this->assertNull($response['totpPeriod']);
        $this->assertNotEmpty($response['id']);

        // Verify in database
        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $password = $passwordRepository->find($response['id']);
        $this->assertNotNull($password);
        $this->assertFalse($password->hasTotp());
        $this->assertNull($password->getEncryptedTotpSecretKey());
    }

    /**
     * Test creating a password with TOTP.
     *
     * @throws RandomException
     */
    public function testCreatePasswordWithTotp(): void
    {
        $body = [
            'title' => 'Test Password With TOTP',
            'vaultId' => '1aaaaaaa-bbbb-cccc-dddd-000000000000', // Development vault
            'encryptedPassword' => $this->makeEncryptedData('testpassword123'),
            'encryptedUsername' => $this->makeEncryptedData('testuser'),
            'target' => 'https://example.com',
            'description' => 'A test password with TOTP',
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('JBSWY3DPEHPK3PXP'),
                'algorithm' => 'sha1',
                'period' => 30,
                'digits' => 6,
            ],
        ];

        $this->postAsUser('/passwords', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(201);

        $response = $this->getDecodedResponse();
        $this->assertEquals('Test Password With TOTP', $response['title']);
        $this->assertTrue($response['hasTotp']);
        $this->assertEquals(30, $response['totpPeriod']);
        $this->assertNotEmpty($response['id']);

        // Verify in database
        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $password = $passwordRepository->find($response['id']);
        $this->assertNotNull($password);
        $this->assertTrue($password->hasTotp());
        $this->assertNotNull($password->getEncryptedTotpSecretKey());
        $this->assertNotNull($password->getTotpSecretKeyNonce());
        $this->assertEquals('sha1', $password->getTotpAlgorithm()->value);
        $this->assertEquals(30, $password->getTotpPeriod()->value);
        $this->assertEquals(6, $password->getTotpDigits()->value);
    }

    /**
     * Test creating a password with TOTP using different algorithm options.
     *
     * @throws RandomException
     */
    public function testCreatePasswordWithTotpSha256(): void
    {
        $body = [
            'title' => 'Test Password With TOTP SHA256',
            'vaultId' => '1aaaaaaa-bbbb-cccc-dddd-000000000000',
            'encryptedPassword' => $this->makeEncryptedData('testpassword123'),
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('JBSWY3DPEHPK3PXP'),
                'algorithm' => 'sha256',
                'period' => 60,
                'digits' => 8,
            ],
        ];

        $this->postAsUser('/passwords', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(201);

        $response = $this->getDecodedResponse();
        $this->assertTrue($response['hasTotp']);
        $this->assertEquals(60, $response['totpPeriod']);

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $password = $passwordRepository->find($response['id']);
        $this->assertEquals('sha256', $password->getTotpAlgorithm()->value);
        $this->assertEquals(60, $password->getTotpPeriod()->value);
        $this->assertEquals(8, $password->getTotpDigits()->value);
    }

    /**
     * Test that invalid TOTP algorithm is rejected.
     *
     * @throws RandomException
     */
    public function testCreatePasswordWithInvalidTotpAlgorithm(): void
    {
        $body = [
            'title' => 'Test Password With Invalid TOTP',
            'vaultId' => '1aaaaaaa-bbbb-cccc-dddd-000000000000',
            'encryptedPassword' => $this->makeEncryptedData('testpassword123'),
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('JBSWY3DPEHPK3PXP'),
                'algorithm' => 'md5', // Invalid algorithm
                'period' => 30,
                'digits' => 6,
            ],
        ];

        $this->postAsUser('/passwords', $body, 'admin@example.com');
        // Symfony returns 500 for enum deserialization errors
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * Test that invalid TOTP period is rejected.
     *
     * @throws RandomException
     */
    public function testCreatePasswordWithInvalidTotpPeriod(): void
    {
        $body = [
            'title' => 'Test Password With Invalid Period',
            'vaultId' => '1aaaaaaa-bbbb-cccc-dddd-000000000000',
            'encryptedPassword' => $this->makeEncryptedData('testpassword123'),
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('JBSWY3DPEHPK3PXP'),
                'algorithm' => 'sha1',
                'period' => 45, // Invalid period
                'digits' => 6,
            ],
        ];

        $this->postAsUser('/passwords', $body, 'admin@example.com');
        // Symfony returns 500 for enum deserialization errors
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * Test that invalid TOTP digits is rejected.
     *
     * @throws RandomException
     */
    public function testCreatePasswordWithInvalidTotpDigits(): void
    {
        $body = [
            'title' => 'Test Password With Invalid Digits',
            'vaultId' => '1aaaaaaa-bbbb-cccc-dddd-000000000000',
            'encryptedPassword' => $this->makeEncryptedData('testpassword123'),
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('JBSWY3DPEHPK3PXP'),
                'algorithm' => 'sha1',
                'period' => 30,
                'digits' => 10, // Invalid digits
            ],
        ];

        $this->postAsUser('/passwords', $body, 'admin@example.com');
        // Symfony returns 500 for enum deserialization errors
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * Test that TOTP with missing encryptedSecretKey is rejected.
     *
     * @throws RandomException
     */
    public function testCreatePasswordWithIncompleteTotpData(): void
    {
        $body = [
            'title' => 'Test Password With Incomplete TOTP',
            'vaultId' => '1aaaaaaa-bbbb-cccc-dddd-000000000000',
            'encryptedPassword' => $this->makeEncryptedData('testpassword123'),
            'totp' => [
                // Missing encryptedSecretKey
                'algorithm' => 'sha1',
                'period' => 30,
                'digits' => 6,
            ],
        ];

        $this->postAsUser('/passwords', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Create encrypted data payload for the server.
     *
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedData' => "string",
        'clientPublicKey' => "string",
        'nonce' => "string",
    ])]
    private function makeEncryptedData(string $plaintext): array
    {
        /** @var EncryptionService $encryptionService */
        $encryptionService = $this->container->get(EncryptionService::class);
        $encrypted = $encryptionService->encryptForServer($plaintext);

        return [
            'encryptedData' => $encrypted['encryptedData'],
            'clientPublicKey' => $encrypted['clientPublicKey'],
            'nonce' => $encrypted['nonce'],
        ];
    }
}
