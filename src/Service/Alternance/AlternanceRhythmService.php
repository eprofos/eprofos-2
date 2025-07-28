<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Entity\User\Student;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Service for managing alternance rhythms and patterns.
 *
 * Handles different alternance rhythms (1 week/1 week, 2 days/3 days, etc.)
 * and provides logic for generating and validating rhythm patterns.
 */
class AlternanceRhythmService
{
    public const RHYTHM_PATTERNS = [
        '1_week_1_week' => [
            'name' => '1 semaine / 1 semaine',
            'description' => 'Alternance d\'une semaine au centre et une semaine en entreprise',
            'center_weeks' => 1,
            'company_weeks' => 1,
            'total_cycle' => 2,
        ],
        '2_weeks_2_weeks' => [
            'name' => '2 semaines / 2 semaines',
            'description' => 'Alternance de deux semaines au centre et deux semaines en entreprise',
            'center_weeks' => 2,
            'company_weeks' => 2,
            'total_cycle' => 4,
        ],
        '3_weeks_1_week' => [
            'name' => '3 semaines / 1 semaine',
            'description' => 'Trois semaines au centre puis une semaine en entreprise',
            'center_weeks' => 3,
            'company_weeks' => 1,
            'total_cycle' => 4,
        ],
        '2_days_3_days' => [
            'name' => '2 jours / 3 jours',
            'description' => 'Deux jours au centre et trois jours en entreprise par semaine',
            'center_days' => 2,
            'company_days' => 3,
            'weekly_pattern' => true,
        ],
        '3_days_2_days' => [
            'name' => '3 jours / 2 jours',
            'description' => 'Trois jours au centre et deux jours en entreprise par semaine',
            'center_days' => 3,
            'company_days' => 2,
            'weekly_pattern' => true,
        ],
        'custom' => [
            'name' => 'Rythme personnalisé',
            'description' => 'Rythme défini spécifiquement pour ce contrat',
            'custom' => true,
        ],
    ];

    public function __construct(
        private AlternanceCalendarService $calendarService,
        private LoggerInterface $logger,
    ) {}

    /**
     * Get all available rhythm patterns.
     */
    public function getAvailableRhythms(): array
    {
        return self::RHYTHM_PATTERNS;
    }

    /**
     * Get rhythm pattern by key.
     */
    public function getRhythmPattern(string $rhythmKey): ?array
    {
        return self::RHYTHM_PATTERNS[$rhythmKey] ?? null;
    }

    /**
     * Generate calendar pattern based on rhythm for a contract.
     */
    public function generateRhythmPattern(
        AlternanceContract $contract,
        string $rhythmKey,
        array $customConfig = [],
    ): array {
        $rhythm = $this->getRhythmPattern($rhythmKey);
        if (!$rhythm) {
            throw new InvalidArgumentException("Rythme non reconnu: {$rhythmKey}");
        }

        $startDate = $contract->getStartDate();
        $endDate = $contract->getEndDate();

        if (!$startDate || !$endDate) {
            throw new InvalidArgumentException('Le contrat doit avoir des dates de début et fin définies');
        }

        if ($rhythmKey === 'custom') {
            return $this->generateCustomPattern($startDate, $endDate, $customConfig);
        }

        if (isset($rhythm['weekly_pattern']) && $rhythm['weekly_pattern']) {
            return $this->generateWeeklyPattern($startDate, $endDate, $rhythm);
        }

        return $this->generateWeeklyBlockPattern($startDate, $endDate, $rhythm);
    }

