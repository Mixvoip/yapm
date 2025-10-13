<?php

/**
 * @author bsteffan
 * @since 2025-05-26
 */

namespace App\Tests\Controller\User;

use App\Tests\Cases\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class UnblockControllerTest extends WebTestCase
{
    public function testAccessDenied(): void
    {
        $this->postAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-a00000000002/unblock", [], "user0@example.com");
        $this->assertAccessDenied();
    }

    public function testUserNotFound(): void
    {
        $userId = "aaaaaaaa-bbbb-cccc-dddd-999999999999";
        $this->postAsUser("/users/$userId/unblock", [], "admin@example.com");
        $this->assertUserNotFound($userId);
    }

    public function testSuccessfulUnblock(): void
    {
        $this->postAsUser("/users/aaaaaaaa-bbbb-cccc-dddd-000000000002/unblock", [], "admin@example.com");
        $this->assertResponseStatusCodeSame(204);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManager->clear();

        $user = $this->userRepository->find("aaaaaaaa-bbbb-cccc-dddd-000000000002");
        $this->assertTrue($user->isActive());
        $this->assertEquals("admin@example.com", $user->getUpdatedBy());
    }
}
