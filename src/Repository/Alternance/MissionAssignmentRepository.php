<?php

namespace App\Repository\Alternance;

use App\Entity\Alternance\MissionAssignment;
use App\Entity\User\Student;
use App\Entity\User\Mentor;
use App\Entity\Alternance\CompanyMission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for MissionAssignment entity
 * 
 * Provides query methods for mission assignments with filtering,
 * searching, progress tracking, and statistics functionality.
 *
 * @extends ServiceEntityRepository<MissionAssignment>
 */
class MissionAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MissionAssignment::class);
    }

    /**
     * Find active assignments by student
     *
     * @param Student $student
     * @return MissionAssignment[]
     */
    public function findActiveByStudent(Student $student): array
    {
        return $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->addSelect('m')
            ->where('ma.student = :student')
            ->andWhere('ma.status IN (:activeStatuses)')
            ->setParameter('student', $student)
            ->setParameter('activeStatuses', ['planifiee', 'en_cours'])
            ->orderBy('ma.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find completed assignments by student and period
     *
     * @param Student $student
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return MissionAssignment[]
     */
    public function findCompletedByStudentAndPeriod(Student $student, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->addSelect('m')
            ->where('ma.student = :student')
            ->andWhere('ma.status = :completed')
            ->andWhere('ma.endDate BETWEEN :startDate AND :endDate')
            ->setParameter('student', $student)
            ->setParameter('completed', 'terminee')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ma.endDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate completion statistics for a student
     *
     * @param Student $student
     * @return array
     */
    public function calculateCompletionStats(Student $student): array
    {
        $result = $this->createQueryBuilder('ma')
            ->select('
                COUNT(ma.id) as total_assignments,
                SUM(CASE WHEN ma.status = \'terminee\' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN ma.status = \'en_cours\' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN ma.status = \'planifiee\' THEN 1 ELSE 0 END) as planned,
                SUM(CASE WHEN ma.status = \'suspendue\' THEN 1 ELSE 0 END) as suspended,
                AVG(ma.completionRate) as avg_completion_rate,
                AVG(ma.mentorRating) as avg_mentor_rating,
                AVG(ma.studentSatisfaction) as avg_student_satisfaction
            ')
            ->where('ma.student = :student')
            ->setParameter('student', $student)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total_assignments'],
            'completed' => (int) $result['completed'],
            'in_progress' => (int) $result['in_progress'],
            'planned' => (int) $result['planned'],
            'suspended' => (int) $result['suspended'],
            'completion_rate' => round((float) $result['avg_completion_rate'], 2),
            'mentor_rating' => round((float) $result['avg_mentor_rating'], 2),
            'student_satisfaction' => round((float) $result['avg_student_satisfaction'], 2),
        ];
    }

    /**
     * Find assignments by mission
     *
     * @param CompanyMission $mission
     * @param string|null $status
     * @return MissionAssignment[]
     */
    public function findByMission(CompanyMission $mission, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.student', 's')
            ->addSelect('s')
            ->where('ma.mission = :mission')
            ->setParameter('mission', $mission);

        if ($status) {
            $qb->andWhere('ma.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->orderBy('ma.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find assignments by mentor (through mission supervisor)
     *
     * @param Mentor $mentor
     * @param string|null $status
     * @return MissionAssignment[]
     */
    public function findByMentor(Mentor $mentor, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->leftJoin('ma.student', 's')
            ->addSelect('m', 's')
            ->where('m.supervisor = :mentor')
            ->setParameter('mentor', $mentor);

        if ($status) {
            $qb->andWhere('ma.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->orderBy('ma.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find overdue assignments
     *
     * @param Mentor|null $mentor
     * @return MissionAssignment[]
     */
    public function findOverdueAssignments(?Mentor $mentor = null): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->leftJoin('ma.student', 's')
            ->addSelect('m', 's')
            ->where('ma.endDate < :today')
            ->andWhere('ma.status IN (:activeStatuses)')
            ->setParameter('today', new \DateTime())
            ->setParameter('activeStatuses', ['planifiee', 'en_cours']);

        if ($mentor) {
            $qb->andWhere('m.supervisor = :mentor')
               ->setParameter('mentor', $mentor);
        }

        return $qb->orderBy('ma.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find assignments by status
     *
     * @param string $status
     * @param int|null $limit
     * @return MissionAssignment[]
     */
    public function findByStatus(string $status, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->leftJoin('ma.student', 's')
            ->addSelect('m', 's')
            ->where('ma.status = :status')
            ->setParameter('status', $status)
            ->orderBy('ma.lastUpdated', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find assignments requiring feedback
     *
     * @param Mentor|null $mentor
     * @return MissionAssignment[]
     */
    public function findRequiringFeedback(?Mentor $mentor = null): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->leftJoin('ma.student', 's')
            ->addSelect('m', 's')
            ->where('ma.status = :completed')
            ->andWhere('(ma.mentorFeedback IS NULL OR ma.mentorRating IS NULL)')
            ->setParameter('completed', 'terminee');

        if ($mentor) {
            $qb->andWhere('m.supervisor = :mentor')
               ->setParameter('mentor', $mentor);
        }

        return $qb->orderBy('ma.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent assignments (last 30 days)
     *
     * @param Student|null $student
     * @param Mentor|null $mentor
     * @return MissionAssignment[]
     */
    public function findRecentAssignments(?Student $student = null, ?Mentor $mentor = null): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->leftJoin('ma.student', 's')
            ->addSelect('m', 's')
            ->where('ma.createdAt >= :since')
            ->setParameter('since', new \DateTimeImmutable('-30 days'));

        if ($student) {
            $qb->andWhere('ma.student = :student')
               ->setParameter('student', $student);
        }

        if ($mentor) {
            $qb->andWhere('m.supervisor = :mentor')
               ->setParameter('mentor', $mentor);
        }

        return $qb->orderBy('ma.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get assignments statistics by mentor
     *
     * @param Mentor $mentor
     * @return array
     */
    public function getAssignmentStatsByMentor(Mentor $mentor): array
    {
        $result = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->select('
                COUNT(ma.id) as total_assignments,
                SUM(CASE WHEN ma.status = \'planifiee\' THEN 1 ELSE 0 END) as planned,
                SUM(CASE WHEN ma.status = \'en_cours\' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN ma.status = \'terminee\' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN ma.status = \'suspendue\' THEN 1 ELSE 0 END) as suspended,
                AVG(ma.completionRate) as avg_completion_rate,
                AVG(ma.mentorRating) as avg_mentor_rating,
                AVG(ma.studentSatisfaction) as avg_student_satisfaction
            ')
            ->where('m.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total_assignments'],
            'by_status' => [
                'planifiee' => (int) $result['planned'],
                'en_cours' => (int) $result['in_progress'],
                'terminee' => (int) $result['completed'],
                'suspendue' => (int) $result['suspended'],
            ],
            'completion_rate' => round((float) $result['avg_completion_rate'], 2),
            'mentor_rating' => round((float) $result['avg_mentor_rating'], 2),
            'student_satisfaction' => round((float) $result['avg_student_satisfaction'], 2),
        ];
    }

    /**
     * Find assignments with low completion rates
     *
     * @param float $threshold
     * @param Mentor|null $mentor
     * @return MissionAssignment[]
     */
    public function findLowCompletionAssignments(float $threshold = 50.0, ?Mentor $mentor = null): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->leftJoin('ma.student', 's')
            ->addSelect('m', 's')
            ->where('ma.completionRate < :threshold')
            ->andWhere('ma.status IN (:activeStatuses)')
            ->setParameter('threshold', $threshold)
            ->setParameter('activeStatuses', ['en_cours']);

        if ($mentor) {
            $qb->andWhere('m.supervisor = :mentor')
               ->setParameter('mentor', $mentor);
        }

        return $qb->orderBy('ma.completionRate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find student's assignments by complexity progression
     *
     * @param Student $student
     * @return array
     */
    public function findStudentProgressionByComplexity(Student $student): array
    {
        $result = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->select('
                m.complexity,
                COUNT(ma.id) as total,
                SUM(CASE WHEN ma.status = \'terminee\' THEN 1 ELSE 0 END) as completed,
                AVG(ma.completionRate) as avg_completion_rate,
                AVG(ma.mentorRating) as avg_rating
            ')
            ->where('ma.student = :student')
            ->setParameter('student', $student)
            ->groupBy('m.complexity')
            ->orderBy('m.complexity', 'ASC')
            ->getQuery()
            ->getResult();

        $progression = [];
        foreach ($result as $row) {
            $progression[$row['complexity']] = [
                'total' => (int) $row['total'],
                'completed' => (int) $row['completed'],
                'completion_rate' => round((float) $row['avg_completion_rate'], 2),
                'avg_rating' => round((float) $row['avg_rating'], 2),
            ];
        }

        return $progression;
    }

    /**
     * Find assignments by date range
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @param array $filters
     * @return MissionAssignment[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->leftJoin('ma.student', 's')
            ->addSelect('m', 's')
            ->where('ma.startDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if (isset($filters['status']) && $filters['status']) {
            $qb->andWhere('ma.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['mentor']) && $filters['mentor']) {
            $qb->andWhere('m.supervisor = :mentor')
               ->setParameter('mentor', $filters['mentor']);
        }

        if (isset($filters['student']) && $filters['student']) {
            $qb->andWhere('ma.student = :student')
               ->setParameter('student', $filters['student']);
        }

        return $qb->orderBy('ma.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count assignments by month for statistics
     *
     * @param int $year
     * @param Mentor|null $mentor
     * @return array
     */
    public function countAssignmentsByMonth(int $year, ?Mentor $mentor = null): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->select('
                MONTH(ma.startDate) as month,
                COUNT(ma.id) as count
            ')
            ->where('YEAR(ma.startDate) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if ($mentor) {
            $qb->andWhere('m.supervisor = :mentor')
               ->setParameter('mentor', $mentor);
        }

        $result = $qb->getQuery()->getResult();

        // Initialize all months with 0
        $monthlyStats = array_fill(1, 12, 0);
        
        foreach ($result as $row) {
            $monthlyStats[(int) $row['month']] = (int) $row['count'];
        }

        return $monthlyStats;
    }

    /**
     * Create a query builder for assignments with common joins
     *
     * @return QueryBuilder
     */
    public function createAssignmentQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->leftJoin('ma.student', 's')
            ->leftJoin('m.supervisor', 'mentor')
            ->addSelect('m', 's', 'mentor');
    }

    /**
     * Find assignments needing attention (overdue or low progress)
     *
     * @param Mentor|null $mentor
     * @return MissionAssignment[]
     */
    public function findAssignmentsNeedingAttention(?Mentor $mentor = null): array
    {
        $qb = $this->createQueryBuilder('ma')
            ->leftJoin('ma.mission', 'm')
            ->leftJoin('ma.student', 's')
            ->addSelect('m', 's')
            ->where('ma.status IN (:activeStatuses)')
            ->andWhere('(ma.endDate < :today OR (ma.completionRate < 50 AND ma.startDate < :weekAgo))')
            ->setParameter('activeStatuses', ['planifiee', 'en_cours'])
            ->setParameter('today', new \DateTime())
            ->setParameter('weekAgo', new \DateTime('-1 week'));

        if ($mentor) {
            $qb->andWhere('m.supervisor = :mentor')
               ->setParameter('mentor', $mentor);
        }

        return $qb->orderBy('ma.endDate', 'ASC')
            ->addOrderBy('ma.completionRate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get assignments dashboard data for a mentor
     *
     * @param Mentor $mentor
     * @return array
     */
    public function getMentorDashboardData(Mentor $mentor): array
    {
        $stats = $this->getAssignmentStatsByMentor($mentor);
        $overdue = $this->findOverdueAssignments($mentor);
        $needingFeedback = $this->findRequiringFeedback($mentor);
        $needingAttention = $this->findAssignmentsNeedingAttention($mentor);

        return [
            'stats' => $stats,
            'overdue_count' => count($overdue),
            'feedback_needed_count' => count($needingFeedback),
            'attention_needed_count' => count($needingAttention),
            'recent_assignments' => $this->findRecentAssignments(null, $mentor),
        ];
    }

    // Additional methods for Doctrine repository pattern
    public function save(MissionAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MissionAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Count assignments by status
     */
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('ma')
            ->select('COUNT(ma.id)')
            ->where('ma.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
