<?php

/**
 * @author bsteffan
 * @since 2025-11-24
 */

namespace App\Tests\Controller\User;

use App\Entity\Group;
use App\Entity\RefreshToken;
use App\Entity\Vault;
use App\Repository\GroupRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\VaultRepository;
use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;

class ResetControllerTest extends WebTestCase
{
    /**
     * Test that a non-admin user cannot reset another user.
     *
     * @return void
     * @throws RandomException
     */
    public function testAccessDenied(): void
    {
        $body = $this->makePasswordPayload();
        $this->postAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-000000000000/reset", $body, "user0@example.com");
        $this->assertAccessDenied();
    }

    /**
     * Test that invalid password returns authentication error.
     *
     * @return void
     * @throws RandomException
     */
    public function testInvalidPassword(): void
    {
        $body = $this->makePasswordPayload("WrongPassword123!");
        $this->postAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-000000000000/reset", $body, "admin@example.com");
        $this->assertResponseStatusCodeSame(401);
        $response = $this->getDecodedResponse();
        $this->assertEquals("Authentication Error", $response['error']);
    }

    /**
     * Test that resetting a non-existent user returns 404.
     *
     * @return void
     * @throws RandomException
     */
    public function testUserNotFound(): void
    {
        $userId = "aaaaaaaa-bbbb-cccc-dddd-999999999999";
        $body = $this->makePasswordPayload();
        $this->postAsUser("/users/$userId/reset", $body, "admin@example.com");
        $this->assertUserNotFound($userId);
    }

    /**
     * Test that admin cannot reset their own account.
     *
     * @return void
     * @throws RandomException
     */
    public function testCannotResetSelf(): void
    {
        $body = $this->makePasswordPayload();
        $this->postAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-a00000000000/reset", $body, "admin@example.com");
        $this->assertResponseStatusCodeSame(403);
        $response = $this->getDecodedResponse();
        $this->assertEquals("Access Denied", $response['error']);
        if (isset($response['message'])) {
            $this->assertStringContainsString("cannot reset your own account", $response['message']);
        }
    }

    /**
     * Test that unverified user cannot be reset.
     *
     * @return void
     * @throws RandomException
     */
    public function testCannotResetUnverifiedUser(): void
    {
        $userId = "aaaaaaaa-bbbb-cccc-dddd-000000000003"; // user3 (unverified user from fixtures)
        $body = $this->makePasswordPayload();
        $this->postAsUser("/users/$userId/reset", $body, "admin@example.com");
        $this->assertResponseStatusCodeSame(400);
        $response = $this->getDecodedResponse();
        $this->assertEquals("Bad Request", $response['error']);
        $this->assertStringContainsString("not verified", $response['message']);
    }

    /**
     * Test successful user reset.
     *
     * @return void
     * @throws RandomException
     */
    public function testSuccessfulReset(): void
    {
        $userId = "aaaaaaaa-bbbb-cccc-dddd-000000000000"; // user0
        $userEmail = "user0@example.com";

        // Mock EmailService to avoid sending actual emails in tests
        $emailServiceMock = $this->createMock(\App\Service\EmailService::class);
        $emailServiceMock->expects($this->once())
                         ->method('sendInvitationEmail');
        $this->container->set(\App\Service\EmailService::class, $emailServiceMock);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        // Verify user exists and has encrypted keys before reset
        $userBefore = $this->userRepository->find($userId);
        $this->assertNotNull($userBefore);
        $this->assertTrue($userBefore->isVerified());
        $this->assertNotNull($userBefore->getPassword());
        $this->assertNotNull($userBefore->getPublicKey());
        $this->assertNotNull($userBefore->getEncryptedPrivateKey());

        // Verify private vault exists
        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $entityManager->getRepository(Vault::class);
        $privateVaultBefore = $vaultRepository->findOneBy(['user' => $userId]);
        $this->assertNotNull($privateVaultBefore, "Private vault should exist before reset");

        // Verify private group exists
        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $privateGroupBefore = $groupRepository->findOneBy([
            'name' => "user-" . $userId,
            'private' => true,
        ]);
        $this->assertNotNull($privateGroupBefore, "Private group should exist before reset");

        // Perform reset
        $body = $this->makePasswordPayload();
        $this->postAsUser("/users/$userId/reset", $body, "admin@example.com");
        $this->assertResponseStatusCodeSame(204);

        // Clear entity manager to force fresh database queries
        $entityManager->clear();

        // Verify user is reset to invited state
        $userAfter = $this->userRepository->find($userId);
        $this->assertNotNull($userAfter);
        $this->assertFalse($userAfter->isVerified(), "User should not be verified after reset");
        $this->assertNull($userAfter->getPassword(), "Password should be null after reset");
        $this->assertNotNull($userAfter->getVerificationToken(), "Verification token should be set");
        $this->assertNull($userAfter->getPublicKey(), "Public key should be null after reset");
        $this->assertNull($userAfter->getEncryptedPrivateKey(), "Encrypted private key should be null after reset");
        $this->assertNull($userAfter->getPrivateKeyNonce(), "Private key nonce should be null after reset");
        $this->assertNull($userAfter->getKeySalt(), "Key salt should be null after reset");
        $this->assertTrue($userAfter->isActive(), "User should be active after reset");
        $this->assertEquals("admin@example.com", $userAfter->getUpdatedBy());

        // Verify private vault is deleted
        $privateVaultAfter = $vaultRepository->findOneBy(['user' => $userId]);
        $this->assertNull($privateVaultAfter, "Private vault should be deleted after reset");

        // Verify private group is deleted
        $privateGroupAfter = $groupRepository->findOneBy([
            'name' => "user-" . $userId,
            'private' => true,
        ]);
        $this->assertNull($privateGroupAfter, "Private group should be deleted after reset");

        // Verify refresh tokens are invalidated
        /** @var RefreshTokenRepository $refreshTokenRepository */
        $refreshTokenRepository = $entityManager->getRepository(RefreshToken::class);
        $refreshTokens = $refreshTokenRepository->findBy(['username' => $userEmail]);
        foreach ($refreshTokens as $refreshToken) {
            $this->assertNull($refreshToken->getValid(), "Refresh tokens should be invalidated");
        }

        // Verify user record still exists (not deleted)
        $this->assertNotNull($userAfter, "User entity should still exist after reset");
    }

    /**
     * Create encrypted password payload for admin user.
     *
     * @param  string  $password
     *
     * @return array
     * @throws RandomException
     */
    private function makePasswordPayload(string $password = "InThePassw0rdManager"): array
    {
        /** @var EncryptionService $encryptionService */
        $encryptionService = $this->container->get(EncryptionService::class);
        $encrypted = $encryptionService->encryptForServer($password);

        return [
            'encryptedData' => $encrypted['encryptedData'],
            'clientPublicKey' => $encrypted['clientPublicKey'],
            'nonce' => $encrypted['nonce'],
        ];
    }
}
