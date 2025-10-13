<?php

/**
 * @author bsteffan
 * @since 2025-06-11
 */

namespace App\Controller\Folder;

use App\Controller\Folder\Dto\PatchDto;
use App\Entity\Enums\FolderField;
use App\Entity\Folder;
use App\Entity\User;
use App\Normalizer\FolderNormalizer;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PatchController extends AbstractController
{
    /**
     * Update a folder.
     *
     * @param  string  $id
     * @param  PatchDto  $patchDto
     * @param  EntityManagerInterface  $entityManager
     *
     * @return JsonResponse
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
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        /** @var FolderRepository $folderRepository */
        $folderRepository = $entityManager->getRepository(Folder::class);
        $folder = $folderRepository->findByIds(
            [$id],
            [
                "PARTIAL f.{id, name, externalId, createdAt, createdBy, updatedAt, updatedBy}",
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

        $updated = false;
        if (!is_null($patchDto->getName()) && $patchDto->getName() !== $folder->getName()) {
            $folder->setName($patchDto->getName());
            $updated = true;
        }

        if ($patchDto->getExternalId() !== false && $patchDto->getExternalId() !== $folder->getExternalId()) {
            $mandatoryFolderFields = $folder->getVault()->getMandatoryFolderFields() ?? [];
            if (is_null($patchDto->getExternalId()) && in_array(FolderField::ExternalId, $mandatoryFolderFields)) {
                throw new BadRequestHttpException("ExternalId is mandatory for this vault.");
            }

            $folder->setExternalId($patchDto->getExternalId());
            $updated = true;
        }

        if ($updated) {
            $folder->setUpdatedBy($loggedInUser->getUserIdentifier());
            $entityManager->persist($folder);
            $entityManager->flush();
        }

        return $this->json($folder, context: [FolderNormalizer::WITH_GROUPS]);
    }
}
