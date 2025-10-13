<?php

/**
 * @author bsteffan
 * @since 2025-08-04
 */

namespace App\Controller\Password;

use App\Controller\PaginatedResponse;
use App\Domain\AppConstants;
use App\Entity\User;
use App\Normalizer\PasswordNormalizer;
use App\Repository\PasswordRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    /**
     * Search passwords.
     *
     * @param  PasswordRepository  $passwordRepository
     * @param  string  $search
     * @param  int  $page
     * @param  int  $limit
     * @param  string  $vaultId
     *
     * @return JsonResponse
     */
    #[Route("/passwords/search", name: "api_passwords_search", methods: ["GET"])]
    public function index(
        PasswordRepository $passwordRepository,
        #[MapQueryParameter] string $search,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 50,
        #[MapQueryParameter] string $vaultId = ""
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        if (trim($search) === "") {
            throw new BadRequestHttpException("Search cannot be empty.");
        }

        $passwords = $passwordRepository->searchPasswords(
            $search,
            $vaultId,
            $loggedInUser->getGroupIds(),
            $page,
            $limit + 1 // Fetch limit +1 to check for the next page
        );

        $hasNextPage = count($passwords) > $limit;
        if ($hasNextPage) {
            array_pop($passwords); // Remove the extra item added to check for the next page
        }

        $baseUrl = AppConstants::$apiBaseUri . '/passwords/search';
        $queryParams = [
            'search' => $search,
            'limit' => $limit,
        ];

        if (!empty($vaultId)) {
            $queryParams['vaultId'] = $vaultId;
        }

        $paginatedResponse = PaginatedResponse::create($passwords, $page, $hasNextPage, $baseUrl, $queryParams);
        return $this->json($paginatedResponse, context: [PasswordNormalizer::FOR_SEARCH]);
    }
}
