<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Controller\Password;

use App\Entity\User;
use App\Normalizer\PasswordNormalizer;
use App\Repository\PasswordRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetController extends AbstractController
{
    /**
     * Get a password by id.
     *
     * @param  string  $id
     * @param  PasswordRepository  $passwordRepository
     *
     * @return JsonResponse
     */
    #[Route(
        "/passwords/{id}",
        name: "api_passwords_get",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["GET"]
    )]
    public function index(string $id, PasswordRepository $passwordRepository): JsonResponse
    {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $password = $passwordRepository->findByIds(
            [$id],
            [
                "PARTIAL p.{id, title, description, location, externalId, target, createdAt, createdBy, updatedAt, updatedBy}",
                "PARTIAL f.{id, name}",
                "PARTIAL gp.{group, password, canWrite}",
                "PARTIAL g.{id, name, private}",
            ],
            folderAlias: "f",
            groupAlias: "g",
            groupPasswordAlias: "gp"
        )[$id] ?? null;

        if (is_null($password) || !$password->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Password with id: $id not found.");
        }

        return $this->json(
            $password,
            context: [
                PasswordNormalizer::WITH_GROUPS,
                PasswordNormalizer::WITH_FOLDER,
            ]
        );
    }
}
