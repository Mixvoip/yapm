<?php

/** @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository */

namespace App\Repository;

use App\Entity\ShareItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShareItem>
 *
 * @method ShareItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShareItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShareItem[]    findAll()
 * @method ShareItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShareItemRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShareItem::class);
    }
}
