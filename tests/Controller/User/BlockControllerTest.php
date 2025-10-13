<?php

/**
 * @author bsteffan
 * @since 2025-04-29
 */

namespace App\Tests\Controller\User;

use App\Entity\RefreshToken;
use App\Repository\RefreshTokenRepository;
use App\Tests\Cases\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class BlockControllerTest extends WebTestCase
{
    public function testAccessDenied(): void
    {
        $this->postAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-a00000000000/block", [], "user0@example.com");
        $this->assertAccessDenied();
    }

    public function testUserNotFound(): void
    {
        $userId = "aaaaaaaa-bbbb-cccc-dddd-999999999999";
        $this->postAsUser("/users/$userId/block", [], "admin@example.com");
        $this->assertUserNotFound($userId);
    }

    public function testUserBlocksHimself(): void
    {
        $this->postAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-a00000000000/block", [], "admin@example.com");
        $this->assertAccessDenied();
    }

    public function testSuccessfulBlock(): void
    {
        $this->postAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-000000000000/block", [], "admin@example.com");
        $this->assertResponseStatusCodeSame(204);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManager->clear();

        $user = $this->userRepository->find("aaaaaaaa-bbbb-cccc-dddd-000000000000");
        $this->assertFalse($user->isActive());
        $this->assertEquals("admin@example.com", $user->getUpdatedBy());

        /** @var RefreshTokenRepository $refreshTokenRepository */
        $refreshTokenRepository = $entityManager->getRepository(RefreshToken::class);
        $refreshTokens = $refreshTokenRepository->findBy(['username' => "user0@example.com"]);
        foreach ($refreshTokens as $refreshToken) {
            $this->assertNull($refreshToken->getValid());
        }
    }
}
