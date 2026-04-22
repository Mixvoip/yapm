<?php

/**
 * @author bsteffan
 * @since 2026-02-17
 */

namespace App\DataFixtures;

use App\Entity\TimeBasedShare;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TimeBasedShareFixtures extends Fixture implements DependentFixtureInterface
{
    public const string ACTIVE_SHARE = 'active-time-based-share';
    public const string EXPIRED_SHARE = 'expired-time-based-share';

    public const string ACTIVE_SHARE_ID = 'bbbbbbbb-0000-0000-0000-000000000001';
    public const string EXPIRED_SHARE_ID = 'bbbbbbbb-0000-0000-0000-000000000002';

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
        /** @var UserRepository $userRepository */
        $userRepository = $manager->getRepository(User::class);

        $admin = $userRepository->findOneBy(['email' => 'admin@example.com']);
        $user0 = $userRepository->findOneBy(['email' => 'user0@example.com']);
        $user1 = $userRepository->findOneBy(['email' => 'user1@example.com']);

        // Active share: admin shared with user0
        $activeShare = new TimeBasedShare();
        $activeShare->setId(self::ACTIVE_SHARE_ID)
                    ->setSharedBy($admin)
                    ->setSharedWith($user0)
                    ->setPasswordTitle('Test Password')
                    ->setSourcePasswordId('aaaaaaaa-0000-0000-0000-000000000001')
                    ->setEncryptedPassword('fake-encrypted-password')
                    ->setPasswordNonce('fake-nonce')
                    ->setPasswordEncryptionPublicKey('fake-public-key')
                    ->setEncryptedUsername('fake-encrypted-username')
                    ->setUsernameNonce('fake-username-nonce')
                    ->setUsernameEncryptionPublicKey('fake-username-public-key')
                    ->setExpiresAt(new DateTimeImmutable('2026-02-17 12:00:00'));

        $manager->persist($activeShare);
        $this->addReference(self::ACTIVE_SHARE, $activeShare);

        // Expired share: user1 shared with admin
        $expiredShare = new TimeBasedShare();
        $expiredShare->setId(self::EXPIRED_SHARE_ID)
                     ->setSharedBy($user1)
                     ->setSharedWith($admin)
                     ->setPasswordTitle('Expired Password')
                     ->setSourcePasswordId('aaaaaaaa-0000-0000-0000-000000000002')
                     ->setEncryptedPassword('fake-expired-password')
                     ->setPasswordNonce('fake-expired-nonce')
                     ->setPasswordEncryptionPublicKey('fake-expired-public-key')
                     ->setExpiresAt(new DateTimeImmutable('2026-02-17 08:00:00'));

        $manager->persist($expiredShare);
        $this->addReference(self::EXPIRED_SHARE, $expiredShare);

        $manager->flush();
    }
}
