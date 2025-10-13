<?php

/** @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository */

namespace App\Repository;

use App\Entity\Enums\ShareProcess\Status;
use App\Entity\Enums\ShareProcess\TargetType;
use App\Entity\ShareProcess;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShareProcess>
 *
 * @method ShareProcess|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShareProcess|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShareProcess[]    findAll()
 * @method ShareProcess[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShareProcessRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShareProcess::class);
    }

    /**
     * Find share processes for a paginated list.
     *
     * @param  string  $search
     * @param  int  $page
     * @param  int  $limit
     * @param  string|null  $userEmail
     * @param  TargetType|null  $targetType
     * @param  Status|null  $status
     * @param  DateTimeImmutable|null  $startDate
     * @param  DateTimeImmutable|null  $endDate
     *
     * @return ShareProcess[]
     */
    public function findForPaginatedList(
        string $search = "",
        int $page = 1,
        int $limit = 50,
        ?string $userEmail = null,
        ?TargetType $targetType = null,
        ?Status $status = null,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('s')
                   ->orderBy('s.createdAt', 'DESC')
                   ->setFirstResult(($page - 1) * $limit)
                   ->setMaxResults($limit);

        if ($search !== "") {
            $orX = $qb->expr()->orX(
                "s.id LIKE :exactSearch",
                "s.scopeId LIKE :exactSearch",
                "s.metadata LIKE :vagueSearch",
                "s.message LIKE :vagueSearch",
            );

            $qb->andWhere($orX)
               ->setParameter('exactSearch', "$search")
               ->setParameter('vagueSearch', "%$search%");
        }

        if (!is_null($userEmail)) {
            $qb->andWhere('s.createdBy = :userEmail')
               ->setParameter('userEmail', $userEmail);
        }

        if (!is_null($targetType)) {
            $qb->andWhere('s.targetType = :targetType')
               ->setParameter('targetType', $targetType);
        }

        if (!is_null($status)) {
            $qb->andWhere('s.status = :status')
               ->setParameter('status', $status);
        }

        if (!is_null($startDate)) {
            $qb->andWhere('s.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if (!is_null($endDate)) {
            $qb->andWhere('s.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }
}
