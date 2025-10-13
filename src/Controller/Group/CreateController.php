<?php

/**
 * @author bsteffan
 * @since 2025-05-26
 */

namespace App\Controller\Group;

use App\Controller\Group\Dto\CreateDto;
use App\Domain\AppConstants;
use App\Entity\Group;
use App\Entity\GroupsUser;
use App\Entity\User;
use App\Normalizer\GroupNormalizer;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\Encryption\EncryptionService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CreateController extends AbstractController
{
    /**
     * Create a new group.
     *
     * @param  CreateDto  $createDto
     * @param  EntityManagerInterface  $entityManager
     * @param  EncryptionService  $encryptionService
     *
     * @return JsonResponse
     * @throws RandomException
     */
    #[Route("/groups", name: "api_groups_create", methods: ["POST"])]
    #[isGranted('ROLE_ADMIN')]
    public function index(
        #[MapRequestPayload] CreateDto $createDto,
        EntityManagerInterface $entityManager,
        EncryptionService $encryptionService
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $entityManager->getFilters()->suspend(AppConstants::EXCLUDE_DELETED_FILTER);
        /** @var GroupRepository $groupRepository */
        $groupRepository = $entityManager->getRepository(Group::class);
        $existingGroup = $groupRepository->findOneBy(['name' => $createDto->name]);
        $entityManager->getFilters()->restore(AppConstants::EXCLUDE_DELETED_FILTER);

        if (!is_null($existingGroup)) {
            return $this->json(['id' => $existingGroup->getId()], 409);
        }

        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);

        $userIds = array_unique(array_merge($createDto->users, $createDto->managers));
        $users = $userRepository->findByIds(
            $userIds,
            ["PARTIAL u.{id, email, username, publicKey}"]
        );
        if (count($users) !== count($userIds)) {
            throw new BadRequestHttpException("Invalid user IDs.");
        }

        $groupKeys = $encryptionService->generateGroupKeypair();
        $group = new Group()->setName($createDto->name)
                            ->setPublicKey($groupKeys['publicKey'])
                            ->setCreatedBy($loggedInUser->getUserIdentifier());
        $entityManager->persist($group);

        $groupsUsers = new ArrayCollection();
        foreach ($users as $user) {
            $encryptedKeyData = $encryptionService->encryptGroupPrivateKeyForUser(
                $groupKeys['privateKey'],
                $user->getPublicKey()
            );

            $groupsUser = new GroupsUser()->setGroup($group)
                                          ->setUser($user)
                                          ->setEncryptedGroupPrivateKey($encryptedKeyData['encryptedGroupPrivateKey'])
                                          ->setGroupPrivateKeyNonce($encryptedKeyData['groupPrivateKeyNonce'])
                                          ->setEncryptionPublicKey($encryptedKeyData['encryptionPublicKey'])
                                          ->setCreatedBy($loggedInUser->getUserIdentifier());

            if (in_array($user->getId(), $createDto->managers)) {
                $groupsUser->setManager(true);
            }

            $entityManager->persist($groupsUser);
            $groupsUsers->add($groupsUser);
            $entityManager->persist($user);
        }

        $encryptionService->secureMemzero($groupKeys['privateKey']);
        $group->setGroupUsers($groupsUsers);

        $entityManager->flush();

        return $this->json(
            $group,
            201,
            context: [
                GroupNormalizer::WITH_USERS,
                GroupNormalizer::WITH_MANAGERS,
            ]
        );
    }
}