    /**
     * Validate rhythm configuration.
     */
    public function validateRhythm(string $rhythmKey, array $config = []): array
    {
        $errors = [];
        $rhythm = $this->getRhythmPattern($rhythmKey);

        if (!$rhythm) {
            $errors[] = "Rythme non reconnu: {$rhythmKey}";

            return $errors;
        }

        if ($rhythmKey === 'custom') {
            if (!isset($config['pattern']) || !is_array($config['pattern'])) {
                $errors[] = 'Le rythme personnalisé doit inclure un pattern';
            } else {
                $pattern = $config['pattern'];
                foreach ($pattern as $index => $location) {
                    if (!in_array($location, ['center', 'company'], true)) {
                        $errors[] = "Position {$index} du pattern: la localisation doit être 'center' ou 'company'";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Apply rhythm to a contract by creating calendar entries.
     */
    public function applyRhythmToContract(
        AlternanceContract $contract,
        string $rhythmKey,
        array $customConfig = [],
        ?string $createdBy = null,
    ): int {
        $student = $contract->getStudent();
        $pattern = $this->generateRhythmPattern($contract, $rhythmKey, $customConfig);

        $createdCount = 0;
        foreach ($pattern as $entry) {
            try {
                // Check if entry already exists
                $existing = $this->calendarService->findByStudentWeekYear(
                    $student,
                    $entry['week'],
                    $entry['year'],
                );

                if (!$existing) {
                    $calendar = $this->calendarService->createCalendarEntry(
                        $student,
                        $contract,
                        $entry['week'],
                        $entry['year'],
                        $entry['location'],
                        $createdBy,
                    );

                    // Add rhythm-specific data
                    $rhythmData = array_diff_key($entry, array_flip(['week', 'year', 'location']));
                    if (!empty($rhythmData)) {
                        $calendar->setNotes('Rythme: ' . json_encode($rhythmData));
                    }

                    $createdCount++;
                }
            } catch (Exception $e) {
                $this->logger->error('Error creating calendar entry', [
                    'student_id' => $student->getId(),
                    'week' => $entry['week'],
                    'year' => $entry['year'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Rhythm applied to contract', [
            'contract_id' => $contract->getId(),
            'rhythm_key' => $rhythmKey,
            'created_entries' => $createdCount,
            'total_pattern_entries' => count($pattern),
        ]);

        return $createdCount;
    }

    /**
     * Analyze actual rhythm from existing calendar entries.
     */
    public function analyzeActualRhythm(
        Student $student,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
    ): array {
        $calendar = $this->calendarService->getStudentCalendar($student, $startDate, $endDate);

        if (empty($calendar)) {
            return ['pattern' => 'none', 'analysis' => 'Aucune donnée de calendrier'];
        }

        $locations = array_map(static fn ($entry) => $entry->getLocation(), $calendar);

        // Detect pattern
        $detectedPattern = $this->detectRhythmPattern($locations);

        // Calculate statistics
        $centerCount = count(array_filter($locations, static fn ($loc) => $loc === 'center'));
        $companyCount = count($locations) - $centerCount;

        return [
            'detected_pattern' => $detectedPattern,
            'total_weeks' => count($locations),
            'center_weeks' => $centerCount,
            'company_weeks' => $companyCount,
            'center_percentage' => count($locations) > 0 ? round(($centerCount / count($locations)) * 100, 1) : 0,
            'company_percentage' => count($locations) > 0 ? round(($companyCount / count($locations)) * 100, 1) : 0,
            'raw_pattern' => $locations,
        ];
    }

    /**
     * Get rhythm recommendations based on contract and formation type.
     */
    public function getRhythmRecommendations(AlternanceContract $contract): array
    {
        $recommendations = [];

        // Default recommendations
        $recommendations[] = [
            'rhythm_key' => '1_week_1_week',
            'score' => 80,
            'reason' => 'Rythme équilibré adapté à la plupart des formations',
        ];

        $recommendations[] = [
            'rhythm_key' => '2_weeks_2_weeks',
            'score' => 70,
            'reason' => 'Permet une meilleure immersion dans chaque environnement',
        ];

        // Analyze contract specifics
        $contractDuration = $this->calculateContractDurationInWeeks($contract);

        if ($contractDuration && $contractDuration <= 26) { // 6 months or less
            $recommendations[] = [
                'rhythm_key' => '3_weeks_1_week',
                'score' => 85,
                'reason' => 'Formation courte : privilégier le temps au centre',
            ];
        }

        // Sort by score
        usort($recommendations, static fn ($a, $b) => $b['score'] - $a['score']);

        return $recommendations;
    }

    /**
     * Generate pattern for weekly rhythms (e.g., 2 days center / 3 days company per week).
     */
    private function generateWeeklyPattern(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $rhythm,
    ): array {
        $pattern = [];
        $currentDate = new DateTime($startDate->format('Y-m-d'));
        $contractEndDate = new DateTime($endDate->format('Y-m-d'));

        while ($currentDate <= $contractEndDate) {
            $week = (int) $currentDate->format('W');
            $year = (int) $currentDate->format('Y');

            // For weekly patterns, we set the dominant location
            // If more days at center, location = 'center', else 'company'
            $centerDays = $rhythm['center_days'] ?? 0;
            $companyDays = $rhythm['company_days'] ?? 0;
            $location = $centerDays >= $companyDays ? 'center' : 'company';

            $pattern[] = [
                'week' => $week,
                'year' => $year,
                'location' => $location,
                'rhythm_type' => 'weekly',
                'center_days' => $centerDays,
                'company_days' => $companyDays,
            ];

            $currentDate->add(new DateInterval('P7D'));
        }

        return $pattern;
    }

    /**
     * Generate pattern for block rhythms (e.g., 1 week center / 1 week company).
     */
    private function generateWeeklyBlockPattern(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $rhythm,
    ): array {
        $pattern = [];
        $currentDate = new DateTime($startDate->format('Y-m-d'));
        $contractEndDate = new DateTime($endDate->format('Y-m-d'));

        $centerWeeks = $rhythm['center_weeks'] ?? 1;
        $companyWeeks = $rhythm['company_weeks'] ?? 1;
        $totalCycle = $rhythm['total_cycle'] ?? 2;

        $weekInCycle = 0;
        $currentLocation = 'center'; // Start with center

        while ($currentDate <= $contractEndDate) {
            $week = (int) $currentDate->format('W');
            $year = (int) $currentDate->format('Y');

            $pattern[] = [
                'week' => $week,
                'year' => $year,
                'location' => $currentLocation,
                'rhythm_type' => 'block',
                'cycle_position' => $weekInCycle + 1,
                'total_cycle' => $totalCycle,
            ];

            $weekInCycle++;

            // Determine next location based on cycle position
            if ($currentLocation === 'center' && $weekInCycle >= $centerWeeks) {
                $currentLocation = 'company';
            } elseif ($currentLocation === 'company' && $weekInCycle >= $centerWeeks + $companyWeeks) {
                $currentLocation = 'center';
                $weekInCycle = 0; // Reset cycle
            }

            $currentDate->add(new DateInterval('P7D'));
        }

        return $pattern;
    }

    /**
     * Generate custom pattern based on user configuration.
     */
    private function generateCustomPattern(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $customConfig,
    ): array {
        $pattern = [];
        $currentDate = new DateTime($startDate->format('Y-m-d'));
        $contractEndDate = new DateTime($endDate->format('Y-m-d'));

        // Custom config should include a pattern array or rules
        $customPattern = $customConfig['pattern'] ?? [];
        $patternLength = count($customPattern);

        if ($patternLength === 0) {
            throw new InvalidArgumentException('Le rythme personnalisé doit inclure un pattern');
        }

        $patternIndex = 0;

        while ($currentDate <= $contractEndDate) {
            $week = (int) $currentDate->format('W');
            $year = (int) $currentDate->format('Y');

            $location = $customPattern[$patternIndex] ?? 'center';

            $pattern[] = [
                'week' => $week,
                'year' => $year,
                'location' => $location,
                'rhythm_type' => 'custom',
                'pattern_index' => $patternIndex,
                'pattern_length' => $patternLength,
            ];

            $patternIndex = ($patternIndex + 1) % $patternLength;
            $currentDate->add(new DateInterval('P7D'));
        }

        return $pattern;
    }

    /**
     * Detect rhythm pattern from location sequence.
     */
    private function detectRhythmPattern(array $locations): string
    {
        if (empty($locations)) {
            return 'unknown';
        }

        $sequence = implode('', array_map(static fn ($loc) => $loc === 'center' ? 'C' : 'E', $locations));

        // Check for common patterns
        if (preg_match('/^(CE)+$/', $sequence)) {
            return '1_week_1_week';
        }

        if (preg_match('/^(CCEE)+$/', $sequence)) {
            return '2_weeks_2_weeks';
        }

        if (preg_match('/^(CCCE)+$/', $sequence)) {
            return '3_weeks_1_week';
        }

        // Check for irregular but recognizable patterns
        $centerCount = substr_count($sequence, 'C');
        $companyCount = substr_count($sequence, 'E');

        if ($centerCount === $companyCount) {
            return 'balanced_custom';
        }

        if ($centerCount > $companyCount) {
            return 'center_heavy';
        }

        return 'company_heavy';
    }

    /**
     * Calculate contract duration in weeks.
     */
    private function calculateContractDurationInWeeks(AlternanceContract $contract): ?int
    {
        $startDate = $contract->getStartDate();
        $endDate = $contract->getEndDate();

        if (!$startDate || !$endDate) {
            return null;
        }

        $diff = $startDate->diff($endDate);

        return (int) ceil($diff->days / 7);
    }
}
