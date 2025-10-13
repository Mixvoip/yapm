<?php

/**
 * @author bsteffan
 * @since 2025-05-28
 * @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository
 */

namespace App\Repository;

use App\Entity\GroupsVault;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupsVault>
 *
 * @method GroupsVault|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupsVault|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupsVault[]    findAll()
 * @method GroupsVault[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupsVaultRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupsVault::class);
    }
}
