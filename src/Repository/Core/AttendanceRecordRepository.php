<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AttendanceRecord;
use App\Entity\Training\Formation;
use App\Entity\Training\Session;
use App\Entity\User\Student;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * AttendanceRecordRepository for managing attendance data and analytics.
 *
 * Critical for Qualiopi Criterion 12 compliance - provides methods for tracking
 * attendance patterns, calculating attendance rates, and generating compliance reports.
 */
class AttendanceRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttendanceRecord::class);
    }

    /**
     * Find or create attendance record for student and session.
     */
    public function findOrCreateForStudentAndSession(Student $student, Session $session): AttendanceRecord
    {
        $record = $this->findOneBy([
            'student' => $student,
            'session' => $session,
        ]);

        if (!$record) {
            $record = new AttendanceRecord();
            $record->setStudent($student);
            $record->setSession($session);

            $this->getEntityManager()->persist($record);
            $this->getEntityManager()->flush();
        }

        return $record;
    }

    /**
     * Find attendance records for a specific student.
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('ar')
            ->leftJoin('ar.session', 's')
            ->leftJoin('s.formation', 'f')
            ->where('ar.student = :student')
            ->setParameter('student', $student)
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find attendance records for a specific session.
     */
    public function findBySession(Session $session): array
    {
        return $this->createQueryBuilder('ar')
            ->leftJoin('ar.student', 's')
            ->where('ar.session = :session')
            ->setParameter('session', $session)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find attendance records for a formation.
     */
    public function findByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('ar')
            ->leftJoin('ar.session', 's')
            ->leftJoin('ar.student', 'st')
            ->where('s.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('s.startDate', 'DESC')
            ->addOrderBy('st.lastName', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find students with poor attendance (Qualiopi metric).
     */
    public function findStudentsWithPoorAttendance(float $threshold = 70.0): array
    {
        return $this->createQueryBuilder('ar')
            ->select([
                'st.id as student_id',
                'st.firstName',
                'st.lastName',
                'st.email',
                'COUNT(ar.id) as total_sessions',
                'SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) as attended_sessions',
                '(SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) * 100.0 / COUNT(ar.id)) as attendance_rate',
            ])
            ->leftJoin('ar.student', 'st')
            ->setParameter('present_statuses', [
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_PARTIAL,
            ])
            ->groupBy('st.id', 'st.firstName', 'st.lastName', 'st.email')
            ->having('(SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) * 100.0 / COUNT(ar.id)) < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END)', 'ASC')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * Calculate attendance rate for a student.
     */
    public function calculateStudentAttendanceRate(Student $student): float
    {
        $result = $this->createQueryBuilder('ar')
            ->select([
                'COUNT(ar.id) as total_sessions',
                'SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) as attended_sessions',
            ])
            ->where('ar.student = :student')
            ->setParameter('student', $student)
            ->setParameter('present_statuses', [
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_PARTIAL,
            ])
            ->getQuery()
            ->getSingleResult()
        ;

        if ($result['total_sessions'] === 0) {
            return 100.0; // No sessions yet, consider as 100%
        }

        return ($result['attended_sessions'] / $result['total_sessions']) * 100;
    }

    /**
     * Calculate attendance rate for a formation.
     */
    public function calculateFormationAttendanceRate(Formation $formation): float
    {
        $result = $this->createQueryBuilder('ar')
            ->select([
                'COUNT(ar.id) as total_records',
                'SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) as attended_records',
            ])
            ->leftJoin('ar.session', 's')
            ->where('s.formation = :formation')
            ->setParameter('formation', $formation)
            ->setParameter('present_statuses', [
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_PARTIAL,
            ])
            ->getQuery()
            ->getSingleResult()
        ;

        if ($result['total_records'] === 0) {
            return 100.0;
        }

        return ($result['attended_records'] / $result['total_records']) * 100;
    }

    /**
     * Get attendance statistics for dashboard.
     */
    public function getAttendanceStats(): array
    {
        $result = $this->createQueryBuilder('ar')
            ->select([
                'COUNT(ar.id) as total_records',
                'SUM(CASE WHEN ar.status = :present THEN 1 ELSE 0 END) as present_count',
                'SUM(CASE WHEN ar.status = :absent THEN 1 ELSE 0 END) as absent_count',
                'SUM(CASE WHEN ar.status = :late THEN 1 ELSE 0 END) as late_count',
                'SUM(CASE WHEN ar.status = :partial THEN 1 ELSE 0 END) as partial_count',
                'SUM(CASE WHEN ar.excused = true THEN 1 ELSE 0 END) as excused_count',
                'AVG(ar.participationScore) as average_participation',
            ])
            ->setParameter('present', AttendanceRecord::STATUS_PRESENT)
            ->setParameter('absent', AttendanceRecord::STATUS_ABSENT)
            ->setParameter('late', AttendanceRecord::STATUS_LATE)
            ->setParameter('partial', AttendanceRecord::STATUS_PARTIAL)
            ->getQuery()
            ->getSingleResult()
        ;

        // Calculate percentages
        $total = $result['total_records'] ?: 1; // Avoid division by zero
        $result['present_percentage'] = ($result['present_count'] / $total) * 100;
        $result['absent_percentage'] = ($result['absent_count'] / $total) * 100;
        $result['late_percentage'] = ($result['late_count'] / $total) * 100;
        $result['partial_percentage'] = ($result['partial_count'] / $total) * 100;
        $result['overall_attendance_rate'] = (($result['present_count'] + $result['late_count'] + $result['partial_count']) / $total) * 100;

        return $result;
    }

    /**
     * Find students with frequent absences (risk indicator).
     */
    public function findFrequentAbsentees(int $absenceThreshold = 3): array
    {
        return $this->createQueryBuilder('ar')
            ->select([
                'st.id as student_id',
                'st.firstName',
                'st.lastName',
                'st.email',
                'COUNT(ar.id) as absence_count',
            ])
            ->leftJoin('ar.student', 'st')
            ->where('ar.status = :absent')
            ->setParameter('absent', AttendanceRecord::STATUS_ABSENT)
            ->groupBy('st.id', 'st.firstName', 'st.lastName', 'st.email')
            ->having('COUNT(ar.id) >= :threshold')
            ->setParameter('threshold', $absenceThreshold)
            ->orderBy('COUNT(ar.id)', 'DESC')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * Find recent absentees (for follow-up).
     */
    public function findRecentAbsentees(int $days = 7): array
    {
        $threshold = new DateTime('-' . $days . ' days');

        return $this->createQueryBuilder('ar')
            ->leftJoin('ar.student', 's')
            ->leftJoin('ar.session', 'sess')
            ->where('ar.status = :absent')
            ->andWhere('sess.startDate >= :threshold')
            ->setParameter('absent', AttendanceRecord::STATUS_ABSENT)
            ->setParameter('threshold', $threshold)
            ->orderBy('sess.startDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get attendance trends over time.
     */
    public function getAttendanceTrends(int $days = 30): array
    {
        $startDate = new DateTime('-' . $days . ' days');

        return $this->createQueryBuilder('ar')
            ->select([
                'DATE(s.startDate) as session_date',
                'COUNT(ar.id) as total_records',
                'SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) as present_count',
                '(SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) * 100.0 / COUNT(ar.id)) as attendance_rate',
            ])
            ->leftJoin('ar.session', 's')
            ->where('s.startDate >= :startDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('present_statuses', [
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_PARTIAL,
            ])
            ->groupBy('session_date')
            ->orderBy('session_date', 'ASC')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * Export attendance data for Qualiopi compliance.
     */
    public function getQualiopi12AttendanceData(): array
    {
        return $this->createQueryBuilder('ar')
            ->select([
                'ar.id',
                's.firstName as student_first_name',
                's.lastName as student_last_name',
                's.email as student_email',
                'sess.name as session_name',
                'f.title as formation_title',
                'sess.startDate',
                'sess.endDate',
                'ar.status',
                'ar.participationScore',
                'ar.excused',
                'ar.absenceReason',
                'ar.minutesLate',
                'ar.minutesEarlyDeparture',
                'ar.recordedAt',
                'ar.recordedBy',
            ])
            ->leftJoin('ar.student', 's')
            ->leftJoin('ar.session', 'sess')
            ->leftJoin('sess.formation', 'f')
            ->orderBy('sess.startDate', 'DESC')
            ->addOrderBy('s.lastName', 'ASC')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * Find students needing attendance intervention.
     */
    public function findStudentsNeedingAttendanceIntervention(): array
    {
        // Students with poor attendance or frequent recent absences
        return $this->createQueryBuilder('ar')
            ->select([
                'st.id as student_id',
                'st.firstName',
                'st.lastName',
                'st.email',
                'COUNT(ar.id) as total_sessions',
                'SUM(CASE WHEN ar.status = :absent THEN 1 ELSE 0 END) as total_absences',
                'SUM(CASE WHEN ar.status = :absent AND sess.startDate >= :recent_threshold THEN 1 ELSE 0 END) as recent_absences',
                '(SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) * 100.0 / COUNT(ar.id)) as attendance_rate',
            ])
            ->leftJoin('ar.student', 'st')
            ->leftJoin('ar.session', 'sess')
            ->setParameter('absent', AttendanceRecord::STATUS_ABSENT)
            ->setParameter('recent_threshold', new DateTime('-14 days'))
            ->setParameter('present_statuses', [
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_PARTIAL,
            ])
            ->groupBy('st.id', 'st.firstName', 'st.lastName', 'st.email')
            ->having('(SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) * 100.0 / COUNT(ar.id)) < 70 OR SUM(CASE WHEN ar.status = :absent AND sess.startDate >= :recent_threshold THEN 1 ELSE 0 END) >= 2')
            ->orderBy('(SUM(CASE WHEN ar.status IN (:present_statuses) THEN 1 ELSE 0 END) * 100.0 / COUNT(ar.id))', 'ASC')
            ->addOrderBy('SUM(CASE WHEN ar.status = :absent AND sess.startDate >= :recent_threshold THEN 1 ELSE 0 END)', 'DESC')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * Get participation score statistics.
     */
    public function getParticipationStats(): array
    {
        return $this->createQueryBuilder('ar')
            ->select([
                'COUNT(ar.id) as total_records',
                'AVG(ar.participationScore) as average_score',
                'MIN(ar.participationScore) as min_score',
                'MAX(ar.participationScore) as max_score',
                'SUM(CASE WHEN ar.participationScore >= 8 THEN 1 ELSE 0 END) as excellent_count',
                'SUM(CASE WHEN ar.participationScore >= 6 AND ar.participationScore < 8 THEN 1 ELSE 0 END) as good_count',
                'SUM(CASE WHEN ar.participationScore >= 4 AND ar.participationScore < 6 THEN 1 ELSE 0 END) as average_count',
                'SUM(CASE WHEN ar.participationScore < 4 THEN 1 ELSE 0 END) as poor_count',
            ])
            ->where('ar.status IN (:present_statuses)')
            ->setParameter('present_statuses', [
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_PARTIAL,
            ])
            ->getQuery()
            ->getSingleResult()
        ;
    }

    /**
     * Create advanced query builder for filtering.
     */
    public function createAdvancedFilterQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('ar')
            ->leftJoin('ar.student', 's')
            ->leftJoin('ar.session', 'sess')
            ->leftJoin('sess.formation', 'f')
        ;
    }

    /**
     * Save entity.
     */
    public function save(AttendanceRecord $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove entity.
     */
    public function remove(AttendanceRecord $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
