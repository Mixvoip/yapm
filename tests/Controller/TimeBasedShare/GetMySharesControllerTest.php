<?php

/**
 * @author bsteffan
 * @since 2026-02-17
 */

namespace App\Tests\Controller\TimeBasedShare;

use App\DataFixtures\TimeBasedShareFixtures;
use App\Tests\Cases\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class GetMySharesControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testReturnsSharesForUser(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        // admin is the creator of the active share
        $this->getAsUser("/time-based-shares/my-shares", [], "admin@example.com");
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertIsArray($response);

        $shareIds = array_column($response, 'id');
        $this->assertContains(TimeBasedShareFixtures::ACTIVE_SHARE_ID, $shareIds);
    }

    public function testDoesNotIncludeExpiredShares(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        // admin is the recipient of the expired share
        $this->getAsUser("/time-based-shares/my-shares", [], "admin@example.com");
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $shareIds = array_column($response, 'id');
        $this->assertNotContains(TimeBasedShareFixtures::EXPIRED_SHARE_ID, $shareIds);
    }

    public function testDoesNotIncludeOtherUsersShares(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        // user1 is the creator of the expired share only, has no active shares
        $this->getAsUser("/time-based-shares/my-shares", [], "user1@example.com");
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $shareIds = array_column($response, 'id');
        $this->assertNotContains(TimeBasedShareFixtures::ACTIVE_SHARE_ID, $shareIds);
    }
}
