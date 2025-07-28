<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\AlternanceCalendar;
use App\Entity\Alternance\AlternanceContract;
use App\Entity\User\Student;
use App\Repository\Alternance\AlternanceCalendarRepository;
use App\Service\Alternance\AlternanceCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for detecting and resolving calendar conflicts
 * 
 * Handles conflict detection, validation, and automated resolution
 * for alternance planning conflicts.
 */
class AlternanceConflictService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlternanceCalendarRepository $calendarRepository,
        private AlternanceCalendarService $calendarService,
        private LoggerInterface $logger
    ) {}

    /**
     * Detect all types of conflicts for a student
     */
    public function detectAllConflicts(Student $student): array
    {
        $conflicts = [];
        
        // 1. Duplicate week conflicts
        $duplicates = $this->detectDuplicateWeeks($student);
        $conflicts['duplicates'] = $duplicates;
        
        // 2. Missing weeks in contract period
        $missingWeeks = $this->detectMissingWeeks($student);
        $conflicts['missing_weeks'] = $missingWeeks;
        
        // 3. Location conflicts (e.g., impossible transitions)
        $locationConflicts = $this->detectLocationConflicts($student);
        $conflicts['location_conflicts'] = $locationConflicts;
        
        // 4. Holiday conflicts
        $holidayConflicts = $this->detectHolidayConflicts($student);
        $conflicts['holiday_conflicts'] = $holidayConflicts;
        
        // 5. Rhythm inconsistencies
        $rhythmConflicts = $this->detectRhythmInconsistencies($student);
        $conflicts['rhythm_conflicts'] = $rhythmConflicts;
        
        return [
            'student' => $student,
            'conflicts' => $conflicts,
            'total_conflicts' => $this->countTotalConflicts($conflicts),
            'severity' => $this->assessConflictSeverity($conflicts)
        ];
    }

    /**
     * Detect duplicate week entries
     */
    public function detectDuplicateWeeks(Student $student): array
    {
        return $this->calendarRepository->findConflicts($student);
    }

    /**
     * Detect missing weeks in contract periods
     */
    public function detectMissingWeeks(Student $student): array
    {
        $missingWeeks = [];
        
        // Get all contracts for the student
        $contracts = $this->entityManager->getRepository(AlternanceContract::class)
            ->findBy(['student' => $student]);
        
        foreach ($contracts as $contract) {
            $expectedWeeks = $this->calendarRepository->generatePlanningData($contract);
            $actualCalendar = $this->calendarRepository->findByContract($contract);
            
            $actualWeeks = [];
            foreach ($actualCalendar as $entry) {
                $actualWeeks[] = [
                    'week' => $entry->getWeek(),
                    'year' => $entry->getYear()
                ];
            }
            
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
                    $missingWeeks[] = [
                        'contract' => $contract,
                        'week' => $expectedWeek['week'],
                        'year' => $expectedWeek['year'],
                        'start_date' => $expectedWeek['start_date'],
                        'end_date' => $expectedWeek['end_date']
                    ];
                }
            }
        }
        
        return $missingWeeks;
    }

    /**
     * Detect location conflicts (impossible transitions)
     */
    public function detectLocationConflicts(Student $student): array
    {
        $conflicts = [];
        
        // Get all calendar entries for student, ordered by date
        $calendar = $this->entityManager->getRepository(AlternanceCalendar::class)
            ->createQueryBuilder('ac')
            ->where('ac.student = :student')
            ->setParameter('student', $student)
            ->orderBy('ac.year', 'ASC')
            ->addOrderBy('ac.week', 'ASC')
            ->getQuery()
            ->getResult();
        
        for ($i = 1; $i < count($calendar); $i++) {
            $previous = $calendar[$i - 1];
            $current = $calendar[$i];
            
            // Check for week continuity issues
            $previousDate = new \DateTime();
            $previousDate->setISODate($previous->getYear(), $previous->getWeek());
            
            $currentDate = new \DateTime();
            $currentDate->setISODate($current->getYear(), $current->getWeek());
            
            $daysDiff = $currentDate->diff($previousDate)->days;
            
            // If more than 2 weeks gap, might be an issue
            if ($daysDiff > 14) {
                $conflicts[] = [
                    'type' => 'week_gap',
                    'previous_entry' => $previous,
                    'current_entry' => $current,
                    'gap_days' => $daysDiff,
                    'message' => "Écart de {$daysDiff} jours entre les semaines planifiées"
                ];
            }
        }
        
        return $conflicts;
    }

    /**
     * Detect holiday conflicts
     */
    public function detectHolidayConflicts(Student $student): array
    {
        $conflicts = [];
        
        $calendar = $this->entityManager->getRepository(AlternanceCalendar::class)
            ->findBy(['student' => $student]);
        
        foreach ($calendar as $entry) {
            $holidays = $entry->getHolidays();
            if (empty($holidays)) {
                continue;
            }
            
            // Check if location is appropriate for holidays
            foreach ($holidays as $holiday) {
                if ($entry->getLocation() === 'company' && count($holidays) > 1) {
                    $conflicts[] = [
                        'type' => 'holiday_location',
                        'entry' => $entry,
                        'holidays' => $holidays,
                        'message' => 'Semaine avec plusieurs jours fériés prévue en entreprise'
                    ];
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Detect rhythm inconsistencies
     */
    public function detectRhythmInconsistencies(Student $student): array
    {
        $conflicts = [];
        
        // Get contracts and analyze rhythm for each
        $contracts = $this->entityManager->getRepository(AlternanceContract::class)
            ->findBy(['student' => $student]);
        
        foreach ($contracts as $contract) {
            $calendar = $this->calendarRepository->findByContract($contract);
            
            if (count($calendar) < 4) { // Need at least 4 weeks to detect pattern
                continue;
            }
            
            $locations = array_map(fn($entry) => $entry->getLocation(), $calendar);
            $pattern = $this->analyzeRhythmPattern($locations);
            
            if ($pattern['inconsistency_score'] > 0.3) { // 30% inconsistency threshold
                $conflicts[] = [
                    'type' => 'rhythm_inconsistency',
                    'contract' => $contract,
                    'pattern' => $pattern,
                    'message' => "Rythme irrégulier détecté (score d'incohérence: " . 
                               round($pattern['inconsistency_score'] * 100, 1) . "%)"
                ];
            }
        }
        
        return $conflicts;
    }

    /**
     * Analyze rhythm pattern for inconsistencies
     */
    private function analyzeRhythmPattern(array $locations): array
    {
        $totalWeeks = count($locations);
        $transitions = 0;
        $expectedTransitions = 0;
        
        // Count actual transitions
        for ($i = 1; $i < $totalWeeks; $i++) {
            if ($locations[$i] !== $locations[$i - 1]) {
                $transitions++;
            }
        }
        
        // Detect likely intended pattern and calculate expected transitions
        $centerCount = count(array_filter($locations, fn($loc) => $loc === 'center'));
        $companyCount = $totalWeeks - $centerCount;
        
        if (abs($centerCount - $companyCount) <= 1) {
            // Likely alternating pattern (1:1 or similar)
            $expectedTransitions = $totalWeeks - 1;
        } else {
            // Likely block pattern
            $expectedTransitions = min($centerCount, $companyCount) * 2;
        }
        
        $inconsistencyScore = $expectedTransitions > 0 ? 
            abs($transitions - $expectedTransitions) / $expectedTransitions : 0;
        
        return [
            'total_weeks' => $totalWeeks,
            'actual_transitions' => $transitions,
            'expected_transitions' => $expectedTransitions,
            'inconsistency_score' => min($inconsistencyScore, 1.0),
            'center_weeks' => $centerCount,
            'company_weeks' => $companyCount
        ];
    }

    /**
     * Count total conflicts across all types
     */
    private function countTotalConflicts(array $conflicts): int
    {
        $total = 0;
        foreach ($conflicts as $conflictType) {
            if (is_array($conflictType)) {
                $total += count($conflictType);
            }
        }
        return $total;
    }

    /**
     * Assess overall conflict severity
     */
    private function assessConflictSeverity(array $conflicts): string
    {
        $total = $this->countTotalConflicts($conflicts);
        
        if ($total === 0) {
            return 'none';
        } elseif ($total <= 2) {
            return 'low';
        } elseif ($total <= 5) {
            return 'medium';
        } else {
            return 'high';
        }
    }

    /**
     * Auto-resolve conflicts where possible
     */
    public function autoResolveConflicts(Student $student, array $options = []): array
    {
        $resolved = [];
        $failed = [];
        
        $allConflicts = $this->detectAllConflicts($student);
        
        // Resolve duplicate weeks
        foreach ($allConflicts['conflicts']['duplicates'] as $duplicate) {
            try {
                $resolution = $this->resolveDuplicateWeek($duplicate, $options);
                $resolved[] = $resolution;
            } catch (\Exception $e) {
                $failed[] = [
                    'conflict' => $duplicate,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Create missing weeks
        foreach ($allConflicts['conflicts']['missing_weeks'] as $missing) {
            try {
                $resolution = $this->createMissingWeek($missing, $options);
                $resolved[] = $resolution;
            } catch (\Exception $e) {
                $failed[] = [
                    'conflict' => $missing,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->logger->info('Auto-resolved conflicts', [
            'student_id' => $student->getId(),
            'resolved_count' => count($resolved),
            'failed_count' => count($failed)
        ]);
        
        return [
            'resolved' => $resolved,
            'failed' => $failed,
            'total_processed' => count($resolved) + count($failed)
        ];
    }

    /**
     * Resolve duplicate week conflict
     */
    private function resolveDuplicateWeek(array $duplicates, array $options): array
    {
        if (count($duplicates) < 2) {
            throw new \InvalidArgumentException('Au moins 2 entrées dupliquées requises');
        }
        
        // Keep the most recent or most complete entry
        $toKeep = $duplicates[0];
        $toRemove = [];
        
        for ($i = 1; $i < count($duplicates); $i++) {
            $entry = $duplicates[$i];
            
            // Prefer confirmed entries
            if ($entry->isConfirmed() && !$toKeep->isConfirmed()) {
                $toRemove[] = $toKeep;
                $toKeep = $entry;
            } else {
                $toRemove[] = $entry;
            }
        }
        
        // Remove duplicates
        foreach ($toRemove as $entry) {
            $this->calendarService->deleteCalendarEntry($entry);
        }
        
        return [
            'type' => 'duplicate_resolved',
            'kept_entry' => $toKeep,
            'removed_entries' => $toRemove,
            'message' => 'Doublons supprimés, entrée la plus pertinente conservée'
        ];
    }

    /**
     * Create missing week entry
     */
    private function createMissingWeek(array $missing, array $options): array
    {
        $contract = $missing['contract'];
        $week = $missing['week'];
        $year = $missing['year'];
        
        // Determine location based on surrounding weeks or rhythm
        $location = $this->determineMissingWeekLocation($contract, $week, $year);
        
        $calendar = $this->calendarService->createCalendarEntry(
            $contract->getStudent(),
            $contract,
            $week,
            $year,
            $location,
            $options['created_by'] ?? 'auto_resolve'
        );
        
        return [
            'type' => 'missing_week_created',
            'created_entry' => $calendar,
            'message' => "Semaine manquante créée: $week/$year ($location)"
        ];
    }

    /**
     * Determine location for missing week based on pattern
     */
    private function determineMissingWeekLocation(
        $contract,
        int $missingWeek,
        int $missingYear
    ): string {
        $calendar = $this->calendarRepository->findByContract($contract);
        
        if (empty($calendar)) {
            return 'center'; // Default to center
        }
        
        // Sort by week/year
        usort($calendar, function($a, $b) {
            if ($a->getYear() !== $b->getYear()) {
                return $a->getYear() <=> $b->getYear();
            }
            return $a->getWeek() <=> $b->getWeek();
        });
        
        // Find surrounding weeks
        $before = null;
        $after = null;
        
        foreach ($calendar as $entry) {
            if ($entry->getYear() < $missingYear || 
                ($entry->getYear() === $missingYear && $entry->getWeek() < $missingWeek)) {
                $before = $entry;
            } elseif ($entry->getYear() > $missingYear || 
                     ($entry->getYear() === $missingYear && $entry->getWeek() > $missingWeek)) {
                $after = $entry;
                break;
            }
        }
        
        // Determine location based on pattern
        if ($before && $after) {
            // If both neighbors are same location, continue pattern
            if ($before->getLocation() === $after->getLocation()) {
                return $before->getLocation();
            }
            // If different, prefer alternating pattern
            return $before->getLocation() === 'center' ? 'company' : 'center';
        } elseif ($before) {
            // Only previous week available, alternate
            return $before->getLocation() === 'center' ? 'company' : 'center';
        } elseif ($after) {
            // Only next week available, alternate
            return $after->getLocation() === 'center' ? 'company' : 'center';
        }
        
        return 'center'; // Default fallback
    }

    /**
     * Generate conflict resolution report
     */
    public function generateConflictReport(Student $student): array
    {
        $conflicts = $this->detectAllConflicts($student);
        
        $report = [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName(),
                'email' => $student->getEmail()
            ],
            'generated_at' => new \DateTime(),
            'conflicts' => $conflicts,
            'recommendations' => $this->generateRecommendations($conflicts)
        ];
        
        return $report;
    }

    /**
     * Generate recommendations based on conflicts
     */
    private function generateRecommendations(array $conflictData): array
    {
        $recommendations = [];
        $conflicts = $conflictData['conflicts'];
        
        if (!empty($conflicts['duplicates'])) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'resolve_duplicates',
                'message' => 'Supprimer les entrées en double en conservant la plus récente'
            ];
        }
        
        if (!empty($conflicts['missing_weeks'])) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'create_missing',
                'message' => 'Créer les semaines manquantes selon le rythme du contrat'
            ];
        }
        
        if (!empty($conflicts['rhythm_conflicts'])) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'regularize_rhythm',
                'message' => 'Régulariser le rythme d\'alternance selon le pattern défini'
            ];
        }
        
        if (!empty($conflicts['holiday_conflicts'])) {
            $recommendations[] = [
                'priority' => 'low',
                'action' => 'adjust_holidays',
                'message' => 'Ajuster les lieux pendant les semaines à forts jours fériés'
            ];
        }
        
        return $recommendations;
    }
}
