<?php

declare(strict_types=1);

namespace App\Controller\Admin\User;

use App\Entity\User\Student;
use App\Form\User\StudentType;
use App\Repository\User\StudentRepository;
use App\Service\Core\StudentService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Student Controller.
 *
 * Handles CRUD operations and management for students in the admin interface.
 * Provides comprehensive student management capabilities including password reset,
 * email verification management, and detailed filtering options.
 */
#[Route('/admin/students', name: 'admin_student_')]
#[IsGranted('ROLE_ADMIN')]
class StudentController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private StudentService $studentService,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * List all students with advanced filtering and pagination.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, StudentRepository $studentRepository): Response
    {
        $this->logger->info('Admin students list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $search = $request->query->get('search');
        $status = $request->query->get('status');
        $emailVerified = $request->query->get('email_verified');
        $city = $request->query->get('city');
        $profession = $request->query->get('profession');
        $registrationPeriod = $request->query->get('registration_period');

        $filters = [
            'search' => $search ?? '',
            'status' => $status ?? '',
            'email_verified' => $emailVerified ?? '',
            'city' => $city ?? '',
            'profession' => $profession ?? '',
            'registration_period' => $registrationPeriod ?? '',
        ];

        $students = $studentRepository->findWithFilters($filters, $page, $limit);
        $totalStudents = $studentRepository->countWithFilters($filters);
        $totalPages = ceil($totalStudents / $limit);

        // Get filter options
        $cities = $studentRepository->getDistinctCities();
        $professions = $studentRepository->getDistinctProfessions();

        // Get statistics for dashboard cards
        $statistics = $studentRepository->getStatistics();

        return $this->render('admin/student/index.html.twig', [
            'students' => $students,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_students' => $totalStudents,
            'filters' => $filters,
            'cities' => $cities ?? [],
            'professions' => $professions ?? [],
            'statistics' => $statistics ?? [],
        ]);
    }

    /**
     * Show detailed student information.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Student $student): Response
    {
        $this->logger->info('Student details viewed', [
            'student_id' => $student->getId(),
            'viewed_by' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $this->render('admin/student/show.html.twig', [
            'student' => $student,
        ]);
    }

    /**
     * Create new student.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $student = new Student();
        $form = $this->createForm(StudentType::class, $student, ['is_admin_creation' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle password hashing if provided
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $this->passwordHasher->hashPassword($student, $plainPassword);
                    $student->setPassword($hashedPassword);
                }

                // Set initial values for admin creation
                $student->setEmailVerified($form->get('emailVerified')->getData() ?? false);
                $student->setIsActive($form->get('isActive')->getData() ?? true);

                $entityManager->persist($student);
                $entityManager->flush();

                // Send welcome email if requested
                if ($form->get('sendWelcomeEmail')->getData()) {
                    $this->studentService->sendWelcomeEmail($student, $plainPassword);
                }

                $this->addFlash('success', 'Étudiant créé avec succès.');

                $this->logger->info('Student created successfully', [
                    'student_id' => $student->getId(),
                    'created_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());

                $this->logger->error('Failed to create student', [
                    'error' => $e->getMessage(),
                    'created_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->render('admin/student/new.html.twig', [
            'student' => $student,
            'form' => $form,
        ]);
    }

    /**
     * Edit existing student.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        $originalPassword = $student->getPassword();
        $form = $this->createForm(StudentType::class, $student, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle password change if provided
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $this->passwordHasher->hashPassword($student, $plainPassword);
                    $student->setPassword($hashedPassword);

                    $this->logger->info('Student password updated', [
                        'student_id' => $student->getId(),
                        'updated_by' => $this->getUser()?->getUserIdentifier(),
                    ]);
                } else {
                    // Keep original password if no new password provided
                    $student->setPassword($originalPassword);
                }

                $entityManager->flush();

                $this->addFlash('success', 'Étudiant modifié avec succès.');

                $this->logger->info('Student updated successfully', [
                    'student_id' => $student->getId(),
                    'updated_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());

                $this->logger->error('Failed to update student', [
                    'student_id' => $student->getId(),
                    'error' => $e->getMessage(),
                    'updated_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->render('admin/student/edit.html.twig', [
            'student' => $student,
            'form' => $form,
        ]);
    }

    /**
     * Delete student (soft delete - deactivate).
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $student->getId(), $request->request->get('_token'))) {
            try {
                // Soft delete - deactivate instead of removing
                $student->setIsActive(false);
                $entityManager->flush();

                $this->addFlash('success', 'Étudiant désactivé avec succès.');

                $this->logger->info('Student deactivated', [
                    'student_id' => $student->getId(),
                    'deactivated_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la désactivation : ' . $e->getMessage());

                $this->logger->error('Failed to deactivate student', [
                    'student_id' => $student->getId(),
                    'error' => $e->getMessage(),
                    'deactivated_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->redirectToRoute('admin_student_index');
    }

    /**
     * Activate/deactivate student.
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_status' . $student->getId(), $request->request->get('_token'))) {
            try {
                $student->setIsActive(!$student->isActive());
                $entityManager->flush();

                $status = $student->isActive() ? 'activé' : 'désactivé';
                $this->addFlash('success', "Étudiant {$status} avec succès.");

                $this->logger->info('Student status toggled', [
                    'student_id' => $student->getId(),
                    'new_status' => $student->isActive(),
                    'updated_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors du changement de statut : ' . $e->getMessage());

                $this->logger->error('Failed to toggle student status', [
                    'student_id' => $student->getId(),
                    'error' => $e->getMessage(),
                    'updated_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
    }

    /**
     * Send password reset link to student.
     */
    #[Route('/{id}/send-password-reset', name: 'send_password_reset', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sendPasswordReset(Request $request, Student $student): JsonResponse
    {
        if (!$this->isCsrfTokenValid('send_password_reset' . $student->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

        try {
            $success = $this->studentService->sendPasswordResetEmail($student);

            if ($success) {
                $this->logger->info('Password reset email sent', [
                    'student_id' => $student->getId(),
                    'sent_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Email de réinitialisation envoyé avec succès.',
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email.',
            ], 500);
        } catch (Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'student_id' => $student->getId(),
                'error' => $e->getMessage(),
                'sent_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send email verification link to student.
     */
    #[Route('/{id}/send-email-verification', name: 'send_email_verification', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sendEmailVerification(Request $request, Student $student): JsonResponse
    {
        if (!$this->isCsrfTokenValid('send_email_verification' . $student->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

        try {
            $success = $this->studentService->sendEmailVerification($student);

            if ($success) {
                $this->logger->info('Email verification sent', [
                    'student_id' => $student->getId(),
                    'sent_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Email de vérification envoyé avec succès.',
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email.',
            ], 500);
        } catch (Exception $e) {
            $this->logger->error('Failed to send email verification', [
                'student_id' => $student->getId(),
                'error' => $e->getMessage(),
                'sent_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually verify student email.
     */
    #[Route('/{id}/verify-email', name: 'verify_email', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function verifyEmail(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('verify_email' . $student->getId(), $request->request->get('_token'))) {
            try {
                $student->verifyEmail();
                $entityManager->flush();

                $this->addFlash('success', 'Email vérifié manuellement avec succès.');

                $this->logger->info('Student email manually verified', [
                    'student_id' => $student->getId(),
                    'verified_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la vérification : ' . $e->getMessage());

                $this->logger->error('Failed to manually verify student email', [
                    'student_id' => $student->getId(),
                    'error' => $e->getMessage(),
                    'verified_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
    }

    /**
     * Generate new password for student.
     */
    #[Route('/{id}/generate-password', name: 'generate_password', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function generatePassword(Request $request, Student $student, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('generate_password' . $student->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

        try {
            $newPassword = $this->studentService->generateRandomPassword();
            $hashedPassword = $this->passwordHasher->hashPassword($student, $newPassword);
            $student->setPassword($hashedPassword);

            // Clear any existing password reset tokens
            $student->clearPasswordResetToken();

            $entityManager->flush();

            // Send the new password via email
            $emailSent = $this->studentService->sendNewPasswordEmail($student, $newPassword);

            $this->logger->info('New password generated for student', [
                'student_id' => $student->getId(),
                'email_sent' => $emailSent,
                'generated_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => $emailSent ?
                    'Nouveau mot de passe généré et envoyé par email.' :
                    'Nouveau mot de passe généré (erreur d\'envoi email).',
                'password' => $newPassword, // Only for admin to see in case email fails
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to generate new password', [
                'student_id' => $student->getId(),
                'error' => $e->getMessage(),
                'generated_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la génération : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export students data to CSV.
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request, StudentRepository $studentRepository): Response
    {
        $this->logger->info('Students data export requested', [
            'exported_by' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $filters = array_filter([
                'search' => $request->query->get('search'),
                'status' => $request->query->get('status'),
                'email_verified' => $request->query->get('email_verified'),
                'city' => $request->query->get('city'),
                'profession' => $request->query->get('profession'),
                'registration_period' => $request->query->get('registration_period'),
            ]);

            $students = $studentRepository->findWithFilters($filters);

            $csvData = $this->studentService->exportToCsv($students);

            $response = new Response($csvData);
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="etudiants_' . date('Y-m-d') . '.csv"');

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Failed to export students data', [
                'error' => $e->getMessage(),
                'exported_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToRoute('admin_student_index');
        }
    }

    /**
     * Bulk actions on students.
     */
    #[Route('/bulk-action', name: 'bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request, StudentRepository $studentRepository, EntityManagerInterface $entityManager): Response
    {
        $action = $request->request->get('bulk_action');
        $studentIds = $request->request->all('student_ids') ?? [];

        if (empty($studentIds)) {
            $this->addFlash('warning', 'Aucun étudiant sélectionné.');

            return $this->redirectToRoute('admin_student_index');
        }

        if (!$this->isCsrfTokenValid('bulk_action', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_student_index');
        }

        try {
            $students = $studentRepository->findBy(['id' => $studentIds]);
            $count = 0;

            foreach ($students as $student) {
                switch ($action) {
                    case 'activate':
                        $student->setIsActive(true);
                        $count++;
                        break;

                    case 'deactivate':
                        $student->setIsActive(false);
                        $count++;
                        break;

                    case 'verify_email':
                        if (!$student->isEmailVerified()) {
                            $student->verifyEmail();
                            $count++;
                        }
                        break;

                    case 'send_password_reset':
                        if ($this->studentService->sendPasswordResetEmail($student)) {
                            $count++;
                        }
                        break;
                }
            }

            if ($count > 0) {
                $entityManager->flush();
            }

            $actionLabels = [
                'activate' => 'activé(s)',
                'deactivate' => 'désactivé(s)',
                'verify_email' => 'email(s) vérifié(s)',
                'send_password_reset' => 'email(s) de réinitialisation envoyé(s)',
            ];

            $this->addFlash('success', "{$count} étudiant(s) {$actionLabels[$action]}.");

            $this->logger->info('Bulk action performed on students', [
                'action' => $action,
                'student_count' => $count,
                'performed_by' => $this->getUser()?->getUserIdentifier(),
            ]);
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'action groupée : ' . $e->getMessage());

            $this->logger->error('Failed to perform bulk action', [
                'action' => $action,
                'error' => $e->getMessage(),
                'performed_by' => $this->getUser()?->getUserIdentifier(),
            ]);
        }

        return $this->redirectToRoute('admin_student_index');
    }

    /**
     * Get client IP address.
     */
    private function getClientIp(): ?string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
