<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Repository;

use App\Entity\User;
use App\Entity\WebAuthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebAuthnCredential>
 *
 * @method WebAuthnCredential|null find($id, $lockMode = null, $lockVersion = null)
 * @method WebAuthnCredential|null findOneBy(array $criteria, array $orderBy = null)
 * @method WebAuthnCredential[]    findAll()
 * @method WebAuthnCredential[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WebAuthnCredentialRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebAuthnCredential::class);
    }

    /**
     * Find all credentials for a user.
     *
     * @param  User  $user
     *
     * @return WebAuthnCredential[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * Find a credential by its WebAuthn credential ID.
     *
     * @param  string  $credentialId  Raw binary credential ID
     *
     * @return WebAuthnCredential|null
     */
    public function findByCredentialId(string $credentialId): ?WebAuthnCredential
    {
        return $this->findOneBy(['credentialId' => $credentialId]);
    }

    /**
     * Delete all credentials for a user.
     *
     * @param  User  $user
     *
     * @return int  Number of deleted credentials
     */
    public function deleteAllForUser(User $user): int
    {
        return $this->createQueryBuilder('w')
                    ->delete()
                    ->where('w.user = :user')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->execute();
    }
}
