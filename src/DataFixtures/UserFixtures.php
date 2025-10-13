<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\Encryption\EncryptionService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Random\RandomException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    /**
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     */
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EncryptionService $encryptionService
    ) {
    }

    /**
     * @throws RandomException
     */
    public function load(ObjectManager $manager): void
    {
        $password = "password123";
        $prototype = new User()->setCreatedBy("fixtures");
        // Create admin user
        $userKeypair = $this->encryptionService->generateUserKeypair("InThePassw0rdManager");
        $adminUser = (clone $prototype)->setEmail('admin@example.com')
                                       ->setId("aaaaaaaa-bbbb-cccc-dddd-a00000000000")
                                       ->setUsername('admin')
                                       ->setPublicKey($userKeypair['publicKey'])
                                       ->setKeySalt($userKeypair['keySalt'])
                                       ->setEncryptedPrivateKey($userKeypair['encryptedPrivateKey'])
                                       ->setPrivateKeyNonce($userKeypair['privateKeyNonce'])
                                       ->setAdmin(true)
                                       ->setVerified(true);
        $adminUser->setPassword(
            $this->passwordHasher->hashPassword(
                $adminUser,
                "InThePassw0rdManager"
            )
        );

        $manager->persist($adminUser);

        // Create regular users
        for ($i = 0; $i < 5; $i++) {
            $user = (clone $prototype)->setEmail(sprintf('user%d@example.com', $i))
                                      ->setId(sprintf('aaaaaaaa-bbbb-cccc-dddd-00000000000%d', $i))
                                      ->setUsername(sprintf('user%d', $i));

            if ($i !== 3) {
                $user->setPassword(
                    $this->passwordHasher->hashPassword(
                        $user,
                        $password
                    )
                );

                $userKeypair = $this->encryptionService->generateUserKeypair($password);
                $user->setVerified(true)
                     ->setPublicKey($userKeypair['publicKey'])
                     ->setKeySalt($userKeypair['keySalt'])
                     ->setEncryptedPrivateKey($userKeypair['encryptedPrivateKey'])
                     ->setPrivateKeyNonce($userKeypair['privateKeyNonce']);
            } else {
                $user->setVerificationToken(bin2hex(random_bytes(16)))
                     ->setKeySalt(null)
                     ->setEncryptedPrivateKey(null)
                     ->setPrivateKeyNonce(null);
            }

            if ($i === 2) {
                $user->setActive(false);
            }

            $manager->persist($user);
        }

        // Create a manager user
        $userKeypair = $this->encryptionService->generateUserKeypair($password);
        $managerUser = (clone $prototype)->setEmail('manager@example.com')
                                         ->setId("aaaaaaaa-bbbb-cccc-dddd-000000000010")
                                         ->setUsername('manager')
                                         ->setPublicKey($userKeypair['publicKey'])
                                         ->setKeySalt($userKeypair['keySalt'])
                                         ->setEncryptedPrivateKey($userKeypair['encryptedPrivateKey'])
                                         ->setPrivateKeyNonce($userKeypair['privateKeyNonce'])
                                         ->setVerified(true);
        $managerUser->setPassword(
            $this->passwordHasher->hashPassword(
                $managerUser,
                $password
            )
        );

        $manager->persist($managerUser);

        // Create a dev user
        $userKeypair = $this->encryptionService->generateUserKeypair($password);
        $devUser = (clone $prototype)->setEmail('dev@example.com')
                                     ->setId("aaaaaaaa-bbbb-cccc-dddd-000000000020")
                                     ->setUsername('dev')
                                     ->setPublicKey($userKeypair['publicKey'])
                                     ->setKeySalt($userKeypair['keySalt'])
                                     ->setEncryptedPrivateKey($userKeypair['encryptedPrivateKey'])
                                     ->setPrivateKeyNonce($userKeypair['privateKeyNonce'])
                                     ->setVerified(true);
        $devUser->setPassword(
            $this->passwordHasher->hashPassword(
                $devUser,
                $password
            )
        );

        $manager->persist($devUser);

        $manager->flush();
    }
}
