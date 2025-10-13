<?php

/**
 * @author bsteffan
 * @since 2025-07-14
 * @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository
 */

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\Enums\AuditAction;
use App\Entity\PermissionAwareEntityInterface;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 *
 * @method AuditLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuditLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method AuditLog[]    findAll()
 * @method AuditLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AuditLogRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Find all audit logs for a paginated list.
     *
     * @param  string  $search
     * @param  int  $page
     * @param  int  $limit
     * @param  string|null  $userId
     * @param  AuditAction|null  $actionType
     * @param  DateTimeImmutable|null  $startDate
     * @param  DateTimeImmutable|null  $endDate
     *
     * @return AuditLog[]
     */
    public function findForPaginatedList(
        string $search = "",
        int $page = 1,
        int $limit = 50,
        ?string $userId = null,
        ?AuditAction $actionType = null,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('a')
                   ->orderBy('a.createdAt', 'DESC')
                   ->setFirstResult(($page - 1) * $limit)
                   ->setMaxResults($limit);

        if ($search !== "") {
            $orX = $qb->expr()->orX(
                "a.id LIKE :exactSearch",
                "a.entityType LIKE :vagueSearch",
                "a.metadata LIKE :vagueSearch",
                "a.entityId LIKE :exactSearch",
                "a.entityId LIKE :entitySearchStart",
                "a.entityId LIKE :entitySearchEnd",
                "a.ipAddress LIKE :exactSearch",
                "a.userEmail LIKE :search",
            );

            $qb->andWhere($orX)
               ->setParameter('exactSearch', $search)
               ->setParameter('vagueSearch', "%$search%")
               ->setParameter('entitySearchEnd', "%_" . $search)
               ->setParameter('entitySearchStart', $search . "_%")
               ->setParameter('search', "$search%");
        }

        if (!is_null($userId)) {
            $qb->andWhere('a.userId = :userId')
               ->setParameter('userId', $userId);
        }

        if (!is_null($actionType)) {
            $qb->andWhere('a.actionType = :actionType')
               ->setParameter('actionType', $actionType);
        }

        if (!is_null($startDate)) {
            $qb->andWhere('a.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if (!is_null($endDate)) {
            $qb->andWhere('a.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()
                  ->getResult();
    }

    /**
     * Find the last 3 or all audit logs for a given entity id.
     *
     * @param  PermissionAwareEntityInterface  $entity
     * @param  bool  $all
     *
     * @return AuditLog[]
     */
    public function findForChangelog(PermissionAwareEntityInterface $entity, bool $all): array
    {
        $qb = $this->createQueryBuilder('a')
                   ->select("PARTIAL a.{id, actionType, userEmail, oldValues, newValues, createdAt}")
                   ->where('a.entityType = :entityClass')
                   ->andWhere('a.entityId = :id')
                   ->andWhere('a.actionType = :actionType')
                   ->setParameter('entityClass', $entity::class)
                   ->setParameter('id', $entity->getId())
                   ->setParameter("actionType", AuditAction::Updated->value)
                   ->orderBy('a.createdAt', 'DESC');

        if (!$all) {
            $qb->setMaxResults(3);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find AuditLogs by ids, returns only provided fields.
     *
     * @param  string[]  $ids
     * @param  string[]  $fields
     * @param  string  $alias
     *
     * @return AuditLog[]
     */
    public function findByIds(array $ids, array $fields, string $alias = "a"): array
    {
        return $this->createQueryBuilder($alias, "$alias.id")
                    ->select($fields)
                    ->where("$alias.id IN (:ids)")
                    ->setParameter('ids', $ids)
                    ->getQuery()
                    ->getResult();
    }
}
