<?php

namespace App\DataFixtures;

use App\Entity\Group;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class GroupFixtures extends Fixture
{
    public const string GROUP_ADMIN = 'group_admin';
    public const string GROUP_MANAGER = 'group_manager';
    public const string GROUP_USER = 'group_user';
    public const string GROUP_GUEST = 'group_guest';
    public const string GROUP_DEVELOPER = 'group_developer';

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager): void
    {
        $groupsData = [
            self::GROUP_ADMIN => 'Administrators',
            self::GROUP_MANAGER => 'Managers',
            self::GROUP_USER => 'Users',
            self::GROUP_GUEST => 'Guests',
            self::GROUP_DEVELOPER => 'Developers',
        ];

        $count = 0;
        foreach ($groupsData as $reference => $name) {
            $group = new Group()->setId("aaaaaaaa-bbbb-cccc-dddd-90000000000" . $count++)
                                ->setName($name)
                                ->setPublicKey($reference . "_public_key")
                                ->setCreatedBy("fixtures");

            $manager->persist($group);
            $this->addReference($reference, $group);
        }

        $manager->flush();
    }
}
