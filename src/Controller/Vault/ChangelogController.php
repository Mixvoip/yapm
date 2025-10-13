<?php

/**
 * @author bsteffan
 * @since 2025-07-23
 */

namespace App\Controller\Vault;

use App\Controller\AbstractChangelogController;
use App\Repository\VaultRepository;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class ChangelogController extends AbstractChangelogController
{
    /**
     * Get the changelog for a vault.
     *
     * @param  string  $id
     * @param  VaultRepository  $vaultRepository
     * @param  string|null  $logId
     * @param  bool  $all
     *
     * @return JsonResponse
     */
    #[Route(
        '/vaults/{id}/changelogs/{logId}',
        name: 'api_vaults_changelogs_get',
        requirements: [
            "id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
            "logId" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|^$",
        ],
        methods: ['GET']
    )]
    public function index(
        string $id,
        VaultRepository $vaultRepository,
        ?string $logId = null,
        #[MapQueryParameter] bool $all = false
    ): JsonResponse {
        $vault = $vaultRepository->findByIds(
            [$id],
            [
                "PARTIAL v.{id}",
                "PARTIAL gv.{vault, group, canWrite}",
                "PARTIAL g.{id}",
            ],
            groupAlias: "g",
            groupVaultAlias: "gv"
        )[$id] ?? null;

        try {
            return $this->getJsonResponse($vault, $logId, $all);
        } catch (EntityNotFoundException) {
            throw $this->createNotFoundException("Vault with id: $id not found.");
        }
    }
}
