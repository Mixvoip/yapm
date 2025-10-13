<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Controller\Group;

use App\Entity\User;
use App\Normalizer\GroupNormalizer;
use App\Repository\GroupRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetController extends AbstractController
{
    /**
     * Get a group by id.
     *
     * @param  string  $id
     * @param  GroupRepository  $groupRepository
     *
     * @return JsonResponse
     */
    #[Route(
        "/groups/{id}",
        name: "api_groups_get",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["GET"]
    )]
    public function index(string $id, GroupRepository $groupRepository): JsonResponse
    {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        if (!$loggedInUser->isAdmin() && !in_array($id, $loggedInUser->getManagedGroupIds())) {
            throw $this->createNotFoundException("Group with id: $id not found.");
        }

        $group = $groupRepository->findByIds(
            [$id],
            [
                "PARTIAL g.{id, name, private, createdAt, createdBy, updatedAt, updatedBy}",
                "PARTIAL gu.{group, user, manager}",
                "PARTIAL u.{id, email, username}",
            ],
            userAlias: "u",
            groupUserAlias: "gu"
        )[$id] ?? null;

        if (is_null($group)) {
            throw $this->createNotFoundException("Group with id: $id not found.");
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
