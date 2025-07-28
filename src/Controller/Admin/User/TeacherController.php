<?php

declare(strict_types=1);

namespace App\Controller\Admin\User;

use App\Entity\User\Teacher;
use App\Form\User\TeacherType;
use App\Repository\User\TeacherRepository;
use App\Service\User\TeacherService;
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
 * Admin Teacher Controller.
 *
 * Handles CRUD operations and management for teachers in the admin interface.
 * Provides comprehensive teacher management capabilities including password reset,
 * email verification management, and detailed filtering options.
 */
#[Route('/admin/teachers', name: 'admin_teacher_')]
#[IsGranted('ROLE_ADMIN')]
class TeacherController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private TeacherService $teacherService,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * List all teachers with advanced filtering and pagination.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, TeacherRepository $teacherRepository): Response
    {
        $this->logger->info('Admin teachers list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $search = $request->query->get('search');
        $status = $request->query->get('status');
        $emailVerified = $request->query->get('email_verified');
        $specialty = $request->query->get('specialty');
        $minExperience = $request->query->get('min_experience');

        $filters = [
            'search' => $search ?? '',
            'status' => $status ?? '',
            'email_verified' => $emailVerified ?? '',
            'specialty' => $specialty ?? '',
            'min_experience' => $minExperience ?? '',
        ];

        $teachers = $teacherRepository->findWithFilters($filters, $page, $limit);
        $totalTeachers = $teacherRepository->countWithFilters($filters);
        $totalPages = ceil($totalTeachers / $limit);

        // Get filter options
        $specialties = $teacherRepository->getDistinctSpecialties();

        // Get statistics for dashboard cards
        $statistics = $teacherRepository->getStatistics();

        return $this->render('admin/teacher/index.html.twig', [
            'teachers' => $teachers,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_teachers' => $totalTeachers,
            'filters' => $filters,
            'specialties' => $specialties ?? [],
            'statistics' => $statistics ?? [],
        ]);
    }

    /**
     * Show detailed teacher information.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Teacher $teacher): Response
    {
        $this->logger->info('Admin teacher details viewed', [
            'teacher_id' => $teacher->getId(),
            'viewed_by' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $this->render('admin/teacher/show.html.twig', [
            'teacher' => $teacher,
        ]);
    }

    /**
     * Create a new teacher.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $teacher = new Teacher();
        $form = $this->createForm(TeacherType::class, $teacher);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash password if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($teacher, $plainPassword);
                $teacher->setPassword($hashedPassword);
            } else {
                // Generate a random password if none provided
                $randomPassword = bin2hex(random_bytes(8));
                $hashedPassword = $this->passwordHasher->hashPassword($teacher, $randomPassword);
                $teacher->setPassword($hashedPassword);
                $this->addFlash('info', 'Un mot de passe temporaire a été généré : ' . $randomPassword);
            }

            $entityManager->persist($teacher);
            $entityManager->flush();

            $this->logger->info('New teacher created by admin', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'created_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Formateur créé avec succès.');

            return $this->redirectToRoute('admin_teacher_show', ['id' => $teacher->getId()]);
        }

        return $this->render('admin/teacher/new.html.twig', [
            'teacher' => $teacher,
            'form' => $form,
        ]);
    }

    /**
     * Edit a teacher.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Teacher $teacher, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TeacherType::class, $teacher);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update password if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($teacher, $plainPassword);
                $teacher->setPassword($hashedPassword);
            }

            $entityManager->flush();

            $this->logger->info('Teacher updated by admin', [
                'teacher_id' => $teacher->getId(),
                'updated_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Formateur modifié avec succès.');

            return $this->redirectToRoute('admin_teacher_show', ['id' => $teacher->getId()]);
        }

        return $this->render('admin/teacher/edit.html.twig', [
            'teacher' => $teacher,
            'form' => $form,
        ]);
    }

    /**
     * Delete a teacher.
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Teacher $teacher, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $teacher->getId(), $request->request->get('_token'))) {
            $teacherId = $teacher->getId();
            $teacherEmail = $teacher->getEmail();

            $entityManager->remove($teacher);
            $entityManager->flush();

            $this->logger->warning('Teacher deleted by admin', [
                'teacher_id' => $teacherId,
                'teacher_email' => $teacherEmail,
                'deleted_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Formateur supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_teacher_index');
    }

    /**
     * Send password reset email to teacher.
     */
    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resetPassword(Teacher $teacher): JsonResponse
    {
        try {
            $result = $this->teacherService->sendPasswordResetEmail($teacher);

            if ($result) {
                $this->logger->info('Password reset email sent to teacher by admin', [
                    'teacher_id' => $teacher->getId(),
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
            ], 400);
        } catch (Exception $e) {
            $this->logger->error('Failed to send password reset email to teacher', [
                'teacher_id' => $teacher->getId(),
                'error' => $e->getMessage(),
                'sent_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send email verification to teacher.
     */
    #[Route('/{id}/verify-email', name: 'verify_email', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function verifyEmail(Teacher $teacher, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            if ($teacher->isEmailVerified()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'L\'email est déjà vérifié.',
                ], 400);
            }

            $teacher->verifyEmail();
            $entityManager->flush();

            $this->logger->info('Teacher email verified by admin', [
                'teacher_id' => $teacher->getId(),
                'verified_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Email vérifié avec succès.',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to verify teacher email', [
                'teacher_id' => $teacher->getId(),
                'error' => $e->getMessage(),
                'verified_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la vérification : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate temporary password for teacher.
     */
    #[Route('/{id}/generate-password', name: 'generate_password', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function generatePassword(Teacher $teacher, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $newPassword = bin2hex(random_bytes(8));
            $hashedPassword = $this->passwordHasher->hashPassword($teacher, $newPassword);
            $teacher->setPassword($hashedPassword);
            $entityManager->flush();

            $this->logger->info('Temporary password generated for teacher by admin', [
                'teacher_id' => $teacher->getId(),
                'generated_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Mot de passe temporaire généré avec succès.',
                'password' => $newPassword,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to generate password for teacher', [
                'teacher_id' => $teacher->getId(),
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
     * Toggle teacher active status.
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Teacher $teacher, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $teacher->setIsActive(!$teacher->isActive());
            $entityManager->flush();

            $status = $teacher->isActive() ? 'activé' : 'désactivé';

            $this->logger->info('Teacher status toggled by admin', [
                'teacher_id' => $teacher->getId(),
                'new_status' => $teacher->isActive() ? 'active' : 'inactive',
                'toggled_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => "Formateur {$status} avec succès.",
                'is_active' => $teacher->isActive(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to toggle teacher status', [
                'teacher_id' => $teacher->getId(),
                'error' => $e->getMessage(),
                'toggled_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors du changement de statut : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export teachers to CSV.
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(TeacherRepository $teacherRepository): Response
    {
        $teachers = $teacherRepository->findAll();

        $csvData = [];
        $csvData[] = [
            'ID',
            'Prénom',
            'Nom',
            'Email',
            'Téléphone',
            'Spécialité',
            'Titre',
            'Années d\'expérience',
            'Statut',
            'Email vérifié',
            'Date d\'inscription',
            'Dernière connexion',
        ];

        foreach ($teachers as $teacher) {
            $csvData[] = [
                $teacher->getId(),
                $teacher->getFirstName(),
                $teacher->getLastName(),
                $teacher->getEmail(),
                $teacher->getPhone() ?? '',
                $teacher->getSpecialty() ?? '',
                $teacher->getTitle() ?? '',
                $teacher->getYearsOfExperience() ?? '',
                $teacher->isActive() ? 'Actif' : 'Inactif',
                $teacher->isEmailVerified() ? 'Oui' : 'Non',
                $teacher->getCreatedAt()?->format('d/m/Y H:i'),
                $teacher->getLastLoginAt()?->format('d/m/Y H:i') ?? 'Jamais',
            ];
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="formateurs_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://temp', 'r+');

        // Add BOM for proper UTF-8 display in Excel
        fwrite($output, "\xEF\xBB\xBF");

        foreach ($csvData as $row) {
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        $this->logger->info('Teachers CSV export performed', [
            'exported_by' => $this->getUser()?->getUserIdentifier(),
            'teacher_count' => count($teachers),
        ]);

        return $response;
    }

    /**
     * Bulk actions on teachers.
     */
    #[Route('/bulk-action', name: 'bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request, TeacherRepository $teacherRepository, EntityManagerInterface $entityManager): Response
    {
        $action = $request->request->get('action');
        $teacherIds = $request->request->all('teacher_ids');

        if (empty($teacherIds) || empty($action)) {
            $this->addFlash('error', 'Aucun formateur sélectionné ou action non spécifiée.');

            return $this->redirectToRoute('admin_teacher_index');
        }

        try {
            $teachers = $teacherRepository->findBy(['id' => $teacherIds]);
            $count = 0;

            foreach ($teachers as $teacher) {
                switch ($action) {
                    case 'activate':
                        $teacher->setIsActive(true);
                        $count++;
                        break;

                    case 'deactivate':
                        $teacher->setIsActive(false);
                        $count++;
                        break;

                    case 'verify_email':
                        if (!$teacher->isEmailVerified()) {
                            $teacher->verifyEmail();
                            $count++;
                        }
                        break;

                    case 'send_password_reset':
                        if ($this->teacherService->sendPasswordResetEmail($teacher)) {
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

            $this->addFlash('success', "{$count} formateur(s) {$actionLabels[$action]}.");

            $this->logger->info('Bulk action performed on teachers', [
                'action' => $action,
                'teacher_count' => $count,
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

        return $this->redirectToRoute('admin_teacher_index');
    }

    /**
     * Get client IP address.
     */
    private function getClientIp(): ?string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
