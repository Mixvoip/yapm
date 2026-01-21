<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Enums\FolderField;
use App\Entity\Enums\PasswordField;
use App\Entity\Group;
use App\Entity\GroupsVault;
use App\Entity\Vault;
use App\Service\Encryption\EncryptionService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ObjectManager;
use Random\RandomException;

/**
 * High-performance fixture using raw SQL for bulk operations
 */
class CustomerVaultFixtures extends Fixture implements DependentFixtureInterface
{
    private const int CUSTOMER_COUNT = 5000;
    private const int PASSWORDS_PER_CUSTOMER = 5;
    private const int BATCH_SIZE = 100; // Process in batches for memory efficiency

    private Connection $connection;
    private array $groupData = [];

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
     * @throws Exception
     * @throws RandomException
     */
    public function load(ObjectManager $manager): void
    {
        $this->connection = $manager->getConnection();

        echo "Creating Customers vault with " . self::CUSTOMER_COUNT . " customers...\n";

        // Create vault and group vault relationships using Doctrine (small number of entities)
        $this->createVaultAndGroupRelations($manager);

        // Load group data for raw SQL operations
        $this->loadGroupData();

        // Use raw SQL for bulk operations
        $this->createFoldersInBatches();
        $this->createPasswordsInBatches();

        echo "Finished creating customer vault fixture!\n";
    }

    /**
     * @param  ObjectManager  $manager
     *
     * @return void
     */
    private function createVaultAndGroupRelations(ObjectManager $manager): void
    {
        echo "Creating vault and group relationships...\n";

        // Create the Customers vault
        $vault = new Vault()->setId("0aaaaaaa-bbbb-cccc-dddd-000000000000")
                            ->setName("Customers")
                            ->setMandatoryFolderFields([FolderField::ExternalId])
                            ->setMandatoryPasswordFields([PasswordField::Location])
                            ->setIconName("people")
                            ->setAllowPasswordsAtRoot(false)
                            ->setDescription("This is the vault for all customer related passwords.")
                            ->setCreatedBy('fixtures');
        $manager->persist($vault);

        // Get group references
        $admin = $this->getReference(GroupFixtures::GROUP_ADMIN, Group::class);
        $managerGroup = $this->getReference(GroupFixtures::GROUP_MANAGER, Group::class);
        $developer = $this->getReference(GroupFixtures::GROUP_DEVELOPER, Group::class);
        $userGroup = $this->getReference(GroupFixtures::GROUP_USER, Group::class);

        // Create group vault relationships
        $groupVaultData = [
            [$admin, true],
            [$developer, true],
            [$userGroup, false],
            [$managerGroup, true],
        ];

        foreach ($groupVaultData as [$group, $canWrite]) {
            $gv = new GroupsVault()->setVault($vault)
                                   ->setGroup($group)
                                   ->setCanWrite($canWrite)
                                   ->setCreatedBy('fixtures');
            $manager->persist($gv);
        }

        $manager->flush();
    }

    /**
     * @return void
     */
    private function loadGroupData(): void
    {
        // Load group data for raw SQL operations
        $admin = $this->getReference(GroupFixtures::GROUP_ADMIN, Group::class);
        $managerGroup = $this->getReference(GroupFixtures::GROUP_MANAGER, Group::class);
        $developer = $this->getReference(GroupFixtures::GROUP_DEVELOPER, Group::class);
        $userGroup = $this->getReference(GroupFixtures::GROUP_USER, Group::class);

        $this->groupData = [
            ['id' => $admin->getId(), 'publicKey' => $admin->getPublicKey(), 'canWrite' => true],
            ['id' => $managerGroup->getId(), 'publicKey' => $managerGroup->getPublicKey(), 'canWrite' => true],
            ['id' => $developer->getId(), 'publicKey' => $developer->getPublicKey(), 'canWrite' => true],
            ['id' => $userGroup->getId(), 'publicKey' => $userGroup->getPublicKey(), 'canWrite' => false],
        ];
    }

