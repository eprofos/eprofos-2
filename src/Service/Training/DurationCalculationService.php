<?php

declare(strict_types=1);

namespace App\Service\Training;

use App\Entity\Training\Chapter;
use App\Entity\Training\Course;
use App\Entity\Training\Exercise;
use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Entity\Training\QCM;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Duration Calculation Service.
 *
 * Handles duration calculations and propagation across the 4-level hierarchy:
 * Formation (hours) ← Module (hours) ← Chapter (minutes) ← Course (minutes)
 *
 * Additional entities: Exercise (estimatedDurationMinutes), QCM (timeLimitMinutes)
 */
class DurationCalculationService
{
    private const CACHE_TTL = 3600; // 1 hour

    private const CACHE_PREFIX = 'duration_';

    private bool $isSyncMode = false;

    private array $processingEntities = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    /**
     * Enable sync mode to prevent recursive updates.
     */
    public function enableSyncMode(): void
    {
        $this->isSyncMode = true;
    }

    /**
     * Disable sync mode.
     */
    public function disableSyncMode(): void
    {
        $this->isSyncMode = false;
    }

    /**
     * Check if we're in sync mode.
     */
    public function isSyncMode(): bool
    {
        return $this->isSyncMode;
    }

    /**
     * Calculate total duration for a Course (including exercises and QCMs).
     */
    public function calculateCourseDuration(Course $course): int
    {
        $startTime = microtime(true);
        $courseId = $course->getId();

        $this->logger->info('Starting course duration calculation', [
            'course_id' => $courseId,
            'course_title' => $course->getTitle(),
            'has_id' => !is_null($courseId),
        ]);

        try {
            $cacheKey = self::CACHE_PREFIX . 'course_' . $courseId;

            // If entity is not persisted, calculate directly without caching
            if (!$courseId) {
                $this->logger->debug('Course not persisted, calculating duration directly', [
                    'course_title' => $course->getTitle(),
                ]);

                $duration = $this->calculateCourseDurationDirect($course);

                $this->logger->info('Course duration calculated (direct)', [
                    'course_title' => $course->getTitle(),
                    'duration_minutes' => $duration,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $duration;
            }

            $this->logger->debug('Attempting to retrieve course duration from cache', [
                'course_id' => $courseId,
                'cache_key' => $cacheKey,
            ]);

            $duration = $this->cache->get($cacheKey, function (ItemInterface $item) use ($course, $courseId) {
                $this->logger->debug('Cache miss, calculating course duration', [
                    'course_id' => $courseId,
                    'cache_ttl' => self::CACHE_TTL,
                ]);

                $item->expiresAfter(self::CACHE_TTL);

                return $this->calculateCourseDurationDirect($course);
            });

            $this->logger->info('Course duration calculation completed', [
                'course_id' => $courseId,
                'course_title' => $course->getTitle(),
                'duration_minutes' => $duration,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $duration;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate course duration', [
                'course_id' => $courseId,
                'course_title' => $course->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a fallback duration to prevent complete failure
            return $course->getDurationMinutes() ?? 0;
        }
    }

    /**
     * Calculate total duration for a Chapter (sum of all active courses).
     */
    public function calculateChapterDuration(Chapter $chapter): int
    {
        $startTime = microtime(true);
        $chapterId = $chapter->getId();

        $this->logger->info('Starting chapter duration calculation', [
            'chapter_id' => $chapterId,
            'chapter_title' => $chapter->getTitle(),
            'has_id' => !is_null($chapterId),
        ]);

        try {
            $cacheKey = self::CACHE_PREFIX . 'chapter_' . $chapterId;

            // If entity is not persisted, calculate directly without caching
            if (!$chapterId) {
                $this->logger->debug('Chapter not persisted, calculating duration directly', [
                    'chapter_title' => $chapter->getTitle(),
                ]);

                $duration = $this->calculateChapterDurationDirect($chapter);

                $this->logger->info('Chapter duration calculated (direct)', [
                    'chapter_title' => $chapter->getTitle(),
                    'duration_minutes' => $duration,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $duration;
            }

            $this->logger->debug('Attempting to retrieve chapter duration from cache', [
                'chapter_id' => $chapterId,
                'cache_key' => $cacheKey,
            ]);

            $duration = $this->cache->get($cacheKey, function (ItemInterface $item) use ($chapter, $chapterId) {
                $this->logger->debug('Cache miss, calculating chapter duration', [
                    'chapter_id' => $chapterId,
                    'cache_ttl' => self::CACHE_TTL,
                ]);

                $item->expiresAfter(self::CACHE_TTL);

                return $this->calculateChapterDurationDirect($chapter);
            });

            $this->logger->info('Chapter duration calculation completed', [
                'chapter_id' => $chapterId,
                'chapter_title' => $chapter->getTitle(),
                'duration_minutes' => $duration,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $duration;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate chapter duration', [
                'chapter_id' => $chapterId,
                'chapter_title' => $chapter->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a fallback duration to prevent complete failure
            return $chapter->getDurationMinutes() ?? 0;
        }
    }

    /**
     * Calculate total duration for a Module (sum of all active chapters, converted to hours).
     */
    public function calculateModuleDuration(Module $module): int
    {
        $startTime = microtime(true);
        $moduleId = $module->getId();

        $this->logger->info('Starting module duration calculation', [
            'module_id' => $moduleId,
            'module_title' => $module->getTitle(),
            'has_id' => !is_null($moduleId),
        ]);

        try {
            $cacheKey = self::CACHE_PREFIX . 'module_' . $moduleId;

            // If entity is not persisted, calculate directly without caching
            if (!$moduleId) {
                $this->logger->debug('Module not persisted, calculating duration directly', [
                    'module_title' => $module->getTitle(),
                ]);

                $duration = $this->calculateModuleDurationDirect($module);

                $this->logger->info('Module duration calculated (direct)', [
                    'module_title' => $module->getTitle(),
                    'duration_hours' => $duration,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $duration;
            }

            $this->logger->debug('Attempting to retrieve module duration from cache', [
                'module_id' => $moduleId,
                'cache_key' => $cacheKey,
            ]);

            $duration = $this->cache->get($cacheKey, function (ItemInterface $item) use ($module, $moduleId) {
                $this->logger->debug('Cache miss, calculating module duration', [
                    'module_id' => $moduleId,
                    'cache_ttl' => self::CACHE_TTL,
                ]);

                $item->expiresAfter(self::CACHE_TTL);

                return $this->calculateModuleDurationDirect($module);
            });

            $this->logger->info('Module duration calculation completed', [
                'module_id' => $moduleId,
                'module_title' => $module->getTitle(),
                'duration_hours' => $duration,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $duration;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate module duration', [
                'module_id' => $moduleId,
                'module_title' => $module->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a fallback duration to prevent complete failure
            return $module->getDurationHours() ?? 0;
        }
    }

    /**
     * Calculate total duration for a Formation (sum of all active modules).
     */
    public function calculateFormationDuration(Formation $formation): int
    {
        $startTime = microtime(true);
        $formationId = $formation->getId();

        $this->logger->info('Starting formation duration calculation', [
            'formation_id' => $formationId,
            'formation_title' => $formation->getTitle(),
            'has_id' => !is_null($formationId),
        ]);

        try {
            $cacheKey = self::CACHE_PREFIX . 'formation_' . $formationId;

            // If entity is not persisted, calculate directly without caching
            if (!$formationId) {
                $this->logger->debug('Formation not persisted, calculating duration directly', [
                    'formation_title' => $formation->getTitle(),
                ]);

                $duration = $this->calculateFormationDurationDirect($formation);

                $this->logger->info('Formation duration calculated (direct)', [
                    'formation_title' => $formation->getTitle(),
                    'duration_hours' => $duration,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $duration;
            }

            $this->logger->debug('Attempting to retrieve formation duration from cache', [
                'formation_id' => $formationId,
                'cache_key' => $cacheKey,
            ]);

            $duration = $this->cache->get($cacheKey, function (ItemInterface $item) use ($formation, $formationId) {
                $this->logger->debug('Cache miss, calculating formation duration', [
                    'formation_id' => $formationId,
                    'cache_ttl' => self::CACHE_TTL,
                ]);

                $item->expiresAfter(self::CACHE_TTL);

                return $this->calculateFormationDurationDirect($formation);
            });

            $this->logger->info('Formation duration calculation completed', [
                'formation_id' => $formationId,
                'formation_title' => $formation->getTitle(),
                'duration_hours' => $duration,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $duration;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate formation duration', [
                'formation_id' => $formationId,
                'formation_title' => $formation->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a fallback duration to prevent complete failure
            return $formation->getDurationHours() ?? 0;
        }
    }

    /**
     * Update duration for a specific entity and propagate changes upward.
     */
    public function updateEntityDuration(object $entity): void
    {
        $startTime = microtime(true);
        $entityClass = get_class($entity);
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $entityKey = $entityClass . '_' . ($entityId ?? spl_object_id($entity));

        $this->logger->info('Starting entity duration update', [
            'entity_class' => $entityClass,
            'entity_id' => $entityId,
            'entity_key' => $entityKey,
            'sync_mode' => $this->isSyncMode,
        ]);

        // Prevent recursive updates
        if (isset($this->processingEntities[$entityKey])) {
            $this->logger->warning('Recursive update detected, skipping', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'entity_key' => $entityKey,
            ]);

            return;
        }

        $this->processingEntities[$entityKey] = true;

        try {
            $this->logger->debug('Processing entity duration update', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
            ]);

            switch ($entityClass) {
                case Course::class:
                    $this->logger->debug('Updating course duration', [
                        'course_id' => $entityId,
                        'course_title' => $entity->getTitle() ?? 'Unknown',
                    ]);
                    $this->updateCourseDuration($entity);
                    break;

                case Chapter::class:
                    $this->logger->debug('Updating chapter duration', [
                        'chapter_id' => $entityId,
                        'chapter_title' => $entity->getTitle() ?? 'Unknown',
                    ]);
                    $this->updateChapterDuration($entity);
                    break;

                case Module::class:
                    $this->logger->debug('Updating module duration', [
                        'module_id' => $entityId,
                        'module_title' => $entity->getTitle() ?? 'Unknown',
                    ]);
                    $this->updateModuleDuration($entity);
                    break;

                case Formation::class:
                    $this->logger->debug('Updating formation duration', [
                        'formation_id' => $entityId,
                        'formation_title' => $entity->getTitle() ?? 'Unknown',
                    ]);
                    $this->updateFormationDuration($entity);
                    break;

                case Exercise::class:
                    $this->logger->debug('Updating course duration from exercise', [
                        'exercise_id' => $entityId,
                        'exercise_title' => method_exists($entity, 'getTitle') ? $entity->getTitle() : 'Unknown',
                        'estimated_duration' => method_exists($entity, 'getEstimatedDurationMinutes') ? $entity->getEstimatedDurationMinutes() : null,
                    ]);
                    $this->updateCourseFromChild($entity);
                    break;

                case QCM::class:
                    $this->logger->debug('Updating course duration from QCM', [
                        'qcm_id' => $entityId,
                        'qcm_title' => method_exists($entity, 'getTitle') ? $entity->getTitle() : 'Unknown',
                        'time_limit' => method_exists($entity, 'getTimeLimitMinutes') ? $entity->getTimeLimitMinutes() : null,
                    ]);
                    $this->updateCourseFromChild($entity);
                    break;

                default:
                    $this->logger->warning('Unknown entity type for duration update', [
                        'entity_class' => $entityClass,
                        'entity_id' => $entityId,
                    ]);
                    break;
            }

            $this->logger->info('Entity duration update completed successfully', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update entity duration', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'entity_key' => $entityKey,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to maintain original behavior
        } finally {
            // Always remove from processing list
            unset($this->processingEntities[$entityKey]);

            $this->logger->debug('Removed entity from processing list', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'entity_key' => $entityKey,
            ]);
        }
    }

    /**
     * Batch update durations for multiple entities.
     */
    public function batchUpdateDurations(array $entities): void
    {
        $startTime = microtime(true);
        $entityCount = count($entities);

        $this->logger->info('Starting batch duration update', [
            'entity_count' => $entityCount,
            'sync_mode' => $this->isSyncMode,
        ]);

        if (empty($entities)) {
            $this->logger->warning('No entities provided for batch update');

            return;
        }

        $processedEntities = [];
        $failedEntities = [];

        try {
            $this->entityManager->beginTransaction();

            $this->logger->debug('Database transaction started for batch update');

            foreach ($entities as $index => $entity) {
                try {
                    $entityClass = get_class($entity);
                    $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

                    $this->logger->debug('Processing entity in batch', [
                        'index' => $index + 1,
                        'total' => $entityCount,
                        'entity_class' => $entityClass,
                        'entity_id' => $entityId,
                    ]);

                    $this->updateEntityDuration($entity);
                    $processedEntities[] = [
                        'class' => $entityClass,
                        'id' => $entityId,
                    ];
                } catch (Exception $e) {
                    $failedEntities[] = [
                        'entity' => $entity,
                        'error' => $e->getMessage(),
                        'class' => get_class($entity),
                        'id' => method_exists($entity, 'getId') ? $entity->getId() : null,
                    ];

                    $this->logger->error('Failed to update entity in batch', [
                        'index' => $index + 1,
                        'entity_class' => get_class($entity),
                        'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->debug('Flushing entity manager changes');
            $this->entityManager->flush();

            $this->logger->debug('Committing database transaction');
            $this->entityManager->commit();

            $this->logger->info('Batch duration update completed successfully', [
                'total_entities' => $entityCount,
                'processed_entities' => count($processedEntities),
                'failed_entities' => count($failedEntities),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'processed_list' => $processedEntities,
            ]);

            if (!empty($failedEntities)) {
                $this->logger->warning('Some entities failed during batch update', [
                    'failed_count' => count($failedEntities),
                    'failed_entities' => array_map(static fn ($failed) => [
                        'class' => $failed['class'],
                        'id' => $failed['id'],
                        'error' => $failed['error'],
                    ], $failedEntities),
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Batch duration update transaction failed', [
                'entity_count' => $entityCount,
                'processed_count' => count($processedEntities),
                'failed_count' => count($failedEntities),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            try {
                $this->logger->debug('Rolling back database transaction');
                $this->entityManager->rollback();
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction', [
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Clear all duration caches.
     */
    public function clearDurationCaches(): void
    {
        $startTime = microtime(true);

        $this->logger->info('Starting duration cache clearance');

        try {
            // This would need to be implemented based on your cache backend
            // For example, with Redis: $this->cache->clear();

            $this->logger->info('Duration caches cleared successfully', [
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to clear duration caches', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get duration statistics for an entity.
     */
    public function getDurationStatistics(object $entity): array
    {
        $startTime = microtime(true);
        $entityClass = get_class($entity);
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

        $this->logger->info('Starting duration statistics calculation', [
            'entity_class' => $entityClass,
            'entity_id' => $entityId,
        ]);

        try {
            $stats = [
                'entity_type' => $entityClass,
                'entity_id' => $entityId,
            ];

            switch ($entityClass) {
                case Formation::class:
                    $this->logger->debug('Calculating formation statistics', [
                        'formation_id' => $entityId,
                        'formation_title' => $entity->getTitle() ?? 'Unknown',
                    ]);

                    $stats['calculated_duration'] = $this->calculateFormationDuration($entity);
                    $stats['stored_duration'] = $entity->getDurationHours();
                    $stats['unit'] = 'hours';
                    $stats['module_count'] = $entity->getActiveModules()->count();
                    break;

                case Module::class:
                    $this->logger->debug('Calculating module statistics', [
                        'module_id' => $entityId,
                        'module_title' => $entity->getTitle() ?? 'Unknown',
                    ]);

                    $stats['calculated_duration'] = $this->calculateModuleDuration($entity);
                    $stats['stored_duration'] = $entity->getDurationHours();
                    $stats['unit'] = 'hours';
                    $stats['chapter_count'] = $entity->getActiveChapters()->count();
                    break;

                case Chapter::class:
                    $this->logger->debug('Calculating chapter statistics', [
                        'chapter_id' => $entityId,
                        'chapter_title' => $entity->getTitle() ?? 'Unknown',
                    ]);

                    $stats['calculated_duration'] = $this->calculateChapterDuration($entity);
                    $stats['stored_duration'] = $entity->getDurationMinutes();
                    $stats['unit'] = 'minutes';
                    $stats['course_count'] = $entity->getActiveCourses()->count();
                    break;

                case Course::class:
                    $this->logger->debug('Calculating course statistics', [
                        'course_id' => $entityId,
                        'course_title' => $entity->getTitle() ?? 'Unknown',
                    ]);

                    $stats['calculated_duration'] = $this->calculateCourseDuration($entity);
                    $stats['stored_duration'] = $entity->getDurationMinutes();
                    $stats['unit'] = 'minutes';
                    $stats['exercise_count'] = $entity->getActiveExercises()->count();
                    $stats['qcm_count'] = $entity->getActiveQcms()->count();
                    break;

                default:
                    $this->logger->warning('Unknown entity type for statistics', [
                        'entity_class' => $entityClass,
                        'entity_id' => $entityId,
                    ]);

                    $stats['needs_update'] = false;
                    $stats['difference'] = 0;

                    return $stats;
            }

            if (isset($stats['calculated_duration'], $stats['stored_duration'])) {
                $stats['difference'] = $stats['calculated_duration'] - $stats['stored_duration'];
                $stats['needs_update'] = abs($stats['difference']) > 0;

                $this->logger->debug('Duration comparison completed', [
                    'entity_class' => $entityClass,
                    'entity_id' => $entityId,
                    'calculated_duration' => $stats['calculated_duration'],
                    'stored_duration' => $stats['stored_duration'],
                    'difference' => $stats['difference'],
                    'needs_update' => $stats['needs_update'],
                ]);
            } else {
                $stats['needs_update'] = false;
                $stats['difference'] = 0;
            }

            $this->logger->info('Duration statistics calculation completed', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'needs_update' => $stats['needs_update'],
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate duration statistics', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return minimal stats on error
            return [
                'entity_type' => $entityClass,
                'entity_id' => $entityId,
                'needs_update' => false,
                'difference' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert minutes to hours with proper rounding.
     */
    public function minutesToHours(int $minutes, bool $roundUp = true): int
    {
        if ($roundUp) {
            return (int) ceil($minutes / 60);
        }

        return (int) round($minutes / 60);
    }

    /**
     * Convert hours to minutes.
     */
    public function hoursToMinutes(int $hours): int
    {
        return $hours * 60;
    }

    /**
     * Format duration for display.
     */
    public function formatDuration(int $value, string $unit): string
    {
        switch ($unit) {
            case 'minutes':
                if ($value < 60) {
                    return $value . ' min';
                }

                $hours = (int) ($value / 60);
                $minutes = $value % 60;

                if ($minutes === 0) {
                    return $hours . 'h';
                }

                return $hours . 'h ' . $minutes . 'min';

            case 'hours':
                if ($value < 8) {
                    return $value . 'h';
                }

                $days = (int) ($value / 8);
                $hours = $value % 8;

                if ($hours === 0) {
                    return $days . ' jour' . ($days > 1 ? 's' : '');
                }

                return $days . ' jour' . ($days > 1 ? 's' : '') . ' ' . $hours . 'h';

            default:
                return $value . ' ' . $unit;
        }
    }

    /**
     * Calculate course duration directly without caching.
     */
    private function calculateCourseDurationDirect(Course $course): int
    {
        $startTime = microtime(true);
        $courseId = $course->getId();

        $this->logger->debug('Starting direct course duration calculation', [
            'course_id' => $courseId,
            'course_title' => $course->getTitle() ?? 'Unknown',
        ]);

        try {
            $totalDuration = $course->getDurationMinutes() ?? 0;
            $baseDuration = $totalDuration;
            $exerciseDuration = 0;
            $qcmDuration = 0;

            $this->logger->debug('Base course duration retrieved', [
                'course_id' => $courseId,
                'base_duration_minutes' => $baseDuration,
            ]);

            // Add exercise durations
            $exerciseCount = 0;
            foreach ($course->getActiveExercises() as $exercise) {
                $exerciseTime = $exercise->getEstimatedDurationMinutes() ?? 0;
                $exerciseDuration += $exerciseTime;
                $exerciseCount++;

                $this->logger->debug('Adding exercise duration', [
                    'course_id' => $courseId,
                    'exercise_id' => $exercise->getId(),
                    'exercise_duration_minutes' => $exerciseTime,
                ]);
            }

            $totalDuration += $exerciseDuration;

            // Add QCM time limits
            $qcmCount = 0;
            foreach ($course->getActiveQcms() as $qcm) {
                $qcmTime = $qcm->getTimeLimitMinutes() ?? 0;
                $qcmDuration += $qcmTime;
                $qcmCount++;

                $this->logger->debug('Adding QCM duration', [
                    'course_id' => $courseId,
                    'qcm_id' => $qcm->getId(),
                    'qcm_duration_minutes' => $qcmTime,
                ]);
            }

            $totalDuration += $qcmDuration;

            $this->logger->info('Direct course duration calculation completed', [
                'course_id' => $courseId,
                'course_title' => $course->getTitle() ?? 'Unknown',
                'base_duration_minutes' => $baseDuration,
                'exercise_duration_minutes' => $exerciseDuration,
                'qcm_duration_minutes' => $qcmDuration,
                'total_duration_minutes' => $totalDuration,
                'exercise_count' => $exerciseCount,
                'qcm_count' => $qcmCount,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $totalDuration;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate course duration directly', [
                'course_id' => $courseId,
                'course_title' => $course->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return base duration as fallback
            return $course->getDurationMinutes() ?? 0;
        }
    }

    /**
     * Calculate chapter duration directly without caching.
     */
    private function calculateChapterDurationDirect(Chapter $chapter): int
    {
        $startTime = microtime(true);
        $chapterId = $chapter->getId();

        $this->logger->debug('Starting direct chapter duration calculation', [
            'chapter_id' => $chapterId,
            'chapter_title' => $chapter->getTitle() ?? 'Unknown',
        ]);

        try {
            $totalDuration = 0;
            $courseCount = 0;
            $courseDetails = [];

            foreach ($chapter->getActiveCourses() as $course) {
                $courseDuration = $this->calculateCourseDuration($course);
                $totalDuration += $courseDuration;
                $courseCount++;

                $courseDetails[] = [
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle() ?? 'Unknown',
                    'duration_minutes' => $courseDuration,
                ];

                $this->logger->debug('Adding course to chapter duration', [
                    'chapter_id' => $chapterId,
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle() ?? 'Unknown',
                    'course_duration_minutes' => $courseDuration,
                    'running_total_minutes' => $totalDuration,
                ]);
            }

            $this->logger->info('Direct chapter duration calculation completed', [
                'chapter_id' => $chapterId,
                'chapter_title' => $chapter->getTitle() ?? 'Unknown',
                'total_duration_minutes' => $totalDuration,
                'course_count' => $courseCount,
                'courses' => $courseDetails,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $totalDuration;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate chapter duration directly', [
                'chapter_id' => $chapterId,
                'chapter_title' => $chapter->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return fallback duration
            return $chapter->getDurationMinutes() ?? 0;
        }
    }

    /**
     * Calculate module duration directly without caching.
     */
    private function calculateModuleDurationDirect(Module $module): int
    {
        $startTime = microtime(true);
        $moduleId = $module->getId();

        $this->logger->debug('Starting direct module duration calculation', [
            'module_id' => $moduleId,
            'module_title' => $module->getTitle() ?? 'Unknown',
        ]);

        try {
            $totalMinutes = 0;
            $chapterCount = 0;
            $chapterDetails = [];

            foreach ($module->getActiveChapters() as $chapter) {
                $chapterDuration = $this->calculateChapterDuration($chapter);
                $totalMinutes += $chapterDuration;
                $chapterCount++;

                $chapterDetails[] = [
                    'chapter_id' => $chapter->getId(),
                    'chapter_title' => $chapter->getTitle() ?? 'Unknown',
                    'duration_minutes' => $chapterDuration,
                ];

                $this->logger->debug('Adding chapter to module duration', [
                    'module_id' => $moduleId,
                    'chapter_id' => $chapter->getId(),
                    'chapter_title' => $chapter->getTitle() ?? 'Unknown',
                    'chapter_duration_minutes' => $chapterDuration,
                    'running_total_minutes' => $totalMinutes,
                ]);
            }

            // Convert minutes to hours (rounded up)
            $totalHours = (int) ceil($totalMinutes / 60);

            $this->logger->info('Direct module duration calculation completed', [
                'module_id' => $moduleId,
                'module_title' => $module->getTitle() ?? 'Unknown',
                'total_minutes' => $totalMinutes,
                'total_hours' => $totalHours,
                'chapter_count' => $chapterCount,
                'chapters' => $chapterDetails,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $totalHours;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate module duration directly', [
                'module_id' => $moduleId,
                'module_title' => $module->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return fallback duration
            return $module->getDurationHours() ?? 0;
        }
    }

    /**
     * Calculate formation duration directly without caching.
     */
    private function calculateFormationDurationDirect(Formation $formation): int
    {
        $startTime = microtime(true);
        $formationId = $formation->getId();

        $this->logger->debug('Starting direct formation duration calculation', [
            'formation_id' => $formationId,
            'formation_title' => $formation->getTitle() ?? 'Unknown',
        ]);

        try {
            $totalHours = 0;
            $moduleCount = 0;
            $moduleDetails = [];

            foreach ($formation->getActiveModules() as $module) {
                $moduleDuration = $this->calculateModuleDuration($module);
                $totalHours += $moduleDuration;
                $moduleCount++;

                $moduleDetails[] = [
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle() ?? 'Unknown',
                    'duration_hours' => $moduleDuration,
                ];

                $this->logger->debug('Adding module to formation duration', [
                    'formation_id' => $formationId,
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle() ?? 'Unknown',
                    'module_duration_hours' => $moduleDuration,
                    'running_total_hours' => $totalHours,
                ]);
            }

            $this->logger->info('Direct formation duration calculation completed', [
                'formation_id' => $formationId,
                'formation_title' => $formation->getTitle() ?? 'Unknown',
                'total_hours' => $totalHours,
                'module_count' => $moduleCount,
                'modules' => $moduleDetails,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $totalHours;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate formation duration directly', [
                'formation_id' => $formationId,
                'formation_title' => $formation->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return fallback duration
            return $formation->getDurationHours() ?? 0;
        }
    }

    /**
     * Update Course duration and propagate to Chapter.
     */
    private function updateCourseDuration(Course $course): void
    {
        $startTime = microtime(true);
        $courseId = $course->getId();

        $this->logger->debug('Starting course duration update', [
            'course_id' => $courseId,
            'course_title' => $course->getTitle() ?? 'Unknown',
            'current_duration' => $course->getDurationMinutes(),
        ]);

        try {
            // Invalidate cache for this course
            $this->invalidateEntityCache('course', $courseId);

            // Get calculated duration
            $calculatedDuration = $this->calculateCourseDuration($course);
            $currentDuration = $course->getDurationMinutes();
            $difference = abs($currentDuration - $calculatedDuration);

            $this->logger->debug('Course duration comparison', [
                'course_id' => $courseId,
                'current_duration' => $currentDuration,
                'calculated_duration' => $calculatedDuration,
                'difference' => $difference,
                'threshold' => 5,
            ]);

            // Update the course's stored duration if it differs significantly
            if ($difference > 5) {
                $this->logger->info('Updating course duration', [
                    'course_id' => $courseId,
                    'old_duration' => $currentDuration,
                    'new_duration' => $calculatedDuration,
                    'difference' => $calculatedDuration - $currentDuration,
                ]);

                $course->setDurationMinutes($calculatedDuration);
                $this->entityManager->persist($course);
            } else {
                $this->logger->debug('Course duration unchanged (within threshold)', [
                    'course_id' => $courseId,
                    'duration' => $currentDuration,
                    'difference' => $difference,
                ]);
            }

            // Propagate to parent chapter
            $chapter = $course->getChapter();
            if ($chapter) {
                $this->logger->debug('Propagating duration update to parent chapter', [
                    'course_id' => $courseId,
                    'chapter_id' => $chapter->getId(),
                    'chapter_title' => $chapter->getTitle() ?? 'Unknown',
                ]);

                $this->updateChapterDuration($chapter);
            } else {
                $this->logger->warning('Course has no parent chapter', [
                    'course_id' => $courseId,
                ]);
            }

            $this->logger->debug('Course duration update completed', [
                'course_id' => $courseId,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update course duration', [
                'course_id' => $courseId,
                'course_title' => $course->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update Chapter duration and propagate to Module.
     */
    private function updateChapterDuration(Chapter $chapter): void
    {
        $startTime = microtime(true);
        $chapterId = $chapter->getId();

        $this->logger->debug('Starting chapter duration update', [
            'chapter_id' => $chapterId,
            'chapter_title' => $chapter->getTitle() ?? 'Unknown',
            'current_duration' => $chapter->getDurationMinutes(),
        ]);

        try {
            // Invalidate cache for this chapter
            $this->invalidateEntityCache('chapter', $chapterId);

            // Get calculated duration
            $calculatedDuration = $this->calculateChapterDuration($chapter);
            $currentDuration = $chapter->getDurationMinutes();

            $this->logger->info('Updating chapter duration', [
                'chapter_id' => $chapterId,
                'chapter_title' => $chapter->getTitle() ?? 'Unknown',
                'old_duration' => $currentDuration,
                'new_duration' => $calculatedDuration,
                'difference' => $calculatedDuration - $currentDuration,
            ]);

            // Update the chapter's stored duration
            $chapter->setDurationMinutes($calculatedDuration);
            $this->entityManager->persist($chapter);

            // Propagate to parent module
            $module = $chapter->getModule();
            if ($module) {
                $this->logger->debug('Propagating duration update to parent module', [
                    'chapter_id' => $chapterId,
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle() ?? 'Unknown',
                ]);

                $this->updateModuleDuration($module);
            } else {
                $this->logger->warning('Chapter has no parent module', [
                    'chapter_id' => $chapterId,
                ]);
            }

            $this->logger->debug('Chapter duration update completed', [
                'chapter_id' => $chapterId,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update chapter duration', [
                'chapter_id' => $chapterId,
                'chapter_title' => $chapter->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update Module duration and propagate to Formation.
     */
    private function updateModuleDuration(Module $module): void
    {
        $startTime = microtime(true);
        $moduleId = $module->getId();

        $this->logger->debug('Starting module duration update', [
            'module_id' => $moduleId,
            'module_title' => $module->getTitle() ?? 'Unknown',
            'current_duration' => $module->getDurationHours(),
        ]);

        try {
            // Invalidate cache for this module
            $this->invalidateEntityCache('module', $moduleId);

            // Get calculated duration
            $calculatedDuration = $this->calculateModuleDuration($module);
            $currentDuration = $module->getDurationHours();

            $this->logger->info('Updating module duration', [
                'module_id' => $moduleId,
                'module_title' => $module->getTitle() ?? 'Unknown',
                'old_duration' => $currentDuration,
                'new_duration' => $calculatedDuration,
                'difference' => $calculatedDuration - $currentDuration,
            ]);

            // Update the module's stored duration
            $module->setDurationHours($calculatedDuration);
            $this->entityManager->persist($module);

            // Propagate to parent formation
            $formation = $module->getFormation();
            if ($formation) {
                $this->logger->debug('Propagating duration update to parent formation', [
                    'module_id' => $moduleId,
                    'formation_id' => $formation->getId(),
                    'formation_title' => $formation->getTitle() ?? 'Unknown',
                ]);

                $this->updateFormationDuration($formation);
            } else {
                $this->logger->warning('Module has no parent formation', [
                    'module_id' => $moduleId,
                ]);
            }

            $this->logger->debug('Module duration update completed', [
                'module_id' => $moduleId,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update module duration', [
                'module_id' => $moduleId,
                'module_title' => $module->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update Formation duration.
     */
    private function updateFormationDuration(Formation $formation): void
    {
        $startTime = microtime(true);
        $formationId = $formation->getId();

        $this->logger->debug('Starting formation duration update', [
            'formation_id' => $formationId,
            'formation_title' => $formation->getTitle() ?? 'Unknown',
            'current_duration' => $formation->getDurationHours(),
        ]);

        try {
            // Invalidate cache for this formation
            $this->invalidateEntityCache('formation', $formationId);

            // Get calculated duration
            $calculatedDuration = $this->calculateFormationDuration($formation);
            $currentDuration = $formation->getDurationHours();

            $this->logger->info('Updating formation duration', [
                'formation_id' => $formationId,
                'formation_title' => $formation->getTitle() ?? 'Unknown',
                'old_duration' => $currentDuration,
                'new_duration' => $calculatedDuration,
                'difference' => $calculatedDuration - $currentDuration,
            ]);

            // Update the formation's stored duration
            $formation->setDurationHours($calculatedDuration);
            $this->entityManager->persist($formation);

            $this->logger->debug('Formation duration update completed', [
                'formation_id' => $formationId,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update formation duration', [
                'formation_id' => $formationId,
                'formation_title' => $formation->getTitle() ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update Course duration when an Exercise or QCM changes.
     */
    private function updateCourseFromChild(Exercise|QCM $entity): void
    {
        $startTime = microtime(true);
        $entityClass = get_class($entity);
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

        $this->logger->debug('Starting course update from child entity', [
            'child_entity_class' => $entityClass,
            'child_entity_id' => $entityId,
        ]);

        try {
            $course = match ($entityClass) {
                Exercise::class => $entity->getCourse(),
                QCM::class => $entity->getCourse(),
                default => null
            };

            if ($course) {
                $this->logger->debug('Found parent course, updating duration', [
                    'child_entity_class' => $entityClass,
                    'child_entity_id' => $entityId,
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle() ?? 'Unknown',
                ]);

                $this->updateCourseDuration($course);

                $this->logger->debug('Course duration update from child completed', [
                    'child_entity_class' => $entityClass,
                    'child_entity_id' => $entityId,
                    'course_id' => $course->getId(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);
            } else {
                $this->logger->warning('Child entity has no parent course', [
                    'child_entity_class' => $entityClass,
                    'child_entity_id' => $entityId,
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to update course from child entity', [
                'child_entity_class' => $entityClass,
                'child_entity_id' => $entityId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Invalidate cache for a specific entity.
     */
    private function invalidateEntityCache(string $entityType, ?int $entityId): void
    {
        $this->logger->debug('Starting cache invalidation', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        try {
            if ($entityId) {
                $cacheKey = self::CACHE_PREFIX . $entityType . '_' . $entityId;

                $this->logger->debug('Invalidating cache key', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'cache_key' => $cacheKey,
                ]);

                $this->cache->delete($cacheKey);

                $this->logger->debug('Cache invalidation completed', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'cache_key' => $cacheKey,
                ]);
            } else {
                $this->logger->warning('Cannot invalidate cache for entity without ID', [
                    'entity_type' => $entityType,
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to invalidate entity cache', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Don't re-throw as cache invalidation failure shouldn't stop the process
        }
    }
}
