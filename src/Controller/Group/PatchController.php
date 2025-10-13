<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Controller\Group;

use App\Controller\EncryptionAwareTrait;
use App\Controller\Group\Dto\PatchDto;
use App\Entity\Group;
use App\Entity\GroupsUser;
use App\Entity\User;
use App\Normalizer\GroupNormalizer;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class PatchController extends AbstractController
{
    use EncryptionAwareTrait;

    /**
     * Update a group.
     *
     * @param  string  $id
     * @param  PatchDto  $patchDto
     * @param  EntityManagerInterface  $entityManager
     * @param  UserPasswordHasherInterface  $passwordHasher
     * @param  EncryptionService  $encryptionService
     *
     * @return JsonResponse
     * @throws RandomException
     */
    #[Route(
        "/groups/{id}",
        name: "api_groups_patch",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["PATCH"]
    )]
    public function index(
        string $id,
        #[MapRequestPayload] PatchDto $patchDto,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        EncryptionService $encryptionService
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $this->passwordHasher = $passwordHasher;
        $this->encryptionService = $encryptionService;

        try {
            $decryptedPrivateKey = $this->decryptUserPrivateKey($patchDto->getEncryptedPassword());
        } catch (Exception $e) {
            return $this->json(
                [
                    'error' => "Authentication Error",
                    'message' => $e->getMessage(),
                ],
                401
            );
        }

        $isGroupManager = in_array($id, $loggedInUser->getManagedGroupIds());
        $isAdmin = $loggedInUser->isAdmin();
        if (!$isAdmin && !$isGroupManager) {
            throw $this->createNotFoundException("Group with id: $id not found.");
        }

        $decryptedGroupKey = null;
        if ($isGroupManager) {
            $groupUser = $loggedInUser->getGroupUserForGroup($id);
            $decryptedGroupKey = $encryptionService->decryptGroupPrivateKey(
                $groupUser->getEncryptedGroupPrivateKey(),
                $groupUser->getGroupPrivateKeyNonce(),
                $groupUser->getEncryptionPublicKey(),
                $decryptedPrivateKey,
            );
        }

        $encryptionService->secureMemzero($decryptedPrivateKey);

        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $group = $groupRepository->findByIds(
            [$id],
            [
                "PARTIAL g.{id, name, private, createdAt, createdBy, updatedAt, updatedBy}",
                "gu",
                "PARTIAL u.{id, email, verified, username}",
            ],
            userAlias: "u",
            groupUserAlias: "gu"
        )[$id] ?? null;

        if (is_null($group)) {
            throw $this->createNotFoundException("Group with id: $id not found.");
        }

        $updated = false;
        $flush = false;
        $uniqueUserIds = array_unique($patchDto->getUsers() ?? []);
        $uniqueManagerIds = array_unique($patchDto->getManagers() ?? []);
        $allUserIds = array_unique(array_merge($uniqueUserIds, $uniqueManagerIds));

        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        $users = $userRepository->findByIds(
            $allUserIds,
            ["PARTIAL u.{id, email, verified, username, publicKey}"]
        );

        if (count($users) !== count($allUserIds)) {
            throw new BadRequestHttpException("Invalid user IDs.");
        }

        foreach ($users as $user) {
            if (!$user->isVerified()) {
                throw new BadRequestHttpException("User with id: {$user->getId()} is not verified.");
            }
        }

        $currentGroupUsers = $group->getGroupUsers();

        if (!is_null($patchDto->getManagers())) {
            $currentManagerIds = $group->getManagerIds();
            $managersToAdd = array_diff($uniqueManagerIds, $currentManagerIds);
            if (!empty($managersToAdd)) {
                if (!$isAdmin) {
                    throw new BadRequestHttpException("Only admins can add managers to a group.");
                }

                foreach ($currentGroupUsers as $groupUser) {
                    if (in_array($groupUser->getUser()->getId(), $managersToAdd)) {
                        $groupUser->setManager(true)
                                  ->setUpdatedBy($loggedInUser->getUserIdentifier());

                        $entityManager->persist($groupUser);
                        $managersToAdd = array_diff($managersToAdd, [$groupUser->getUser()->getId()]);
                        $flush = true;
                    }
                }

                if ($isGroupManager && !empty($managersToAdd) && !is_null($decryptedGroupKey)) {
                    foreach ($managersToAdd as $managerToAdd) {
                        $user = $users[$managerToAdd];

                        $encryptedKeyData = $encryptionService->encryptGroupPrivateKeyForUser(
                            $decryptedGroupKey,
                            $user->getPublicKey()
                        );

                        $groupUser = new GroupsUser()->setGroup($group)
                                                     ->setUser($user)
                                                     ->setManager(true)
                                                     ->setEncryptedGroupPrivateKey(
                                                         $encryptedKeyData['encryptedGroupPrivateKey']
                                                     )
                                                     ->setGroupPrivateKeyNonce(
                                                         $encryptedKeyData['groupPrivateKeyNonce']
                                                     )
                                                     ->setEncryptionPublicKey($encryptedKeyData['encryptionPublicKey'])
                                                     ->setCreatedBy($loggedInUser->getUserIdentifier());
                        $entityManager->persist($groupUser);
                        $currentGroupUsers->add($groupUser);
                        $updated = true;
                    }
                }
            }

            $managersToRemove = array_diff($currentManagerIds, $uniqueManagerIds);
            if (!empty($managersToRemove)) {
                if (!$isAdmin) {
                    throw new BadRequestHttpException("Only admins can remove managers from a group.");
                }

                foreach ($currentGroupUsers as $groupUser) {
                    if (in_array($groupUser->getUser()->getId(), $managersToRemove)) {
                        $groupUser->setManager(false)
                                  ->setUpdatedBy($loggedInUser->getUserIdentifier());

                        $entityManager->persist($groupUser);
                        $flush = true;
                    }
                }
            }
        }

        if (!is_null($patchDto->getUsers())) {
            $currentUserIds = array_map(fn($gu) => $gu->getUser()->getId(), $currentGroupUsers->toArray());
            $usersToAdd = array_diff($uniqueUserIds, $currentUserIds);
            if (!empty($usersToAdd)) {
                if (!$isGroupManager && is_null($decryptedGroupKey)) {
                    throw new BadRequestHttpException("Only group managers can add users to a group.");
                }

                foreach ($usersToAdd as $userToAdd) {
                    $user = $users[$userToAdd];

                    $encryptedKeyData = $encryptionService->encryptGroupPrivateKeyForUser(
                        $decryptedGroupKey,
                        $user->getPublicKey()
                    );

                    $groupUser = new GroupsUser()->setGroup($group)
                                                 ->setUser($user)
                                                 ->setEncryptedGroupPrivateKey(
                                                     $encryptedKeyData['encryptedGroupPrivateKey']
                                                 )
                                                 ->setGroupPrivateKeyNonce($encryptedKeyData['groupPrivateKeyNonce'])
                                                 ->setEncryptionPublicKey($encryptedKeyData['encryptionPublicKey'])
                                                 ->setCreatedBy($loggedInUser->getUserIdentifier());
                    $entityManager->persist($groupUser);
                    $currentGroupUsers->add($groupUser);
                    $updated = true;
                }

                $encryptionService->secureMemzero($decryptedGroupKey);
            }

            $usersToRemove = array_diff($currentUserIds, $uniqueUserIds);
            if (!empty($usersToRemove)) {
                $remainingUsers = array_diff($currentUserIds, $usersToRemove);

                if (count($remainingUsers) === 0) {
                    throw new BadRequestHttpException("Cannot remove the last user from a group.");
                }

                foreach ($currentGroupUsers as $groupUser) {
                    if (in_array($groupUser->getUser()->getId(), $usersToRemove)) {
                        $entityManager->remove($groupUser);
                        $currentGroupUsers->removeElement($groupUser);
                        $updated = true;
                    }
                }
            }
        }

        $encryptionService->secureMemzero($decryptedGroupKey);

        if ($updated) {
            $group->setUpdatedBy($loggedInUser->getUserIdentifier());
            $entityManager->persist($group);
            $flush = true;
        }

        if ($flush) {
            $entityManager->flush();
        }

        return $this->json(
            $group,
            context: [
                GroupNormalizer::WITH_USERS,
                GroupNormalizer::WITH_MANAGERS,
            ]
        );
    }
}