    /**
     * @return void
     * @throws Exception
     */
    private function createFoldersInBatches(): void
    {
        echo "Creating folders in batches...\n";

        $now = new DateTimeImmutable()->format('Y-m-d H:i:s');

        for ($batch = 0; $batch < ceil(self::CUSTOMER_COUNT / self::BATCH_SIZE); $batch++) {
            $start = $batch * self::BATCH_SIZE;
            $end = min($start + self::BATCH_SIZE, self::CUSTOMER_COUNT);

            echo "Processing folder batch " . ($batch + 1) . " (customers $start-" . ($end - 1) . ")...\n";

            // Prepare folder data
            $folderData = [];
            $folderGroupData = [];

            for ($i = $start; $i < $end; $i++) {
                $padded = str_pad((string)$i, 4, '0', STR_PAD_LEFT);
                $folderId = "aaaaaaaa-bbbb-cccc-dddd-fc000000" . $padded;

                $folderData[] = [
                    'id' => $folderId,
                    'name' => "Customer-$i",
                    'external_id' => (string)$i,
                    'vault_id' => '0aaaaaaa-bbbb-cccc-dddd-000000000000',
                    'created_at' => $now,
                    'created_by' => 'fixtures',
                ];

                // FolderGroup data for each group
                foreach ($this->groupData as $group) {
                    $folderGroupData[] = [
                        'group_id' => $group['id'],
                        'folder_id' => $folderId,
                        'can_write' => $group['canWrite'] ? 1 : 0,
                        'created_by' => 'fixtures',
                        'created_at' => $now,
                    ];
                }
            }

            // Execute folder batch insert
            foreach ($folderData as $folder) {
                $this->connection->insert('folders', $folder);
            }

            // Execute folder group batch insert
            foreach ($folderGroupData as $folderGroup) {
                $this->connection->insert('folders_groups', $folderGroup);
            }
        }
    }

    /**
     * @return void
     * @throws Exception
     * @throws RandomException
     */
    private function createPasswordsInBatches(): void
    {
        echo "Creating passwords in batches...\n";

        $now = new DateTimeImmutable()->format('Y-m-d H:i:s');

        for ($batch = 0; $batch < ceil(self::CUSTOMER_COUNT / self::BATCH_SIZE); $batch++) {
            $start = $batch * self::BATCH_SIZE;
            $end = min($start + self::BATCH_SIZE, self::CUSTOMER_COUNT);

            echo "Processing password batch " . ($batch + 1) . " (customers $start-" . ($end - 1) . ")...\n";

            for ($i = $start; $i < $end; $i++) {
                $paddedI = str_pad((string)$i, 4, '0', STR_PAD_LEFT);
                $folderId = "aaaaaaaa-bbbb-cccc-dddd-fc000000" . $paddedI;

                for ($j = 0; $j < self::PASSWORDS_PER_CUSTOMER; $j++) {
                    $passwordId = "aaaccaaa-bbbb-cccc-dddd-0000000$paddedI$j";

                    // Generate encryption data
                    $passwordKey = $this->encryptionService->generatePasswordKey();
                    $encPwd = $this->encryptionService->encryptPasswordData("password-customer-$i-$j", $passwordKey);
                    $encUser = $this->encryptionService->encryptPasswordData("username-customer-$i-$j", $passwordKey);

                    // Insert password using DBAL's insert method
                    $this->connection->insert('passwords', [
                        'id' => $passwordId,
                        'title' => "Customer-$i Password-$j",
                        'external_id' => (string)$i,
                        'encrypted_username' => $encUser['encryptedData'],
                        'encrypted_password' => $encPwd['encryptedData'],
                        'username_nonce' => $encUser['nonce'],
                        'password_nonce' => $encPwd['nonce'],
                        'target' => "https://customer-$i.example.com",
                        'description' => "Auto-generated password $j for customer $i",
                        'location' => "floor $j, rack 1",
                        'vault_id' => '0aaaaaaa-bbbb-cccc-dddd-000000000000',
                        'folder_id' => $folderId,
                        'created_by' => 'fixtures',
                        'created_at' => $now,
                    ]);

                    // Insert GroupPassword records for each group
                    foreach ($this->groupData as $group) {
                        $keys = $this->encryptionService->encryptPasswordKeyForGroup($passwordKey, $group['publicKey']);

                        $this->connection->insert('groups_passwords', [
                            'group_id' => $group['id'],
                            'password_id' => $passwordId,
                            'encrypted_password_key' => $keys['encryptedPasswordKey'],
                            'encryption_public_key' => $keys['encryptionPublicKey'],
                            'nonce' => $keys['nonce'],
                            'can_write' => $group['canWrite'] ? 1 : 0,
                            'created_by' => 'fixtures',
                            'created_at' => $now,
                        ]);
                    }

                    // Zero sensitive key
                    $this->encryptionService->secureMemzero($passwordKey);
                }
            }
        }
    }
}
