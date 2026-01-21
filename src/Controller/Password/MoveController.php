<?php

/**
 * @author bsteffan
 * @since 2025-10-28
 */

namespace App\Controller\Password;

use App\Controller\EncryptionAwareTrait;
use App\Controller\Password\Dto\MoveDto;
use App\Entity\Enums\PasswordField;
use App\Entity\Folder;
use App\Entity\GroupsPassword;
use App\Entity\Password;
use App\Entity\User;
use App\Entity\Vault;
use App\Repository\FolderRepository;
use App\Repository\PasswordRepository;
use App\Repository\VaultRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class MoveController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Move an existing password to a new vault/folder.
     *
     * @param  string  $id
     * @param  MoveDto  $dto
     * @param  EntityManagerInterface  $entityManager
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     *
     * @return Response
     * @throws RandomException
     */
    #[Route(
        "/passwords/{id}/move",
        name: "passwords_id_move",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["POST"]
    )]
    public function index(
        string $id,
        #[MapRequestPayload] MoveDto $dto,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        EncryptionService $encryptionService
    ): Response {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $this->passwordHasher = $passwordHasher;
        $this->encryptionService = $encryptionService;

        /** @var PasswordRepository $passwordRepository */
        $passwordRepository = $entityManager->getRepository(Password::class);
        $password = $passwordRepository->findByIds(
            [$id],
            [
                "PARTIAL p.{id, title, externalId, target, location, updatedAt, updatedBy}",
                "PARTIAL gp.{group, password, encryptedPasswordKey, encryptionPublicKey, nonce, canWrite}",
                "PARTIAL g.{id, name}",
            ],
            groupAlias: "g",
            groupPasswordAlias: "gp"
        )[$id] ?? null;

        if (is_null($password) || !$password->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Password with id: $id not found.");
        }

        if (!$password->hasWritePermission($loggedInUser->getGroupIds())) {
            throw $this->createAccessDeniedException("You don't have permission to move this password.");
        }

        try {
            $decryptedPrivateKey = $this->decryptUserPrivateKey($dto->encryptedUserPassword);
        } catch (Exception $e) {
            return $this->json(
                [
                    'error' => "Authentication Error",
                    'message' => $e->getMessage(),
                ],
                401
            );
        }

        if ($password->getVault()->getId() === $dto->vaultId && $password->getFolder()?->getId() === $dto->folderId) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $entityManager->getRepository(Vault::class);
        $vaultId = $dto->vaultId;
        $vault = $vaultRepository->findByIds(
            [$vaultId],
            [
                "PARTIAL v.{id, name, mandatoryPasswordFields, allowPasswordsAtRoot}",
                "PARTIAL gv.{group, vault, canWrite, partial}",
                "PARTIAL g.{id, name, private, publicKey}",
            ],
            groupAlias: 'g',
            groupVaultAlias: 'gv'
        )[$vaultId] ?? null;

        if (is_null($vault) || !$vault->hasReadPermission($loggedInUser->getGroupIds())) {
            throw new BadRequestHttpException("Invalid vault ID.");
        }

        if (!$vault->isAllowPasswordsAtRoot() && is_null($dto->folderId)) {
            throw new BadRequestHttpException("The destination vault only allows passwords to be created in folders.");
        }

        $folder = null;
        if (!is_null($dto->folderId)) {
            /** @var FolderRepository $folderRepository */
            $folderRepository = $entityManager->getRepository(Folder::class);
            $folder = $folderRepository->findByIds(
                [$dto->folderId],
                [
                    "PARTIAL f.{id, name, externalId}",
                    "PARTIAL fg.{folder, group, canWrite, partial}",
                    "PARTIAL g.{id, name, private, publicKey}",
                    "PARTIAL v.{id}",
                ],
                groupAlias: "g",
                folderGroupAlias: "fg",
                vaultAlias: "v"
            )[$dto->folderId] ?? null;

            if (is_null($folder) || !$folder->hasReadPermission($loggedInUser->getGroupIds())) {
                throw new BadRequestHttpException("Invalid folder ID.");
            }

            if ($folder->getVault()->getId() !== $vault->getId()) {
                throw new BadRequestHttpException("Folder is not in the given vault.");
            }

            if (!$folder->hasWritePermission($loggedInUser->getGroupIds())) {
                throw new BadRequestHttpException("You don't have permission to move the password to this folder.");
            }
        } elseif (!$vault->hasWritePermission($loggedInUser->getGroupIds())) {
            throw new BadRequestHttpException("You don't have permission to move the password to this vault.");
        }

        $this->validateMandatoryFields($password, $vault);

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
        $groupIds = array_map(fn($g) => $g->getId(), $groups);

        $hasWrite = false;
        foreach ($groups as $g) {
            if (($groupPermissions[$g->getId()] ?? false) === true) {
                $hasWrite = true;
                break;
            }
        }

        if (!$hasWrite) {
            throw new BadRequestHttpException("At least one group must have write access on the password.");
        }

        $userIdentifier = $loggedInUser->getUserIdentifier();

        // Current relations
        $currentGroupPasswords = [];
        foreach ($password->getGroupPasswords() as $groupPassword) {
            $currentGroupPasswords[$groupPassword->getGroup()->getId()] = $groupPassword;
        }
        $currentIds = array_keys($currentGroupPasswords);

        $toRemove = array_diff($currentIds, $groupIds);
        $toAdd = array_diff($groupIds, $currentIds);
        $toKeep = array_intersect($currentIds, $groupIds);

        if (!empty($toAdd)) {
            $decryptionData = $this->findDecryptionData($password);
            if (is_null($decryptionData)) {
                $this->encryptionService->secureMemzero($decryptedPrivateKey);
                return $this->json([
                    'error' => "Decryption Error",
                    'message' => "No valid decryption key found for this password.",
                ]);
            }

            $groupUser = $decryptionData['groupUser'];
            $existingGroupPassword = $decryptionData['groupPassword'];
            $decryptedPasswordKey = $this->decryptPasswordKey($groupUser, $existingGroupPassword, $decryptedPrivateKey);
            $this->encryptionService->secureMemzero($decryptedPrivateKey);

            foreach ($groups as $group) {
                if (!in_array($group->getId(), $toAdd, true)) {
                    continue;
                }

                $keys = $encryptionService->encryptPasswordKeyForGroup($decryptedPasswordKey, $group->getPublicKey());

                $groupPassword = new GroupsPassword()->setPassword($password)
                                                     ->setGroup($group)
                                                     ->setNonce($keys['nonce'])
                                                     ->setEncryptedPasswordKey($keys['encryptedPasswordKey'])
                                                     ->setEncryptionPublicKey($keys['encryptionPublicKey'])
                                                     ->setCanWrite($groupPermissions[$group->getId()] ?? true)
                                                     ->setCreatedBy($userIdentifier);

                $password->addGroupPassword($groupPassword);
                $entityManager->persist($groupPassword);
            }

            // Zero out sensitive key
            $encryptionService->secureMemzero($decryptedPasswordKey);
        }

        foreach ($toKeep as $groupId) {
            /** @var GroupsPassword $groupPassword */
            $groupPassword = $currentGroupPasswords[$groupId];
            $newCanWrite = $groupPermissions[$groupId] ?? $groupPassword->canWrite();
            if ($groupPassword->canWrite() !== $newCanWrite) {
                $groupPassword->setCanWrite($newCanWrite)
                              ->setUpdatedBy($userIdentifier);
                $entityManager->persist($groupPassword);
            }
        }

        foreach ($toRemove as $groupId) {
            /** @var GroupsPassword $groupPassword */
            $groupPassword = $currentGroupPasswords[$groupId];
            $password->getGroupPasswords()
                     ->removeElement($groupPassword);
            $entityManager->remove($groupPassword);
        }

        $password->setVault($vault)
                 ->setFolder($folder)
                 ->setUpdatedBy($userIdentifier);

        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Validate all mandatory fields of the destination vault are set.
     *
     * @param  Password  $password
     * @param  Vault  $vault
     *
     * @return void
     * @throws BadRequestHttpException
     */
    private function validateMandatoryFields(Password $password, Vault $vault): void
    {
        $mandatoryFields = $vault->getMandatoryPasswordFields() ?? [];
        if (empty($mandatoryFields)) {
            return;
        }

        $missingFields = [];

        if (is_null($password->getExternalId()) && in_array(PasswordField::ExternalId, $mandatoryFields)) {
            $missingFields[] = "externalId";
        }

        if (is_null($password->getLocation()) && in_array(PasswordField::Location, $mandatoryFields)) {
            $missingFields[] = "location";
        }

        if (is_null($password->getTarget()) && in_array(PasswordField::Target, $mandatoryFields)) {
            $missingFields[] = "target";
        }

        if (!empty($missingFields)) {
            throw new BadRequestHttpException(
                "The following mandatory fields are missing for the destination: " . implode(", ", $missingFields)
            );
        }
    }
}
