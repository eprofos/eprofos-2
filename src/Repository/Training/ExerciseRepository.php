<?php

declare(strict_types=1);

namespace App\Repository\Training;

use App\Entity\Training\Exercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exercise>
 */
class ExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercise::class);
    }

    /**
     * Find active exercises for a specific course.
     *
     * @param mixed $courseId
     */
    public function findActiveByCourse($courseId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.course = :courseId')
            ->andWhere('e.isActive = :active')
            ->setParameter('courseId', $courseId)
            ->setParameter('active', true)
            ->orderBy('e.orderIndex', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find exercises by type.
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.type = :type')
            ->andWhere('e.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('e.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find exercises by difficulty.
     */
    public function findByDifficulty(string $difficulty): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.difficulty = :difficulty')
            ->andWhere('e.isActive = :active')
            ->setParameter('difficulty', $difficulty)
            ->setParameter('active', true)
            ->orderBy('e.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get total estimated duration by course.
     *
     * @param mixed $courseId
     */
    public function getTotalEstimatedDurationByCourse($courseId): int
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.estimatedDurationMinutes) as total')
            ->andWhere('e.course = :courseId')
            ->andWhere('e.isActive = :active')
            ->setParameter('courseId', $courseId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $result ? (int) $result : 0;
    }

    /**
     * Find exercises with specific evaluation criteria.
     */
    public function findWithEvaluationCriteria(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.evaluationCriteria IS NOT NULL')
            ->andWhere('e.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('e.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
