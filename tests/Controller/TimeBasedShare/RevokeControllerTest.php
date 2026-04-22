<?php

/**
 * @author bsteffan
 * @since 2026-02-17
 */

namespace App\Tests\Controller\TimeBasedShare;

use App\DataFixtures\TimeBasedShareFixtures;
use App\Entity\TimeBasedShare;
use App\Tests\Cases\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class RevokeControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testSuccessfulRevoke(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::ACTIVE_SHARE_ID;
        $this->postAsUser("/time-based-shares/$shareId/revoke", [], "admin@example.com");
        $this->assertResponseStatusCodeSame(204);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManager->clear();

        $share = $entityManager->getRepository(TimeBasedShare::class)->find($shareId);
        $this->assertLessThanOrEqual(new \DateTimeImmutable(), $share->getExpiresAt());
    }

    public function testNotFoundNonExistentId(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = "bbbbbbbb-0000-0000-0000-999999999999";
        $this->postAsUser("/time-based-shares/$shareId/revoke", [], "admin@example.com");
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Time-based share not found.',
        ]);
    }

    public function testNotFoundExpiredShare(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::EXPIRED_SHARE_ID;
        $this->postAsUser("/time-based-shares/$shareId/revoke", [], "user1@example.com");
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Time-based share not found.',
        ]);
    }

    public function testNotFoundRecipientTriesToRevoke(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::ACTIVE_SHARE_ID;
        $this->postAsUser("/time-based-shares/$shareId/revoke", [], "user0@example.com");
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Time-based share not found.',
        ]);
    }
}
