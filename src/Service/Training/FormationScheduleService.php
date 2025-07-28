<?php

declare(strict_types=1);

namespace App\Service\Training;

use App\Entity\Training\Chapter;
use App\Entity\Training\Formation;
use App\Entity\Training\Module;

/**
 * Service for calculating and organizing formation schedules.
 *
 * Calculates daily study schedules (morning/afternoon) with all durations
 * and details for formations, modules, chapters, and courses.
 */
class FormationScheduleService
{
    // Daily schedule constants
    private const MORNING_DURATION = 3.5 * 60; // 3.5 hours in minutes (210 minutes)

    private const AFTERNOON_DURATION = 3.5 * 60; // 3.5 hours in minutes (210 minutes)

    private const DAILY_DURATION = self::MORNING_DURATION + self::AFTERNOON_DURATION; // 7 hours in minutes

    // Session labels
    private const MORNING_LABEL = 'Matin';

    private const AFTERNOON_LABEL = 'Après-midi';

    /**
     * Calculate the complete daily schedule for a formation.
     */
    public function calculateFormationSchedule(Formation $formation): array
    {
        $activeModules = $formation->getActiveModules();

        if ($activeModules->isEmpty()) {
            return [
                'formation' => $formation,
                'totalDuration' => 0,
                'totalDays' => 0,
                'days' => [],
                'summary' => $this->createEmptySummary(),
            ];
        }

        // Get all scheduled items (modules, chapters, courses)
        $scheduledItems = $this->getScheduledItems($activeModules);

        // Calculate total duration
        $totalDuration = array_sum(array_column($scheduledItems, 'durationMinutes'));

        // Organize into daily schedule
        $dailySchedule = $this->organizeIntoDailySchedule($scheduledItems);

        // Calculate summary statistics
        $summary = $this->calculateSummary($scheduledItems, $dailySchedule);

        return [
            'formation' => $formation,
            'totalDuration' => $totalDuration,
            'totalDays' => count($dailySchedule),
            'days' => $dailySchedule,
            'summary' => $summary,
        ];
    }

    /**
     * Format duration in minutes to human readable format.
     */
    public function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = (int) ($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours . 'h';
        }

