<?php

declare(strict_types=1);

namespace App\Repository\Alternance;

use App\Entity\Alternance\CompanyVisit;
use App\Entity\User\Student;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyVisit>
 */
class CompanyVisitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyVisit::class);
    }

    /**
     * Find visits by student and date range.
     */
    public function findByStudentAndDateRange(Student $student, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('cv')
            ->andWhere('cv.student = :student')
            ->andWhere('cv.visitDate >= :startDate')
            ->andWhere('cv.visitDate <= :endDate')
            ->setParameter('student', $student)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('cv.visitDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find visits requiring follow-up.
     */
    public function findVisitsRequiringFollowUp(): array
    {
        return $this->createQueryBuilder('cv')
            ->andWhere('cv.followUpRequired = :followUp')
            ->setParameter('followUp', true)
            ->orderBy('cv.visitDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get visit statistics for a period.
     */
    public function getVisitStatistics(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('cv')
            ->select('
                COUNT(cv.id) as total_visits,
                COUNT(CASE WHEN cv.followUpRequired = true THEN 1 END) as follow_up_required,
                COUNT(CASE WHEN cv.nextVisitDate IS NOT NULL THEN 1 END) as next_visit_scheduled,
                AVG(cv.overallRating) as avg_overall_rating,
                AVG(cv.workingConditionsRating) as avg_working_conditions,
                AVG(cv.supervisionRating) as avg_supervision,
                AVG(cv.integrationRating) as avg_integration,
                AVG(cv.duration) as avg_duration
            ')
            ->andWhere('cv.visitDate >= :startDate')
            ->andWhere('cv.visitDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total_visits' => (int) $result['total_visits'],
            'follow_up_required' => (int) $result['follow_up_required'],
            'next_visit_scheduled' => (int) $result['next_visit_scheduled'],
            'follow_up_rate' => $result['total_visits'] > 0 ?
                round(($result['follow_up_required'] / $result['total_visits']) * 100, 2) : 0,
            'avg_overall_rating' => $result['avg_overall_rating'] ? round($result['avg_overall_rating'], 2) : null,
            'avg_working_conditions' => $result['avg_working_conditions'] ? round($result['avg_working_conditions'], 2) : null,
            'avg_supervision' => $result['avg_supervision'] ? round($result['avg_supervision'], 2) : null,
            'avg_integration' => $result['avg_integration'] ? round($result['avg_integration'], 2) : null,
            'avg_duration' => $result['avg_duration'] ? round($result['avg_duration']) : null,
        ];
    }

    /**
     * Find visits by visitor (teacher).
     *
     * @param mixed $teacher
     */
    public function findByVisitor($teacher): array
    {
        return $this->createQueryBuilder('cv')
            ->andWhere('cv.visitor = :teacher')
            ->setParameter('teacher', $teacher)
            ->orderBy('cv.visitDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find visits by mentor.
     *
     * @param mixed $mentor
     */
    public function findByMentor($mentor): array
    {
        return $this->createQueryBuilder('cv')
            ->andWhere('cv.mentor = :mentor')
            ->setParameter('mentor', $mentor)
            ->orderBy('cv.visitDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find visits by type.
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('cv')
            ->andWhere('cv.visitType = :type')
            ->setParameter('type', $type)
            ->orderBy('cv.visitDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find recent visits for dashboard.
     */
    public function findRecentVisits(int $limit = 10): array
    {
        return $this->createQueryBuilder('cv')
            ->orderBy('cv.visitDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find visits with low ratings.
     */
    public function findVisitsWithLowRatings(float $threshold = 6.0): array
    {
        return $this->createQueryBuilder('cv')
            ->andWhere('cv.overallRating < :threshold OR cv.workingConditionsRating < :threshold OR cv.supervisionRating < :threshold OR cv.integrationRating < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('cv.visitDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find visits by company (through mentor).
     */
    public function findByCompany(string $companyName): array
    {
        return $this->createQueryBuilder('cv')
            ->join('cv.mentor', 'm')
            ->andWhere('m.companyName LIKE :companyName')
            ->setParameter('companyName', '%' . $companyName . '%')
            ->orderBy('cv.visitDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Count visits by type.
     */
    public function countByType(): array
    {
        $result = $this->createQueryBuilder('cv')
            ->select('cv.visitType, COUNT(cv.id) as count')
            ->groupBy('cv.visitType')
            ->getQuery()
            ->getResult()
        ;

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['visitType']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Find students needing visits.
     */
    public function findStudentsNeedingVisits(int $daysSinceLastVisit = 60): array
    {
        $cutoffDate = new DateTime('-' . $daysSinceLastVisit . ' days');

        return $this->createQueryBuilder('cv')
            ->select('DISTINCT s.id as student_id, s.firstName, s.lastName, MAX(cv.visitDate) as last_visit_date')
            ->join('cv.student', 's')
            ->andWhere('cv.visitDate < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->groupBy('s.id, s.firstName, s.lastName')
            ->orderBy('last_visit_date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find visits with positive outcomes.
     */
    public function findVisitsWithPositiveOutcomes(float $minRating = 7.0): array
    {
        return $this->createQueryBuilder('cv')
            ->andWhere('cv.overallRating >= :minRating')
            ->andWhere('cv.workingConditionsRating >= :minRating')
            ->andWhere('cv.supervisionRating >= :minRating')
            ->andWhere('cv.integrationRating >= :minRating')
            ->setParameter('minRating', $minRating)
            ->orderBy('cv.visitDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find overdue follow-up visits.
     */
    public function findOverdueFollowUps(): array
    {
        return $this->createQueryBuilder('cv')
            ->andWhere('cv.followUpRequired = true')
            ->andWhere('cv.nextVisitDate IS NOT NULL')
            ->andWhere('cv.nextVisitDate < :now')
            ->setParameter('now', new DateTime())
            ->orderBy('cv.nextVisitDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get monthly visit trends.
     */
    public function getMonthlyVisitTrends(int $months = 12): array
    {
        $startDate = new DateTime('-' . $months . ' months');

        return $this->createQueryBuilder('cv')
            ->select('
                YEAR(cv.visitDate) as year,
                MONTH(cv.visitDate) as month,
                COUNT(cv.id) as visit_count,
                AVG(cv.overallRating) as avg_rating
            ')
            ->andWhere('cv.visitDate >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('year, month')
            ->orderBy('year, month', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
