<?php

namespace App\DataFixtures;

use App\Entity\Folder;
use App\Entity\FoldersGroup;
use App\Entity\Group;
use App\Entity\GroupsPassword;
use App\Entity\GroupsVault;
use App\Entity\Password;
use App\Entity\Vault;
use App\Service\Encryption\EncryptionService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Random\RandomException;

/**
 * Context fixture for the "Development" vault. Builds the full set:
 * - Group-to-Vault permissions
 * - Folders and hierarchy
 * - FolderGroup relations
 * - Passwords (per-folder and at vault root)
 * - GroupPassword relations (with encryption)
 */
class DevelopmentVaultFixtures extends Fixture implements DependentFixtureInterface
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
            GroupFixtures::class,
            GroupUserFixtures::class,
        ];
    }

    /**
     * @inheritDoc
     * @throws RandomException
     */
    public function load(ObjectManager $manager): void
    {
        // Create the Development vault here (moved from VaultFixtures)
        $devVault = new Vault()->setId("1aaaaaaa-bbbb-cccc-dddd-000000000000")
                               ->setName("Development")
                               ->setIconName("code")
                               ->setCreatedBy('fixtures');
        $manager->persist($devVault);

        // === Group-Vault permissions (Admin, Developer write) ===
        /** @var Group $admin */
        $admin = $this->getReference(GroupFixtures::GROUP_ADMIN, Group::class);
        /** @var Group $developer */
        $developer = $this->getReference(GroupFixtures::GROUP_DEVELOPER, Group::class);

        $gvPrototype = new GroupsVault()->setVault($devVault)
                                        ->setCreatedBy('fixtures');

        foreach ([[$admin, true], [$developer, true]] as [$group, $canWrite]) {
            $gv = (clone $gvPrototype)
                ->setGroup($group)
                ->setCanWrite($canWrite);
            $manager->persist($gv);
        }

        // === Folders with exact IDs/hierarchy ===
        $folderPrototype = new Folder()->setVault($devVault)
                                       ->setCreatedBy('fixtures');

        $main = (clone $folderPrototype)->setId('aaaaaaaa-bbbb-cccc-dddd-fd0000000000')
                                        ->setName('main');
        $manager->persist($main);

        $prod = (clone $folderPrototype)->setId('aaaaaaaa-bbbb-cccc-dddd-fd0000000001')
                                        ->setName('prod')
                                        ->setParent($main);
        $manager->persist($prod);

        $staging = (clone $folderPrototype)->setId('aaaaaaaa-bbbb-cccc-dddd-fd0000000002')
                                           ->setName('staging')
                                           ->setParent($main);
        $manager->persist($staging);

        $nexus = (clone $folderPrototype)->setId('aaaaaaaa-bbbb-cccc-dddd-fd0000000003')
                                         ->setName('nexus');
        $manager->persist($nexus);

        $yapm = (clone $folderPrototype)->setId('aaaaaaaa-bbbb-cccc-dddd-fd0000000004')
                                        ->setName('yapm')
                                        ->setParent($nexus);
        $manager->persist($yapm);

        $cdrs = (clone $folderPrototype)->setId('aaaaaaaa-bbbb-cccc-dddd-fd0000000005')
                                        ->setName('cdrs');
        $manager->persist($cdrs);

        // === FolderGroup relations ===
        $fgPrototype = new FoldersGroup()->setCreatedBy('fixtures');

        $allDevFolders = [
            $main,
            $prod,
            $staging,
            $nexus,
            $yapm,
            $cdrs,
        ];

        $developerAllowed = [
            $main->getId(),
            $staging->getId(),
            $nexus->getId(),
            $yapm->getId(),
        ];

        foreach ($allDevFolders as $folder) {
            // Admin always with write
            $fgAdmin = (clone $fgPrototype)->setGroup($admin)
                                           ->setFolder($folder)
                                           ->setCanWrite(true);
            $manager->persist($fgAdmin);

            // Developer on allowed folders
            if (in_array($folder->getId(), $developerAllowed, true)) {
                $fgDev = (clone $fgPrototype)->setGroup($developer)
                                             ->setFolder($folder)
                                             ->setCanWrite(true);
                $manager->persist($fgDev);
            }
        }

        // === Passwords in folders (exact IDs/titles as before) ===
        $foldersInOrder = [
            [$yapm, 'Yapm'],
            [$cdrs, 'Cdrs'],
            [$main, 'Main'],
            [$nexus, 'Nexus'],
            [$prod, 'Prod'],
            [$staging, 'Staging'],
        ];

        // Define which folders grant developer access
        $developerFolderSet = [$main, $staging, $nexus, $yapm];

        foreach ($foldersInOrder as $j => [$folder, $folderNameBase]) {
            for ($i = 0; $i <= 2; $i++) {
                $password = new Password()->setId(sprintf('aaacdaaa-bbbb-cccc-dddd-0000000000%d%d', $j, $i))
                                          ->setTitle($folderNameBase . ' Password ' . $i)
                                          ->setVault($devVault)
                                          ->setFolder($folder)
                                          ->setCreatedBy('fixtures');

                // Encrypt placeholder data
                $passwordKey = $this->encryptionService->generatePasswordKey();
                $encPwd = $this->encryptionService->encryptPasswordData('password-' . $i, $passwordKey);
                $encUser = $this->encryptionService->encryptPasswordData('username-' . $i, $passwordKey);

                $password->setEncryptedPassword($encPwd['encryptedData'])
                         ->setPasswordNonce($encPwd['nonce'])
                         ->setEncryptedUsername($encUser['encryptedData'])
                         ->setUsernameNonce($encUser['nonce']);

                $manager->persist($password);

                // GroupPassword assignments per folder rules: Admin always; Dev on selected folders
                $this->persistGroupPassword($manager, $passwordKey, $password, $admin);

                $allowDev = in_array($folder, $developerFolderSet, true);
                if ($allowDev) {
                    $this->persistGroupPassword($manager, $passwordKey, $password, $developer);
                }

                $this->encryptionService->secureMemzero($passwordKey);
            }
        }

        // === Root-level passwords (5) ===
        for ($i = 0; $i <= 4; $i++) {
            $password = new Password()->setId(sprintf('aaaddaaa-bbbb-cccc-dddd-00000000000%d', $i))
                                      ->setTitle('Root Vault Password ' . $i)
                                      ->setVault($devVault)
                                      ->setCreatedBy('fixtures');

            $passwordKey = $this->encryptionService->generatePasswordKey();
            $encPwd = $this->encryptionService->encryptPasswordData('password-' . $i, $passwordKey);
            $encUser = $this->encryptionService->encryptPasswordData('username-' . $i, $passwordKey);

            $password->setEncryptedPassword($encPwd['encryptedData'])
                     ->setPasswordNonce($encPwd['nonce'])
                     ->setEncryptedUsername($encUser['encryptedData'])
                     ->setUsernameNonce($encUser['nonce']);

            $manager->persist($password);

            // Admin always; Developer only first two
            $this->persistGroupPassword($manager, $passwordKey, $password, $admin);
            if ($i <= 1) {
                $this->persistGroupPassword($manager, $passwordKey, $password, $developer);
            }

            $this->encryptionService->secureMemzero($passwordKey);
        }

        $manager->flush();
    }

    /**
     * @param  ObjectManager  $manager
     * @param  string  $passwordKey
     * @param  Password  $password
     * @param  Group  $group
     *
     * @return void
     * @throws RandomException
     */
    private function persistGroupPassword(
        ObjectManager $manager,
        string $passwordKey,
        Password $password,
        Group $group
    ): void {
        $keys = $this->encryptionService->encryptPasswordKeyForGroup($passwordKey, $group->getPublicKey());
        $gp = new GroupsPassword()->setGroup($group)
                                  ->setPassword($password)
                                  ->setEncryptedPasswordKey($keys['encryptedPasswordKey'])
                                  ->setEncryptionPublicKey($keys['encryptionPublicKey'])
                                  ->setNonce($keys['nonce'])
                                  ->setCreatedBy('fixtures')
                                  ->setCanWrite(true);
        $manager->persist($gp);
    }
}
