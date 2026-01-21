<?php

/**
 * @author bsteffan
 * @since 2025-05-28
 * @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository
 */

namespace App\Repository;

use App\Entity\Password;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Password>
 *
 * @method Password|null find($id, $lockMode = null, $lockVersion = null)
 * @method Password|null findOneBy(array $criteria, array $orderBy = null)
 * @method Password[]    findAll()
 * @method Password[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PasswordRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Password::class);
    }

    /**
     * Find Passwords by ids, returns only provided fields.
     *
     * @param  string[]  $ids
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string|null  $folderAlias
     * @param  string|null  $groupAlias
     * @param  string|null  $groupPasswordAlias
     * @param  string|null  $vaultAlias
     *
     * @return Password[]
     */
    public function findByIds(
        array $ids,
        array $fields,
        string $alias = "p",
        ?string $folderAlias = null,
        ?string $groupAlias = null,
        ?string $groupPasswordAlias = null,
        ?string $vaultAlias = null
    ): array {
        $qb = $this->createQueryBuilder($alias, "$alias.id")
                   ->select($fields)
                   ->where("$alias.id IN (:ids)")
                   ->setParameter('ids', $ids);

        if (!is_null($folderAlias)) {
            $qb->leftJoin("$alias.folder", $folderAlias);
        }

        if (!is_null($groupAlias) && !is_null($groupPasswordAlias)) {
            $qb->innerJoin("$alias.groupPasswords", $groupPasswordAlias);
            $qb->innerJoin("$groupPasswordAlias.group", $groupAlias);
        }

        if (!is_null($vaultAlias)) {
            $qb->innerJoin("$alias.vault", $vaultAlias);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all vault root passwords for a vault id and group ids.
     *
     * @param  string  $vaultId
     * @param  string[]  $groupIds
     *
     * @return Password[]
     */
    public function findForVaultRoot(string $vaultId, array $groupIds): array
    {
        return $this->createQueryBuilder('p')
                    ->select("PARTIAL p.{id, title}")
                    ->innerJoin('p.groupPasswords', 'gp')
                    ->innerJoin('gp.group', 'g')
                    ->where('p.vault = :vaultId')
                    ->andWhere('p.folder is NULL')
                    ->andWhere('g.id IN (:groupIds)')
                    ->setParameter('vaultId', $vaultId)
                    ->setParameter('groupIds', $groupIds)
                    ->getQuery()
                    ->getResult();
    }

    /**
     * Find all passwords for a folder id and group ids.
     * Access control is done via EXISTS subquery to ensure all groups are loaded for each password.
     *
     * @param  string  $folderId
     * @param  string[]  $groupIds
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string  $groupAlias
     * @param  string  $groupsPasswordAlias
     *
     * @return Password[]
     */
    public function findForFolder(
        string $folderId,
        array $groupIds,
        array $fields,
        string $alias = "p",
        string $groupAlias = "g",
        string $groupsPasswordAlias = "gp"
    ): array {
        return $this->createQueryBuilder($alias)
                    ->select($fields)
                    ->innerJoin("$alias.groupPasswords", $groupsPasswordAlias)
                    ->innerJoin("$groupsPasswordAlias.group", $groupAlias)
                    ->where("$alias.folder = :folderId")
                    ->andWhere(
                        "EXISTS (SELECT 1 FROM App\Entity\GroupsPassword gp_access
                                 WHERE gp_access.password = $alias
                                 AND gp_access.group IN (:groupIds))"
                    )
                    ->setParameter('folderId', $folderId)
                    ->setParameter('groupIds', $groupIds)
                    ->orderBy("$alias.title")
                    ->getQuery()
                    ->getResult();
    }

    /**
     * Search passwords by search term, vault id and group ids.
     *
     * @param  string  $search
     * @param  string  $vaultId
     * @param  string[]  $groupIds
     * @param  int  $firstResult
     * @param  int  $limit
     *
     * @return Password[]
     */
    public function searchPasswords(
        string $search,
        string $vaultId,
        array $groupIds,
        int $firstResult,
        int $limit
    ): array {
        $qb = $this->createQueryBuilder('p')
                   ->select("PARTIAL p.{id, title, target, externalId}", "PARTIAL v.{id, name}", "PARTIAL f.{id, name}")
                   ->innerJoin('p.vault', "v")
                   ->leftJoin("p.folder", "f")
                   ->innerJoin('p.groupPasswords', 'gp')
                   ->innerJoin('gp.group', 'g')
                   ->where("g.id IN (:groupIds)")
                   ->setParameter('groupIds', $groupIds)
                   ->groupBy('p.id')
                   ->orderBy('p.id')
                   ->setFirstResult($firstResult)
                   ->setMaxResults($limit);

        if ($search !== "") {
            if (is_numeric($search)) {
                $qb->andWhere("p.externalId = :exactSearch");
            } else {
                $orX = $qb->expr()->orX(
                    "p.title LIKE :vagueSearch",
                    "p.description LIKE :vagueSearch",
                    "p.target LIKE :vagueSearch",
                    "p.externalId LIKE :exactSearch",
                );
                $qb->andWhere($orX)
                   ->setParameter('vagueSearch', "%$search%");
            }

            $qb->setParameter("exactSearch", "$search");
        }

        if (!empty($vaultId)) {
            $qb->andWhere("p.vault = :vaultId")
               ->setParameter('vaultId', $vaultId);
        }

        return $qb->getQuery()->getResult();
    }
}
