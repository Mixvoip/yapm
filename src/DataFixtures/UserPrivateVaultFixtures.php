<?php

namespace App\DataFixtures;

use App\Entity\Folder;
use App\Entity\FoldersGroup;
use App\Entity\Group;
use App\Entity\GroupsPassword;
use App\Entity\GroupsUser;
use App\Entity\GroupsVault;
use App\Entity\Password;
use App\Entity\User;
use App\Entity\Vault;
use App\Service\Encryption\EncryptionService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Random\RandomException;

class UserPrivateVaultFixtures extends Fixture implements DependentFixtureInterface
{
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
            UserFixtures::class,
        ];
    }

    /**
     * @inheritDoc
     * @throws RandomException
     */
    public function load(ObjectManager $manager): void
    {
        /** @var User[] $users */
        $users = $manager->getRepository(User::class)->findAll();

        foreach ($users as $user) {
            if ($user->getUserIdentifier() === "user3@example.com") {
                continue;
            }

            // Create per-user private group
            $groupKeys = $this->encryptionService->generateGroupKeypair();

            $tail = substr($user->getId(), strrpos($user->getId(), '-') + 1);

            $group = new Group()->setId("11111111-bbbb-cccc-dddd-" . $tail)
                                ->setName("user-" . $user->getId())
                                ->setPrivate(true)
                                ->setPublicKey($groupKeys['publicKey'])
                                ->setCreatedBy('fixtures');
            $manager->persist($group);

            // If the user has a public key (i.e., verified), add as the only member
            if (!is_null($user->getPublicKey())) {
                $encryptedKey = $this->encryptionService->encryptGroupPrivateKeyForUser(
                    $groupKeys['privateKey'],
                    $user->getPublicKey()
                );

                $groupUser = new GroupsUser()->setGroup($group)
                                             ->setUser($user)
                                             ->setEncryptedGroupPrivateKey($encryptedKey['encryptedGroupPrivateKey'])
                                             ->setGroupPrivateKeyNonce($encryptedKey['groupPrivateKeyNonce'])
                                             ->setEncryptionPublicKey($encryptedKey['encryptionPublicKey'])
                                             ->setCreatedBy('fixtures');
                // Do NOT mark as manager to avoid changing managedGroupIds expectations in tests
                $manager->persist($groupUser);
            }

            // Zero private key buffer
            $this->encryptionService->secureMemzero($groupKeys['privateKey']);

            // Create per-user private vault and link group with write access
            $vault = new Vault()->setId("22222222-bbbb-cccc-dddd-" . $tail)
                                ->setName('Private vault')
                                ->setIconName("folder_shared")
                                ->setUser($user)
                                ->setCreatedBy('fixtures');
            $manager->persist($vault);

            $groupVault = new GroupsVault()->setGroup($group)
                                           ->setVault($vault)
                                           ->setCanWrite(true)
                                           ->setCreatedBy('fixtures');
            $manager->persist($groupVault);

            // Root passwords
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Root Password 1',
                "44444440-bbbb-cccc-dddd-" . $tail
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Root Password 2',
                "44444441-bbbb-cccc-dddd-" . $tail
            );

            // Folder A with two passwords and a subfolder with three passwords
            $folderA = $this->createFolder(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Personal',
                "33333330-bbbb-cccc-dddd-" . $tail
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Personal Password 1',
                "44444442-bbbb-cccc-dddd-" . $tail,
                $folderA
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Personal Password 2',
                "44444443-bbbb-cccc-dddd-" . $tail,
                $folderA
            );

            $subA = $this->createFolder(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Personal Subfolder',
                "33333331-bbbb-cccc-dddd-" . $tail,
                $folderA
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Personal Sub Password 1',
                "44444444-bbbb-cccc-dddd-" . $tail,
                $subA
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Personal Sub Password 2',
                "44444445-bbbb-cccc-dddd-" . $tail,
                $subA
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Personal Sub Password 3',
                "44444446-bbbb-cccc-dddd-" . $tail,
                $subA
            );

            // Root Folder B with four passwords
            $folderB = $this->createFolder(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Archive',
                "33333332-bbbb-cccc-dddd-" . $tail
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Archive Password 1',
                "44444447-bbbb-cccc-dddd-" . $tail,
                $folderB
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Archive Password 2',
                "44444448-bbbb-cccc-dddd-" . $tail,
                $folderB
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Archive Password 3',
                "44444449-bbbb-cccc-dddd-" . $tail,
                $folderB
            );
            $this->createPassword(
                $manager,
                $vault,
                $group,
                $user->getUsername() . ' Archive Password 4',
                "4444444a-bbbb-cccc-dddd-" . $tail,
                $folderB
            );
        }

        $manager->flush();
    }

    /**
     * @param  ObjectManager  $manager
     * @param  Vault  $vault
     * @param  Group  $group
     * @param  string  $name
     * @param  string  $id
     * @param  Folder|null  $parent
     *
     * @return Folder
     */
    private function createFolder(
        ObjectManager $manager,
        Vault $vault,
        Group $group,
        string $name,
        string $id,
        ?Folder $parent = null
    ): Folder {
        $folder = new Folder()->setId($id)
                              ->setName($name)
                              ->setVault($vault)
                              ->setParent($parent)
                              ->setCreatedBy('fixtures');
        $manager->persist($folder);

        $fg = new FoldersGroup()->setFolder($folder)
                                ->setGroup($group)
                                ->setCanWrite(true)
                                ->setCreatedBy('fixtures');
        $manager->persist($fg);

        return $folder;
    }

    /**
     * @param  ObjectManager  $manager
     * @param  Vault  $vault
     * @param  Group  $group
     * @param  string  $title
     * @param  string  $id
     * @param  Folder|null  $folder
     *
     * @return void
     * @throws RandomException
     */
    private function createPassword(
        ObjectManager $manager,
        Vault $vault,
        Group $group,
        string $title,
        string $id,
        ?Folder $folder = null
    ): void {
        $password = new Password()->setId($id)
                                  ->setTitle($title)
                                  ->setVault($vault)
                                  ->setFolder($folder)
                                  ->setCreatedBy('fixtures');

        $passwordKey = $this->encryptionService->generatePasswordKey();
        $encPwd = $this->encryptionService->encryptPasswordData('password-' . $title, $passwordKey);
        $encUser = $this->encryptionService->encryptPasswordData('username-' . $title, $passwordKey);

        $password->setEncryptedPassword($encPwd['encryptedData'])
                 ->setPasswordNonce($encPwd['nonce'])
                 ->setEncryptedUsername($encUser['encryptedData'])
                 ->setUsernameNonce($encUser['nonce']);
        $manager->persist($password);

        $keys = $this->encryptionService->encryptPasswordKeyForGroup($passwordKey, $group->getPublicKey());
        $gp = new GroupsPassword()->setGroup($group)
                                  ->setPassword($password)
                                  ->setEncryptedPasswordKey($keys['encryptedPasswordKey'])
                                  ->setEncryptionPublicKey($keys['encryptionPublicKey'])
                                  ->setNonce($keys['nonce'])
                                  ->setCanWrite(true)
                                  ->setCreatedBy('fixtures');
        $manager->persist($gp);

        $this->encryptionService->secureMemzero($passwordKey);
    }
}
