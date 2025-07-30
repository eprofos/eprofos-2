<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Training\Formation;
use App\Entity\Training\Session;
use App\Entity\Training\SessionRegistration;
use App\Entity\User\Student;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * StudentEnrollmentRepository for managing student enrollment data and queries.
 *
 * Critical for the Student Content Access System - provides methods for finding
 * enrollments, managing enrollment status, and supporting access control decisions.
 *
 * @extends ServiceEntityRepository<StudentEnrollment>
 */
class StudentEnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentEnrollment::class);
    }

    /**
     * Find active enrollments for a student.
     *
     * @return StudentEnrollment[]
     */
    public function findActiveEnrollmentsByStudent(Student $student): array
    {
        return $this->createQueryBuilder('se')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->leftJoin('s.formation', 'f')
            ->where('se.student = :student')
            ->andWhere('se.status = :status')
            ->setParameter('student', $student)
            ->setParameter('status', StudentEnrollment::STATUS_ENROLLED)
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find enrollment by student and session.
     */
    public function findEnrollmentByStudentAndSession(Student $student, Session $session): ?StudentEnrollment
    {
        return $this->createQueryBuilder('se')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->where('se.student = :student')
            ->andWhere('sr.session = :session')
            ->setParameter('student', $student)
            ->setParameter('session', $session)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find enrollment by student and session registration.
     */
    public function findEnrollmentByStudentAndSessionRegistration(Student $student, SessionRegistration $sessionRegistration): ?StudentEnrollment
    {
        return $this->findOneBy([
            'student' => $student,
            'sessionRegistration' => $sessionRegistration,
        ]);
    }

    /**
     * Find enrollments for a formation.
     *
     * @return StudentEnrollment[]
     */
    public function findEnrollmentsByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('se')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->where('s.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('se.enrolledAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find enrollments by status.
     *
     * @return StudentEnrollment[]
     */
    public function findEnrollmentsByStatus(string $status): array
    {
        return $this->createQueryBuilder('se')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->leftJoin('s.formation', 'f')
            ->where('se.status = :status')
            ->setParameter('status', $status)
            ->orderBy('se.enrolledAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find enrollments for a session.
     *
     * @return StudentEnrollment[]
     */
    public function findEnrollmentsBySession(Session $session): array
    {
        return $this->createQueryBuilder('se')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->where('sr.session = :session')
            ->setParameter('session', $session)
            ->orderBy('se.enrolledAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find recent enrollments (for dashboard).
     *
     * @return StudentEnrollment[]
     */
    public function findRecentEnrollments(int $limit = 10): array
    {
        return $this->createQueryBuilder('se')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->leftJoin('s.formation', 'f')
            ->orderBy('se.enrolledAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Check if student has access to formation.
     */
    public function hasStudentAccessToFormation(Student $student, Formation $formation): bool
    {
        $enrollment = $this->createQueryBuilder('se')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->where('se.student = :student')
            ->andWhere('s.formation = :formation')
            ->andWhere('se.status = :status')
            ->setParameter('student', $student)
            ->setParameter('formation', $formation)
            ->setParameter('status', StudentEnrollment::STATUS_ENROLLED)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $enrollment !== null;
    }

    /**
     * Get enrollment statistics.
     */
    public function getEnrollmentStats(): array
    {
        $result = $this->createQueryBuilder('se')
            ->select([
                'COUNT(se.id) as total_enrollments',
                'SUM(CASE WHEN se.status = :enrolled THEN 1 ELSE 0 END) as active_enrollments',
                'SUM(CASE WHEN se.status = :completed THEN 1 ELSE 0 END) as completed_enrollments',
                'SUM(CASE WHEN se.status = :dropped_out THEN 1 ELSE 0 END) as dropped_enrollments',
                'SUM(CASE WHEN se.status = :suspended THEN 1 ELSE 0 END) as suspended_enrollments',
            ])
            ->setParameter('enrolled', StudentEnrollment::STATUS_ENROLLED)
            ->setParameter('completed', StudentEnrollment::STATUS_COMPLETED)
            ->setParameter('dropped_out', StudentEnrollment::STATUS_DROPPED_OUT)
            ->setParameter('suspended', StudentEnrollment::STATUS_SUSPENDED)
            ->getQuery()
            ->getSingleResult()
        ;

        return [
            'total' => (int) $result['total_enrollments'],
            'active' => (int) $result['active_enrollments'],
            'completed' => (int) $result['completed_enrollments'],
            'dropped' => (int) $result['dropped_enrollments'],
            'suspended' => (int) $result['suspended_enrollments'],
        ];
    }

    /**
     * Get completion rate for a formation.
     */
    public function getFormationCompletionRate(Formation $formation): float
    {
        $result = $this->createQueryBuilder('se')
            ->select([
                'COUNT(se.id) as total_enrollments',
                'SUM(CASE WHEN se.status = :completed THEN 1 ELSE 0 END) as completed_enrollments',
            ])
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->where('s.formation = :formation')
            ->setParameter('formation', $formation)
            ->setParameter('completed', StudentEnrollment::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleResult()
        ;

        $total = (int) $result['total_enrollments'];
        $completed = (int) $result['completed_enrollments'];

        return $total > 0 ? ($completed / $total) * 100 : 0.0;
    }

    /**
     * Get dropout rate for a formation.
     */
    public function getFormationDropoutRate(Formation $formation): float
    {
        $result = $this->createQueryBuilder('se')
            ->select([
                'COUNT(se.id) as total_enrollments',
                'SUM(CASE WHEN se.status = :dropped_out THEN 1 ELSE 0 END) as dropped_enrollments',
            ])
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->where('s.formation = :formation')
            ->setParameter('formation', $formation)
            ->setParameter('dropped_out', StudentEnrollment::STATUS_DROPPED_OUT)
            ->getQuery()
            ->getSingleResult()
        ;

        $total = (int) $result['total_enrollments'];
        $dropped = (int) $result['dropped_enrollments'];

        return $total > 0 ? ($dropped / $total) * 100 : 0.0;
    }

    /**
     * Find enrollments at risk of dropout.
     *
     * @return StudentEnrollment[]
     */
    public function findAtRiskEnrollments(): array
    {
        return $this->createQueryBuilder('se')
            ->leftJoin('se.progress', 'sp')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->leftJoin('s.formation', 'f')
            ->where('se.status = :enrolled')
            ->andWhere('sp.atRiskOfDropout = :at_risk')
            ->setParameter('enrolled', StudentEnrollment::STATUS_ENROLLED)
            ->setParameter('at_risk', true)
            ->orderBy('sp.riskScore', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find students without progress records (potential data integrity issues).
     *
     * @return StudentEnrollment[]
     */
    public function findEnrollmentsWithoutProgress(): array
    {
        return $this->createQueryBuilder('se')
            ->leftJoin('se.progress', 'sp')
            ->where('se.status = :enrolled')
            ->andWhere('sp.id IS NULL')
            ->setParameter('enrolled', StudentEnrollment::STATUS_ENROLLED)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find overdue enrollments (sessions started but not completed after expected duration).
     *
     * @return StudentEnrollment[]
     */
    public function findOverdueEnrollments(int $overdueThresholdDays = 30): array
    {
        $threshold = new DateTime('-' . $overdueThresholdDays . ' days');

        return $this->createQueryBuilder('se')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->where('se.status = :enrolled')
            ->andWhere('s.startDate < :threshold')
            ->setParameter('enrolled', StudentEnrollment::STATUS_ENROLLED)
            ->setParameter('threshold', $threshold)
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Create enrollment query builder with common joins.
     */
    public function createEnrollmentQueryBuilder(string $alias = 'se'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin($alias . '.student', 'st')
            ->leftJoin($alias . '.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 's')
            ->leftJoin('s.formation', 'f')
            ->leftJoin($alias . '.progress', 'sp')
        ;
    }

    /**
     * Filter enrollments by multiple criteria.
     *
     * @return StudentEnrollment[]
     */
    public function findEnrollmentsByCriteria(array $criteria = []): array
    {
        $qb = $this->createEnrollmentQueryBuilder();

        if (!empty($criteria['status'])) {
            $qb->andWhere('se.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['formation'])) {
            $qb->andWhere('f.id = :formation')
               ->setParameter('formation', $criteria['formation']);
        }

        if (!empty($criteria['student'])) {
            $qb->andWhere('st.id = :student')
               ->setParameter('student', $criteria['student']);
        }

        if (!empty($criteria['enrolledAfter'])) {
            $qb->andWhere('se.enrolledAt >= :enrolledAfter')
               ->setParameter('enrolledAfter', $criteria['enrolledAfter']);
        }

        if (!empty($criteria['enrolledBefore'])) {
            $qb->andWhere('se.enrolledAt <= :enrolledBefore')
               ->setParameter('enrolledBefore', $criteria['enrolledBefore']);
        }

        if (isset($criteria['atRisk']) && $criteria['atRisk'] === true) {
            $qb->andWhere('sp.atRiskOfDropout = :at_risk')
               ->setParameter('at_risk', true);
        }

        $qb->orderBy('se.enrolledAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Save entity.
     */
    public function save(StudentEnrollment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove entity.
     */
    public function remove(StudentEnrollment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
