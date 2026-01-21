<?php

/**
 * @author bsteffan
 * @since 2025-06-11
 * @noinspection PhpMultipleClassDeclarationsInspection DateMalformedStringException
 */

namespace App\Controller\Folder;

use App\Controller\AbstractJsonPatchController;
use App\Controller\Folder\Dto\PatchDto;
use App\Controller\NulledValueGetterTrait;
use App\Entity\Enums\FolderField;
use App\Entity\Folder;
use App\Entity\User;
use App\Exception\InvalidRequestBodyException;
use App\Normalizer\FolderNormalizer;
use App\Repository\FolderRepository;
use DateMalformedStringException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolation;

class PatchController extends AbstractJsonPatchController
{
    use NulledValueGetterTrait;

    /**
     * Update a folder.
     *
     * @param  string  $id
     * @param  PatchDto  $patchDto
     * @param  Request  $request
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
     * @throws DateMalformedStringException
     * @throws InvalidRequestBodyException
     */
    #[Route(
        "/folders/{id}",
        name: "api_folders_id_patch",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["PATCH"]
    )]
    public function index(
        string $id,
        #[MapRequestPayload] PatchDto $patchDto,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        /** @var FolderRepository $folderRepository */
        $folderRepository = $entityManager->getRepository(Folder::class);
        $folder = $folderRepository->findByIds(
            [$id],
            [
                "PARTIAL f.{id, name, externalId, iconName, description, createdAt, createdBy, updatedAt, updatedBy}",
                "PARTIAL fg.{folder, group, canWrite, partial}",
                "PARTIAL g.{id, name, private}",
                "PARTIAL v.{id, name, mandatoryFolderFields}",
            ],
            groupAlias: "g",
            folderGroupAlias: "fg",
            vaultAlias: "v"
        )[$id] ?? null;

        if (is_null($folder) || !$folder->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Folder with id: $id not found.");
        }

        if (!$folder->hasWritePermission($loggedInUser->getGroupIds())) {
            throw $this->createAccessDeniedException("You don't have permission to update this folder.");
        }

        $this->initializePatchData($request);
        $this->addDefaultPatchData($folder, $patchDto);
        $this->addExternalIdPatchData($folder, $patchDto->externalId);

        if ($this->patch()) {
            $folder->setUpdatedBy($loggedInUser->getUserIdentifier());
            $entityManager->flush();
        }

        return $this->json($folder, context: [FolderNormalizer::WITH_GROUPS]);
    }

    /**
     * Add externalId to patch data if requested and valid.
     *
     * @param  Folder  $folder
     * @param  string|null  $externalId
     *
     * @return void
     */
    private function addExternalIdPatchData(Folder $folder, ?string $externalId): void
    {
        if (!$this->isPatchRequested("externalId")) {
            return;
        }

        $externalId = self::getTrimmedOrNull($externalId);
        $mandatoryFolderFields = $folder->getVault()->getMandatoryFolderFields() ?? [];

        if (is_null($externalId) && in_array(FolderField::ExternalId, $mandatoryFolderFields)) {
            $violation = new ConstraintViolation(
                "ExternalId is mandatory for this vault.",
                null,
                [],
                $externalId,
                "externalId",
                $externalId
            );
            $this->addViolation($violation);
            return;
        }

        $this->addPatchData(
            "externalId",
            $folder->getExternalId(),
            $externalId,
            [$folder, "setExternalId"]
        );
    }
}
