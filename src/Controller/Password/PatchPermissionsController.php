<?php

/**
 * @author bsteffan
 * @since 2025-08-12
 */

namespace App\Controller\Password;

use App\Controller\Dto\GroupPermissionDto;
use App\Controller\EncryptionAwareTrait;
use App\Controller\GroupInheritanceTrait;
use App\Controller\GroupValidationTrait;
use App\Controller\Password\Dto\PatchPermissionsDto;
use App\Entity\Group;
use App\Entity\GroupsPassword;
use App\Entity\Password;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Repository\PasswordRepository;
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

class PatchPermissionsController extends AbstractController
{
    use EncryptionAwareTrait;
    use GroupInheritanceTrait;
    use GroupValidationTrait;

    /**
     * Patch permissions for a password by managing its group access list.
     *
     * @param  string  $id
     * @param  EntityManagerInterface  $entityManager
     * @param  PatchPermissionsDto  $dto
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     *
     * @return Response
     * @throws RandomException
     */
    #[Route(
        "/passwords/{id}/permissions",
        name: "api_patch_passwords_id_permissions",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["PATCH"]
    )]
    public function index(
        string $id,
        EntityManagerInterface $entityManager,
        #[MapRequestPayload] PatchPermissionsDto $dto,
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
                "PARTIAL p.{id, title, externalId, target, updatedAt, updatedBy}",
                "PARTIAL gp.{group, password, encryptedPasswordKey, encryptionPublicKey, nonce, canWrite}",
                "PARTIAL g.{id, name, publicKey}",
                "PARTIAL v.{id, name}",
                "PARTIAL f.{id, name}",
            ],
            folderAlias: 'f',
            groupAlias: 'g',
            groupPasswordAlias: 'gp',
            vaultAlias: 'v'
        )[$id] ?? null;

        if (is_null($password) || !$password->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Password with id: $id not found.");
        }

        if ($password->getVault()->isPrivate()) {
            throw new BadRequestHttpException("You can't update permissions for a private vault.");
        }

        if (!$password->hasWritePermission($loggedInUser->getGroupIds())) {
            throw $this->createAccessDeniedException("You don't have permission to update this password.");
        }

        $requestedGroupIds = array_map(fn(GroupPermissionDto $g) => $g->groupId, $dto->groups);
        $this->assertNoDuplicateGroupIds($requestedGroupIds);

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $groups = $groupRepository->findByIds(
            $requestedGroupIds,
            ["PARTIAL g.{id, name, publicKey}"]
        );
        $this->assertGroupsExist($requestedGroupIds, $groups);
        $this->assertNoPrivateGroups($groups);

        // Map requested canWrite flags by group id
        $groupPermissions = [];
        foreach ($dto->groups as $groupDto) {
            $groupPermissions[$groupDto->groupId] = $groupDto->canWrite;
        }

        // Decrypt user's private key and the password key for re-encryption
        try {
            $decryptedPrivateKey = $this->decryptUserPrivateKey($dto->encryptedPassword);
        } catch (Exception $e) {
            return $this->json(
                [
                    'error' => "Authentication Error",
                    'message' => $e->getMessage(),
                ],
                401
            );
        }

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

        $userIdentifier = $loggedInUser->getUserIdentifier();

        // Current relations
        $currentGroupPasswords = [];
        foreach ($password->getGroupPasswords() as $groupPassword) {
            $currentGroupPasswords[$groupPassword->getGroup()->getId()] = $groupPassword;
        }
        $currentIds = array_keys($currentGroupPasswords);

        $requestedIds = array_map(fn($g) => $g->getId(), $groups);

        $toRemove = array_diff($currentIds, $requestedIds);
        $toAdd = array_diff($requestedIds, $currentIds);
        $toKeep = array_intersect($currentIds, $requestedIds);

        // Enforce at least one group with write access remains
        $hasWriteAccess = false;
        foreach ($requestedIds as $groupId) {
            if (($groupPermissions[$groupId] ?? false) === true) {
                $hasWriteAccess = true;
                break;
            }
        }

        if (!$hasWriteAccess) {
            $this->encryptionService->secureMemzero($decryptedPasswordKey);
            throw new BadRequestHttpException("At least one group must have write access to the password.");
        }

        // Add new group relations (with encrypted password key)
        $addedGroups = [];
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
            $addedGroups[] = $group;
        }

        // Zero out sensitive key
        $encryptionService->secureMemzero($decryptedPasswordKey);

        // Propagate newly added groups to parent folder and vault
        if (!empty($addedGroups)) {
            $folder = $password->getFolder();
            if (!is_null($folder)) {
                $this->addMissingGroupsToParentFolder($folder, $addedGroups, $userIdentifier, $entityManager);
            }

            $this->addMissingGroupsToVault($password->getVault(), $addedGroups, $userIdentifier, $entityManager);
        }

        // Update canWrite for kept groups
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

        // Remove group relations (also keep in-memory collection consistent)
        foreach ($toRemove as $groupId) {
            /** @var GroupsPassword $groupPassword */
            $groupPassword = $currentGroupPasswords[$groupId];
            $password->getGroupPasswords()
                     ->removeElement($groupPassword);
            $entityManager->remove($groupPassword);
        }

        if (!empty($toRemove)) {
            $folder = $password->getFolder();
            if (!is_null($folder)) {
                $this->markParentFolderAsPartial($folder, $toRemove, $userIdentifier, $entityManager);
            }

            $this->markVaultAsPartial($password->getVault(), $toRemove, $userIdentifier, $entityManager);
        }

        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
