<?php

/**
 * @author bsteffan
 * @since 2025-10-23
 */

namespace App\Controller\Folder;

use App\Controller\PaginatedResponse;
use App\Domain\AppConstants;
use App\Entity\User;
use App\Normalizer\FolderNormalizer;
use App\Repository\FolderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    /**
     * Search folders.
     *
     * @param  FolderRepository  $folderRepository
     * @param  string  $search
     * @param  int  $page
     * @param  int  $limit
     * @param  string  $vaultId
     * @param  bool  $writableOnly
     *
     * @return JsonResponse
     */
    #[Route("/folders/search", name: "api_folders_search", methods: ["GET"])]
    public function index(
        FolderRepository $folderRepository,
        #[MapQueryParameter] string $search,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 50,
        #[MapQueryParameter] string $vaultId = "",
        #[MapQueryParameter] bool $writableOnly = false
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        if (trim($search) === "") {
            throw new BadRequestHttpException("Search cannot be empty.");
        }

        $folders = $folderRepository->searchFolders(
            $search,
            $vaultId,
            $loggedInUser->getGroupIds(),
            ($page - 1) * $limit,
            $limit + 1,
            $writableOnly
        );

        $hasNextPage = count($folders) > $limit;
        if ($hasNextPage) {
            array_pop($folders);
        }

        $baseUrl = AppConstants::$apiBaseUri . "/folders/search";
        $queryParams = [
            "search" => $search,
            "limit" => $limit,
        ];

        if (!empty($vaultId)) {
            $queryParams["vaultId"] = $vaultId;
        }

        $paginatedResponse = PaginatedResponse::create($folders, $page, $hasNextPage, $baseUrl, $queryParams);
        return $this->json($paginatedResponse, context: [FolderNormalizer::FOR_SEARCH]);
    }
}
