<?php

/**
 * @author bsteffan
 * @since 2025-07-23
 */

namespace App\Controller\Folder;

use App\Controller\AbstractChangelogController;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class ChangelogController extends AbstractChangelogController
{
    /**
     * Get the changelog for a folder.
     *
     * @param  string  $id
     * @param  FolderRepository  $folderRepository
     * @param  string|null  $logId
     * @param  bool  $all
     *
     * @return JsonResponse
     */
    #[Route(
        '/folders/{id}/changelogs/{logId}',
        name: 'api_folders_changelogs_get',
        requirements: [
            "id" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
            "logId" => "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|^$",
        ],
        methods: ['GET']
    )]
    public function index(
        string $id,
        FolderRepository $folderRepository,
        ?string $logId = null,
        #[MapQueryParameter] bool $all = false
    ): JsonResponse {
        $folder = $folderRepository->findByIds(
            [$id],
            [
                "PARTIAL f.{id}",
                "PARTIAL fg.{folder, group, canWrite}",
                "PARTIAL g.{id}",
            ],
            groupAlias: "g",
            folderGroupAlias: "fg"
        )[$id] ?? null;

        try {
            return $this->getJsonResponse($folder, $logId, $all);
        } catch (EntityNotFoundException) {
            throw $this->createNotFoundException("Folder with id: $id not found.");
        }
    }
}
