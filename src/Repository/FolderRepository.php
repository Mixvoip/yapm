<?php

/**
 * @author bsteffan
 * @since 2025-05-28
 * @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository
 */

namespace App\Repository;

use App\Entity\Folder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Folder>
 *
 * @method Folder|null find($id, $lockMode = null, $lockVersion = null)
 * @method Folder|null findOneBy(array $criteria, array $orderBy = null)
 * @method Folder[]    findAll()
 * @method Folder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FolderRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Folder::class);
    }

    /**
     * Return folders by ids, returns only provided fields.
     *
     * @param  string[]  $ids
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string|null  $groupAlias
     * @param  string|null  $folderGroupAlias
     * @param  string|null  $vaultAlias
     *
     * @return Folder[]
     */
    public function findByIds(
        array $ids,
        array $fields,
        string $alias = "f",
        ?string $groupAlias = null,
        ?string $folderGroupAlias = null,
        ?string $vaultAlias = null,
    ): array {
        $qb = $this->createQueryBuilder($alias, "$alias.id")
                   ->select($fields)
                   ->where("$alias.id IN (:ids)")
                   ->setParameter('ids', $ids);

        if (!is_null($groupAlias) && !is_null($folderGroupAlias)) {
            $qb->innerJoin("$alias.folderGroups", $folderGroupAlias);
            $qb->innerJoin("$folderGroupAlias.group", $groupAlias);
        }

        if (!is_null($vaultAlias)) {
            $qb->innerJoin("$alias.vault", $vaultAlias);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all folders for a folder id and group ids.
     *
     * @param  string  $folderId
     * @param  string[]  $groupIds
     *
     * @return Folder[]
     */
    public function findForFolder(string $folderId, array $groupIds): array
    {
        return $this->createQueryBuilder('f')
                    ->select("PARTIAL f.{id, name}")
                    ->innerJoin('f.folderGroups', 'fg')
                    ->innerJoin('fg.group', 'g')
                    ->where('f.parent = :folderId')
                    ->andWhere('g.id IN (:groupIds)')
                    ->setParameter('folderId', $folderId)
                    ->setParameter('groupIds', $groupIds)
                    ->getQuery()
                    ->getResult();
    }

    /**
     * Find all folders for a vault id and group ids.
     *
     * @param  string  $vaultId
     * @param  string[]  $groupIds
     *
     * @return Folder[]
     */
    public function findForVaultRoot(string $vaultId, array $groupIds): array
    {
        return $this->createQueryBuilder('f')
                    ->select("PARTIAL f.{id, name}")
                    ->innerJoin('f.folderGroups', 'fg')
                    ->innerJoin('fg.group', 'g')
                    ->where('f.vault = :vaultId')
                    ->andWhere('f.parent is NULL')
                    ->andWhere('g.id IN (:groupIds)')
                    ->setParameter('vaultId', $vaultId)
                    ->setParameter('groupIds', $groupIds)
                    ->getQuery()
                    ->getResult();
    }
}
