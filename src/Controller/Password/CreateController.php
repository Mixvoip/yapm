<?php

/**
 * @author bsteffan
 * @since 2025-06-10
 */

namespace App\Controller\Password;

use App\Controller\Dto\GroupPermissionDto;
use App\Controller\EncryptionAwareTrait;
use App\Controller\GroupInheritanceTrait;
use App\Controller\GroupValidationTrait;
use App\Controller\Password\Dto\CreateDto;
use App\Entity\Enums\PasswordField;
use App\Entity\Folder;
use App\Entity\Group;
use App\Entity\GroupsPassword;
use App\Entity\Password;
use App\Entity\User;
use App\Entity\Vault;
use App\Normalizer\PasswordNormalizer;
use App\Repository\FolderRepository;
use App\Repository\GroupRepository;
use App\Repository\VaultRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CreateController extends AbstractController
{
    use GroupInheritanceTrait;
    use EncryptionAwareTrait;
    use GroupValidationTrait;

    /**
     * Create a new password.
     *
     * @param  CreateDto  $createDto
     * @param  EntityManagerInterface  $entityManager
     * @param  EncryptionService  $encryptionService
     *
     * @return JsonResponse
     * @throws RandomException
     */
    #[Route("/passwords", name: "api_passwords_create", methods: ["POST"])]
    public function index(
        #[MapRequestPayload] CreateDto $createDto,
        EntityManagerInterface $entityManager,
        EncryptionService $encryptionService
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();
        $groupIds = $loggedInUser->getGroupIds();
        $loggedInUserIdentifier = $loggedInUser->getUserIdentifier();
        $this->encryptionService = $encryptionService;

        /** @var VaultRepository $vaultRepository */
        $vaultRepository = $entityManager->getRepository(Vault::class);
        $vaultId = $createDto->getVaultId();
        $vault = $vaultRepository->findByIds(
            [$vaultId],
            [
                "PARTIAL v.{id, name, mandatoryFolderFields, allowPasswordsAtRoot}",
                "PARTIAL gv.{group, vault, canWrite, partial}",
                "PARTIAL g.{id, name, private, publicKey}",
            ],
            groupAlias: 'g',
            groupVaultAlias: 'gv'
        )[$vaultId] ?? null;

        if (is_null($vault) || !$vault->hasReadPermission($groupIds)) {
            throw new BadRequestHttpException("Invalid vault ID.");
        }

        if (!$vault->isAllowPasswordsAtRoot() && is_null($createDto->getFolderId())) {
            throw new BadRequestHttpException("This vault only allows passwords to be created in folders.");
        }

        $requestedGroupIds = array_map(fn(GroupPermissionDto $g) => $g->groupId, $createDto->getGroups());
        $this->assertNoDuplicateGroupIds($requestedGroupIds);

        $groups = [];
        $explicitGroupsProvided = !empty($requestedGroupIds);
        $folder = null;

        if ($vault->isPrivate() && $explicitGroupsProvided) {
            throw new BadRequestHttpException("Private vaults don't allow setting groups.");
        }

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);

        if ($explicitGroupsProvided) {
            $groups = $groupRepository->findByIds(
                $requestedGroupIds,
                ["PARTIAL g.{id, name, private, publicKey}"]
            );
            $this->assertGroupsExist($requestedGroupIds, $groups);
            $this->assertNoPrivateGroups($groups);

            $groupPermissions = [];
            foreach ($createDto->getGroups() as $groupDto) {
                $groupPermissions[$groupDto->groupId] = $groupDto->canWrite;
            }
        }

        if (!is_null($createDto->getFolderId())) {
            /** @var FolderRepository $folderRepository */
            $folderRepository = $entityManager->getRepository(Folder::class);
            $folder = $folderRepository->findByIds(
                [$createDto->getFolderId()],
                [
                    "PARTIAL f.{id, name, externalId}",
                    "PARTIAL fg.{folder, group, canWrite, partial}",
                    "PARTIAL g.{id, name, private, publicKey}",
                    "PARTIAL v.{id}",
                ],
                groupAlias: "g",
                folderGroupAlias: "fg",
                vaultAlias: "v"
            )[$createDto->getFolderId()] ?? null;

            if (is_null($folder) || !$folder->hasReadPermission($groupIds)) {
                throw new BadRequestHttpException("Invalid folder ID.");
            }

            if ($folder->getVault()->getId() !== $vault->getId()) {
                throw new BadRequestHttpException("Folder is not in the same vault.");
            }

            if (!$folder->hasWritePermission($groupIds)) {
                throw new BadRequestHttpException("You don't have permission to create a password in this folder.");
            }
        } elseif (!$vault->hasWritePermission($groupIds)) {
            throw new BadRequestHttpException("You don't have permission to create a password in this vault.");
        }

        // If no groups are explicitly provided, we use the folder's groups or the vault's groups.
        if (!$explicitGroupsProvided) {
            $groupPermissions = [];
            if ($folder) {
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
        }
        $groupIds = array_map(fn($g) => $g->getId(), $groups);

        // Guard: at least one write-capable group must be present
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

        $mandatoryPasswordFields = $vault->getMandatoryPasswordFields() ?? [];
        if (is_null($createDto->getExternalId() && in_array(PasswordField::ExternalId, $mandatoryPasswordFields))) {
            throw new BadRequestHttpException("External ID is mandatory for this vault.");
        }

        if (is_null($createDto->getLocation() && in_array(PasswordField::Location, $mandatoryPasswordFields))) {
            throw new BadRequestHttpException("Location is mandatory for this vault.");
        }

        if (is_null($createDto->getTarget() && in_array(PasswordField::Target, $mandatoryPasswordFields))) {
            throw new BadRequestHttpException("Target is mandatory for this vault.");
        }

        $passwordKey = $encryptionService->generatePasswordKey();
        $encryptedPassword = $this->encryptPasswordData($createDto->getEncryptedPassword(), $passwordKey);
        $encryptedUsername = $this->encryptPasswordData($createDto->getEncryptedUsername(), $passwordKey);

        $password = new Password()->setTitle($createDto->getTitle())
                                  ->setEncryptedPassword($encryptedPassword['encryptedData'])
                                  ->setPasswordNonce($encryptedPassword['encryptedDataNonce'])
                                  ->setTarget($createDto->getTarget())
                                  ->setDescription($createDto->getDescription())
                                  ->setLocation($createDto->getLocation())
                                  ->setExternalId($createDto->getExternalId())
                                  ->setVault($vault)
                                  ->setFolder($folder)
                                  ->setCreatedBy($loggedInUserIdentifier);

        if (!is_null($createDto->getEncryptedUsername())) {
            $password->setEncryptedUsername($encryptedUsername['encryptedData'])
                     ->setUsernameNonce($encryptedUsername['encryptedDataNonce']);
        }

        $entityManager->persist($password);

        foreach ($groups as $group) {
            $permission = $groupPermissions[$group->getId()] ?? false;

            $groupPasswordKeys = $encryptionService->encryptPasswordKeyForGroup($passwordKey, $group->getPublicKey());

            $groupPassword = new GroupsPassword()->setPassword($password)
                                                 ->setGroup($group)
                                                 ->setNonce($groupPasswordKeys['nonce'])
                                                 ->setEncryptedPasswordKey($groupPasswordKeys['encryptedPasswordKey'])
                                                 ->setEncryptionPublicKey($groupPasswordKeys['encryptionPublicKey'])
                                                 ->setCanWrite($permission)
                                                 ->setCreatedBy($loggedInUserIdentifier);

            $password->addGroupPassword($groupPassword);
            $entityManager->persist($groupPassword);
        }

        $encryptionService->secureMemzero($passwordKey);

        if (!is_null($folder)) {
            // Check if the parent is missing groups if explicit groups are provided.
            if ($explicitGroupsProvided) {
                $folderGroupIds = $folder->getGroupIds();
                $extraGroupIds = array_diff($groupIds, $folderGroupIds);

                if (!empty($extraGroupIds)) {
                    $this->addMissingGroupsToParentFolder(
                        $folder,
                        $groups,
                        $loggedInUserIdentifier,
                        $entityManager
                    );
                }

                $excludedGroupIds = array_diff($folderGroupIds, $groupIds);
                if (!empty($excludedGroupIds)) {
                    $this->markParentFolderAsPartial(
                        $folder,
                        $excludedGroupIds,
                        $loggedInUserIdentifier,
                        $entityManager
                    );
                }
            }
        }

        if ($explicitGroupsProvided) {
            $this->addMissingGroupsToVault($vault, $groups, $loggedInUserIdentifier, $entityManager);

            $excludedGroupIds = array_diff($vault->getGroupIds(), $groupIds);
            if (!empty($excludedGroupIds)) {
                $this->markVaultAsPartial(
                    $vault,
                    $excludedGroupIds,
                    $loggedInUserIdentifier,
                    $entityManager
                );
            }
        }

        $entityManager->flush();

        return $this->json(
            $password,
            201,
            context: [
                PasswordNormalizer::WITH_FOLDER,
                PasswordNormalizer::WITH_GROUPS,
            ]
        );
    }
}
