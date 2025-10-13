<?php

namespace App\Controller\User;

use App\Controller\User\Dto\PatchDto;
use App\Entity\Group;
use App\Entity\User;
use App\Normalizer\UserNormalizer;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PatchController extends AbstractController
{
    /**
     * Update a user.
     *
     * @param  string  $id
     * @param  PatchDto  $patchDto
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     */
    #[Route(
        '/users/{id}',
        name: 'api_users_update',
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ['PATCH']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        string $id,
        #[MapRequestPayload] PatchDto $patchDto,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findByIds(
            [$id],
            [
                "PARTIAL u.{id, email, username, admin, verified, active, createdAt, createdBy, updatedAt, updatedBy}",
                "gu",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: 'g',
            groupUserAlias: "gu"
        )[$id] ?? null;

        if (is_null($user)) {
            throw $this->createNotFoundException("User with id: $id not found.");
        }

        $updated = false;
        if (!is_null($patchDto->isAdmin()) && $user->isAdmin() !== $patchDto->isAdmin()) {
            if ($user->isAdmin() && $userRepository->getAdminCount() <= 1 && $patchDto->isAdmin() === false) {
                throw $this->createAccessDeniedException();
            }

            $user->setAdmin($patchDto->isAdmin());
            $updated = true;
        }

        if (!is_null($patchDto->getGroups())) {
            /** @var GroupRepository $groupRepository */
            $groupRepository = $entityManager->getRepository(Group::class);
            $uniqueGroupIds = array_unique($patchDto->getGroups());
            $groups = $groupRepository->findByIds($uniqueGroupIds, ["PARTIAL g.{id}"]);
            if (count($groups) !== count($uniqueGroupIds)) {
                throw new BadRequestHttpException("Invalid group IDs.");
            }

            // remove private groups
            $currentGroupUsers = $user->getGroupUsers();
            $currentGroupIds = [];
            foreach ($currentGroupUsers as $groupUser) {
                if (!$groupUser->getGroup()->isPrivate()) {
                    $currentGroupIds[] = $groupUser->getGroup()->getId();
                }
            }

            $groupsToAdd = array_diff($uniqueGroupIds, $currentGroupIds);
            if (!empty($groupsToAdd)) {
                throw new BadRequestHttpException("Adding groups is not allowed.");
            }

            $groupsToRemove = array_diff($currentGroupIds, $uniqueGroupIds);
            if (!empty($groupsToRemove)) {
                foreach ($currentGroupUsers as $groupUser) {
                    if ($groupUser->getGroup()->isPrivate()) {
                        continue;
                    }

                    if (in_array($groupUser->getGroup()->getId(), $groupsToRemove)) {
                        $entityManager->remove($groupUser);
                        $currentGroupUsers->removeElement($groupUser);
                        $updated = true;
                    }
                }
            }
        }

        if ($updated) {
            $user->setUpdatedBy($loggedInUser->getUserIdentifier());

            $entityManager->persist($user);
            $entityManager->flush();
        }

        return $this->json($user, context: [UserNormalizer::WITH_GROUPS]);
    }
}
