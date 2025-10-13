<?php

/**
 * @author bsteffan
 * @since 2025-07-22
 */

namespace App\Controller\Password;

use App\Controller\AbstractChangelogController;
use App\Repository\PasswordRepository;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class ChangelogController extends AbstractChangelogController
{
    /**
     * Get the changelog for a password.
     *
     * @param  string  $id
     * @param  PasswordRepository  $passwordRepository
     * @param  string|null  $logId
     * @param  bool  $all
     *
     * @return JsonResponse
     */
    #[Route(
        '/passwords/{id}/changelogs/{logId}',
        name: 'api_passwords_changelogs_get',
        requirements: [
            "id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
            "logId" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|^$",
        ],
        methods: ['GET']
    )]
    public function index(
        string $id,
        PasswordRepository $passwordRepository,
        ?string $logId = null,
        #[MapQueryParameter] bool $all = false
    ): JsonResponse {
        $password = $passwordRepository->findByIds(
            [$id],
            [
                "PARTIAL p.{id}",
                "PARTIAL gp.{group, password, canWrite}",
                "PARTIAL g.{id}",
            ],
            groupAlias: "g",
            groupPasswordAlias: "gp"
        )[$id] ?? null;

        try {
            return $this->getJsonResponse($password, $logId, $all);
        } catch (EntityNotFoundException) {
            throw $this->createNotFoundException("Password with id: $id not found.");
        }
    }
}
