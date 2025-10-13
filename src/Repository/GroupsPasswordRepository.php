<?php

/**
 * @author bsteffan
 * @since 2025-05-28
 * @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository
 */

namespace App\Repository;

use App\Entity\GroupsPassword;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupsPassword>
 *
 * @method GroupsPassword|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupsPassword|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupsPassword[]    findAll()
 * @method GroupsPassword[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupsPasswordRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupsPassword::class);
    }
}
