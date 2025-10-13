<?php

namespace App\Controller\User;

use App\Controller\Enums\DisplayType;
use App\Entity\User;
use App\Normalizer\UserNormalizer;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class GetListController extends AbstractController
{
    /**
     * Get a list of users.
     *
     * @param  UserRepository  $userRepository
     * @param  string  $search
     * @param  DisplayType  $displayType
     * @param  bool  $activeOnly
     *
     * @return JsonResponse
     */
    #[Route('/users', name: 'api_users_list', methods: ['GET'])]
    public function index(
        UserRepository $userRepository,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] DisplayType $displayType = DisplayType::List,
        #[MapQueryParameter] bool $activeOnly = false
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        if (
            !$loggedInUser->isAdmin()
            && ($displayType !== DisplayType::List || empty($loggedInUser->getManagedGroupIds()))
        ) {
            throw $this->createAccessDeniedException();
        }

        $fields = ["PARTIAL u.{id, email, username}"];
        $context = [UserNormalizer::MINIMISED];
        $groupAlias = null;
        $groupUserAlias = null;

        if ($displayType === DisplayType::Table) {
            $context = [UserNormalizer::WITH_GROUPS];
            $fields = [
                "PARTIAL u.{id, email, username, admin, verified, active, createdAt, createdBy, updatedAt, updatedBy}",
                "PARTIAL gu.{group, user, manager}",
                "PARTIAL g.{id, name, private}",
            ];
            $groupAlias = "g";
            $groupUserAlias = "gu";
        }

        $users = $userRepository->searchUsers(
            $fields,
            groupAlias: $groupAlias,
            groupUserAlias: $groupUserAlias,
            search: $search,
            activeOnly: $activeOnly
        );

        return $this->json($users, context: $context);
    }
}
