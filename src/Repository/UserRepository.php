<?php

/** @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository */

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @param  User  $entity
     * @param  bool  $flush
     *
     * @return void
     */
    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param  User  $entity
     * @param  bool  $flush
     *
     * @return void
     */
    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user, true);
    }

    /**
     * Find Users by their IDs, returns only provided fields.
     *
     * @param  string[]  $ids
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string|null  $groupAlias
     * @param  string|null  $groupUserAlias
     *
     * @return User[]
     */
    public function findByIds(
        array $ids,
        array $fields,
        string $alias = "u",
        ?string $groupAlias = null,
        ?string $groupUserAlias = null
    ): array {
        $qb = $this->createQueryBuilder($alias, "$alias.id")
                   ->select($fields)
                   ->where("$alias.id IN (:ids)")
                   ->setParameter("ids", $ids);

        if (!is_null($groupAlias) && !is_null($groupUserAlias)) {
            $qb->leftJoin("$alias.groupUsers", $groupUserAlias)
               ->leftJoin("$groupUserAlias.group", $groupAlias);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find Users by their usernames or email, returns only provided fields.
     *
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string|null  $groupAlias
     * @param  string|null  $groupUserAlias
     * @param  string|null  $search
     * @param  bool  $activeOnly
     *
     * @return User[]
     */
    public function searchUsers(
        array $fields,
        string $alias = "u",
        ?string $groupAlias = null,
        ?string $groupUserAlias = null,
        ?string $search = null,
        bool $activeOnly = false
    ): array {
        $qb = $this->createQueryBuilder($alias)
                   ->select($fields)
                   ->orderBy("$alias.username", 'ASC');

        if (!is_null($groupAlias) && !is_null($groupUserAlias)) {
            $qb->leftJoin("$alias.groupUsers", $groupUserAlias)
               ->leftJoin("$groupUserAlias.group", $groupAlias);
        }

        if (!empty($search)) {
            $orX = $qb->expr()->orX(
                $qb->expr()->like("$alias.username", ":search"),
                $qb->expr()->like("$alias.email", ":search")
            );

            $qb->andWhere($orX)
               ->setParameter('search', "$search%");
        }

        if ($activeOnly) {
            $qb->andWhere("$alias.active = :active")
               ->andWhere("$alias.verified = :verified")
               ->setParameter('active', true)
               ->setParameter('verified', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find a user by their verification token.
     *
     * @param  string  $token
     *
     * @return User|null
     * @throws NonUniqueResultException
     */
    public function findForVerification(string $token): ?User
    {
        return $this->createQueryBuilder("u")
                    ->select(
                        "PARTIAL u.{id, email, username, password, publicKey, encryptedPrivateKey, keySalt, privateKeyNonce, verificationToken, verified, updatedAt, updatedBy}"
                    )
                    ->where("u.verificationToken = :token")
                    ->andWhere("u.verified = :verified")
                    ->setParameter("token", $token)
                    ->setParameter("verified", false)
                    ->getQuery()
                    ->getOneOrNullResult();
    }

    /**
     * Return the number of admin users.
     *
     * @return int
     */
    public function getAdminCount(): int
    {
        return $this->createQueryBuilder("u")
                    ->select("COUNT(u.id)")
                    ->where("u.admin = :admin")
                    ->andWhere("u.active = :active")
                    ->setParameter("admin", true)
                    ->setParameter("active", true)
                    ->getQuery()
                    ->getSingleScalarResult();
    }
}
