<?php

/**
 * @author bsteffan
 * @since 2026-02-18
 */

namespace App\Tests\Controller\TimeBasedShare;

use App\Entity\TimeBasedShare;
use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class CreateControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'admin@example.com';
    private const string ADMIN_ID = 'aaaaaaaa-bbbb-cccc-dddd-a00000000000';
    private const string USER0_EMAIL = 'user0@example.com';
    private const string USER1_ID = 'aaaaaaaa-bbbb-cccc-dddd-000000000001';
    private const string USER2_ID = 'aaaaaaaa-bbbb-cccc-dddd-000000000002';
    private const string USER3_ID = 'aaaaaaaa-bbbb-cccc-dddd-000000000003';
    private const string DEV_VAULT_PASSWORD_ID = 'aaaddaaa-bbbb-cccc-dddd-000000000000';

    /**
     * @param  string  $password
     *
     * @return array
     */
    private function makeEncryptedPassword(string $password = 'InThePassw0rdManager'): array
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
     * @param  array  $overrides
     *
     * @return array
     */
    private function makeCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'passwordId' => self::DEV_VAULT_PASSWORD_ID,
            'recipientId' => self::USER1_ID,
            'duration' => '1h',
            'authData' => $this->makeEncryptedPassword(),
        ], $overrides);
    }

    public function testSuccessfulCreate(): void
    {
        $data = $this->makeCreatePayload();
        $this->postAsUser('/time-based-shares', $data, self::ADMIN_EMAIL);
        $this->assertResponseStatusCodeSame(201);

        $response = $this->getDecodedResponse();
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('Root Vault Password 0', $response['passwordTitle']);
        $this->assertEquals(self::ADMIN_ID, $response['sharedBy']['id']);
        $this->assertEquals(self::USER1_ID, $response['sharedWith']['id']);
        $this->assertArrayHasKey('expiresAt', $response);
        $this->assertNull($response['accessedAt']);
        $this->assertArrayHasKey('createdAt', $response);
    }

    public function testSelfShareRejection(): void
    {
        $data = $this->makeCreatePayload(['recipientId' => self::ADMIN_ID]);
        $this->postAsUser('/time-based-shares', $data, self::ADMIN_EMAIL);
        $this->assertResponse(400, [
            'error' => 'Validation Error',
            'message' => 'You cannot create a time-based share with yourself.',
        ]);
    }

    public function testPasswordNotFound(): void
    {
        $data = $this->makeCreatePayload([
            'authData' => $this->makeEncryptedPassword('password123'),
        ]);
        $this->postAsUser('/time-based-shares', $data, self::USER0_EMAIL);
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Password with id: ' . self::DEV_VAULT_PASSWORD_ID . ' not found.',
        ]);
    }

    public function testInactiveRecipient(): void
    {
        $data = $this->makeCreatePayload(['recipientId' => self::USER2_ID]);
        $this->postAsUser('/time-based-shares', $data, self::ADMIN_EMAIL);
        $this->assertResponse(400, [
            'error' => 'Validation Error',
            'message' => 'Recipient user not found or is inactive.',
        ]);
    }

    public function testNonExistentRecipient(): void
    {
        $data = $this->makeCreatePayload(['recipientId' => '99999999-9999-9999-9999-999999999999']);
        $this->postAsUser('/time-based-shares', $data, self::ADMIN_EMAIL);
        $this->assertResponse(400, [
            'error' => 'Validation Error',
            'message' => 'Recipient user not found or is inactive.',
        ]);
    }

    public function testRecipientWithoutPublicKey(): void
    {
        $data = $this->makeCreatePayload(['recipientId' => self::USER3_ID]);
        $this->postAsUser('/time-based-shares', $data, self::ADMIN_EMAIL);
        $this->assertResponse(400, [
            'error' => 'Validation Error',
            'message' => 'Recipient user has not completed setup.',
        ]);
    }

    public function testDuplicateActiveShare(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        $admin = $this->userRepository->findOneBy(['email' => self::ADMIN_EMAIL]);
        $user1 = $this->userRepository->findOneBy(['email' => 'user1@example.com']);

        // Create an existing active share directly in the DB
        $existingShare = new TimeBasedShare();
        $existingShare->setSharedBy($admin)
                      ->setSharedWith($user1)
                      ->setPasswordTitle('Test')
                      ->setSourcePasswordId(self::DEV_VAULT_PASSWORD_ID)
                      ->setEncryptedPassword('fake')
                      ->setPasswordNonce('fake')
                      ->setPasswordEncryptionPublicKey('fake')
                      ->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $entityManager->persist($existingShare);
        $entityManager->flush();

        $data = $this->makeCreatePayload();
        $this->postAsUser('/time-based-shares', $data, self::ADMIN_EMAIL);
        $this->assertResponse(409, [
            'error' => 'Conflict',
            'message' => 'An active share for this password already exists for this recipient.',
        ]);
    }

    public function testInvalidMasterPassword(): void
    {
        $data = $this->makeCreatePayload([
            'authData' => $this->makeEncryptedPassword('wrong_password'),
        ]);
        $this->postAsUser('/time-based-shares', $data, self::ADMIN_EMAIL);
        $this->assertResponse(401, [
            'error' => 'Authentication Error',
            'message' => 'Invalid password.',
        ]);
    }
}
