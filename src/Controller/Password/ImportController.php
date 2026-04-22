<?php

/**
 * @author bsteffan
 * @since 2026-03-27
 */

namespace App\Controller\Password;

use App\Controller\EncryptionAwareTrait;
use App\Controller\NulledValueGetterTrait;
use App\Controller\Password\Dto\ImportPasswordItemDto;
use App\Controller\Password\Dto\ImportPasswordsDto;
use App\Entity\Enums\PasswordField;
use App\Entity\Folder;
use App\Entity\GroupsPassword;
use App\Entity\Password;
use App\Entity\User;
use App\Entity\Vault;
use App\Repository\FolderRepository;
use App\Repository\VaultRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ImportController extends AbstractController
{
    use EncryptionAwareTrait;
    use NulledValueGetterTrait;

    /**
     * Bulk import passwords from encrypted CSV data.
     *
     * @param  ImportPasswordsDto  $importDto
     * @param  EntityManagerInterface  $entityManager
     * @param  EncryptionService  $encryptionService
     *
     * @return JsonResponse
     */
    #[Route("/passwords/import", name: "api_passwords_import", methods: ["POST"])]
    public function index(
        #[MapRequestPayload] ImportPasswordsDto $importDto,
        EntityManagerInterface $entityManager,
        EncryptionService $encryptionService,
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();
        $userGroupIds = $loggedInUser->getGroupIds();
        $loggedInUserIdentifier = $loggedInUser->getUserIdentifier();
        $this->encryptionService = $encryptionService;

        $items = $importDto->items;

        // Collect all unique vault IDs and folder IDs for batch loading
        $vaultIds = [];
        $folderIds = [];

        foreach ($items as $item) {
            $vaultIds[$item->vaultId] = true;

            if (!is_null($item->folderId)) {
                $folderIds[$item->folderId] = true;
            }
        }

        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $entityManager->getRepository(Vault::class);
        $vaults = $vaultRepository->findByIds(
            array_keys($vaultIds),
            [
                "PARTIAL v.{id, name, mandatoryPasswordFields, allowPasswordsAtRoot}",
                "PARTIAL gv.{group, vault, canWrite, partial}",
                "PARTIAL g.{id, name, private, publicKey}",
            ],
            groupAlias: 'g',
            groupVaultAlias: 'gv'
        );

        $folders = [];
        if (!empty($folderIds)) {
            /** @var FolderRepository $folderRepository */
            $folderRepository = $entityManager->getRepository(Folder::class);
            $folders = $folderRepository->findByIds(
                array_keys($folderIds),
                [
                    "PARTIAL f.{id, name, externalId}",
                    "PARTIAL fg.{folder, group, canWrite, partial}",
                    "PARTIAL g.{id, name, private, publicKey}",
                    "PARTIAL v.{id}",
                ],
                groupAlias: "g",
                folderGroupAlias: "fg",
                vaultAlias: "v"
            );
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($items as $index => $item) {
            try {
                $this->processImportItem(
                    $item,
                    $vaults,
                    $folders,
                    $userGroupIds,
                    $loggedInUserIdentifier,
                    $encryptionService,
                    $entityManager
                );

                $results[] = ['row' => $index, 'success' => true];
                $successCount++;
            } catch (Exception $e) {
                $results[] = ['row' => $index, 'success' => false, 'error' => $e->getMessage()];
                $failureCount++;
            }
        }

        if ($successCount > 0) {
            $entityManager->flush();
        }

        return $this->json([
            'results' => $results,
            'successCount' => $successCount,
            'failureCount' => $failureCount,
        ]);
    }

    /**
     * Process a single import item.
     *
     * @param  ImportPasswordItemDto  $item
     * @param  Vault[]  $vaults
     * @param  Folder[]  $folders
     * @param  string[]  $userGroupIds
     * @param  string  $createdBy
     * @param  EncryptionService  $encryptionService
     * @param  EntityManagerInterface  $entityManager
     *
     * @return void
     * @throws Exception
     */
    private function processImportItem(
        ImportPasswordItemDto $item,
        array $vaults,
        array $folders,
        array $userGroupIds,
        string $createdBy,
        EncryptionService $encryptionService,
        EntityManagerInterface $entityManager,
    ): void {
        // Resolve vault
        $vault = $vaults[$item->vaultId] ?? null;
        if (is_null($vault) || !$vault->hasReadPermission($userGroupIds)) {
            throw new Exception("Invalid vault ID.");
        }

        if (!$vault->isAllowPasswordsAtRoot() && is_null($item->folderId)) {
            throw new Exception("This vault only allows passwords to be created in folders.");
        }

        // Resolve folder
        $folder = null;
        if (!is_null($item->folderId)) {
            $folder = $folders[$item->folderId] ?? null;
            if (is_null($folder) || !$folder->hasReadPermission($userGroupIds)) {
                throw new Exception("Invalid folder ID.");
            }

            if ($folder->getVault()->getId() !== $vault->getId()) {
                throw new Exception("Folder is not in the same vault.");
            }

            if (!$folder->hasWritePermission($userGroupIds)) {
                throw new Exception("You don't have write permission on this folder.");
            }
        } elseif (!$vault->hasWritePermission($userGroupIds)) {
            throw new Exception("You don't have write permission on this vault.");
        }

        // Validate mandatory password fields
        $mandatoryPasswordFields = $vault->getMandatoryPasswordFields() ?? [];
        if (is_null(self::getTrimmedOrNull($item->externalId)) && in_array(PasswordField::ExternalId, $mandatoryPasswordFields)) {
            throw new Exception("External ID is mandatory for this vault.");
        }

        if (is_null(self::getTrimmedOrNull($item->target)) && in_array(PasswordField::Target, $mandatoryPasswordFields)) {
            throw new Exception("Target is mandatory for this vault.");
        }

        if (is_null(self::getTrimmedOrNull($item->location)) && in_array(PasswordField::Location, $mandatoryPasswordFields)) {
            throw new Exception("Location is mandatory for this vault.");
        }

        // Inherit groups from folder or vault (non-partial only)
        $groups = [];
        $groupPermissions = [];

        if (!is_null($folder)) {
            $source = array_filter(
                $folder->getFolderGroups()->toArray(),
                fn($fg) => !$fg->isPartial()
            );
            $groups = array_map(fn($fg) => $fg->getGroup(), $source);
            foreach ($source as $fg) {
                $groupPermissions[$fg->getGroup()->getId()] = $fg->canWrite();
            }
        } else {
            $source = array_filter(
                $vault->getGroupVaults()->toArray(),
                fn($gv) => !$gv->isPartial()
            );
            $groups = array_map(fn($gv) => $gv->getGroup(), $source);
            foreach ($source as $gv) {
                $groupPermissions[$gv->getGroup()->getId()] = $gv->canWrite();
            }
        }

        // Guard: at least one write-capable group
        $hasWrite = array_any($groupPermissions, fn($canWrite) => $canWrite === true);
        if (!$hasWrite) {
            throw new Exception("No group with write access found for this location.");
        }

        // Encrypt password data
        $passwordKey = $encryptionService->generatePasswordKey();

        $encryptedPassword = $this->encryptPasswordData($item->encryptedPassword, $passwordKey);
        $encryptedUsername = $this->encryptPasswordData($item->encryptedUsername, $passwordKey);

        // Create Password entity
        $password = new Password();
        $password->setTitle($item->title)
                 ->setEncryptedPassword($encryptedPassword['encryptedData'])
                 ->setPasswordNonce($encryptedPassword['encryptedDataNonce'])
                 ->setTarget(self::getTrimmedOrNull($item->target))
                 ->setLocation(self::getTrimmedOrNull($item->location))
                 ->setExternalId(self::getTrimmedOrNull($item->externalId))
                 ->setVault($vault)
                 ->setFolder($folder)
                 ->setCreatedBy($createdBy);

        if (!is_null($item->encryptedUsername)) {
            $password->setEncryptedUsername($encryptedUsername['encryptedData'])
                     ->setUsernameNonce($encryptedUsername['encryptedDataNonce']);
        }

        $entityManager->persist($password);

        // Create GroupsPassword entries for each inherited group
        foreach ($groups as $group) {
            $permission = $groupPermissions[$group->getId()] ?? false;
            $groupPasswordKeys = $encryptionService->encryptPasswordKeyForGroup(
                $passwordKey,
                $group->getPublicKey()
            );

            $groupPassword = new GroupsPassword();
            $groupPassword->setPassword($password)
                          ->setGroup($group)
                          ->setNonce($groupPasswordKeys['nonce'])
                          ->setEncryptedPasswordKey($groupPasswordKeys['encryptedPasswordKey'])
                          ->setEncryptionPublicKey($groupPasswordKeys['encryptionPublicKey'])
                          ->setCanWrite($permission)
                          ->setCreatedBy($createdBy);

            $password->addGroupPassword($groupPassword);
            $entityManager->persist($groupPassword);
        }

        $encryptionService->secureMemzero($passwordKey);
    }
}
