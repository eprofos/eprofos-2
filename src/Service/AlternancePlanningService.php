<?php

namespace App\Service;

use App\Entity\Alternance\AlternanceCalendar;
use App\Entity\Alternance\AlternanceContract;
use App\Entity\User\Student;
use App\Service\AlternanceCalendarService;
use App\Service\AlternanceRhythmService;
use App\Repository\Alternance\AlternanceCalendarRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for automated planning generation and management
 * 
 * Handles automatic generation of planning based on contracts and rhythms,
 * manages constraints, holidays, and planning optimization.
 */
class AlternancePlanningService
{
    private const FRENCH_HOLIDAYS = [
        'new_year' => ['month' => 1, 'day' => 1, 'name' => 'Jour de l\'An'],
        'labor_day' => ['month' => 5, 'day' => 1, 'name' => 'Fête du Travail'],
        'victory_day' => ['month' => 5, 'day' => 8, 'name' => 'Fête de la Victoire'],
        'bastille_day' => ['month' => 7, 'day' => 14, 'name' => 'Fête Nationale'],
        'assumption' => ['month' => 8, 'day' => 15, 'name' => 'Assomption'],
        'all_saints' => ['month' => 11, 'day' => 1, 'name' => 'Toussaint'],
        'armistice' => ['month' => 11, 'day' => 11, 'name' => 'Armistice'],
        'christmas' => ['month' => 12, 'day' => 25, 'name' => 'Noël'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlternanceCalendarService $calendarService,
        private AlternanceRhythmService $rhythmService,
        private AlternanceCalendarRepository $calendarRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Generate complete planning for a contract
     */
    public function generateContractPlanning(
        AlternanceContract $contract,
        string $rhythmKey,
        array $options = []
    ): array {
        $student = $contract->getStudent();
        $startDate = $contract->getStartDate();
        $endDate = $contract->getEndDate();

        if (!$startDate || !$endDate) {
            throw new \InvalidArgumentException('Le contrat doit avoir des dates de début et fin');
        }

        // Get rhythm pattern
        $pattern = $this->rhythmService->generateRhythmPattern($contract, $rhythmKey, $options['custom_config'] ?? []);
        
        // Apply constraints (holidays, company closures, etc.)
        $constrainedPattern = $this->applyConstraints($pattern, $options);
        
        // Generate calendar entries
        $result = $this->createCalendarEntries($student, $contract, $constrainedPattern, $options['created_by'] ?? null);
        
        $this->logger->info('Contract planning generated', [
            'contract_id' => $contract->getId(),
            'student_id' => $student->getId(),
            'rhythm_key' => $rhythmKey,
            'total_weeks' => count($pattern),
            'created_entries' => $result['created'],
            'skipped_entries' => $result['skipped']
        ]);

        return $result;
    }

    /**
     * Apply constraints to a planning pattern
     */
    private function applyConstraints(array $pattern, array $options): array
    {
        $constrainedPattern = [];
        
        foreach ($pattern as $entry) {
            $week = $entry['week'];
            $year = $entry['year'];
            $location = $entry['location'];
            
            // Check for holidays
            $holidays = $this->getHolidaysForWeek($week, $year);
            if (!empty($holidays)) {
                $entry['holidays'] = $holidays;
                
                // Adjust location if needed during holiday weeks
                if (isset($options['holiday_adjustment']) && $options['holiday_adjustment']) {
                    $location = $this->adjustLocationForHolidays($location, $holidays);
                    $entry['location'] = $location;
                    $entry['adjusted_for_holidays'] = true;
                }
            }
            
            // Check for company closures
            if (isset($options['company_closures'])) {
                $closures = $this->checkCompanyClosures($week, $year, $options['company_closures']);
                if (!empty($closures) && $location === 'company') {
                    $entry['location'] = 'center';
                    $entry['company_closures'] = $closures;
                    $entry['adjusted_for_closure'] = true;
                }
            }
            
            // Check for center closures (vacations)
            if (isset($options['center_closures'])) {
                $closures = $this->checkCenterClosures($week, $year, $options['center_closures']);
                if (!empty($closures) && $location === 'center') {
                    $entry['location'] = 'company';
                    $entry['center_closures'] = $closures;
                    $entry['adjusted_for_closure'] = true;
                }
            }
            
            $constrainedPattern[] = $entry;
        }
        
        return $constrainedPattern;
    }

    /**
     * Get French holidays for a specific week
     */
    private function getHolidaysForWeek(int $week, int $year): array
    {
        $holidays = [];
        
        // Calculate week date range
        $monday = new \DateTime();
        $monday->setISODate($year, $week);
        $friday = clone $monday;
        $friday->add(new \DateInterval('P4D'));
        
        // Check fixed holidays
        foreach (self::FRENCH_HOLIDAYS as $key => $holiday) {
            $holidayDate = new \DateTime("$year-{$holiday['month']}-{$holiday['day']}");
            if ($holidayDate >= $monday && $holidayDate <= $friday) {
                $holidays[] = [
                    'key' => $key,
                    'name' => $holiday['name'],
                    'date' => $holidayDate->format('Y-m-d'),
                    'type' => 'fixed'
                ];
            }
        }
        
        // Add variable holidays (Easter-based)
        $easterHolidays = $this->getEasterHolidays($year);
        foreach ($easterHolidays as $holiday) {
            $holidayDate = new \DateTime($holiday['date']);
            if ($holidayDate >= $monday && $holidayDate <= $friday) {
                $holidays[] = $holiday;
            }
        }
        
        return $holidays;
    }

    /**
     * Get Easter-based holidays for a year
     */
    private function getEasterHolidays(int $year): array
    {
        $easter = new \DateTime("@" . easter_date($year));
        
        $holidays = [];
        
        // Easter Monday
        $easterMonday = clone $easter;
        $easterMonday->add(new \DateInterval('P1D'));
        $holidays[] = [
            'key' => 'easter_monday',
            'name' => 'Lundi de Pâques',
            'date' => $easterMonday->format('Y-m-d'),
            'type' => 'variable'
        ];
        
        // Ascension Day (39 days after Easter)
        $ascension = clone $easter;
        $ascension->add(new \DateInterval('P39D'));
        $holidays[] = [
            'key' => 'ascension',
            'name' => 'Ascension',
            'date' => $ascension->format('Y-m-d'),
            'type' => 'variable'
        ];
        
        // Whit Monday (50 days after Easter)
        $whitMonday = clone $easter;
        $whitMonday->add(new \DateInterval('P50D'));
        $holidays[] = [
            'key' => 'whit_monday',
            'name' => 'Lundi de Pentecôte',
            'date' => $whitMonday->format('Y-m-d'),
            'type' => 'variable'
        ];
        
        return $holidays;
    }

    /**
     * Adjust location based on holidays
     */
    private function adjustLocationForHolidays(string $location, array $holidays): string
    {
        // If multiple holidays in the week, prefer center (better supervision)
        if (count($holidays) >= 2) {
            return 'center';
        }
        
        // Keep original location for single holidays
        return $location;
    }

    /**
     * Check for company closures
     */
    private function checkCompanyClosures(int $week, int $year, array $companyClosures): array
    {
        $closures = [];
        
        foreach ($companyClosures as $closure) {
            $startDate = new \DateTime($closure['start_date']);
            $endDate = new \DateTime($closure['end_date']);
            
            $weekStart = new \DateTime();
            $weekStart->setISODate($year, $week);
            $weekEnd = clone $weekStart;
            $weekEnd->add(new \DateInterval('P6D'));
            
            // Check if closure period overlaps with week
            if ($startDate <= $weekEnd && $endDate >= $weekStart) {
                $closures[] = [
                    'name' => $closure['name'],
                    'start_date' => $closure['start_date'],
                    'end_date' => $closure['end_date'],
                    'reason' => $closure['reason'] ?? 'Fermeture entreprise'
                ];
            }
        }
        
        return $closures;
    }

    /**
     * Check for center closures (school holidays)
     */
    private function checkCenterClosures(int $week, int $year, array $centerClosures): array
    {
        $closures = [];
        
        foreach ($centerClosures as $closure) {
            $startDate = new \DateTime($closure['start_date']);
            $endDate = new \DateTime($closure['end_date']);
            
            $weekStart = new \DateTime();
            $weekStart->setISODate($year, $week);
            $weekEnd = clone $weekStart;
            $weekEnd->add(new \DateInterval('P6D'));
            
            // Check if closure period overlaps with week
            if ($startDate <= $weekEnd && $endDate >= $weekStart) {
                $closures[] = [
                    'name' => $closure['name'],
                    'start_date' => $closure['start_date'],
                    'end_date' => $closure['end_date'],
                    'reason' => $closure['reason'] ?? 'Congés scolaires'
                ];
            }
        }
        
        return $closures;
    }

    /**
     * Create calendar entries from pattern
     */
    private function createCalendarEntries(
        Student $student,
        AlternanceContract $contract,
        array $pattern,
        ?string $createdBy = null
    ): array {
        $created = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($pattern as $entry) {
            try {
                // Check if entry already exists
                $existing = $this->calendarService->findByStudentWeekYear(
                    $student,
                    $entry['week'],
                    $entry['year']
                );
                
                if ($existing) {
                    $skipped++;
                    continue;
                }
                
                // Create calendar entry
                $calendar = $this->calendarService->createCalendarEntry(
                    $student,
                    $contract,
                    $entry['week'],
                    $entry['year'],
                    $entry['location'],
                    $createdBy
                );
                
                // Add additional data
                $this->enrichCalendarEntry($calendar, $entry);
                $created++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'week' => $entry['week'],
                    'year' => $entry['year'],
                    'error' => $e->getMessage()
                ];
                
                $this->logger->error('Error creating calendar entry', [
                    'student_id' => $student->getId(),
                    'week' => $entry['week'],
                    'year' => $entry['year'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_pattern' => count($pattern)
        ];
    }

    /**
     * Enrich calendar entry with additional data from pattern
     */
    private function enrichCalendarEntry(AlternanceCalendar $calendar, array $entry): void
    {
        // Add holidays
        if (!empty($entry['holidays'])) {
            $calendar->setHolidays($entry['holidays']);
        }
        
        // Add notes about adjustments
        $notes = [];
        if (isset($entry['adjusted_for_holidays'])) {
            $notes[] = 'Lieu ajusté en raison de jours fériés';
        }
        if (isset($entry['adjusted_for_closure'])) {
            $notes[] = 'Lieu ajusté en raison de fermeture';
        }
        if (isset($entry['company_closures'])) {
            $closureNames = array_column($entry['company_closures'], 'name');
            $notes[] = 'Fermetures entreprise: ' . implode(', ', $closureNames);
        }
        if (isset($entry['center_closures'])) {
            $closureNames = array_column($entry['center_closures'], 'name');
            $notes[] = 'Congés centre: ' . implode(', ', $closureNames);
        }
        
        if (!empty($notes)) {
            $calendar->setNotes(implode(' | ', $notes));
        }
        
        $this->entityManager->flush();
    }

    /**
     * Regenerate planning for a contract
     */
    public function regeneratePlanning(
        AlternanceContract $contract,
        string $rhythmKey,
        array $options = []
    ): array {
        $student = $contract->getStudent();
        
        // Delete existing calendar entries for this contract
        $existingEntries = $this->calendarRepository->findByContract($contract);
        foreach ($existingEntries as $entry) {
            $this->calendarService->deleteCalendarEntry($entry);
        }
        
        $this->logger->info('Existing planning deleted', [
            'contract_id' => $contract->getId(),
            'deleted_entries' => count($existingEntries)
        ]);
        
        // Generate new planning
        return $this->generateContractPlanning($contract, $rhythmKey, $options);
    }

    /**
     * Validate planning against constraints
     */
    public function validatePlanning(AlternanceContract $contract): array
    {
        $student = $contract->getStudent();
        $calendar = $this->calendarRepository->findByContract($contract);
        
        $issues = [];
        
        // Check for missing weeks
        $expectedWeeks = $this->calendarRepository->generatePlanningData($contract);
        $actualWeeks = array_map(fn($entry) => [
            'week' => $entry->getWeek(),
            'year' => $entry->getYear()
        ], $calendar);
        
        foreach ($expectedWeeks as $expectedWeek) {
            $found = false;
            foreach ($actualWeeks as $actualWeek) {
                if ($actualWeek['week'] === $expectedWeek['week'] && 
                    $actualWeek['year'] === $expectedWeek['year']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $issues[] = [
                    'type' => 'missing_week',
                    'message' => "Semaine manquante: {$expectedWeek['week']}/{$expectedWeek['year']}",
                    'week' => $expectedWeek['week'],
                    'year' => $expectedWeek['year']
                ];
            }
        }
        
        // Check for conflicts
        $conflicts = $this->calendarService->detectConflicts($student);
        foreach ($conflicts as $conflict) {
            $issues[] = [
                'type' => 'conflict',
                'message' => "Conflit détecté pour la semaine {$conflict->getWeek()}/{$conflict->getYear()}",
                'week' => $conflict->getWeek(),
                'year' => $conflict->getYear()
            ];
        }
        
        // Check rhythm consistency
        $rhythmAnalysis = $this->rhythmService->analyzeActualRhythm(
            $student,
            $contract->getStartDate(),
            $contract->getEndDate()
        );
        
        if ($rhythmAnalysis['detected_pattern'] === 'unknown') {
            $issues[] = [
                'type' => 'irregular_rhythm',
                'message' => 'Rythme irrégulier détecté',
                'rhythm_data' => $rhythmAnalysis
            ];
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'total_weeks' => count($calendar),
            'rhythm_analysis' => $rhythmAnalysis
        ];
    }

    /**
     * Get planning statistics for a period
     */
    public function getPlanningStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $stats = $this->calendarService->getCalendarStatistics($startDate, $endDate);
        
        // Add more detailed statistics
        $confirmedCount = 0;
        $totalCount = 0;
        
        // Get all calendar entries in the period
        $qb = $this->calendarRepository->createQueryBuilder('ac');
        $startWeek = (int) $startDate->format('W');
        $startYear = (int) $startDate->format('Y');
        $endWeek = (int) $endDate->format('W');
        $endYear = (int) $endDate->format('Y');

        if ($startYear === $endYear) {
            $qb->andWhere('ac.year = :year')
                ->andWhere('ac.week BETWEEN :startWeek AND :endWeek')
                ->setParameter('year', $startYear)
                ->setParameter('startWeek', $startWeek)
                ->setParameter('endWeek', $endWeek);
        } else {
            $qb->andWhere(
                '(ac.year = :startYear AND ac.week >= :startWeek) OR 
                 (ac.year = :endYear AND ac.week <= :endWeek) OR
                 (ac.year > :startYear AND ac.year < :endYear)'
            )
            ->setParameter('startYear', $startYear)
            ->setParameter('endYear', $endYear)
            ->setParameter('startWeek', $startWeek)
            ->setParameter('endWeek', $endWeek);
        }
        
        $calendars = $qb->getQuery()->getResult();
        $totalCount = count($calendars);
        $confirmedCount = count(array_filter($calendars, fn($c) => $c->isConfirmed()));
        
        $withEvaluations = count($this->calendarRepository->findWithEvaluations($startDate, $endDate));
        $withMeetings = count($this->calendarRepository->findWithMeetings($startDate, $endDate));
        
        return array_merge($stats, [
            'confirmed_weeks' => $confirmedCount,
            'unconfirmed_weeks' => $totalCount - $confirmedCount,
            'weeks_with_evaluations' => $withEvaluations,
            'weeks_with_meetings' => $withMeetings,
            'confirmation_rate' => $totalCount > 0 ? round(($confirmedCount / $totalCount) * 100, 1) : 0
        ]);
    }
}
