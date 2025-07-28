<?php

declare(strict_types=1);

namespace App\Repository\Alternance;

use App\Entity\Alternance\AlternanceCalendar;
use App\Entity\Alternance\AlternanceContract;
use App\Entity\User\Student;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for AlternanceCalendar entity.
 *
 * Provides specialized queries for planning and calendar management
 * including period-based searches, conflict detection, and planning data generation.
 */
class AlternanceCalendarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlternanceCalendar::class);
    }

    /**
     * Find calendar entries for a student within a specific date period.
     */
    public function findByStudentAndPeriod(Student $student, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $startWeek = (int) $startDate->format('W');
        $startYear = (int) $startDate->format('Y');
        $endWeek = (int) $endDate->format('W');
        $endYear = (int) $endDate->format('Y');

        $qb = $this->createQueryBuilder('ac')
            ->andWhere('ac.student = :student')
            ->setParameter('student', $student)
        ;

        if ($startYear === $endYear) {
            $qb->andWhere('ac.year = :year')
                ->andWhere('ac.week BETWEEN :startWeek AND :endWeek')
                ->setParameter('year', $startYear)
                ->setParameter('startWeek', $startWeek)
                ->setParameter('endWeek', $endWeek)
            ;
        } else {
            $qb->andWhere(
                '(ac.year = :startYear AND ac.week >= :startWeek) OR 
                 (ac.year = :endYear AND ac.week <= :endWeek) OR
                 (ac.year > :startYear AND ac.year < :endYear)',
            )
                ->setParameter('startYear', $startYear)
                ->setParameter('endYear', $endYear)
                ->setParameter('startWeek', $startWeek)
                ->setParameter('endWeek', $endWeek)
            ;
        }

        return $qb->orderBy('ac.year', 'ASC')
            ->addOrderBy('ac.week', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Detect conflicts for a student (multiple entries for same week/year).
     */
    public function findConflicts(Student $student): array
    {
        return $this->createQueryBuilder('ac1')
            ->select('ac1', 'ac2')
            ->join(
                AlternanceCalendar::class,
                'ac2',
                'WITH',
                'ac1.student = ac2.student AND ac1.week = ac2.week AND ac1.year = ac2.year AND ac1.id != ac2.id',
            )
            ->where('ac1.student = :student')
            ->setParameter('student', $student)
            ->orderBy('ac1.year', 'ASC')
            ->addOrderBy('ac1.week', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find calendar entries by location and specific week.
     */
    public function findByLocationAndWeek(string $location, int $week, int $year): array
    {
        return $this->createQueryBuilder('ac')
            ->join('ac.student', 's')
            ->andWhere('ac.location = :location')
            ->andWhere('ac.week = :week')
            ->andWhere('ac.year = :year')
            ->setParameter('location', $location)
            ->setParameter('week', $week)
            ->setParameter('year', $year)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Generate planning data for a contract (weeks distribution).
     */
    public function generatePlanningData(AlternanceContract $contract): array
    {
        $startDate = $contract->getStartDate();
        $endDate = $contract->getEndDate();

        if (!$startDate || !$endDate) {
            return [];
        }

        $weeks = [];
        $currentDate = new DateTime($startDate->format('Y-m-d'));
        $contractEndDate = new DateTime($endDate->format('Y-m-d'));

        while ($currentDate <= $contractEndDate) {
            $week = (int) $currentDate->format('W');
            $year = (int) $currentDate->format('Y');

            $weekEndDate = new DateTime($currentDate->format('Y-m-d'));
            $weekEndDate->add(new DateInterval('P6D'));

            $weeks[] = [
                'week' => $week,
                'year' => $year,
                'start_date' => new DateTime($currentDate->format('Y-m-d')),
                'end_date' => $weekEndDate,
            ];

            $currentDate->add(new DateInterval('P7D'));
        }

        return $weeks;
    }

    /**
     * Find unconfirmed calendar entries.
     */
    public function findUnconfirmed(): array
    {
        return $this->createQueryBuilder('ac')
            ->join('ac.student', 's')
            ->andWhere('ac.isConfirmed = :confirmed')
            ->setParameter('confirmed', false)
            ->orderBy('ac.year', 'ASC')
            ->addOrderBy('ac.week', 'ASC')
            ->addOrderBy('s.lastName', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find calendar entries for a specific contract.
     */
    public function findByContract(AlternanceContract $contract): array
    {
        return $this->createQueryBuilder('ac')
            ->andWhere('ac.contract = :contract')
            ->setParameter('contract', $contract)
            ->orderBy('ac.year', 'ASC')
            ->addOrderBy('ac.week', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get calendar statistics for a period.
     */
    public function getCalendarStatistics(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $startWeek = (int) $startDate->format('W');
        $startYear = (int) $startDate->format('Y');
        $endWeek = (int) $endDate->format('W');
        $endYear = (int) $endDate->format('Y');

        $qb = $this->createQueryBuilder('ac')
            ->select('ac.location', 'COUNT(ac.id) as count')
            ->groupBy('ac.location')
        ;

        if ($startYear === $endYear) {
            $qb->andWhere('ac.year = :year')
                ->andWhere('ac.week BETWEEN :startWeek AND :endWeek')
                ->setParameter('year', $startYear)
                ->setParameter('startWeek', $startWeek)
                ->setParameter('endWeek', $endWeek)
            ;
        } else {
            $qb->andWhere(
                '(ac.year = :startYear AND ac.week >= :startWeek) OR 
                 (ac.year = :endYear AND ac.week <= :endWeek) OR
                 (ac.year > :startYear AND ac.year < :endYear)',
            )
                ->setParameter('startYear', $startYear)
                ->setParameter('endYear', $endYear)
                ->setParameter('startWeek', $startWeek)
                ->setParameter('endWeek', $endWeek)
            ;
        }

        $results = $qb->getQuery()->getResult();

        $stats = ['center' => 0, 'company' => 0, 'total' => 0];
        foreach ($results as $result) {
            $stats[$result['location']] = (int) $result['count'];
            $stats['total'] += (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Find students at a specific location for a given week.
     */
    public function findStudentsByLocationAndWeek(string $location, int $week, int $year): array
    {
        return $this->createQueryBuilder('ac')
            ->select('s.id', 's.firstName', 's.lastName', 's.email')
            ->join('ac.student', 's')
            ->andWhere('ac.location = :location')
            ->andWhere('ac.week = :week')
            ->andWhere('ac.year = :year')
            ->setParameter('location', $location)
            ->setParameter('week', $week)
            ->setParameter('year', $year)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find calendar entries with evaluations scheduled.
     */
    public function findWithEvaluations(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $startWeek = (int) $startDate->format('W');
        $startYear = (int) $startDate->format('Y');
        $endWeek = (int) $endDate->format('W');
        $endYear = (int) $endDate->format('Y');

        $qb = $this->createQueryBuilder('ac')
            ->join('ac.student', 's')
            ->andWhere('ac.evaluations IS NOT NULL')
            ->andWhere('JSON_LENGTH(ac.evaluations) > 0')
        ;

        if ($startYear === $endYear) {
            $qb->andWhere('ac.year = :year')
                ->andWhere('ac.week BETWEEN :startWeek AND :endWeek')
                ->setParameter('year', $startYear)
                ->setParameter('startWeek', $startWeek)
                ->setParameter('endWeek', $endWeek)
            ;
        } else {
            $qb->andWhere(
                '(ac.year = :startYear AND ac.week >= :startWeek) OR 
                 (ac.year = :endYear AND ac.week <= :endWeek) OR
                 (ac.year > :startYear AND ac.year < :endYear)',
            )
                ->setParameter('startYear', $startYear)
                ->setParameter('endYear', $endYear)
                ->setParameter('startWeek', $startWeek)
                ->setParameter('endWeek', $endWeek)
            ;
        }

        return $qb->orderBy('ac.year', 'ASC')
            ->addOrderBy('ac.week', 'ASC')
            ->addOrderBy('s.lastName', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find calendar entries with meetings scheduled.
     */
    public function findWithMeetings(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $startWeek = (int) $startDate->format('W');
        $startYear = (int) $startDate->format('Y');
        $endWeek = (int) $endDate->format('W');
        $endYear = (int) $endDate->format('Y');

        $qb = $this->createQueryBuilder('ac')
            ->join('ac.student', 's')
            ->andWhere('ac.meetings IS NOT NULL')
            ->andWhere('JSON_LENGTH(ac.meetings) > 0')
        ;

        if ($startYear === $endYear) {
            $qb->andWhere('ac.year = :year')
                ->andWhere('ac.week BETWEEN :startWeek AND :endWeek')
                ->setParameter('year', $startYear)
                ->setParameter('startWeek', $startWeek)
                ->setParameter('endWeek', $endWeek)
            ;
        } else {
            $qb->andWhere(
                '(ac.year = :startYear AND ac.week >= :startWeek) OR 
                 (ac.year = :endYear AND ac.week <= :endWeek) OR
                 (ac.year > :startYear AND ac.year < :endYear)',
            )
                ->setParameter('startYear', $startYear)
                ->setParameter('endYear', $endYear)
                ->setParameter('startWeek', $startWeek)
                ->setParameter('endWeek', $endWeek)
            ;
        }

        return $qb->orderBy('ac.year', 'ASC')
            ->addOrderBy('ac.week', 'ASC')
            ->addOrderBy('s.lastName', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get rhythm analysis for a student (center vs company weeks pattern).
     */
    public function getRhythmAnalysis(Student $student, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $calendar = $this->findByStudentAndPeriod($student, $startDate, $endDate);

        $analysis = [
            'total_weeks' => count($calendar),
            'center_weeks' => 0,
            'company_weeks' => 0,
            'rhythm_pattern' => [],
            'longest_center_streak' => 0,
            'longest_company_streak' => 0,
            'current_streak' => ['location' => null, 'count' => 0],
        ];

        $currentStreakLocation = null;
        $currentStreakCount = 0;
        $maxCenterStreak = 0;
        $maxCompanyStreak = 0;

        foreach ($calendar as $entry) {
            if ($entry->getLocation() === 'center') {
                $analysis['center_weeks']++;
            } else {
                $analysis['company_weeks']++;
            }

            $analysis['rhythm_pattern'][] = $entry->getLocation();

            // Calculate streaks
            if ($currentStreakLocation === $entry->getLocation()) {
                $currentStreakCount++;
            } else {
                if ($currentStreakLocation === 'center') {
                    $maxCenterStreak = max($maxCenterStreak, $currentStreakCount);
                } elseif ($currentStreakLocation === 'company') {
                    $maxCompanyStreak = max($maxCompanyStreak, $currentStreakCount);
                }

                $currentStreakLocation = $entry->getLocation();
                $currentStreakCount = 1;
            }
        }

        // Final streak update
        if ($currentStreakLocation === 'center') {
            $maxCenterStreak = max($maxCenterStreak, $currentStreakCount);
        } elseif ($currentStreakLocation === 'company') {
            $maxCompanyStreak = max($maxCompanyStreak, $currentStreakCount);
        }

        $analysis['longest_center_streak'] = $maxCenterStreak;
        $analysis['longest_company_streak'] = $maxCompanyStreak;
        $analysis['current_streak'] = [
            'location' => $currentStreakLocation,
            'count' => $currentStreakCount,
        ];

        if ($analysis['total_weeks'] > 0) {
            $analysis['center_percentage'] = round(($analysis['center_weeks'] / $analysis['total_weeks']) * 100, 1);
            $analysis['company_percentage'] = round(($analysis['company_weeks'] / $analysis['total_weeks']) * 100, 1);
        } else {
            $analysis['center_percentage'] = 0;
            $analysis['company_percentage'] = 0;
        }

        return $analysis;
    }
}
