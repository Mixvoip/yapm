<?php

/**
 * @author bsteffan
 * @since 2025-05-28
 * @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository
 */

namespace App\Repository;

use App\Entity\FoldersGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FoldersGroup>
 *
 * @method FoldersGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method FoldersGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method FoldersGroup[]    findAll()
 * @method FoldersGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FoldersGroupRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FoldersGroup::class);
    }
}
