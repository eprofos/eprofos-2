<?php

declare(strict_types=1);

namespace App\Service\Training;

use App\Entity\Training\Chapter;
use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use Psr\Log\LoggerInterface;

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

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Calculate the complete daily schedule for a formation.
     */
    public function calculateFormationSchedule(Formation $formation): array
    {
        $formationId = $formation->getId();
        $formationTitle = $formation->getTitle();

        $this->logger->info('Starting formation schedule calculation', [
            'formation_id' => $formationId,
            'formation_title' => $formationTitle,
            'formation_slug' => $formation->getSlug(),
            'formation_level' => $formation->getLevel(),
            'formation_duration_days' => $formation->getDurationHours() ?? 0,
        ]);

        try {
            $activeModules = $formation->getActiveModules();
            $moduleCount = $activeModules->count();

            $this->logger->debug('Retrieved active modules for formation', [
                'formation_id' => $formationId,
                'active_modules_count' => $moduleCount,
            ]);

            if ($activeModules->isEmpty()) {
                $this->logger->warning('Formation has no active modules', [
                    'formation_id' => $formationId,
                    'formation_title' => $formationTitle,
                ]);

                return [
                    'formation' => $formation,
                    'totalDuration' => 0,
                    'totalDays' => 0,
                    'days' => [],
                    'summary' => $this->createEmptySummary(),
                ];
            }

            // Get all scheduled items (modules, chapters, courses)
            $this->logger->debug('Getting scheduled items from modules', [
                'formation_id' => $formationId,
                'modules_count' => $moduleCount,
            ]);

            $scheduledItems = $this->getScheduledItems($activeModules);
            $itemsCount = count($scheduledItems);

            $this->logger->info('Scheduled items retrieved successfully', [
                'formation_id' => $formationId,
                'total_items' => $itemsCount,
                'items_breakdown' => $this->getItemsBreakdown($scheduledItems),
            ]);

            // Calculate total duration
            $totalDuration = array_sum(array_column($scheduledItems, 'durationMinutes'));

            $this->logger->debug('Total duration calculated', [
                'formation_id' => $formationId,
                'total_duration_minutes' => $totalDuration,
                'total_duration_hours' => round($totalDuration / 60, 2),
                'total_duration_days' => round($totalDuration / (7 * 60), 2),
            ]);

            // Organize into daily schedule
            $this->logger->debug('Organizing items into daily schedule', [
                'formation_id' => $formationId,
                'items_to_schedule' => $itemsCount,
            ]);

            $dailySchedule = $this->organizeIntoDailySchedule($scheduledItems);
            $totalDays = count($dailySchedule);

            $this->logger->info('Daily schedule organized successfully', [
                'formation_id' => $formationId,
                'total_days' => $totalDays,
                'schedule_breakdown' => $this->getScheduleBreakdown($dailySchedule),
            ]);

            // Calculate summary statistics
            $this->logger->debug('Calculating summary statistics', [
                'formation_id' => $formationId,
            ]);

            $summary = $this->calculateSummary($scheduledItems, $dailySchedule);

            $this->logger->info('Formation schedule calculation completed successfully', [
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'total_duration_minutes' => $totalDuration,
                'total_days' => $totalDays,
                'summary' => [
                    'total_items' => $summary['totalItems'],
                    'total_segments' => $summary['totalSegments'],
                    'split_items' => $summary['splitItems'],
                    'total_exercises' => $summary['totalExercises'],
                    'total_qcms' => $summary['totalQcms'],
                ],
            ]);

            return [
                'formation' => $formation,
                'totalDuration' => $totalDuration,
                'totalDays' => $totalDays,
                'days' => $dailySchedule,
                'summary' => $summary,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error calculating formation schedule', [
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return safe fallback data
            return [
                'formation' => $formation,
                'totalDuration' => 0,
                'totalDays' => 0,
                'days' => [],
                'summary' => $this->createEmptySummary(),
                'error' => 'Une erreur est survenue lors du calcul du planning de formation.',
            ];
        }
    }

    /**
     * Format duration in minutes to human readable format.
     */
    public function formatDuration(int $minutes): string
    {
        try {
            $this->logger->debug('Formatting duration', [
                'minutes' => $minutes,
            ]);

            if ($minutes < 60) {
                return $minutes . ' min';
            }

            $hours = (int) ($minutes / 60);
            $remainingMinutes = $minutes % 60;

            if ($remainingMinutes === 0) {
                return $hours . 'h';
            }

            return $hours . 'h ' . $remainingMinutes . 'min';

        } catch (\Exception $e) {
            $this->logger->warning('Error formatting duration', [
                'minutes' => $minutes,
                'error_message' => $e->getMessage(),
            ]);

            return $minutes . ' min'; // Safe fallback
        }
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
        try {
            $originalTitle = $item['title'] ?? 'Unknown';

            if (!$this->isSplitItem($item)) {
                return $originalTitle;
            }

            // Remove the "(partie X)" suffix that was added during segmentation
            $cleanTitle = preg_replace('/\s*\(partie\s+\d+\)$/', '', $originalTitle);

            if ($this->isContinuationSegment($item)) {
                // Check if title already contains "(suite)" to avoid duplication
                if (!preg_match('/\(suite\)/', $cleanTitle)) {
                    return $cleanTitle . ' (suite)';
                }

                return $cleanTitle;
            }

            return $cleanTitle;

        } catch (\Exception $e) {
            $this->logger->warning('Error getting display title', [
                'item_title' => $item['title'] ?? 'Unknown',
                'error_message' => $e->getMessage(),
            ]);

            return $item['title'] ?? 'Unknown'; // Safe fallback
        }
    }

    /**
     * Get all scheduled items from modules in hierarchical order.
     *
     * @param mixed $modules
     */
    private function getScheduledItems($modules): array
    {
        $this->logger->debug('Starting to get scheduled items from modules', [
            'modules_count' => is_countable($modules) ? count($modules) : 'unknown',
        ]);

        try {
            $items = [];
            $moduleIndex = 0;

            foreach ($modules as $module) {
                $moduleId = $module->getId();
                $moduleTitle = $module->getTitle();
                $moduleIndex++;

                $this->logger->debug('Processing module for scheduling', [
                    'module_id' => $moduleId,
                    'module_title' => $moduleTitle,
                    'module_index' => $moduleIndex,
                    'module_order' => $module->getOrderIndex(),
                ]);

                try {
                    // Add module introduction (presentation du module)
                    $items[] = [
                        'type' => 'module',
                        'entity' => $module,
                        'title' => $moduleTitle,
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

                    $this->logger->debug('Module introduction added to schedule', [
                        'module_id' => $moduleId,
                        'module_title' => $moduleTitle,
                    ]);

                    // Add chapters and their courses in order
                    $activeChapters = $module->getActiveChapters();
                    $chaptersCount = $activeChapters->count();

                    $this->logger->debug('Processing chapters for module', [
                        'module_id' => $moduleId,
                        'chapters_count' => $chaptersCount,
                    ]);

                    $chapterIndex = 0;
                    foreach ($activeChapters as $chapter) {
                        $chapterId = $chapter->getId();
                        $chapterTitle = $chapter->getTitle();
                        $chapterIndex++;

                        $this->logger->debug('Processing chapter for scheduling', [
                            'module_id' => $moduleId,
                            'chapter_id' => $chapterId,
                            'chapter_title' => $chapterTitle,
                            'chapter_index' => $chapterIndex,
                            'chapter_order' => $chapter->getOrderIndex(),
                        ]);

                        try {
                            // Add chapter introduction (presentation du chapitre)
                            $items[] = [
                                'type' => 'chapter',
                                'entity' => $chapter,
                                'title' => $chapterTitle,
                                'description' => $chapter->getDescription(),
                                'durationMinutes' => 15, // 15 minutes pour présentation du chapitre
                                'learningObjectives' => $chapter->getLearningObjectives(),
                                'teachingMethods' => $chapter->getTeachingMethods(),
                                'assessmentMethods' => $chapter->getAssessmentMethods(),
                                'prerequisites' => $chapter->getPrerequisites(),
                                'orderIndex' => $chapter->getOrderIndex(),
                                'moduleTitle' => $moduleTitle,
                                'hasSubItems' => !$chapter->getActiveCourses()->isEmpty(),
                                'originalDuration' => 15, // Durée originale pour le suivi
                                'isSegment' => false,
                            ];

                            $this->logger->debug('Chapter introduction added to schedule', [
                                'module_id' => $moduleId,
                                'chapter_id' => $chapterId,
                                'chapter_title' => $chapterTitle,
                            ]);

                            // Add all courses for this chapter
                            $activeCourses = $chapter->getActiveCourses();
                            $coursesCount = $activeCourses->count();

                            $this->logger->debug('Processing courses for chapter', [
                                'module_id' => $moduleId,
                                'chapter_id' => $chapterId,
                                'courses_count' => $coursesCount,
                            ]);

                            $courseIndex = 0;
                            foreach ($activeCourses as $course) {
                                $courseId = $course->getId();
                                $courseTitle = $course->getTitle();
                                $courseDuration = $course->getDurationMinutes();
                                $courseIndex++;

                                $this->logger->debug('Processing course for scheduling', [
                                    'module_id' => $moduleId,
                                    'chapter_id' => $chapterId,
                                    'course_id' => $courseId,
                                    'course_title' => $courseTitle,
                                    'course_duration_minutes' => $courseDuration,
                                    'course_index' => $courseIndex,
                                    'course_order' => $course->getOrderIndex(),
                                ]);

                                $exerciseCount = $course->getActiveExercises()->count();
                                $qcmCount = $course->getActiveQcms()->count();

                                $items[] = [
                                    'type' => 'course',
                                    'entity' => $course,
                                    'title' => $courseTitle,
                                    'description' => $course->getDescription(),
                                    'durationMinutes' => $courseDuration,
                                    'learningObjectives' => $course->getLearningObjectives(),
                                    'teachingMethods' => $course->getTeachingMethods(),
                                    'assessmentMethods' => $course->getAssessmentMethods(),
                                    'prerequisites' => $course->getPrerequisites(),
                                    'courseType' => $course->getType(),
                                    'orderIndex' => $course->getOrderIndex(),
                                    'chapterTitle' => $chapterTitle,
                                    'moduleTitle' => $moduleTitle,
                                    'hasSubItems' => false,
                                    'exerciseCount' => $exerciseCount,
                                    'qcmCount' => $qcmCount,
                                    'originalDuration' => $courseDuration, // Durée originale pour le suivi
                                    'isSegment' => false,
                                ];

                                $this->logger->debug('Course added to schedule', [
                                    'module_id' => $moduleId,
                                    'chapter_id' => $chapterId,
                                    'course_id' => $courseId,
                                    'course_title' => $courseTitle,
                                    'course_duration_minutes' => $courseDuration,
                                    'exercise_count' => $exerciseCount,
                                    'qcm_count' => $qcmCount,
                                ]);
                            }

                        } catch (\Exception $e) {
                            $this->logger->error('Error processing chapter for scheduling', [
                                'module_id' => $moduleId,
                                'chapter_id' => $chapterId,
                                'chapter_title' => $chapterTitle,
                                'error_message' => $e->getMessage(),
                                'error_file' => $e->getFile(),
                                'error_line' => $e->getLine(),
                            ]);
                            // Continue processing other chapters
                            continue;
                        }
                    }

                } catch (\Exception $e) {
                    $this->logger->error('Error processing module for scheduling', [
                        'module_id' => $moduleId,
                        'module_title' => $moduleTitle,
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);
                    // Continue processing other modules
                    continue;
                }
            }

            $this->logger->info('Scheduled items retrieval completed', [
                'total_items' => count($items),
                'items_breakdown' => $this->getItemsBreakdown($items),
            ]);

            return $items;

        } catch (\Exception $e) {
            $this->logger->error('Critical error while getting scheduled items', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return empty array as fallback
            return [];
        }
    }

    /**
     * Organize scheduled items into daily schedule with smart item splitting.
     */
    private function organizeIntoDailySchedule(array $items): array
    {
        $this->logger->debug('Starting daily schedule organization', [
            'total_items' => count($items),
            'morning_duration' => self::MORNING_DURATION,
            'afternoon_duration' => self::AFTERNOON_DURATION,
            'daily_duration' => self::DAILY_DURATION,
        ]);

        try {
            $days = [];
            $currentDay = 1;
            $currentSession = 'morning';
            $currentSessionTime = 0;
            $totalItemsProcessed = 0;
            $totalSegmentsCreated = 0;
            $splitItemsCount = 0;

            // Initialize first day
            $this->initializeDay($days, $currentDay);

            $this->logger->debug('First day initialized', [
                'current_day' => $currentDay,
                'current_session' => $currentSession,
            ]);

            foreach ($items as $itemIndex => $item) {
                $itemTitle = $item['title'] ?? 'Unknown';
                $itemType = $item['type'] ?? 'unknown';
                $remainingDuration = $item['durationMinutes'];

                $this->logger->debug('Processing item for daily schedule', [
                    'item_index' => $itemIndex,
                    'item_type' => $itemType,
                    'item_title' => $itemTitle,
                    'original_duration' => $remainingDuration,
                    'current_day' => $currentDay,
                    'current_session' => $currentSession,
                    'current_session_time' => $currentSessionTime,
                ]);

                // Skip items with 0 duration
                if ($remainingDuration <= 0) {
                    $this->logger->debug('Skipping item with zero duration', [
                        'item_index' => $itemIndex,
                        'item_title' => $itemTitle,
                        'item_type' => $itemType,
                    ]);
                    continue;
                }

                // Split item across sessions/days if needed
                $segmentNumber = 1;
                $itemWasSplit = false;

                while ($remainingDuration > 0) {
                    // Calculate available time in current session
                    $availableTime = $this->getAvailableSessionTime($currentSession, $currentSessionTime);

                    $this->logger->debug('Checking available session time', [
                        'item_title' => $itemTitle,
                        'segment_number' => $segmentNumber,
                        'remaining_duration' => $remainingDuration,
                        'available_time' => $availableTime,
                        'current_session' => $currentSession,
                        'current_session_time' => $currentSessionTime,
                    ]);

                    // If no time available in current session, move to next
                    if ($availableTime <= 0) {
                        $this->logger->debug('No time available in current session, moving to next', [
                            'item_title' => $itemTitle,
                            'current_day' => $currentDay,
                            'current_session' => $currentSession,
                        ]);

                        [$currentDay, $currentSession, $currentSessionTime] = $this->moveToNextSession(
                            $days,
                            $currentDay,
                            $currentSession,
                        );

                        $this->logger->debug('Moved to next session', [
                            'new_day' => $currentDay,
                            'new_session' => $currentSession,
                            'new_session_time' => $currentSessionTime,
                        ]);

                        continue;
                    }

                    // Calculate duration for this segment
                    $segmentDuration = min($remainingDuration, $availableTime);
                    $willBeSplit = $remainingDuration > $availableTime;

                    if ($willBeSplit) {
                        $itemWasSplit = true;
                    }

                    $this->logger->debug('Creating item segment', [
                        'item_title' => $itemTitle,
                        'segment_number' => $segmentNumber,
                        'segment_duration' => $segmentDuration,
                        'will_be_split' => $willBeSplit,
                        'remaining_after_segment' => $remainingDuration - $segmentDuration,
                    ]);

                    // Create item segment
                    $itemSegment = $this->createItemSegment(
                        $item,
                        $segmentDuration,
                        $segmentNumber,
                        $willBeSplit,
                    );

                    // Add segment to current session
                    $days[$currentDay][$currentSession]['items'][] = $itemSegment;
                    $days[$currentDay][$currentSession]['duration'] += $segmentDuration;
                    $days[$currentDay]['totalDuration'] += $segmentDuration;

                    $this->logger->debug('Segment added to session', [
                        'item_title' => $itemTitle,
                        'segment_number' => $segmentNumber,
                        'day' => $currentDay,
                        'session' => $currentSession,
                        'segment_duration' => $segmentDuration,
                        'session_total_duration' => $days[$currentDay][$currentSession]['duration'],
                        'day_total_duration' => $days[$currentDay]['totalDuration'],
                    ]);

                    // Update tracking variables
                    $currentSessionTime += $segmentDuration;
                    $remainingDuration -= $segmentDuration;
                    $segmentNumber++;
                    $totalSegmentsCreated++;

                    // If segment fills the session, move to next session
                    if ($currentSessionTime >= $this->getSessionDuration($currentSession)) {
                        $this->logger->debug('Session filled, moving to next session', [
                            'filled_session' => $currentSession,
                            'session_duration' => $currentSessionTime,
                            'max_session_duration' => $this->getSessionDuration($currentSession),
                        ]);

                        [$currentDay, $currentSession, $currentSessionTime] = $this->moveToNextSession(
                            $days,
                            $currentDay,
                            $currentSession,
                        );
                    }
                }

                if ($itemWasSplit) {
                    $splitItemsCount++;
                    $this->logger->info('Item was split across sessions', [
                        'item_title' => $itemTitle,
                        'item_type' => $itemType,
                        'total_segments' => $segmentNumber - 1,
                        'original_duration' => $item['durationMinutes'],
                    ]);
                }

                $totalItemsProcessed++;
            }

            $this->logger->info('Daily schedule organization completed', [
                'total_days' => count($days),
                'total_items_processed' => $totalItemsProcessed,
                'total_segments_created' => $totalSegmentsCreated,
                'split_items_count' => $splitItemsCount,
                'schedule_breakdown' => $this->getScheduleBreakdown($days),
            ]);

            return $days;

        } catch (\Exception $e) {
            $this->logger->error('Error organizing daily schedule', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return empty schedule as fallback
            return [];
        }
    }

    /**
     * Initialize a new day in the schedule.
     */
    private function initializeDay(array &$days, int $dayNumber): void
    {
        try {
            $this->logger->debug('Initializing new day in schedule', [
                'day_number' => $dayNumber,
            ]);

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

            $this->logger->debug('Day initialized successfully', [
                'day_number' => $dayNumber,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error initializing day in schedule', [
                'day_number' => $dayNumber,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
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
        try {
            $this->logger->debug('Moving to next session', [
                'current_day' => $currentDay,
                'current_session' => $currentSession,
            ]);

            if ($currentSession === 'morning') {
                // Move to afternoon of same day
                $this->logger->debug('Moving from morning to afternoon', [
                    'day' => $currentDay,
                ]);
                return [$currentDay, 'afternoon', 0];
            }

            // Move to morning of next day
            $nextDay = $currentDay + 1;
            $this->logger->debug('Moving to next day', [
                'current_day' => $currentDay,
                'next_day' => $nextDay,
            ]);

            $this->initializeDay($days, $nextDay);

            $this->logger->debug('Successfully moved to next session', [
                'new_day' => $nextDay,
                'new_session' => 'morning',
            ]);

            return [$nextDay, 'morning', 0];

        } catch (\Exception $e) {
            $this->logger->error('Error moving to next session', [
                'current_day' => $currentDay,
                'current_session' => $currentSession,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a segment of an item for scheduling.
     */
    private function createItemSegment(array $item, int $duration, int $segmentNumber, bool $isSplit): array
    {
        try {
            $itemTitle = $item['title'] ?? 'Unknown';
            $itemType = $item['type'] ?? 'unknown';

            $this->logger->debug('Creating item segment', [
                'item_title' => $itemTitle,
                'item_type' => $itemType,
                'segment_duration' => $duration,
                'segment_number' => $segmentNumber,
                'is_split' => $isSplit,
                'original_duration' => $item['durationMinutes'] ?? 0,
            ]);

            $segment = $item;
            $segment['durationMinutes'] = $duration;
            $segment['isSegment'] = $isSplit;
            $segment['segmentNumber'] = $segmentNumber;

            if ($isSplit) {
                // Modify title to indicate it's a continuation
                $originalTitle = $item['title'];
                $segment['title'] = $originalTitle . ' (partie ' . $segmentNumber . ')';
                $segment['isContinuation'] = $segmentNumber > 1;

                $this->logger->debug('Item segment split across sessions', [
                    'original_title' => $originalTitle,
                    'segment_title' => $segment['title'],
                    'is_continuation' => $segment['isContinuation'],
                ]);
            }

            $this->logger->debug('Item segment created successfully', [
                'item_title' => $itemTitle,
                'segment_title' => $segment['title'],
                'segment_duration' => $duration,
                'is_split' => $isSplit,
            ]);

            return $segment;

        } catch (\Exception $e) {
            $this->logger->error('Error creating item segment', [
                'item_title' => $item['title'] ?? 'Unknown',
                'duration' => $duration,
                'segment_number' => $segmentNumber,
                'is_split' => $isSplit,
                'error_message' => $e->getMessage(),
            ]);

            // Return basic segment as fallback
            $segment = $item;
            $segment['durationMinutes'] = $duration;
            $segment['isSegment'] = false;
            $segment['segmentNumber'] = 1;

            return $segment;
        }
    }

    /**
     * Calculate summary statistics.
     */
    private function calculateSummary(array $items, array $dailySchedule): array
    {
        $this->logger->debug('Starting summary statistics calculation', [
            'total_items' => count($items),
            'total_days' => count($dailySchedule),
        ]);

        try {
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
                try {
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

                        $this->logger->debug('Processed item for summary', [
                            'item_id' => $itemId,
                            'item_type' => $type,
                            'item_title' => $item['title'] ?? 'Unknown',
                            'original_duration' => $item['originalDuration'],
                        ]);
                    }

                } catch (\Exception $e) {
                    $this->logger->warning('Error processing item for summary', [
                        'item_title' => $item['title'] ?? 'Unknown',
                        'error_message' => $e->getMessage(),
                    ]);
                    // Continue with next item
                    continue;
                }
            }

            // Count segments and split items from daily schedule
            foreach ($dailySchedule as $dayNumber => $day) {
                try {
                    $this->logger->debug('Processing day for summary', [
                        'day_number' => $dayNumber,
                        'day_total_duration' => $day['totalDuration'] ?? 0,
                    ]);

                    foreach (['morning', 'afternoon'] as $session) {
                        if (!empty($day[$session]['items'])) {
                            if ($session === 'morning') {
                                $summary['totalMorningSessions']++;
                            } else {
                                $summary['totalAfternoonSessions']++;
                            }

                            foreach ($day[$session]['items'] as $item) {
                                try {
                                    $summary['totalSegments']++;
                                    $summary['segmentsByType'][$item['type']]++;

                                    if ($this->isSplitItem($item)) {
                                        $itemId = $this->getItemId($item);
                                        if (!isset($processedItems[$itemId . '_split'])) {
                                            $summary['splitItems']++;
                                            $processedItems[$itemId . '_split'] = true;

                                            $this->logger->debug('Found split item', [
                                                'item_id' => $itemId,
                                                'item_title' => $item['title'] ?? 'Unknown',
                                                'segment_number' => $item['segmentNumber'] ?? 1,
                                            ]);
                                        }
                                    }

                                } catch (\Exception $e) {
                                    $this->logger->warning('Error processing segment for summary', [
                                        'day_number' => $dayNumber,
                                        'session' => $session,
                                        'item_title' => $item['title'] ?? 'Unknown',
                                        'error_message' => $e->getMessage(),
                                    ]);
                                    // Continue with next segment
                                    continue;
                                }
                            }
                        }
                    }

                } catch (\Exception $e) {
                    $this->logger->warning('Error processing day for summary', [
                        'day_number' => $dayNumber,
                        'error_message' => $e->getMessage(),
                    ]);
                    // Continue with next day
                    continue;
                }
            }

            // Calculate average day duration
            if ($summary['totalDays'] > 0) {
                $summary['averageDayDuration'] = $summary['totalDuration'] / $summary['totalDays'];
            }

            $this->logger->info('Summary statistics calculated successfully', [
                'total_items' => $summary['totalItems'],
                'total_segments' => $summary['totalSegments'],
                'split_items' => $summary['splitItems'],
                'total_duration' => $summary['totalDuration'],
                'total_days' => $summary['totalDays'],
                'average_day_duration' => $summary['averageDayDuration'],
                'total_exercises' => $summary['totalExercises'],
                'total_qcms' => $summary['totalQcms'],
            ]);

            return $summary;

        } catch (\Exception $e) {
            $this->logger->error('Critical error calculating summary statistics', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return safe fallback summary
            return $this->createEmptySummary();
        }
    }

    /**
     * Get unique identifier for an item.
     */
    private function getItemId(array $item): string
    {
        try {
            $entity = $item['entity'] ?? null;
            if ($entity && method_exists($entity, 'getId')) {
                $entityId = $entity->getId();
                $itemType = $item['type'] ?? 'unknown';
                return $itemType . '_' . $entityId;
            }

            // Fallback to title-based ID
            $itemTitle = $item['title'] ?? 'unknown';
            $itemType = $item['type'] ?? 'unknown';
            $fallbackId = $itemType . '_' . md5($itemTitle);

            $this->logger->debug('Using fallback ID for item', [
                'item_title' => $itemTitle,
                'item_type' => $itemType,
                'fallback_id' => $fallbackId,
            ]);

            return $fallbackId;

        } catch (\Exception $e) {
            $this->logger->warning('Error generating item ID', [
                'item_title' => $item['title'] ?? 'Unknown',
                'item_type' => $item['type'] ?? 'unknown',
                'error_message' => $e->getMessage(),
            ]);

            // Ultimate fallback
            return 'unknown_' . md5(serialize($item));
        }
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

    /**
     * Get breakdown of items by type for logging.
     */
    private function getItemsBreakdown(array $items): array
    {
        try {
            $breakdown = [
                'module' => 0,
                'chapter' => 0,
                'course' => 0,
                'total_duration' => 0,
                'average_duration' => 0,
            ];

            $totalDuration = 0;
            foreach ($items as $item) {
                $type = $item['type'] ?? 'unknown';
                $duration = $item['durationMinutes'] ?? 0;

                if (isset($breakdown[$type])) {
                    $breakdown[$type]++;
                }

                $totalDuration += $duration;
            }

            $breakdown['total_duration'] = $totalDuration;
            $breakdown['average_duration'] = count($items) > 0 ? round($totalDuration / count($items), 2) : 0;

            return $breakdown;

        } catch (\Exception $e) {
            $this->logger->warning('Error calculating items breakdown', [
                'error_message' => $e->getMessage(),
            ]);

            return [
                'module' => 0,
                'chapter' => 0,
                'course' => 0,
                'total_duration' => 0,
                'average_duration' => 0,
                'error' => true,
            ];
        }
    }

    /**
     * Get breakdown of daily schedule for logging.
     */
    private function getScheduleBreakdown(array $dailySchedule): array
    {
        try {
            $breakdown = [
                'total_days' => count($dailySchedule),
                'morning_sessions' => 0,
                'afternoon_sessions' => 0,
                'total_segments' => 0,
                'average_day_duration' => 0,
                'min_day_duration' => null,
                'max_day_duration' => null,
                'sessions_details' => [],
            ];

            $totalDuration = 0;
            $dayDurations = [];

            foreach ($dailySchedule as $dayNumber => $day) {
                $dayDuration = $day['totalDuration'] ?? 0;
                $totalDuration += $dayDuration;
                $dayDurations[] = $dayDuration;

                $sessionDetails = [
                    'day' => $dayNumber,
                    'morning_duration' => $day['morning']['duration'] ?? 0,
                    'afternoon_duration' => $day['afternoon']['duration'] ?? 0,
                    'morning_items' => count($day['morning']['items'] ?? []),
                    'afternoon_items' => count($day['afternoon']['items'] ?? []),
                    'total_duration' => $dayDuration,
                ];

                $breakdown['sessions_details'][] = $sessionDetails;

                if (!empty($day['morning']['items'])) {
                    $breakdown['morning_sessions']++;
                    $breakdown['total_segments'] += count($day['morning']['items']);
                }

                if (!empty($day['afternoon']['items'])) {
                    $breakdown['afternoon_sessions']++;
                    $breakdown['total_segments'] += count($day['afternoon']['items']);
                }
            }

            if (count($dayDurations) > 0) {
                $breakdown['average_day_duration'] = round($totalDuration / count($dayDurations), 2);
                $breakdown['min_day_duration'] = min($dayDurations);
                $breakdown['max_day_duration'] = max($dayDurations);
            }

            return $breakdown;

        } catch (\Exception $e) {
            $this->logger->warning('Error calculating schedule breakdown', [
                'error_message' => $e->getMessage(),
            ]);

            return [
                'total_days' => 0,
                'morning_sessions' => 0,
                'afternoon_sessions' => 0,
                'total_segments' => 0,
                'average_day_duration' => 0,
                'error' => true,
            ];
        }
    }
}
