<?php

/**
 * @author bsteffan
 * @since 2025-06-18
 */

namespace App\Controller\Folder;

use App\Entity\User;
use App\Normalizer\FolderNormalizer;
use App\Normalizer\PasswordNormalizer;
use App\Repository\FolderRepository;
use App\Repository\PasswordRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetStructureController extends AbstractController
{
    /**
     * Get the folder and password structure for a folder.
     *
     * @param  string  $id
     * @param  FolderRepository  $folderRepository
     * @param  PasswordRepository  $passwordRepository
     *
     * @return JsonResponse
     */
    #[Route(
        "/folders/{id}/structure",
        name: "api_folders_get_structure",
        requirements: ["id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"],
        methods: ["GET"]
    )]
    public function index(
        string $id,
        FolderRepository $folderRepository,
        PasswordRepository $passwordRepository
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();
        $groupIds = $loggedInUser->getGroupIds();
        $folder = $folderRepository->findByIds(
            [$id],
            [
                "PARTIAL f.{id, name, iconName, parent}",
                "PARTIAL fg.{folder, group, canWrite}",
                "PARTIAL g.{id}",
            ],
            groupAlias: "g",
            folderGroupAlias: "fg"
        )[$id] ?? null;

        if (is_null($folder) || !$folder->hasReadPermission($groupIds)) {
            throw $this->createNotFoundException("Folder with id: $id not found.");
        }

        return $this->json(
            $folder->getLazyFolderTree($folderRepository, $passwordRepository, $groupIds),
            context: [
                PasswordNormalizer::MINIMISED,
                FolderNormalizer::MINIMISED,
            ]
        );
    }
}
