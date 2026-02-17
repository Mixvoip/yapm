<?php

/**
 * @author bsteffan
 * @since 2026-01-29
 */

namespace App\Tests\Controller\Password;

use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use Random\RandomException;

class GetTotpDataControllerTest extends WebTestCase
{
    /**
     * Test that a non-existent password returns 404.
     *
     * @throws RandomException
     */
    public function testPasswordNotFound(): void
    {
        $passwordId = 'aaaaaaaa-bbbb-cccc-dddd-999999999999';

        $body = $this->makePwdPayload();

        $this->postAsUser("/passwords/$passwordId/totp", $body, 'admin@example.com');
        $this->assertResponse(
            404,
            [
                'error' => "Resource not found",
                'message' => "Password with id: $passwordId not found.",
            ]
        );
    }

    /**
     * Test that a user without access to the password gets 404.
     *
     * @throws RandomException
     */
    public function testUserWithoutAccess(): void
    {
        // This password is only accessible by admin group
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000041';

        $body = $this->makePwdPayload();

        $this->postAsUser("/passwords/$passwordId/totp", $body, 'user0@example.com');
        $this->assertResponse(
            404,
            [
                'error' => "Resource not found",
                'message' => "Password with id: $passwordId not found.",
            ]
        );
    }

    /**
     * Test that a password without TOTP returns null.
     *
     * @throws RandomException
     */
    public function testPasswordWithoutTotp(): void
    {
        // This password exists and user has access, but no TOTP is configured
        $passwordId = 'aaaccaaa-bbbb-cccc-dddd-000000000000';

        $body = $this->makePwdPayload();

        $this->postAsUser("/passwords/$passwordId/totp", $body, 'admin@example.com');
        $this->assertResponse(
            200,
            [
                'totp' => null,
            ]
        );
    }

    /**
     * Test successful TOTP data retrieval with decryption.
     *
     * @throws RandomException
     */
    public function testSuccessfulTotpRetrieval(): void
    {
        // This password has TOTP from fixtures (i=2 in yapm folder)
        $passwordId = 'aaacdaaa-bbbb-cccc-dddd-000000000002';

        $body = $this->makePwdPayload();

        $this->postAsUser("/passwords/$passwordId/totp", $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();

        // Verify TOTP data structure
        $this->assertArrayHasKey('totp', $response);
        $this->assertNotNull($response['totp']);
        $this->assertArrayHasKey('secretKey', $response['totp']);
        $this->assertArrayHasKey('algorithm', $response['totp']);
        $this->assertArrayHasKey('period', $response['totp']);
        $this->assertArrayHasKey('digits', $response['totp']);

        // Verify TOTP metadata values match fixture configuration
        $this->assertEquals('sha1', $response['totp']['algorithm']);
        $this->assertEquals(30, $response['totp']['period']);
        $this->assertEquals(6, $response['totp']['digits']);

        // Verify re-encrypted secret key structure
        $this->assertArrayHasKey('encryptedData', $response['totp']['secretKey']);
        $this->assertArrayHasKey('nonce', $response['totp']['secretKey']);
        $this->assertNotEmpty($response['totp']['secretKey']['encryptedData']);
        $this->assertNotEmpty($response['totp']['secretKey']['nonce']);

        // Verify userKeys structure
        $this->assertArrayHasKey('userKeys', $response);
        $this->assertArrayHasKey('privateKey', $response['userKeys']);
        $this->assertArrayHasKey('nonce', $response['userKeys']);
        $this->assertArrayHasKey('salt', $response['userKeys']);
        $this->assertNotEmpty($response['userKeys']['privateKey']);
        $this->assertNotEmpty($response['userKeys']['nonce']);
        $this->assertNotEmpty($response['userKeys']['salt']);
    }

    /**
     * Create an encrypted password payload for authentication.
     *
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedData' => "string",
        'clientPublicKey' => "string",
        'nonce' => "string",
    ])]
    private function makePwdPayload(): array
    {
        /** @var EncryptionService $encryptionService */
        $encryptionService = $this->container->get(EncryptionService::class);
        $encrypted = $encryptionService->encryptForServer("InThePassw0rdManager");

        return [
            'encryptedData' => $encrypted['encryptedData'],
            'clientPublicKey' => $encrypted['clientPublicKey'],
            'nonce' => $encrypted['nonce'],
        ];
    }
}
