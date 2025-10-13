<?php

/**
 * @author bsteffan
 * @since 2025-09-15
 */

namespace App\Controller\Vault;

use App\Entity\User;
use App\Normalizer\VaultNormalizer;
use App\Repository\VaultRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetController extends AbstractController
{
    /**
     * Get a vault by id.
     *
     * @param  string  $id
     * @param  VaultRepository  $vaultRepository
     *
     * @return JsonResponse
     */
    #[Route(
        "/vaults/{id}",
        name: "api_vaults_get",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["GET"]
    )]
    public function index(string $id, VaultRepository $vaultRepository): JsonResponse
    {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $vault = $vaultRepository->findByIds(
            [$id],
            [
                "PARTIAL v.{id, name, mandatoryPasswordFields, mandatoryFolderFields, iconName, allowPasswordsAtRoot, createdAt, createdBy, updatedAt, updatedBy}",
                "PARTIAL gv.{vault, group, canWrite, partial}",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            groupVaultAlias: "gv"
        )[$id] ?? null;

        if (is_null($vault) || !$vault->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Vault with id: $id not found.");
        }

        return $this->json(
            $vault,
            context: [
                VaultNormalizer::WITH_GROUPS,
                VaultNormalizer::WITH_MANDATORY_FIELDS,
            ]
        );
    }
}
