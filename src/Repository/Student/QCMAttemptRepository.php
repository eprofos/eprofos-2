<?php

declare(strict_types=1);

namespace App\Repository\Student;

use App\Entity\Student\QCMAttempt;
use App\Entity\Training\QCM;
use App\Entity\User\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QCMAttempt>
 */
class QCMAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QCMAttempt::class);
    }

    /**
     * Find attempts by student.
     *
     * @return QCMAttempt[]
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('qa')
            ->andWhere('qa.student = :student')
            ->setParameter('student', $student)
            ->orderBy('qa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find current active attempt by student and QCM.
     */
    public function findActiveAttempt(Student $student, QCM $qcm): ?QCMAttempt
    {
        return $this->createQueryBuilder('qa')
            ->andWhere('qa.student = :student')
            ->andWhere('qa.qcm = :qcm')
            ->andWhere('qa.status = :status')
            ->setParameter('student', $student)
            ->setParameter('qcm', $qcm)
            ->setParameter('status', QCMAttempt::STATUS_IN_PROGRESS)
            ->orderBy('qa.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find latest attempt by student and QCM.
     */
    public function findLatestAttempt(Student $student, QCM $qcm): ?QCMAttempt
    {
        return $this->createQueryBuilder('qa')
            ->andWhere('qa.student = :student')
            ->andWhere('qa.qcm = :qcm')
            ->setParameter('student', $student)
            ->setParameter('qcm', $qcm)
            ->orderBy('qa.attemptNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all attempts for a specific QCM and student.
     *
     * @return QCMAttempt[]
     */
    public function findAllByStudentAndQCM(Student $student, QCM $qcm): array
    {
        return $this->createQueryBuilder('qa')
            ->andWhere('qa.student = :student')
            ->andWhere('qa.qcm = :qcm')
            ->setParameter('student', $student)
            ->setParameter('qcm', $qcm)
            ->orderBy('qa.attemptNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next attempt number for a student and QCM.
     */
    public function getNextAttemptNumber(Student $student, QCM $qcm): int
    {
        $lastAttempt = $this->createQueryBuilder('qa')
            ->select('qa.attemptNumber')
            ->andWhere('qa.student = :student')
            ->andWhere('qa.qcm = :qcm')
            ->setParameter('student', $student)
            ->setParameter('qcm', $qcm)
            ->orderBy('qa.attemptNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $lastAttempt ? $lastAttempt['attemptNumber'] + 1 : 1;
    }

    /**
     * Count completed attempts for a student and QCM.
     */
    public function countCompletedAttempts(Student $student, QCM $qcm): int
    {
        return (int) $this->createQueryBuilder('qa')
            ->select('COUNT(qa)')
            ->andWhere('qa.student = :student')
            ->andWhere('qa.qcm = :qcm')
            ->andWhere('qa.status = :status')
            ->setParameter('student', $student)
            ->setParameter('qcm', $qcm)
            ->setParameter('status', QCMAttempt::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check if student can start a new attempt.
     */
    public function canStartNewAttempt(Student $student, QCM $qcm): bool
    {
        $completedAttempts = $this->countCompletedAttempts($student, $qcm);
        $activeAttempt = $this->findActiveAttempt($student, $qcm);
        
        // Check if active attempt has expired
        if ($activeAttempt && $activeAttempt->hasExpired()) {
            $activeAttempt = null; // Treat expired attempts as inactive
        }
        
        return $completedAttempts < $qcm->getMaxAttempts() && $activeAttempt === null;
    }

    /**
     * Get student's best score for a QCM.
     */
    public function getBestScore(Student $student, QCM $qcm): ?int
    {
        $result = $this->createQueryBuilder('qa')
            ->select('MAX(qa.score) as bestScore')
            ->andWhere('qa.student = :student')
            ->andWhere('qa.qcm = :qcm')
            ->andWhere('qa.status = :status')
            ->setParameter('student', $student)
            ->setParameter('qcm', $qcm)
            ->setParameter('status', QCMAttempt::STATUS_COMPLETED)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['bestScore'] ?? null;
    }

    /**
     * Check if student has passed a QCM.
     */
    public function hasStudentPassed(Student $student, QCM $qcm): bool
    {
        $passedAttempt = $this->createQueryBuilder('qa')
            ->andWhere('qa.student = :student')
            ->andWhere('qa.qcm = :qcm')
            ->andWhere('qa.passed = :passed')
            ->setParameter('student', $student)
            ->setParameter('qcm', $qcm)
            ->setParameter('passed', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $passedAttempt !== null;
    }

    /**
     * Find expired attempts that need cleanup.
     *
     * @return QCMAttempt[]
     */
    public function findExpiredAttempts(): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('qa')
            ->andWhere('qa.status = :status')
            ->andWhere('qa.expiresAt IS NOT NULL')
            ->andWhere('qa.expiresAt <= :now')
            ->setParameter('status', QCMAttempt::STATUS_IN_PROGRESS)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get QCM statistics.
     */
    public function getQCMStatistics(QCM $qcm): array
    {
        $qb = $this->createQueryBuilder('qa')
            ->select([
                'COUNT(DISTINCT qa.student) as totalStudents',
                'COUNT(qa) as totalAttempts',
                'AVG(qa.score) as averageScore',
                'MAX(qa.score) as maxScore',
                'MIN(qa.score) as minScore',
                'AVG(qa.timeSpent) as averageTimeSpent',
                'SUM(CASE WHEN qa.passed = true THEN 1 ELSE 0 END) as passedCount'
            ])
            ->andWhere('qa.qcm = :qcm')
            ->andWhere('qa.status = :status')
            ->setParameter('qcm', $qcm)
            ->setParameter('status', QCMAttempt::STATUS_COMPLETED);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Get question statistics for a QCM.
     */
    public function getQuestionStatistics(QCM $qcm): array
    {
        $attempts = $this->createQueryBuilder('qa')
            ->andWhere('qa.qcm = :qcm')
            ->andWhere('qa.status = :status')
            ->setParameter('qcm', $qcm)
            ->setParameter('status', QCMAttempt::STATUS_COMPLETED)
            ->getQuery()
            ->getResult();

        $questionCount = $qcm->getQuestionCount();
        $statistics = [];

        for ($i = 0; $i < $questionCount; $i++) {
            $statistics[$i] = [
                'question_index' => $i,
                'total_attempts' => 0,
                'correct_answers' => 0,
                'success_rate' => 0,
            ];
        }

        foreach ($attempts as $attempt) {
            $questionScores = $attempt->getQuestionScores() ?? [];
            foreach ($questionScores as $index => $score) {
                if (isset($statistics[$index])) {
                    $statistics[$index]['total_attempts']++;
                    if ($score['correct']) {
                        $statistics[$index]['correct_answers']++;
                    }
                }
            }
        }

        // Calculate success rates
        foreach ($statistics as $index => $stat) {
            if ($stat['total_attempts'] > 0) {
                $statistics[$index]['success_rate'] = ($stat['correct_answers'] / $stat['total_attempts']) * 100;
            }
        }

        return $statistics;
    }

    public function save(QCMAttempt $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(QCMAttempt $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
