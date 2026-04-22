<?php

/**
 * @author bsteffan
 * @since 2026-02-17
 */

namespace App\Tests\Controller\TimeBasedShare;

use App\DataFixtures\TimeBasedShareFixtures;
use App\Tests\Cases\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class GetControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testSuccessfulGetAsRecipient(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::ACTIVE_SHARE_ID;
        $this->getAsUser("/time-based-shares/$shareId", [], "user0@example.com");
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals($shareId, $response['id']);
        $this->assertEquals('Test Password', $response['passwordTitle']);
        $this->assertArrayHasKey('sharedBy', $response);
        $this->assertArrayHasKey('sharedWith', $response);
    }

    public function testSuccessfulGetAsCreator(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::ACTIVE_SHARE_ID;
        $this->getAsUser("/time-based-shares/$shareId", [], "admin@example.com");
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals($shareId, $response['id']);
        $this->assertEquals('Test Password', $response['passwordTitle']);
        $this->assertArrayHasKey('sharedBy', $response);
        $this->assertArrayHasKey('sharedWith', $response);
    }

    public function testNotFoundNonExistentId(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = "bbbbbbbb-0000-0000-0000-999999999999";
        $this->getAsUser("/time-based-shares/$shareId", [], "admin@example.com");
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Time-based share not found.',
        ]);
    }

    public function testNotFoundExpiredShare(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::EXPIRED_SHARE_ID;
        $this->getAsUser("/time-based-shares/$shareId", [], "admin@example.com");
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Time-based share not found.',
        ]);
    }

    public function testNotFoundUnrelatedUser(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        $shareId = TimeBasedShareFixtures::ACTIVE_SHARE_ID;
        $this->getAsUser("/time-based-shares/$shareId", [], "user1@example.com");
        $this->assertResponse(404, [
            'error' => 'Resource not found',
            'message' => 'Time-based share not found.',
        ]);
    }
}
