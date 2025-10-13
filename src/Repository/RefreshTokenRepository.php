<?php

/**
 * @author bsteffan
 * @since 2025-04-25
 * @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository
 */

namespace App\Repository;

use App\Entity\RefreshToken;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;

class RefreshTokenRepository extends ServiceEntityRepository implements RefreshTokenRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * Save a refresh token
     */
    public function save(RefreshToken $token, bool $flush = false): void
    {
        $this->getEntityManager()->persist($token);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a refresh token
     */
    public function remove(RefreshToken $token, bool $flush = false): void
    {
        $this->getEntityManager()->remove($token);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Invalidate all refresh tokens for a user.
     *
     * @param  string  $email
     *
     * @return void
     */
    public function invalidateAllForUser(string $email): void
    {
        $this->createQueryBuilder("rt")
             ->update()
             ->set("rt.valid", ":valid")
             ->where("rt.username = :user")
             ->setParameter("valid", null)
             ->setParameter("user", $email)
             ->getQuery()
             ->execute();
    }

    /**
     * @inheritDoc
     */
    public function findInvalid($datetime = null): array
    {
        return $this->createQueryBuilder("rt")
                    ->where("rt.valid < :valid")
                    ->andWhere("rt.valid IS NOT NULL")
                    ->setParameter("valid", $datetime ?? new DateTime())
                    ->getQuery()
                    ->getResult();
    }
}
