<?php

/**
 * @author bsteffan
 * @since 2025-05-28
 */

namespace App\DataFixtures;

use App\Entity\Group;
use App\Entity\GroupsUser;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Random\RandomException;

class GroupUserFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var User[] $users */
    private array $users = [];

    /**
     * @param  EncryptionService  $encryptionService
     */
    public function __construct(
        private readonly EncryptionService $encryptionService
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDependencies(): array
    {
        return [
            GroupFixtures::class,
            UserFixtures::class,
        ];
    }

    /**
     * @param  ObjectManager  $manager
     *
     * @return void
     */
    public function findDependencies(ObjectManager $manager): void
    {
        /** @var UserRepository $userRepository */
        $userRepository = $manager->getRepository(User::class);
        $users = $userRepository->findAll();
        foreach ($users as $user) {
            $this->users[$user->getUsername()] = $user;
        }
    }

    /**
     * @inheritDoc
     * @throws RandomException
     */
    public function load(ObjectManager $manager): void
    {
        $this->findDependencies($manager);

        $userGroupKeys = $this->encryptionService->generateGroupKeypair();
        $adminGroupKeys = $this->encryptionService->generateGroupKeypair();
        $managerGroupKeys = $this->encryptionService->generateGroupKeypair();
        $guestGroupKeys = $this->encryptionService->generateGroupKeypair();
        $developerGroupKeys = $this->encryptionService->generateGroupKeypair();

        $adminGroup = $this->getReference(GroupFixtures::GROUP_ADMIN, Group::class)
                           ->setPublicKey($adminGroupKeys['publicKey']);
        $managerGroup = $this->getReference(GroupFixtures::GROUP_MANAGER, Group::class)
                             ->setPublicKey($managerGroupKeys['publicKey']);
        $developerGroup = $this->getReference(GroupFixtures::GROUP_DEVELOPER, Group::class)
                               ->setPublicKey($developerGroupKeys['publicKey']);
        $guestGroup = $this->getReference(GroupFixtures::GROUP_GUEST, Group::class)
                           ->setPublicKey($guestGroupKeys['publicKey']);
        $userGroup = $this->getReference(GroupFixtures::GROUP_USER, Group::class)
                          ->setPublicKey($userGroupKeys['publicKey']);
        $allGroups = [$adminGroup, $managerGroup, $developerGroup, $guestGroup, $userGroup];

        $adminGuestKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $guestGroupKeys['privateKey'],
            $this->users["admin"]->getPublicKey()
        );

        $adminDeveloperKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $developerGroupKeys['privateKey'],
            $this->users["admin"]->getPublicKey()
        );

        $adminAdminKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $adminGroupKeys['privateKey'],
            $this->users["admin"]->getPublicKey()
        );

        $user0UserKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $userGroupKeys['privateKey'],
            $this->users["user0"]->getPublicKey()
        );

        $user1UserKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $userGroupKeys['privateKey'],
            $this->users["user1"]->getPublicKey()
        );

        $user2UserKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $userGroupKeys['privateKey'],
            $this->users["user2"]->getPublicKey()
        );

        $user4UserKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $userGroupKeys['privateKey'],
            $this->users["user4"]->getPublicKey()
        );

        $managerUserKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $userGroupKeys['privateKey'],
            $this->users["manager"]->getPublicKey()
        );

        $managerManagerKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $managerGroupKeys['privateKey'],
            $this->users["manager"]->getPublicKey()
        );

        $devDevKeys = $this->encryptionService->encryptGroupPrivateKeyForUser(
            $developerGroupKeys['privateKey'],
            $this->users["dev"]->getPublicKey()
        );

        $prototype = new GroupsUser()->setCreatedBy("fixtures")
                                     ->setGroup($userGroup);

        $groupUsers = [
            (clone $prototype)->setUser($this->users["admin"])
                              ->setGroup($guestGroup)
                              ->setEncryptedGroupPrivateKey($adminGuestKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($adminGuestKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($adminGuestKeys['encryptionPublicKey'])
                              ->setManager(true),
            (clone $prototype)->setUser($this->users["admin"])
                              ->setGroup($developerGroup)
                              ->setEncryptedGroupPrivateKey($adminDeveloperKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($adminDeveloperKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($adminDeveloperKeys['encryptionPublicKey'])
                              ->setManager(true),
            (clone $prototype)->setUser($this->users["admin"])
                              ->setGroup($adminGroup)
                              ->setEncryptedGroupPrivateKey($adminAdminKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($adminAdminKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($adminAdminKeys['encryptionPublicKey'])
                              ->setManager(true),
            (clone $prototype)->setUser($this->users["user0"])
                              ->setEncryptedGroupPrivateKey($user0UserKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($user0UserKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($user0UserKeys['encryptionPublicKey']),
            (clone $prototype)->setUser($this->users["user1"])
                              ->setEncryptedGroupPrivateKey($user1UserKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($user1UserKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($user1UserKeys['encryptionPublicKey']),
            (clone $prototype)->setUser($this->users["user2"])
                              ->setEncryptedGroupPrivateKey($user2UserKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($user2UserKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($user2UserKeys['encryptionPublicKey']),
            (clone $prototype)->setUser($this->users["user4"])
                              ->setEncryptedGroupPrivateKey($user4UserKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($user4UserKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($user4UserKeys['encryptionPublicKey']),
            (clone $prototype)->setUser($this->users["manager"])
                              ->setEncryptedGroupPrivateKey($managerUserKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($managerUserKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($managerUserKeys['encryptionPublicKey'])
                              ->setManager(true),
            (clone $prototype)->setUser($this->users["manager"])
                              ->setEncryptedGroupPrivateKey($managerManagerKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($managerManagerKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($managerManagerKeys['encryptionPublicKey'])
                              ->setGroup($managerGroup)
                              ->setManager(true),
            (clone $prototype)->setUser($this->users["dev"])
                              ->setEncryptedGroupPrivateKey($devDevKeys['encryptedGroupPrivateKey'])
                              ->setGroupPrivateKeyNonce($devDevKeys['groupPrivateKeyNonce'])
                              ->setEncryptionPublicKey($devDevKeys['encryptionPublicKey'])
                              ->setGroup($this->getReference(GroupFixtures::GROUP_DEVELOPER, Group::class)),
        ];

        foreach ($groupUsers as $groupUser) {
            $manager->persist($groupUser);
        }

        foreach ($allGroups as $group) {
            $manager->persist($group);
        }

        $manager->flush();
    }
}
