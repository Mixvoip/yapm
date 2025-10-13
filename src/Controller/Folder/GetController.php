<?php

/**
 * @author bsteffan
 * @since 2025-06-05
 */

namespace App\Controller\Folder;

use App\Entity\User;
use App\Normalizer\FolderNormalizer;
use App\Repository\FolderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetController extends AbstractController
{
    /**
     * Get a Folder by id.
     *
     * @param  string  $id
     * @param  FolderRepository  $folderRepository
     *
     * @return JsonResponse
     */
    #[Route(
        "/folders/{id}",
        name: "api_folders_get",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["GET"]
    )]
    public function index(string $id, FolderRepository $folderRepository): JsonResponse
    {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        $folder = $folderRepository->findByIds(
            [$id],
            [
                "PARTIAL f.{id, name, externalId, createdAt, createdBy, updatedAt, updatedBy}",
                "PARTIAL fg.{folder, group, canWrite, partial}",
                "PARTIAL g.{id, name, private}",
            ],
            groupAlias: "g",
            folderGroupAlias: "fg",
        )[$id] ?? null;

        if (is_null($folder) || !$folder->hasReadPermission($loggedInUser->getGroupIds())) {
            throw $this->createNotFoundException("Folder with id: $id not found.");
        }

        return $this->json($folder, context: [FolderNormalizer::WITH_GROUPS]);
    }
}
