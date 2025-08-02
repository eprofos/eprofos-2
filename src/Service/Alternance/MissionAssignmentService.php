<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\CompanyMission;
use App\Entity\Alternance\MissionAssignment;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Repository\Alternance\MissionAssignmentRepository;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service for managing mission assignments.
 *
 * Handles assignment creation, progress tracking, evaluation,
 * and business logic for mission assignments in the alternance system.
 */
class MissionAssignmentService
{
    private EntityManagerInterface $entityManager;

    private MissionAssignmentRepository $assignmentRepository;

    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        MissionAssignmentRepository $assignmentRepository,
        LoggerInterface $logger,
    ) {
        $this->entityManager = $entityManager;
        $this->assignmentRepository = $assignmentRepository;
        $this->logger = $logger;
    }

    /**
     * Create a new mission assignment.
     */
    public function createAssignment(CompanyMission $mission, Student $student, array $data): MissionAssignment
    {
        $this->logger->info('Starting assignment creation process', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'data_keys' => array_keys($data),
            'data_size' => count($data),
        ]);

        try {
            // Validate prerequisites
            $this->logger->debug('Validating assignment prerequisites', [
                'mission_id' => $mission->getId(),
                'student_id' => $student->getId(),
            ]);

            $this->validateAssignmentPrerequisites($mission, $student);

            $this->logger->debug('Assignment prerequisites validation successful');

            $assignment = new MissionAssignment();
            $this->logger->debug('Created new MissionAssignment entity', [
                'entity_id' => spl_object_id($assignment),
            ]);

            $assignment->setMission($mission);
            $this->logger->debug('Set assignment mission', [
                'mission_id' => $mission->getId(),
                'mission_title' => $mission->getTitle(),
            ]);

            $assignment->setStudent($student);
            $this->logger->debug('Set assignment student', [
                'student_id' => $student->getId(),
                'student_name' => $student->getFullName(),
            ]);

            $assignment->setStartDate($data['startDate']);
            $this->logger->debug('Set assignment start date', [
                'start_date' => $data['startDate']->format('Y-m-d'),
            ]);

            $assignment->setEndDate($data['endDate']);
            $this->logger->debug('Set assignment end date', [
                'end_date' => $data['endDate']->format('Y-m-d'),
                'duration_days' => $data['startDate']->diff($data['endDate'])->days,
            ]);

            $status = $data['status'] ?? 'planifiee';
            $assignment->setStatus($status);
            $this->logger->debug('Set assignment status', [
                'status' => $status,
                'status_provided' => isset($data['status']),
            ]);

            // Set intermediate objectives if provided
            if (isset($data['intermediateObjectives'])) {
                $assignment->setIntermediateObjectives($data['intermediateObjectives']);
                $this->logger->debug('Set provided intermediate objectives', [
                    'objectives_count' => count($data['intermediateObjectives']),
                    'objectives' => $data['intermediateObjectives'],
                ]);
            } else {
                // Auto-generate intermediate objectives based on mission objectives
                $this->logger->debug('Auto-generating intermediate objectives from mission');
                $intermediateObjectives = $this->generateIntermediateObjectives($mission);
                $assignment->setIntermediateObjectives($intermediateObjectives);
                $this->logger->debug('Set auto-generated intermediate objectives', [
                    'objectives_count' => count($intermediateObjectives),
                    'objectives' => $intermediateObjectives,
                ]);
            }

            $this->logger->debug('Persisting assignment entity');
            $this->entityManager->persist($assignment);

            $this->logger->debug('Flushing entity manager');
            $this->entityManager->flush();

            $this->logger->info('Mission assignment created successfully', [
                'assignment_id' => $assignment->getId(),
                'mission_id' => $mission->getId(),
                'mission_title' => $mission->getTitle(),
                'student_id' => $student->getId(),
                'student_name' => $student->getFullName(),
                'start_date' => $assignment->getStartDate()->format('Y-m-d'),
                'end_date' => $assignment->getEndDate()->format('Y-m-d'),
                'status' => $assignment->getStatus(),
                'intermediate_objectives_count' => count($assignment->getIntermediateObjectives()),
                'created_at' => $assignment->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

            return $assignment;
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime exception during assignment creation', [
                'mission_id' => $mission->getId(),
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'data_provided' => array_keys($data),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected exception during assignment creation', [
                'mission_id' => $mission->getId(),
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'data_provided' => array_keys($data),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to create assignment: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update an existing assignment.
     */
    public function updateAssignment(MissionAssignment $assignment, array $data): MissionAssignment
    {
        $this->logger->info('Starting assignment update process', [
            'assignment_id' => $assignment->getId(),
            'mission_id' => $assignment->getMission()->getId(),
            'student_id' => $assignment->getStudent()->getId(),
            'current_status' => $assignment->getStatus(),
            'data_keys' => array_keys($data),
            'data_size' => count($data),
        ]);

        try {
            $originalStatus = $assignment->getStatus();
            $originalData = [
                'status' => $originalStatus,
                'completion_rate' => $assignment->getCompletionRate(),
                'start_date' => $assignment->getStartDate()->format('Y-m-d'),
                'end_date' => $assignment->getEndDate()->format('Y-m-d'),
            ];

            $this->logger->debug('Captured original assignment data', [
                'assignment_id' => $assignment->getId(),
                'original_data' => $originalData,
            ]);

            // Update basic properties with detailed logging
            if (isset($data['startDate'])) {
                $oldStartDate = $assignment->getStartDate()->format('Y-m-d');
                $assignment->setStartDate($data['startDate']);
                $this->logger->debug('Updated assignment start date', [
                    'assignment_id' => $assignment->getId(),
                    'old_start_date' => $oldStartDate,
                    'new_start_date' => $data['startDate']->format('Y-m-d'),
                ]);
            }

            if (isset($data['endDate'])) {
                $oldEndDate = $assignment->getEndDate()->format('Y-m-d');
                $assignment->setEndDate($data['endDate']);
                $this->logger->debug('Updated assignment end date', [
                    'assignment_id' => $assignment->getId(),
                    'old_end_date' => $oldEndDate,
                    'new_end_date' => $data['endDate']->format('Y-m-d'),
                    'new_duration_days' => $assignment->getStartDate()->diff($data['endDate'])->days,
                ]);
            }

            if (isset($data['status'])) {
                $assignment->setStatus($data['status']);
                $this->logger->debug('Updated assignment status', [
                    'assignment_id' => $assignment->getId(),
                    'old_status' => $originalStatus,
                    'new_status' => $data['status'],
                ]);
            }

            if (isset($data['completionRate'])) {
                $oldCompletionRate = $assignment->getCompletionRate();
                $assignment->updateProgress($data['completionRate']);
                $this->logger->debug('Updated assignment completion rate', [
                    'assignment_id' => $assignment->getId(),
                    'old_completion_rate' => $oldCompletionRate,
                    'new_completion_rate' => $data['completionRate'],
                ]);
            }

            // Update feedback and ratings
            if (isset($data['mentorFeedback'])) {
                $oldFeedbackLength = strlen($assignment->getMentorFeedback() ?? '');
                $assignment->setMentorFeedback($data['mentorFeedback']);
                $this->logger->debug('Updated mentor feedback', [
                    'assignment_id' => $assignment->getId(),
                    'old_feedback_length' => $oldFeedbackLength,
                    'new_feedback_length' => strlen($data['mentorFeedback']),
                ]);
            }

            if (isset($data['studentFeedback'])) {
                $oldFeedbackLength = strlen($assignment->getStudentFeedback() ?? '');
                $assignment->setStudentFeedback($data['studentFeedback']);
                $this->logger->debug('Updated student feedback', [
                    'assignment_id' => $assignment->getId(),
                    'old_feedback_length' => $oldFeedbackLength,
                    'new_feedback_length' => strlen($data['studentFeedback']),
                ]);
            }

            if (isset($data['mentorRating'])) {
                $oldRating = $assignment->getMentorRating();
                $assignment->setMentorRating($data['mentorRating']);
                $this->logger->debug('Updated mentor rating', [
                    'assignment_id' => $assignment->getId(),
                    'old_rating' => $oldRating,
                    'new_rating' => $data['mentorRating'],
                ]);
            }

            if (isset($data['studentSatisfaction'])) {
                $oldSatisfaction = $assignment->getStudentSatisfaction();
                $assignment->setStudentSatisfaction($data['studentSatisfaction']);
                $this->logger->debug('Updated student satisfaction', [
                    'assignment_id' => $assignment->getId(),
                    'old_satisfaction' => $oldSatisfaction,
                    'new_satisfaction' => $data['studentSatisfaction'],
                ]);
            }

            // Update arrays with detailed logging
            if (isset($data['intermediateObjectives'])) {
                $oldObjectivesCount = count($assignment->getIntermediateObjectives());
                $assignment->setIntermediateObjectives($data['intermediateObjectives']);
                $this->logger->debug('Updated intermediate objectives', [
                    'assignment_id' => $assignment->getId(),
                    'old_objectives_count' => $oldObjectivesCount,
                    'new_objectives_count' => count($data['intermediateObjectives']),
                    'new_objectives' => $data['intermediateObjectives'],
                ]);
            }

            if (isset($data['difficulties'])) {
                $oldDifficultiesCount = count($assignment->getDifficulties());
                $assignment->setDifficulties($data['difficulties']);
                $this->logger->debug('Updated difficulties', [
                    'assignment_id' => $assignment->getId(),
                    'old_difficulties_count' => $oldDifficultiesCount,
                    'new_difficulties_count' => count($data['difficulties']),
                    'new_difficulties' => $data['difficulties'],
                ]);
            }

            if (isset($data['achievements'])) {
                $oldAchievementsCount = count($assignment->getAchievements());
                $assignment->setAchievements($data['achievements']);
                $this->logger->debug('Updated achievements', [
                    'assignment_id' => $assignment->getId(),
                    'old_achievements_count' => $oldAchievementsCount,
                    'new_achievements_count' => count($data['achievements']),
                    'new_achievements' => $data['achievements'],
                ]);
            }

            if (isset($data['competenciesAcquired'])) {
                $oldCompetenciesCount = count($assignment->getCompetenciesAcquired());
                $assignment->setCompetenciesAcquired($data['competenciesAcquired']);
                $this->logger->debug('Updated competencies acquired', [
                    'assignment_id' => $assignment->getId(),
                    'old_competencies_count' => $oldCompetenciesCount,
                    'new_competencies_count' => count($data['competenciesAcquired']),
                    'new_competencies' => $data['competenciesAcquired'],
                ]);
            }

            $this->logger->debug('Flushing entity manager for assignment update');
            $this->entityManager->flush();

            $this->logger->info('Mission assignment updated successfully', [
                'assignment_id' => $assignment->getId(),
                'mission_id' => $assignment->getMission()->getId(),
                'student_id' => $assignment->getStudent()->getId(),
                'original_status' => $originalStatus,
                'new_status' => $assignment->getStatus(),
                'updated_fields' => array_keys($data),
                'final_completion_rate' => $assignment->getCompletionRate(),
                'updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            return $assignment;
        } catch (Exception $e) {
            $this->logger->error('Exception during assignment update', [
                'assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'data_provided' => array_keys($data),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to update assignment: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Start an assignment (change status to in progress).
     */
    public function startAssignment(MissionAssignment $assignment): void
    {
        $this->logger->info('Starting assignment process', [
            'assignment_id' => $assignment->getId(),
            'mission_id' => $assignment->getMission()->getId(),
            'mission_title' => $assignment->getMission()->getTitle(),
            'student_id' => $assignment->getStudent()->getId(),
            'student_name' => $assignment->getStudent()->getFullName(),
            'current_status' => $assignment->getStatus(),
        ]);

        try {
            if ($assignment->getStatus() !== 'planifiee') {
                $this->logger->warning('Assignment start blocked - invalid status', [
                    'assignment_id' => $assignment->getId(),
                    'current_status' => $assignment->getStatus(),
                    'required_status' => 'planifiee',
                ]);

                throw new RuntimeException('Only planned assignments can be started.');
            }

            $this->logger->debug('Status validation successful, starting assignment');

            $assignment->start();

            $this->logger->debug('Assignment start method called, flushing entity manager');
            $this->entityManager->flush();

            $this->logger->info('Mission assignment started successfully', [
                'assignment_id' => $assignment->getId(),
                'student_id' => $assignment->getStudent()->getId(),
                'student_name' => $assignment->getStudent()->getFullName(),
                'mission_id' => $assignment->getMission()->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'new_status' => $assignment->getStatus(),
                'started_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime exception during assignment start', [
                'assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'current_status' => $assignment->getStatus(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected exception during assignment start', [
                'assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'current_status' => $assignment->getStatus(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to start assignment: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Complete an assignment.
     */
    public function completeAssignment(MissionAssignment $assignment, array $completionData = []): void
    {
        $this->logger->info('Starting assignment completion process', [
            'assignment_id' => $assignment->getId(),
            'mission_id' => $assignment->getMission()->getId(),
            'mission_title' => $assignment->getMission()->getTitle(),
            'student_id' => $assignment->getStudent()->getId(),
            'student_name' => $assignment->getStudent()->getFullName(),
            'current_status' => $assignment->getStatus(),
            'completion_data_keys' => array_keys($completionData),
            'completion_data_size' => count($completionData),
        ]);

        try {
            if (!in_array($assignment->getStatus(), ['planifiee', 'en_cours'], true)) {
                $this->logger->warning('Assignment completion blocked - invalid status', [
                    'assignment_id' => $assignment->getId(),
                    'current_status' => $assignment->getStatus(),
                    'required_statuses' => ['planifiee', 'en_cours'],
                ]);

                throw new RuntimeException('Only planned or in-progress assignments can be completed.');
            }

            $this->logger->debug('Status validation successful for completion');

            $preCompletionData = [
                'status' => $assignment->getStatus(),
                'completion_rate' => $assignment->getCompletionRate(),
                'achievements_count' => count($assignment->getAchievements()),
                'competencies_count' => count($assignment->getCompetenciesAcquired()),
            ];

            $this->logger->debug('Captured pre-completion data', [
                'assignment_id' => $assignment->getId(),
                'pre_completion_data' => $preCompletionData,
            ]);

            $assignment->complete();

            $this->logger->debug('Assignment complete method called', [
                'assignment_id' => $assignment->getId(),
                'new_status' => $assignment->getStatus(),
            ]);

            // Update completion data if provided
            if (isset($completionData['achievements'])) {
                $oldAchievementsCount = count($assignment->getAchievements());
                $assignment->setAchievements($completionData['achievements']);
                $this->logger->debug('Updated achievements on completion', [
                    'assignment_id' => $assignment->getId(),
                    'old_achievements_count' => $oldAchievementsCount,
                    'new_achievements_count' => count($completionData['achievements']),
                    'achievements' => $completionData['achievements'],
                ]);
            }

            if (isset($completionData['competenciesAcquired'])) {
                $oldCompetenciesCount = count($assignment->getCompetenciesAcquired());
                $assignment->setCompetenciesAcquired($completionData['competenciesAcquired']);
                $this->logger->debug('Updated competencies acquired on completion', [
                    'assignment_id' => $assignment->getId(),
                    'old_competencies_count' => $oldCompetenciesCount,
                    'new_competencies_count' => count($completionData['competenciesAcquired']),
                    'competencies' => $completionData['competenciesAcquired'],
                ]);
            }

            if (isset($completionData['studentFeedback'])) {
                $oldFeedbackLength = strlen($assignment->getStudentFeedback() ?? '');
                $assignment->setStudentFeedback($completionData['studentFeedback']);
                $this->logger->debug('Updated student feedback on completion', [
                    'assignment_id' => $assignment->getId(),
                    'old_feedback_length' => $oldFeedbackLength,
                    'new_feedback_length' => strlen($completionData['studentFeedback']),
                ]);
            }

            $this->logger->debug('Flushing entity manager for assignment completion');
            $this->entityManager->flush();

            $this->logger->info('Mission assignment completed successfully', [
                'assignment_id' => $assignment->getId(),
                'student_id' => $assignment->getStudent()->getId(),
                'student_name' => $assignment->getStudent()->getFullName(),
                'mission_id' => $assignment->getMission()->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'completion_rate' => $assignment->getCompletionRate(),
                'final_achievements_count' => count($assignment->getAchievements()),
                'final_competencies_count' => count($assignment->getCompetenciesAcquired()),
                'completion_data_provided' => array_keys($completionData),
                'completed_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime exception during assignment completion', [
                'assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'current_status' => $assignment->getStatus(),
                'completion_data_keys' => array_keys($completionData),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected exception during assignment completion', [
                'assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'current_status' => $assignment->getStatus(),
                'completion_data_keys' => array_keys($completionData),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to complete assignment: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Suspend an assignment.
     */
    public function suspendAssignment(MissionAssignment $assignment, string $reason): void
    {
        $this->logger->info('Starting assignment suspension process', [
            'assignment_id' => $assignment->getId(),
            'mission_id' => $assignment->getMission()->getId(),
            'mission_title' => $assignment->getMission()->getTitle(),
            'student_id' => $assignment->getStudent()->getId(),
            'student_name' => $assignment->getStudent()->getFullName(),
            'current_status' => $assignment->getStatus(),
            'suspension_reason' => $reason,
            'reason_length' => strlen($reason),
        ]);

        try {
            if (!in_array($assignment->getStatus(), ['planifiee', 'en_cours'], true)) {
                $this->logger->warning('Assignment suspension blocked - invalid status', [
                    'assignment_id' => $assignment->getId(),
                    'current_status' => $assignment->getStatus(),
                    'required_statuses' => ['planifiee', 'en_cours'],
                ]);

                throw new RuntimeException('Only planned or in-progress assignments can be suspended.');
            }

            $this->logger->debug('Status validation successful for suspension');

            $preSuspensionData = [
                'status' => $assignment->getStatus(),
                'completion_rate' => $assignment->getCompletionRate(),
                'difficulties_count' => count($assignment->getDifficulties()),
            ];

            $this->logger->debug('Captured pre-suspension data', [
                'assignment_id' => $assignment->getId(),
                'pre_suspension_data' => $preSuspensionData,
            ]);

            $assignment->suspend();

            $this->logger->debug('Assignment suspend method called', [
                'assignment_id' => $assignment->getId(),
                'new_status' => $assignment->getStatus(),
            ]);

            // Add suspension reason to difficulties
            $difficulties = $assignment->getDifficulties();
            $suspensionEntry = [
                'type' => 'suspension',
                'reason' => $reason,
                'date' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ];
            $difficulties[] = $suspensionEntry;
            $assignment->setDifficulties($difficulties);

            $this->logger->debug('Added suspension entry to difficulties', [
                'assignment_id' => $assignment->getId(),
                'suspension_entry' => $suspensionEntry,
                'total_difficulties_count' => count($difficulties),
            ]);

            $this->logger->debug('Flushing entity manager for assignment suspension');
            $this->entityManager->flush();

            $this->logger->info('Mission assignment suspended successfully', [
                'assignment_id' => $assignment->getId(),
                'mission_id' => $assignment->getMission()->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'student_id' => $assignment->getStudent()->getId(),
                'student_name' => $assignment->getStudent()->getFullName(),
                'reason' => $reason,
                'new_status' => $assignment->getStatus(),
                'total_difficulties_count' => count($assignment->getDifficulties()),
                'suspended_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime exception during assignment suspension', [
                'assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'current_status' => $assignment->getStatus(),
                'suspension_reason' => $reason,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected exception during assignment suspension', [
                'assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'current_status' => $assignment->getStatus(),
                'suspension_reason' => $reason,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to suspend assignment: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Resume a suspended assignment.
     */
    public function resumeAssignment(MissionAssignment $assignment): void
    {
        $this->logger->info('Starting assignment resume process', [
            'assignment_id' => $assignment->getId(),
            'mission_id' => $assignment->getMission()->getId(),
            'mission_title' => $assignment->getMission()->getTitle(),
            'student_id' => $assignment->getStudent()->getId(),
            'student_name' => $assignment->getStudent()->getFullName(),
            'current_status' => $assignment->getStatus(),
        ]);

        try {
            if ($assignment->getStatus() !== 'suspendue') {
                $this->logger->warning('Assignment resume blocked - invalid status', [
                    'assignment_id' => $assignment->getId(),
                    'current_status' => $assignment->getStatus(),
                    'required_status' => 'suspendue',
                ]);

                throw new RuntimeException('Only suspended assignments can be resumed.');
            }

            $this->logger->debug('Status validation successful for resume');

            $preResumeData = [
                'status' => $assignment->getStatus(),
                'completion_rate' => $assignment->getCompletionRate(),
                'difficulties_count' => count($assignment->getDifficulties()),
            ];

            $this->logger->debug('Captured pre-resume data', [
                'assignment_id' => $assignment->getId(),
                'pre_resume_data' => $preResumeData,
            ]);

            $assignment->resume();

            $this->logger->debug('Assignment resume method called', [
                'assignment_id' => $assignment->getId(),
                'new_status' => $assignment->getStatus(),
            ]);

            // Add resume entry to difficulties for tracking
            $difficulties = $assignment->getDifficulties();
            $resumeEntry = [
                'type' => 'resume',
                'note' => 'Assignment resumed after suspension',
                'date' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ];
            $difficulties[] = $resumeEntry;
            $assignment->setDifficulties($difficulties);

            $this->logger->debug('Added resume entry to difficulties', [
                'assignment_id' => $assignment->getId(),
                'resume_entry' => $resumeEntry,
                'total_difficulties_count' => count($difficulties),
            ]);

            $this->logger->debug('Flushing entity manager for assignment resume');
            $this->entityManager->flush();

            $this->logger->info('Mission assignment resumed successfully', [
                'assignment_id' => $assignment->getId(),
                'mission_id' => $assignment->getMission()->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'student_id' => $assignment->getStudent()->getId(),
                'student_name' => $assignment->getStudent()->getFullName(),
                'new_status' => $assignment->getStatus(),
                'total_difficulties_count' => count($assignment->getDifficulties()),
                'resumed_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime exception during assignment resume', [
                'assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'current_status' => $assignment->getStatus(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected exception during assignment resume', [
                'assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'current_status' => $assignment->getStatus(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to resume assignment: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update assignment progress.
     */
    public function updateProgress(MissionAssignment $assignment, float $completionRate, array $progressData = []): void
    {
        $assignment->updateProgress($completionRate);

        // Update intermediate objectives progress
        if (isset($progressData['intermediateObjectives'])) {
            $assignment->setIntermediateObjectives($progressData['intermediateObjectives']);
        }

        // Add achievements
        if (isset($progressData['achievements'])) {
            $existingAchievements = $assignment->getAchievements();
            $newAchievements = array_merge($existingAchievements, $progressData['achievements']);
            $assignment->setAchievements($newAchievements);
        }

        // Add difficulties if any
        if (isset($progressData['difficulties'])) {
            $existingDifficulties = $assignment->getDifficulties();
            $newDifficulties = array_merge($existingDifficulties, $progressData['difficulties']);
            $assignment->setDifficulties($newDifficulties);
        }

        $this->entityManager->flush();

        $this->logger->info('Mission assignment progress updated', [
            'assignment_id' => $assignment->getId(),
            'completion_rate' => $completionRate,
        ]);
    }

    /**
     * Add mentor evaluation.
     */
    public function addMentorEvaluation(MissionAssignment $assignment, int $rating, string $feedback, array $competenciesAcquired = []): void
    {
        if ($assignment->getStatus() !== 'terminee') {
            throw new RuntimeException('Only completed assignments can be evaluated.');
        }

        $assignment->setMentorRating($rating);
        $assignment->setMentorFeedback($feedback);

        if (!empty($competenciesAcquired)) {
            $assignment->setCompetenciesAcquired($competenciesAcquired);
        }

        $this->entityManager->flush();

        $this->logger->info('Mentor evaluation added', [
            'assignment_id' => $assignment->getId(),
            'rating' => $rating,
            'competencies_count' => count($competenciesAcquired),
        ]);
    }

    /**
     * Add student satisfaction feedback.
     */
    public function addStudentFeedback(MissionAssignment $assignment, int $satisfaction, string $feedback): void
    {
        $assignment->setStudentSatisfaction($satisfaction);
        $assignment->setStudentFeedback($feedback);

        $this->entityManager->flush();

        $this->logger->info('Student feedback added', [
            'assignment_id' => $assignment->getId(),
            'satisfaction' => $satisfaction,
        ]);
    }

    /**
     * Get assignment statistics for a student.
     */
    public function getStudentAssignmentStats(Student $student): array
    {
        return $this->assignmentRepository->calculateCompletionStats($student);
    }

    /**
     * Get assignment statistics for a mentor.
     */
    public function getMentorAssignmentStats(Mentor $mentor): array
    {
        return $this->assignmentRepository->getAssignmentStatsByMentor($mentor);
    }

    /**
     * Find assignments needing attention.
     */
    public function findAssignmentsNeedingAttention(?Mentor $mentor = null): array
    {
        return [
            'overdue' => $this->assignmentRepository->findOverdueAssignments($mentor),
            'low_progress' => $this->assignmentRepository->findLowCompletionAssignments(50.0, $mentor),
            'need_feedback' => $this->assignmentRepository->findRequiringFeedback($mentor),
            'need_attention' => $this->assignmentRepository->findAssignmentsNeedingAttention($mentor),
        ];
    }

    /**
     * Get dashboard data for a mentor.
     */
    public function getMentorDashboard(Mentor $mentor): array
    {
        return $this->assignmentRepository->getMentorDashboardData($mentor);
    }

    /**
     * Bulk update assignment status.
     *
     * @return int Number of updated assignments
     */
    public function bulkUpdateAssignmentStatus(array $assignmentIds, string $status): int
    {
        $updatedCount = 0;

        foreach ($assignmentIds as $assignmentId) {
            $assignment = $this->assignmentRepository->find($assignmentId);
            if ($assignment) {
                $assignment->setStatus($status);
                $updatedCount++;
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Bulk assignment status update', [
            'assignment_ids' => $assignmentIds,
            'status' => $status,
            'updated_count' => $updatedCount,
        ]);

        return $updatedCount;
    }

    /**
     * Export assignment data for reporting.
     */
    public function exportAssignmentData(array $filters = []): array
    {
        $assignments = $this->assignmentRepository->findAll();

        $exportData = [];
        foreach ($assignments as $assignment) {
            $exportData[] = [
                'id' => $assignment->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'student_name' => $assignment->getStudent()->getFullName(),
                'mentor_name' => $assignment->getMission()->getSupervisor()->getFullName(),
                'status' => $assignment->getStatusLabel(),
                'start_date' => $assignment->getStartDate()->format('Y-m-d'),
                'end_date' => $assignment->getEndDate()->format('Y-m-d'),
                'completion_rate' => $assignment->getCompletionRate(),
                'mentor_rating' => $assignment->getMentorRating(),
                'student_satisfaction' => $assignment->getStudentSatisfaction(),
                'created_at' => $assignment->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $exportData;
    }

    /**
     * Calculate assignment duration statistics.
     */
    public function calculateDurationStatistics(array $filters = []): array
    {
        $assignments = $this->assignmentRepository->findByStatus('terminee');

        $durations = [];
        $totalDuration = 0;

        foreach ($assignments as $assignment) {
            $duration = $assignment->getDurationInDays();
            $durations[] = $duration;
            $totalDuration += $duration;
        }

        if (empty($durations)) {
            return [
                'count' => 0,
                'average' => 0,
                'min' => 0,
                'max' => 0,
            ];
        }

        sort($durations);
        $count = count($durations);

        return [
            'count' => $count,
            'average' => round($totalDuration / $count, 2),
            'min' => min($durations),
            'max' => max($durations),
            'median' => $count % 2 === 0
                ? ($durations[$count / 2 - 1] + $durations[$count / 2]) / 2
                : $durations[floor($count / 2)],
        ];
    }

    /**
     * Assign mission to contract.
     *
     * @param mixed $contract
     */
    public function assignMissionToContract(CompanyMission $mission, $contract): MissionAssignment
    {
        $assignment = new MissionAssignment();
        $assignment->setMission($mission);
        $assignment->setStudent($contract->getStudent());
        $assignment->setStartDate(new DateTime());
        $assignment->setEndDate($this->calculateExpectedEndDate($mission));
        $assignment->setStatus('planifiee');

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        $this->logger->info('Mission assigned to contract', [
            'mission_id' => $mission->getId(),
            'contract_id' => $contract->getId(),
            'assignment_id' => $assignment->getId(),
        ]);

        return $assignment;
    }

    /**
     * Validate assignment prerequisites.
     *
     * @throws RuntimeException
     */
    private function validateAssignmentPrerequisites(CompanyMission $mission, Student $student): void
    {
        $this->logger->debug('Starting assignment prerequisites validation', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'mission_prerequisites_count' => count($mission->getPrerequisites()),
        ]);

        try {
            // Check if student already has an active assignment for this mission
            $this->logger->debug('Checking for existing active assignments', [
                'mission_id' => $mission->getId(),
                'student_id' => $student->getId(),
            ]);

            $existingAssignment = $this->assignmentRepository->findOneBy([
                'mission' => $mission,
                'student' => $student,
                'status' => ['planifiee', 'en_cours'],
            ]);

            if ($existingAssignment) {
                $this->logger->error('Student already has active assignment for mission', [
                    'mission_id' => $mission->getId(),
                    'student_id' => $student->getId(),
                    'existing_assignment_id' => $existingAssignment->getId(),
                    'existing_assignment_status' => $existingAssignment->getStatus(),
                ]);

                throw new RuntimeException('Student already has an active assignment for this mission.');
            }

            $this->logger->debug('No existing active assignments found');

            // Check mission prerequisites
            $prerequisites = $mission->getPrerequisites();
            if (!empty($prerequisites)) {
                $this->logger->debug('Mission has prerequisites - validation required', [
                    'mission_id' => $mission->getId(),
                    'student_id' => $student->getId(),
                    'prerequisites_count' => count($prerequisites),
                    'prerequisites' => $prerequisites,
                ]);

                // This would check if the student meets the prerequisites
                // For now, we'll just log it as the logic needs to be implemented
                $this->logger->info('Mission prerequisites validation needed - implementation required', [
                    'mission_id' => $mission->getId(),
                    'mission_title' => $mission->getTitle(),
                    'student_id' => $student->getId(),
                    'student_name' => $student->getFullName(),
                    'prerequisites' => $prerequisites,
                    'note' => 'Prerequisites validation logic should be implemented based on business rules',
                ]);

            // TODO: Implement actual prerequisite validation logic
            // This could involve checking:
            // - Completed missions
            // - Required skills
            // - Minimum completion rates
            // - Student level/experience
            } else {
                $this->logger->debug('Mission has no prerequisites', [
                    'mission_id' => $mission->getId(),
                ]);
            }

            $this->logger->debug('Assignment prerequisites validation completed successfully', [
                'mission_id' => $mission->getId(),
                'student_id' => $student->getId(),
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime exception during prerequisites validation', [
                'mission_id' => $mission->getId(),
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected exception during prerequisites validation', [
                'mission_id' => $mission->getId(),
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to validate assignment prerequisites: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate intermediate objectives based on mission objectives.
     */
    private function generateIntermediateObjectives(CompanyMission $mission): array
    {
        $this->logger->debug('Starting intermediate objectives generation', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
        ]);

        try {
            $objectives = $mission->getObjectives();
            $this->logger->debug('Retrieved mission objectives', [
                'mission_id' => $mission->getId(),
                'objectives_count' => count($objectives),
                'objectives' => $objectives,
            ]);

            $intermediateObjectives = [];

            foreach ($objectives as $index => $objective) {
                $intermediateObjective = [
                    'id' => $index + 1,
                    'title' => $objective,
                    'completed' => false,
                    'completion_date' => null,
                    'notes' => '',
                ];
                $intermediateObjectives[] = $intermediateObjective;

                $this->logger->debug('Generated intermediate objective', [
                    'mission_id' => $mission->getId(),
                    'objective_index' => $index,
                    'objective_id' => $index + 1,
                    'objective_title' => $objective,
                    'intermediate_objective' => $intermediateObjective,
                ]);
            }

            $this->logger->debug('Intermediate objectives generation completed', [
                'mission_id' => $mission->getId(),
                'total_objectives_generated' => count($intermediateObjectives),
                'intermediate_objectives' => $intermediateObjectives,
            ]);

            return $intermediateObjectives;
        } catch (Exception $e) {
            $this->logger->error('Exception during intermediate objectives generation', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty array as fallback
            $this->logger->warning('Returning empty objectives array as fallback', [
                'mission_id' => $mission->getId(),
            ]);

            return [];
        }
    }

    /**
     * Calculate expected end date based on mission duration.
     */
    private function calculateExpectedEndDate(CompanyMission $mission): DateTime
    {
        $this->logger->debug('Starting expected end date calculation', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
        ]);

        try {
            $duration = $mission->getDuration() ?? 30; // Default 30 days
            $this->logger->debug('Retrieved mission duration', [
                'mission_id' => $mission->getId(),
                'duration' => $duration,
                'duration_provided' => $mission->getDuration() !== null,
                'default_used' => $mission->getDuration() === null,
            ]);

            $startDate = new DateTime();
            $endDate = $startDate->add(new DateInterval("P{$duration}D"));

            $this->logger->debug('Calculated expected end date', [
                'mission_id' => $mission->getId(),
                'start_date' => $startDate->format('Y-m-d'),
                'duration_days' => $duration,
                'end_date' => $endDate->format('Y-m-d'),
                'calculation_method' => "start_date + {$duration} days",
            ]);

            return $endDate;
        } catch (Exception $e) {
            $this->logger->error('Exception during expected end date calculation', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return fallback date (30 days from now)
            $fallbackDate = (new DateTime())->add(new DateInterval('P30D'));
            $this->logger->warning('Returning fallback end date', [
                'mission_id' => $mission->getId(),
                'fallback_date' => $fallbackDate->format('Y-m-d'),
                'fallback_duration' => '30 days',
            ]);

            return $fallbackDate;
        }
    }
}
