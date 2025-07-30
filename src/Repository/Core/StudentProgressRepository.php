<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\StudentProgress;
use App\Entity\Training\Formation;
use App\Entity\User\Student;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * StudentProgressRepository for managing student progress data and analytics.
 *
 * Critical for Qualiopi Criterion 12 compliance - provides methods for tracking
 * student engagement, detecting dropout risks, and generating compliance reports.
 */
class StudentProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentProgress::class);
    }

    /**
     * Find or create student progress for a specific formation.
     */
    public function findOrCreateForStudentAndFormation(Student $student, Formation $formation): StudentProgress
    {
        $progress = $this->findOneBy([
            'student' => $student,
            'formation' => $formation,
        ]);

        if (!$progress) {
            $progress = new StudentProgress();
            $progress->setStudent($student);
            $progress->setFormation($formation);

            $this->getEntityManager()->persist($progress);
            $this->getEntityManager()->flush();
        }

        return $progress;
    }

    /**
     * Find students at risk of dropout.
     */
    public function findAtRiskStudents(): array
    {
        return $this->createQueryBuilder('sp')
            ->where('sp.atRiskOfDropout = :atRisk')
            ->setParameter('atRisk', true)
            ->orderBy('sp.riskScore', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find students with low engagement (critical for Qualiopi).
     */
    public function findLowEngagementStudents(int $threshold = 40): array
    {
        return $this->createQueryBuilder('sp')
            ->where('sp.engagementScore < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('sp.engagementScore', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find inactive students (no activity for X days).
     */
    public function findInactiveStudents(int $days = 7): array
    {
        $threshold = new DateTime('-' . $days . ' days');

        return $this->createQueryBuilder('sp')
            ->where('sp.lastActivity < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('sp.lastActivity', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find students needing follow-up (for intervention).
     */
    public function findStudentsNeedingFollowUp(): array
    {
        return $this->createQueryBuilder('sp')
            ->where('sp.atRiskOfDropout = :atRisk OR sp.engagementScore < :engagementThreshold')
            ->andWhere('sp.lastRiskAssessment IS NULL OR sp.lastRiskAssessment < :assessmentThreshold')
            ->setParameter('atRisk', true)
            ->setParameter('engagementThreshold', 50)
            ->setParameter('assessmentThreshold', new DateTime('-3 days'))
            ->orderBy('sp.riskScore', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get completion statistics for a formation.
     */
    public function getFormationCompletionStats(Formation $formation): array
    {
        $qb = $this->createQueryBuilder('sp')
            ->select([
                'COUNT(sp.id) as totalStudents',
                'AVG(sp.completionPercentage) as averageCompletion',
                'SUM(CASE WHEN sp.completedAt IS NOT NULL THEN 1 ELSE 0 END) as completedStudents',
                'SUM(CASE WHEN sp.atRiskOfDropout = true THEN 1 ELSE 0 END) as atRiskStudents',
                'AVG(sp.engagementScore) as averageEngagement',
                'AVG(sp.attendanceRate) as averageAttendance',
            ])
            ->where('sp.formation = :formation')
            ->setParameter('formation', $formation)
        ;

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Get engagement metrics for dashboard.
     */
    public function getEngagementMetrics(): array
    {
        $qb = $this->createQueryBuilder('sp')
            ->select([
                'COUNT(sp.id) as totalActivePrograms',
                'SUM(CASE WHEN sp.engagementScore >= 80 THEN 1 ELSE 0 END) as highEngagement',
                'SUM(CASE WHEN sp.engagementScore BETWEEN 60 AND 79 THEN 1 ELSE 0 END) as mediumEngagement',
                'SUM(CASE WHEN sp.engagementScore < 60 THEN 1 ELSE 0 END) as lowEngagement',
                'SUM(CASE WHEN sp.atRiskOfDropout = true THEN 1 ELSE 0 END) as atRiskCount',
                'AVG(sp.engagementScore) as averageEngagement',
                'AVG(sp.completionPercentage) as averageCompletion',
            ])
            ->where('sp.completedAt IS NULL') // Only active trainings
        ;

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Find progress records for a specific student.
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('sp')
            ->leftJoin('sp.formation', 'f')
            ->leftJoin('sp.currentModule', 'm')
            ->leftJoin('sp.currentChapter', 'c')
            ->where('sp.student = :student')
            ->setParameter('student', $student)
            ->orderBy('sp.startedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find students by completion percentage range.
     */
    public function findByCompletionRange(float $minPercentage, float $maxPercentage): array
    {
        return $this->createQueryBuilder('sp')
            ->where('sp.completionPercentage BETWEEN :min AND :max')
            ->setParameter('min', $minPercentage)
            ->setParameter('max', $maxPercentage)
            ->orderBy('sp.completionPercentage', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get retention statistics (Qualiopi requirement).
     */
    public function getRetentionStats(): array
    {
        $qb = $this->createQueryBuilder('sp')
            ->select([
                'COUNT(sp.id) as totalEnrollments',
                'SUM(CASE WHEN sp.completedAt IS NOT NULL THEN 1 ELSE 0 END) as completions',
                'SUM(CASE WHEN sp.atRiskOfDropout = true THEN 1 ELSE 0 END) as atRisk',
                'SUM(CASE WHEN sp.lastActivity < :inactiveThreshold THEN 1 ELSE 0 END) as inactive',
                'AVG(sp.attendanceRate) as averageAttendance',
                'SUM(CASE WHEN sp.attendanceRate < 70 THEN 1 ELSE 0 END) as poorAttendance',
            ])
            ->setParameter('inactiveThreshold', new DateTime('-14 days'))
        ;

        $result = $qb->getQuery()->getSingleResult();

        // Calculate dropout rate
        $totalActive = $result['totalEnrollments'] - $result['completions'];
        $result['dropoutRate'] = $totalActive > 0 ?
            (($result['atRisk'] + $result['inactive']) / $totalActive) * 100 : 0;

        // Calculate completion rate
        $result['completionRate'] = $result['totalEnrollments'] > 0 ?
            ($result['completions'] / $result['totalEnrollments']) * 100 : 0;

        return $result;
    }

    /**
     * Find students requiring immediate intervention.
     */
    public function findCriticalRiskStudents(): array
    {
        return $this->createQueryBuilder('sp')
            ->where('sp.riskScore >= :criticalThreshold')
            ->andWhere('sp.completedAt IS NULL')
            ->setParameter('criticalThreshold', 60)
            ->orderBy('sp.riskScore', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Export data for Qualiopi compliance reporting.
     */
    public function getQualiopi12ComplianceData(): array
    {
        return $this->createQueryBuilder('sp')
            ->select([
                'sp.id',
                's.firstName',
                's.lastName',
                's.email',
                'f.title as formationTitle',
                'sp.completionPercentage',
                'sp.engagementScore',
                'sp.attendanceRate',
                'sp.riskScore',
                'sp.atRiskOfDropout',
                'sp.startedAt',
                'sp.completedAt',
                'sp.lastActivity',
                'sp.missedSessions',
                'sp.totalTimeSpent',
                'sp.loginCount',
            ])
            ->leftJoin('sp.student', 's')
            ->leftJoin('sp.formation', 'f')
            ->orderBy('sp.riskScore', 'DESC')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * Find progress records with specific difficulty signals.
     */
    public function findByDifficultySignal(string $signal): array
    {
        return $this->createQueryBuilder('sp')
            ->where('JSON_CONTAINS(sp.difficultySignals, :signal) = 1')
            ->setParameter('signal', json_encode($signal))
            ->orderBy('sp.lastActivity', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Update risk assessment for all active students.
     */
    public function updateAllRiskAssessments(): int
    {
        $activeProgress = $this->createQueryBuilder('sp')
            ->where('sp.completedAt IS NULL')
            ->getQuery()
            ->getResult()
        ;

        $updatedCount = 0;
        foreach ($activeProgress as $progress) {
            $progress->detectRiskSignals();
            $updatedCount++;
        }

        $this->getEntityManager()->flush();

        return $updatedCount;
    }

    /**
     * Get students with poor attendance (Qualiopi metric).
     */
    public function findPoorAttendanceStudents(float $threshold = 70.0): array
    {
        return $this->createQueryBuilder('sp')
            ->where('sp.attendanceRate < :threshold')
            ->andWhere('sp.completedAt IS NULL')
            ->setParameter('threshold', $threshold)
            ->orderBy('sp.attendanceRate', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Create query builder for advanced filtering.
     */
    public function createAdvancedFilterQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('sp')
            ->leftJoin('sp.student', 's')
            ->leftJoin('sp.formation', 'f')
            ->leftJoin('sp.currentModule', 'm')
            ->leftJoin('sp.currentChapter', 'c')
        ;
    }

    /**
     * Count students by progress range.
     */
    public function countByProgressRange(float $min, float $max): int
    {
        return $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id)')
            ->leftJoin('sp.student', 's')
            ->leftJoin('s.enrollments', 'se')
            ->where('se.status = :enrolled')
            ->andWhere('sp.completionPercentage >= :min')
            ->andWhere('sp.completionPercentage <= :max')
            ->setParameter('enrolled', 'enrolled')
            ->setParameter('min', $min)
            ->setParameter('max', $max)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average progress across all active students.
     */
    public function getAverageProgress(): float
    {
        $result = $this->createQueryBuilder('sp')
            ->select('AVG(sp.completionPercentage)')
            ->leftJoin('sp.student', 's')
            ->leftJoin('s.enrollments', 'se')
            ->where('se.status = :enrolled')
            ->setParameter('enrolled', 'enrolled')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float) $result, 2) : 0.0;
    }

    /**
     * Save entity.
     */
    public function save(StudentProgress $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove entity.
     */
    public function remove(StudentProgress $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
