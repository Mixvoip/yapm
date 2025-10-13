<?php

/**
 * @author bsteffan
 * @since 2025-04-28
 */

namespace App\Tests\Cases;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class WebTestCase extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{
    protected KernelBrowser $client;
    protected ContainerInterface $container;
    protected UserRepository $userRepository;
    protected string $apiBaseUri;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
        $this->userRepository = $this->container->get("doctrine.orm.entity_manager")
                                                ->getRepository(User::class);
        $this->apiBaseUri = $this->container->getParameter('api_base_uri');
    }

    /**
     * Perform a GET request as a logged-in user.
     *
     * @param  string  $uri
     * @param  array  $queryParams
     * @param  string  $userEmail
     *
     * @return void
     */
    public function getAsUser(string $uri, array $queryParams, string $userEmail): void
    {
        $user = $this->userRepository->findOneBy(['email' => $userEmail]);
        if (is_null($user)) {
            $this->fail("User with email $userEmail not found");
        }
        $this->client->loginUser($user);
        $this->client->request("GET", $this->apiBaseUri . $uri, $queryParams);
    }

    public function postAsUser(string $uri, array $data, string $userEmail): void
    {
        $user = $this->userRepository->findOneBy(['email' => $userEmail]);
        if (is_null($user)) {
            $this->fail("User with email $userEmail not found");
        }
        $this->client->loginUser($user);
        $this->client->request(
            "POST",
            $this->apiBaseUri . $uri,
            [],
            [],
            ['CONTENT_TYPE' => "application/json"],
            json_encode($data)
        );
    }

    public function patchAsUser(string $uri, array $data, string $userEmail): void
    {
        $user = $this->userRepository->findOneBy(['email' => $userEmail]);
        if (is_null($user)) {
            $this->fail("User with email $userEmail not found");
        }
        $this->client->loginUser($user);
        $this->client->request(
            "PATCH",
            $this->apiBaseUri . $uri,
            [],
            [],
            ['CONTENT_TYPE' => "application/json"],
            json_encode($data)
        );
    }

    public function getDecodedResponse(): array
    {
        $response = $this->client->getResponse();
        return json_decode($response->getContent(), true);
    }

    public function assertResponse(int $statusCode, array $expectedResponse): void
    {
        $this->assertResponseStatusCodeSame($statusCode);
        $this->assertEquals($expectedResponse, $this->getDecodedResponse());
    }

    public function assertAccessDenied(): void
    {
        $this->assertResponseStatusCodeSame(403);
        $this->assertEquals(
            ["error" => "Access Denied",],
            $this->getDecodedResponse()
        );
    }

    public function assertUserNotFound(string $userId): void
    {
        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            [
                "message" => "User with id: $userId not found.",
                "error" => "Resource not found",
            ],
            $this->getDecodedResponse()
        );
    }
}
