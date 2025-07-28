<?php

declare(strict_types=1);

namespace App\Repository\Training;

use App\Entity\Training\QCM;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QCM>
 */
class QCMRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QCM::class);
    }

    /**
     * Find active QCMs for a specific course.
     *
     * @param mixed $courseId
     */
    public function findActiveByCourse($courseId): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.course = :courseId')
            ->andWhere('q.isActive = :active')
            ->setParameter('courseId', $courseId)
            ->setParameter('active', true)
            ->orderBy('q.orderIndex', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find QCMs with time limits.
     */
    public function findWithTimeLimit(): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.timeLimitMinutes IS NOT NULL')
            ->andWhere('q.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('q.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find QCMs by question count range.
     */
    public function findByQuestionCountRange(int $min, int $max): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('JSON_LENGTH(q.questions) >= :min')
            ->andWhere('JSON_LENGTH(q.questions) <= :max')
            ->andWhere('q.isActive = :active')
            ->setParameter('min', $min)
            ->setParameter('max', $max)
            ->setParameter('active', true)
            ->orderBy('q.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find QCMs with multiple attempts allowed.
     */
    public function findWithMultipleAttempts(): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.maxAttempts > 1')
            ->andWhere('q.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('q.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get average passing percentage.
     */
    public function getAveragePassingPercentage(): float
    {
        $result = $this->createQueryBuilder('q')
            ->select('AVG(q.passingScore / q.maxScore * 100) as average')
            ->andWhere('q.maxScore > 0')
            ->andWhere('q.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $result ? (float) $result : 0.0;
    }

    /**
     * Find QCMs with specific evaluation criteria.
     */
    public function findWithEvaluationCriteria(): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.evaluationCriteria IS NOT NULL')
            ->andWhere('q.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('q.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
