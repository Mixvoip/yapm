<?php

/**
 * @author bsteffan
 * @since 2025-07-23
 */

namespace App\Controller;

use App\Entity\PermissionAwareEntityInterface;
use App\Entity\User;
use App\Normalizer\AuditLogNormalizer;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractChangelogController extends AbstractController
{
    /**
     * @param  AuditLogRepository  $auditLogRepository
     */
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository
    ) {
    }

    /**
     * Get JsonResponse.
     *
     * @param  PermissionAwareEntityInterface|null  $entity
     * @param  string|null  $logId
     * @param  bool  $all
     *
     * @return JsonResponse
     * @throws NotFoundHttpException
     */
    protected function getJsonResponse(?PermissionAwareEntityInterface $entity, ?string $logId, bool $all): JsonResponse
    {
        /** @var User $loggedInUser */
        $loggedInUser = $this->getUser();

        if (is_null($entity) || !$entity->hasReadPermission($loggedInUser->getGroupIds())) {
            throw new EntityNotFoundException();
        }

        if (!empty($logId)) {
            $auditLog = $this->auditLogRepository->findByIds(
                [$logId],
                ["PARTIAL a.{id, actionType, userEmail, oldValues, newValues, createdAt}"]
            )[$logId] ?? null;

            if (is_null($auditLog)) {
                throw $this->createNotFoundException("Changelog with id: $logId not found.");
            }
            return $this->json($auditLog, context: [AuditLogNormalizer::CHANGELOG]);
        }

        // Get audit logs
        $auditLogs = $this->auditLogRepository->findForChangelog($entity, $all);
        return $this->json($auditLogs, context: [AuditLogNormalizer::SUMMARY]);
    }
}
