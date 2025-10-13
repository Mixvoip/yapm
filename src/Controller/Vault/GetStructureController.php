<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Controller\Vault;

use App\Entity\User;
use App\Normalizer\FolderNormalizer;
use App\Normalizer\PasswordNormalizer;
use App\Repository\FolderRepository;
use App\Repository\PasswordRepository;
use App\Repository\VaultRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetStructureController extends AbstractController
{
    /**
     * Get the folder and password structure for a vault.
     *
     * @param  string  $id
     * @param  VaultRepository  $vaultRepository
     * @param  FolderRepository  $folderRepository
     * @param  PasswordRepository  $passwordRepository
     *
     * @return JsonResponse
     */
    #[Route(
        "/vaults/{id}/structure",
        name: "api_vaults_get_structure",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["GET"]
    )]
    public function index(
        string $id,
        VaultRepository $vaultRepository,
        FolderRepository $folderRepository,
        PasswordRepository $passwordRepository
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();
        $groupIds = $loggedInUser->getGroupIds();
        $vault = $vaultRepository->findByIds(
            [$id],
            [
                "PARTIAL v.{id, name}",
                "PARTIAL gv.{vault, group, canWrite}",
                "PARTIAL g.{id}",
            ],
            groupAlias: "g",
            groupVaultAlias: "gv"
        )[$id] ?? null;

        if (is_null($vault) || !$vault->hasReadPermission($groupIds)) {
            throw $this->createNotFoundException("Vault with id: $id not found.");
        }

        return $this->json(
            $vault->getLazyFolderTree($folderRepository, $passwordRepository, $groupIds),
            context: [
                PasswordNormalizer::MINIMISED,
                FolderNormalizer::MINIMISED,
            ]
        );
    }
}
