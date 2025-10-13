<?php

namespace App\Tests\Controller\User;

use App\Service\Encryption\EncryptionService;
use App\Service\Utility\Base64UrlHelper;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\RandomException;

class RegisterControllerTest extends WebTestCase
{
    private readonly EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryptionService = $this->container->get(EncryptionService::class);
    }

    /**
     * @throws RandomException
     */
    public function testSuccessfulRegistration(): void
    {
        $body = $this->getValidBody();
        $user = $this->userRepository->findOneBy(['email' => "user3@example.com"]);
        $token = Base64UrlHelper::encode($user->getVerificationToken());
        $this->postAsUser("/register/$token", $body, "user3@example.com");
        $this->assertResponseStatusCodeSame(204);

        $this->assertEquals("user3@example.com", $user->getUpdatedBy());
        $this->assertNotNull($user->getPassword());
        $this->assertTrue($user->isVerified());
        $this->assertNull($user->getVerificationToken());
        $this->assertNotNull($user->getPublicKey());
        $this->assertNotNull($user->getEncryptedPrivateKey());
        $this->assertNotNull($user->getPrivateKeyNonce());
        $this->assertNotNull($user->getKeySalt());
    }

    /**
     * @throws RandomException
     */
    public function testInvalidToken(): void
    {
        $body = $this->getValidBody();
        $token = Base64UrlHelper::encode("Whatever");
        $this->postAsUser("/register/$token", $body, "user3@example.com");
        $this->assertResponse(400, ['error' => 'HTTP Error', 'message' => 'Invalid token.']);
    }

    /**
     * @throws RandomException
     */
    #[DataProvider('provideBadDtoCases')]
    public function testInvalidDto(array $body, array $expectedResponse): void
    {
        $errorCode = 422;
        if (!empty($body)) {
            $encryptedPassword = $this->encryptionService->encryptForServer($body['password']);
            $body = ['encryptedPassword' => $encryptedPassword];
            $errorCode = 400;
        }
        $user = $this->userRepository->findOneBy(['email' => "user3@example.com"]);
        $token = Base64UrlHelper::encode($user->getVerificationToken());
        $this->postAsUser("/register/$token", $body, "user3@example.com");

        $this->assertResponse($errorCode, $expectedResponse);
    }

    #[ArrayShape([
        'password wrong format' => "array",
        'password too short' => "array[]",
        'password not provided' => "array",
        'password no lowercase' => "array",
        'password no special chars' => "array",
        'password no numbers' => "array",
        'password no uppercase' => "array",
    ])]
    public static function provideBadDtoCases(): array
    {
        return [
            'password wrong format' => [
                [
                    'password' => 123,
                ],
                [
                    'error' => "HTTP Error",
                    'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number and one special character.',
                ],
            ],
            'password too short' => [
                [
                    'password' => 'fo0Bar!',
                ],
                [
                    'error' => "HTTP Error",
                    'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number and one special character.',
                ],
            ],
            'password not provided' => [
                [],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "encryptedPassword",
                            'message' => "This value should be of type App\Controller\Dto\EncryptedClientDataDto.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'password no lowercase' => [
                [
                    'password' => '  FOOBAR123! ',
                ],
                [
                    'error' => "HTTP Error",
                    'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number and one special character.',
                ],
            ],
            'password no special chars' => [
                [
                    'password' => 'fooBar123',
                ],
                [
                    'error' => "HTTP Error",
                    'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number and one special character.',
                ],
            ],
            'password no numbers' => [
                [
                    'password' => 'fooBar!@#!',
                ],
                [
                    'error' => "HTTP Error",
                    'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number and one special character.',
                ],
            ],
            'password no uppercase' => [
                [
                    'password' => 'foobar123#',
                ],
                [
                    'error' => "HTTP Error",
                    'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number and one special character.',
                ],
            ],
        ];
    }

    /**
     * @throws RandomException
     */
    #[ArrayShape(['encryptedPassword' => "array"])]
    private function getValidBody(): array
    {
        $password = "fooBar123!";

        $encryptedPassword = $this->encryptionService->encryptForServer($password);
        return [
            'encryptedPassword' => $encryptedPassword,
        ];
    }
}
