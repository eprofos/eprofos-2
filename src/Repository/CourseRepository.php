<?php

namespace App\Repository;

use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /**
     * Find active courses for a specific chapter
     */
    public function findActiveByChapter($chapterId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.chapter = :chapterId')
            ->andWhere('c.isActive = :active')
            ->setParameter('chapterId', $chapterId)
            ->setParameter('active', true)
            ->orderBy('c.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find courses with their exercises and QCMs
     */
    public function findWithActivitiesByChapter($chapterId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.exercises', 'e')
            ->leftJoin('c.qcms', 'q')
            ->addSelect('e', 'q')
            ->andWhere('c.chapter = :chapterId')
            ->andWhere('c.isActive = :active')
            ->setParameter('chapterId', $chapterId)
            ->setParameter('active', true)
            ->orderBy('c.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find courses by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->andWhere('c.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total duration by chapter
     */
    public function getTotalDurationByChapter($chapterId): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.durationMinutes) as total')
            ->andWhere('c.chapter = :chapterId')
            ->andWhere('c.isActive = :active')
            ->setParameter('chapterId', $chapterId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : 0;
    }
}
