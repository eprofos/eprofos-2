<?php

declare(strict_types=1);

namespace App\Service\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Core\StudentProgress;
use App\Entity\Training\Session;
use App\Entity\Training\SessionRegistration;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Training\SessionRegistrationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * StudentEnrollmentService handles the linking of students to session registrations
 * and manages the enrollment process, including automatic and manual linking.
 *
 * This service is critical for the Student Content Access System, enabling
 * students to access training content based on their session enrollments.
 */
class StudentEnrollmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentEnrollmentRepository $enrollmentRepository,
        private SessionRegistrationRepository $sessionRegistrationRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Link a student to a session registration and create enrollment.
     *
     * @throws \Exception If enrollment validation fails
     */
    public function linkStudentToSessionRegistration(Student $student, SessionRegistration $registration): StudentEnrollment
    {
        $this->logger->info('Starting student enrollment linking process', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
            'registration_id' => $registration->getId(),
            'session_id' => $registration->getSession()->getId(),
            'formation_id' => $registration->getSession()->getFormation()->getId(),
            'formation_title' => $registration->getSession()->getFormation()->getTitle(),
        ]);

        try {
            // Validate enrollment eligibility
            $this->logger->debug('Validating enrollment eligibility', [
                'student_id' => $student->getId(),
                'session_id' => $registration->getSession()->getId(),
            ]);

            if (!$this->validateEnrollmentEligibility($student, $registration->getSession())) {
                $this->logger->warning('Student enrollment eligibility validation failed', [
                    'student_id' => $student->getId(),
                    'student_email' => $student->getEmail(),
                    'session_id' => $registration->getSession()->getId(),
                    'student_active' => $student->isActive(),
                    'session_end_date' => $registration->getSession()->getEndDate()?->format('Y-m-d H:i:s'),
                    'session_capacity' => $registration->getSession()->getMaxCapacity(),
                ]);
                throw new \Exception('Student is not eligible for enrollment in this session');
            }

            // Check if registration is already linked
            $this->logger->debug('Checking if registration is already linked', [
                'registration_id' => $registration->getId(),
                'has_linked_student' => $registration->hasLinkedStudent(),
                'linked_student_id' => $registration->getLinkedStudent()?->getId(),
            ]);

            if ($registration->hasLinkedStudent()) {
                $this->logger->warning('Registration already linked to another student', [
                    'registration_id' => $registration->getId(),
                    'linked_student_id' => $registration->getLinkedStudent()->getId(),
                    'linked_student_email' => $registration->getLinkedStudent()->getEmail(),
                    'attempting_student_id' => $student->getId(),
                ]);
                throw new \Exception('Session registration is already linked to another student');
            }

            // Check for duplicate enrollment
            $this->logger->debug('Checking for existing enrollment', [
                'student_id' => $student->getId(),
                'registration_id' => $registration->getId(),
            ]);

            $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSessionRegistration(
                $student,
                $registration
            );

            if ($existingEnrollment !== null) {
                $this->logger->warning('Student already enrolled in this session', [
                    'student_id' => $student->getId(),
                    'registration_id' => $registration->getId(),
                    'existing_enrollment_id' => $existingEnrollment->getId(),
                    'existing_enrollment_status' => $existingEnrollment->getStatus(),
                ]);
                throw new \Exception('Student is already enrolled in this session');
            }

            // Create the enrollment
            $this->logger->debug('Creating new student enrollment', [
                'student_id' => $student->getId(),
                'registration_id' => $registration->getId(),
                'enrollment_source' => 'manual_link',
            ]);

            $enrollment = new StudentEnrollment();
            $enrollment->setStudent($student);
            $enrollment->setSessionRegistration($registration);
            $enrollment->setEnrollmentSource('manual_link');
            $enrollment->setAdminNotes('Linked via admin interface or automatic email matching');

            // Create associated StudentProgress
            $this->logger->debug('Creating associated student progress', [
                'student_id' => $student->getId(),
                'formation_id' => $registration->getSession()->getFormation()->getId(),
            ]);

            $progress = new StudentProgress();
            $progress->setStudent($student);
            $progress->setFormation($registration->getSession()->getFormation());
            $enrollment->setProgress($progress);

            // Link the registration to the student
            $this->logger->debug('Linking registration to student', [
                'registration_id' => $registration->getId(),
                'student_id' => $student->getId(),
            ]);

            $registration->linkStudent($student);

            // Persist entities
            $this->logger->debug('Persisting entities to database', [
                'enrollment_id' => 'new',
                'progress_id' => 'new',
                'registration_id' => $registration->getId(),
            ]);

            $this->entityManager->persist($enrollment);
            $this->entityManager->persist($progress);
            $this->entityManager->persist($registration);
            $this->entityManager->flush();

            $this->logger->info('Successfully persisted enrollment entities', [
                'enrollment_id' => $enrollment->getId(),
                'progress_id' => $progress->getId(),
                'registration_id' => $registration->getId(),
                'student_id' => $student->getId(),
            ]);

            // Send enrollment notification
            try {
                $this->logger->debug('Sending enrollment notification email', [
                    'enrollment_id' => $enrollment->getId(),
                    'student_email' => $student->getEmail(),
                ]);
                $this->sendEnrollmentNotification($enrollment);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send enrollment notification, but enrollment was successful', [
                    'enrollment_id' => $enrollment->getId(),
                    'student_email' => $student->getEmail(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // Log the successful action
            $this->logger->info('Student successfully linked to session registration', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'student_name' => $student->getFullName(),
                'registration_id' => $registration->getId(),
                'session_id' => $registration->getSession()->getId(),
                'formation_id' => $registration->getSession()->getFormation()->getId(),
                'formation_title' => $registration->getSession()->getFormation()->getTitle(),
                'enrollment_source' => $enrollment->getEnrollmentSource(),
            ]);

            return $enrollment;

        } catch (\Exception $e) {
            $this->logger->error('Failed to link student to session registration', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'registration_id' => $registration->getId(),
                'session_id' => $registration->getSession()->getId(),
                'formation_id' => $registration->getSession()->getFormation()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Automatically link student to session registrations by email matching.
     *
     * @return StudentEnrollment[] Array of created enrollments
     */
    public function autoLinkByEmail(Student $student): array
    {
        $this->logger->info('Starting automatic email linking process', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
        ]);

        $enrollments = [];

        try {
            // Find matching unlinked session registrations by email
            $this->logger->debug('Searching for unlinked confirmed registrations by email', [
                'student_email' => $student->getEmail(),
            ]);

            $registrations = $this->sessionRegistrationRepository->findUnlinkedConfirmedByEmail(
                $student->getEmail()
            );

            $this->logger->info('Found potential registrations for auto-linking', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'found_registrations_count' => count($registrations),
                'registration_ids' => array_map(fn($r) => $r->getId(), $registrations),
            ]);

            foreach ($registrations as $registration) {
                $this->logger->debug('Processing registration for auto-linking', [
                    'student_id' => $student->getId(),
                    'registration_id' => $registration->getId(),
                    'session_id' => $registration->getSession()->getId(),
                    'formation_id' => $registration->getSession()->getFormation()->getId(),
                    'formation_title' => $registration->getSession()->getFormation()->getTitle(),
                ]);

                try {
                    // Skip if enrollment already exists for this session
                    $this->logger->debug('Checking for existing enrollment for this session', [
                        'student_id' => $student->getId(),
                        'session_id' => $registration->getSession()->getId(),
                    ]);

                    $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSession(
                        $student,
                        $registration->getSession()
                    );

                    if ($existingEnrollment !== null) {
                        $this->logger->warning('Skipping auto-link: existing enrollment found for session', [
                            'student_id' => $student->getId(),
                            'session_id' => $registration->getSession()->getId(),
                            'existing_enrollment_id' => $existingEnrollment->getId(),
                            'existing_enrollment_status' => $existingEnrollment->getStatus(),
                            'registration_id' => $registration->getId(),
                        ]);
                        continue;
                    }

                    // Validate enrollment eligibility
                    if (!$this->validateEnrollmentEligibility($student, $registration->getSession())) {
                        $this->logger->warning('Skipping auto-link: student not eligible for session', [
                            'student_id' => $student->getId(),
                            'session_id' => $registration->getSession()->getId(),
                            'registration_id' => $registration->getId(),
                            'student_active' => $student->isActive(),
                            'session_end_date' => $registration->getSession()->getEndDate()?->format('Y-m-d H:i:s'),
                        ]);
                        continue;
                    }

                    // Create enrollment with automatic source
                    $this->logger->debug('Creating auto-linked enrollment', [
                        'student_id' => $student->getId(),
                        'registration_id' => $registration->getId(),
                        'enrollment_source' => 'auto_email_match',
                    ]);

                    $enrollment = new StudentEnrollment();
                    $enrollment->setStudent($student);
                    $enrollment->setSessionRegistration($registration);
                    $enrollment->setEnrollmentSource('auto_email_match');
                    $enrollment->setAdminNotes('Automatically linked by email matching');

                    // Create associated StudentProgress
                    $this->logger->debug('Creating associated student progress for auto-link', [
                        'student_id' => $student->getId(),
                        'formation_id' => $registration->getSession()->getFormation()->getId(),
                    ]);

                    $progress = new StudentProgress();
                    $progress->setStudent($student);
                    $progress->setFormation($registration->getSession()->getFormation());
                    $enrollment->setProgress($progress);

                    // Link the registration to the student
                    $this->logger->debug('Linking registration to student for auto-link', [
                        'registration_id' => $registration->getId(),
                        'student_id' => $student->getId(),
                    ]);

                    $registration->linkStudent($student);

                    // Persist entities
                    $this->logger->debug('Persisting auto-linked entities', [
                        'enrollment_id' => 'new',
                        'progress_id' => 'new',
                        'registration_id' => $registration->getId(),
                    ]);

                    $this->entityManager->persist($enrollment);
                    $this->entityManager->persist($progress);
                    $this->entityManager->persist($registration);

                    $enrollments[] = $enrollment;

                    $this->logger->info('Successfully auto-linked student to session registration by email', [
                        'student_id' => $student->getId(),
                        'student_email' => $student->getEmail(),
                        'registration_id' => $registration->getId(),
                        'session_id' => $registration->getSession()->getId(),
                        'formation_id' => $registration->getSession()->getFormation()->getId(),
                        'formation_title' => $registration->getSession()->getFormation()->getTitle(),
                        'enrollment_source' => 'auto_email_match',
                    ]);

                } catch (\Exception $e) {
                    $this->logger->error('Failed to auto-link student to session registration', [
                        'student_id' => $student->getId(),
                        'student_email' => $student->getEmail(),
                        'registration_id' => $registration->getId(),
                        'session_id' => $registration->getSession()->getId(),
                        'formation_id' => $registration->getSession()->getFormation()->getId(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue with next registration instead of breaking the whole process
                }
            }

            if (!empty($enrollments)) {
                $this->logger->info('Flushing auto-linked enrollments to database', [
                    'student_id' => $student->getId(),
                    'enrollments_count' => count($enrollments),
                    'enrollment_ids' => array_map(fn($e) => $e->getId(), $enrollments),
                ]);

                $this->entityManager->flush();

                $this->logger->info('Successfully flushed auto-linked enrollments', [
                    'student_id' => $student->getId(),
                    'enrollments_count' => count($enrollments),
                ]);

                // Send notifications for all new enrollments
                foreach ($enrollments as $enrollment) {
                    try {
                        $this->logger->debug('Sending notification for auto-linked enrollment', [
                            'enrollment_id' => $enrollment->getId(),
                            'student_email' => $student->getEmail(),
                        ]);
                        $this->sendEnrollmentNotification($enrollment);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to send notification for auto-linked enrollment', [
                            'enrollment_id' => $enrollment->getId(),
                            'student_email' => $student->getEmail(),
                            'error_message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            } else {
                $this->logger->info('No enrollments created during auto-linking process', [
                    'student_id' => $student->getId(),
                    'student_email' => $student->getEmail(),
                    'registrations_found' => count($registrations),
                ]);
            }

            $this->logger->info('Completed automatic email linking process', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'total_registrations_found' => count($registrations),
                'successful_enrollments' => count($enrollments),
                'enrollment_ids' => array_map(fn($e) => $e->getId(), $enrollments),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Critical error during automatic email linking process', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'enrollments_created_before_error' => count($enrollments),
            ]);
            throw $e;
        }

        return $enrollments;
    }

    /**
     * Bulk enroll students in a session.
     *
     * @param Student[] $students
     * @return array Array with 'success' and 'failed' counts and details
     */
    public function bulkEnrollStudents(Session $session, array $students, string $adminNotes = '', bool $notifyStudents = false): array
    {
        $this->logger->info('Starting bulk enrollment process', [
            'session_id' => $session->getId(),
            'formation_id' => $session->getFormation()->getId(),
            'formation_title' => $session->getFormation()->getTitle(),
            'students_count' => count($students),
            'student_ids' => array_map(fn($s) => $s->getId(), $students),
            'admin_notes' => $adminNotes,
            'notify_students' => $notifyStudents,
        ]);

        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        try {
            foreach ($students as $index => $student) {
                $this->logger->debug('Processing student for bulk enrollment', [
                    'student_index' => $index + 1,
                    'total_students' => count($students),
                    'student_id' => $student->getId(),
                    'student_email' => $student->getEmail(),
                    'student_name' => $student->getFullName(),
                    'session_id' => $session->getId(),
                ]);

                try {
                    // Check if student already has an enrollment for this session
                    $this->logger->debug('Checking for existing enrollment in bulk process', [
                        'student_id' => $student->getId(),
                        'session_id' => $session->getId(),
                    ]);

                    $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSession(
                        $student,
                        $session
                    );

                    if ($existingEnrollment !== null) {
                        $this->logger->warning('Student already enrolled in session during bulk enrollment', [
                            'student_id' => $student->getId(),
                            'student_name' => $student->getFullName(),
                            'session_id' => $session->getId(),
                            'existing_enrollment_id' => $existingEnrollment->getId(),
                            'existing_enrollment_status' => $existingEnrollment->getStatus(),
                        ]);

                        $results['failed']++;
                        $results['details'][] = [
                            'student' => $student->getFullName(),
                            'error' => 'Already enrolled in this session',
                        ];
                        continue;
                    }

                    // Validate enrollment eligibility
                    if (!$this->validateEnrollmentEligibility($student, $session)) {
                        $this->logger->warning('Student not eligible for bulk enrollment', [
                            'student_id' => $student->getId(),
                            'student_name' => $student->getFullName(),
                            'session_id' => $session->getId(),
                            'student_active' => $student->isActive(),
                            'session_end_date' => $session->getEndDate()?->format('Y-m-d H:i:s'),
                        ]);

                        $results['failed']++;
                        $results['details'][] = [
                            'student' => $student->getFullName(),
                            'error' => 'Student not eligible for enrollment',
                        ];
                        continue;
                    }

                    // Create a session registration for bulk enrollment
                    $this->logger->debug('Creating session registration for bulk enrollment', [
                        'student_id' => $student->getId(),
                        'session_id' => $session->getId(),
                    ]);

                    $registration = new SessionRegistration();
                    $registration->setFirstName($student->getFirstName());
                    $registration->setLastName($student->getLastName());
                    $registration->setEmail($student->getEmail());
                    $registration->setPhone($student->getPhone());
                    $registration->setCompany($student->getCompany());
                    $registration->setSession($session);
                    $registration->setStatus('confirmed');
                    $registration->confirm();
                    $registration->linkStudent($student);

                    // Create the enrollment
                    $this->logger->debug('Creating enrollment for bulk process', [
                        'student_id' => $student->getId(),
                        'session_id' => $session->getId(),
                        'enrollment_source' => 'bulk_admin',
                    ]);

                    $enrollment = new StudentEnrollment();
                    $enrollment->setStudent($student);
                    $enrollment->setSessionRegistration($registration);
                    $enrollment->setEnrollmentSource('bulk_admin');
                    $enrollment->setAdminNotes($adminNotes ?: 'Bulk enrollment via admin interface');

                    // Create associated StudentProgress
                    $this->logger->debug('Creating student progress for bulk enrollment', [
                        'student_id' => $student->getId(),
                        'formation_id' => $session->getFormation()->getId(),
                    ]);

                    $progress = new StudentProgress();
                    $progress->setStudent($student);
                    $progress->setFormation($session->getFormation());
                    $enrollment->setProgress($progress);

                    // Persist entities
                    $this->logger->debug('Persisting bulk enrollment entities', [
                        'student_id' => $student->getId(),
                        'registration_id' => 'new',
                        'enrollment_id' => 'new',
                        'progress_id' => 'new',
                    ]);

                    $this->entityManager->persist($registration);
                    $this->entityManager->persist($enrollment);
                    $this->entityManager->persist($progress);

                    $results['success']++;
                    $results['details'][] = [
                        'student' => $student->getFullName(),
                        'status' => 'success',
                    ];

                    // Send notification if requested
                    if ($notifyStudents) {
                        try {
                            $this->logger->debug('Sending notification for bulk enrolled student', [
                                'student_id' => $student->getId(),
                                'student_email' => $student->getEmail(),
                                'enrollment_id' => 'new',
                            ]);
                            $this->sendEnrollmentNotification($enrollment);
                        } catch (\Exception $e) {
                            $this->logger->error('Failed to send notification for bulk enrolled student', [
                                'student_id' => $student->getId(),
                                'student_email' => $student->getEmail(),
                                'error_message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }

                    $this->logger->info('Successfully bulk enrolled student in session', [
                        'student_id' => $student->getId(),
                        'student_name' => $student->getFullName(),
                        'student_email' => $student->getEmail(),
                        'session_id' => $session->getId(),
                        'formation_id' => $session->getFormation()->getId(),
                        'registration_id' => $registration->getId(),
                        'enrollment_source' => 'bulk_admin',
                        'notification_sent' => $notifyStudents,
                    ]);

                } catch (\Exception $e) {
                    $this->logger->error('Failed to bulk enroll individual student', [
                        'student_id' => $student->getId(),
                        'student_name' => $student->getFullName(),
                        'student_email' => $student->getEmail(),
                        'session_id' => $session->getId(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $results['failed']++;
                    $results['details'][] = [
                        'student' => $student->getFullName(),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $this->logger->info('Flushing bulk enrollment changes to database', [
                'session_id' => $session->getId(),
                'success_count' => $results['success'],
                'failed_count' => $results['failed'],
                'total_processed' => count($students),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Successfully completed bulk enrollment process', [
                'session_id' => $session->getId(),
                'formation_id' => $session->getFormation()->getId(),
                'formation_title' => $session->getFormation()->getTitle(),
                'total_students' => count($students),
                'successful_enrollments' => $results['success'],
                'failed_enrollments' => $results['failed'],
                'notifications_enabled' => $notifyStudents,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Critical error during bulk enrollment process', [
                'session_id' => $session->getId(),
                'formation_id' => $session->getFormation()->getId(),
                'total_students' => count($students),
                'processed_before_error' => $results['success'] + $results['failed'],
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Bulk update enrollment status.
     *
     * @param StudentEnrollment[] $enrollments
     */
    public function bulkUpdateStatus(array $enrollments, string $newStatus, string $dropoutReason = '', string $adminNotes = '', bool $notifyStudents = false): array
    {
        $this->logger->info('Starting bulk status update process', [
            'enrollments_count' => count($enrollments),
            'enrollment_ids' => array_map(fn($e) => $e->getId(), $enrollments),
            'new_status' => $newStatus,
            'dropout_reason' => $dropoutReason,
            'admin_notes' => $adminNotes,
            'notify_students' => $notifyStudents,
        ]);

        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        try {
            foreach ($enrollments as $index => $enrollment) {
                $this->logger->debug('Processing enrollment for bulk status update', [
                    'enrollment_index' => $index + 1,
                    'total_enrollments' => count($enrollments),
                    'enrollment_id' => $enrollment->getId(),
                    'student_id' => $enrollment->getStudent()->getId(),
                    'student_name' => $enrollment->getStudent()->getFullName(),
                    'current_status' => $enrollment->getStatus(),
                    'new_status' => $newStatus,
                    'formation_id' => $enrollment->getFormation()->getId(),
                ]);

                try {
                    $oldStatus = $enrollment->getStatus();
                    
                    $this->logger->debug('Updating enrollment status', [
                        'enrollment_id' => $enrollment->getId(),
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ]);

                    $enrollment->setStatus($newStatus);

                    if ($newStatus === StudentEnrollment::STATUS_DROPPED_OUT && $dropoutReason) {
                        $this->logger->debug('Setting dropout reason for enrollment', [
                            'enrollment_id' => $enrollment->getId(),
                            'dropout_reason' => $dropoutReason,
                        ]);
                        $enrollment->setDropoutReason($dropoutReason);
                    }

                    if ($adminNotes) {
                        $currentNotes = $enrollment->getAdminNotes() ?: '';
                        $updatedNotes = $currentNotes . "\n[" . date('Y-m-d H:i') . "] " . $adminNotes;
                        
                        $this->logger->debug('Updating admin notes for enrollment', [
                            'enrollment_id' => $enrollment->getId(),
                            'previous_notes_length' => strlen($currentNotes),
                            'new_notes_addition' => $adminNotes,
                        ]);
                        
                        $enrollment->setAdminNotes($updatedNotes);
                    }

                    $this->entityManager->persist($enrollment);
                    
                    $results['success']++;
                    $results['details'][] = [
                        'student' => $enrollment->getStudent()->getFullName(),
                        'formation' => $enrollment->getFormation()->getTitle(),
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'status' => 'success',
                    ];

                    // Send notification if requested
                    if ($notifyStudents) {
                        try {
                            $this->logger->debug('Sending status change notification', [
                                'enrollment_id' => $enrollment->getId(),
                                'student_email' => $enrollment->getStudent()->getEmail(),
                                'old_status' => $oldStatus,
                                'new_status' => $newStatus,
                            ]);
                            $this->sendStatusChangeNotification($enrollment, $oldStatus, $newStatus);
                        } catch (\Exception $e) {
                            $this->logger->error('Failed to send status change notification', [
                                'enrollment_id' => $enrollment->getId(),
                                'student_email' => $enrollment->getStudent()->getEmail(),
                                'error_message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }

                    $this->logger->info('Successfully updated enrollment status in bulk process', [
                        'enrollment_id' => $enrollment->getId(),
                        'student_id' => $enrollment->getStudent()->getId(),
                        'student_name' => $enrollment->getStudent()->getFullName(),
                        'formation_id' => $enrollment->getFormation()->getId(),
                        'formation_title' => $enrollment->getFormation()->getTitle(),
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'dropout_reason' => $dropoutReason,
                        'notification_sent' => $notifyStudents,
                    ]);

                } catch (\Exception $e) {
                    $this->logger->error('Failed to update individual enrollment status in bulk process', [
                        'enrollment_id' => $enrollment->getId(),
                        'student_id' => $enrollment->getStudent()->getId(),
                        'student_name' => $enrollment->getStudent()->getFullName(),
                        'formation_id' => $enrollment->getFormation()->getId(),
                        'current_status' => $enrollment->getStatus(),
                        'intended_new_status' => $newStatus,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $results['failed']++;
                    $results['details'][] = [
                        'student' => $enrollment->getStudent()->getFullName(),
                        'formation' => $enrollment->getFormation()->getTitle(),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $this->logger->info('Flushing bulk status update changes to database', [
                'total_enrollments' => count($enrollments),
                'success_count' => $results['success'],
                'failed_count' => $results['failed'],
                'new_status' => $newStatus,
            ]);

            $this->entityManager->flush();

            $this->logger->info('Successfully completed bulk status update process', [
                'total_enrollments' => count($enrollments),
                'successful_updates' => $results['success'],
                'failed_updates' => $results['failed'],
                'new_status' => $newStatus,
                'dropout_reason' => $dropoutReason,
                'notifications_enabled' => $notifyStudents,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Critical error during bulk status update process', [
                'total_enrollments' => count($enrollments),
                'processed_before_error' => $results['success'] + $results['failed'],
                'new_status' => $newStatus,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Bulk unenroll students.
     *
     * @param StudentEnrollment[] $enrollments
     */
    public function bulkUnenroll(array $enrollments, string $reason, bool $notifyStudents = false): array
    {
        $this->logger->info('Starting bulk unenrollment process', [
            'enrollments_count' => count($enrollments),
            'enrollment_ids' => array_map(fn($e) => $e->getId(), $enrollments),
            'reason' => $reason,
            'notify_students' => $notifyStudents,
        ]);

        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        try {
            foreach ($enrollments as $index => $enrollment) {
                $this->logger->debug('Processing enrollment for bulk unenrollment', [
                    'enrollment_index' => $index + 1,
                    'total_enrollments' => count($enrollments),
                    'enrollment_id' => $enrollment->getId(),
                    'student_id' => $enrollment->getStudent()->getId(),
                    'student_name' => $enrollment->getStudent()->getFullName(),
                    'formation_id' => $enrollment->getFormation()->getId(),
                    'formation_title' => $enrollment->getFormation()->getTitle(),
                    'current_status' => $enrollment->getStatus(),
                    'unenroll_reason' => $reason,
                ]);

                try {
                    $student = $enrollment->getStudent();
                    $formation = $enrollment->getFormation();
                    $oldStatus = $enrollment->getStatus();

                    $this->logger->debug('Marking enrollment as dropped out', [
                        'enrollment_id' => $enrollment->getId(),
                        'student_id' => $student->getId(),
                        'old_status' => $oldStatus,
                        'dropout_reason' => $reason,
                    ]);

                    // Mark as dropped out with reason
                    $enrollment->markDroppedOut($reason);
                    $this->entityManager->persist($enrollment);

                    $results['success']++;
                    $results['details'][] = [
                        'student' => $student->getFullName(),
                        'formation' => $formation->getTitle(),
                        'status' => 'success',
                    ];

                    // Send notification if requested
                    if ($notifyStudents) {
                        try {
                            $this->logger->debug('Sending unenrollment notification', [
                                'enrollment_id' => $enrollment->getId(),
                                'student_email' => $student->getEmail(),
                                'reason' => $reason,
                            ]);
                            $this->sendUnenrollmentNotification($enrollment, $reason);
                        } catch (\Exception $e) {
                            $this->logger->error('Failed to send unenrollment notification', [
                                'enrollment_id' => $enrollment->getId(),
                                'student_email' => $student->getEmail(),
                                'error_message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }

                    $this->logger->info('Successfully unenrolled student in bulk process', [
                        'enrollment_id' => $enrollment->getId(),
                        'student_id' => $student->getId(),
                        'student_name' => $student->getFullName(),
                        'student_email' => $student->getEmail(),
                        'formation_id' => $formation->getId(),
                        'formation_title' => $formation->getTitle(),
                        'old_status' => $oldStatus,
                        'new_status' => $enrollment->getStatus(),
                        'dropout_reason' => $reason,
                        'notification_sent' => $notifyStudents,
                    ]);

                } catch (\Exception $e) {
                    $this->logger->error('Failed to unenroll individual student in bulk process', [
                        'enrollment_id' => $enrollment->getId(),
                        'student_id' => $enrollment->getStudent()->getId(),
                        'student_name' => $enrollment->getStudent()->getFullName(),
                        'formation_id' => $enrollment->getFormation()->getId(),
                        'formation_title' => $enrollment->getFormation()->getTitle(),
                        'unenroll_reason' => $reason,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $results['failed']++;
                    $results['details'][] = [
                        'student' => $enrollment->getStudent()->getFullName(),
                        'formation' => $enrollment->getFormation()->getTitle(),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $this->logger->info('Flushing bulk unenrollment changes to database', [
                'total_enrollments' => count($enrollments),
                'success_count' => $results['success'],
                'failed_count' => $results['failed'],
                'reason' => $reason,
            ]);

            $this->entityManager->flush();

            $this->logger->info('Successfully completed bulk unenrollment process', [
                'total_enrollments' => count($enrollments),
                'successful_unenrollments' => $results['success'],
                'failed_unenrollments' => $results['failed'],
                'reason' => $reason,
                'notifications_enabled' => $notifyStudents,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Critical error during bulk unenrollment process', [
                'total_enrollments' => count($enrollments),
                'processed_before_error' => $results['success'] + $results['failed'],
                'reason' => $reason,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Validate bulk enrollment before processing.
     */
    public function validateBulkEnrollment(Session $session, array $students): array
    {
        $this->logger->info('Starting bulk enrollment validation', [
            'session_id' => $session->getId(),
            'formation_id' => $session->getFormation()->getId(),
            'formation_title' => $session->getFormation()->getTitle(),
            'students_count' => count($students),
            'student_ids' => array_map(fn($s) => $s->getId(), $students),
            'session_max_capacity' => $session->getMaxCapacity(),
        ]);

        $validation = [
            'valid' => [],
            'invalid' => [],
            'warnings' => [],
            'session_capacity' => $session->getMaxCapacity(),
            'current_enrollments' => 0,
            'available_spots' => 0,
        ];

        try {
            // Check current enrollments
            $this->logger->debug('Checking current enrollments for session', [
                'session_id' => $session->getId(),
            ]);

            $currentEnrollments = $this->enrollmentRepository->findBy(['sessionRegistration.session' => $session]);
            $validation['current_enrollments'] = count($currentEnrollments);
            
            $this->logger->debug('Current enrollment count determined', [
                'session_id' => $session->getId(),
                'current_enrollments' => $validation['current_enrollments'],
                'session_capacity' => $session->getMaxCapacity(),
            ]);
            
            if ($session->getMaxCapacity() > 0) {
                $validation['available_spots'] = $session->getMaxCapacity() - $validation['current_enrollments'];
                $this->logger->debug('Available spots calculated', [
                    'session_id' => $session->getId(),
                    'available_spots' => $validation['available_spots'],
                    'max_capacity' => $session->getMaxCapacity(),
                    'current_enrollments' => $validation['current_enrollments'],
                ]);
            } else {
                $this->logger->debug('Session has unlimited capacity', [
                    'session_id' => $session->getId(),
                ]);
            }

            foreach ($students as $index => $student) {
                $this->logger->debug('Validating individual student for bulk enrollment', [
                    'student_index' => $index + 1,
                    'total_students' => count($students),
                    'student_id' => $student->getId(),
                    'student_name' => $student->getFullName(),
                    'student_email' => $student->getEmail(),
                    'student_active' => $student->isActive(),
                ]);

                $issues = [];

                try {
                    // Check if student is active
                    if (!$student->isActive()) {
                        $issues[] = 'Student account is inactive';
                        $this->logger->warning('Student account is inactive', [
                            'student_id' => $student->getId(),
                            'student_name' => $student->getFullName(),
                        ]);
                    }

                    // Check if already enrolled
                    $this->logger->debug('Checking for existing enrollment', [
                        'student_id' => $student->getId(),
                        'session_id' => $session->getId(),
                    ]);

                    $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSession($student, $session);
                    if ($existingEnrollment) {
                        $issues[] = 'Already enrolled in this session';
                        $this->logger->warning('Student already enrolled in session', [
                            'student_id' => $student->getId(),
                            'student_name' => $student->getFullName(),
                            'session_id' => $session->getId(),
                            'existing_enrollment_id' => $existingEnrollment->getId(),
                            'existing_enrollment_status' => $existingEnrollment->getStatus(),
                        ]);
                    }

                    // Check session capacity
                    if ($session->getMaxCapacity() > 0 && $validation['available_spots'] <= 0) {
                        $issues[] = 'Session is at full capacity';
                        $this->logger->warning('Session at full capacity for student', [
                            'student_id' => $student->getId(),
                            'student_name' => $student->getFullName(),
                            'session_id' => $session->getId(),
                            'max_capacity' => $session->getMaxCapacity(),
                            'current_enrollments' => $validation['current_enrollments'],
                            'available_spots' => $validation['available_spots'],
                        ]);
                    }

                    if (empty($issues)) {
                        $validation['valid'][] = [
                            'student' => $student,
                            'name' => $student->getFullName(),
                            'email' => $student->getEmail(),
                        ];
                        if ($session->getMaxCapacity() > 0) {
                            $validation['available_spots']--;
                        }

                        $this->logger->debug('Student validated successfully for bulk enrollment', [
                            'student_id' => $student->getId(),
                            'student_name' => $student->getFullName(),
                            'remaining_spots' => $validation['available_spots'],
                        ]);
                    } else {
                        $validation['invalid'][] = [
                            'student' => $student,
                            'name' => $student->getFullName(),
                            'email' => $student->getEmail(),
                            'issues' => $issues,
                        ];

                        $this->logger->warning('Student validation failed for bulk enrollment', [
                            'student_id' => $student->getId(),
                            'student_name' => $student->getFullName(),
                            'validation_issues' => $issues,
                        ]);
                    }

                } catch (\Exception $e) {
                    $this->logger->error('Error during individual student validation', [
                        'student_id' => $student->getId(),
                        'student_name' => $student->getFullName(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $validation['invalid'][] = [
                        'student' => $student,
                        'name' => $student->getFullName(),
                        'email' => $student->getEmail(),
                        'issues' => ['Validation error: ' . $e->getMessage()],
                    ];
                }
            }

            $this->logger->info('Completed bulk enrollment validation', [
                'session_id' => $session->getId(),
                'formation_id' => $session->getFormation()->getId(),
                'total_students' => count($students),
                'valid_students' => count($validation['valid']),
                'invalid_students' => count($validation['invalid']),
                'session_capacity' => $validation['session_capacity'],
                'current_enrollments' => $validation['current_enrollments'],
                'available_spots' => $validation['available_spots'],
                'validation_success_rate' => count($students) > 0 ? round((count($validation['valid']) / count($students)) * 100, 2) : 0,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Critical error during bulk enrollment validation', [
                'session_id' => $session->getId(),
                'formation_id' => $session->getFormation()->getId(),
                'total_students' => count($students),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $validation;
    }

    /**
     * Send status change notification email.
     */
    private function sendStatusChangeNotification(StudentEnrollment $enrollment, string $oldStatus, string $newStatus): void
    {
        try {
            $student = $enrollment->getStudent();
            $formation = $enrollment->getFormation();

            $email = (new Email())
                ->from('noreply@eprofos.fr')
                ->to($student->getEmail())
                ->subject('Changement de statut d\'inscription - ' . $formation->getTitle())
                ->html($this->twig->render('emails/enrollment_status_change.html.twig', [
                    'student' => $student,
                    'enrollment' => $enrollment,
                    'formation' => $formation,
                    'oldStatus' => $oldStatus,
                    'newStatus' => $newStatus,
                ]));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send status change notification', [
                'enrollment_id' => $enrollment->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send unenrollment notification email.
     */
    private function sendUnenrollmentNotification(StudentEnrollment $enrollment, string $reason): void
    {
        try {
            $student = $enrollment->getStudent();
            $formation = $enrollment->getFormation();

            $email = (new Email())
                ->from('noreply@eprofos.fr')
                ->to($student->getEmail())
                ->subject('Désinscription - ' . $formation->getTitle())
                ->html($this->twig->render('emails/enrollment_unenrollment.html.twig', [
                    'student' => $student,
                    'enrollment' => $enrollment,
                    'formation' => $formation,
                    'reason' => $reason,
                ]));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send unenrollment notification', [
                'enrollment_id' => $enrollment->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate enrollment eligibility for a student.
     */
    public function validateEnrollmentEligibility(Student $student, Session $session): bool
    {
        $this->logger->debug('Starting enrollment eligibility validation', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
            'session_id' => $session->getId(),
            'formation_id' => $session->getFormation()->getId(),
            'formation_title' => $session->getFormation()->getTitle(),
        ]);

        try {
            // Check if student is active
            $this->logger->debug('Checking if student is active', [
                'student_id' => $student->getId(),
                'student_active' => $student->isActive(),
            ]);

            if (!$student->isActive()) {
                $this->logger->warning('Student enrollment eligibility failed: student is inactive', [
                    'student_id' => $student->getId(),
                    'student_email' => $student->getEmail(),
                    'session_id' => $session->getId(),
                ]);
                return false;
            }

            // Check if session is in the future or ongoing
            $now = new DateTimeImmutable();
            $sessionEndDate = $session->getEndDate();
            
            $this->logger->debug('Checking session date validity', [
                'session_id' => $session->getId(),
                'session_end_date' => $sessionEndDate?->format('Y-m-d H:i:s'),
                'current_date' => $now->format('Y-m-d H:i:s'),
                'session_has_ended' => $sessionEndDate && $sessionEndDate < $now,
            ]);

            if ($sessionEndDate && $sessionEndDate < $now) {
                $this->logger->warning('Student enrollment eligibility failed: session has ended', [
                    'student_id' => $student->getId(),
                    'session_id' => $session->getId(),
                    'session_end_date' => $sessionEndDate->format('Y-m-d H:i:s'),
                    'current_date' => $now->format('Y-m-d H:i:s'),
                ]);
                return false;
            }

            // Check if session has available spots
            $maxCapacity = $session->getMaxCapacity();
            
            $this->logger->debug('Checking session capacity', [
                'session_id' => $session->getId(),
                'max_capacity' => $maxCapacity,
                'has_capacity_limit' => $maxCapacity > 0,
            ]);

            if ($maxCapacity > 0) {
                $confirmedRegistrations = $this->sessionRegistrationRepository->findConfirmedBySession($session);
                $confirmedCount = count($confirmedRegistrations);
                
                $this->logger->debug('Session capacity details', [
                    'session_id' => $session->getId(),
                    'max_capacity' => $maxCapacity,
                    'confirmed_registrations' => $confirmedCount,
                    'available_spots' => $maxCapacity - $confirmedCount,
                    'is_full' => $confirmedCount >= $maxCapacity,
                ]);

                if ($confirmedCount >= $maxCapacity) {
                    $this->logger->warning('Student enrollment eligibility failed: session is at full capacity', [
                        'student_id' => $student->getId(),
                        'session_id' => $session->getId(),
                        'max_capacity' => $maxCapacity,
                        'confirmed_registrations' => $confirmedCount,
                    ]);
                    return false;
                }
            }

            $this->logger->info('Student enrollment eligibility validation passed', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'session_id' => $session->getId(),
                'formation_id' => $session->getFormation()->getId(),
                'student_active' => $student->isActive(),
                'session_end_date' => $sessionEndDate?->format('Y-m-d H:i:s'),
                'session_capacity_available' => $maxCapacity <= 0 || count($this->sessionRegistrationRepository->findConfirmedBySession($session)) < $maxCapacity,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error during enrollment eligibility validation', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'session_id' => $session->getId(),
                'formation_id' => $session->getFormation()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Send enrollment notification email to student.
     */
    public function sendEnrollmentNotification(StudentEnrollment $enrollment): void
    {
        $this->logger->info('Starting enrollment notification process', [
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()->getId(),
            'student_email' => $enrollment->getStudent()->getEmail(),
            'formation_id' => $enrollment->getFormation()->getId(),
            'formation_title' => $enrollment->getFormation()->getTitle(),
        ]);

        try {
            $student = $enrollment->getStudent();
            $formation = $enrollment->getFormation();
            $session = $enrollment->getSession();

            $this->logger->debug('Preparing enrollment notification email', [
                'enrollment_id' => $enrollment->getId(),
                'student_email' => $student->getEmail(),
                'student_name' => $student->getFullName(),
                'formation_title' => $formation->getTitle(),
                'session_id' => $session?->getId(),
                'session_start_date' => $session?->getStartDate()?->format('Y-m-d H:i:s'),
            ]);

            $email = (new Email())
                ->from('noreply@eprofos.fr')
                ->to($student->getEmail())
                ->subject('Confirmation d\'inscription - ' . $formation->getTitle())
                ->html($this->twig->render('emails/enrollment_confirmation.html.twig', [
                    'student' => $student,
                    'enrollment' => $enrollment,
                    'formation' => $formation,
                    'session' => $session,
                ]));

            $this->logger->debug('Sending enrollment notification email', [
                'enrollment_id' => $enrollment->getId(),
                'recipient_email' => $student->getEmail(),
                'email_subject' => 'Confirmation d\'inscription - ' . $formation->getTitle(),
                'template' => 'emails/enrollment_confirmation.html.twig',
            ]);

            $this->mailer->send($email);

            $this->logger->info('Enrollment notification email sent successfully', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'student_name' => $student->getFullName(),
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'session_id' => $session?->getId(),
                'email_sent_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send enrollment notification email', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $enrollment->getStudent()->getId(),
                'student_email' => $enrollment->getStudent()->getEmail(),
                'student_name' => $enrollment->getStudent()->getFullName(),
                'formation_id' => $enrollment->getFormation()->getId(),
                'formation_title' => $enrollment->getFormation()->getTitle(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Unlink a student from a session registration and remove enrollment.
     */
    public function unlinkStudentFromSessionRegistration(StudentEnrollment $enrollment): void
    {
        $this->logger->info('Starting student unlinking process', [
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()->getId(),
            'student_email' => $enrollment->getStudent()->getEmail(),
            'student_name' => $enrollment->getStudent()->getFullName(),
            'registration_id' => $enrollment->getSessionRegistration()->getId(),
            'session_id' => $enrollment->getSession()->getId(),
            'formation_id' => $enrollment->getFormation()->getId(),
            'formation_title' => $enrollment->getFormation()->getTitle(),
        ]);

        try {
            $registration = $enrollment->getSessionRegistration();
            $student = $enrollment->getStudent();
            $session = $enrollment->getSession();
            $formation = $enrollment->getFormation();

            $this->logger->debug('Preparing to unlink student from registration', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $student->getId(),
                'registration_id' => $registration->getId(),
                'session_id' => $session->getId(),
                'registration_linked_student_id' => $registration->getLinkedStudent()?->getId(),
            ]);

            // Unlink the registration
            $this->logger->debug('Unlinking registration from student', [
                'registration_id' => $registration->getId(),
                'previously_linked_student_id' => $registration->getLinkedStudent()?->getId(),
            ]);

            $registration->unlinkStudent();

            // Remove the enrollment and associated progress
            $this->logger->debug('Removing enrollment and associated entities', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $student->getId(),
                'progress_id' => $enrollment->getProgress()?->getId(),
            ]);

            $this->entityManager->remove($enrollment);
            $this->entityManager->persist($registration);
            
            $this->logger->debug('Flushing unlinking changes to database', [
                'enrollment_id' => $enrollment->getId(),
                'registration_id' => $registration->getId(),
                'student_id' => $student->getId(),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Successfully unlinked student from session registration', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'student_name' => $student->getFullName(),
                'registration_id' => $registration->getId(),
                'session_id' => $session->getId(),
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'unlinked_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to unlink student from session registration', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $enrollment->getStudent()->getId(),
                'student_email' => $enrollment->getStudent()->getEmail(),
                'registration_id' => $enrollment->getSessionRegistration()->getId(),
                'session_id' => $enrollment->getSession()->getId(),
                'formation_id' => $enrollment->getFormation()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get linking statistics for admin dashboard.
     */
    public function getLinkingStats(): array
    {
        $this->logger->debug('Starting linking statistics calculation');

        try {
            $this->logger->debug('Counting total confirmed registrations');
            $totalConfirmedRegistrations = $this->sessionRegistrationRepository->count(['status' => 'confirmed']);
            
            $this->logger->debug('Counting linked registrations');
            $linkedRegistrations = $this->sessionRegistrationRepository->createQueryBuilder('sr')
                ->select('COUNT(sr.id)')
                ->where('sr.status = :status')
                ->andWhere('sr.linkedStudent IS NOT NULL')
                ->setParameter('status', 'confirmed')
                ->getQuery()
                ->getSingleScalarResult();

            $unlinkedRegistrations = $totalConfirmedRegistrations - $linkedRegistrations;
            $linkingRate = $totalConfirmedRegistrations > 0 
                ? round(($linkedRegistrations / $totalConfirmedRegistrations) * 100, 2) 
                : 0;

            $stats = [
                'total_confirmed_registrations' => $totalConfirmedRegistrations,
                'linked_registrations' => (int) $linkedRegistrations,
                'unlinked_registrations' => $unlinkedRegistrations,
                'linking_rate' => $linkingRate,
            ];

            $this->logger->info('Successfully calculated linking statistics', [
                'total_confirmed_registrations' => $totalConfirmedRegistrations,
                'linked_registrations' => (int) $linkedRegistrations,
                'unlinked_registrations' => $unlinkedRegistrations,
                'linking_rate_percentage' => $linkingRate,
                'calculation_timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            return $stats;

        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate linking statistics', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Find students that could be auto-linked based on email matching.
     *
     * @return array Array of potential matches
     */
    public function findPotentialAutoLinks(): array
    {
        $this->logger->info('Starting search for potential auto-links based on email matching');

        try {
            $this->logger->debug('Fetching unlinked confirmed registrations');
            $unlinkedRegistrations = $this->sessionRegistrationRepository->findUnlinkedConfirmedRegistrations();
            
            $this->logger->info('Found unlinked confirmed registrations', [
                'unlinked_registrations_count' => count($unlinkedRegistrations),
                'registration_ids' => array_map(fn($r) => $r->getId(), $unlinkedRegistrations),
            ]);

            $potentialMatches = [];
            $studentRepo = $this->entityManager->getRepository(Student::class);

            foreach ($unlinkedRegistrations as $index => $registration) {
                $this->logger->debug('Processing registration for potential auto-link', [
                    'registration_index' => $index + 1,
                    'total_registrations' => count($unlinkedRegistrations),
                    'registration_id' => $registration->getId(),
                    'registration_email' => $registration->getEmail(),
                    'registration_name' => $registration->getFirstName() . ' ' . $registration->getLastName(),
                    'session_id' => $registration->getSession()->getId(),
                    'formation_id' => $registration->getSession()->getFormation()->getId(),
                ]);

                try {
                    $this->logger->debug('Searching for student with matching email', [
                        'registration_id' => $registration->getId(),
                        'search_email' => $registration->getEmail(),
                    ]);

                    $student = $studentRepo->findOneBy(['email' => $registration->getEmail()]);

                    if ($student !== null) {
                        $this->logger->debug('Found matching student for registration', [
                            'registration_id' => $registration->getId(),
                            'registration_email' => $registration->getEmail(),
                            'student_id' => $student->getId(),
                            'student_name' => $student->getFullName(),
                            'student_active' => $student->isActive(),
                            'session_id' => $registration->getSession()->getId(),
                            'formation_id' => $registration->getSession()->getFormation()->getId(),
                        ]);

                        $potentialMatches[] = [
                            'registration' => $registration,
                            'student' => $student,
                            'session' => $registration->getSession(),
                            'formation' => $registration->getSession()->getFormation(),
                        ];
                    } else {
                        $this->logger->debug('No matching student found for registration', [
                            'registration_id' => $registration->getId(),
                            'registration_email' => $registration->getEmail(),
                        ]);
                    }

                } catch (\Exception $e) {
                    $this->logger->error('Error while searching for matching student', [
                        'registration_id' => $registration->getId(),
                        'registration_email' => $registration->getEmail(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->logger->info('Completed search for potential auto-links', [
                'total_unlinked_registrations' => count($unlinkedRegistrations),
                'potential_matches_found' => count($potentialMatches),
                'match_rate_percentage' => count($unlinkedRegistrations) > 0 
                    ? round((count($potentialMatches) / count($unlinkedRegistrations)) * 100, 2) 
                    : 0,
                'potential_match_details' => array_map(fn($match) => [
                    'registration_id' => $match['registration']->getId(),
                    'student_id' => $match['student']->getId(),
                    'student_email' => $match['student']->getEmail(),
                    'formation_title' => $match['formation']->getTitle(),
                ], $potentialMatches),
            ]);

            return $potentialMatches;

        } catch (\Exception $e) {
            $this->logger->error('Critical error during potential auto-links search', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Process all potential auto-links.
     *
     * @return array Results of the auto-linking process
     */
    public function processAllAutoLinks(): array
    {
        $this->logger->info('Starting process for all potential auto-links');

        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        try {
            $this->logger->debug('Finding potential auto-link matches');
            $potentialMatches = $this->findPotentialAutoLinks();
            
            $this->logger->info('Found potential auto-link matches to process', [
                'potential_matches_count' => count($potentialMatches),
                'match_details' => array_map(fn($match) => [
                    'registration_id' => $match['registration']->getId(),
                    'student_id' => $match['student']->getId(),
                    'student_email' => $match['student']->getEmail(),
                    'formation_title' => $match['formation']->getTitle(),
                ], $potentialMatches),
            ]);

            foreach ($potentialMatches as $index => $match) {
                $results['processed']++;
                
                $this->logger->debug('Processing auto-link match', [
                    'match_index' => $index + 1,
                    'total_matches' => count($potentialMatches),
                    'registration_id' => $match['registration']->getId(),
                    'student_id' => $match['student']->getId(),
                    'student_email' => $match['student']->getEmail(),
                    'student_name' => $match['student']->getFullName(),
                    'formation_id' => $match['formation']->getId(),
                    'formation_title' => $match['formation']->getTitle(),
                    'session_id' => $match['session']->getId(),
                ]);
                
                try {
                    $this->logger->debug('Attempting to link student to registration', [
                        'student_id' => $match['student']->getId(),
                        'registration_id' => $match['registration']->getId(),
                        'session_id' => $match['session']->getId(),
                    ]);

                    $enrollment = $this->linkStudentToSessionRegistration(
                        $match['student'],
                        $match['registration']
                    );
                    
                    $results['success']++;
                    $results['details'][] = [
                        'student' => $match['student']->getFullName(),
                        'formation' => $match['formation']->getTitle(),
                        'status' => 'success',
                        'enrollment_id' => $enrollment->getId(),
                    ];

                    $this->logger->info('Successfully processed auto-link', [
                        'enrollment_id' => $enrollment->getId(),
                        'student_id' => $match['student']->getId(),
                        'student_email' => $match['student']->getEmail(),
                        'student_name' => $match['student']->getFullName(),
                        'registration_id' => $match['registration']->getId(),
                        'formation_id' => $match['formation']->getId(),
                        'formation_title' => $match['formation']->getTitle(),
                        'session_id' => $match['session']->getId(),
                        'processed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ]);

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'student' => $match['student']->getFullName(),
                        'formation' => $match['formation']->getTitle(),
                        'error' => $e->getMessage(),
                    ];

                    $this->logger->error('Failed to process auto-link match', [
                        'student_id' => $match['student']->getId(),
                        'student_email' => $match['student']->getEmail(),
                        'student_name' => $match['student']->getFullName(),
                        'registration_id' => $match['registration']->getId(),
                        'formation_id' => $match['formation']->getId(),
                        'formation_title' => $match['formation']->getTitle(),
                        'session_id' => $match['session']->getId(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->logger->info('Completed processing all potential auto-links', [
                'total_potential_matches' => count($potentialMatches),
                'total_processed' => $results['processed'],
                'successful_links' => $results['success'],
                'failed_links' => $results['failed'],
                'success_rate_percentage' => $results['processed'] > 0 
                    ? round(($results['success'] / $results['processed']) * 100, 2) 
                    : 0,
                'completed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Critical error during auto-links processing', [
                'processed_before_error' => $results['processed'],
                'success_before_error' => $results['success'],
                'failed_before_error' => $results['failed'],
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $results;
    }
}
