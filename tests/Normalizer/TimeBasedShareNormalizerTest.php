<?php

/**
 * @author bsteffan
 * @since 2026-02-18
 */

namespace App\Tests\Normalizer;

use App\DataFixtures\TimeBasedShareFixtures;
use App\Entity\TimeBasedShare;
use App\Repository\TimeBasedShareRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class TimeBasedShareNormalizerTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testNormalization(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-02-17 10:00:00'));

        /** @var TimeBasedShareRepository $repository */
        $repository = $this->getContainer()
                           ->get("doctrine.orm.entity_manager")
                           ->getRepository(TimeBasedShare::class);

        $share = $repository->findActiveById(TimeBasedShareFixtures::ACTIVE_SHARE_ID);
        $this->assertNotNull($share);

        $normalized = $this->getContainer()->get("serializer")->normalize($share);

        $this->assertEquals(TimeBasedShareFixtures::ACTIVE_SHARE_ID, $normalized['id']);
        $this->assertEquals('Test Password', $normalized['passwordTitle']);

        // sharedBy (admin)
        $this->assertArrayHasKey('sharedBy', $normalized);
        $this->assertEquals('aaaaaaaa-bbbb-cccc-dddd-a00000000000', $normalized['sharedBy']['id']);
        $this->assertEquals('admin', $normalized['sharedBy']['username']);
        $this->assertEquals('admin@example.com', $normalized['sharedBy']['email']);

        // sharedWith (user0)
        $this->assertArrayHasKey('sharedWith', $normalized);
        $this->assertEquals('aaaaaaaa-bbbb-cccc-dddd-000000000000', $normalized['sharedWith']['id']);
        $this->assertEquals('user0', $normalized['sharedWith']['username']);
        $this->assertEquals('user0@example.com', $normalized['sharedWith']['email']);

        // Dates
        $this->assertEquals('2026-02-17 12:00:00', $normalized['expiresAt']);
        $this->assertNull($normalized['accessedAt']);
        $this->assertArrayHasKey('createdAt', $normalized);
    }
}
