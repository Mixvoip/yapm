<?php

/**
 * @author bsteffan
 * @since 2025-08-12
 */

namespace App\Controller\Password;

use App\Controller\Dto\GroupPermissionDto;
use App\Controller\Dto\UserPermissionDto;
use App\Controller\EncryptionAwareTrait;
use App\Controller\GroupInheritanceTrait;
use App\Controller\GroupValidationTrait;
use App\Controller\Password\Dto\PatchPermissionsDto;
use App\Entity\Group;
use App\Entity\GroupsPassword;
use App\Entity\Password;
use App\Entity\User;
use App\Message\PartialAccessCleanUpMessage;
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
use Symfony\Component\Messenger\MessageBusInterface;
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
        EncryptionService $encryptionService,
        MessageBusInterface $messageBus
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
                "PARTIAL g.{id, name, publicKey, private}",
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

        // Validate at least one permission is provided
        $this->assertAtLeastOnePermission($dto->groups, $dto->userPermissions);

        // Process regular groups
        $requestedGroupIds = array_map(fn(GroupPermissionDto $g) => $g->groupId, $dto->groups);
        if (!empty($requestedGroupIds)) {
            $this->assertNoDuplicateGroupIds($requestedGroupIds);
        }

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $groups = [];
        if (!empty($requestedGroupIds)) {
            $groups = $groupRepository->findByIds(
                $requestedGroupIds,
                ["PARTIAL g.{id, name, publicKey, private}"]
            );
            $this->assertGroupsExist($requestedGroupIds, $groups);
            $this->assertNoPrivateGroups($groups);
        }

        // Process user permissions (private groups)
        $requestedUserIds = array_map(fn(UserPermissionDto $u) => $u->userId, $dto->userPermissions);
        $privateGroups = [];
        $userIdToGroupId = [];
        if (!empty($requestedUserIds)) {
            $this->assertNoDuplicateUserIds($requestedUserIds);
            $privateGroups = $groupRepository->findPrivateGroupsByUserIds($requestedUserIds);
            $this->assertUsersExist($requestedUserIds, $privateGroups);

            // Build userId -> groupId map
            foreach ($privateGroups as $group) {
                foreach ($group->getGroupUsers() as $gu) {
                    $userIdToGroupId[$gu->getUser()->getId()] = $group->getId();
                }
            }
        }

        // Map requested canWrite flags by group id (regular groups)
        $groupPermissions = [];
        foreach ($dto->groups as $groupDto) {
            $groupPermissions[$groupDto->groupId] = $groupDto->canWrite;
        }

        // Map requested canWrite flags by group id (private groups / user permissions)
        $userPermissions = [];
        foreach ($dto->userPermissions as $userDto) {
            $groupId = $userIdToGroupId[$userDto->userId];
            $userPermissions[$groupId] = $userDto->canWrite;
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

        // Split current relations into regular groups and private groups
        $currentRegularGroups = [];
        $currentPrivateGroups = [];
        foreach ($password->getGroupPasswords() as $groupPassword) {
            if ($groupPassword->getGroup()->isPrivate()) {
                $currentPrivateGroups[$groupPassword->getGroup()->getId()] = $groupPassword;
            } else {
                $currentRegularGroups[$groupPassword->getGroup()->getId()] = $groupPassword;
            }
        }

        // Calculate diff for regular groups
        $requestedRegularIds = array_map(fn($g) => $g->getId(), $groups);
        $toRemoveRegular = array_diff(array_keys($currentRegularGroups), $requestedRegularIds);
        $toAddRegular = array_diff($requestedRegularIds, array_keys($currentRegularGroups));
        $toKeepRegular = array_intersect(array_keys($currentRegularGroups), $requestedRegularIds);

        // Calculate diff for private groups (user permissions)
        $requestedPrivateIds = array_keys($userPermissions);
        $toRemovePrivate = array_diff(array_keys($currentPrivateGroups), $requestedPrivateIds);
        $toAddPrivate = array_diff($requestedPrivateIds, array_keys($currentPrivateGroups));
        $toKeepPrivate = array_intersect(array_keys($currentPrivateGroups), $requestedPrivateIds);

        // Enforce at least one group or user with write access
        $hasWriteAccess = array_any($groupPermissions, fn($canWrite) => $canWrite === true)
                          || array_any($userPermissions, fn($canWrite) => $canWrite === true);

        if (!$hasWriteAccess) {
            $this->encryptionService->secureMemzero($decryptedPasswordKey);
            throw new BadRequestHttpException("At least one group or user must have write access to the password.");
        }

        // Add new regular group relations (with encrypted password key)
        $addedGroups = [];
        foreach ($groups as $group) {
            if (!in_array($group->getId(), $toAddRegular, true)) {
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

        // Add new private group relations (user permissions)
        $addedPrivateGroups = [];
        foreach ($privateGroups as $group) {
            if (!in_array($group->getId(), $toAddPrivate, true)) {
                continue;
            }

            $keys = $encryptionService->encryptPasswordKeyForGroup($decryptedPasswordKey, $group->getPublicKey());

            $groupPassword = new GroupsPassword()->setPassword($password)
                                                 ->setGroup($group)
                                                 ->setNonce($keys['nonce'])
                                                 ->setEncryptedPasswordKey($keys['encryptedPasswordKey'])
                                                 ->setEncryptionPublicKey($keys['encryptionPublicKey'])
                                                 ->setCanWrite($userPermissions[$group->getId()] ?? true)
                                                 ->setCreatedBy($userIdentifier);

            $password->addGroupPassword($groupPassword);
            $entityManager->persist($groupPassword);
            $addedPrivateGroups[] = $group;
        }

        // Zero out sensitive key
        $encryptionService->secureMemzero($decryptedPasswordKey);

        // Propagate newly added regular groups to parent folder and vault
        if (!empty($addedGroups)) {
            $folder = $password->getFolder();
            if (!is_null($folder)) {
                $this->addMissingGroupsToParentFolder($folder, $addedGroups, $userIdentifier, $entityManager);
            }

            $this->addMissingGroupsToVault($password->getVault(), $addedGroups, $userIdentifier, $entityManager);
        }

        // Propagate newly added private groups to parent folder and vault (as partial)
        if (!empty($addedPrivateGroups)) {
            $folder = $password->getFolder();
            if (!is_null($folder)) {
                $this->addMissingGroupsToParentFolder($folder, $addedPrivateGroups, $userIdentifier, $entityManager);
            }

            $this->addMissingGroupsToVault($password->getVault(), $addedPrivateGroups, $userIdentifier, $entityManager);
        }

        // Update canWrite for kept regular groups
        foreach ($toKeepRegular as $groupId) {
            /** @var GroupsPassword $groupPassword */
            $groupPassword = $currentRegularGroups[$groupId];
            $newCanWrite = $groupPermissions[$groupId] ?? $groupPassword->canWrite();
            if ($groupPassword->canWrite() !== $newCanWrite) {
                $groupPassword->setCanWrite($newCanWrite)
                              ->setUpdatedBy($userIdentifier);
                $entityManager->persist($groupPassword);
            }
        }

        // Update canWrite for kept private groups (user permissions)
        foreach ($toKeepPrivate as $groupId) {
            /** @var GroupsPassword $groupPassword */
            $groupPassword = $currentPrivateGroups[$groupId];
            $newCanWrite = $userPermissions[$groupId] ?? $groupPassword->canWrite();
            if ($groupPassword->canWrite() !== $newCanWrite) {
                $groupPassword->setCanWrite($newCanWrite)
                              ->setUpdatedBy($userIdentifier);
                $entityManager->persist($groupPassword);
            }
        }

        // Remove regular group relations
        foreach ($toRemoveRegular as $groupId) {
            /** @var GroupsPassword $groupPassword */
            $groupPassword = $currentRegularGroups[$groupId];
            $password->getGroupPasswords()
                     ->removeElement($groupPassword);
            $entityManager->remove($groupPassword);
        }

        // Remove private group relations (user permissions)
        foreach ($toRemovePrivate as $groupId) {
            /** @var GroupsPassword $groupPassword */
            $groupPassword = $currentPrivateGroups[$groupId];
            $password->getGroupPasswords()
                     ->removeElement($groupPassword);
            $entityManager->remove($groupPassword);
        }

        // Mark parent as partial for removed regular groups
        if (!empty($toRemoveRegular)) {
            $folder = $password->getFolder();
            if (!is_null($folder)) {
                $this->markParentFolderAsPartial($folder, $toRemoveRegular, $userIdentifier, $entityManager);
            }

            $this->markVaultAsPartial($password->getVault(), $toRemoveRegular, $userIdentifier, $entityManager);
        }

        // Mark parent as partial for removed private groups (for cleanup)
        if (!empty($toRemovePrivate)) {
            $folder = $password->getFolder();
            if (!is_null($folder)) {
                $this->markParentFolderAsPartial($folder, $toRemovePrivate, $userIdentifier, $entityManager);
            }

            $this->markVaultAsPartial($password->getVault(), $toRemovePrivate, $userIdentifier, $entityManager);
        }

        $entityManager->flush();

        // Dispatch partial access cleanup for removed groups
        $removedGroupIds = array_merge($toRemoveRegular, $toRemovePrivate);
        if (!empty($removedGroupIds)) {
            $messageBus->dispatch(
                new PartialAccessCleanUpMessage(
                    $password->getVault()->getId(),
                    $removedGroupIds
                )
            );
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
