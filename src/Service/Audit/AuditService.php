<?php

/**
 * @author bsteffan
 * @since 2025-07-14
 */

namespace App\Service\Audit;

use App\Entity\AuditLog;
use App\Entity\Enums\AuditAction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class AuditService
{
    /**
     * @param  EntityManagerInterface  $entityManager
     * @param  Security  $security
     * @param  RequestStack  $requestStack
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Log an audit event.
     *
     * @param  AuditAction  $auditAction
     * @param  AuditableEntityInterface  $auditableEntity
     * @param  array|null  $oldValues
     * @param  array|null  $newValues
     *
     * @return void
     */
    public function log(
        AuditAction $auditAction,
        AuditableEntityInterface $auditableEntity,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $userId = null;
        $userEmail = null;

        if (!is_null($currentUser)) {
            $userId = $currentUser->getId();
            $userEmail = $currentUser->getEmail();
        }

        $ipAddress = null;
        $userAgent = null;

        $request = $this->requestStack->getCurrentRequest();
        if (!is_null($request)) {
            $ipAddress = static::getClientIp($request);
            $userAgent = $request->headers->get('User-Agent');
        }

        $auditLog = new AuditLog()->setUserId($userId)
                                  ->setEntityId($auditableEntity->getId())
                                  ->setEntityType($auditableEntity::class)
                                  ->setOldValues($oldValues)
                                  ->setNewValues($newValues)
                                  ->setMetadata($auditableEntity)
                                  ->setIpAddress($ipAddress)
                                  ->setUserAgent($userAgent)
                                  ->setActionType($auditAction)
                                  ->setUserEmail($userEmail);

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }

    /**
     * @param  string  $email
     * @param  string  $response
     *
     * @return void
     */
    public function logFailedLoginAttempt(string $email, string $response): void
    {
        $ipAddress = null;
        $userAgent = null;

        $request = $this->requestStack->getCurrentRequest();
        if (!is_null($request)) {
            $ipAddress = static::getClientIp($request);
            $userAgent = $request->headers->get('User-Agent');
        }

        $auditLog = new AuditLog()->setUserId(null)
                                  ->setEntityId(null)
                                  ->setEntityType(User::class)
                                  ->setOldValues(null)
                                  ->setNewValues(null)
                                  ->setMetadata($response)
                                  ->setIpAddress($ipAddress)
                                  ->setUserAgent($userAgent)
                                  ->setActionType(AuditAction::FailedLogin)
                                  ->setUserEmail($email);

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }

    /**
     * Get the real client IP address.
     *
     * @param  Request  $request
     *
     * @return string|null
     */
    public static function getClientIp(Request $request): ?string
    {
        $realIp = $request->server->get('HTTP_X_REAL_IP');
        if (!empty($realIp) && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }

        return $request->getClientIp();
    }
}
