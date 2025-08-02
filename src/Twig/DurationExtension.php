<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Training\DurationCalculationService;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for duration formatting and calculation.
 */
class DurationExtension extends AbstractExtension
{
    public function __construct(
        private DurationCalculationService $durationService,
        private LoggerInterface $logger,
    ) {}

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
     * Format duration for display.
     */
    public function formatDuration(int $value, string $unit = 'minutes'): string
    {
        try {
            $this->logger->debug('Starting duration formatting', [
                'value' => $value,
                'unit' => $unit,
                'class' => self::class,
                'method' => __METHOD__,
            ]);

            if ($value < 0) {
                $this->logger->warning('Negative duration value provided', [
                    'value' => $value,
                    'unit' => $unit,
                ]);

                throw new InvalidArgumentException('Duration value cannot be negative');
            }

            if (!in_array($unit, ['minutes', 'hours', 'days'], true)) {
                $this->logger->error('Invalid duration unit provided', [
                    'unit' => $unit,
                    'allowed_units' => ['minutes', 'hours', 'days'],
                ]);

                throw new InvalidArgumentException('Invalid unit. Allowed units: minutes, hours, days');
            }

            $result = $this->durationService->formatDuration($value, $unit);

            $this->logger->debug('Duration formatting completed successfully', [
                'input_value' => $value,
                'input_unit' => $unit,
                'formatted_result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error formatting duration', [
                'value' => $value,
                'unit' => $unit,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a fallback value to prevent template crashes
            return $value > 0 ? $value . ' ' . $unit : '0 ' . $unit;
        }
    }

    /**
     * Convert minutes to hours.
     */
    public function minutesToHours(int $minutes, bool $roundUp = true): int
    {
        try {
            $this->logger->debug('Converting minutes to hours', [
                'minutes' => $minutes,
                'round_up' => $roundUp,
                'class' => self::class,
                'method' => __METHOD__,
            ]);

            if ($minutes < 0) {
                $this->logger->warning('Negative minutes value provided', [
                    'minutes' => $minutes,
                ]);

                throw new InvalidArgumentException('Minutes value cannot be negative');
            }

            $result = $this->durationService->minutesToHours($minutes, $roundUp);

            $this->logger->debug('Minutes to hours conversion completed', [
                'input_minutes' => $minutes,
                'round_up' => $roundUp,
                'result_hours' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error converting minutes to hours', [
                'minutes' => $minutes,
                'round_up' => $roundUp,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a safe fallback value
            return max(0, (int) ceil($minutes / 60));
        }
    }

    /**
     * Convert hours to minutes.
     */
    public function hoursToMinutes(int $hours): int
    {
        try {
            $this->logger->debug('Converting hours to minutes', [
                'hours' => $hours,
                'class' => self::class,
                'method' => __METHOD__,
            ]);

            if ($hours < 0) {
                $this->logger->warning('Negative hours value provided', [
                    'hours' => $hours,
                ]);

                throw new InvalidArgumentException('Hours value cannot be negative');
            }

            $result = $this->durationService->hoursToMinutes($hours);

            $this->logger->debug('Hours to minutes conversion completed', [
                'input_hours' => $hours,
                'result_minutes' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error converting hours to minutes', [
                'hours' => $hours,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a safe fallback value
            return max(0, $hours * 60);
        }
    }

    /**
     * Calculate total duration for a course.
     */
    public function calculateCourseDuration(object $course): int
    {
        try {
            $this->logger->debug('Calculating course duration', [
                'course_class' => get_class($course),
                'course_id' => method_exists($course, 'getId') ? $course->getId() : 'unknown',
                'course_name' => method_exists($course, 'getTitle') ? $course->getTitle() : 'unknown',
                'class' => self::class,
                'method' => __METHOD__,
            ]);

            if (!is_object($course)) {
                $this->logger->error('Invalid course object provided', [
                    'provided_type' => gettype($course),
                ]);

                throw new InvalidArgumentException('Course must be an object');
            }

            $result = $this->durationService->calculateCourseDuration($course);

            $this->logger->info('Course duration calculated successfully', [
                'course_id' => method_exists($course, 'getId') ? $course->getId() : 'unknown',
                'calculated_duration' => $result,
                'duration_unit' => 'minutes',
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error calculating course duration', [
                'course_class' => is_object($course) ? get_class($course) : gettype($course),
                'course_id' => is_object($course) && method_exists($course, 'getId') ? $course->getId() : 'unknown',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a safe fallback value
            return 0;
        }
    }

    /**
     * Calculate total duration for a chapter.
     */
    public function calculateChapterDuration(object $chapter): int
    {
        try {
            $this->logger->debug('Calculating chapter duration', [
                'chapter_class' => get_class($chapter),
                'chapter_id' => method_exists($chapter, 'getId') ? $chapter->getId() : 'unknown',
                'chapter_title' => method_exists($chapter, 'getTitle') ? $chapter->getTitle() : 'unknown',
                'class' => self::class,
                'method' => __METHOD__,
            ]);

            if (!is_object($chapter)) {
                $this->logger->error('Invalid chapter object provided', [
                    'provided_type' => gettype($chapter),
                ]);

                throw new InvalidArgumentException('Chapter must be an object');
            }

            $result = $this->durationService->calculateChapterDuration($chapter);

            $this->logger->info('Chapter duration calculated successfully', [
                'chapter_id' => method_exists($chapter, 'getId') ? $chapter->getId() : 'unknown',
                'calculated_duration' => $result,
                'duration_unit' => 'minutes',
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error calculating chapter duration', [
                'chapter_class' => is_object($chapter) ? get_class($chapter) : gettype($chapter),
                'chapter_id' => is_object($chapter) && method_exists($chapter, 'getId') ? $chapter->getId() : 'unknown',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a safe fallback value
            return 0;
        }
    }

    /**
     * Calculate total duration for a module.
     */
    public function calculateModuleDuration(object $module): int
    {
        try {
            $this->logger->debug('Calculating module duration', [
                'module_class' => get_class($module),
                'module_id' => method_exists($module, 'getId') ? $module->getId() : 'unknown',
                'module_title' => method_exists($module, 'getTitle') ? $module->getTitle() : 'unknown',
                'class' => self::class,
                'method' => __METHOD__,
            ]);

            if (!is_object($module)) {
                $this->logger->error('Invalid module object provided', [
                    'provided_type' => gettype($module),
                ]);

                throw new InvalidArgumentException('Module must be an object');
            }

            $result = $this->durationService->calculateModuleDuration($module);

            $this->logger->info('Module duration calculated successfully', [
                'module_id' => method_exists($module, 'getId') ? $module->getId() : 'unknown',
                'calculated_duration' => $result,
                'duration_unit' => 'minutes',
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error calculating module duration', [
                'module_class' => is_object($module) ? get_class($module) : gettype($module),
                'module_id' => is_object($module) && method_exists($module, 'getId') ? $module->getId() : 'unknown',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a safe fallback value
            return 0;
        }
    }

    /**
     * Calculate total duration for a formation.
     */
    public function calculateFormationDuration(object $formation): int
    {
        try {
            $this->logger->debug('Calculating formation duration', [
                'formation_class' => get_class($formation),
                'formation_id' => method_exists($formation, 'getId') ? $formation->getId() : 'unknown',
                'formation_title' => method_exists($formation, 'getTitle') ? $formation->getTitle() : 'unknown',
                'formation_slug' => method_exists($formation, 'getSlug') ? $formation->getSlug() : 'unknown',
                'class' => self::class,
                'method' => __METHOD__,
            ]);

            if (!is_object($formation)) {
                $this->logger->error('Invalid formation object provided', [
                    'provided_type' => gettype($formation),
                ]);

                throw new InvalidArgumentException('Formation must be an object');
            }

            $result = $this->durationService->calculateFormationDuration($formation);

            $this->logger->info('Formation duration calculated successfully', [
                'formation_id' => method_exists($formation, 'getId') ? $formation->getId() : 'unknown',
                'formation_title' => method_exists($formation, 'getTitle') ? $formation->getTitle() : 'unknown',
                'calculated_duration' => $result,
                'duration_unit' => 'minutes',
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error calculating formation duration', [
                'formation_class' => is_object($formation) ? get_class($formation) : gettype($formation),
                'formation_id' => is_object($formation) && method_exists($formation, 'getId') ? $formation->getId() : 'unknown',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a safe fallback value
            return 0;
        }
    }

    /**
     * Get duration statistics for an entity.
     */
    public function getDurationStatistics(object $entity): array
    {
        try {
            $this->logger->debug('Getting duration statistics', [
                'entity_class' => get_class($entity),
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : 'unknown',
                'class' => self::class,
                'method' => __METHOD__,
            ]);

            if (!is_object($entity)) {
                $this->logger->error('Invalid entity object provided for statistics', [
                    'provided_type' => gettype($entity),
                ]);

                throw new InvalidArgumentException('Entity must be an object');
            }

            $result = $this->durationService->getDurationStatistics($entity);

            $this->logger->info('Duration statistics calculated successfully', [
                'entity_class' => get_class($entity),
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : 'unknown',
                'statistics_keys' => array_keys($result),
                'total_duration' => $result['total_duration'] ?? 'not_available',
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error getting duration statistics', [
                'entity_class' => is_object($entity) ? get_class($entity) : gettype($entity),
                'entity_id' => is_object($entity) && method_exists($entity, 'getId') ? $entity->getId() : 'unknown',
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return a safe fallback array
            return [
                'total_duration' => 0,
                'average_duration' => 0,
                'min_duration' => 0,
                'max_duration' => 0,
                'count' => 0,
                'error' => true,
                'error_message' => $e->getMessage(),
            ];
        }
    }
}
