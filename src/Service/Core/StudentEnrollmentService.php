<?php

declare(strict_types=1);

namespace App\Service\Core;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Core\StudentProgress;
use App\Entity\Training\Formation;
use App\Entity\Training\SessionRegistration;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Core\StudentProgressRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * StudentEnrollmentService for managing student enrollment business logic.
 *
 * Handles enrollment creation, status management, and automatic StudentProgress
 * creation for the Student Content Access System.
 */
class StudentEnrollmentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StudentEnrollmentRepository $enrollmentRepository,
        private readonly StudentProgressRepository $progressRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Create enrollment from confirmed session registration.
     */
    public function createEnrollmentFromSessionRegistration(
        Student $student,
        SessionRegistration $sessionRegistration,
        string $enrollmentSource = 'manual',
        ?string $adminNotes = null,
    ): StudentEnrollment {
        $this->logger->info('Starting enrollment creation process', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
            'session_registration_id' => $sessionRegistration->getId(),
            'registration_status' => $sessionRegistration->getStatus(),
            'enrollment_source' => $enrollmentSource,
            'admin_notes' => $adminNotes,
            'timestamp' => new DateTimeImmutable(),
        ]);

        try {
            // Validate session registration is confirmed
            if (!$sessionRegistration->isConfirmed()) {
                $this->logger->error('Attempted to create enrollment from unconfirmed session registration', [
                    'student_id' => $student->getId(),
                    'session_registration_id' => $sessionRegistration->getId(),
                    'current_status' => $sessionRegistration->getStatus(),
                    'required_status' => 'confirmed',
                ]);

                throw new InvalidArgumentException('Session registration must be confirmed to create enrollment');
            }

            $this->logger->debug('Session registration validation passed', [
                'session_registration_id' => $sessionRegistration->getId(),
                'status' => $sessionRegistration->getStatus(),
            ]);

            // Check if enrollment already exists
            $this->logger->debug('Checking for existing enrollment', [
                'student_id' => $student->getId(),
                'session_registration_id' => $sessionRegistration->getId(),
            ]);

            $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSessionRegistration(
                $student,
                $sessionRegistration,
            );

            if ($existingEnrollment) {
                $this->logger->warning('Enrollment already exists, cannot create duplicate', [
                    'student_id' => $student->getId(),
                    'session_registration_id' => $sessionRegistration->getId(),
                    'existing_enrollment_id' => $existingEnrollment->getId(),
                    'existing_enrollment_status' => $existingEnrollment->getStatus(),
                    'existing_enrollment_created_at' => $existingEnrollment->getCreatedAt()?->format('Y-m-d H:i:s'),
                ]);

                throw new InvalidArgumentException('Enrollment already exists for this student and session registration');
            }

            $this->logger->debug('No existing enrollment found, proceeding with creation');

            // Create enrollment
            $enrollment = new StudentEnrollment();
            $enrollment->setStudent($student);
            $enrollment->setSessionRegistration($sessionRegistration);
            $enrollment->setStatus(StudentEnrollment::STATUS_ENROLLED);
            $enrollment->setEnrollmentSource($enrollmentSource);
            $enrollment->setAdminNotes($adminNotes);

            $this->logger->debug('Enrollment entity created with basic properties', [
                'enrollment_status' => $enrollment->getStatus(),
                'enrollment_source' => $enrollment->getEnrollmentSource(),
            ]);

            // Create associated StudentProgress
            $formation = $sessionRegistration->getSession()?->getFormation();
            if ($formation) {
                $this->logger->debug('Creating or finding student progress for formation', [
                    'formation_id' => $formation->getId(),
                    'formation_title' => $formation->getTitle(),
                    'student_id' => $student->getId(),
                ]);

                try {
                    $progress = $this->progressRepository->findOrCreateForStudentAndFormation($student, $formation);
                    $enrollment->setProgress($progress);

                    $this->logger->debug('Student progress associated with enrollment', [
                        'progress_id' => $progress->getId(),
                        'progress_completion_percentage' => $progress->getCompletionPercentage(),
                        'progress_engagement_score' => $progress->getEngagementScore(),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to create or find student progress', [
                        'formation_id' => $formation->getId(),
                        'student_id' => $student->getId(),
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'stack_trace' => $e->getTraceAsString(),
                    ]);
                    // Continue without progress association - enrollment can exist without progress
                }
            } else {
                $this->logger->warning('No formation found for session registration', [
                    'session_registration_id' => $sessionRegistration->getId(),
                    'session_id' => $sessionRegistration->getSession()?->getId(),
                ]);
            }

            // Persist enrollment
            $this->logger->debug('Persisting enrollment to database');
            $this->entityManager->persist($enrollment);
            $this->entityManager->flush();

            $this->logger->info('Student enrollment successfully created', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'session_registration_id' => $sessionRegistration->getId(),
                'formation_id' => $formation?->getId(),
                'formation_title' => $formation?->getTitle(),
                'enrollment_source' => $enrollmentSource,
                'enrollment_status' => $enrollment->getStatus(),
                'created_at' => $enrollment->getCreatedAt()?->format('Y-m-d H:i:s'),
                'progress_associated' => $enrollment->getProgress() !== null,
            ]);

            return $enrollment;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for enrollment creation', [
                'student_id' => $student->getId(),
                'session_registration_id' => $sessionRegistration->getId(),
                'error_message' => $e->getMessage(),
                'enrollment_source' => $enrollmentSource,
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during enrollment creation', [
                'student_id' => $student->getId(),
                'session_registration_id' => $sessionRegistration->getId(),
                'enrollment_source' => $enrollmentSource,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to create student enrollment: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create enrollments for all confirmed registrations of a student.
     *
     * @return StudentEnrollment[]
     */
    public function createEnrollmentsForStudentRegistrations(
        Student $student,
        string $enrollmentSource = 'bulk_creation',
    ): array {
        $this->logger->info('Starting bulk enrollment creation for student', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
            'enrollment_source' => $enrollmentSource,
            'timestamp' => new DateTimeImmutable(),
        ]);

        $enrollments = [];
        $stats = [
            'total_registrations_found' => 0,
            'enrollments_created' => 0,
            'enrollments_skipped' => 0,
            'errors_encountered' => 0,
        ];

        try {
            // Find all confirmed session registrations for this student's email
            $this->logger->debug('Searching for confirmed session registrations', [
                'student_email' => $student->getEmail(),
                'required_status' => 'confirmed',
            ]);

            $sessionRegistrations = $this->entityManager->getRepository(SessionRegistration::class)
                ->findBy([
                    'email' => $student->getEmail(),
                    'status' => 'confirmed',
                ])
            ;

            $stats['total_registrations_found'] = count($sessionRegistrations);

            $this->logger->info('Found confirmed session registrations for student', [
                'student_id' => $student->getId(),
                'registrations_count' => count($sessionRegistrations),
                'registration_ids' => array_map(static fn ($reg) => $reg->getId(), $sessionRegistrations),
            ]);

            foreach ($sessionRegistrations as $index => $sessionRegistration) {
                $this->logger->debug('Processing session registration', [
                    'registration_index' => $index + 1,
                    'total_registrations' => count($sessionRegistrations),
                    'session_registration_id' => $sessionRegistration->getId(),
                    'session_id' => $sessionRegistration->getSession()?->getId(),
                    'formation_id' => $sessionRegistration->getSession()?->getFormation()?->getId(),
                    'formation_title' => $sessionRegistration->getSession()?->getFormation()?->getTitle(),
                ]);

                try {
                    // Check if enrollment already exists
                    $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSessionRegistration(
                        $student,
                        $sessionRegistration,
                    );

                    if ($existingEnrollment) {
                        $stats['enrollments_skipped']++;
                        $this->logger->debug('Enrollment already exists, skipping', [
                            'student_id' => $student->getId(),
                            'session_registration_id' => $sessionRegistration->getId(),
                            'existing_enrollment_id' => $existingEnrollment->getId(),
                            'existing_enrollment_status' => $existingEnrollment->getStatus(),
                        ]);

                        continue;
                    }

                    $this->logger->debug('Creating new enrollment for session registration', [
                        'session_registration_id' => $sessionRegistration->getId(),
                    ]);

                    $enrollment = $this->createEnrollmentFromSessionRegistration(
                        $student,
                        $sessionRegistration,
                        $enrollmentSource,
                        'Auto-created from confirmed session registration',
                    );

                    $enrollments[] = $enrollment;
                    $stats['enrollments_created']++;

                    $this->logger->debug('Successfully created enrollment', [
                        'enrollment_id' => $enrollment->getId(),
                        'session_registration_id' => $sessionRegistration->getId(),
                        'formation_title' => $sessionRegistration->getSession()?->getFormation()?->getTitle(),
                    ]);
                } catch (InvalidArgumentException $e) {
                    $stats['errors_encountered']++;
                    $this->logger->warning('Failed to create enrollment for session registration - invalid argument', [
                        'student_id' => $student->getId(),
                        'session_registration_id' => $sessionRegistration->getId(),
                        'error_message' => $e->getMessage(),
                        'formation_title' => $sessionRegistration->getSession()?->getFormation()?->getTitle(),
                    ]);
                } catch (Exception $e) {
                    $stats['errors_encountered']++;
                    $this->logger->error('Unexpected error creating enrollment for session registration', [
                        'student_id' => $student->getId(),
                        'session_registration_id' => $sessionRegistration->getId(),
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'formation_title' => $sessionRegistration->getSession()?->getFormation()?->getTitle(),
                    ]);
                }
            }

            $this->logger->info('Bulk enrollment creation completed for student', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'stats' => $stats,
                'enrollments_created_ids' => array_map(static fn ($enrollment) => $enrollment->getId(), $enrollments),
            ]);

            return $enrollments;
        } catch (Exception $e) {
            $this->logger->critical('Critical error during bulk enrollment creation', [
                'student_id' => $student->getId(),
                'enrollment_source' => $enrollmentSource,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'partial_stats' => $stats,
            ]);

            throw new RuntimeException('Failed to create bulk enrollments for student: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update enrollment status with validation.
     */
    public function updateEnrollmentStatus(
        StudentEnrollment $enrollment,
        string $newStatus,
        ?string $reason = null,
    ): StudentEnrollment {
        $this->logger->info('Starting enrollment status update', [
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()?->getId(),
            'student_email' => $enrollment->getStudent()?->getEmail(),
            'current_status' => $enrollment->getStatus(),
            'new_status' => $newStatus,
            'reason' => $reason,
            'formation_id' => $enrollment->getFormation()?->getId(),
            'formation_title' => $enrollment->getFormation()?->getTitle(),
            'timestamp' => new DateTimeImmutable(),
        ]);

        $oldStatus = $enrollment->getStatus();

        try {
            // Validate status transition
            $this->logger->debug('Validating status transition', [
                'enrollment_id' => $enrollment->getId(),
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
            ]);

            $this->validateStatusTransition($oldStatus, $newStatus);

            $this->logger->debug('Status transition validation passed', [
                'enrollment_id' => $enrollment->getId(),
                'validated_transition' => $oldStatus . ' -> ' . $newStatus,
            ]);

            // Update status based on new status
            switch ($newStatus) {
                case StudentEnrollment::STATUS_COMPLETED:
                    $this->logger->debug('Marking enrollment as completed', [
                        'enrollment_id' => $enrollment->getId(),
                    ]);
                    $enrollment->markCompleted();
                    break;

                case StudentEnrollment::STATUS_DROPPED_OUT:
                    if (!$reason) {
                        $this->logger->error('Dropout reason is required but not provided', [
                            'enrollment_id' => $enrollment->getId(),
                            'student_id' => $enrollment->getStudent()?->getId(),
                            'new_status' => $newStatus,
                        ]);

                        throw new InvalidArgumentException('Dropout reason is required when marking as dropped out');
                    }
                    $this->logger->debug('Marking enrollment as dropped out', [
                        'enrollment_id' => $enrollment->getId(),
                        'reason' => $reason,
                    ]);
                    $enrollment->markDroppedOut($reason);
                    break;

                case StudentEnrollment::STATUS_SUSPENDED:
                    $this->logger->debug('Marking enrollment as suspended', [
                        'enrollment_id' => $enrollment->getId(),
                    ]);
                    $enrollment->markSuspended();
                    break;

                case StudentEnrollment::STATUS_ENROLLED:
                    $this->logger->debug('Reactivating enrollment', [
                        'enrollment_id' => $enrollment->getId(),
                    ]);
                    $enrollment->reactivate();
                    break;

                default:
                    $this->logger->error('Invalid enrollment status provided', [
                        'enrollment_id' => $enrollment->getId(),
                        'invalid_status' => $newStatus,
                        'valid_statuses' => [
                            StudentEnrollment::STATUS_ENROLLED,
                            StudentEnrollment::STATUS_COMPLETED,
                            StudentEnrollment::STATUS_DROPPED_OUT,
                            StudentEnrollment::STATUS_SUSPENDED,
                        ],
                    ]);

                    throw new InvalidArgumentException('Invalid enrollment status: ' . $newStatus);
            }

            $this->logger->debug('Persisting enrollment status changes');
            $this->entityManager->flush();

            $this->logger->info('Enrollment status successfully updated', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $enrollment->getStudent()?->getId(),
                'student_email' => $enrollment->getStudent()?->getEmail(),
                'old_status' => $oldStatus,
                'new_status' => $enrollment->getStatus(),
                'reason' => $reason,
                'formation_title' => $enrollment->getFormation()?->getTitle(),
                'updated_at' => $enrollment->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'completed_at' => $enrollment->getCompletedAt()?->format('Y-m-d H:i:s'),
            ]);

            return $enrollment;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument during enrollment status update', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $enrollment->getStudent()?->getId(),
                'old_status' => $oldStatus,
                'attempted_new_status' => $newStatus,
                'reason' => $reason,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during enrollment status update', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $enrollment->getStudent()?->getId(),
                'old_status' => $oldStatus,
                'attempted_new_status' => $newStatus,
                'reason' => $reason,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to update enrollment status: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if student has access to formation content.
     */
    public function hasStudentAccessToFormation(Student $student, Formation $formation): bool
    {
        $this->logger->debug('Checking student access to formation', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
        ]);

        try {
            $hasAccess = $this->enrollmentRepository->hasStudentAccessToFormation($student, $formation);

            $this->logger->debug('Student formation access check completed', [
                'student_id' => $student->getId(),
                'formation_id' => $formation->getId(),
                'has_access' => $hasAccess,
            ]);

            return $hasAccess;
        } catch (Exception $e) {
            $this->logger->error('Error checking student access to formation', [
                'student_id' => $student->getId(),
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return false on error for security (deny access on error)
            return false;
        }
    }

    /**
     * Get all accessible formations for a student.
     *
     * @return Formation[]
     */
    public function getAccessibleFormationsForStudent(Student $student): array
    {
        $this->logger->debug('Retrieving accessible formations for student', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
        ]);

        try {
            $enrollments = $this->enrollmentRepository->findActiveEnrollmentsByStudent($student);

            $this->logger->debug('Found active enrollments for student', [
                'student_id' => $student->getId(),
                'enrollments_count' => count($enrollments),
                'enrollment_ids' => array_map(static fn ($enrollment) => $enrollment->getId(), $enrollments),
            ]);

            $formations = [];
            $formationDetails = [];

            foreach ($enrollments as $enrollment) {
                $formation = $enrollment->getFormation();
                if ($formation) {
                    $formations[] = $formation;
                    $formationDetails[] = [
                        'formation_id' => $formation->getId(),
                        'formation_title' => $formation->getTitle(),
                        'enrollment_id' => $enrollment->getId(),
                        'enrollment_status' => $enrollment->getStatus(),
                    ];
                }
            }

            $uniqueFormations = array_unique($formations);

            $this->logger->info('Successfully retrieved accessible formations for student', [
                'student_id' => $student->getId(),
                'total_formations' => count($uniqueFormations),
                'formation_details' => $formationDetails,
                'unique_formation_ids' => array_map(static fn ($formation) => $formation->getId(), $uniqueFormations),
            ]);

            return $uniqueFormations;
        } catch (Exception $e) {
            $this->logger->error('Error retrieving accessible formations for student', [
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return empty array on error
            return [];
        }
    }

    /**
     * Bulk create enrollments from confirmed session registrations.
     *
     * @return array{created: int, skipped: int, errors: int}
     */
    public function bulkCreateEnrollmentsFromConfirmedRegistrations(): array
    {
        $this->logger->info('Starting bulk enrollment creation from confirmed session registrations', [
            'timestamp' => new DateTimeImmutable(),
            'operation_type' => 'bulk_enrollment_creation',
        ]);

        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];
        $processedRegistrations = 0;
        $errorDetails = [];

        try {
            $confirmedRegistrations = $this->entityManager->getRepository(SessionRegistration::class)
                ->findBy(['status' => 'confirmed'])
            ;

            $this->logger->info('Found confirmed session registrations for bulk processing', [
                'total_confirmed_registrations' => count($confirmedRegistrations),
                'registration_ids' => array_map(static fn ($reg) => $reg->getId(), $confirmedRegistrations),
            ]);

            foreach ($confirmedRegistrations as $index => $registration) {
                $processedRegistrations++;

                $this->logger->debug('Processing session registration for bulk enrollment', [
                    'registration_index' => $index + 1,
                    'total_registrations' => count($confirmedRegistrations),
                    'session_registration_id' => $registration->getId(),
                    'registration_email' => $registration->getEmail(),
                    'session_id' => $registration->getSession()?->getId(),
                    'formation_id' => $registration->getSession()?->getFormation()?->getId(),
                    'formation_title' => $registration->getSession()?->getFormation()?->getTitle(),
                ]);

                try {
                    // Try to find a matching student by email
                    $student = $this->entityManager->getRepository(Student::class)
                        ->findOneBy(['email' => $registration->getEmail()])
                    ;

                    if (!$student) {
                        $stats['skipped']++;
                        $this->logger->debug('No student found for registration email, skipping', [
                            'email' => $registration->getEmail(),
                            'session_registration_id' => $registration->getId(),
                        ]);

                        continue;
                    }

                    $this->logger->debug('Found matching student for registration', [
                        'student_id' => $student->getId(),
                        'student_email' => $student->getEmail(),
                        'student_name' => $student->getFullName(),
                        'session_registration_id' => $registration->getId(),
                    ]);

                    // Check if enrollment already exists
                    $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSessionRegistration(
                        $student,
                        $registration,
                    );

                    if ($existingEnrollment) {
                        $stats['skipped']++;
                        $this->logger->debug('Enrollment already exists, skipping', [
                            'student_id' => $student->getId(),
                            'session_registration_id' => $registration->getId(),
                            'existing_enrollment_id' => $existingEnrollment->getId(),
                            'existing_enrollment_status' => $existingEnrollment->getStatus(),
                        ]);

                        continue;
                    }

                    // Create enrollment
                    $this->logger->debug('Creating new enrollment from session registration', [
                        'student_id' => $student->getId(),
                        'session_registration_id' => $registration->getId(),
                    ]);

                    $enrollment = $this->createEnrollmentFromSessionRegistration(
                        $student,
                        $registration,
                        'bulk_creation',
                        'Auto-created from bulk enrollment process',
                    );

                    $stats['created']++;

                    $this->logger->debug('Successfully created enrollment in bulk process', [
                        'enrollment_id' => $enrollment->getId(),
                        'student_id' => $student->getId(),
                        'session_registration_id' => $registration->getId(),
                        'formation_title' => $registration->getSession()?->getFormation()?->getTitle(),
                    ]);
                } catch (Exception $e) {
                    $stats['errors']++;
                    $errorDetail = [
                        'session_registration_id' => $registration->getId(),
                        'email' => $registration->getEmail(),
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'formation_title' => $registration->getSession()?->getFormation()?->getTitle(),
                    ];
                    $errorDetails[] = $errorDetail;

                    $this->logger->error('Error creating enrollment from session registration in bulk process', array_merge($errorDetail, [
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]));
                }
            }

            $this->logger->info('Bulk enrollment creation completed successfully', [
                'total_processed' => $processedRegistrations,
                'final_stats' => $stats,
                'error_details' => $errorDetails,
                'success_rate' => $processedRegistrations > 0 ? ($stats['created'] / $processedRegistrations) * 100 : 0,
            ]);

            return $stats;
        } catch (Exception $e) {
            $this->logger->critical('Critical error during bulk enrollment creation', [
                'processed_registrations' => $processedRegistrations,
                'partial_stats' => $stats,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'error_details' => $errorDetails,
            ]);

            throw new RuntimeException('Critical failure in bulk enrollment creation: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Find enrollments that need attention (at-risk, overdue, etc.).
     *
     * @return array{at_risk: StudentEnrollment[], overdue: StudentEnrollment[], without_progress: StudentEnrollment[]}
     */
    public function findEnrollmentsNeedingAttention(): array
    {
        $this->logger->info('Starting analysis of enrollments needing attention', [
            'timestamp' => new DateTimeImmutable(),
            'analysis_types' => ['at_risk', 'overdue', 'without_progress'],
        ]);

        try {
            $this->logger->debug('Querying for at-risk enrollments');
            $atRiskEnrollments = $this->enrollmentRepository->findAtRiskEnrollments();

            $this->logger->debug('Querying for overdue enrollments');
            $overdueEnrollments = $this->enrollmentRepository->findOverdueEnrollments();

            $this->logger->debug('Querying for enrollments without progress');
            $enrollmentsWithoutProgress = $this->enrollmentRepository->findEnrollmentsWithoutProgress();

            $results = [
                'at_risk' => $atRiskEnrollments,
                'overdue' => $overdueEnrollments,
                'without_progress' => $enrollmentsWithoutProgress,
            ];

            $this->logger->info('Successfully analyzed enrollments needing attention', [
                'at_risk_count' => count($atRiskEnrollments),
                'overdue_count' => count($overdueEnrollments),
                'without_progress_count' => count($enrollmentsWithoutProgress),
                'total_needing_attention' => count($atRiskEnrollments) + count($overdueEnrollments) + count($enrollmentsWithoutProgress),
                'at_risk_enrollment_ids' => array_map(static fn ($e) => $e->getId(), $atRiskEnrollments),
                'overdue_enrollment_ids' => array_map(static fn ($e) => $e->getId(), $overdueEnrollments),
                'without_progress_enrollment_ids' => array_map(static fn ($e) => $e->getId(), $enrollmentsWithoutProgress),
            ]);

            return $results;
        } catch (Exception $e) {
            $this->logger->error('Error analyzing enrollments needing attention', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return empty arrays on error
            return [
                'at_risk' => [],
                'overdue' => [],
                'without_progress' => [],
            ];
        }
    }

    /**
     * Get enrollment analytics for dashboard.
     */
    public function getEnrollmentAnalytics(): array
    {
        $this->logger->info('Starting enrollment analytics generation for dashboard', [
            'timestamp' => new DateTimeImmutable(),
            'analytics_components' => ['stats', 'at_risk_analysis', 'recent_enrollments'],
        ]);

        try {
            $this->logger->debug('Retrieving enrollment statistics');
            $stats = $this->enrollmentRepository->getEnrollmentStats();

            $this->logger->debug('Analyzing enrollments needing attention');
            $needingAttention = $this->findEnrollmentsNeedingAttention();

            $this->logger->debug('Retrieving recent enrollments');
            $recentEnrollments = $this->enrollmentRepository->findRecentEnrollments(5);

            $analytics = [
                'stats' => $stats,
                'at_risk_count' => count($needingAttention['at_risk']),
                'overdue_count' => count($needingAttention['overdue']),
                'without_progress_count' => count($needingAttention['without_progress']),
                'recent_enrollments' => $recentEnrollments,
            ];

            $this->logger->info('Successfully generated enrollment analytics', [
                'stats_summary' => $stats,
                'attention_summary' => [
                    'at_risk_count' => $analytics['at_risk_count'],
                    'overdue_count' => $analytics['overdue_count'],
                    'without_progress_count' => $analytics['without_progress_count'],
                ],
                'recent_enrollments_count' => count($recentEnrollments),
                'recent_enrollment_ids' => array_map(static fn ($e) => $e->getId(), $recentEnrollments),
            ]);

            return $analytics;
        } catch (Exception $e) {
            $this->logger->error('Error generating enrollment analytics', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return basic fallback analytics on error
            return [
                'stats' => [],
                'at_risk_count' => 0,
                'overdue_count' => 0,
                'without_progress_count' => 0,
                'recent_enrollments' => [],
                'error' => true,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate status transition rules.
     */
    private function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
        $this->logger->debug('Validating enrollment status transition', [
            'current_status' => $currentStatus,
            'new_status' => $newStatus,
        ]);

        try {
            // Define allowed transitions
            $allowedTransitions = [
                StudentEnrollment::STATUS_ENROLLED => [
                    StudentEnrollment::STATUS_COMPLETED,
                    StudentEnrollment::STATUS_DROPPED_OUT,
                    StudentEnrollment::STATUS_SUSPENDED,
                ],
                StudentEnrollment::STATUS_SUSPENDED => [
                    StudentEnrollment::STATUS_ENROLLED,
                    StudentEnrollment::STATUS_DROPPED_OUT,
                ],
                StudentEnrollment::STATUS_COMPLETED => [
                    // Generally final, but could be reopened in special cases
                ],
                StudentEnrollment::STATUS_DROPPED_OUT => [
                    // Generally final, but could be reactivated in special cases
                    StudentEnrollment::STATUS_ENROLLED,
                ],
            ];

            $allowed = $allowedTransitions[$currentStatus] ?? [];

            $this->logger->debug('Status transition validation details', [
                'current_status' => $currentStatus,
                'new_status' => $newStatus,
                'allowed_transitions' => $allowed,
                'transition_valid' => in_array($newStatus, $allowed, true),
            ]);

            if (!in_array($newStatus, $allowed, true)) {
                $this->logger->warning('Invalid status transition attempted', [
                    'current_status' => $currentStatus,
                    'attempted_new_status' => $newStatus,
                    'allowed_transitions' => $allowed,
                    'all_allowed_transitions' => $allowedTransitions,
                ]);

                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid status transition from "%s" to "%s"',
                        $currentStatus,
                        $newStatus,
                    ),
                );
            }

            $this->logger->debug('Status transition validation passed', [
                'current_status' => $currentStatus,
                'new_status' => $newStatus,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Status transition validation failed', [
                'current_status' => $currentStatus,
                'new_status' => $newStatus,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during status transition validation', [
                'current_status' => $currentStatus,
                'new_status' => $newStatus,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to validate status transition: ' . $e->getMessage(), 0, $e);
        }
    }
}
