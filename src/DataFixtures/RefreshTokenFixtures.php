<?php

namespace App\DataFixtures;

use App\Entity\RefreshToken;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RefreshTokenFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @inheritDoc
     */
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager): void
    {
        // Create refresh tokens for admin user
        $this->createRefreshToken(
            $manager,
            'admin@example.com',
            'admin_refresh_token_1',
            new DateTime('+100 years')
        );

        // Create refresh tokens for regular users (only for verified users)
        for ($i = 0; $i < 5; $i++) {
            // Skip user2 as it's not active (see UserFixtures)
            // Skip user3 as it's not verified (see UserFixtures)
            if ($i === 2 || $i === 3) {
                continue;
            }

            $this->createRefreshToken(
                $manager,
                "user$i@example.com",
                sprintf('user%d_refresh_token_1', $i),
                new DateTime('+100 years')
            );
        }

        // Create refresh token for manager user
        $this->createRefreshToken(
            $manager,
            "manager@example.com",
            'manager_refresh_token_1',
            new DateTime('+100 years')
        );

        $this->createRefreshTokensForBlockTest($manager);

        $manager->flush();
    }

    /**
     * Create refresh tokens for block test.
     *
     * @param  ObjectManager  $manager
     *
     * @return void
     */
    private function createRefreshTokensForBlockTest(ObjectManager $manager): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createRefreshToken(
                $manager,
                'user0@example.com',
                sprintf('user0_refresh_token_1%d', $i),
                new DateTime("+ 11$i years")
            );
        }
    }

    /**
     * Create a refresh token for a user.
     *
     * @param  ObjectManager  $manager
     * @param  string  $userId
     * @param  string  $tokenValue
     * @param  DateTime  $validity
     *
     * @return void
     */
    private function createRefreshToken(
        ObjectManager $manager,
        string $userId,
        string $tokenValue,
        DateTime $validity
    ): void {
        $refreshToken = new RefreshToken()->setRefreshToken($tokenValue)
                                          ->setUsername($userId)
                                          ->setValid($validity);
        $manager->persist($refreshToken);
    }
}
