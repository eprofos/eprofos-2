<?php

namespace App\Repository;

use App\Entity\Chapter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chapter>
 */
class ChapterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chapter::class);
    }

    public function findByModuleOrdered(int $moduleId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.module = :moduleId')
            ->andWhere('c.isActive = true')
            ->setParameter('moduleId', $moduleId)
            ->orderBy('c.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
