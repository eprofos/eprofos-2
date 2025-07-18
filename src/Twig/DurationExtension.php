<?php

namespace App\Twig;

use App\Service\DurationCalculationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for duration formatting and calculation
 */
class DurationExtension extends AbstractExtension
{
    public function __construct(
        private DurationCalculationService $durationService
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('duration_format', [$this, 'formatDuration']),
            new TwigFilter('minutes_to_hours', [$this, 'minutesToHours']),
            new TwigFilter('hours_to_minutes', [$this, 'hoursToMinutes']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('calculate_course_duration', [$this, 'calculateCourseDuration']),
            new TwigFunction('calculate_chapter_duration', [$this, 'calculateChapterDuration']),
            new TwigFunction('calculate_module_duration', [$this, 'calculateModuleDuration']),
            new TwigFunction('calculate_formation_duration', [$this, 'calculateFormationDuration']),
            new TwigFunction('duration_statistics', [$this, 'getDurationStatistics']),
        ];
    }

    /**
     * Format duration for display
     */
    public function formatDuration(int $value, string $unit = 'minutes'): string
    {
        return $this->durationService->formatDuration($value, $unit);
    }

    /**
     * Convert minutes to hours
     */
    public function minutesToHours(int $minutes, bool $roundUp = true): int
    {
        return $this->durationService->minutesToHours($minutes, $roundUp);
    }

    /**
     * Convert hours to minutes
     */
    public function hoursToMinutes(int $hours): int
    {
        return $this->durationService->hoursToMinutes($hours);
    }

    /**
     * Calculate total duration for a course
     */
    public function calculateCourseDuration(object $course): int
    {
        return $this->durationService->calculateCourseDuration($course);
    }

    /**
     * Calculate total duration for a chapter
     */
    public function calculateChapterDuration(object $chapter): int
    {
        return $this->durationService->calculateChapterDuration($chapter);
    }

    /**
     * Calculate total duration for a module
     */
    public function calculateModuleDuration(object $module): int
    {
        return $this->durationService->calculateModuleDuration($module);
    }

    /**
     * Calculate total duration for a formation
     */
    public function calculateFormationDuration(object $formation): int
    {
        return $this->durationService->calculateFormationDuration($formation);
    }

    /**
     * Get duration statistics for an entity
     */
    public function getDurationStatistics(object $entity): array
    {
        return $this->durationService->getDurationStatistics($entity);
    }
}
