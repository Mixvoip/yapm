<?php

/**
 * @author bsteffan
 * @since 2025-07-15
 */

namespace App\Controller\AuditLog;

use App\Controller\PaginatedResponse;
use App\Controller\QueryParameterResolver\DateTimeResolver;
use App\Domain\AppConstants;
use App\Entity\Enums\AuditAction;
use App\Repository\AuditLogRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GetListController extends AbstractController
{
    /**
     * Get a list of audit logs.
     *
     * @param  AuditLogRepository  $auditLogRepository
     * @param  string  $search
     * @param  int  $page
     * @param  int  $limit
     * @param  string|null  $userId
     * @param  AuditAction|null  $actionType
     * @param  DateTimeImmutable|null  $startDate
     * @param  DateTimeImmutable|null  $endDate
     *
     * @return JsonResponse
     */
    #[Route("/audit-logs", name: "api_audit_logs_list", methods: ["GET"])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        AuditLogRepository $auditLogRepository,
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 50,
        #[MapQueryParameter] ?string $userId = null,
        #[MapQueryParameter] ?AuditAction $actionType = null,
        #[MapQueryParameter(resolver: DateTimeResolver::class)] ?DateTimeImmutable $startDate = null,
        #[MapQueryParameter(resolver: DateTimeResolver::class)] ?DateTimeImmutable $endDate = null
    ): JsonResponse {
        $auditLogs = $auditLogRepository->findForPaginatedList(
            $search,
            $page,
            $limit + 1, // Fetch $limit +1 to check for the next page
            $userId,
            $actionType,
            $startDate,
            $endDate
        );

        $hasNextPage = count($auditLogs) > $limit;

        if ($hasNextPage) {
            array_pop($auditLogs); // Remove the extra item added to check for the next page
        }

        $baseUrl = AppConstants::$apiBaseUri . '/audit-logs';

        $queryParams = array_filter(
            [
                'search' => $search !== '' ? $search : null,
                'limit' => $limit,
                'userId' => $userId,
                'actionType' => $actionType?->value,
                'startDate' => $startDate?->format('Y-m-d H:i:s'),
                'endDate' => $endDate?->format('Y-m-d H:i:s'),
            ],
            fn($value) => !is_null($value)
        );

        $paginatedResponse = PaginatedResponse::create($auditLogs, $page, $hasNextPage, $baseUrl, $queryParams);

        return $this->json($paginatedResponse);
    }
}
