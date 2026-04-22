<?php

/**
 * @author bsteffan
 * @since 2026-03-30
 */

namespace App\Tests\Controller\Password;

use App\Entity\GroupsPassword;
use App\Entity\Password;
use App\Repository\PasswordRepository;
use App\Service\Encryption\EncryptionService;
use App\Tests\Cases\WebTestCase;
use JetBrains\PhpStorm\ArrayShape;
use Random\RandomException;

class ImportControllerTest extends WebTestCase
{
    private const string DEV_VAULT_ID = '1aaaaaaa-bbbb-cccc-dddd-000000000000';
    private const string CUSTOMER_VAULT_ID = '0aaaaaaa-bbbb-cccc-dddd-000000000000';
    private const string MAIN_FOLDER_ID = 'aaaaaaaa-bbbb-cccc-dddd-fd0000000000';
    private const string CUSTOMER_FOLDER_ID = 'aaaaaaaa-bbbb-cccc-dddd-fc0000000000';

    /**
     * Test importing a single password at vault root.
     *
     * @throws RandomException
     */
    public function testImportSinglePassword(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Imported Password',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret123'),
                    'encryptedUsername' => $this->makeEncryptedData('admin'),
                    'target' => 'https://example.com',
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(1, $response['successCount']);
        $this->assertEquals(0, $response['failureCount']);
        $this->assertTrue($response['results'][0]['success']);
    }

    /**
     * Test importing multiple passwords in a single request.
     *
     * @throws RandomException
     */
    public function testImportMultiplePasswords(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Imported Password 1',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret1'),
                ],
                [
                    'title' => 'Imported Password 2',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret2'),
                    'encryptedUsername' => $this->makeEncryptedData('user2'),
                    'target' => 'https://example2.com',
                    'externalId' => 'ext-002',
                ],
                [
                    'title' => 'Imported Password 3',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret3'),
                    'folderId' => self::MAIN_FOLDER_ID,
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(3, $response['successCount']);
        $this->assertEquals(0, $response['failureCount']);
        $this->assertCount(3, $response['results']);
    }

    /**
     * Test importing a password into a folder.
     *
     * @throws RandomException
     */
    public function testImportPasswordIntoFolder(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Folder Password',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                    'folderId' => self::MAIN_FOLDER_ID,
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(1, $response['successCount']);
        $this->assertEquals(0, $response['failureCount']);
    }

    /**
     * Test that GroupsPassword entries are created for inherited groups.
     *
     * @throws RandomException
     */
    public function testImportCreatesGroupsPasswordEntries(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Password With Groups',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(1, $response['successCount']);

        // Verify GroupsPassword entries were created
        $em = $this->container->get('doctrine')->getManager();
        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $em->getRepository(Password::class);
        $passwords = $passwordRepository->findBy(['title' => 'Password With Groups']);
        $this->assertCount(1, $passwords);

        $groupsPasswords = $em->getRepository(GroupsPassword::class)->findBy([
            'password' => $passwords[0],
        ]);
        $this->assertGreaterThan(0, count($groupsPasswords));
    }

    /**
     * Test import with invalid vault ID fails for that item.
     *
     * @throws RandomException
     */
    public function testImportWithInvalidVaultId(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Bad Vault Password',
                    'vaultId' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(0, $response['successCount']);
        $this->assertEquals(1, $response['failureCount']);
        $this->assertFalse($response['results'][0]['success']);
        $this->assertEquals('Invalid vault ID.', $response['results'][0]['error']);
    }

    /**
     * Test import with invalid folder ID fails for that item.
     *
     * @throws RandomException
     */
    public function testImportWithInvalidFolderId(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Bad Folder Password',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                    'folderId' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(0, $response['successCount']);
        $this->assertEquals(1, $response['failureCount']);
        $this->assertEquals('Invalid folder ID.', $response['results'][0]['error']);
    }

    /**
     * Test import where folder is not in the specified vault.
     *
     * @throws RandomException
     */
    public function testImportWithFolderNotInVault(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Wrong Vault Folder',
                    'vaultId' => self::CUSTOMER_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                    'folderId' => self::MAIN_FOLDER_ID, // This folder belongs to dev vault
                    'location' => 'Somewhere',
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(0, $response['successCount']);
        $this->assertEquals('Folder is not in the same vault.', $response['results'][0]['error']);
    }

    /**
     * Test import into vault that does not allow passwords at root.
     *
     * @throws RandomException
     */
    public function testImportRejectsPasswordAtRootWhenNotAllowed(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Root Not Allowed',
                    'vaultId' => self::CUSTOMER_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                    'location' => 'Somewhere',
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(0, $response['successCount']);
        $this->assertEquals(
            'This vault only allows passwords to be created in folders.',
            $response['results'][0]['error']
        );
    }

    /**
     * Test import validates mandatory password fields (Customer vault requires Location).
     *
     * @throws RandomException
     */
    public function testImportValidatesMandatoryFields(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Missing Mandatory Field',
                    'vaultId' => self::CUSTOMER_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                    'folderId' => self::CUSTOMER_FOLDER_ID,
                    // Missing 'location' which is mandatory for customer vault
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(0, $response['successCount']);
        $this->assertEquals('Location is mandatory for this vault.', $response['results'][0]['error']);
    }

    /**
     * Test import succeeds when mandatory fields are provided.
     *
     * @throws RandomException
     */
    public function testImportSucceedsWithMandatoryFields(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'With Mandatory Fields',
                    'vaultId' => self::CUSTOMER_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                    'folderId' => self::CUSTOMER_FOLDER_ID,
                    'location' => 'Production Server',
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(1, $response['successCount']);
        $this->assertEquals(0, $response['failureCount']);
    }

    /**
     * Test partial success - some items succeed, some fail.
     *
     * @throws RandomException
     */
    public function testImportPartialSuccess(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Good Password',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret1'),
                ],
                [
                    'title' => 'Bad Vault Password',
                    'vaultId' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
                    'encryptedPassword' => $this->makeEncryptedData('secret2'),
                ],
                [
                    'title' => 'Another Good Password',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret3'),
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(2, $response['successCount']);
        $this->assertEquals(1, $response['failureCount']);
        $this->assertTrue($response['results'][0]['success']);
        $this->assertFalse($response['results'][1]['success']);
        $this->assertTrue($response['results'][2]['success']);
    }

    /**
     * Test that a user without vault access cannot import.
     *
     * @throws RandomException
     */
    public function testImportWithoutVaultAccess(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'No Access Password',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                ],
            ],
        ];

        // user0 is in Users group which has no access to dev vault
        $this->postAsUser('/passwords/import', $body, 'user0@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(0, $response['successCount']);
        $this->assertEquals(1, $response['failureCount']);
        $this->assertEquals('Invalid vault ID.', $response['results'][0]['error']);
    }

    /**
     * Test validation rejects empty items array.
     *
     * @throws RandomException
     */
    public function testImportRejectsEmptyItems(): void
    {
        $body = [
            'items' => [],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Test validation rejects missing title.
     *
     * @throws RandomException
     */
    public function testImportRejectsMissingTitle(): void
    {
        $body = [
            'items' => [
                [
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Test validation rejects missing encrypted password.
     */
    public function testImportRejectsMissingEncryptedPassword(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'No Encrypted Password',
                    'vaultId' => self::DEV_VAULT_ID,
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Test that empty string optional fields are treated as null.
     *
     * @throws RandomException
     */
    public function testImportHandlesEmptyStringFields(): void
    {
        $body = [
            'items' => [
                [
                    'title' => 'Empty Strings Password',
                    'vaultId' => self::DEV_VAULT_ID,
                    'encryptedPassword' => $this->makeEncryptedData('secret'),
                    'target' => '',
                    'location' => '',
                    'externalId' => '',
                ],
            ],
        ];

        $this->postAsUser('/passwords/import', $body, 'admin@example.com');
        $this->assertResponseStatusCodeSame(200);

        $response = $this->getDecodedResponse();
        $this->assertEquals(1, $response['successCount']);
    }

    /**
     * Create encrypted data payload for the server.
     *
     * @throws RandomException
     */
    #[ArrayShape([
        'encryptedData' => "string",
        'clientPublicKey' => "string",
        'nonce' => "string",
    ])]
    private function makeEncryptedData(string $plaintext): array
    {
        /** @var EncryptionService $encryptionService */
        $encryptionService = $this->container->get(EncryptionService::class);
        $encrypted = $encryptionService->encryptForServer($plaintext);

        return [
            'encryptedData' => $encrypted['encryptedData'],
            'clientPublicKey' => $encrypted['clientPublicKey'],
            'nonce' => $encrypted['nonce'],
        ];
    }
}
