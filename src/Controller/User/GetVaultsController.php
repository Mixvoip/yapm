<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Controller\User;

use App\Entity\User;
use App\Normalizer\VaultNormalizer;
use App\Repository\UserRepository;
use App\Repository\VaultRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetVaultsController extends AbstractController
{
    /**
     * Get all vaults a user has access to.
     *
     * @param  string  $id
     * @param  UserRepository  $userRepository
     * @param  VaultRepository  $vaultRepository
     *
     * @return JsonResponse
     */
    #[Route(
        '/users/{id}/vaults',
        name: 'api_users_get_vaults',
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ['GET']
    )]
    public function index(
        string $id,
        UserRepository $userRepository,
        VaultRepository $vaultRepository
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        if (!$loggedInUser->isAdmin() && $id !== $loggedInUser->getId()) {
            throw $this->createNotFoundException("User with id: $id not found.");
        }

        $user = $userRepository->findByIds(
            [$id],
            [
                "PARTIAL u.{id}",
                "PARTIAL gu.{group, user}",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            groupUserAlias: "gu"
        )[$id] ?? null;

        if (is_null($user)) {
            throw $this->createNotFoundException("User with id: $id not found.");
        }

        $vaults = $vaultRepository->findReadableByGroupIds(
            $user->getGroupIds(),
            [
                "PARTIAL v.{id, name, iconName, allowPasswordsAtRoot, mandatoryPasswordFields, mandatoryFolderFields}",
                "PARTIAL gv.{vault, group, canWrite, partial}",
                "PARTIAL g.{id, name, private}",
            ]
        );

        return $this->json(
            array_values($vaults),
            context: [
                VaultNormalizer::MINIMISED,
                VaultNormalizer::WITH_GROUPS,
                VaultNormalizer::WITH_MANDATORY_FIELDS,
            ]
        );
    }
}