        return $hours . 'h ' . $remainingMinutes . 'min';
    }

    /**
     * Get CSS class for item type.
     */
    public function getItemTypeClass(string $type): string
    {
        return match ($type) {
            'module' => 'bg-primary',
            'chapter' => 'bg-info',
            'course' => 'bg-success',
            default => 'bg-secondary'
        };
    }

    /**
     * Get icon for item type.
     */
    public function getItemTypeIcon(string $type): string
    {
        return match ($type) {
            'module' => 'book',
            'chapter' => 'file-text',
            'course' => 'play-circle',
            default => 'circle'
        };
    }

    /**
     * Get label for item type.
     */
    public function getItemTypeLabel(string $type): string
    {
        return match ($type) {
            'module' => 'Module',
            'chapter' => 'Chapitre',
            'course' => 'Cours',
            default => 'Item'
        };
    }

    /**
     * Check if an item is a continuation segment.
     */
    public function isContinuationSegment(array $item): bool
    {
        return isset($item['isContinuation']) && $item['isContinuation'] === true;
    }

    /**
     * Check if an item is split across sessions.
     */
    public function isSplitItem(array $item): bool
    {
        return isset($item['isSegment']) && $item['isSegment'] === true;
    }

    /**
     * Get the original duration of an item (before splitting).
     */
    public function getOriginalDuration(array $item): int
    {
        return $item['originalDuration'] ?? $item['durationMinutes'];
    }

    /**
     * Get segment information for display.
     */
    public function getSegmentInfo(array $item): ?string
    {
        if (!$this->isSplitItem($item)) {
            return null;
        }

        $segmentNumber = $item['segmentNumber'] ?? 1;

        return "Partie {$segmentNumber}";
    }

    /**
     * Calculate completion percentage for a segment.
     */
    public function getSegmentCompletionPercentage(array $item): float
    {
        if (!$this->isSplitItem($item)) {
            return 100.0;
        }

        $originalDuration = $this->getOriginalDuration($item);
        if ($originalDuration <= 0) {
            return 0.0;
        }

        return round(($item['durationMinutes'] / $originalDuration) * 100, 1);
    }

    /**
     * Get CSS class for continuation segments.
     */
    public function getContinuationClass(array $item): string
    {
        if ($this->isContinuationSegment($item)) {
            return 'continuation-segment';
        }

        return '';
    }

    /**
     * Get display title for an item (handles segments).
     */
    public function getDisplayTitle(array $item): string
    {
        if (!$this->isSplitItem($item)) {
            return $item['title'];
        }

        // Remove the "(partie X)" suffix that was added during segmentation
        $originalTitle = preg_replace('/\s*\(partie\s+\d+\)$/', '', $item['title']);

        if ($this->isContinuationSegment($item)) {
            // Check if title already contains "(suite)" to avoid duplication
            if (!preg_match('/\(suite\)/', $originalTitle)) {
                return $originalTitle . ' (suite)';
            }

            return $originalTitle;
        }

        return $originalTitle;
    }

    /**
     * Get all scheduled items from modules in hierarchical order.
     *
     * @param mixed $modules
     */
    private function getScheduledItems($modules): array
    {
        $items = [];

        foreach ($modules as $module) {
            // Add module introduction (presentation du module)
            $items[] = [
                'type' => 'module',
                'entity' => $module,
                'title' => $module->getTitle(),
                'description' => $module->getDescription(),
                'durationMinutes' => 30, // 30 minutes pour présentation du module
                'learningObjectives' => $module->getLearningObjectives(),
                'teachingMethods' => $module->getTeachingMethods(),
                'evaluationMethods' => $module->getEvaluationMethods(),
                'prerequisites' => $module->getPrerequisites(),
                'orderIndex' => $module->getOrderIndex(),
                'hasSubItems' => !$module->getActiveChapters()->isEmpty(),
                'originalDuration' => 30, // Durée originale pour le suivi
                'isSegment' => false,
            ];

            // Add chapters and their courses in order
            foreach ($module->getActiveChapters() as $chapter) {
                // Add chapter introduction (presentation du chapitre)
                $items[] = [
                    'type' => 'chapter',
                    'entity' => $chapter,
                    'title' => $chapter->getTitle(),
                    'description' => $chapter->getDescription(),
                    'durationMinutes' => 15, // 15 minutes pour présentation du chapitre
                    'learningObjectives' => $chapter->getLearningObjectives(),
                    'teachingMethods' => $chapter->getTeachingMethods(),
                    'assessmentMethods' => $chapter->getAssessmentMethods(),
                    'prerequisites' => $chapter->getPrerequisites(),
                    'orderIndex' => $chapter->getOrderIndex(),
                    'moduleTitle' => $module->getTitle(),
                    'hasSubItems' => !$chapter->getActiveCourses()->isEmpty(),
                    'originalDuration' => 15, // Durée originale pour le suivi
                    'isSegment' => false,
                ];

                // Add all courses for this chapter
                foreach ($chapter->getActiveCourses() as $course) {
                    $courseDuration = $course->getDurationMinutes();
                    $items[] = [
                        'type' => 'course',
                        'entity' => $course,
                        'title' => $course->getTitle(),
                        'description' => $course->getDescription(),
                        'durationMinutes' => $courseDuration,
                        'learningObjectives' => $course->getLearningObjectives(),
                        'teachingMethods' => $course->getTeachingMethods(),
                        'assessmentMethods' => $course->getAssessmentMethods(),
                        'prerequisites' => $course->getPrerequisites(),
                        'courseType' => $course->getType(),
                        'orderIndex' => $course->getOrderIndex(),
                        'chapterTitle' => $chapter->getTitle(),
                        'moduleTitle' => $module->getTitle(),
                        'hasSubItems' => false,
                        'exerciseCount' => $course->getActiveExercises()->count(),
                        'qcmCount' => $course->getActiveQcms()->count(),
                        'originalDuration' => $courseDuration, // Durée originale pour le suivi
                        'isSegment' => false,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Organize scheduled items into daily schedule with smart item splitting.
     */
    private function organizeIntoDailySchedule(array $items): array
    {
        $days = [];
        $currentDay = 1;
        $currentSession = 'morning';
        $currentSessionTime = 0;

        // Initialize first day
        $this->initializeDay($days, $currentDay);

        foreach ($items as $item) {
            $remainingDuration = $item['durationMinutes'];

            // Skip items with 0 duration
            if ($remainingDuration <= 0) {
                continue;
            }

            // Split item across sessions/days if needed
            $segmentNumber = 1;
            while ($remainingDuration > 0) {
                // Calculate available time in current session
                $availableTime = $this->getAvailableSessionTime($currentSession, $currentSessionTime);

                // If no time available in current session, move to next
                if ($availableTime <= 0) {
                    [$currentDay, $currentSession, $currentSessionTime] = $this->moveToNextSession(
                        $days,
                        $currentDay,
                        $currentSession,
                    );

                    continue;
                }

                // Calculate duration for this segment
                $segmentDuration = min($remainingDuration, $availableTime);

                // Create item segment
                $itemSegment = $this->createItemSegment(
                    $item,
                    $segmentDuration,
                    $segmentNumber,
                    $remainingDuration > $availableTime,
                );

                // Add segment to current session
                $days[$currentDay][$currentSession]['items'][] = $itemSegment;
                $days[$currentDay][$currentSession]['duration'] += $segmentDuration;
                $days[$currentDay]['totalDuration'] += $segmentDuration;

                // Update tracking variables
                $currentSessionTime += $segmentDuration;
                $remainingDuration -= $segmentDuration;
                $segmentNumber++;

                // If segment fills the session, move to next session
                if ($currentSessionTime >= $this->getSessionDuration($currentSession)) {
                    [$currentDay, $currentSession, $currentSessionTime] = $this->moveToNextSession(
                        $days,
                        $currentDay,
                        $currentSession,
                    );
                }
            }
        }

        return $days;
    }

    /**
     * Initialize a new day in the schedule.
     */
    private function initializeDay(array &$days, int $dayNumber): void
    {
        $days[$dayNumber] = [
            'dayNumber' => $dayNumber,
            'morning' => [
                'session' => self::MORNING_LABEL,
                'duration' => 0,
                'items' => [],
            ],
            'afternoon' => [
                'session' => self::AFTERNOON_LABEL,
                'duration' => 0,
                'items' => [],
            ],
            'totalDuration' => 0,
        ];
    }

    /**
     * Get available time in current session.
     */
    private function getAvailableSessionTime(string $session, int $currentTime): int
    {
        $sessionDuration = $this->getSessionDuration($session);

        return max(0, $sessionDuration - $currentTime);
    }

    /**
     * Get duration for a session type.
     */
    private function getSessionDuration(string $session): int
    {
        return match ($session) {
            'morning' => self::MORNING_DURATION,
            'afternoon' => self::AFTERNOON_DURATION,
            default => 0
        };
    }

    /**
     * Move to next available session.
     */
    private function moveToNextSession(array &$days, int $currentDay, string $currentSession): array
    {
        if ($currentSession === 'morning') {
            // Move to afternoon of same day
            return [$currentDay, 'afternoon', 0];
        }
        // Move to morning of next day
        $nextDay = $currentDay + 1;
        $this->initializeDay($days, $nextDay);

        return [$nextDay, 'morning', 0];
    }

    /**
     * Create a segment of an item for scheduling.
     */
    private function createItemSegment(array $item, int $duration, int $segmentNumber, bool $isSplit): array
    {
        $segment = $item;
        $segment['durationMinutes'] = $duration;
        $segment['isSegment'] = $isSplit;
        $segment['segmentNumber'] = $segmentNumber;

        if ($isSplit) {
            // Modify title to indicate it's a continuation
            $segment['title'] = $item['title'] . ' (partie ' . $segmentNumber . ')';
            $segment['isContinuation'] = $segmentNumber > 1;
        }

        return $segment;
    }

    /**
     * Calculate summary statistics.
     */
    private function calculateSummary(array $items, array $dailySchedule): array
    {
        $summary = [
            'totalItems' => 0,
            'totalSegments' => 0,
            'splitItems' => 0,
            'itemsByType' => [
                'module' => 0,
                'chapter' => 0,
                'course' => 0,
            ],
            'segmentsByType' => [
                'module' => 0,
                'chapter' => 0,
                'course' => 0,
            ],
            'totalDuration' => 0,
            'averageDayDuration' => 0,
            'totalDays' => count($dailySchedule),
            'totalMorningSessions' => 0,
            'totalAfternoonSessions' => 0,
            'totalExercises' => 0,
            'totalQcms' => 0,
            'durationByType' => [
                'module' => 0,
                'chapter' => 0,
                'course' => 0,
            ],
        ];

        // Count original items (before segmentation)
        $processedItems = [];
        foreach ($items as $item) {
            $type = $item['type'];
            $itemId = $this->getItemId($item);

            if (!isset($processedItems[$itemId])) {
                $summary['totalItems']++;
                $summary['itemsByType'][$type]++;
                $summary['totalDuration'] += $item['originalDuration'];
                $summary['durationByType'][$type] += $item['originalDuration'];

                // Count exercises and QCMs
                if ($type === 'course') {
                    $summary['totalExercises'] += $item['exerciseCount'] ?? 0;
                    $summary['totalQcms'] += $item['qcmCount'] ?? 0;
                }

                $processedItems[$itemId] = true;
            }
        }

        // Count segments and split items from daily schedule
        foreach ($dailySchedule as $day) {
            foreach (['morning', 'afternoon'] as $session) {
                if (!empty($day[$session]['items'])) {
                    if ($session === 'morning') {
                        $summary['totalMorningSessions']++;
                    } else {
                        $summary['totalAfternoonSessions']++;
                    }

                    foreach ($day[$session]['items'] as $item) {
                        $summary['totalSegments']++;
                        $summary['segmentsByType'][$item['type']]++;

                        if ($this->isSplitItem($item)) {
                            $itemId = $this->getItemId($item);
                            if (!isset($processedItems[$itemId . '_split'])) {
                                $summary['splitItems']++;
                                $processedItems[$itemId . '_split'] = true;
                            }
                        }
                    }
                }
            }
        }

        // Calculate average day duration
        if ($summary['totalDays'] > 0) {
            $summary['averageDayDuration'] = $summary['totalDuration'] / $summary['totalDays'];
        }

        return $summary;
    }

    /**
     * Get unique identifier for an item.
     */
    private function getItemId(array $item): string
    {
        $entity = $item['entity'] ?? null;
        if ($entity && method_exists($entity, 'getId')) {
            return $item['type'] . '_' . $entity->getId();
        }

        // Fallback to title-based ID
        return $item['type'] . '_' . md5($item['title']);
    }

    /**
     * Create empty summary for formations without modules.
     */
    private function createEmptySummary(): array
    {
        return [
            'totalItems' => 0,
            'totalSegments' => 0,
            'splitItems' => 0,
            'itemsByType' => [
                'module' => 0,
                'chapter' => 0,
                'course' => 0,
            ],
            'segmentsByType' => [
                'module' => 0,
                'chapter' => 0,
                'course' => 0,
            ],
            'totalDuration' => 0,
            'averageDayDuration' => 0,
            'totalDays' => 0,
            'totalMorningSessions' => 0,
            'totalAfternoonSessions' => 0,
            'totalExercises' => 0,
            'totalQcms' => 0,
            'durationByType' => [
                'module' => 0,
                'chapter' => 0,
                'course' => 0,
            ],
        ];
    }
}
