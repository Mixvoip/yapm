<?php

/**
 * @author bsteffan
 * @since 2026-02-17
 */

namespace App\Tests\Controller\TimeBasedShare;

use App\DataFixtures\TimeBasedShareFixtures;
use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class GetSensitiveDataControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testNotFoundNonExistentId(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = "bbbbbbbb-0000-0000-0000-999999999999";
        $this->postAsUser("/time-based-shares/$shareId/sensitive-data", $this->makeAuthPayload(), "admin@example.com");
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Time-based share not found.',
        ]);
    }

    public function testNotFoundExpiredShare(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::EXPIRED_SHARE_ID;
        $this->postAsUser("/time-based-shares/$shareId/sensitive-data", $this->makeAuthPayload(), "admin@example.com");
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Time-based share not found.',
        ]);
    }

    public function testNotFoundCreatorTriesToAccess(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::ACTIVE_SHARE_ID;
        // admin is the creator, not the recipient
        $this->postAsUser("/time-based-shares/$shareId/sensitive-data", $this->makeAuthPayload(), "admin@example.com");
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Time-based share not found.',
        ]);
    }

    public function testSuccessAsRecipient(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::ACTIVE_SHARE_ID;
        // user0 is the recipient
        $this->postAsUser(
            "/time-based-shares/$shareId/sensitive-data",
            $this->makeAuthPayload('password123'),
            "user0@example.com"
        );
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();

        // Encrypted password data from fixture
        $this->assertEquals('fake-encrypted-password', $response['password']['encryptedData']);
        $this->assertEquals('fake-nonce', $response['password']['nonce']);
        $this->assertEquals('fake-public-key', $response['password']['encryptionPublicKey']);

        // Encrypted username data from fixture
        $this->assertNotNull($response['username']);
        $this->assertEquals('fake-encrypted-username', $response['username']['encryptedData']);
        $this->assertEquals('fake-username-nonce', $response['username']['nonce']);
        $this->assertEquals('fake-username-public-key', $response['username']['encryptionPublicKey']);

        // User keys are present with auth method
        $this->assertEquals('password', $response['userKeys']['authMethod']);
        $this->assertArrayHasKey('privateKey', $response['userKeys']);
        $this->assertArrayHasKey('nonce', $response['userKeys']);
        $this->assertArrayHasKey('salt', $response['userKeys']);
    }

    /**
     * @param  string  $password
     *
     * @return array
     */
    private function makeAuthPayload(string $password = 'InThePassw0rdManager'): array
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
}
