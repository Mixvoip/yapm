<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Normalizer\UserNormalizer;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetController extends AbstractController
{
    /**
     * Get a user by ID.
     *
     * @param  string  $id
     * @param  UserRepository  $userRepository
     *
     * @return JsonResponse
     */
    #[Route(
        '/users/{id}',
        name: 'api_users_get',
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ['GET']
    )]
    public function index(string $id, UserRepository $userRepository): JsonResponse
    {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        if (!$loggedInUser->isAdmin() && $id !== $loggedInUser->getId()) {
            throw $this->createNotFoundException("User with id: $id not found.");
        }

        $user = $userRepository->findByIds(
            [$id],
            [
                "PARTIAL u.{id, email, username, admin, active, verified, createdAt, updatedAt, createdBy, updatedBy}",
                "PARTIAL gu.{group, user, manager}",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            groupUserAlias: "gu"
        )[$id] ?? null;

        if (is_null($user)) {
            throw $this->createNotFoundException("User with id: $id not found.");
        }

        return $this->json($user, context: [UserNormalizer::WITH_GROUPS]);
    }
}
