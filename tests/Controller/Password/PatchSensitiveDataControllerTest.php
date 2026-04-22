<?php

/**
 * @author bsteffan
 * @since 2026-01-29
 */

namespace App\Tests\Controller\Password;

use App\Entity\Enums\TotpAlgorithm;
use App\Entity\Enums\TotpDigits;
use App\Entity\Enums\TotpPeriod;
use App\Entity\Password;
use App\Repository\PasswordRepository;
use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use Random\RandomException;

class PatchSensitiveDataControllerTest extends WebTestCase
{
    /**
     * Test that password not found returns 404.
     *
     * @throws RandomException
     */
    public function testPasswordNotFound(): void
    {
        $passwordId = 'aaaaaaaa-bbbb-cccc-dddd-999999999999';

        $body = [
            'authData' => $this->makePwdPayload(),
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'admin@example.com');
        $this->assertResponse(
            404,
            [
                'error' => "Resource not found",
                'message' => "Password with id: $passwordId not found.",
            ]
        );
    }

    /**
     * Test that user without write access gets 403.
     *
     * @throws RandomException
     */
    public function testUserWithoutWriteAccess(): void
    {
        // Use a password where user0 has read-only access (via Users group)
        $passwordId = 'aaaccaaa-bbbb-cccc-dddd-000000000000';

        $body = [
            'authData' => $this->makePwdPayload('user0password'),
            'encryptedPassword' => $this->makeEncryptedData('newpassword'),
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'user0@example.com');
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Test patching with no changes returns 204.
     *
     * @throws RandomException
     */
    public function testPatchWithNoChanges(): void
    {
        // Use an existing password from fixtures that admin has write access to
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        $body = [
            'authData' => $this->makePwdPayload(),
            // No encryptedPassword, encryptedUsername, or totp provided
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * Test adding TOTP to a password that doesn't have one.
     *
     * @throws RandomException
     */
    public function testAddTotpToPassword(): void
    {
        // Use an existing password from fixtures (no TOTP by default)
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $passwordBefore = $passwordRepository->find($passwordId);
        $this->assertFalse($passwordBefore->hasTotp(), 'Password should not have TOTP initially');

        $body = [
            'authData' => $this->makePwdPayload(),
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('JBSWY3DPEHPK3PXP'),
                'algorithm' => 'sha1',
                'period' => 30,
                'digits' => 6,
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        // Verify TOTP was added
        $password = $passwordRepository->find($passwordId);
        $this->assertTrue($password->hasTotp());
        $this->assertNotNull($password->getEncryptedTotpSecretKey());
        $this->assertEquals(TotpAlgorithm::Sha1, $password->getTotpAlgorithm());
        $this->assertEquals(TotpPeriod::Thirty, $password->getTotpPeriod());
        $this->assertEquals(TotpDigits::Six, $password->getTotpDigits());
    }

    /**
     * Test adding TOTP with sha256 algorithm.
     *
     * @throws RandomException
     */
    public function testAddTotpWithSha256(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000042';

        $body = [
            'authData' => $this->makePwdPayload(),
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('NEWSECRET'),
                'algorithm' => 'sha256',
                'period' => 60,
                'digits' => 8,
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $password = $passwordRepository->find($passwordId);
        $this->assertTrue($password->hasTotp());
        $this->assertEquals(TotpAlgorithm::Sha256, $password->getTotpAlgorithm());
        $this->assertEquals(TotpPeriod::Sixty, $password->getTotpPeriod());
        $this->assertEquals(TotpDigits::Eight, $password->getTotpDigits());
    }

    /**
     * Test setting totp to null clears it (even if no TOTP was set before).
     *
     * @throws RandomException
     */
    public function testSetTotpToNullOnPasswordWithoutTotp(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000050';

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $passwordBefore = $passwordRepository->find($passwordId);
        $this->assertFalse($passwordBefore->hasTotp(), 'Password should not have TOTP initially');

        $body = [
            'authData' => $this->makePwdPayload(),
            'totp' => null,
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        // Password should still have no TOTP
        $password = $passwordRepository->find($passwordId);
        $this->assertFalse($password->hasTotp());
    }

    /**
     * Test clearing TOTP from a password that has TOTP configured.
     *
     * @throws RandomException
     */
    public function testClearTotpFromPasswordWithTotp(): void
    {
        // This password has TOTP from fixtures (i=2 in yapm folder)
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000002';

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $passwordBefore = $passwordRepository->find($passwordId);
        $this->assertTrue($passwordBefore->hasTotp(), 'Password should have TOTP initially');

        $body = [
            'authData' => $this->makePwdPayload(),
            'totp' => null,
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        // Verify all TOTP fields are cleared
        $password = $passwordRepository->find($passwordId);
        $this->assertFalse($password->hasTotp());
        $this->assertNull($password->getEncryptedTotpSecretKey());
        $this->assertNull($password->getTotpSecretKeyNonce());
        $this->assertNull($password->getTotpAlgorithm());
        $this->assertNull($password->getTotpPeriod());
        $this->assertNull($password->getTotpDigits());
    }

    /**
     * Test clearing username while adding TOTP in same request.
     *
     * @throws RandomException
     */
    public function testClearUsernameAndAddTotpSimultaneously(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000051';

        $body = [
            'authData' => $this->makePwdPayload(),
            'encryptedUsername' => null,
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('SECRETKEY'),
                'algorithm' => 'sha512',
                'period' => 30,
                'digits' => 6,
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $password = $passwordRepository->find($passwordId);
        $this->assertNull($password->getEncryptedUsername());
        $this->assertTrue($password->hasTotp());
        $this->assertEquals(TotpAlgorithm::Sha512, $password->getTotpAlgorithm());
    }

    /**
     * Test that invalid TOTP data in patch is rejected.
     *
     * @throws RandomException
     */
    public function testPatchWithInvalidTotpAlgorithm(): void
    {
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        $body = [
            'authData' => $this->makePwdPayload(),
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('SECRETKEY'),
                'algorithm' => 'invalid', // Invalid algorithm
                'period' => 30,
                'digits' => 6,
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'admin@example.com');
        // Symfony returns 500 for enum deserialization errors
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * Test patching password and TOTP simultaneously.
     *
     * @throws RandomException
     */
    public function testPatchPasswordAndTotpSimultaneously(): void
    {
        // Use an existing password from fixtures
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000052';

        // Update password and add TOTP in the same request
        $body = [
            'authData' => $this->makePwdPayload(),
            'encryptedPassword' => $this->makeEncryptedData('newpassword'),
            'totp' => [
                'encryptedSecretKey' => $this->makeEncryptedData('NEWSECRET'),
                'algorithm' => 'sha1',
                'period' => 30,
                'digits' => 6,
            ],
        ];

        $this->patchAsUser("/passwords/$passwordId/sensitive", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(204);

        // Verify both were updated
        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $this->container->get('doctrine')->getRepository(Password::class);
        $password = $passwordRepository->find($passwordId);
        $this->assertNotNull($password->getEncryptedPassword());
        $this->assertTrue($password->hasTotp());
    }

    /**
     * Create an encrypted password payload for user authentication.
     *
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedData' => "string",
        'clientPublicKey' => "string",
        'nonce' => "string",
    ])]
    private function makePwdPayload(string $password = "InThePassw0rdManager"): array
    {
        /** @var EncryptionService $encryptionService */
        $encryptionService = $this->container->get(EncryptionService::class);
        $encrypted = $encryptionService->encryptForServer($password);

        return [
            'encryptedPassword' => [
                'encryptedData' => $encrypted['encryptedData'],
                'clientPublicKey' => $encrypted['clientPublicKey'],
                'nonce' => $encrypted['nonce'],
            ],
        ];
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
