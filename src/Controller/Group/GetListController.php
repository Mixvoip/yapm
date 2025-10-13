<?php

/**
 * @author bsteffan
 * @since 2025-04-28
 */

namespace App\Controller\Group;

use App\Controller\Enums\DisplayType;
use App\Entity\User;
use App\Normalizer\GroupNormalizer;
use App\Repository\GroupRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class GetListController extends AbstractController
{
    /**
     * Get a list of groups.
     *
     * @param  GroupRepository  $groupRepository
     * @param  string  $search
     * @param  DisplayType  $displayType
     *
     * @return JsonResponse
     */
    #[Route("/groups", name: "api_groups_list", methods: ["GET"])]
    public function index(
        GroupRepository $groupRepository,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] DisplayType $displayType = DisplayType::List
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $context = [GroupNormalizer::MINIMISED];
        $fields = ["PARTIAL g.{id, name}"];
        $userAlias = null;
        $groupUserAlias = null;

        if ($displayType === DisplayType::Table) {
            $fields = [
                "PARTIAL g.{id, name, private, createdAt, createdBy, updatedAt, updatedBy}",
                "PARTIAL gu.{group, user, manager}",
                "PARTIAL u.{id, email, username}",
            ];
            $context = [
                GroupNormalizer::WITH_USER_COUNT,
                GroupNormalizer::WITH_MANAGERS,
            ];
            $userAlias = "u";
            $groupUserAlias = "gu";
        }

        $groups = $groupRepository->searchGroups(
            $fields,
            userAlias: $userAlias,
            groupUserAlias: $groupUserAlias,
            search: $search
        );

        if ($loggedInUser->isAdmin() || $displayType === DisplayType::List) {
            return $this->json($groups, context: $context);
        }

        $managerGroupIds = $loggedInUser->getManagedGroupIds();
        $managerGroups = [];
        foreach ($groups as $group) {
            if (in_array($group->getId(), $managerGroupIds)) {
                $managerGroups[] = $group;
            }
        }

        return $this->json($managerGroups, context: $context);
    }
}
