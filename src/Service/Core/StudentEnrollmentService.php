<?php

declare(strict_types=1);

namespace App\Service\Core;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Core\StudentProgress;
use App\Entity\Training\SessionRegistration;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Core\StudentProgressRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

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
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create enrollment from confirmed session registration.
     */
    public function createEnrollmentFromSessionRegistration(
        Student $student,
        SessionRegistration $sessionRegistration,
        string $enrollmentSource = 'manual',
        ?string $adminNotes = null
    ): StudentEnrollment {
        // Validate session registration is confirmed
        if (!$sessionRegistration->isConfirmed()) {
            throw new InvalidArgumentException('Session registration must be confirmed to create enrollment');
        }

        // Check if enrollment already exists
        $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSessionRegistration(
            $student,
            $sessionRegistration
        );

        if ($existingEnrollment) {
            throw new InvalidArgumentException('Enrollment already exists for this student and session registration');
        }

        // Create enrollment
        $enrollment = new StudentEnrollment();
        $enrollment->setStudent($student);
        $enrollment->setSessionRegistration($sessionRegistration);
        $enrollment->setStatus(StudentEnrollment::STATUS_ENROLLED);
        $enrollment->setEnrollmentSource($enrollmentSource);
        $enrollment->setAdminNotes($adminNotes);

        // Create associated StudentProgress
        $formation = $sessionRegistration->getSession()?->getFormation();
        if ($formation) {
            $progress = $this->progressRepository->findOrCreateForStudentAndFormation($student, $formation);
            $enrollment->setProgress($progress);
        }

        // Persist enrollment
        $this->entityManager->persist($enrollment);
        $this->entityManager->flush();

        $this->logger->info('Student enrollment created', [
            'student_id' => $student->getId(),
            'session_registration_id' => $sessionRegistration->getId(),
            'formation_id' => $formation?->getId(),
            'enrollment_source' => $enrollmentSource,
        ]);

        return $enrollment;
    }

    /**
     * Create enrollments for all confirmed registrations of a student.
     *
     * @return StudentEnrollment[]
     */
    public function createEnrollmentsForStudentRegistrations(
        Student $student,
        string $enrollmentSource = 'bulk_creation'
    ): array {
        // Find all confirmed session registrations for this student's email
        $sessionRegistrations = $this->entityManager->getRepository(SessionRegistration::class)
            ->findBy([
                'email' => $student->getEmail(),
                'status' => 'confirmed',
            ]);

        $enrollments = [];

        foreach ($sessionRegistrations as $sessionRegistration) {
            try {
                // Check if enrollment already exists
                $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSessionRegistration(
                    $student,
                    $sessionRegistration
                );

                if (!$existingEnrollment) {
                    $enrollment = $this->createEnrollmentFromSessionRegistration(
                        $student,
                        $sessionRegistration,
                        $enrollmentSource,
                        'Auto-created from confirmed session registration'
                    );
                    $enrollments[] = $enrollment;
                }
            } catch (InvalidArgumentException $e) {
                $this->logger->warning('Failed to create enrollment for session registration', [
                    'student_id' => $student->getId(),
                    'session_registration_id' => $sessionRegistration->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $enrollments;
    }

    /**
     * Update enrollment status with validation.
     */
    public function updateEnrollmentStatus(
        StudentEnrollment $enrollment,
        string $newStatus,
        ?string $reason = null
    ): StudentEnrollment {
        $oldStatus = $enrollment->getStatus();

        // Validate status transition
        $this->validateStatusTransition($oldStatus, $newStatus);

        // Update status based on new status
        switch ($newStatus) {
            case StudentEnrollment::STATUS_COMPLETED:
                $enrollment->markCompleted();
                break;

            case StudentEnrollment::STATUS_DROPPED_OUT:
                if (!$reason) {
                    throw new InvalidArgumentException('Dropout reason is required when marking as dropped out');
                }
                $enrollment->markDroppedOut($reason);
                break;

            case StudentEnrollment::STATUS_SUSPENDED:
                $enrollment->markSuspended();
                break;

            case StudentEnrollment::STATUS_ENROLLED:
                $enrollment->reactivate();
                break;

            default:
                throw new InvalidArgumentException('Invalid enrollment status: ' . $newStatus);
        }

        $this->entityManager->flush();

        $this->logger->info('Enrollment status updated', [
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()?->getId(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);

        return $enrollment;
    }

    /**
     * Check if student has access to formation content.
     */
    public function hasStudentAccessToFormation(Student $student, \App\Entity\Training\Formation $formation): bool
    {
        return $this->enrollmentRepository->hasStudentAccessToFormation($student, $formation);
    }

    /**
     * Get all accessible formations for a student.
     *
     * @return \App\Entity\Training\Formation[]
     */
    public function getAccessibleFormationsForStudent(Student $student): array
    {
        $enrollments = $this->enrollmentRepository->findActiveEnrollmentsByStudent($student);
        
        $formations = [];
        foreach ($enrollments as $enrollment) {
            $formation = $enrollment->getFormation();
            if ($formation) {
                $formations[] = $formation;
            }
        }

        return array_unique($formations);
    }

    /**
     * Bulk create enrollments from confirmed session registrations.
     *
     * @return array{created: int, skipped: int, errors: int}
     */
    public function bulkCreateEnrollmentsFromConfirmedRegistrations(): array
    {
        $confirmedRegistrations = $this->entityManager->getRepository(SessionRegistration::class)
            ->findBy(['status' => 'confirmed']);

        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($confirmedRegistrations as $registration) {
            try {
                // Try to find a matching student by email
                $student = $this->entityManager->getRepository(Student::class)
                    ->findOneBy(['email' => $registration->getEmail()]);

                if (!$student) {
                    $stats['skipped']++;
                    $this->logger->debug('No student found for email', [
                        'email' => $registration->getEmail(),
                        'session_registration_id' => $registration->getId(),
                    ]);
                    continue;
                }

                // Check if enrollment already exists
                $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSessionRegistration(
                    $student,
                    $registration
                );

                if ($existingEnrollment) {
                    $stats['skipped']++;
                    continue;
                }

                // Create enrollment
                $this->createEnrollmentFromSessionRegistration(
                    $student,
                    $registration,
                    'bulk_creation',
                    'Auto-created from bulk enrollment process'
                );

                $stats['created']++;

            } catch (\Exception $e) {
                $stats['errors']++;
                $this->logger->error('Error creating enrollment from session registration', [
                    'session_registration_id' => $registration->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Bulk enrollment creation completed', $stats);

        return $stats;
    }

    /**
     * Find enrollments that need attention (at-risk, overdue, etc.).
     *
     * @return array{at_risk: StudentEnrollment[], overdue: StudentEnrollment[], without_progress: StudentEnrollment[]}
     */
    public function findEnrollmentsNeedingAttention(): array
    {
        return [
            'at_risk' => $this->enrollmentRepository->findAtRiskEnrollments(),
            'overdue' => $this->enrollmentRepository->findOverdueEnrollments(),
            'without_progress' => $this->enrollmentRepository->findEnrollmentsWithoutProgress(),
        ];
    }

    /**
     * Get enrollment analytics for dashboard.
     */
    public function getEnrollmentAnalytics(): array
    {
        $stats = $this->enrollmentRepository->getEnrollmentStats();
        $needingAttention = $this->findEnrollmentsNeedingAttention();

        return [
            'stats' => $stats,
            'at_risk_count' => count($needingAttention['at_risk']),
            'overdue_count' => count($needingAttention['overdue']),
            'without_progress_count' => count($needingAttention['without_progress']),
            'recent_enrollments' => $this->enrollmentRepository->findRecentEnrollments(5),
        ];
    }

    /**
     * Validate status transition rules.
     */
    private function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
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

        if (!in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid status transition from "%s" to "%s"',
                    $currentStatus,
                    $newStatus
                )
            );
        }
    }
}
