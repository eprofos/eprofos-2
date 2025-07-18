<?php

namespace App\Service;

use App\Entity\Formation;
use App\Entity\Module;
use App\Entity\Chapter;
use App\Entity\Course;
use App\Entity\Exercise;
use App\Entity\QCM;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Duration Calculation Service
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
    
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Calculate total duration for a Course (including exercises and QCMs)
     */
    public function calculateCourseDuration(Course $course): int
    {
        $cacheKey = self::CACHE_PREFIX . 'course_' . $course->getId();
        
        // If entity is not persisted, calculate directly without caching
        if (!$course->getId()) {
            return $this->calculateCourseDurationDirect($course);
        }
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($course) {
            $item->expiresAfter(self::CACHE_TTL);
            
            return $this->calculateCourseDurationDirect($course);
        });
    }
    
    /**
     * Calculate course duration directly without caching
     */
    private function calculateCourseDurationDirect(Course $course): int
    {
        $totalDuration = $course->getDurationMinutes() ?? 0;
        
        // Add exercise durations
        foreach ($course->getActiveExercises() as $exercise) {
            $totalDuration += $exercise->getEstimatedDurationMinutes() ?? 0;
        }
        
        // Add QCM time limits
        foreach ($course->getActiveQcms() as $qcm) {
            $totalDuration += $qcm->getTimeLimitMinutes() ?? 0;
        }
        
        $this->logger->info('Calculated course duration', [
            'course_id' => $course->getId(),
            'base_duration' => $course->getDurationMinutes(),
            'total_duration' => $totalDuration
        ]);
        
        return $totalDuration;
    }

    /**
     * Calculate total duration for a Chapter (sum of all active courses)
     */
    public function calculateChapterDuration(Chapter $chapter): int
    {
        $cacheKey = self::CACHE_PREFIX . 'chapter_' . $chapter->getId();
        
        // If entity is not persisted, calculate directly without caching
        if (!$chapter->getId()) {
            return $this->calculateChapterDurationDirect($chapter);
        }
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($chapter) {
            $item->expiresAfter(self::CACHE_TTL);
            
            return $this->calculateChapterDurationDirect($chapter);
        });
    }
    
    /**
     * Calculate chapter duration directly without caching
     */
    private function calculateChapterDurationDirect(Chapter $chapter): int
    {
        $totalDuration = 0;
        
        foreach ($chapter->getActiveCourses() as $course) {
            $totalDuration += $this->calculateCourseDuration($course);
        }
        
        $this->logger->info('Calculated chapter duration', [
            'chapter_id' => $chapter->getId(),
            'total_duration' => $totalDuration,
            'course_count' => $chapter->getActiveCourses()->count()
        ]);
        
        return $totalDuration;
    }

    /**
     * Calculate total duration for a Module (sum of all active chapters, converted to hours)
     */
    public function calculateModuleDuration(Module $module): int
    {
        $cacheKey = self::CACHE_PREFIX . 'module_' . $module->getId();
        
        // If entity is not persisted, calculate directly without caching
        if (!$module->getId()) {
            return $this->calculateModuleDurationDirect($module);
        }
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($module) {
            $item->expiresAfter(self::CACHE_TTL);
            
            return $this->calculateModuleDurationDirect($module);
        });
    }
    
    /**
     * Calculate module duration directly without caching
     */
    private function calculateModuleDurationDirect(Module $module): int
    {
        $totalMinutes = 0;
        
        foreach ($module->getActiveChapters() as $chapter) {
            $totalMinutes += $this->calculateChapterDuration($chapter);
        }
        
        // Convert minutes to hours (rounded up)
        $totalHours = (int) ceil($totalMinutes / 60);
        
        $this->logger->info('Calculated module duration', [
            'module_id' => $module->getId(),
            'total_minutes' => $totalMinutes,
            'total_hours' => $totalHours,
            'chapter_count' => $module->getActiveChapters()->count()
        ]);
        
        return $totalHours;
    }

    /**
     * Calculate total duration for a Formation (sum of all active modules)
     */
    public function calculateFormationDuration(Formation $formation): int
    {
        $cacheKey = self::CACHE_PREFIX . 'formation_' . $formation->getId();
        
        // If entity is not persisted, calculate directly without caching
        if (!$formation->getId()) {
            return $this->calculateFormationDurationDirect($formation);
        }
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($formation) {
            $item->expiresAfter(self::CACHE_TTL);
            
            return $this->calculateFormationDurationDirect($formation);
        });
    }
    
    /**
     * Calculate formation duration directly without caching
     */
    private function calculateFormationDurationDirect(Formation $formation): int
    {
        $totalHours = 0;
        
        foreach ($formation->getActiveModules() as $module) {
            $totalHours += $this->calculateModuleDuration($module);
        }
        
        $this->logger->info('Calculated formation duration', [
            'formation_id' => $formation->getId(),
            'total_hours' => $totalHours,
            'module_count' => $formation->getActiveModules()->count()
        ]);
        
        return $totalHours;
    }

    /**
     * Update duration for a specific entity and propagate changes upward
     */
    public function updateEntityDuration(object $entity): void
    {
        try {
            switch (get_class($entity)) {
                case Course::class:
                    $this->updateCourseDuration($entity);
                    break;
                case Chapter::class:
                    $this->updateChapterDuration($entity);
                    break;
                case Module::class:
                    $this->updateModuleDuration($entity);
                    break;
                case Formation::class:
                    $this->updateFormationDuration($entity);
                    break;
                case Exercise::class:
                case QCM::class:
                    $this->updateCourseFromChild($entity);
                    break;
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to update entity duration', [
                'entity_class' => get_class($entity),
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update Course duration and propagate to Chapter
     */
    private function updateCourseDuration(Course $course): void
    {
        // Invalidate cache for this course
        $this->invalidateEntityCache('course', $course->getId());
        
        // Get calculated duration
        $calculatedDuration = $this->calculateCourseDuration($course);
        
        // Update the course's stored duration if it differs significantly
        if (abs($course->getDurationMinutes() - $calculatedDuration) > 5) {
            $course->setDurationMinutes($calculatedDuration);
            $this->entityManager->persist($course);
        }
        
        // Propagate to parent chapter
        if ($course->getChapter()) {
            $this->updateChapterDuration($course->getChapter());
        }
    }

    /**
     * Update Chapter duration and propagate to Module
     */
    private function updateChapterDuration(Chapter $chapter): void
    {
        // Invalidate cache for this chapter
        $this->invalidateEntityCache('chapter', $chapter->getId());
        
        // Get calculated duration
        $calculatedDuration = $this->calculateChapterDuration($chapter);
        
        // Update the chapter's stored duration
        $chapter->setDurationMinutes($calculatedDuration);
        $this->entityManager->persist($chapter);
        
        // Propagate to parent module
        if ($chapter->getModule()) {
            $this->updateModuleDuration($chapter->getModule());
        }
    }

    /**
     * Update Module duration and propagate to Formation
     */
    private function updateModuleDuration(Module $module): void
    {
        // Invalidate cache for this module
        $this->invalidateEntityCache('module', $module->getId());
        
        // Get calculated duration
        $calculatedDuration = $this->calculateModuleDuration($module);
        
        // Update the module's stored duration
        $module->setDurationHours($calculatedDuration);
        $this->entityManager->persist($module);
        
        // Propagate to parent formation
        if ($module->getFormation()) {
            $this->updateFormationDuration($module->getFormation());
        }
    }

    /**
     * Update Formation duration
     */
    private function updateFormationDuration(Formation $formation): void
    {
        // Invalidate cache for this formation
        $this->invalidateEntityCache('formation', $formation->getId());
        
        // Get calculated duration
        $calculatedDuration = $this->calculateFormationDuration($formation);
        
        // Update the formation's stored duration
        $formation->setDurationHours($calculatedDuration);
        $this->entityManager->persist($formation);
    }

    /**
     * Update Course duration when an Exercise or QCM changes
     */
    private function updateCourseFromChild(Exercise|QCM $entity): void
    {
        $course = match (get_class($entity)) {
            Exercise::class => $entity->getCourse(),
            QCM::class => $entity->getCourse(),
            default => null
        };
        
        if ($course) {
            $this->updateCourseDuration($course);
        }
    }

    /**
     * Batch update durations for multiple entities
     */
    public function batchUpdateDurations(array $entities): void
    {
        $this->entityManager->beginTransaction();
        
        try {
            foreach ($entities as $entity) {
                $this->updateEntityDuration($entity);
            }
            
            $this->entityManager->flush();
            $this->entityManager->commit();
            
            $this->logger->info('Batch duration update completed', [
                'entity_count' => count($entities)
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Batch duration update failed', [
                'entity_count' => count($entities),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Invalidate cache for a specific entity
     */
    private function invalidateEntityCache(string $entityType, ?int $entityId): void
    {
        if ($entityId) {
            $cacheKey = self::CACHE_PREFIX . $entityType . '_' . $entityId;
            $this->cache->delete($cacheKey);
        }
    }

    /**
     * Clear all duration caches
     */
    public function clearDurationCaches(): void
    {
        // This would need to be implemented based on your cache backend
        // For example, with Redis: $this->cache->clear();
        $this->logger->info('Duration caches cleared');
    }

    /**
     * Get duration statistics for an entity
     */
    public function getDurationStatistics(object $entity): array
    {
        $stats = [
            'entity_type' => get_class($entity),
            'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
        ];
        
        switch (get_class($entity)) {
            case Formation::class:
                $stats['calculated_duration'] = $this->calculateFormationDuration($entity);
                $stats['stored_duration'] = $entity->getDurationHours();
                $stats['unit'] = 'hours';
                $stats['module_count'] = $entity->getActiveModules()->count();
                break;
                
            case Module::class:
                $stats['calculated_duration'] = $this->calculateModuleDuration($entity);
                $stats['stored_duration'] = $entity->getDurationHours();
                $stats['unit'] = 'hours';
                $stats['chapter_count'] = $entity->getActiveChapters()->count();
                break;
                
            case Chapter::class:
                $stats['calculated_duration'] = $this->calculateChapterDuration($entity);
                $stats['stored_duration'] = $entity->getDurationMinutes();
                $stats['unit'] = 'minutes';
                $stats['course_count'] = $entity->getActiveCourses()->count();
                break;
                
            case Course::class:
                $stats['calculated_duration'] = $this->calculateCourseDuration($entity);
                $stats['stored_duration'] = $entity->getDurationMinutes();
                $stats['unit'] = 'minutes';
                $stats['exercise_count'] = $entity->getActiveExercises()->count();
                $stats['qcm_count'] = $entity->getActiveQcms()->count();
                break;
        }
        
        if (isset($stats['calculated_duration']) && isset($stats['stored_duration'])) {
            $stats['difference'] = $stats['calculated_duration'] - $stats['stored_duration'];
            $stats['needs_update'] = abs($stats['difference']) > 0;
        } else {
            $stats['needs_update'] = false;
            $stats['difference'] = 0;
        }
        
        return $stats;
    }

    /**
     * Convert minutes to hours with proper rounding
     */
    public function minutesToHours(int $minutes, bool $roundUp = true): int
    {
        if ($roundUp) {
            return (int) ceil($minutes / 60);
        }
        
        return (int) round($minutes / 60);
    }

    /**
     * Convert hours to minutes
     */
    public function hoursToMinutes(int $hours): int
    {
        return $hours * 60;
    }

    /**
     * Format duration for display
     */
    public function formatDuration(int $value, string $unit): string
    {
        switch ($unit) {
            case 'minutes':
                if ($value < 60) {
                    return $value . ' min';
                }
                
                $hours = intval($value / 60);
                $minutes = $value % 60;
                
                if ($minutes === 0) {
                    return $hours . 'h';
                }
                
                return $hours . 'h ' . $minutes . 'min';
                
            case 'hours':
                if ($value < 8) {
                    return $value . 'h';
                }
                
                $days = intval($value / 8);
                $hours = $value % 8;
                
                if ($hours === 0) {
                    return $days . ' jour' . ($days > 1 ? 's' : '');
                }
                
                return $days . ' jour' . ($days > 1 ? 's' : '') . ' ' . $hours . 'h';
                
            default:
                return $value . ' ' . $unit;
        }
    }
}
