<?php

/**
 * @author bsteffan
 * @since 2025-05-28
 * @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository
 */

namespace App\Repository;

use App\Entity\Vault;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vault>
 *
 * @method Vault|null find($id, $lockMode = null, $lockVersion = null)
 * @method Vault|null findOneBy(array $criteria, array $orderBy = null)
 * @method Vault[]    findAll()
 * @method Vault[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VaultRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vault::class);
    }

    /**
     * Find Vaults by ids, returns only provided fields.
     *
     * @param  string[]  $ids
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string|null  $groupAlias
     * @param  string|null  $groupVaultAlias
     *
     * @return Vault[]
     */
    public function findByIds(
        array $ids,
        array $fields,
        string $alias = "v",
        ?string $groupAlias = null,
        ?string $groupVaultAlias = null
    ): array {
        $qb = $this->createQueryBuilder($alias, "$alias.id")
                   ->select($fields)
                   ->where('v.id IN (:ids)')
                   ->setParameter('ids', $ids);

        if (!is_null($groupAlias) && !is_null($groupVaultAlias)) {
            $qb->innerJoin("$alias.groupVaults", $groupVaultAlias);
            $qb->innerJoin("$groupVaultAlias.group", $groupAlias);
        }

        return $qb->getQuery()
                  ->getResult();
    }

    /**
     * Find all readable vaults for given group ids.
     *
     * @param  string[]  $groupIds
     * @param  string[]  $fields
     * @param  string  $alias
     * @param  string  $groupVaultAlias
     * @param  string  $groupAlias
     *
     * @return Vault[]
     */
    public function findReadableByGroupIds(
        array $groupIds,
        array $fields,
        string $alias = "v",
        string $groupVaultAlias = "gv",
        string $groupAlias = "g"
    ): array {
        /** @var Vault[] $vaults */
        $vaults = $this->createQueryBuilder($alias, "$alias.id")
                       ->select($fields)
                       ->innerJoin("$alias.groupVaults", $groupVaultAlias)
                       ->innerJoin("$groupVaultAlias.group", $groupAlias)
                       ->orderBy("$alias.name", 'ASC')
                       ->getQuery()
                       ->getResult();

        return array_filter($vaults, function (Vault $vault) use ($groupIds) {
            return $vault->hasReadPermission($groupIds);
        });
    }
}
