<?php

/**
 * @author bsteffan
 * @since 2026-02-16
 */

namespace App\Repository;

use App\Entity\TimeBasedShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockAwareTrait;

/**
 * @extends ServiceEntityRepository<TimeBasedShare>
 *
 * @method TimeBasedShare|null find($id, $lockMode = null, $lockVersion = null)
 * @method TimeBasedShare|null findOneBy(array $criteria, array $orderBy = null)
 * @method TimeBasedShare[]    findAll()
 * @method TimeBasedShare[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TimeBasedShareRepository extends ServiceEntityRepository
{
    use ClockAwareTrait;

    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeBasedShare::class);
    }

    /**
     * Find a non-expired share by ID with joined users.
     *
     * @param  string  $id
     *
     * @return TimeBasedShare|null
     */
    public function findActiveById(string $id): ?TimeBasedShare
    {
        return $this->createQueryBuilder('tbs')
                    ->select('tbs', 'PARTIAL sb.{id, email, username}', 'PARTIAL sw.{id, email, username}')
                    ->innerJoin('tbs.sharedBy', 'sb')
                    ->innerJoin('tbs.sharedWith', 'sw')
                    ->where('tbs.id = :id')
                    ->andWhere('tbs.expiresAt > :now')
                    ->setParameter('id', $id)
                    ->setParameter('now', $this->now())
                    ->getQuery()
                    ->getOneOrNullResult();
    }

    /**
     * Find an active share for a specific password and recipient combination.
     *
     * @param  string  $sourcePasswordId
     * @param  string  $recipientId
     *
     * @return TimeBasedShare|null
     */
    public function findActiveByPasswordAndRecipient(string $sourcePasswordId, string $recipientId): ?TimeBasedShare
    {
        return $this->createQueryBuilder('tbs')
                    ->select('PARTIAL tbs.{id}')
                    ->where('tbs.sourcePasswordId = :passwordId')
                    ->andWhere('tbs.sharedWith = :recipientId')
                    ->andWhere('tbs.expiresAt > :now')
                    ->setParameter('passwordId', $sourcePasswordId)
                    ->setParameter('recipientId', $recipientId)
                    ->setParameter('now', $this->now())
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
    }

    /**
     * Expire all active shares for a given source password.
     *
     * @param  string  $sourcePasswordId
     *
     * @return int Number of shares expired
     */
    public function expireBySourcePasswordId(string $sourcePasswordId): int
    {
        $shares = $this->createQueryBuilder('tbs')
                       ->select('PARTIAL tbs.{id, passwordTitle, expiresAt}', 'PARTIAL sw.{id, username}')
                       ->innerJoin('tbs.sharedWith', 'sw')
                       ->where('tbs.sourcePasswordId = :passwordId')
                       ->andWhere('tbs.expiresAt > :now')
                       ->setParameter('passwordId', $sourcePasswordId)
                       ->setParameter('now', $this->now())
                       ->getQuery()
                       ->getResult();

        $now = $this->now();
        foreach ($shares as $share) {
            $share->setExpiresAt($now);
        }

        return count($shares);
    }

    /**
     * Find all non-expired shares where the given user is either the creator or the recipient.
     *
     * @param  string  $userId
     *
     * @return TimeBasedShare[]
     */
    public function findActiveForUser(string $userId): array
    {
        return $this->createQueryBuilder('tbs')
                    ->select('tbs', 'PARTIAL sb.{id, email, username}', 'PARTIAL sw.{id, email, username}')
                    ->innerJoin('tbs.sharedBy', 'sb')
                    ->innerJoin('tbs.sharedWith', 'sw')
                    ->where('tbs.sharedBy = :userId OR tbs.sharedWith = :userId')
                    ->andWhere('tbs.expiresAt > :now')
                    ->setParameter('userId', $userId)
                    ->setParameter('now', $this->now())
                    ->orderBy('tbs.createdAt', 'DESC')
                    ->getQuery()
                    ->getResult();
    }
}
