<?php

/**
 * @author bsteffan
 * @since 2025-09-16
 * @noinspection PhpMultipleClassDeclarationsInspection DateMalformedStringException
 */

namespace App\Controller\Vault;

use App\Controller\AbstractJsonPatchController;
use App\Controller\Vault\Dto\PatchDto;
use App\Entity\User;
use App\Entity\Vault;
use App\Normalizer\VaultNormalizer;
use DateMalformedStringException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class PatchController extends AbstractJsonPatchController
{
    /**
     * @param  string  $id
     * @param  PatchDto  $dto
     * @param  Request  $request
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     * @throws DateMalformedStringException
     */
    #[Route(
        "/vaults/{id}",
        name: "api_vaults_id_patch",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["PATCH"]
    )]
    public function index(
        string $id,
        #[MapRequestPayload] PatchDto $dto,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $vaultRepository = $entityManager->getRepository(Vault::class);
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

        if (!$vault->hasWritePermission($loggedInUser->getGroupIds())) {
            throw $this->createAccessDeniedException("You don't have permission to update this vault.");
        }

        $this->initializePatchData($request);
        $this->addDefaultPatchData($vault, $dto);

        if ($this->patch()) {
            $vault->setUpdatedBy($loggedInUser->getUserIdentifier());
            $entityManager->flush();
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
