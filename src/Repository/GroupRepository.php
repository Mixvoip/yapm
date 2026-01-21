<?php

/** @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository */

namespace App\Repository;

use App\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 *
 * @method Group|null find($id, $lockMode = null, $lockVersion = null)
 * @method Group|null findOneBy(array $criteria, array $orderBy = null)
 * @method Group[]    findAll()
 * @method Group[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    /**
     * @param  Group  $entity
     * @param  bool  $flush
     *
     * @return void
     */
    public function save(Group $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param  Group  $entity
     * @param  bool  $flush
     *
     * @return void
     */
    public function remove(Group $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find Groups by their IDs, returns only provided fields.
     *
     * @param  string[]  $ids
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string|null  $userAlias
     * @param  string|null  $groupUserAlias
     *
     * @return Group[]
     */
    public function findByIds(
        array $ids,
        array $fields,
        string $alias = "g",
        ?string $userAlias = null,
        ?string $groupUserAlias = null
    ): array {
        $qb = $this->createQueryBuilder($alias, "$alias.id")
                   ->select($fields)
                   ->where("$alias.id IN (:ids)")
                   ->andWhere("$alias.private = false")
                   ->setParameter("ids", $ids);

        if (!is_null($userAlias) && !is_null($groupUserAlias)) {
            $qb->leftJoin("$alias.groupUsers", $groupUserAlias)
               ->leftJoin("$groupUserAlias.user", $userAlias);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find Groups by their names, returns only provided fields.
     *
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string|null  $userAlias
     * @param  string|null  $groupUserAlias
     * @param  string|null  $search
     *
     * @return Group[]
     */
    public function searchGroups(
        array $fields,
        string $alias = "g",
        ?string $userAlias = null,
        ?string $groupUserAlias = null,
        ?string $search = null
    ): array {
        $qb = $this->createQueryBuilder($alias)
                   ->select($fields)
                   ->orderBy("$alias.name", 'ASC')
                   ->where("$alias.private = false");

        if (!is_null($userAlias) && !is_null($groupUserAlias)) {
            $qb->leftJoin("$alias.groupUsers", $groupUserAlias)
               ->leftJoin("$groupUserAlias.user", $userAlias);
        }

        if (!empty($search)) {
            $qb->andWhere("$alias.name LIKE :search")
               ->setParameter('search', "$search%");
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find private groups by user IDs.
     * Returns array keyed by group ID.
     *
     * @param  string[]  $userIds
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string  $groupUserAlias
     * @param  string  $userAlias
     *
     * @return Group[]
     */
    public function findPrivateGroupsByUserIds(
        array $userIds,
        array $fields = [],
        string $alias = 'g',
        string $groupUserAlias = 'gu',
        string $userAlias = 'u'
    ): array {
        if (empty($userIds)) {
            return [];
        }

        $defaultFields = [
            "PARTIAL $alias.{id, name, publicKey, private}",
            "PARTIAL $groupUserAlias.{group, user}",
            "PARTIAL $userAlias.{id, username, email}",
        ];

        $qb = $this->createQueryBuilder($alias, "$alias.id")
                   ->select(empty($fields) ? $defaultFields : $fields)
                   ->innerJoin("$alias.groupUsers", $groupUserAlias)
                   ->innerJoin("$groupUserAlias.user", $userAlias)
                   ->where("$userAlias.id IN (:userIds)")
                   ->andWhere("$alias.private = true")
                   ->setParameter('userIds', $userIds);

        return $qb->getQuery()->getResult();
    }
}
