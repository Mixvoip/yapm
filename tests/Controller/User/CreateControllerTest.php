<?php

namespace App\Tests\Controller\User;

use App\Normalizer\UserNormalizer;
use App\Service\EmailService;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use Symfony\Component\Mailer\Exception\TransportException;

class CreateControllerTest extends WebTestCase
{
    private EmailService $emailServiceMock;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->emailServiceMock = $this->createMock(EmailService::class);
        $this->container->set(EmailService::class, $this->emailServiceMock);
    }

    public function testAccessDenied(): void
    {
        $body = $this->getValidBody();
        $this->postAsUser("/users", $body, "user0@example.com");
        $this->assertAccessDenied();
    }

    public function testSuccessfulCreate(): void
    {
        $body = $this->getValidBody();
        $this->postAsUser("/users", $body, "admin@example.com");
        $user = $this->userRepository->findOneBy(['email' => "test@example.com"]);
        $expectedResponse = $this->container->get("serializer")->normalize(
            $user,
            context: [UserNormalizer::WITH_GROUPS]
        );
        $this->assertResponse(201, $expectedResponse);
    }

    public function testEmailNotSent(): void
    {
        $this->emailServiceMock->expects($this->once())
                               ->method("sendInvitationEmail")
                               ->willThrowException(new TransportException("Test exception"));
        $body = $this->getValidBody();
        $this->postAsUser("/users", $body, "admin@example.com");
        $this->assertResponse(502, ['error' => "Test exception", 'message' => "Failed to send email."]);
    }

    public function testDuplicateEmail(): void
    {
        $this->emailServiceMock->expects($this->never())
                               ->method("sendInvitationEmail");
        $body = $this->getValidBody();
        $body['email'] = "admin@example.com";
        $this->postAsUser("/users", $body, "admin@example.com");
        $this->assertResponse(400, ['error' => "HTTP Error", 'message' => "Unable to create user."]);
    }

    public function testDuplicateUsername(): void
    {
        $this->emailServiceMock->expects($this->never())
                               ->method("sendInvitationEmail");
        $body = $this->getValidBody();
        $body['username'] = "admin";
        $this->postAsUser("/users", $body, "admin@example.com");
        $this->assertResponse(400, ['error' => "HTTP Error", 'message' => "Unable to create user."]);
    }

    #[DataProvider('provideInvalidDtoCases')]
    public function testInvalidDto(array $body, array $expectedResponse): void
    {
        $this->emailServiceMock->expects($this->never())
                               ->method("sendInvitationEmail");
        $this->postAsUser("/users", $body, "admin@example.com");
        $this->assertResponse(422, $expectedResponse);
    }

    #[ArrayShape([
        'email wrong format' => "array",
        'email not provided' => "array",
        'username not valid' => "array",
        'username not provided' => "array",
        'admin wrong format' => "array",
        'admin not provided' => "array",
    ])]
    public static function provideInvalidDtoCases(): array
    {
        return [
            'email wrong format' => [
                [
                    'email' => "test.com",
                    'username' => "test",
                    'admin' => false,
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "email",
                            'message' => "This value is not a valid email address.",
                            'code' => "bd79c0ab-ddba-46cc-a703-a7a4b08de310",
                        ],
                    ],
                ],
            ],
            'email not provided' => [
                [
                    'username' => "test",
                    'admin' => false,
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "email",
                            'message' => "This value should be of type string.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'username not valid' => [
                [
                    'email' => "test@example.com",
                    'username' => 123,
                    'admin' => false,
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "username",
                            'message' => "This value should be of type string.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'username not provided' => [
                [
                    'email' => "test@example.com",
                    'admin' => false,
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "username",
                            'message' => "This value should be of type string.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'admin wrong format' => [
                [
                    'email' => "test@example.com",
                    'username' => "test",
                    'admin' => "Whatever",
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "admin",
                            'message' => "This value should be of type bool.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
            'admin not provided' => [
                [
                    'email' => "test@example.com",
                    'username' => "test",
                ],
                [
                    'error' => "Unprocessable Entity",
                    'message' => [
                        [
                            'parameter' => "admin",
                            'message' => "This value should be of type bool.",
                            'code' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    #[ArrayShape([
        "email" => "string",
        "username" => "string",
        "admin" => "false",
    ])]
    private function getValidBody(): array
    {
        return [
            "email" => "test@example.com",
            "username" => "test",
            "admin" => false,
        ];
    }
}
