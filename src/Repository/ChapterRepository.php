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

    /**
     * Find chapters by module ordered by order index
     */
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

    /**
     * Find all chapters by module (including inactive ones)
     */
    public function findAllByModuleOrdered(int $moduleId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.module = :moduleId')
            ->setParameter('moduleId', $moduleId)
            ->orderBy('c.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the next order index for a module
     */
    public function getNextOrderIndex(int $moduleId): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('MAX(c.orderIndex)')
            ->where('c.module = :moduleId')
            ->setParameter('moduleId', $moduleId)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Update order indexes for chapters
     */
    public function updateOrderIndexes(array $chapterIds): void
    {
        $connection = $this->getEntityManager()->getConnection();
        
        foreach ($chapterIds as $index => $chapterId) {
            $connection->update(
                'chapter',
                ['order_index' => $index + 1],
                ['id' => $chapterId]
            );
        }
    }

    /**
     * Find chapters with search filters
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.module', 'm')
            ->leftJoin('m.formation', 'f')
            ->orderBy('c.orderIndex', 'ASC');

        if (isset($filters['module'])) {
            $qb->andWhere('c.module = :module')
               ->setParameter('module', $filters['module']);
        }

        if (isset($filters['formation'])) {
            $qb->andWhere('m.formation = :formation')
               ->setParameter('formation', $filters['formation']);
        }

        if (isset($filters['active'])) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', $filters['active']);
        }

        if (isset($filters['search'])) {
            $qb->andWhere('c.title LIKE :search OR c.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get total duration for all chapters in a module
     */
    public function getTotalDurationForModule(int $moduleId): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.durationMinutes)')
            ->where('c.module = :moduleId')
            ->andWhere('c.isActive = true')
            ->setParameter('moduleId', $moduleId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }
}
