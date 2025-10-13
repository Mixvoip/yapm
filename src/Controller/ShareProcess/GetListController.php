<?php

/**
 * @author bsteffan
 * @since 2025-09-11
 */

namespace App\Controller\ShareProcess;

use App\Controller\PaginatedResponse;
use App\Controller\QueryParameterResolver\DateTimeResolver;
use App\Domain\AppConstants;
use App\Entity\Enums\ShareProcess\Status;
use App\Entity\Enums\ShareProcess\TargetType;
use App\Entity\User;
use App\Repository\ShareProcessRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class GetListController extends AbstractController
{
    /**
     * Get a list of share processes.
     *
     * @param  ShareProcessRepository  $shareProcessRepository
     * @param  string  $search
     * @param  int  $page
     * @param  int  $limit
     * @param  string|null  $userEmail
     * @param  TargetType|null  $targetType
     * @param  Status|null  $status
     * @param  DateTimeImmutable|null  $startDate
     * @param  DateTimeImmutable|null  $endDate
     *
     * @return JsonResponse
     */
    #[Route("/share-processes", name: "share_process_list", methods: ["GET"])]
    public function index(
        ShareProcessRepository $shareProcessRepository,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 50,
        #[MapQueryParameter] ?string $userEmail = null,
        #[MapQueryParameter] ?TargetType $targetType = null,
        #[MapQueryParameter] ?Status $status = null,
        #[MapQueryParameter(resolver: DateTimeResolver::class)] ?DateTimeImmutable $startDate = null,
        #[MapQueryParameter(resolver: DateTimeResolver::class)] ?DateTimeImmutable $endDate = null
    ): JsonResponse {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        if (!$loggedInUser->isAdmin() && $userEmail !== $loggedInUser->getUserIdentifier()) {
            throw $this->createAccessDeniedException("Non-admin users can only view their own share processes.");
        }

        $shareProcesses = $shareProcessRepository->findForPaginatedList(
            $search,
            $page,
            $limit + 1,
            $userEmail,
            $targetType,
            $status,
            $startDate,
            $endDate
        );

        $hasNextPage = count($shareProcesses) > $limit;
        if ($hasNextPage) {
            array_pop($shareProcesses);
        }

        $baseUrl = AppConstants::$apiBaseUri . '/share-process';
        $queryParams = array_filter(
            [
                'search' => $search !== '' ? $search : null,
                'limit' => $limit,
                'userEmail' => $userEmail,
                'targetType' => $targetType?->value,
                'status' => $status?->value,
                'startDate' => $startDate?->format('Y-m-d H:i:s'),
                'endDate' => $endDate?->format('Y-m-d H:i:s'),
            ],
            fn($value) => !is_null($value)
        );

        $paginatedResponse = PaginatedResponse::create($shareProcesses, $page, $hasNextPage, $baseUrl, $queryParams);
        return $this->json($paginatedResponse);
    }
}
