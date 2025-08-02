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
#[Route('/admin/teachers')]
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
    #[Route('/', name: 'admin_teacher_index', methods: ['GET'])]
    public function index(Request $request, TeacherRepository $teacherRepository): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $clientIp = $request->getClientIp();
        
        $this->logger->info('Admin teachers list accessed - START', [
            'user' => $userIdentifier,
            'ip' => $clientIp,
            'user_agent' => $request->headers->get('User-Agent'),
            'timestamp' => new \DateTime(),
        ]);

        try {
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

            $this->logger->debug('Teachers list filters applied', [
                'user' => $userIdentifier,
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit,
            ]);

            // Execute repository queries with detailed logging
            $this->logger->debug('Executing findWithFilters query', [
                'user' => $userIdentifier,
                'filters' => $filters,
            ]);
            $teachers = $teacherRepository->findWithFilters($filters, $page, $limit);
            
            $this->logger->debug('Executing countWithFilters query', [
                'user' => $userIdentifier,
                'filters' => $filters,
            ]);
            $totalTeachers = $teacherRepository->countWithFilters($filters);
            $totalPages = ceil($totalTeachers / $limit);

            $this->logger->debug('Query results obtained', [
                'user' => $userIdentifier,
                'teachers_count' => count($teachers),
                'total_teachers' => $totalTeachers,
                'total_pages' => $totalPages,
            ]);

            // Get filter options
            $this->logger->debug('Fetching distinct specialties', [
                'user' => $userIdentifier,
            ]);
            $specialties = $teacherRepository->getDistinctSpecialties();

            // Get statistics for dashboard cards
            $this->logger->debug('Fetching statistics', [
                'user' => $userIdentifier,
            ]);
            $statistics = $teacherRepository->getStatistics();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info('Admin teachers list accessed - SUCCESS', [
                'user' => $userIdentifier,
                'ip' => $clientIp,
                'execution_time_ms' => $executionTime,
                'teachers_count' => count($teachers),
                'total_teachers' => $totalTeachers,
                'filters_applied' => array_filter($filters),
                'specialties_count' => count($specialties ?? []),
                'statistics_available' => !empty($statistics),
            ]);

            return $this->render('admin/teacher/index.html.twig', [
                'teachers' => $teachers,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_teachers' => $totalTeachers,
                'filters' => $filters,
                'specialties' => $specialties ?? [],
                'statistics' => $statistics ?? [],
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in teachers list', [
                'user' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'filters' => $filters ?? [],
                'page' => $page ?? 1,
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors du chargement des formateurs.');
            
            return $this->render('admin/teacher/index.html.twig', [
                'teachers' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_teachers' => 0,
                'filters' => $filters ?? [],
                'specialties' => [],
                'statistics' => [],
            ]);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->critical('Unexpected error in teachers list', [
                'user' => $userIdentifier,
                'ip' => $clientIp,
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'filters' => $filters ?? [],
                'page' => $page ?? 1,
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du chargement des formateurs.');
            
            return $this->render('admin/teacher/index.html.twig', [
                'teachers' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_teachers' => 0,
                'filters' => [],
                'specialties' => [],
                'statistics' => [],
            ]);
        }
    }

    /**
     * Show detailed teacher information.
     */
    #[Route('/{id}', name: 'admin_teacher_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Teacher $teacher): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin teacher details viewed - START', [
            'teacher_id' => $teacher->getId(),
            'teacher_email' => $teacher->getEmail(),
            'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
            'viewed_by' => $userIdentifier,
            'timestamp' => new \DateTime(),
        ]);

        try {
            // Log detailed teacher information access
            $this->logger->debug('Teacher details access', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_status' => $teacher->isActive() ? 'active' : 'inactive',
                'email_verified' => $teacher->isEmailVerified(),
                'specialty' => $teacher->getSpecialty(),
                'years_experience' => $teacher->getYearsOfExperience(),
                'last_login' => $teacher->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'created_at' => $teacher->getCreatedAt()?->format('Y-m-d H:i:s'),
                'viewed_by' => $userIdentifier,
            ]);

            $this->logger->info('Admin teacher details viewed - SUCCESS', [
                'teacher_id' => $teacher->getId(),
                'viewed_by' => $userIdentifier,
            ]);

            return $this->render('admin/teacher/show.html.twig', [
                'teacher' => $teacher,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error displaying teacher details', [
                'teacher_id' => $teacher->getId(),
                'viewed_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'affichage des détails du formateur.');
            
            return $this->redirectToRoute('admin_teacher_index');
        }
    }

    /**
     * Create a new teacher.
     */
    #[Route('/new', name: 'admin_teacher_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $clientIp = $request->getClientIp();
        
        $this->logger->info('New teacher creation accessed', [
            'accessed_by' => $userIdentifier,
            'ip' => $clientIp,
            'method' => $request->getMethod(),
            'timestamp' => new \DateTime(),
        ]);

        try {
            $teacher = new Teacher();
            $form = $this->createForm(TeacherType::class, $teacher);
            
            $this->logger->debug('Teacher form created', [
                'form_name' => $form->getName(),
                'accessed_by' => $userIdentifier,
            ]);
            
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Teacher creation form submitted', [
                    'form_valid' => $form->isValid(),
                    'submitted_by' => $userIdentifier,
                    'form_errors' => $form->isValid() ? [] : (string) $form->getErrors(true, false),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Processing valid teacher form', [
                        'teacher_email' => $teacher->getEmail(),
                        'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                        'submitted_by' => $userIdentifier,
                    ]);

                    // Hash password if provided
                    $plainPassword = $form->get('plainPassword')->getData();
                    $passwordGenerated = false;
                    
                    if ($plainPassword) {
                        $this->logger->debug('Using provided password for new teacher', [
                            'teacher_email' => $teacher->getEmail(),
                            'submitted_by' => $userIdentifier,
                        ]);
                        $hashedPassword = $this->passwordHasher->hashPassword($teacher, $plainPassword);
                        $teacher->setPassword($hashedPassword);
                    } else {
                        // Generate a random password if none provided
                        $randomPassword = bin2hex(random_bytes(8));
                        $hashedPassword = $this->passwordHasher->hashPassword($teacher, $randomPassword);
                        $teacher->setPassword($hashedPassword);
                        $passwordGenerated = true;
                        
                        $this->logger->info('Generated temporary password for new teacher', [
                            'teacher_email' => $teacher->getEmail(),
                            'password_length' => strlen($randomPassword),
                            'submitted_by' => $userIdentifier,
                        ]);
                        
                        $this->addFlash('info', 'Un mot de passe temporaire a été généré : ' . $randomPassword);
                    }

                    $this->logger->debug('Persisting new teacher to database', [
                        'teacher_email' => $teacher->getEmail(),
                        'submitted_by' => $userIdentifier,
                    ]);

                    $entityManager->persist($teacher);
                    $entityManager->flush();

                    $this->logger->info('New teacher created by admin - SUCCESS', [
                        'teacher_id' => $teacher->getId(),
                        'teacher_email' => $teacher->getEmail(),
                        'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                        'specialty' => $teacher->getSpecialty(),
                        'years_experience' => $teacher->getYearsOfExperience(),
                        'password_generated' => $passwordGenerated,
                        'created_by' => $userIdentifier,
                        'ip' => $clientIp,
                    ]);

                    $this->addFlash('success', 'Formateur créé avec succès.');

                    return $this->redirectToRoute('admin_teacher_show', ['id' => $teacher->getId()]);
                } else {
                    $this->logger->warning('Invalid teacher creation form submitted', [
                        'form_errors' => (string) $form->getErrors(true, false),
                        'submitted_by' => $userIdentifier,
                        'attempted_email' => $teacher->getEmail(),
                    ]);
                }
            } else {
                $this->logger->debug('Teacher creation form displayed', [
                    'accessed_by' => $userIdentifier,
                ]);
            }

            return $this->render('admin/teacher/new.html.twig', [
                'teacher' => $teacher,
                'form' => $form,
            ]);

        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $this->logger->error('Duplicate email constraint violation during teacher creation', [
                'attempted_email' => $teacher->getEmail() ?? 'unknown',
                'created_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Un formateur avec cette adresse email existe déjà.');
            
            return $this->render('admin/teacher/new.html.twig', [
                'teacher' => $teacher ?? new Teacher(),
                'form' => $form ?? $this->createForm(TeacherType::class, new Teacher()),
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during teacher creation', [
                'attempted_email' => $teacher->getEmail() ?? 'unknown',
                'created_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la création du formateur.');
            
            return $this->render('admin/teacher/new.html.twig', [
                'teacher' => $teacher ?? new Teacher(),
                'form' => $form ?? $this->createForm(TeacherType::class, new Teacher()),
            ]);

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during teacher creation', [
                'attempted_email' => $teacher->getEmail() ?? 'unknown',
                'created_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la création du formateur.');
            
            return $this->render('admin/teacher/new.html.twig', [
                'teacher' => new Teacher(),
                'form' => $this->createForm(TeacherType::class, new Teacher()),
            ]);
        }
    }

    /**
     * Edit a teacher.
     */
    #[Route('/{id}/edit', name: 'admin_teacher_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Teacher $teacher, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $clientIp = $request->getClientIp();
        
        $this->logger->info('Teacher edit accessed', [
            'teacher_id' => $teacher->getId(),
            'teacher_email' => $teacher->getEmail(),
            'edited_by' => $userIdentifier,
            'ip' => $clientIp,
            'method' => $request->getMethod(),
            'timestamp' => new \DateTime(),
        ]);

        try {
            // Store original values for comparison
            $originalData = [
                'email' => $teacher->getEmail(),
                'first_name' => $teacher->getFirstName(),
                'last_name' => $teacher->getLastName(),
                'phone' => $teacher->getPhone(),
                'specialty' => $teacher->getSpecialty(),
                'title' => $teacher->getTitle(),
                'years_experience' => $teacher->getYearsOfExperience(),
                'is_active' => $teacher->isActive(),
            ];

            $form = $this->createForm(TeacherType::class, $teacher);
            
            $this->logger->debug('Teacher edit form created', [
                'teacher_id' => $teacher->getId(),
                'form_name' => $form->getName(),
                'edited_by' => $userIdentifier,
            ]);
            
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Teacher edit form submitted', [
                    'teacher_id' => $teacher->getId(),
                    'form_valid' => $form->isValid(),
                    'edited_by' => $userIdentifier,
                    'form_errors' => $form->isValid() ? [] : (string) $form->getErrors(true, false),
                ]);

                if ($form->isValid()) {
                    $passwordChanged = false;
                    
                    // Update password if provided
                    $plainPassword = $form->get('plainPassword')->getData();
                    if ($plainPassword) {
                        $hashedPassword = $this->passwordHasher->hashPassword($teacher, $plainPassword);
                        $teacher->setPassword($hashedPassword);
                        $passwordChanged = true;
                        
                        $this->logger->info('Teacher password updated', [
                            'teacher_id' => $teacher->getId(),
                            'edited_by' => $userIdentifier,
                        ]);
                    }

                    // Log changes
                    $newData = [
                        'email' => $teacher->getEmail(),
                        'first_name' => $teacher->getFirstName(),
                        'last_name' => $teacher->getLastName(),
                        'phone' => $teacher->getPhone(),
                        'specialty' => $teacher->getSpecialty(),
                        'title' => $teacher->getTitle(),
                        'years_experience' => $teacher->getYearsOfExperience(),
                        'is_active' => $teacher->isActive(),
                    ];

                    $changes = [];
                    foreach ($originalData as $field => $originalValue) {
                        if ($originalValue !== $newData[$field]) {
                            $changes[$field] = [
                                'from' => $originalValue,
                                'to' => $newData[$field],
                            ];
                        }
                    }

                    $this->logger->debug('Flushing teacher changes to database', [
                        'teacher_id' => $teacher->getId(),
                        'changes' => $changes,
                        'password_changed' => $passwordChanged,
                        'edited_by' => $userIdentifier,
                    ]);

                    $entityManager->flush();

                    $this->logger->info('Teacher updated by admin - SUCCESS', [
                        'teacher_id' => $teacher->getId(),
                        'teacher_email' => $teacher->getEmail(),
                        'changes' => $changes,
                        'password_changed' => $passwordChanged,
                        'updated_by' => $userIdentifier,
                        'ip' => $clientIp,
                    ]);

                    $this->addFlash('success', 'Formateur modifié avec succès.');

                    return $this->redirectToRoute('admin_teacher_show', ['id' => $teacher->getId()]);
                } else {
                    $this->logger->warning('Invalid teacher edit form submitted', [
                        'teacher_id' => $teacher->getId(),
                        'form_errors' => (string) $form->getErrors(true, false),
                        'edited_by' => $userIdentifier,
                    ]);
                }
            } else {
                $this->logger->debug('Teacher edit form displayed', [
                    'teacher_id' => $teacher->getId(),
                    'edited_by' => $userIdentifier,
                ]);
            }

            return $this->render('admin/teacher/edit.html.twig', [
                'teacher' => $teacher,
                'form' => $form,
            ]);

        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $this->logger->error('Duplicate email constraint violation during teacher edit', [
                'teacher_id' => $teacher->getId(),
                'attempted_email' => $teacher->getEmail(),
                'edited_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Un formateur avec cette adresse email existe déjà.');
            
            return $this->render('admin/teacher/edit.html.twig', [
                'teacher' => $teacher,
                'form' => $form ?? $this->createForm(TeacherType::class, $teacher),
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during teacher edit', [
                'teacher_id' => $teacher->getId(),
                'edited_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la modification du formateur.');
            
            return $this->render('admin/teacher/edit.html.twig', [
                'teacher' => $teacher,
                'form' => $form ?? $this->createForm(TeacherType::class, $teacher),
            ]);

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during teacher edit', [
                'teacher_id' => $teacher->getId(),
                'edited_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la modification du formateur.');
            
            return $this->render('admin/teacher/edit.html.twig', [
                'teacher' => $teacher,
                'form' => $this->createForm(TeacherType::class, $teacher),
            ]);
        }
    }

    /**
     * Delete a teacher.
     */
    #[Route('/{id}', name: 'admin_teacher_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Teacher $teacher, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $clientIp = $request->getClientIp();
        $teacherId = $teacher->getId();
        $teacherEmail = $teacher->getEmail();
        $teacherName = $teacher->getFirstName() . ' ' . $teacher->getLastName();
        
        $this->logger->info('Teacher deletion attempted', [
            'teacher_id' => $teacherId,
            'teacher_email' => $teacherEmail,
            'teacher_name' => $teacherName,
            'deleted_by' => $userIdentifier,
            'ip' => $clientIp,
            'timestamp' => new \DateTime(),
        ]);

        try {
            $token = $request->request->get('_token');
            
            $this->logger->debug('Validating CSRF token for teacher deletion', [
                'teacher_id' => $teacherId,
                'token_provided' => !empty($token),
                'deleted_by' => $userIdentifier,
            ]);

            if ($this->isCsrfTokenValid('delete' . $teacherId, $token)) {
                $this->logger->debug('CSRF token validated, proceeding with deletion', [
                    'teacher_id' => $teacherId,
                    'deleted_by' => $userIdentifier,
                ]);

                // Log teacher details before deletion
                $teacherDetails = [
                    'id' => $teacherId,
                    'email' => $teacherEmail,
                    'name' => $teacherName,
                    'specialty' => $teacher->getSpecialty(),
                    'years_experience' => $teacher->getYearsOfExperience(),
                    'is_active' => $teacher->isActive(),
                    'email_verified' => $teacher->isEmailVerified(),
                    'created_at' => $teacher->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'last_login' => $teacher->getLastLoginAt()?->format('Y-m-d H:i:s'),
                ];

                $this->logger->debug('Removing teacher from database', [
                    'teacher_details' => $teacherDetails,
                    'deleted_by' => $userIdentifier,
                ]);

                $entityManager->remove($teacher);
                $entityManager->flush();

                $this->logger->warning('Teacher deleted by admin - SUCCESS', [
                    'teacher_details' => $teacherDetails,
                    'deleted_by' => $userIdentifier,
                    'ip' => $clientIp,
                ]);

                $this->addFlash('success', 'Formateur supprimé avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for teacher deletion', [
                    'teacher_id' => $teacherId,
                    'token_provided' => !empty($token),
                    'deleted_by' => $userIdentifier,
                    'ip' => $clientIp,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_teacher_index');

        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
            $this->logger->error('Foreign key constraint violation during teacher deletion', [
                'teacher_id' => $teacherId,
                'teacher_email' => $teacherEmail,
                'deleted_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Impossible de supprimer ce formateur car il est lié à d\'autres données (formations, sessions, etc.).');
            
            return $this->redirectToRoute('admin_teacher_index');

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during teacher deletion', [
                'teacher_id' => $teacherId,
                'teacher_email' => $teacherEmail,
                'deleted_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la suppression du formateur.');
            
            return $this->redirectToRoute('admin_teacher_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during teacher deletion', [
                'teacher_id' => $teacherId,
                'teacher_email' => $teacherEmail,
                'deleted_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la suppression du formateur.');
            
            return $this->redirectToRoute('admin_teacher_index');
        }
    }

    /**
     * Send password reset email to teacher.
     */
    #[Route('/{id}/reset-password', name: 'admin_teacher_reset_password', methods: ['POST'], requirements: ['id' => '\d+'])]
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
     * Manually verify teacher email.
     */
    #[Route('/{id}/verify-email', name: 'admin_teacher_verify_email', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function verifyEmail(Request $request, Teacher $teacher, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('verify_email' . $teacher->getId(), $request->request->get('_token'))) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Token CSRF invalide.',
            ], 400);
        }

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
     * Send email verification link to teacher.
     */
    #[Route('/{id}/send-email-verification', name: 'admin_teacher_send_email_verification', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sendEmailVerification(Request $request, Teacher $teacher): JsonResponse
    {
        if (!$this->isCsrfTokenValid('send_email_verification' . $teacher->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

        try {
            $success = $this->teacherService->sendEmailVerification($teacher);

            if ($success) {
                $this->logger->info('Email verification sent to teacher', [
                    'teacher_id' => $teacher->getId(),
                    'sent_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Email de vérification envoyé avec succès.',
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email de vérification.',
            ], 500);
        } catch (Exception $e) {
            $this->logger->error('Failed to send email verification to teacher', [
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
     * Generate temporary password for teacher.
     */
    #[Route('/{id}/generate-password', name: 'admin_teacher_generate_password', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function generatePassword(Request $request, Teacher $teacher, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('generate_password' . $teacher->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

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
    #[Route('/{id}/toggle-status', name: 'admin_teacher_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
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
    #[Route('/export', name: 'admin_teacher_export', methods: ['GET'])]
    public function export(TeacherRepository $teacherRepository): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Teachers CSV export started', [
            'exported_by' => $userIdentifier,
            'timestamp' => new \DateTime(),
        ]);

        try {
            $this->logger->debug('Fetching all teachers for export', [
                'exported_by' => $userIdentifier,
            ]);

            $teachers = $teacherRepository->findAll();
            $teacherCount = count($teachers);

            $this->logger->debug('Teachers fetched for export', [
                'teacher_count' => $teacherCount,
                'exported_by' => $userIdentifier,
            ]);

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

            $this->logger->debug('Building CSV data rows', [
                'teacher_count' => $teacherCount,
                'exported_by' => $userIdentifier,
            ]);

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

            $this->logger->debug('Creating CSV output stream', [
                'teacher_count' => $teacherCount,
                'exported_by' => $userIdentifier,
            ]);

            $output = fopen('php://temp', 'r+');

            // Add BOM for proper UTF-8 display in Excel
            fwrite($output, "\xEF\xBB\xBF");

            foreach ($csvData as $row) {
                fputcsv($output, $row, ';');
            }

            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);

            $response->setContent($content);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Teachers CSV export completed - SUCCESS', [
                'exported_by' => $userIdentifier,
                'teacher_count' => $teacherCount,
                'execution_time_ms' => $executionTime,
                'file_size_bytes' => strlen($content),
                'filename' => 'formateurs_' . date('Y-m-d') . '.csv',
            ]);

            return $response;

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during teachers export', [
                'exported_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de l\'export des formateurs.');
            
            return $this->redirectToRoute('admin_teacher_index');

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->critical('Unexpected error during teachers export', [
                'exported_by' => $userIdentifier,
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'export des formateurs.');
            
            return $this->redirectToRoute('admin_teacher_index');
        }
    }

    /**
     * Bulk actions on teachers.
     */
    #[Route('/bulk-action', name: 'admin_teacher_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request, TeacherRepository $teacherRepository, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $clientIp = $request->getClientIp();
        
        $action = $request->request->get('action');
        $teacherIds = $request->request->all('teacher_ids');

        $this->logger->info('Bulk action on teachers started', [
            'action' => $action,
            'teacher_ids' => $teacherIds,
            'teacher_count' => count($teacherIds ?? []),
            'performed_by' => $userIdentifier,
            'ip' => $clientIp,
            'timestamp' => new \DateTime(),
        ]);

        if (empty($teacherIds) || empty($action)) {
            $this->logger->warning('Bulk action failed - missing parameters', [
                'action' => $action,
                'teacher_ids_provided' => !empty($teacherIds),
                'teacher_ids_count' => count($teacherIds ?? []),
                'performed_by' => $userIdentifier,
            ]);

            $this->addFlash('error', 'Aucun formateur sélectionné ou action non spécifiée.');
            return $this->redirectToRoute('admin_teacher_index');
        }

        try {
            $this->logger->debug('Fetching teachers for bulk action', [
                'action' => $action,
                'teacher_ids' => $teacherIds,
                'performed_by' => $userIdentifier,
            ]);

            $teachers = $teacherRepository->findBy(['id' => $teacherIds]);
            $foundTeacherIds = array_map(fn($teacher) => $teacher->getId(), $teachers);
            $missingTeacherIds = array_diff($teacherIds, $foundTeacherIds);

            if (!empty($missingTeacherIds)) {
                $this->logger->warning('Some teachers not found for bulk action', [
                    'action' => $action,
                    'missing_teacher_ids' => $missingTeacherIds,
                    'performed_by' => $userIdentifier,
                ]);
            }

            $count = 0;
            $errors = [];

            $this->logger->debug('Executing bulk action on teachers', [
                'action' => $action,
                'found_teachers_count' => count($teachers),
                'performed_by' => $userIdentifier,
            ]);

            foreach ($teachers as $teacher) {
                try {
                    switch ($action) {
                        case 'activate':
                            if (!$teacher->isActive()) {
                                $teacher->setIsActive(true);
                                $count++;
                                $this->logger->debug('Teacher activated in bulk action', [
                                    'teacher_id' => $teacher->getId(),
                                    'teacher_email' => $teacher->getEmail(),
                                    'performed_by' => $userIdentifier,
                                ]);
                            }
                            break;

                        case 'deactivate':
                            if ($teacher->isActive()) {
                                $teacher->setIsActive(false);
                                $count++;
                                $this->logger->debug('Teacher deactivated in bulk action', [
                                    'teacher_id' => $teacher->getId(),
                                    'teacher_email' => $teacher->getEmail(),
                                    'performed_by' => $userIdentifier,
                                ]);
                            }
                            break;

                        case 'verify_email':
                            if (!$teacher->isEmailVerified()) {
                                $teacher->verifyEmail();
                                $count++;
                                $this->logger->debug('Teacher email verified in bulk action', [
                                    'teacher_id' => $teacher->getId(),
                                    'teacher_email' => $teacher->getEmail(),
                                    'performed_by' => $userIdentifier,
                                ]);
                            }
                            break;

                        case 'send_password_reset':
                            if ($this->teacherService->sendPasswordResetEmail($teacher)) {
                                $count++;
                                $this->logger->debug('Password reset email sent in bulk action', [
                                    'teacher_id' => $teacher->getId(),
                                    'teacher_email' => $teacher->getEmail(),
                                    'performed_by' => $userIdentifier,
                                ]);
                            } else {
                                $errors[] = "Échec de l'envoi pour {$teacher->getEmail()}";
                                $this->logger->warning('Failed to send password reset email in bulk action', [
                                    'teacher_id' => $teacher->getId(),
                                    'teacher_email' => $teacher->getEmail(),
                                    'performed_by' => $userIdentifier,
                                ]);
                            }
                            break;

                        default:
                            $this->logger->warning('Unknown bulk action requested', [
                                'action' => $action,
                                'teacher_id' => $teacher->getId(),
                                'performed_by' => $userIdentifier,
                            ]);
                            break;
                    }
                } catch (\Exception $teacherError) {
                    $errors[] = "Erreur pour {$teacher->getEmail()}: {$teacherError->getMessage()}";
                    $this->logger->error('Error processing individual teacher in bulk action', [
                        'action' => $action,
                        'teacher_id' => $teacher->getId(),
                        'teacher_email' => $teacher->getEmail(),
                        'error' => $teacherError->getMessage(),
                        'performed_by' => $userIdentifier,
                    ]);
                }
            }

            if ($count > 0) {
                $this->logger->debug('Flushing bulk action changes to database', [
                    'action' => $action,
                    'successful_count' => $count,
                    'performed_by' => $userIdentifier,
                ]);
                $entityManager->flush();
            }

            $actionLabels = [
                'activate' => 'activé(s)',
                'deactivate' => 'désactivé(s)',
                'verify_email' => 'email(s) vérifié(s)',
                'send_password_reset' => 'email(s) de réinitialisation envoyé(s)',
            ];

            $successMessage = "{$count} formateur(s) {$actionLabels[$action]}.";
            if (!empty($errors)) {
                $successMessage .= " Erreurs: " . implode(', ', $errors);
            }

            $this->addFlash('success', $successMessage);

            $this->logger->info('Bulk action on teachers completed - SUCCESS', [
                'action' => $action,
                'successful_count' => $count,
                'error_count' => count($errors),
                'errors' => $errors,
                'performed_by' => $userIdentifier,
                'ip' => $clientIp,
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during bulk action', [
                'action' => $action,
                'teacher_ids' => $teacherIds,
                'performed_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de l\'action groupée : ' . $e->getMessage());

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during bulk action', [
                'action' => $action,
                'teacher_ids' => $teacherIds,
                'performed_by' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'action groupée : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_teacher_index');
    }

    /**
     * Get client IP address.
     */
    private function getClientIp(): ?string
    {
        try {
            $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
            
            $this->logger->debug('Client IP detection', [
                'HTTP_X_FORWARDED_FOR' => $forwardedFor,
                'REMOTE_ADDR' => $remoteAddr,
                'detected_ip' => $forwardedFor ?? $remoteAddr,
            ]);
            
            return $forwardedFor ?? $remoteAddr;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to detect client IP', [
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }
}
