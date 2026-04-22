<?php

/**
 * @author bsteffan
 * @since 2026-03-12
 */

namespace App\Tests\Controller\User;

use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\RandomException;

class ChangePasswordControllerTest extends WebTestCase
{
    private readonly EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryptionService = $this->container->get(EncryptionService::class);
    }

    /**
     * Test successful password change.
     *
     * @return void
     * @throws RandomException
     */
    public function testSuccessfulPasswordChange(): void
    {
        $userEmail = "user0@example.com";
        $currentPassword = "password123";
        $newPassword = "NewPassw0rd!";

        $user = $this->userRepository->findOneBy(['email' => $userEmail]);
        $oldKeySalt = $user->getKeySalt();
        $oldEncryptedPrivateKey = $user->getEncryptedPrivateKey();
        $oldPrivateKeyNonce = $user->getPrivateKeyNonce();

        $body = $this->makeChangePasswordPayload($currentPassword, $newPassword);
        $this->postAsUser("/change-password", $body, $userEmail);
        $this->assertResponseStatusCodeSame(204);

        // Verify encryption fields were updated
        $this->assertNotEquals($oldKeySalt, $user->getKeySalt());
        $this->assertNotEquals($oldEncryptedPrivateKey, $user->getEncryptedPrivateKey());
        $this->assertNotEquals($oldPrivateKeyNonce, $user->getPrivateKeyNonce());
        $this->assertEquals($userEmail, $user->getUpdatedBy());

        // Verify the new password can decrypt the private key
        $decryptedPrivateKey = $this->encryptionService->decryptUserPrivateKey(
            $newPassword,
            $user->getEncryptedPrivateKey(),
            $user->getPrivateKeyNonce(),
            $user->getKeySalt()
        );
        $this->assertNotEmpty($decryptedPrivateKey);
    }

    /**
     * Test that old password no longer works after change.
     *
     * @return void
     * @throws RandomException
     */
    public function testOldPasswordNoLongerWorks(): void
    {
        $userEmail = "user1@example.com";
        $currentPassword = "password123";
        $newPassword = "NewPassw0rd!";

        $body = $this->makeChangePasswordPayload($currentPassword, $newPassword);
        $this->postAsUser("/change-password", $body, $userEmail);
        $this->assertResponseStatusCodeSame(204);

        $user = $this->userRepository->findOneBy(['email' => $userEmail]);

        // Old password should fail decryption
        $this->expectException(\RuntimeException::class);
        $this->encryptionService->decryptUserPrivateKey(
            $currentPassword,
            $user->getEncryptedPrivateKey(),
            $user->getPrivateKeyNonce(),
            $user->getKeySalt()
        );
    }

    /**
     * Test that the user's public key remains unchanged.
     *
     * @return void
     * @throws RandomException
     */
    public function testPublicKeyUnchanged(): void
    {
        $userEmail = "user0@example.com";
        $user = $this->userRepository->findOneBy(['email' => $userEmail]);
        $publicKeyBefore = $user->getPublicKey();

        $body = $this->makeChangePasswordPayload("password123", "NewPassw0rd!");
        $this->postAsUser("/change-password", $body, $userEmail);
        $this->assertResponseStatusCodeSame(204);

        $this->assertEquals($publicKeyBefore, $user->getPublicKey());
    }

    /**
     * Test wrong current password returns authentication error.
     *
     * @return void
     * @throws RandomException
     */
    public function testWrongCurrentPassword(): void
    {
        $body = $this->makeChangePasswordPayload("WrongPassword123!", "NewPassw0rd!");
        $this->postAsUser("/change-password", $body, "user0@example.com");
        $this->assertResponseStatusCodeSame(401);

        $response = $this->getDecodedResponse();
        $this->assertEquals("Authentication Error", $response['error']);
    }

    /**
     * Test weak new password is rejected.
     *
     * @return void
     * @throws RandomException
     */
    #[DataProvider('provideWeakPasswords')]
    public function testWeakNewPasswordRejected(string $weakPassword): void
    {
        $body = $this->makeChangePasswordPayload("password123", $weakPassword);
        $this->postAsUser("/change-password", $body, "user0@example.com");
        $this->assertResponse(400, [
            'error' => "HTTP Error",
            'message' => "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number and one special character.",
        ]);
    }

    #[ArrayShape([
        'too short' => "array",
        'no uppercase' => "array",
        'no lowercase' => "array",
        'no number' => "array",
        'no special char' => "array",
    ])]
    public static function provideWeakPasswords(): array
    {
        return [
            'too short' => ['fB1!abc'],
            'no uppercase' => ['foobar123!'],
            'no lowercase' => ['FOOBAR123!'],
            'no number' => ['fooBar!@#!'],
            'no special char' => ['fooBar1234'],
        ];
    }

    /**
     * Test unauthenticated request returns 401.
     *
     * @return void
     * @throws RandomException
     */
    public function testUnauthenticatedRequest(): void
    {
        $body = $this->makeChangePasswordPayload("password123", "NewPassw0rd!");
        $this->client->request(
            "POST",
            $this->apiBaseUri . "/change-password",
            [],
            [],
            ['CONTENT_TYPE' => "application/json"],
            json_encode($body)
        );
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Test invalid DTO returns 422.
     *
     * @return void
     */
    public function testInvalidDto(): void
    {
        $this->postAsUser("/change-password", [], "user0@example.com");
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Test admin user can change their own password.
     *
     * @return void
     * @throws RandomException
     */
    public function testAdminCanChangePassword(): void
    {
        $body = $this->makeChangePasswordPayload("InThePassw0rdManager", "NewAdm1nPass!");
        $this->postAsUser("/change-password", $body, "admin@example.com");
        $this->assertResponseStatusCodeSame(204);

        $admin = $this->userRepository->findOneBy(['email' => "admin@example.com"]);
        $decryptedPrivateKey = $this->encryptionService->decryptUserPrivateKey(
            "NewAdm1nPass!",
            $admin->getEncryptedPrivateKey(),
            $admin->getPrivateKeyNonce(),
            $admin->getKeySalt()
        );
        $this->assertNotEmpty($decryptedPrivateKey);
    }

    /**
     * Create the request payload for changing password.
     *
     * @param  string  $currentPassword
     * @param  string  $newPassword
     *
     * @return array
     * @throws RandomException
     */
    private function makeChangePasswordPayload(string $currentPassword, string $newPassword): array
    {
        $encryptedCurrentPassword = $this->encryptionService->encryptForServer($currentPassword);
        $encryptedNewPassword = $this->encryptionService->encryptForServer($newPassword);

        return [
            'authData' => [
                'encryptedPassword' => [
                    'encryptedData' => $encryptedCurrentPassword['encryptedData'],
                    'clientPublicKey' => $encryptedCurrentPassword['clientPublicKey'],
                    'nonce' => $encryptedCurrentPassword['nonce'],
                ],
            ],
            'newEncryptedPassword' => [
                'encryptedData' => $encryptedNewPassword['encryptedData'],
                'clientPublicKey' => $encryptedNewPassword['clientPublicKey'],
                'nonce' => $encryptedNewPassword['nonce'],
            ],
        ];
    }
}
