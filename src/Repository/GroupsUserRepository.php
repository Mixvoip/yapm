<?php

/**
 * @author bsteffan
 * @since 2025-05-28
 * @noinspection PhpMultipleClassDeclarationsInspection ServiceEntityRepository
 */

namespace App\Repository;

use App\Entity\GroupsUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupsUser>
 *
 * @method GroupsUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupsUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupsUser[]    findAll()
 * @method GroupsUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupsUserRepository extends ServiceEntityRepository
{
    /**
     * @inheritDoc
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupsUser::class);
    }
}
