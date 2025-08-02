<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\ProgressAssessment;
use App\Entity\Core\StudentProgress;
use App\Entity\User\Student;
use App\Repository\Alternance\ProgressAssessmentRepository;
use App\Repository\Core\StudentProgressRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * ProgressAssessmentService.
 *
 * Handles business logic for progress assessment management including
 * creation, calculation, risk assessment, and progression tracking.
 */
class ProgressAssessmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProgressAssessmentRepository $progressAssessmentRepository,
        private StudentProgressRepository $studentProgressRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Create a new progress assessment.
     */
    public function createProgressAssessment(
        Student $student,
        DateTimeInterface $period,
    ): ProgressAssessment {
        $operationId = uniqid('create_assessment_', true);
        $startTime = microtime(true);

        $this->logger->info('Starting progress assessment creation', [
            'operation_id' => $operationId,
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'period' => $period->format('Y-m-d'),
            'method' => __METHOD__,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Validate input parameters
            if (!$student || !$student->getId()) {
                $this->logger->error('Invalid student provided for assessment creation', [
                    'operation_id' => $operationId,
                    'student_valid' => $student !== null,
                    'student_id' => $student?->getId(),
                ]);

                throw new InvalidArgumentException('Valid student is required for assessment creation');
            }

            if (!$period) {
                $this->logger->error('Invalid period provided for assessment creation', [
                    'operation_id' => $operationId,
                    'period_provided' => $period,
                ]);

                throw new InvalidArgumentException('Valid period is required for assessment creation');
            }

            $this->logger->debug('Input parameters validated successfully', [
                'operation_id' => $operationId,
                'student_id' => $student->getId(),
                'period' => $period->format('Y-m-d H:i:s'),
            ]);

            // Create new assessment entity
            try {
                $this->logger->debug('Creating new progress assessment entity', [
                    'operation_id' => $operationId,
                    'step' => 'create_entity',
                ]);

                $assessment = new ProgressAssessment();
                $assessment->setStudent($student)
                    ->setPeriod($period)
                ;

                $this->logger->debug('Progress assessment entity created successfully', [
                    'operation_id' => $operationId,
                    'step' => 'create_entity',
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to create progress assessment entity', [
                    'operation_id' => $operationId,
                    'step' => 'create_entity',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                throw $e;
            }

            // Calculate initial progression values
            try {
                $this->logger->debug('Starting progression calculation', [
                    'operation_id' => $operationId,
                    'step' => 'calculate_progression',
                ]);

                $this->calculateProgression($assessment);

                $this->logger->debug('Progression calculation completed successfully', [
                    'operation_id' => $operationId,
                    'step' => 'calculate_progression',
                    'center_progression' => $assessment->getCenterProgression(),
                    'company_progression' => $assessment->getCompanyProgression(),
                    'overall_progression' => $assessment->getOverallProgression(),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to calculate progression for new assessment', [
                    'operation_id' => $operationId,
                    'step' => 'calculate_progression',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // Continue with assessment creation even if progression calculation fails
                $this->logger->warning('Continuing assessment creation without progression calculation', [
                    'operation_id' => $operationId,
                ]);
            }

            // Persist and flush assessment
            try {
                $this->logger->debug('Persisting assessment to database', [
                    'operation_id' => $operationId,
                    'step' => 'persist_assessment',
                ]);

                $this->entityManager->persist($assessment);
                $this->entityManager->flush();

                $this->logger->debug('Assessment persisted successfully', [
                    'operation_id' => $operationId,
                    'step' => 'persist_assessment',
                    'assessment_id' => $assessment->getId(),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to persist progress assessment', [
                    'operation_id' => $operationId,
                    'step' => 'persist_assessment',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Progress assessment created successfully', [
                'operation_id' => $operationId,
                'assessment_id' => $assessment->getId(),
                'student_id' => $student->getId(),
                'student_name' => $student->getFullName(),
                'period' => $period->format('Y-m-d'),
                'execution_time_ms' => $executionTime,
                'assessment_data' => [
                    'center_progression' => $assessment->getCenterProgression(),
                    'company_progression' => $assessment->getCompanyProgression(),
                    'overall_progression' => $assessment->getOverallProgression(),
                    'risk_level' => $assessment->getRiskLevel(),
                ],
            ]);

            return $assessment;
        } catch (Throwable $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->critical('Critical failure in progress assessment creation', [
                'operation_id' => $operationId,
                'student_id' => $student?->getId(),
                'period' => $period?->format('Y-m-d'),
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw $e;
        }
    }

    /**
     * Calculate progression for an assessment.
     */
    public function calculateProgression(ProgressAssessment $assessment): ProgressAssessment
    {
        $operationId = uniqid('calc_progression_', true);
        $startTime = microtime(true);

        $this->logger->info('Starting progression calculation', [
            'operation_id' => $operationId,
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()?->getId(),
            'method' => __METHOD__,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Validate assessment
            if (!$assessment || !$assessment->getStudent()) {
                $this->logger->error('Invalid assessment or student for progression calculation', [
                    'operation_id' => $operationId,
                    'assessment_valid' => $assessment !== null,
                    'student_valid' => $assessment?->getStudent() !== null,
                ]);

                throw new InvalidArgumentException('Valid assessment with student is required');
            }

            $student = $assessment->getStudent();

            $this->logger->debug('Assessment validated, searching for student progress', [
                'operation_id' => $operationId,
                'student_id' => $student->getId(),
                'step' => 'find_student_progress',
            ]);

            // Find student progress with detailed error handling
            $studentProgress = null;

            try {
                $studentProgress = $this->studentProgressRepository->findOneBy(['student' => $student]);

                $this->logger->debug('Student progress search completed', [
                    'operation_id' => $operationId,
                    'student_id' => $student->getId(),
                    'step' => 'find_student_progress',
                    'progress_found' => $studentProgress !== null,
                    'progress_id' => $studentProgress?->getId(),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to search for student progress', [
                    'operation_id' => $operationId,
                    'student_id' => $student->getId(),
                    'step' => 'find_student_progress',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // Continue with null progress
            }

            if (!$studentProgress) {
                $this->logger->warning('No student progress found for progression calculation', [
                    'operation_id' => $operationId,
                    'student_id' => $student->getId(),
                    'step' => 'validate_student_progress',
                ]);

                // Set default values when no progress data available
                $assessment->setCenterProgression('0.00');
                $assessment->setCompanyProgression('0.00');
                $assessment->calculateOverallProgression();
                $assessment->calculateRiskLevel();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                $this->logger->info('Progression calculation completed with default values', [
                    'operation_id' => $operationId,
                    'student_id' => $student->getId(),
                    'execution_time_ms' => $executionTime,
                    'reason' => 'no_student_progress_found',
                ]);

                return $assessment;
            }

            // Calculate center progression from formation progress
            try {
                $this->logger->debug('Calculating center progression', [
                    'operation_id' => $operationId,
                    'step' => 'center_progression',
                    'student_progress_id' => $studentProgress->getId(),
                ]);

                $centerProgression = (float) $studentProgress->getCompletionPercentage();
                $assessment->setCenterProgression(number_format($centerProgression, 2));

                $this->logger->debug('Center progression calculated successfully', [
                    'operation_id' => $operationId,
                    'step' => 'center_progression',
                    'raw_percentage' => $studentProgress->getCompletionPercentage(),
                    'formatted_progression' => $centerProgression,
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to calculate center progression', [
                    'operation_id' => $operationId,
                    'step' => 'center_progression',
                    'student_progress_id' => $studentProgress->getId(),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $assessment->setCenterProgression('0.00');
            }

            // Calculate company progression from mission progress
            try {
                $this->logger->debug('Calculating company progression', [
                    'operation_id' => $operationId,
                    'step' => 'company_progression',
                    'student_progress_id' => $studentProgress->getId(),
                ]);

                $companyProgression = $this->calculateCompanyProgression($studentProgress);
                $assessment->setCompanyProgression(number_format($companyProgression, 2));

                $this->logger->debug('Company progression calculated successfully', [
                    'operation_id' => $operationId,
                    'step' => 'company_progression',
                    'calculated_progression' => $companyProgression,
                    'formatted_progression' => number_format($companyProgression, 2),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to calculate company progression', [
                    'operation_id' => $operationId,
                    'step' => 'company_progression',
                    'student_progress_id' => $studentProgress->getId(),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $assessment->setCompanyProgression('0.00');
            }

            // Calculate overall progression
            try {
                $this->logger->debug('Calculating overall progression', [
                    'operation_id' => $operationId,
                    'step' => 'overall_progression',
                    'center_progression' => $assessment->getCenterProgression(),
                    'company_progression' => $assessment->getCompanyProgression(),
                ]);

                $assessment->calculateOverallProgression();

                $this->logger->debug('Overall progression calculated successfully', [
                    'operation_id' => $operationId,
                    'step' => 'overall_progression',
                    'overall_progression' => $assessment->getOverallProgression(),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to calculate overall progression', [
                    'operation_id' => $operationId,
                    'step' => 'overall_progression',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // Continue without overall progression
            }

            // Update skills matrix
            try {
                $this->logger->debug('Updating skills matrix', [
                    'operation_id' => $operationId,
                    'step' => 'skills_matrix',
                    'student_progress_id' => $studentProgress->getId(),
                ]);

                $this->updateSkillsMatrix($assessment, $studentProgress);

                $this->logger->debug('Skills matrix updated successfully', [
                    'operation_id' => $operationId,
                    'step' => 'skills_matrix',
                    'skills_count' => count($assessment->getSkillsMatrix() ?? []),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to update skills matrix', [
                    'operation_id' => $operationId,
                    'step' => 'skills_matrix',
                    'student_progress_id' => $studentProgress->getId(),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // Continue without skills matrix update
            }

            // Calculate risk level
            try {
                $this->logger->debug('Calculating risk level', [
                    'operation_id' => $operationId,
                    'step' => 'risk_level',
                    'overall_progression' => $assessment->getOverallProgression(),
                ]);

                $assessment->calculateRiskLevel();

                $this->logger->debug('Risk level calculated successfully', [
                    'operation_id' => $operationId,
                    'step' => 'risk_level',
                    'risk_level' => $assessment->getRiskLevel(),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to calculate risk level', [
                    'operation_id' => $operationId,
                    'step' => 'risk_level',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // Continue without risk level calculation
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Progression calculation completed successfully', [
                'operation_id' => $operationId,
                'assessment_id' => $assessment->getId(),
                'student_id' => $student->getId(),
                'execution_time_ms' => $executionTime,
                'final_data' => [
                    'center_progression' => $assessment->getCenterProgression(),
                    'company_progression' => $assessment->getCompanyProgression(),
                    'overall_progression' => $assessment->getOverallProgression(),
                    'risk_level' => $assessment->getRiskLevel(),
                    'skills_count' => count($assessment->getSkillsMatrix() ?? []),
                ],
            ]);

            return $assessment;
        } catch (Throwable $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->critical('Critical failure in progression calculation', [
                'operation_id' => $operationId,
                'assessment_id' => $assessment?->getId(),
                'student_id' => $assessment?->getStudent()?->getId(),
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw $e;
        }
    }

    /**
     * Update progress assessment with objectives.
     */
    public function updateObjectives(
        ProgressAssessment $assessment,
        array $completedObjectives = [],
        array $pendingObjectives = [],
        array $upcomingObjectives = [],
    ): ProgressAssessment {
        $operationId = uniqid('update_objectives_', true);
        $startTime = microtime(true);

        $this->logger->info('Starting objectives update', [
            'operation_id' => $operationId,
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()?->getId(),
            'completed_count' => count($completedObjectives),
            'pending_count' => count($pendingObjectives),
            'upcoming_count' => count($upcomingObjectives),
            'method' => __METHOD__,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Validate assessment
            if (!$assessment || !$assessment->getId()) {
                $this->logger->error('Invalid assessment for objectives update', [
                    'operation_id' => $operationId,
                    'assessment_valid' => $assessment !== null,
                    'assessment_id' => $assessment?->getId(),
                ]);

                throw new InvalidArgumentException('Valid assessment is required for objectives update');
            }

            $this->logger->debug('Assessment validated, processing objectives', [
                'operation_id' => $operationId,
                'assessment_id' => $assessment->getId(),
            ]);

            // Process completed objectives
            $completedSuccessCount = 0;
            $completedErrorCount = 0;

            foreach ($completedObjectives as $index => $objective) {
                try {
                    $this->logger->debug("Processing completed objective {$index}", [
                        'operation_id' => $operationId,
                        'objective_index' => $index,
                        'objective_keys' => array_keys($objective),
                        'has_category' => isset($objective['category']),
                        'has_objective' => isset($objective['objective']),
                        'has_completed_at' => isset($objective['completed_at']),
                    ]);

                    // Validate required fields
                    if (!isset($objective['objective']) || empty($objective['objective'])) {
                        $this->logger->warning("Completed objective {$index} missing required 'objective' field", [
                            'operation_id' => $operationId,
                            'objective_index' => $index,
                            'objective_data' => $objective,
                        ]);
                        $completedErrorCount++;

                        continue;
                    }

                    $category = $objective['category'] ?? 'general';
                    $objectiveText = $objective['objective'];
                    $completedAt = null;

                    if (isset($objective['completed_at'])) {
                        try {
                            $completedAt = new DateTime($objective['completed_at']);
                        } catch (Throwable $e) {
                            $this->logger->warning("Invalid completed_at date for completed objective {$index}", [
                                'operation_id' => $operationId,
                                'objective_index' => $index,
                                'completed_at_value' => $objective['completed_at'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $assessment->addCompletedObjective($category, $objectiveText, $completedAt);
                    $completedSuccessCount++;

                    $this->logger->debug("Completed objective {$index} processed successfully", [
                        'operation_id' => $operationId,
                        'objective_index' => $index,
                        'category' => $category,
                        'objective_length' => strlen($objectiveText),
                        'has_completed_at' => $completedAt !== null,
                    ]);
                } catch (Throwable $e) {
                    $completedErrorCount++;
                    $this->logger->warning("Failed to process completed objective {$index}", [
                        'operation_id' => $operationId,
                        'objective_index' => $index,
                        'objective_data' => $objective,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            // Process pending objectives
            $pendingSuccessCount = 0;
            $pendingErrorCount = 0;

            foreach ($pendingObjectives as $index => $objective) {
                try {
                    $this->logger->debug("Processing pending objective {$index}", [
                        'operation_id' => $operationId,
                        'objective_index' => $index,
                        'objective_keys' => array_keys($objective),
                        'has_category' => isset($objective['category']),
                        'has_objective' => isset($objective['objective']),
                        'has_target_date' => isset($objective['target_date']),
                        'has_priority' => isset($objective['priority']),
                    ]);

                    // Validate required fields
                    if (!isset($objective['objective']) || empty($objective['objective'])) {
                        $this->logger->warning("Pending objective {$index} missing required 'objective' field", [
                            'operation_id' => $operationId,
                            'objective_index' => $index,
                            'objective_data' => $objective,
                        ]);
                        $pendingErrorCount++;

                        continue;
                    }

                    $category = $objective['category'] ?? 'general';
                    $objectiveText = $objective['objective'];
                    $targetDate = $objective['target_date'] ?? null;
                    $priority = (int) ($objective['priority'] ?? 3);

                    // Validate priority range
                    if ($priority < 1 || $priority > 5) {
                        $this->logger->debug("Invalid priority for pending objective {$index}, using default", [
                            'operation_id' => $operationId,
                            'objective_index' => $index,
                            'provided_priority' => $priority,
                            'default_priority' => 3,
                        ]);
                        $priority = 3;
                    }

                    $assessment->addPendingObjective($category, $objectiveText, $targetDate, $priority);
                    $pendingSuccessCount++;

                    $this->logger->debug("Pending objective {$index} processed successfully", [
                        'operation_id' => $operationId,
                        'objective_index' => $index,
                        'category' => $category,
                        'objective_length' => strlen($objectiveText),
                        'target_date' => $targetDate,
                        'priority' => $priority,
                    ]);
                } catch (Throwable $e) {
                    $pendingErrorCount++;
                    $this->logger->warning("Failed to process pending objective {$index}", [
                        'operation_id' => $operationId,
                        'objective_index' => $index,
                        'objective_data' => $objective,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            // Process upcoming objectives
            $upcomingSuccessCount = 0;
            $upcomingErrorCount = 0;

            foreach ($upcomingObjectives as $index => $objective) {
                try {
                    $this->logger->debug("Processing upcoming objective {$index}", [
                        'operation_id' => $operationId,
                        'objective_index' => $index,
                        'objective_keys' => array_keys($objective),
                        'has_category' => isset($objective['category']),
                        'has_objective' => isset($objective['objective']),
                        'has_start_date' => isset($objective['start_date']),
                    ]);

                    // Validate required fields
                    if (!isset($objective['objective']) || empty($objective['objective'])) {
                        $this->logger->warning("Upcoming objective {$index} missing required 'objective' field", [
                            'operation_id' => $operationId,
                            'objective_index' => $index,
                            'objective_data' => $objective,
                        ]);
                        $upcomingErrorCount++;

                        continue;
                    }

                    $category = $objective['category'] ?? 'general';
                    $objectiveText = $objective['objective'];
                    $startDate = $objective['start_date'] ?? null;

                    $assessment->addUpcomingObjective($category, $objectiveText, $startDate);
                    $upcomingSuccessCount++;

                    $this->logger->debug("Upcoming objective {$index} processed successfully", [
                        'operation_id' => $operationId,
                        'objective_index' => $index,
                        'category' => $category,
                        'objective_length' => strlen($objectiveText),
                        'start_date' => $startDate,
                    ]);
                } catch (Throwable $e) {
                    $upcomingErrorCount++;
                    $this->logger->warning("Failed to process upcoming objective {$index}", [
                        'operation_id' => $operationId,
                        'objective_index' => $index,
                        'objective_data' => $objective,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            // Flush changes to database
            try {
                $this->logger->debug('Flushing objectives changes to database', [
                    'operation_id' => $operationId,
                    'step' => 'flush_changes',
                ]);

                $this->entityManager->flush();

                $this->logger->debug('Objectives changes flushed successfully', [
                    'operation_id' => $operationId,
                    'step' => 'flush_changes',
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to flush objectives changes', [
                    'operation_id' => $operationId,
                    'step' => 'flush_changes',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Objectives update completed successfully', [
                'operation_id' => $operationId,
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()?->getId(),
                'execution_time_ms' => $executionTime,
                'processing_summary' => [
                    'completed_objectives' => [
                        'total' => count($completedObjectives),
                        'success' => $completedSuccessCount,
                        'errors' => $completedErrorCount,
                        'success_rate' => count($completedObjectives) > 0 ? round(($completedSuccessCount / count($completedObjectives)) * 100, 2) : 100,
                    ],
                    'pending_objectives' => [
                        'total' => count($pendingObjectives),
                        'success' => $pendingSuccessCount,
                        'errors' => $pendingErrorCount,
                        'success_rate' => count($pendingObjectives) > 0 ? round(($pendingSuccessCount / count($pendingObjectives)) * 100, 2) : 100,
                    ],
                    'upcoming_objectives' => [
                        'total' => count($upcomingObjectives),
                        'success' => $upcomingSuccessCount,
                        'errors' => $upcomingErrorCount,
                        'success_rate' => count($upcomingObjectives) > 0 ? round(($upcomingSuccessCount / count($upcomingObjectives)) * 100, 2) : 100,
                    ],
                ],
            ]);

            return $assessment;
        } catch (Throwable $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->critical('Critical failure in objectives update', [
                'operation_id' => $operationId,
                'assessment_id' => $assessment?->getId(),
                'student_id' => $assessment?->getStudent()?->getId(),
                'execution_time_ms' => $executionTime,
                'objectives_counts' => [
                    'completed' => count($completedObjectives),
                    'pending' => count($pendingObjectives),
                    'upcoming' => count($upcomingObjectives),
                ],
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw $e;
        }
    }

    /**
     * Add difficulties to assessment.
     */
    public function addDifficulties(ProgressAssessment $assessment, array $difficulties): ProgressAssessment
    {
        foreach ($difficulties as $difficulty) {
            $assessment->addDifficulty(
                $difficulty['area'] ?? 'general',
                $difficulty['description'],
                $difficulty['severity'] ?? 3,
            );
        }

        // Recalculate risk level after adding difficulties
        $assessment->calculateRiskLevel();

        $this->entityManager->flush();

        return $assessment;
    }

    /**
     * Add support needed to assessment.
     */
    public function addSupportNeeded(ProgressAssessment $assessment, array $supportItems): ProgressAssessment
    {
        foreach ($supportItems as $support) {
            $assessment->addSupportNeeded(
                $support['type'] ?? 'general',
                $support['description'],
                $support['urgency'] ?? 3,
            );
        }

        // Recalculate risk level after adding support needs
        $assessment->calculateRiskLevel();

        $this->entityManager->flush();

        return $assessment;
    }

    /**
     * Generate comprehensive progress report.
     */
    public function generateProgressReport(Student $student, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $operationId = uniqid('progress_report_', true);
        $startTime = microtime(true);

        $this->logger->info('Starting progress report generation', [
            'operation_id' => $operationId,
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'date_range_days' => $startDate->diff($endDate)->days,
            'method' => __METHOD__,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Validate input parameters
            if (!$student || !$student->getId()) {
                $this->logger->error('Invalid student provided for progress report', [
                    'operation_id' => $operationId,
                    'student_valid' => $student !== null,
                    'student_id' => $student?->getId(),
                ]);

                throw new InvalidArgumentException('Valid student is required for progress report');
            }

            if (!$startDate || !$endDate) {
                $this->logger->error('Invalid date range provided for progress report', [
                    'operation_id' => $operationId,
                    'start_date_valid' => $startDate !== null,
                    'end_date_valid' => $endDate !== null,
                ]);

                throw new InvalidArgumentException('Valid date range is required for progress report');
            }

            if ($startDate > $endDate) {
                $this->logger->error('Invalid date range: start date is after end date', [
                    'operation_id' => $operationId,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ]);

                throw new InvalidArgumentException('Start date must be before or equal to end date');
            }

            $this->logger->debug('Input parameters validated successfully', [
                'operation_id' => $operationId,
                'student_id' => $student->getId(),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'date_range_days' => $startDate->diff($endDate)->days,
            ]);

            // Initialize report structure
            $report = [
                'student' => [
                    'id' => $student->getId(),
                    'name' => $student->getFullName(),
                    'email' => $student->getEmail(),
                ],
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'summary' => [
                    'total_assessments' => 0,
                    'current_risk_level' => null,
                    'current_progression' => null,
                    'progression_trend' => 'insufficient_data',
                ],
                'assessments' => [],
                'recommendations' => [],
            ];

            // Fetch assessments with detailed error handling
            $assessments = [];

            try {
                $this->logger->debug('Fetching assessments for date range', [
                    'operation_id' => $operationId,
                    'step' => 'fetch_assessments',
                    'student_id' => $student->getId(),
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ]);

                $assessments = $this->progressAssessmentRepository->findByStudentAndDateRange($student, $startDate, $endDate);

                $this->logger->debug('Assessments fetched successfully', [
                    'operation_id' => $operationId,
                    'step' => 'fetch_assessments',
                    'assessments_count' => count($assessments),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to fetch assessments for progress report', [
                    'operation_id' => $operationId,
                    'step' => 'fetch_assessments',
                    'student_id' => $student->getId(),
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // Continue with empty assessments array
                $assessments = [];
            }

            // Fetch progression trend with detailed error handling
            $trend = [];

            try {
                $this->logger->debug('Fetching progression trend data', [
                    'operation_id' => $operationId,
                    'step' => 'fetch_trend',
                    'student_id' => $student->getId(),
                    'months_back' => 6,
                ]);

                $trend = $this->progressAssessmentRepository->getStudentProgressionTrend($student, 6);

                $this->logger->debug('Progression trend fetched successfully', [
                    'operation_id' => $operationId,
                    'step' => 'fetch_trend',
                    'trend_data_keys' => array_keys($trend),
                    'overall_progression_points' => count($trend['overall_progression'] ?? []),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to fetch progression trend', [
                    'operation_id' => $operationId,
                    'step' => 'fetch_trend',
                    'student_id' => $student->getId(),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // Continue with empty trend
                $trend = ['overall_progression' => []];
            }

            // Update report summary
            $report['summary']['total_assessments'] = count($assessments);
            $report['summary']['progression_trend'] = $this->calculateProgressionTrend($trend);

            $this->logger->debug('Report summary updated', [
                'operation_id' => $operationId,
                'total_assessments' => $report['summary']['total_assessments'],
                'progression_trend' => $report['summary']['progression_trend'],
            ]);

            // Process assessments if available
            if (!empty($assessments)) {
                try {
                    $this->logger->debug('Processing assessments for report', [
                        'operation_id' => $operationId,
                        'step' => 'process_assessments',
                        'assessments_count' => count($assessments),
                    ]);

                    $latestAssessment = end($assessments);
                    $report['summary']['current_risk_level'] = $latestAssessment->getRiskLevel();
                    $report['summary']['current_progression'] = (float) $latestAssessment->getOverallProgression();

                    $this->logger->debug('Latest assessment data extracted', [
                        'operation_id' => $operationId,
                        'latest_assessment_id' => $latestAssessment->getId(),
                        'current_risk_level' => $report['summary']['current_risk_level'],
                        'current_progression' => $report['summary']['current_progression'],
                    ]);

                    // Process each assessment
                    $processedCount = 0;
                    $processingErrors = 0;

                    foreach ($assessments as $index => $assessment) {
                        try {
                            $this->logger->debug("Processing assessment {$index}", [
                                'operation_id' => $operationId,
                                'assessment_index' => $index,
                                'assessment_id' => $assessment->getId(),
                            ]);

                            $assessmentData = [
                                'id' => $assessment->getId(),
                                'period' => $assessment->getPeriod()->format('Y-m-d'),
                                'center_progression' => (float) $assessment->getCenterProgression(),
                                'company_progression' => (float) $assessment->getCompanyProgression(),
                                'overall_progression' => (float) $assessment->getOverallProgression(),
                                'risk_level' => $assessment->getRiskLevel(),
                                'objectives_summary' => $assessment->getObjectivesSummary(),
                                'skills_summary' => $assessment->getSkillsMatrixSummary(),
                            ];

                            $report['assessments'][] = $assessmentData;
                            $processedCount++;

                            $this->logger->debug("Assessment {$index} processed successfully", [
                                'operation_id' => $operationId,
                                'assessment_index' => $index,
                                'assessment_id' => $assessment->getId(),
                                'overall_progression' => $assessmentData['overall_progression'],
                                'risk_level' => $assessmentData['risk_level'],
                            ]);
                        } catch (Throwable $e) {
                            $processingErrors++;
                            $this->logger->warning("Failed to process assessment {$index}", [
                                'operation_id' => $operationId,
                                'assessment_index' => $index,
                                'assessment_id' => $assessment->getId(),
                                'error' => $e->getMessage(),
                                'error_class' => get_class($e),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                            ]);
                        }
                    }

                    $this->logger->debug('Assessments processing completed', [
                        'operation_id' => $operationId,
                        'step' => 'process_assessments',
                        'total_assessments' => count($assessments),
                        'processed_count' => $processedCount,
                        'processing_errors' => $processingErrors,
                        'success_rate' => count($assessments) > 0 ? round(($processedCount / count($assessments)) * 100, 2) : 100,
                    ]);

                    // Generate recommendations
                    try {
                        $this->logger->debug('Generating recommendations', [
                            'operation_id' => $operationId,
                            'step' => 'generate_recommendations',
                            'latest_assessment_id' => $latestAssessment->getId(),
                        ]);

                        $report['recommendations'] = $this->generateRecommendations($latestAssessment);

                        $this->logger->debug('Recommendations generated successfully', [
                            'operation_id' => $operationId,
                            'step' => 'generate_recommendations',
                            'recommendations_count' => count($report['recommendations']),
                        ]);
                    } catch (Throwable $e) {
                        $this->logger->error('Failed to generate recommendations', [
                            'operation_id' => $operationId,
                            'step' => 'generate_recommendations',
                            'latest_assessment_id' => $latestAssessment->getId(),
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                        $report['recommendations'] = [];
                    }
                } catch (Throwable $e) {
                    $this->logger->error('Failed to process assessments for report', [
                        'operation_id' => $operationId,
                        'step' => 'process_assessments',
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                    // Continue with empty assessments and recommendations
                    $report['assessments'] = [];
                    $report['recommendations'] = [];
                }
            } else {
                $this->logger->info('No assessments found for the specified date range', [
                    'operation_id' => $operationId,
                    'student_id' => $student->getId(),
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ]);
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Progress report generation completed successfully', [
                'operation_id' => $operationId,
                'student_id' => $student->getId(),
                'student_name' => $student->getFullName(),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'execution_time_ms' => $executionTime,
                'report_summary' => [
                    'total_assessments' => $report['summary']['total_assessments'],
                    'current_risk_level' => $report['summary']['current_risk_level'],
                    'current_progression' => $report['summary']['current_progression'],
                    'progression_trend' => $report['summary']['progression_trend'],
                    'processed_assessments' => count($report['assessments']),
                    'recommendations_count' => count($report['recommendations']),
                ],
                'data_quality' => [
                    'has_assessments' => !empty($report['assessments']),
                    'has_current_data' => $report['summary']['current_risk_level'] !== null,
                    'has_recommendations' => !empty($report['recommendations']),
                    'trend_data_available' => $report['summary']['progression_trend'] !== 'insufficient_data',
                ],
            ]);

            return $report;
        } catch (Throwable $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->critical('Critical failure in progress report generation', [
                'operation_id' => $operationId,
                'student_id' => $student?->getId(),
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw $e;
        }
    }

    /**
     * Detect students at risk.
     */
    public function detectStudentsAtRisk(int $riskThreshold = 3): array
    {
        $operationId = uniqid('detect_at_risk_', true);
        $startTime = microtime(true);

        $this->logger->info('Starting at-risk students detection', [
            'operation_id' => $operationId,
            'risk_threshold' => $riskThreshold,
            'method' => __METHOD__,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Validate risk threshold
            if ($riskThreshold < 1 || $riskThreshold > 5) {
                $this->logger->warning('Invalid risk threshold provided, using default', [
                    'operation_id' => $operationId,
                    'provided_threshold' => $riskThreshold,
                    'default_threshold' => 3,
                ]);
                $riskThreshold = 3;
            }

            $this->logger->debug('Risk threshold validated', [
                'operation_id' => $operationId,
                'validated_threshold' => $riskThreshold,
            ]);

            // Fetch at-risk assessments
            $atRiskAssessments = [];

            try {
                $this->logger->debug('Fetching assessments with high risk levels', [
                    'operation_id' => $operationId,
                    'step' => 'fetch_at_risk_assessments',
                    'risk_threshold' => $riskThreshold,
                ]);

                $atRiskAssessments = $this->progressAssessmentRepository->findStudentsAtRisk($riskThreshold);

                $this->logger->debug('At-risk assessments fetched successfully', [
                    'operation_id' => $operationId,
                    'step' => 'fetch_at_risk_assessments',
                    'assessments_count' => count($atRiskAssessments),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to fetch at-risk assessments', [
                    'operation_id' => $operationId,
                    'step' => 'fetch_at_risk_assessments',
                    'risk_threshold' => $riskThreshold,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // Continue with empty array
                $atRiskAssessments = [];
            }

            $studentsAtRisk = [];
            $processedCount = 0;
            $processingErrors = 0;

            $this->logger->debug('Starting student data processing', [
                'operation_id' => $operationId,
                'step' => 'process_students',
                'total_assessments' => count($atRiskAssessments),
            ]);

            // Process each at-risk assessment
            foreach ($atRiskAssessments as $index => $assessment) {
                try {
                    $this->logger->debug("Processing at-risk assessment {$index}", [
                        'operation_id' => $operationId,
                        'assessment_index' => $index,
                        'assessment_id' => $assessment->getId(),
                    ]);

                    // Validate assessment
                    if (!$assessment || !$assessment->getStudent()) {
                        $this->logger->warning("Invalid assessment or missing student at index {$index}", [
                            'operation_id' => $operationId,
                            'assessment_index' => $index,
                            'assessment_valid' => $assessment !== null,
                            'student_valid' => $assessment?->getStudent() !== null,
                        ]);
                        $processingErrors++;

                        continue;
                    }

                    $student = $assessment->getStudent();

                    // Generate risk factors analysis
                    $riskFactors = [];

                    try {
                        $this->logger->debug("Generating risk factors for assessment {$index}", [
                            'operation_id' => $operationId,
                            'assessment_index' => $index,
                            'assessment_id' => $assessment->getId(),
                            'step' => 'risk_factors_analysis',
                        ]);

                        $riskFactors = $assessment->getRiskFactorsAnalysis();

                        $this->logger->debug("Risk factors generated successfully for assessment {$index}", [
                            'operation_id' => $operationId,
                            'assessment_index' => $index,
                            'risk_factors_count' => count($riskFactors),
                        ]);
                    } catch (Throwable $e) {
                        $this->logger->warning("Failed to generate risk factors for assessment {$index}", [
                            'operation_id' => $operationId,
                            'assessment_index' => $index,
                            'assessment_id' => $assessment->getId(),
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                        ]);
                        $riskFactors = [];
                    }

                    // Generate recommendations
                    $recommendations = [];

                    try {
                        $this->logger->debug("Generating recommendations for assessment {$index}", [
                            'operation_id' => $operationId,
                            'assessment_index' => $index,
                            'assessment_id' => $assessment->getId(),
                            'step' => 'recommendations',
                        ]);

                        $recommendations = $this->generateRecommendations($assessment);

                        $this->logger->debug("Recommendations generated successfully for assessment {$index}", [
                            'operation_id' => $operationId,
                            'assessment_index' => $index,
                            'recommendations_count' => count($recommendations),
                        ]);
                    } catch (Throwable $e) {
                        $this->logger->warning("Failed to generate recommendations for assessment {$index}", [
                            'operation_id' => $operationId,
                            'assessment_index' => $index,
                            'assessment_id' => $assessment->getId(),
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                        ]);
                        $recommendations = [];
                    }

                    // Compile student data
                    $studentData = [
                        'student' => $student,
                        'assessment' => $assessment,
                        'risk_level' => $assessment->getRiskLevel(),
                        'risk_factors' => $riskFactors,
                        'last_assessment_date' => $assessment->getPeriod(),
                        'recommendations' => $recommendations,
                    ];

                    $studentsAtRisk[] = $studentData;
                    $processedCount++;

                    $this->logger->debug("At-risk student {$index} processed successfully", [
                        'operation_id' => $operationId,
                        'assessment_index' => $index,
                        'student_id' => $student->getId(),
                        'student_name' => $student->getFullName(),
                        'risk_level' => $assessment->getRiskLevel(),
                        'risk_factors_count' => count($riskFactors),
                        'recommendations_count' => count($recommendations),
                    ]);
                } catch (Throwable $e) {
                    $processingErrors++;
                    $this->logger->warning("Failed to process at-risk assessment {$index}", [
                        'operation_id' => $operationId,
                        'assessment_index' => $index,
                        'assessment_id' => $assessment?->getId(),
                        'student_id' => $assessment?->getStudent()?->getId(),
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('At-risk students detection completed successfully', [
                'operation_id' => $operationId,
                'risk_threshold' => $riskThreshold,
                'execution_time_ms' => $executionTime,
                'processing_summary' => [
                    'total_assessments' => count($atRiskAssessments),
                    'processed_count' => $processedCount,
                    'processing_errors' => $processingErrors,
                    'success_rate' => count($atRiskAssessments) > 0 ? round(($processedCount / count($atRiskAssessments)) * 100, 2) : 100,
                    'at_risk_students_count' => count($studentsAtRisk),
                ],
                'risk_distribution' => $this->analyzeRiskDistribution($studentsAtRisk),
            ]);

            return $studentsAtRisk;
        } catch (Throwable $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->critical('Critical failure in at-risk students detection', [
                'operation_id' => $operationId,
                'risk_threshold' => $riskThreshold,
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw $e;
        }
    }

    /**
     * Generate intervention plan for at-risk student.
     */
    public function generateInterventionPlan(ProgressAssessment $assessment): array
    {
        $riskFactors = $assessment->getRiskFactorsAnalysis();
        $interventions = [];

        foreach ($riskFactors as $factor) {
            switch ($factor['factor']) {
                case 'Progression globale faible':
                    $interventions[] = [
                        'type' => 'academic_support',
                        'priority' => 'high',
                        'title' => 'Soutien pédagogique renforcé',
                        'actions' => [
                            'Entretien avec le référent pédagogique',
                            'Plan de rattrapage personnalisé',
                            'Tutorat intensif',
                        ],
                        'timeline' => '2 semaines',
                    ];
                    break;

                case 'Difficultés importantes':
                    $interventions[] = [
                        'type' => 'mentoring',
                        'priority' => 'high',
                        'title' => 'Accompagnement spécialisé',
                        'actions' => [
                            'Rencontre tripartite étudiant-formateur-tuteur',
                            'Adaptation du rythme de formation',
                            'Ressources pédagogiques supplémentaires',
                        ],
                        'timeline' => '1 semaine',
                    ];
                    break;

                case 'Déséquilibre centre-entreprise':
                    $interventions[] = [
                        'type' => 'coordination',
                        'priority' => 'medium',
                        'title' => 'Harmonisation centre-entreprise',
                        'actions' => [
                            'Réunion de coordination tripartite',
                            'Ajustement du planning alternance',
                            'Clarification des objectifs',
                        ],
                        'timeline' => '3 semaines',
                    ];
                    break;
            }
        }

        // Add general intervention if no specific factors found
        if (empty($interventions) && $assessment->getRiskLevel() >= 3) {
            $interventions[] = [
                'type' => 'general_support',
                'priority' => 'medium',
                'title' => 'Accompagnement général',
                'actions' => [
                    'Entretien de situation',
                    'Évaluation des besoins',
                    'Plan d\'accompagnement personnalisé',
                ],
                'timeline' => '2 semaines',
            ];
        }

        return $interventions;
    }

    /**
     * Update assessment from external data source.
     */
    public function updateFromExternalData(ProgressAssessment $assessment, array $externalData): ProgressAssessment
    {
        // This method would be used to update assessments from external systems
        // Implementation depends on the specific external data format

        if (isset($externalData['progression'])) {
            $progression = $externalData['progression'];
            if (isset($progression['center'])) {
                $assessment->setCenterProgression(number_format($progression['center'], 2));
            }
            if (isset($progression['company'])) {
                $assessment->setCompanyProgression(number_format($progression['company'], 2));
            }
            $assessment->calculateOverallProgression();
        }

        if (isset($externalData['skills_matrix'])) {
            foreach ($externalData['skills_matrix'] as $skillCode => $skillData) {
                $assessment->updateSkillInMatrix(
                    $skillCode,
                    $skillData['name'],
                    $skillData['level'],
                    $skillData['last_assessed'] ?? null,
                );
            }
        }

        $assessment->calculateRiskLevel();
        $this->entityManager->flush();

        return $assessment;
    }

    /**
     * Generate period report for multiple students.
     */
    public function generatePeriodReport(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        return $this->progressAssessmentRepository->generateProgressionReport($startDate, $endDate);
    }

    /**
     * Export progress data for Qualiopi compliance.
     */
    public function exportProgressData(Student $student, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $assessments = $this->progressAssessmentRepository->findByStudentAndDateRange($student, $startDate, $endDate);

        $exportData = [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName(),
                'email' => $student->getEmail(),
            ],
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'progression_data' => [],
            'compliance_indicators' => [],
        ];

        foreach ($assessments as $assessment) {
            $exportData['progression_data'][] = [
                'id' => $assessment->getId(),
                'period' => $assessment->getPeriod()->format('Y-m-d'),
                'center_progression' => (float) $assessment->getCenterProgression(),
                'company_progression' => (float) $assessment->getCompanyProgression(),
                'overall_progression' => (float) $assessment->getOverallProgression(),
                'risk_level' => $assessment->getRiskLevel(),
                'objectives_completion_rate' => $assessment->calculateObjectivesCompletionRate(),
                'skills_mastery_rate' => $assessment->getSkillsMatrixSummary()['mastered_skills'] ?? 0,
                'progression_status' => $assessment->getProgressionStatus(),
            ];
        }

        // Add compliance indicators
        if (!empty($assessments)) {
            $latestAssessment = end($assessments);
            $exportData['compliance_indicators'] = [
                'regular_assessment' => count($assessments) >= 4, // Quarterly assessments
                'progression_tracking' => true,
                'risk_monitoring' => $latestAssessment->getRiskLevel() <= 3,
                'skills_development' => !empty($latestAssessment->getSkillsMatrix()),
                'objectives_management' => $latestAssessment->calculateObjectivesCompletionRate() > 0,
            ];
        }

        return $exportData;
    }

    /**
     * Analyze assessment and provide detailed insights.
     */
    public function analyzeAssessment(ProgressAssessment $assessment): array
    {
        return [
            'overall_status' => $this->getOverallStatus($assessment),
            'progression_analysis' => $this->analyzeProgression($assessment),
            'risk_analysis' => $assessment->getRiskFactorsAnalysis(),
            'objectives_analysis' => $this->analyzeObjectives($assessment),
            'skills_analysis' => $this->analyzeSkills($assessment),
            'recommendations' => $this->generateRecommendations($assessment),
            'intervention_plan' => $this->generateInterventionPlan($assessment),
            'trends' => $this->getProgressionTrends($assessment),
        ];
    }

    /**
     * Approve an assessment.
     */
    public function approveAssessment(ProgressAssessment $assessment, string $comments = ''): ProgressAssessment
    {
        // For now, we'll add a note to the assessment about approval
        // In a real implementation, you might add a validation_status field to the entity
        $assessment->setNextSteps(
            ($assessment->getNextSteps() ? $assessment->getNextSteps() . "\n\n" : '') .
            '[VALIDÉ ' . (new DateTime())->format('d/m/Y H:i') . '] ' .
            ($comments ?: 'Évaluation approuvée'),
        );

        $this->entityManager->flush();

        $this->logger->info('Progress assessment approved', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()->getId(),
            'comments' => $comments,
        ]);

        return $assessment;
    }

    /**
     * Reject an assessment.
     */
    public function rejectAssessment(ProgressAssessment $assessment, string $comments = ''): ProgressAssessment
    {
        // For now, we'll add a note to the assessment about rejection
        // In a real implementation, you might add a validation_status field to the entity
        $assessment->setNextSteps(
            ($assessment->getNextSteps() ? $assessment->getNextSteps() . "\n\n" : '') .
            '[REJETÉ ' . (new DateTime())->format('d/m/Y H:i') . '] ' .
            ($comments ?: 'Évaluation rejetée - révision nécessaire'),
        );

        $this->entityManager->flush();

        $this->logger->info('Progress assessment rejected', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()->getId(),
            'comments' => $comments,
        ]);

        return $assessment;
    }

    /**
     * Calculate company progression from student progress.
     */
    private function calculateCompanyProgression(StudentProgress $studentProgress): float
    {
        $operationId = uniqid('company_progression_', true);

        $this->logger->debug('Starting company progression calculation', [
            'operation_id' => $operationId,
            'student_progress_id' => $studentProgress->getId(),
            'method' => __METHOD__,
        ]);

        try {
            // Validate student progress
            if (!$studentProgress || !$studentProgress->getId()) {
                $this->logger->warning('Invalid student progress for company progression calculation', [
                    'operation_id' => $operationId,
                    'student_progress_valid' => $studentProgress !== null,
                    'student_progress_id' => $studentProgress?->getId(),
                ]);

                return 0.0;
            }

            // Get mission progress data
            $missionProgress = [];

            try {
                $this->logger->debug('Retrieving mission progress data', [
                    'operation_id' => $operationId,
                    'student_progress_id' => $studentProgress->getId(),
                    'step' => 'get_mission_progress',
                ]);

                $missionProgress = $studentProgress->getMissionProgress();

                $this->logger->debug('Mission progress data retrieved', [
                    'operation_id' => $operationId,
                    'student_progress_id' => $studentProgress->getId(),
                    'step' => 'get_mission_progress',
                    'mission_count' => count($missionProgress ?? []),
                    'has_mission_data' => !empty($missionProgress),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to retrieve mission progress data', [
                    'operation_id' => $operationId,
                    'student_progress_id' => $studentProgress->getId(),
                    'step' => 'get_mission_progress',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $missionProgress = [];
            }

            if (empty($missionProgress)) {
                $this->logger->info('No mission progress data found, returning 0.0', [
                    'operation_id' => $operationId,
                    'student_progress_id' => $studentProgress->getId(),
                    'reason' => 'empty_mission_progress',
                ]);

                return 0.0;
            }

            // Calculate weighted completion
            $totalWeight = 0;
            $weightedCompletion = 0;
            $validMissions = 0;
            $invalidMissions = 0;

            $this->logger->debug('Starting mission progress calculation', [
                'operation_id' => $operationId,
                'total_missions' => count($missionProgress),
                'step' => 'calculate_weighted_completion',
            ]);

            foreach ($missionProgress as $index => $mission) {
                try {
                    $this->logger->debug("Processing mission {$index}", [
                        'operation_id' => $operationId,
                        'mission_index' => $index,
                        'mission_keys' => is_array($mission) ? array_keys($mission) : 'not_array',
                        'has_completion_rate' => isset($mission['completion_rate']),
                    ]);

                    // Validate mission data
                    if (!is_array($mission)) {
                        $this->logger->warning("Mission {$index} is not an array", [
                            'operation_id' => $operationId,
                            'mission_index' => $index,
                            'mission_type' => gettype($mission),
                            'mission_value' => $mission,
                        ]);
                        $invalidMissions++;

                        continue;
                    }

                    // Extract completion rate
                    $completionRate = 0;
                    if (isset($mission['completion_rate'])) {
                        if (is_numeric($mission['completion_rate'])) {
                            $completionRate = (float) $mission['completion_rate'];
                        } else {
                            $this->logger->warning("Invalid completion rate for mission {$index}", [
                                'operation_id' => $operationId,
                                'mission_index' => $index,
                                'completion_rate_type' => gettype($mission['completion_rate']),
                                'completion_rate_value' => $mission['completion_rate'],
                            ]);
                        }
                    }

                    // Validate completion rate range
                    if ($completionRate < 0 || $completionRate > 100) {
                        $this->logger->warning("Completion rate out of range for mission {$index}", [
                            'operation_id' => $operationId,
                            'mission_index' => $index,
                            'completion_rate' => $completionRate,
                            'expected_range' => '0-100',
                        ]);
                        $completionRate = max(0, min(100, $completionRate)); // Clamp to valid range
                    }

                    // All missions have equal weight for now
                    $weight = 1;

                    $weightedCompletion += $completionRate * $weight;
                    $totalWeight += $weight;
                    $validMissions++;

                    $this->logger->debug("Mission {$index} processed successfully", [
                        'operation_id' => $operationId,
                        'mission_index' => $index,
                        'completion_rate' => $completionRate,
                        'weight' => $weight,
                        'contribution' => $completionRate * $weight,
                    ]);
                } catch (Throwable $e) {
                    $invalidMissions++;
                    $this->logger->warning("Failed to process mission {$index}", [
                        'operation_id' => $operationId,
                        'mission_index' => $index,
                        'mission_data' => $mission,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            // Calculate final progression
            $finalProgression = 0.0;
            if ($totalWeight > 0) {
                $finalProgression = $weightedCompletion / $totalWeight;
            }

            $this->logger->debug('Company progression calculation completed', [
                'operation_id' => $operationId,
                'student_progress_id' => $studentProgress->getId(),
                'calculation_summary' => [
                    'total_missions' => count($missionProgress),
                    'valid_missions' => $validMissions,
                    'invalid_missions' => $invalidMissions,
                    'total_weight' => $totalWeight,
                    'weighted_completion' => $weightedCompletion,
                    'final_progression' => $finalProgression,
                    'success_rate' => count($missionProgress) > 0 ? round(($validMissions / count($missionProgress)) * 100, 2) : 100,
                ],
            ]);

            return $finalProgression;
        } catch (Throwable $e) {
            $this->logger->error('Critical failure in company progression calculation', [
                'operation_id' => $operationId,
                'student_progress_id' => $studentProgress?->getId(),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return default value to prevent crashes
            return 0.0;
        }
    }

    /**
     * Update skills matrix in assessment.
     */
    private function updateSkillsMatrix(ProgressAssessment $assessment, StudentProgress $studentProgress): void
    {
        $operationId = uniqid('update_skills_', true);

        $this->logger->debug('Starting skills matrix update', [
            'operation_id' => $operationId,
            'assessment_id' => $assessment->getId(),
            'student_progress_id' => $studentProgress->getId(),
            'method' => __METHOD__,
        ]);

        try {
            // Validate input parameters
            if (!$assessment || !$assessment->getId()) {
                $this->logger->error('Invalid assessment for skills matrix update', [
                    'operation_id' => $operationId,
                    'assessment_valid' => $assessment !== null,
                    'assessment_id' => $assessment?->getId(),
                ]);

                return;
            }

            if (!$studentProgress || !$studentProgress->getId()) {
                $this->logger->error('Invalid student progress for skills matrix update', [
                    'operation_id' => $operationId,
                    'student_progress_valid' => $studentProgress !== null,
                    'student_progress_id' => $studentProgress?->getId(),
                ]);

                return;
            }

            // Get skills acquired data
            $skillsAcquired = [];

            try {
                $this->logger->debug('Retrieving skills acquired data', [
                    'operation_id' => $operationId,
                    'student_progress_id' => $studentProgress->getId(),
                    'step' => 'get_skills_acquired',
                ]);

                $skillsAcquired = $studentProgress->getSkillsAcquired();

                $this->logger->debug('Skills acquired data retrieved', [
                    'operation_id' => $operationId,
                    'student_progress_id' => $studentProgress->getId(),
                    'step' => 'get_skills_acquired',
                    'skills_count' => count($skillsAcquired ?? []),
                    'has_skills_data' => !empty($skillsAcquired),
                ]);
            } catch (Throwable $e) {
                $this->logger->error('Failed to retrieve skills acquired data', [
                    'operation_id' => $operationId,
                    'student_progress_id' => $studentProgress->getId(),
                    'step' => 'get_skills_acquired',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $skillsAcquired = [];
            }

            if (empty($skillsAcquired)) {
                $this->logger->info('No skills acquired data found, skills matrix update skipped', [
                    'operation_id' => $operationId,
                    'assessment_id' => $assessment->getId(),
                    'student_progress_id' => $studentProgress->getId(),
                ]);

                return;
            }

            // Process skills data
            $updatedSkillsCount = 0;
            $skippedSkillsCount = 0;
            $errorSkillsCount = 0;

            $this->logger->debug('Starting skills processing', [
                'operation_id' => $operationId,
                'total_skills' => count($skillsAcquired),
                'step' => 'process_skills',
            ]);

            foreach ($skillsAcquired as $skillCode => $skillData) {
                try {
                    $this->logger->debug("Processing skill: {$skillCode}", [
                        'operation_id' => $operationId,
                        'skill_code' => $skillCode,
                        'skill_data_keys' => is_array($skillData) ? array_keys($skillData) : 'not_array',
                        'has_name' => isset($skillData['name']),
                        'has_level' => isset($skillData['level']),
                        'has_acquired_at' => isset($skillData['acquired_at']),
                    ]);

                    // Validate skill code
                    if (empty($skillCode) || !is_string($skillCode)) {
                        $this->logger->warning('Invalid skill code', [
                            'operation_id' => $operationId,
                            'skill_code' => $skillCode,
                            'skill_code_type' => gettype($skillCode),
                        ]);
                        $skippedSkillsCount++;

                        continue;
                    }

                    // Validate skill data
                    if (!is_array($skillData)) {
                        $this->logger->warning("Skill data is not an array for skill: {$skillCode}", [
                            'operation_id' => $operationId,
                            'skill_code' => $skillCode,
                            'skill_data_type' => gettype($skillData),
                            'skill_data_value' => $skillData,
                        ]);
                        $skippedSkillsCount++;

                        continue;
                    }

                    // Extract skill information with defaults
                    $skillName = $skillData['name'] ?? $skillCode;
                    $skillLevel = 0;
                    $acquiredAt = null;

                    // Validate and process skill level
                    if (isset($skillData['level'])) {
                        if (is_numeric($skillData['level'])) {
                            $skillLevel = (int) $skillData['level'];
                            // Validate level range (typically 0-5 or 0-100)
                            if ($skillLevel < 0 || $skillLevel > 100) {
                                $this->logger->warning("Skill level out of range for skill: {$skillCode}", [
                                    'operation_id' => $operationId,
                                    'skill_code' => $skillCode,
                                    'skill_level' => $skillLevel,
                                    'expected_range' => '0-100',
                                ]);
                                $skillLevel = max(0, min(100, $skillLevel)); // Clamp to valid range
                            }
                        } else {
                            $this->logger->warning("Invalid skill level type for skill: {$skillCode}", [
                                'operation_id' => $operationId,
                                'skill_code' => $skillCode,
                                'level_type' => gettype($skillData['level']),
                                'level_value' => $skillData['level'],
                            ]);
                        }
                    }

                    // Process acquired_at date
                    if (isset($skillData['acquired_at']) && !empty($skillData['acquired_at'])) {
                        try {
                            if ($skillData['acquired_at'] instanceof DateTimeInterface) {
                                $acquiredAt = $skillData['acquired_at'];
                            } elseif (is_string($skillData['acquired_at'])) {
                                $acquiredAt = new DateTime($skillData['acquired_at']);
                            }
                        } catch (Throwable $e) {
                            $this->logger->warning("Invalid acquired_at date for skill: {$skillCode}", [
                                'operation_id' => $operationId,
                                'skill_code' => $skillCode,
                                'acquired_at_value' => $skillData['acquired_at'],
                                'date_error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Update skill in matrix
                    try {
                        $assessment->updateSkillInMatrix($skillCode, $skillName, $skillLevel, $acquiredAt);
                        $updatedSkillsCount++;

                        $this->logger->debug("Skill updated successfully: {$skillCode}", [
                            'operation_id' => $operationId,
                            'skill_code' => $skillCode,
                            'skill_name' => $skillName,
                            'skill_level' => $skillLevel,
                            'has_acquired_at' => $acquiredAt !== null,
                        ]);
                    } catch (Throwable $e) {
                        $errorSkillsCount++;
                        $this->logger->error("Failed to update skill in matrix: {$skillCode}", [
                            'operation_id' => $operationId,
                            'skill_code' => $skillCode,
                            'skill_name' => $skillName,
                            'skill_level' => $skillLevel,
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                        ]);
                    }
                } catch (Throwable $e) {
                    $errorSkillsCount++;
                    $this->logger->warning("Failed to process skill: {$skillCode}", [
                        'operation_id' => $operationId,
                        'skill_code' => $skillCode,
                        'skill_data' => $skillData,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            $this->logger->debug('Skills matrix update completed', [
                'operation_id' => $operationId,
                'assessment_id' => $assessment->getId(),
                'student_progress_id' => $studentProgress->getId(),
                'processing_summary' => [
                    'total_skills' => count($skillsAcquired),
                    'updated_skills' => $updatedSkillsCount,
                    'skipped_skills' => $skippedSkillsCount,
                    'error_skills' => $errorSkillsCount,
                    'success_rate' => count($skillsAcquired) > 0 ? round(($updatedSkillsCount / count($skillsAcquired)) * 100, 2) : 100,
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Critical failure in skills matrix update', [
                'operation_id' => $operationId,
                'assessment_id' => $assessment?->getId(),
                'student_progress_id' => $studentProgress?->getId(),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);
        }
    }

    /**
     * Calculate progression trend.
     */
    private function calculateProgressionTrend(array $trend): string
    {
        if (count($trend['overall_progression']) < 2) {
            return 'insufficient_data';
        }

        $progressionValues = $trend['overall_progression'];
        $recent = array_slice($progressionValues, -3); // Last 3 assessments
        $older = array_slice($progressionValues, 0, 3); // First 3 assessments

        if (empty($recent) || empty($older)) {
            return 'stable';
        }

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);
        $difference = $recentAvg - $olderAvg;

        if ($difference > 10) {
            return 'improving';
        }
        if ($difference < -10) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Generate recommendations based on assessment.
     */
    private function generateRecommendations(ProgressAssessment $assessment): array
    {
        $recommendations = [];
        $riskLevel = $assessment->getRiskLevel();
        $progressionStatus = $assessment->getProgressionStatus();

        // Risk-based recommendations
        if ($riskLevel >= 4) {
            $recommendations[] = [
                'type' => 'urgent_intervention',
                'priority' => 'high',
                'title' => 'Intervention urgente requise',
                'description' => 'Le niveau de risque critique nécessite une intervention immédiate.',
            ];
        } elseif ($riskLevel >= 3) {
            $recommendations[] = [
                'type' => 'increased_monitoring',
                'priority' => 'medium',
                'title' => 'Surveillance renforcée',
                'description' => 'Augmenter la fréquence des points de suivi.',
            ];
        }

        // Progression-based recommendations
        if ($progressionStatus === 'critical') {
            $recommendations[] = [
                'type' => 'intensive_support',
                'priority' => 'high',
                'title' => 'Soutien intensif nécessaire',
                'description' => 'Mise en place d\'un plan de rattrapage intensif.',
            ];
        } elseif ($progressionStatus === 'needs_improvement') {
            $recommendations[] = [
                'type' => 'additional_support',
                'priority' => 'medium',
                'title' => 'Accompagnement supplémentaire',
                'description' => 'Renforcer l\'accompagnement pédagogique et professionnel.',
            ];
        }

        // Specific recommendations based on objectives and skills
        $objectivesSummary = $assessment->getObjectivesSummary();
        if ($objectivesSummary['completion_rate'] < 50) {
            $recommendations[] = [
                'type' => 'objectives_review',
                'priority' => 'medium',
                'title' => 'Révision des objectifs',
                'description' => 'Revoir et adapter les objectifs pédagogiques.',
            ];
        }

        $skillsSummary = $assessment->getSkillsMatrixSummary();
        if ($skillsSummary['declining_skills'] > 0) {
            $recommendations[] = [
                'type' => 'skills_reinforcement',
                'priority' => 'medium',
                'title' => 'Renforcement des compétences',
                'description' => 'Focus sur les compétences en régression.',
            ];
        }

        return $recommendations;
    }

    /**
     * Get overall status of assessment.
     */
    private function getOverallStatus(ProgressAssessment $assessment): array
    {
        $overallProgression = (float) $assessment->getOverallProgression();
        $riskLevel = $assessment->getRiskLevel();

        if ($overallProgression >= 80 && $riskLevel <= 2) {
            $status = 'excellent';
            $message = 'Progression excellente, aucune intervention requise';
        } elseif ($overallProgression >= 60 && $riskLevel <= 3) {
            $status = 'good';
            $message = 'Progression satisfaisante, surveillance normale';
        } elseif ($overallProgression >= 40 && $riskLevel <= 3) {
            $status = 'average';
            $message = 'Progression moyenne, accompagnement recommandé';
        } elseif ($riskLevel >= 4) {
            $status = 'critical';
            $message = 'Situation critique, intervention urgente requise';
        } else {
            $status = 'poor';
            $message = 'Progression insuffisante, soutien renforcé nécessaire';
        }

        return [
            'status' => $status,
            'message' => $message,
            'progression' => $overallProgression,
            'risk_level' => $riskLevel,
        ];
    }

    /**
     * Analyze progression details.
     */
    private function analyzeProgression(ProgressAssessment $assessment): array
    {
        $centerProgression = (float) $assessment->getCenterProgression();
        $companyProgression = (float) $assessment->getCompanyProgression();
        $overallProgression = (float) $assessment->getOverallProgression();

        $imbalance = abs($centerProgression - $companyProgression);

        return [
            'center_progression' => $centerProgression,
            'company_progression' => $companyProgression,
            'overall_progression' => $overallProgression,
            'imbalance' => $imbalance,
            'imbalance_level' => $imbalance > 20 ? 'high' : ($imbalance > 10 ? 'medium' : 'low'),
            'progression_status' => $assessment->getProgressionStatus(),
        ];
    }

    /**
     * Analyze objectives completion.
     */
    private function analyzeObjectives(ProgressAssessment $assessment): array
    {
        $summary = $assessment->getObjectivesSummary();

        return [
            'summary' => $summary,
            'completion_rate' => $summary['completion_rate'] ?? 0,
            'at_risk_objectives' => $this->getAtRiskObjectives($assessment),
            'priority_objectives' => $this->getPriorityObjectives($assessment),
        ];
    }

    /**
     * Analyze skills development.
     */
    private function analyzeSkills(ProgressAssessment $assessment): array
    {
        $skillsSummary = $assessment->getSkillsMatrixSummary();

        return [
            'summary' => $skillsSummary,
            'strong_skills' => $this->getStrongSkills($assessment),
            'weak_skills' => $this->getWeakSkills($assessment),
            'developing_skills' => $this->getDevelopingSkills($assessment),
        ];
    }

    /**
     * Get progression trends for a student.
     */
    private function getProgressionTrends(ProgressAssessment $assessment): array
    {
        $student = $assessment->getStudent();
        $trend = $this->progressAssessmentRepository->getStudentProgressionTrend($student, 6);

        return [
            'trend_direction' => $this->calculateProgressionTrend($trend),
            'recent_assessments' => array_slice($trend['overall_progression'] ?? [], -3),
            'improvement_rate' => $this->calculateImprovementRate($trend),
        ];
    }

    private function getAtRiskObjectives(ProgressAssessment $assessment): array
    {
        // Implementation would analyze pending objectives with high priority or overdue dates
        return [];
    }

    private function getPriorityObjectives(ProgressAssessment $assessment): array
    {
        // Implementation would return high priority pending objectives
        return [];
    }

    private function getStrongSkills(ProgressAssessment $assessment): array
    {
        // Implementation would return skills with high levels
        return [];
    }

    private function getWeakSkills(ProgressAssessment $assessment): array
    {
        // Implementation would return skills with low levels
        return [];
    }

    private function getDevelopingSkills(ProgressAssessment $assessment): array
    {
        // Implementation would return skills showing improvement
        return [];
    }

    private function calculateImprovementRate(array $trend): float
    {
        if (count($trend['overall_progression'] ?? []) < 2) {
            return 0.0;
        }

        $progressions = $trend['overall_progression'];
        $first = reset($progressions);
        $last = end($progressions);

        return $last - $first;
    }

    /**
     * Analyze risk distribution for logging purposes.
     */
    private function analyzeRiskDistribution(array $studentsAtRisk): array
    {
        try {
            $riskLevels = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

            foreach ($studentsAtRisk as $studentData) {
                $riskLevel = $studentData['risk_level'] ?? 0;
                if ($riskLevel >= 1 && $riskLevel <= 5) {
                    $riskLevels[$riskLevel]++;
                }
            }

            return [
                'total_students' => count($studentsAtRisk),
                'by_risk_level' => $riskLevels,
                'high_risk_count' => $riskLevels[4] + $riskLevels[5],
                'medium_risk_count' => $riskLevels[3],
                'low_risk_count' => $riskLevels[1] + $riskLevels[2],
            ];
        } catch (Throwable $e) {
            $this->logger->warning('Failed to analyze risk distribution', [
                'error' => $e->getMessage(),
                'students_count' => count($studentsAtRisk),
            ]);

            return [
                'total_students' => count($studentsAtRisk),
                'by_risk_level' => [],
                'high_risk_count' => 0,
                'medium_risk_count' => 0,
                'low_risk_count' => 0,
                'analysis_error' => true,
            ];
        }
    }
}
