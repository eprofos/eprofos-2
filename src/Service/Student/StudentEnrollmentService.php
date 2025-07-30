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
        // Validate enrollment eligibility
        if (!$this->validateEnrollmentEligibility($student, $registration->getSession())) {
            throw new \Exception('Student is not eligible for enrollment in this session');
        }

        // Check if registration is already linked
        if ($registration->hasLinkedStudent()) {
            throw new \Exception('Session registration is already linked to another student');
        }

        // Check for duplicate enrollment
        $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSessionRegistration(
            $student,
            $registration
        );

        if ($existingEnrollment !== null) {
            throw new \Exception('Student is already enrolled in this session');
        }

        // Create the enrollment
        $enrollment = new StudentEnrollment();
        $enrollment->setStudent($student);
        $enrollment->setSessionRegistration($registration);
        $enrollment->setEnrollmentSource('manual_link');
        $enrollment->setAdminNotes('Linked via admin interface or automatic email matching');

        // Create associated StudentProgress
        $progress = new StudentProgress();
        $progress->setStudent($student);
        $progress->setFormation($registration->getSession()->getFormation());
        $enrollment->setProgress($progress);

        // Link the registration to the student
        $registration->linkStudent($student);

        // Persist entities
        $this->entityManager->persist($enrollment);
        $this->entityManager->persist($progress);
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        // Send enrollment notification
        $this->sendEnrollmentNotification($enrollment);

        // Log the action
        $this->logger->info('Student linked to session registration', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'registration_id' => $registration->getId(),
            'session_id' => $registration->getSession()->getId(),
            'formation_id' => $registration->getSession()->getFormation()->getId(),
        ]);

        return $enrollment;
    }

    /**
     * Automatically link student to session registrations by email matching.
     *
     * @return StudentEnrollment[] Array of created enrollments
     */
    public function autoLinkByEmail(Student $student): array
    {
        $enrollments = [];

        // Find matching unlinked session registrations by email
        $registrations = $this->sessionRegistrationRepository->findUnlinkedConfirmedByEmail(
            $student->getEmail()
        );

        foreach ($registrations as $registration) {
            try {
                // Skip if enrollment already exists for this session
                $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSession(
                    $student,
                    $registration->getSession()
                );

                if ($existingEnrollment !== null) {
                    continue;
                }

                // Create enrollment with automatic source
                $enrollment = new StudentEnrollment();
                $enrollment->setStudent($student);
                $enrollment->setSessionRegistration($registration);
                $enrollment->setEnrollmentSource('auto_email_match');
                $enrollment->setAdminNotes('Automatically linked by email matching');

                // Create associated StudentProgress
                $progress = new StudentProgress();
                $progress->setStudent($student);
                $progress->setFormation($registration->getSession()->getFormation());
                $enrollment->setProgress($progress);

                // Link the registration to the student
                $registration->linkStudent($student);

                // Persist entities
                $this->entityManager->persist($enrollment);
                $this->entityManager->persist($progress);
                $this->entityManager->persist($registration);

                $enrollments[] = $enrollment;

                $this->logger->info('Auto-linked student to session registration by email', [
                    'student_id' => $student->getId(),
                    'student_email' => $student->getEmail(),
                    'registration_id' => $registration->getId(),
                    'session_id' => $registration->getSession()->getId(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to auto-link student to session registration', [
                    'student_id' => $student->getId(),
                    'registration_id' => $registration->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($enrollments)) {
            $this->entityManager->flush();

            // Send notifications for all new enrollments
            foreach ($enrollments as $enrollment) {
                $this->sendEnrollmentNotification($enrollment);
            }
        }

        return $enrollments;
    }

    /**
     * Bulk enroll students in a session.
     *
     * @param Student[] $students
     * @return array Array with 'success' and 'failed' counts and details
     */
    public function bulkEnrollStudents(Session $session, array $students): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($students as $student) {
            try {
                // Check if student already has an enrollment for this session
                $existingEnrollment = $this->enrollmentRepository->findEnrollmentByStudentAndSession(
                    $student,
                    $session
                );

                if ($existingEnrollment !== null) {
                    $results['failed']++;
                    $results['details'][] = [
                        'student' => $student->getFullName(),
                        'error' => 'Already enrolled in this session',
                    ];
                    continue;
                }

                // Create a session registration for bulk enrollment
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
                $enrollment = new StudentEnrollment();
                $enrollment->setStudent($student);
                $enrollment->setSessionRegistration($registration);
                $enrollment->setEnrollmentSource('bulk_admin');
                $enrollment->setAdminNotes('Bulk enrollment via admin interface');

                // Create associated StudentProgress
                $progress = new StudentProgress();
                $progress->setStudent($student);
                $progress->setFormation($session->getFormation());
                $enrollment->setProgress($progress);

                // Persist entities
                $this->entityManager->persist($registration);
                $this->entityManager->persist($enrollment);
                $this->entityManager->persist($progress);

                $results['success']++;
                $results['details'][] = [
                    'student' => $student->getFullName(),
                    'status' => 'success',
                ];

                $this->logger->info('Bulk enrolled student in session', [
                    'student_id' => $student->getId(),
                    'session_id' => $session->getId(),
                ]);
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'student' => $student->getFullName(),
                    'error' => $e->getMessage(),
                ];

                $this->logger->error('Failed to bulk enroll student', [
                    'student_id' => $student->getId(),
                    'session_id' => $session->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->entityManager->flush();

        return $results;
    }

    /**
     * Validate enrollment eligibility for a student.
     */
    public function validateEnrollmentEligibility(Student $student, Session $session): bool
    {
        // Check if student is active
        if (!$student->isActive()) {
            return false;
        }

        // Check if session is in the future or ongoing
        $now = new DateTimeImmutable();
        if ($session->getEndDate() && $session->getEndDate() < $now) {
            return false;
        }

        // Check if session has available spots
        if ($session->getMaxCapacity() > 0) {
            $confirmedRegistrations = $this->sessionRegistrationRepository->findConfirmedBySession($session);
            if (count($confirmedRegistrations) >= $session->getMaxCapacity()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send enrollment notification email to student.
     */
    public function sendEnrollmentNotification(StudentEnrollment $enrollment): void
    {
        try {
            $student = $enrollment->getStudent();
            $formation = $enrollment->getFormation();
            $session = $enrollment->getSession();

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

            $this->mailer->send($email);

            $this->logger->info('Enrollment notification sent', [
                'student_email' => $student->getEmail(),
                'enrollment_id' => $enrollment->getId(),
                'formation_id' => $formation->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send enrollment notification', [
                'enrollment_id' => $enrollment->getId(),
                'student_email' => $enrollment->getStudent()->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Unlink a student from a session registration and remove enrollment.
     */
    public function unlinkStudentFromSessionRegistration(StudentEnrollment $enrollment): void
    {
        $registration = $enrollment->getSessionRegistration();
        $student = $enrollment->getStudent();

        // Unlink the registration
        $registration->unlinkStudent();

        // Remove the enrollment and associated progress
        $this->entityManager->remove($enrollment);
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $this->logger->info('Student unlinked from session registration', [
            'student_id' => $student->getId(),
            'registration_id' => $registration->getId(),
            'enrollment_id' => $enrollment->getId(),
        ]);
    }

    /**
     * Get linking statistics for admin dashboard.
     */
    public function getLinkingStats(): array
    {
        $totalConfirmedRegistrations = $this->sessionRegistrationRepository->count(['status' => 'confirmed']);
        $linkedRegistrations = $this->sessionRegistrationRepository->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.status = :status')
            ->andWhere('sr.linkedStudent IS NOT NULL')
            ->setParameter('status', 'confirmed')
            ->getQuery()
            ->getSingleScalarResult();

        $unlinkedRegistrations = $totalConfirmedRegistrations - $linkedRegistrations;

        return [
            'total_confirmed_registrations' => $totalConfirmedRegistrations,
            'linked_registrations' => (int) $linkedRegistrations,
            'unlinked_registrations' => $unlinkedRegistrations,
            'linking_rate' => $totalConfirmedRegistrations > 0 
                ? round(($linkedRegistrations / $totalConfirmedRegistrations) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Find students that could be auto-linked based on email matching.
     *
     * @return array Array of potential matches
     */
    public function findPotentialAutoLinks(): array
    {
        $unlinkedRegistrations = $this->sessionRegistrationRepository->findUnlinkedConfirmedRegistrations();
        $potentialMatches = [];

        foreach ($unlinkedRegistrations as $registration) {
            $studentRepo = $this->entityManager->getRepository(Student::class);
            $student = $studentRepo->findOneBy(['email' => $registration->getEmail()]);

            if ($student !== null) {
                $potentialMatches[] = [
                    'registration' => $registration,
                    'student' => $student,
                    'session' => $registration->getSession(),
                    'formation' => $registration->getSession()->getFormation(),
                ];
            }
        }

        return $potentialMatches;
    }

    /**
     * Process all potential auto-links.
     *
     * @return array Results of the auto-linking process
     */
    public function processAllAutoLinks(): array
    {
        $potentialMatches = $this->findPotentialAutoLinks();
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($potentialMatches as $match) {
            $results['processed']++;
            
            try {
                $this->linkStudentToSessionRegistration(
                    $match['student'],
                    $match['registration']
                );
                
                $results['success']++;
                $results['details'][] = [
                    'student' => $match['student']->getFullName(),
                    'formation' => $match['formation']->getTitle(),
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'student' => $match['student']->getFullName(),
                    'formation' => $match['formation']->getTitle(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
