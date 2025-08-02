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
#[Route('/admin/students')]
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
    #[Route('/', name: 'admin_student_index', methods: ['GET'])]
    public function index(Request $request, StudentRepository $studentRepository): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $clientIp = $request->getClientIp();

        $this->logger->info('Admin students list access initiated', [
            'user' => $userIdentifier,
            'ip' => $clientIp,
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('referer'),
            'method' => $request->getMethod(),
            'route' => 'admin_student_index',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Extract and validate request parameters
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 20;
            $search = trim($request->query->get('search', ''));
            $status = $request->query->get('status');
            $emailVerified = $request->query->get('email_verified');
            $city = $request->query->get('city');
            $profession = $request->query->get('profession');
            $registrationPeriod = $request->query->get('registration_period');

            $this->logger->debug('Request parameters extracted', [
                'page' => $page,
                'limit' => $limit,
                'search' => $search ? '[REDACTED]' : '',
                'status' => $status,
                'email_verified' => $emailVerified,
                'city' => $city,
                'profession' => $profession,
                'registration_period' => $registrationPeriod,
                'user' => $userIdentifier,
            ]);

            $filters = [
                'search' => $search,
                'status' => $status ?? '',
                'email_verified' => $emailVerified ?? '',
                'city' => $city ?? '',
                'profession' => $profession ?? '',
                'registration_period' => $registrationPeriod ?? '',
            ];

            // Log filter application
            $activeFilters = array_filter($filters);
            $this->logger->info('Filters applied to student search', [
                'active_filters_count' => count($activeFilters),
                'active_filters' => array_keys($activeFilters),
                'has_search' => !empty($search),
                'user' => $userIdentifier,
            ]);

            // Execute repository queries with detailed logging
            $queryStartTime = microtime(true);

            try {
                $students = $studentRepository->findWithFilters($filters, $page, $limit);
                $studentsQueryTime = microtime(true) - $queryStartTime;

                $this->logger->debug('Students query executed successfully', [
                    'query_time_ms' => round($studentsQueryTime * 1000, 2),
                    'results_count' => count($students),
                    'page' => $page,
                    'limit' => $limit,
                    'user' => $userIdentifier,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to fetch students with filters', [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'filters' => $filters,
                    'page' => $page,
                    'limit' => $limit,
                    'user' => $userIdentifier,
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            try {
                $totalStudents = $studentRepository->countWithFilters($filters);
                $totalPages = ceil($totalStudents / $limit);

                $this->logger->debug('Students count query executed', [
                    'total_students' => $totalStudents,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'user' => $userIdentifier,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to count students with filters', [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'filters' => $filters,
                    'user' => $userIdentifier,
                ]);
                throw $e;
            }

            // Get filter options with error handling
            $cities = [];
            $professions = [];
            $statistics = [];

            try {
                $cities = $studentRepository->getDistinctCities();
                $this->logger->debug('Distinct cities fetched', [
                    'cities_count' => count($cities),
                    'user' => $userIdentifier,
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Failed to fetch distinct cities', [
                    'error' => $e->getMessage(),
                    'user' => $userIdentifier,
                ]);
            }

            try {
                $professions = $studentRepository->getDistinctProfessions();
                $this->logger->debug('Distinct professions fetched', [
                    'professions_count' => count($professions),
                    'user' => $userIdentifier,
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Failed to fetch distinct professions', [
                    'error' => $e->getMessage(),
                    'user' => $userIdentifier,
                ]);
            }

            try {
                $statistics = $studentRepository->getStatistics();
                $this->logger->debug('Student statistics fetched', [
                    'statistics_keys' => array_keys($statistics),
                    'user' => $userIdentifier,
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Failed to fetch student statistics', [
                    'error' => $e->getMessage(),
                    'user' => $userIdentifier,
                ]);
            }

            $totalTime = microtime(true) - $startTime;

            $this->logger->info('Admin students list loaded successfully', [
                'user' => $userIdentifier,
                'total_time_ms' => round($totalTime * 1000, 2),
                'students_count' => count($students),
                'total_students' => $totalStudents,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'cities_count' => count($cities),
                'professions_count' => count($professions),
                'has_statistics' => !empty($statistics),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/student/index.html.twig', [
                'students' => $students,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_students' => $totalStudents,
                'filters' => $filters,
                'cities' => $cities,
                'professions' => $professions,
                'statistics' => $statistics,
            ]);
        } catch (Exception $e) {
            $totalTime = microtime(true) - $startTime;

            $this->logger->critical('Critical error in admin students list', [
                'user' => $userIdentifier,
                'ip' => $clientIp,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_time_ms' => round($totalTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'page' => $request->query->get('page'),
                    'search' => $request->query->get('search') ? '[REDACTED]' : null,
                    'status' => $request->query->get('status'),
                ],
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue lors du chargement de la liste des étudiants. Veuillez réessayer.');

            // Return a minimal view in case of error
            return $this->render('admin/student/index.html.twig', [
                'students' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_students' => 0,
                'filters' => [],
                'cities' => [],
                'professions' => [],
                'statistics' => [],
                'error' => true,
            ]);
        }
    }

    /**
     * Show detailed student information.
     */
    #[Route('/{id}', name: 'admin_student_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Student $student): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $studentId = $student->getId();

        $this->logger->info('Student details view initiated', [
            'student_id' => $studentId,
            'student_email' => $student->getEmail(),
            'student_status' => $student->isActive() ? 'active' : 'inactive',
            'viewed_by' => $userIdentifier,
            'route' => 'admin_student_show',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Log student data access for audit purposes
            $this->logger->debug('Student data accessed for viewing', [
                'student_id' => $studentId,
                'student_data' => [
                    'email' => $student->getEmail(),
                    'first_name' => $student->getFirstName() ?? '[NOT_SET]',
                    'last_name' => $student->getLastName() ?? '[NOT_SET]',
                    'is_active' => $student->isActive(),
                    'email_verified' => $student->isEmailVerified(),
                    'created_at' => $student->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updated_at' => $student->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    'last_login' => $student->getLastLoginAt()?->format('Y-m-d H:i:s') ?? 'Never',
                    'city' => $student->getCity() ?? '[NOT_SET]',
                    'profession' => $student->getProfession() ?? '[NOT_SET]',
                ],
                'viewed_by' => $userIdentifier,
                'access_level' => 'admin_full_access',
            ]);

            // Check for any security concerns
            if (!$student->isActive()) {
                $this->logger->warning('Inactive student profile accessed', [
                    'student_id' => $studentId,
                    'student_email' => $student->getEmail(),
                    'viewed_by' => $userIdentifier,
                    'reason' => 'admin_accessing_inactive_student',
                ]);
            }

            if (!$student->isEmailVerified()) {
                $this->logger->notice('Unverified student profile accessed', [
                    'student_id' => $studentId,
                    'student_email' => $student->getEmail(),
                    'viewed_by' => $userIdentifier,
                    'verification_status' => 'email_not_verified',
                ]);
            }

            $this->logger->info('Student details successfully displayed', [
                'student_id' => $studentId,
                'viewed_by' => $userIdentifier,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/student/show.html.twig', [
                'student' => $student,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error displaying student details', [
                'student_id' => $studentId,
                'viewed_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage des détails de l\'étudiant.');

            return $this->redirectToRoute('admin_student_index');
        }
    }

    /**
     * Create new student.
     */
    #[Route('/new', name: 'admin_student_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $startTime = microtime(true);

        $this->logger->info('Student creation form accessed', [
            'created_by' => $userIdentifier,
            'method' => $request->getMethod(),
            'route' => 'admin_student_new',
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $student = new Student();
            
            $this->logger->debug('New student entity created', [
                'entity_class' => get_class($student),
                'created_by' => $userIdentifier,
            ]);

            $form = $this->createForm(StudentType::class, $student, ['is_admin_creation' => true]);

            $this->logger->debug('Student form created', [
                'form_class' => get_class($form),
                'form_options' => ['is_admin_creation' => true],
                'created_by' => $userIdentifier,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Student creation form submitted', [
                    'created_by' => $userIdentifier,
                    'form_valid' => $form->isValid(),
                    'submission_timestamp' => date('Y-m-d H:i:s'),
                ]);

                if ($form->isValid()) {
                    try {
                        $formData = $form->getData();
                        $plainPassword = $form->get('plainPassword')->getData();
                        $emailVerified = $form->get('emailVerified')->getData() ?? false;
                        $isActive = $form->get('isActive')->getData() ?? true;
                        $sendWelcomeEmail = $form->get('sendWelcomeEmail')->getData();

                        $this->logger->debug('Form data extracted for student creation', [
                            'email' => $formData->getEmail(),
                            'first_name' => $formData->getFirstName(),
                            'last_name' => $formData->getLastName(),
                            'has_password' => !empty($plainPassword),
                            'email_verified' => $emailVerified,
                            'is_active' => $isActive,
                            'send_welcome_email' => $sendWelcomeEmail,
                            'created_by' => $userIdentifier,
                        ]);

                        // Handle password hashing if provided
                        if ($plainPassword) {
                            try {
                                $hashedPassword = $this->passwordHasher->hashPassword($student, $plainPassword);
                                $student->setPassword($hashedPassword);
                                
                                $this->logger->debug('Password hashed successfully', [
                                    'student_email' => $student->getEmail(),
                                    'password_length' => strlen($plainPassword),
                                    'created_by' => $userIdentifier,
                                ]);
                            } catch (Exception $e) {
                                $this->logger->error('Password hashing failed', [
                                    'error' => $e->getMessage(),
                                    'student_email' => $student->getEmail(),
                                    'created_by' => $userIdentifier,
                                ]);
                                throw $e;
                            }
                        }

                        // Set initial values for admin creation
                        $student->setEmailVerified($emailVerified);
                        $student->setIsActive($isActive);

                        $this->logger->debug('Student entity prepared for persistence', [
                            'student_email' => $student->getEmail(),
                            'email_verified' => $student->isEmailVerified(),
                            'is_active' => $student->isActive(),
                            'created_by' => $userIdentifier,
                        ]);

                        // Persist to database
                        try {
                            $entityManager->persist($student);
                            $entityManager->flush();

                            $this->logger->info('Student persisted to database successfully', [
                                'student_id' => $student->getId(),
                                'student_email' => $student->getEmail(),
                                'created_by' => $userIdentifier,
                                'persistence_timestamp' => date('Y-m-d H:i:s'),
                            ]);
                        } catch (Exception $e) {
                            $this->logger->error('Database persistence failed for student creation', [
                                'error' => $e->getMessage(),
                                'error_code' => $e->getCode(),
                                'student_email' => $student->getEmail(),
                                'created_by' => $userIdentifier,
                                'trace' => $e->getTraceAsString(),
                            ]);
                            throw $e;
                        }

                        // Send welcome email if requested
                        if ($sendWelcomeEmail) {
                            try {
                                $emailSent = $this->studentService->sendWelcomeEmail($student, $plainPassword);
                                
                                $this->logger->info('Welcome email sending attempted', [
                                    'student_id' => $student->getId(),
                                    'student_email' => $student->getEmail(),
                                    'email_sent' => $emailSent,
                                    'created_by' => $userIdentifier,
                                ]);

                                if (!$emailSent) {
                                    $this->logger->warning('Welcome email failed to send', [
                                        'student_id' => $student->getId(),
                                        'student_email' => $student->getEmail(),
                                        'created_by' => $userIdentifier,
                                    ]);
                                }
                            } catch (Exception $e) {
                                $this->logger->error('Welcome email sending failed with exception', [
                                    'student_id' => $student->getId(),
                                    'student_email' => $student->getEmail(),
                                    'error' => $e->getMessage(),
                                    'created_by' => $userIdentifier,
                                ]);
                                // Don't throw - student creation was successful
                            }
                        }

                        $totalTime = microtime(true) - $startTime;

                        $this->addFlash('success', 'Étudiant créé avec succès.');

                        $this->logger->info('Student creation completed successfully', [
                            'student_id' => $student->getId(),
                            'student_email' => $student->getEmail(),
                            'created_by' => $userIdentifier,
                            'total_time_ms' => round($totalTime * 1000, 2),
                            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                            'welcome_email_sent' => $sendWelcomeEmail,
                        ]);

                        return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
                    } catch (Exception $e) {
                        $entityManager->rollback();

                        $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());

                        $this->logger->error('Student creation failed with exception', [
                            'error' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'created_by' => $userIdentifier,
                            'form_data' => [
                                'email' => $form->get('email')->getData(),
                                'first_name' => $form->get('firstName')->getData(),
                                'last_name' => $form->get('lastName')->getData(),
                            ],
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                } else {
                    // Log form validation errors
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }

                    $this->logger->warning('Student creation form validation failed', [
                        'created_by' => $userIdentifier,
                        'form_errors' => $errors,
                        'form_data' => [
                            'email' => $form->get('email')->getData(),
                            'first_name' => $form->get('firstName')->getData(),
                            'last_name' => $form->get('lastName')->getData(),
                        ],
                    ]);
                }
            }

            $this->logger->debug('Rendering student creation form', [
                'created_by' => $userIdentifier,
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
            ]);

            return $this->render('admin/student/new.html.twig', [
                'student' => $student,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $totalTime = microtime(true) - $startTime;

            $this->logger->critical('Critical error in student creation process', [
                'created_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_time_ms' => round($totalTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la création de l\'étudiant. Veuillez réessayer.');

            return $this->redirectToRoute('admin_student_index');
        }
    }

    /**
     * Edit existing student.
     */
    #[Route('/{id}/edit', name: 'admin_student_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $studentId = $student->getId();
        $startTime = microtime(true);

        $this->logger->info('Student edit form accessed', [
            'student_id' => $studentId,
            'student_email' => $student->getEmail(),
            'edited_by' => $userIdentifier,
            'method' => $request->getMethod(),
            'route' => 'admin_student_edit',
            'ip' => $request->getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Store original data for comparison
            $originalData = [
                'email' => $student->getEmail(),
                'first_name' => $student->getFirstName(),
                'last_name' => $student->getLastName(),
                'is_active' => $student->isActive(),
                'email_verified' => $student->isEmailVerified(),
                'city' => $student->getCity(),
                'profession' => $student->getProfession(),
            ];

            $originalPassword = $student->getPassword();

            $this->logger->debug('Original student data captured for edit comparison', [
                'student_id' => $studentId,
                'original_data' => $originalData,
                'edited_by' => $userIdentifier,
            ]);

            $form = $this->createForm(StudentType::class, $student, ['is_edit' => true]);

            $this->logger->debug('Student edit form created', [
                'student_id' => $studentId,
                'form_class' => get_class($form),
                'form_options' => ['is_edit' => true],
                'edited_by' => $userIdentifier,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Student edit form submitted', [
                    'student_id' => $studentId,
                    'edited_by' => $userIdentifier,
                    'form_valid' => $form->isValid(),
                    'submission_timestamp' => date('Y-m-d H:i:s'),
                ]);

                if ($form->isValid()) {
                    try {
                        $plainPassword = $form->get('plainPassword')->getData();

                        // Detect changes in student data
                        $changes = [];
                        $currentData = [
                            'email' => $student->getEmail(),
                            'first_name' => $student->getFirstName(),
                            'last_name' => $student->getLastName(),
                            'is_active' => $student->isActive(),
                            'email_verified' => $student->isEmailVerified(),
                            'city' => $student->getCity(),
                            'profession' => $student->getProfession(),
                        ];

                        foreach ($originalData as $field => $originalValue) {
                            if ($currentData[$field] !== $originalValue) {
                                $changes[$field] = [
                                    'from' => $originalValue,
                                    'to' => $currentData[$field],
                                ];
                            }
                        }

                        $this->logger->info('Student data changes detected', [
                            'student_id' => $studentId,
                            'changes_count' => count($changes),
                            'changed_fields' => array_keys($changes),
                            'changes' => $changes,
                            'password_changed' => !empty($plainPassword),
                            'edited_by' => $userIdentifier,
                        ]);

                        // Handle password change if provided
                        if ($plainPassword) {
                            try {
                                $hashedPassword = $this->passwordHasher->hashPassword($student, $plainPassword);
                                $student->setPassword($hashedPassword);

                                $this->logger->info('Student password updated successfully', [
                                    'student_id' => $studentId,
                                    'student_email' => $student->getEmail(),
                                    'password_length' => strlen($plainPassword),
                                    'updated_by' => $userIdentifier,
                                    'update_timestamp' => date('Y-m-d H:i:s'),
                                ]);
                            } catch (Exception $e) {
                                $this->logger->error('Failed to hash new password', [
                                    'student_id' => $studentId,
                                    'error' => $e->getMessage(),
                                    'updated_by' => $userIdentifier,
                                ]);
                                throw $e;
                            }
                        } else {
                            // Keep original password if no new password provided
                            $student->setPassword($originalPassword);
                            
                            $this->logger->debug('Original password retained', [
                                'student_id' => $studentId,
                                'updated_by' => $userIdentifier,
                            ]);
                        }

                        // Persist changes to database
                        try {
                            $entityManager->flush();

                            $this->logger->info('Student changes persisted to database', [
                                'student_id' => $studentId,
                                'student_email' => $student->getEmail(),
                                'changes_applied' => $changes,
                                'password_updated' => !empty($plainPassword),
                                'updated_by' => $userIdentifier,
                                'persistence_timestamp' => date('Y-m-d H:i:s'),
                            ]);
                        } catch (Exception $e) {
                            $this->logger->error('Database persistence failed for student update', [
                                'student_id' => $studentId,
                                'error' => $e->getMessage(),
                                'error_code' => $e->getCode(),
                                'changes_attempted' => $changes,
                                'updated_by' => $userIdentifier,
                                'trace' => $e->getTraceAsString(),
                            ]);
                            throw $e;
                        }

                        $totalTime = microtime(true) - $startTime;

                        $this->addFlash('success', 'Étudiant modifié avec succès.');

                        $this->logger->info('Student edit completed successfully', [
                            'student_id' => $studentId,
                            'student_email' => $student->getEmail(),
                            'updated_by' => $userIdentifier,
                            'total_time_ms' => round($totalTime * 1000, 2),
                            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                            'changes_applied' => count($changes),
                        ]);

                        return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
                    } catch (Exception $e) {
                        $entityManager->rollback();

                        $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());

                        $this->logger->error('Student edit failed with exception', [
                            'student_id' => $studentId,
                            'error' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'updated_by' => $userIdentifier,
                            'original_data' => $originalData,
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                } else {
                    // Log form validation errors
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }

                    $this->logger->warning('Student edit form validation failed', [
                        'student_id' => $studentId,
                        'updated_by' => $userIdentifier,
                        'form_errors' => $errors,
                        'attempted_data' => [
                            'email' => $form->get('email')->getData(),
                            'first_name' => $form->get('firstName')->getData(),
                            'last_name' => $form->get('lastName')->getData(),
                        ],
                    ]);
                }
            }

            $this->logger->debug('Rendering student edit form', [
                'student_id' => $studentId,
                'edited_by' => $userIdentifier,
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
            ]);

            return $this->render('admin/student/edit.html.twig', [
                'student' => $student,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $totalTime = microtime(true) - $startTime;

            $this->logger->critical('Critical error in student edit process', [
                'student_id' => $studentId,
                'edited_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_time_ms' => round($totalTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la modification de l\'étudiant. Veuillez réessayer.');

            return $this->redirectToRoute('admin_student_show', ['id' => $studentId]);
        }
    }

    /**
     * Delete student (soft delete - deactivate).
     */
    #[Route('/{id}/delete', name: 'admin_student_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $studentId = $student->getId();
        $studentEmail = $student->getEmail();

        $this->logger->info('Student deletion initiated', [
            'student_id' => $studentId,
            'student_email' => $studentEmail,
            'deleted_by' => $userIdentifier,
            'route' => 'admin_student_delete',
            'ip' => $request->getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $token = $request->request->get('_token');
            $expectedToken = 'delete' . $studentId;

            $this->logger->debug('CSRF token validation for student deletion', [
                'student_id' => $studentId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'delete' . $studentId,
                'deleted_by' => $userIdentifier,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $token)) {
                try {
                    // Store pre-deletion state for audit
                    $preDeleteState = [
                        'was_active' => $student->isActive(),
                        'email_verified' => $student->isEmailVerified(),
                        'created_at' => $student->getCreatedAt()?->format('Y-m-d H:i:s'),
                        'last_login' => $student->getLastLoginAt()?->format('Y-m-d H:i:s'),
                        'city' => $student->getCity(),
                        'profession' => $student->getProfession(),
                    ];

                    $this->logger->info('Pre-deletion state captured', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'pre_delete_state' => $preDeleteState,
                        'deleted_by' => $userIdentifier,
                    ]);

                    // Soft delete - deactivate instead of removing
                    $student->setIsActive(false);

                    $this->logger->debug('Student deactivation applied', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'new_status' => 'inactive',
                        'deleted_by' => $userIdentifier,
                    ]);

                    try {
                        $entityManager->flush();

                        $this->logger->info('Student deactivation persisted to database', [
                            'student_id' => $studentId,
                            'student_email' => $studentEmail,
                            'deleted_by' => $userIdentifier,
                            'deletion_type' => 'soft_delete',
                            'persistence_timestamp' => date('Y-m-d H:i:s'),
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('Database persistence failed for student deactivation', [
                            'student_id' => $studentId,
                            'student_email' => $studentEmail,
                            'error' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'deleted_by' => $userIdentifier,
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }

                    $this->addFlash('success', 'Étudiant désactivé avec succès.');

                    $this->logger->info('Student deactivation completed successfully', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'deactivated_by' => $userIdentifier,
                        'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    ]);
                } catch (Exception $e) {
                    $entityManager->rollback();

                    $this->addFlash('error', 'Erreur lors de la désactivation : ' . $e->getMessage());

                    $this->logger->error('Student deactivation failed with exception', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'deactivated_by' => $userIdentifier,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for student deletion', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'token_provided' => !empty($token),
                    'expected_token' => $expectedToken,
                    'deleted_by' => $userIdentifier,
                    'ip' => $request->getClientIp(),
                    'security_incident' => 'csrf_token_mismatch',
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Opération annulée.');
            }

            return $this->redirectToRoute('admin_student_index');
        } catch (Exception $e) {
            $this->logger->critical('Critical error in student deletion process', [
                'student_id' => $studentId,
                'student_email' => $studentEmail,
                'deleted_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la désactivation de l\'étudiant.');

            return $this->redirectToRoute('admin_student_index');
        }
    }

    /**
     * Activate/deactivate student.
     */
    #[Route('/{id}/toggle-status', name: 'admin_student_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $studentId = $student->getId();
        $studentEmail = $student->getEmail();
        $currentStatus = $student->isActive();

        $this->logger->info('Student status toggle initiated', [
            'student_id' => $studentId,
            'student_email' => $studentEmail,
            'current_status' => $currentStatus ? 'active' : 'inactive',
            'requested_status' => !$currentStatus ? 'active' : 'inactive',
            'toggled_by' => $userIdentifier,
            'route' => 'admin_student_toggle_status',
            'ip' => $request->getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $token = $request->request->get('_token');
            $expectedToken = 'toggle_status' . $studentId;

            $this->logger->debug('CSRF token validation for status toggle', [
                'student_id' => $studentId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'toggle_status' . $studentId,
                'toggled_by' => $userIdentifier,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $token)) {
                try {
                    $newStatus = !$student->isActive();
                    $student->setIsActive($newStatus);

                    $this->logger->debug('Student status change applied', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'old_status' => $currentStatus,
                        'new_status' => $newStatus,
                        'toggled_by' => $userIdentifier,
                    ]);

                    try {
                        $entityManager->flush();

                        $this->logger->info('Student status change persisted to database', [
                            'student_id' => $studentId,
                            'student_email' => $studentEmail,
                            'status_changed_from' => $currentStatus ? 'active' : 'inactive',
                            'status_changed_to' => $newStatus ? 'active' : 'inactive',
                            'toggled_by' => $userIdentifier,
                            'persistence_timestamp' => date('Y-m-d H:i:s'),
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('Database persistence failed for status toggle', [
                            'student_id' => $studentId,
                            'student_email' => $studentEmail,
                            'attempted_status' => $newStatus,
                            'error' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'toggled_by' => $userIdentifier,
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }

                    $status = $student->isActive() ? 'activé' : 'désactivé';
                    $this->addFlash('success', "Étudiant {$status} avec succès.");

                    $this->logger->info('Student status toggle completed successfully', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'new_status' => $student->isActive(),
                        'status_label' => $status,
                        'updated_by' => $userIdentifier,
                        'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    ]);
                } catch (Exception $e) {
                    $entityManager->rollback();

                    $this->addFlash('error', 'Erreur lors du changement de statut : ' . $e->getMessage());

                    $this->logger->error('Student status toggle failed with exception', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'attempted_status' => !$currentStatus,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'updated_by' => $userIdentifier,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for student status toggle', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'token_provided' => !empty($token),
                    'expected_token' => $expectedToken,
                    'toggled_by' => $userIdentifier,
                    'ip' => $request->getClientIp(),
                    'security_incident' => 'csrf_token_mismatch',
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Opération annulée.');
            }

            return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
        } catch (Exception $e) {
            $this->logger->critical('Critical error in student status toggle process', [
                'student_id' => $studentId,
                'student_email' => $studentEmail,
                'current_status' => $currentStatus,
                'toggled_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors du changement de statut.');

            return $this->redirectToRoute('admin_student_show', ['id' => $studentId]);
        }
    }

    /**
     * Send password reset link to student.
     */
    #[Route('/{id}/send-password-reset', name: 'admin_student_send_password_reset', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sendPasswordReset(Request $request, Student $student): JsonResponse
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $studentId = $student->getId();
        $studentEmail = $student->getEmail();
        $startTime = microtime(true);

        $this->logger->info('Password reset email request initiated', [
            'student_id' => $studentId,
            'student_email' => $studentEmail,
            'requested_by' => $userIdentifier,
            'route' => 'admin_student_send_password_reset',
            'ip' => $request->getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $token = $request->request->get('_token');
            $expectedToken = 'send_password_reset' . $studentId;

            $this->logger->debug('CSRF token validation for password reset', [
                'student_id' => $studentId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'send_password_reset' . $studentId,
                'requested_by' => $userIdentifier,
            ]);

            if (!$this->isCsrfTokenValid($expectedToken, $token)) {
                $this->logger->warning('Invalid CSRF token for password reset', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'token_provided' => !empty($token),
                    'expected_token' => $expectedToken,
                    'requested_by' => $userIdentifier,
                    'ip' => $request->getClientIp(),
                    'security_incident' => 'csrf_token_mismatch',
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token CSRF invalide'
                ], 400);
            }

            // Validate student eligibility for password reset
            if (!$student->isActive()) {
                $this->logger->warning('Password reset attempted for inactive student', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'student_status' => 'inactive',
                    'requested_by' => $userIdentifier,
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Impossible d\'envoyer un email de réinitialisation pour un étudiant inactif.'
                ], 400);
            }

            try {
                $this->logger->debug('Initiating password reset email sending', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'service_class' => get_class($this->studentService),
                    'requested_by' => $userIdentifier,
                ]);

                $success = $this->studentService->sendPasswordResetEmail($student);

                $totalTime = microtime(true) - $startTime;

                if ($success) {
                    $this->logger->info('Password reset email sent successfully', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'sent_by' => $userIdentifier,
                        'email_service_response' => 'success',
                        'processing_time_ms' => round($totalTime * 1000, 2),
                        'timestamp' => date('Y-m-d H:i:s'),
                    ]);

                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Email de réinitialisation envoyé avec succès.',
                    ]);
                } else {
                    $this->logger->error('Password reset email sending failed', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'sent_by' => $userIdentifier,
                        'email_service_response' => 'failure',
                        'processing_time_ms' => round($totalTime * 1000, 2),
                        'error_type' => 'service_returned_false',
                    ]);

                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Erreur lors de l\'envoi de l\'email.',
                    ], 500);
                }
            } catch (Exception $e) {
                $totalTime = microtime(true) - $startTime;

                $this->logger->error('Password reset email sending failed with exception', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'sent_by' => $userIdentifier,
                    'processing_time_ms' => round($totalTime * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage(),
                ], 500);
            }
        } catch (Exception $e) {
            $totalTime = microtime(true) - $startTime;

            $this->logger->critical('Critical error in password reset process', [
                'student_id' => $studentId,
                'student_email' => $studentEmail,
                'requested_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'processing_time_ms' => round($totalTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur critique est survenue. Veuillez réessayer.',
            ], 500);
        }
    }

    /**
     * Send email verification link to student.
     */
    #[Route('/{id}/send-email-verification', name: 'admin_student_send_email_verification', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sendEmailVerification(Request $request, Student $student): JsonResponse
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $studentId = $student->getId();
        $studentEmail = $student->getEmail();
        $startTime = microtime(true);

        $this->logger->info('Email verification request initiated', [
            'student_id' => $studentId,
            'student_email' => $studentEmail,
            'current_verification_status' => $student->isEmailVerified(),
            'requested_by' => $userIdentifier,
            'route' => 'admin_student_send_email_verification',
            'ip' => $request->getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $token = $request->request->get('_token');
            $expectedToken = 'send_email_verification' . $studentId;

            $this->logger->debug('CSRF token validation for email verification', [
                'student_id' => $studentId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'send_email_verification' . $studentId,
                'requested_by' => $userIdentifier,
            ]);

            if (!$this->isCsrfTokenValid($expectedToken, $token)) {
                $this->logger->warning('Invalid CSRF token for email verification', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'token_provided' => !empty($token),
                    'expected_token' => $expectedToken,
                    'requested_by' => $userIdentifier,
                    'ip' => $request->getClientIp(),
                    'security_incident' => 'csrf_token_mismatch',
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token CSRF invalide'
                ], 400);
            }

            // Check if email is already verified
            if ($student->isEmailVerified()) {
                $this->logger->info('Email verification attempted for already verified student', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'verification_status' => 'already_verified',
                    'requested_by' => $userIdentifier,
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'L\'email de cet étudiant est déjà vérifié.',
                ], 400);
            }

            // Validate student eligibility for email verification
            if (!$student->isActive()) {
                $this->logger->warning('Email verification attempted for inactive student', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'student_status' => 'inactive',
                    'requested_by' => $userIdentifier,
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Impossible d\'envoyer un email de vérification pour un étudiant inactif.',
                ], 400);
            }

            try {
                $this->logger->debug('Initiating email verification sending', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'service_class' => get_class($this->studentService),
                    'requested_by' => $userIdentifier,
                ]);

                $success = $this->studentService->sendEmailVerification($student);

                $totalTime = microtime(true) - $startTime;

                if ($success) {
                    $this->logger->info('Email verification sent successfully', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'sent_by' => $userIdentifier,
                        'email_service_response' => 'success',
                        'processing_time_ms' => round($totalTime * 1000, 2),
                        'timestamp' => date('Y-m-d H:i:s'),
                    ]);

                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Email de vérification envoyé avec succès.',
                    ]);
                } else {
                    $this->logger->error('Email verification sending failed', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'sent_by' => $userIdentifier,
                        'email_service_response' => 'failure',
                        'processing_time_ms' => round($totalTime * 1000, 2),
                        'error_type' => 'service_returned_false',
                    ]);

                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Erreur lors de l\'envoi de l\'email.',
                    ], 500);
                }
            } catch (Exception $e) {
                $totalTime = microtime(true) - $startTime;

                $this->logger->error('Email verification sending failed with exception', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'sent_by' => $userIdentifier,
                    'processing_time_ms' => round($totalTime * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi : ' . $e->getMessage(),
                ], 500);
            }
        } catch (Exception $e) {
            $totalTime = microtime(true) - $startTime;

            $this->logger->critical('Critical error in email verification process', [
                'student_id' => $studentId,
                'student_email' => $studentEmail,
                'requested_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'processing_time_ms' => round($totalTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur critique est survenue. Veuillez réessayer.',
            ], 500);
        }
    }

    /**
     * Manually verify student email.
     */
    #[Route('/{id}/verify-email', name: 'admin_student_verify_email', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function verifyEmail(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $studentId = $student->getId();
        $studentEmail = $student->getEmail();

        $this->logger->info('Manual email verification initiated', [
            'student_id' => $studentId,
            'student_email' => $studentEmail,
            'current_verification_status' => $student->isEmailVerified(),
            'verified_by' => $userIdentifier,
            'route' => 'admin_student_verify_email',
            'ip' => $request->getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $token = $request->request->get('_token');
            $expectedToken = 'verify_email' . $studentId;

            $this->logger->debug('CSRF token validation for manual email verification', [
                'student_id' => $studentId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'verify_email' . $studentId,
                'verified_by' => $userIdentifier,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $token)) {
                try {
                    // Check if email is already verified
                    if ($student->isEmailVerified()) {
                        $this->logger->info('Email verification attempted for already verified student', [
                            'student_id' => $studentId,
                            'student_email' => $studentEmail,
                            'verification_status' => 'already_verified',
                            'verified_by' => $userIdentifier,
                        ]);

                        $this->addFlash('info', 'L\'email de cet étudiant est déjà vérifié.');
                    } else {
                        // Store pre-verification state
                        $preVerificationState = [
                            'email_verified_at' => $student->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
                            'verification_token' => $student->getEmailVerificationToken() ?? null,
                        ];

                        $this->logger->debug('Pre-verification state captured', [
                            'student_id' => $studentId,
                            'pre_verification_state' => $preVerificationState,
                            'verified_by' => $userIdentifier,
                        ]);

                        // Manually verify email
                        $student->verifyEmail();

                        $this->logger->debug('Email verification applied to student entity', [
                            'student_id' => $studentId,
                            'student_email' => $studentEmail,
                            'new_verification_status' => $student->isEmailVerified(),
                            'verified_by' => $userIdentifier,
                        ]);

                        try {
                            $entityManager->flush();

                            $this->logger->info('Manual email verification persisted to database', [
                                'student_id' => $studentId,
                                'student_email' => $studentEmail,
                                'verified_by' => $userIdentifier,
                                'verification_timestamp' => date('Y-m-d H:i:s'),
                                'verification_method' => 'manual_admin_verification',
                            ]);
                        } catch (Exception $e) {
                            $this->logger->error('Database persistence failed for email verification', [
                                'student_id' => $studentId,
                                'student_email' => $studentEmail,
                                'error' => $e->getMessage(),
                                'error_code' => $e->getCode(),
                                'verified_by' => $userIdentifier,
                                'trace' => $e->getTraceAsString(),
                            ]);
                            throw $e;
                        }

                        $this->addFlash('success', 'Email vérifié manuellement avec succès.');

                        $this->logger->info('Manual email verification completed successfully', [
                            'student_id' => $studentId,
                            'student_email' => $studentEmail,
                            'verified_by' => $userIdentifier,
                            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                        ]);
                    }
                } catch (Exception $e) {
                    $entityManager->rollback();

                    $this->addFlash('error', 'Erreur lors de la vérification : ' . $e->getMessage());

                    $this->logger->error('Manual email verification failed with exception', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'verified_by' => $userIdentifier,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for manual email verification', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'token_provided' => !empty($token),
                    'expected_token' => $expectedToken,
                    'verified_by' => $userIdentifier,
                    'ip' => $request->getClientIp(),
                    'security_incident' => 'csrf_token_mismatch',
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Opération annulée.');
            }

            return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
        } catch (Exception $e) {
            $this->logger->critical('Critical error in manual email verification process', [
                'student_id' => $studentId,
                'student_email' => $studentEmail,
                'verified_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la vérification.');

            return $this->redirectToRoute('admin_student_show', ['id' => $studentId]);
        }
    }

    /**
     * Generate new password for student.
     */
    #[Route('/{id}/generate-password', name: 'admin_student_generate_password', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function generatePassword(Request $request, Student $student, EntityManagerInterface $entityManager): JsonResponse
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $studentId = $student->getId();
        $studentEmail = $student->getEmail();
        $startTime = microtime(true);

        $this->logger->info('Password generation request initiated', [
            'student_id' => $studentId,
            'student_email' => $studentEmail,
            'requested_by' => $userIdentifier,
            'route' => 'admin_student_generate_password',
            'ip' => $request->getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $token = $request->request->get('_token');
            $expectedToken = 'generate_password' . $studentId;

            $this->logger->debug('CSRF token validation for password generation', [
                'student_id' => $studentId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'generate_password' . $studentId,
                'requested_by' => $userIdentifier,
            ]);

            if (!$this->isCsrfTokenValid($expectedToken, $token)) {
                $this->logger->warning('Invalid CSRF token for password generation', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'token_provided' => !empty($token),
                    'expected_token' => $expectedToken,
                    'requested_by' => $userIdentifier,
                    'ip' => $request->getClientIp(),
                    'security_incident' => 'csrf_token_mismatch',
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token CSRF invalide'
                ], 400);
            }

            // Validate student eligibility for password generation
            if (!$student->isActive()) {
                $this->logger->warning('Password generation attempted for inactive student', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'student_status' => 'inactive',
                    'requested_by' => $userIdentifier,
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Impossible de générer un mot de passe pour un étudiant inactif.',
                ], 400);
            }

            try {
                // Generate new password
                $this->logger->debug('Generating new password', [
                    'student_id' => $studentId,
                    'service_class' => get_class($this->studentService),
                    'requested_by' => $userIdentifier,
                ]);

                $newPassword = $this->studentService->generateRandomPassword();
                $passwordLength = strlen($newPassword);

                $this->logger->debug('New password generated', [
                    'student_id' => $studentId,
                    'password_length' => $passwordLength,
                    'requested_by' => $userIdentifier,
                ]);

                // Hash the password
                try {
                    $hashedPassword = $this->passwordHasher->hashPassword($student, $newPassword);
                    $student->setPassword($hashedPassword);

                    $this->logger->debug('Password hashed successfully', [
                        'student_id' => $studentId,
                        'requested_by' => $userIdentifier,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Password hashing failed', [
                        'student_id' => $studentId,
                        'error' => $e->getMessage(),
                        'requested_by' => $userIdentifier,
                    ]);
                    throw $e;
                }

                // Clear any existing password reset tokens
                try {
                    $student->clearPasswordResetToken();

                    $this->logger->debug('Password reset tokens cleared', [
                        'student_id' => $studentId,
                        'requested_by' => $userIdentifier,
                    ]);
                } catch (Exception $e) {
                    $this->logger->warning('Failed to clear password reset tokens', [
                        'student_id' => $studentId,
                        'error' => $e->getMessage(),
                        'requested_by' => $userIdentifier,
                    ]);
                    // Continue anyway
                }

                // Persist changes to database
                try {
                    $entityManager->flush();

                    $this->logger->info('New password persisted to database', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'generated_by' => $userIdentifier,
                        'persistence_timestamp' => date('Y-m-d H:i:s'),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Database persistence failed for password generation', [
                        'student_id' => $studentId,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'generated_by' => $userIdentifier,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }

                // Send the new password via email
                $emailSent = false;
                try {
                    $this->logger->debug('Attempting to send new password via email', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'generated_by' => $userIdentifier,
                    ]);

                    $emailSent = $this->studentService->sendNewPasswordEmail($student, $newPassword);

                    $this->logger->info('New password email sending attempted', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'email_sent' => $emailSent,
                        'generated_by' => $userIdentifier,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to send new password email', [
                        'student_id' => $studentId,
                        'student_email' => $studentEmail,
                        'error' => $e->getMessage(),
                        'generated_by' => $userIdentifier,
                    ]);
                    // Continue - password was generated successfully
                }

                $totalTime = microtime(true) - $startTime;

                $this->logger->info('Password generation completed successfully', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'email_sent' => $emailSent,
                    'generated_by' => $userIdentifier,
                    'processing_time_ms' => round($totalTime * 1000, 2),
                    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);

                return new JsonResponse([
                    'success' => true,
                    'message' => $emailSent ?
                        'Nouveau mot de passe généré et envoyé par email.' :
                        'Nouveau mot de passe généré (erreur d\'envoi email).',
                    'password' => $newPassword, // Only for admin to see in case email fails
                ]);
            } catch (Exception $e) {
                $entityManager->rollback();

                $totalTime = microtime(true) - $startTime;

                $this->logger->error('Password generation failed with exception', [
                    'student_id' => $studentId,
                    'student_email' => $studentEmail,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'generated_by' => $userIdentifier,
                    'processing_time_ms' => round($totalTime * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la génération : ' . $e->getMessage(),
                ], 500);
            }
        } catch (Exception $e) {
            $totalTime = microtime(true) - $startTime;

            $this->logger->critical('Critical error in password generation process', [
                'student_id' => $studentId,
                'student_email' => $studentEmail,
                'generated_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'processing_time_ms' => round($totalTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur critique est survenue. Veuillez réessayer.',
            ], 500);
        }
    }

    /**
     * Export students data to CSV.
     */
    #[Route('/export', name: 'admin_student_export', methods: ['GET'])]
    public function export(Request $request, StudentRepository $studentRepository): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $startTime = microtime(true);

        $this->logger->info('Students data export requested', [
            'exported_by' => $userIdentifier,
            'route' => 'admin_student_export',
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Extract and validate filters
            $filters = array_filter([
                'search' => $request->query->get('search'),
                'status' => $request->query->get('status'),
                'email_verified' => $request->query->get('email_verified'),
                'city' => $request->query->get('city'),
                'profession' => $request->query->get('profession'),
                'registration_period' => $request->query->get('registration_period'),
            ]);

            $this->logger->info('Export filters applied', [
                'filters_count' => count($filters),
                'active_filters' => array_keys($filters),
                'filters' => $filters,
                'exported_by' => $userIdentifier,
            ]);

            try {
                $queryStartTime = microtime(true);
                $students = $studentRepository->findWithFilters($filters);
                $queryTime = microtime(true) - $queryStartTime;

                $this->logger->info('Students data retrieved for export', [
                    'students_count' => count($students),
                    'query_time_ms' => round($queryTime * 1000, 2),
                    'filters_applied' => count($filters),
                    'exported_by' => $userIdentifier,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to retrieve students data for export', [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'filters' => $filters,
                    'exported_by' => $userIdentifier,
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            try {
                $exportStartTime = microtime(true);
                $csvData = $this->studentService->exportToCsv($students);
                $exportTime = microtime(true) - $exportStartTime;

                $this->logger->info('CSV data generated successfully', [
                    'students_count' => count($students),
                    'csv_size_bytes' => strlen($csvData),
                    'csv_size_kb' => round(strlen($csvData) / 1024, 2),
                    'export_time_ms' => round($exportTime * 1000, 2),
                    'exported_by' => $userIdentifier,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate CSV data', [
                    'students_count' => count($students),
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'exported_by' => $userIdentifier,
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            $filename = 'etudiants_' . date('Y-m-d_H-i-s') . '.csv';

            $response = new Response($csvData);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Content-Length', (string) strlen($csvData));

            $totalTime = microtime(true) - $startTime;

            $this->logger->info('Students export completed successfully', [
                'exported_by' => $userIdentifier,
                'filename' => $filename,
                'students_exported' => count($students),
                'file_size_bytes' => strlen($csvData),
                'file_size_kb' => round(strlen($csvData) / 1024, 2),
                'total_time_ms' => round($totalTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return $response;
        } catch (Exception $e) {
            $totalTime = microtime(true) - $startTime;

            $this->logger->error('Students export failed with exception', [
                'exported_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_time_ms' => round($totalTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToRoute('admin_student_index');
        }
    }

    /**
     * Bulk actions on students.
     */
    #[Route('/bulk-action', name: 'admin_student_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request, StudentRepository $studentRepository, EntityManagerInterface $entityManager): Response
    {
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $startTime = microtime(true);

        $this->logger->info('Bulk action request initiated', [
            'performed_by' => $userIdentifier,
            'route' => 'admin_student_bulk_action',
            'ip' => $request->getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $action = $request->request->get('bulk_action');
            $studentIds = $request->request->all('student_ids') ?? [];

            $this->logger->debug('Bulk action parameters extracted', [
                'action' => $action,
                'student_ids_count' => count($studentIds),
                'student_ids' => $studentIds,
                'performed_by' => $userIdentifier,
            ]);

            // Validate student selection
            if (empty($studentIds)) {
                $this->logger->warning('Bulk action attempted with no students selected', [
                    'action' => $action,
                    'performed_by' => $userIdentifier,
                ]);

                $this->addFlash('warning', 'Aucun étudiant sélectionné.');

                return $this->redirectToRoute('admin_student_index');
            }

            // Validate action parameter
            $allowedActions = ['activate', 'deactivate', 'verify_email', 'send_password_reset'];
            if (!in_array($action, $allowedActions, true)) {
                $this->logger->warning('Invalid bulk action attempted', [
                    'action' => $action,
                    'allowed_actions' => $allowedActions,
                    'performed_by' => $userIdentifier,
                ]);

                $this->addFlash('error', 'Action non autorisée.');

                return $this->redirectToRoute('admin_student_index');
            }

            // CSRF token validation
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('bulk_action', $token)) {
                $this->logger->warning('Invalid CSRF token for bulk action', [
                    'action' => $action,
                    'student_ids_count' => count($studentIds),
                    'token_provided' => !empty($token),
                    'performed_by' => $userIdentifier,
                    'ip' => $request->getClientIp(),
                    'security_incident' => 'csrf_token_mismatch',
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');

                return $this->redirectToRoute('admin_student_index');
            }

            try {
                // Retrieve students
                $queryStartTime = microtime(true);
                $students = $studentRepository->findBy(['id' => $studentIds]);
                $queryTime = microtime(true) - $queryStartTime;

                $this->logger->info('Students retrieved for bulk action', [
                    'action' => $action,
                    'requested_students' => count($studentIds),
                    'found_students' => count($students),
                    'query_time_ms' => round($queryTime * 1000, 2),
                    'performed_by' => $userIdentifier,
                ]);

                if (count($students) !== count($studentIds)) {
                    $foundIds = array_map(fn($student) => $student->getId(), $students);
                    $missingIds = array_diff($studentIds, $foundIds);

                    $this->logger->warning('Some students not found for bulk action', [
                        'action' => $action,
                        'missing_student_ids' => $missingIds,
                        'found_students' => count($students),
                        'performed_by' => $userIdentifier,
                    ]);
                }

                $count = 0;
                $errors = [];
                $actionStartTime = microtime(true);

                // Process each student
                foreach ($students as $student) {
                    try {
                        $this->logger->debug('Processing student for bulk action', [
                            'student_id' => $student->getId(),
                            'student_email' => $student->getEmail(),
                            'action' => $action,
                            'performed_by' => $userIdentifier,
                        ]);

                        switch ($action) {
                            case 'activate':
                                if (!$student->isActive()) {
                                    $student->setIsActive(true);
                                    $count++;
                                }
                                break;

                            case 'deactivate':
                                if ($student->isActive()) {
                                    $student->setIsActive(false);
                                    $count++;
                                }
                                break;

                            case 'verify_email':
                                if (!$student->isEmailVerified()) {
                                    $student->verifyEmail();
                                    $count++;
                                }
                                break;

                            case 'send_password_reset':
                                try {
                                    if ($this->studentService->sendPasswordResetEmail($student)) {
                                        $count++;
                                    } else {
                                        $errors[] = "Échec envoi email pour {$student->getEmail()}";
                                    }
                                } catch (Exception $e) {
                                    $errors[] = "Erreur email pour {$student->getEmail()}: {$e->getMessage()}";
                                    
                                    $this->logger->error('Email sending failed in bulk action', [
                                        'student_id' => $student->getId(),
                                        'student_email' => $student->getEmail(),
                                        'action' => $action,
                                        'error' => $e->getMessage(),
                                        'performed_by' => $userIdentifier,
                                    ]);
                                }
                                break;
                        }
                    } catch (Exception $e) {
                        $errors[] = "Erreur pour étudiant ID {$student->getId()}: {$e->getMessage()}";
                        
                        $this->logger->error('Error processing student in bulk action', [
                            'student_id' => $student->getId(),
                            'action' => $action,
                            'error' => $e->getMessage(),
                            'performed_by' => $userIdentifier,
                        ]);
                    }
                }

                $actionTime = microtime(true) - $actionStartTime;

                // Persist changes if any
                if ($count > 0 && $action !== 'send_password_reset') {
                    try {
                        $persistStartTime = microtime(true);
                        $entityManager->flush();
                        $persistTime = microtime(true) - $persistStartTime;

                        $this->logger->info('Bulk action changes persisted to database', [
                            'action' => $action,
                            'changes_count' => $count,
                            'persist_time_ms' => round($persistTime * 1000, 2),
                            'performed_by' => $userIdentifier,
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('Database persistence failed for bulk action', [
                            'action' => $action,
                            'changes_attempted' => $count,
                            'error' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'performed_by' => $userIdentifier,
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }
                }

                // Report results
                $actionLabels = [
                    'activate' => 'activé(s)',
                    'deactivate' => 'désactivé(s)',
                    'verify_email' => 'email(s) vérifié(s)',
                    'send_password_reset' => 'email(s) de réinitialisation envoyé(s)',
                ];

                if ($count > 0) {
                    $this->addFlash('success', "{$count} étudiant(s) {$actionLabels[$action]}.");
                }

                if (!empty($errors)) {
                    $this->addFlash('warning', 'Certaines opérations ont échoué: ' . implode(', ', array_slice($errors, 0, 3)));
                }

                $totalTime = microtime(true) - $startTime;

                $this->logger->info('Bulk action completed successfully', [
                    'action' => $action,
                    'students_processed' => count($students),
                    'successful_operations' => $count,
                    'errors_count' => count($errors),
                    'action_time_ms' => round($actionTime * 1000, 2),
                    'total_time_ms' => round($totalTime * 1000, 2),
                    'performed_by' => $userIdentifier,
                    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);
            } catch (Exception $e) {
                $entityManager->rollback();

                $this->addFlash('error', 'Erreur lors de l\'action groupée : ' . $e->getMessage());

                $this->logger->error('Bulk action failed with exception', [
                    'action' => $action,
                    'student_ids_count' => count($studentIds),
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'performed_by' => $userIdentifier,
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return $this->redirectToRoute('admin_student_index');
        } catch (Exception $e) {
            $totalTime = microtime(true) - $startTime;

            $this->logger->critical('Critical error in bulk action process', [
                'performed_by' => $userIdentifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_time_ms' => round($totalTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de l\'action groupée.');

            return $this->redirectToRoute('admin_student_index');
        }
    }

    /**
     * Get client IP address.
     */
    private function getClientIp(): ?string
    {
        // Try different headers in order of preference
        $ipSources = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipSources as $source) {
            if (!empty($_SERVER[$source])) {
                $ip = $_SERVER[$source];
                
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                } elseif (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip; // Return private/reserved IPs for development
                }
            }
        }

        return null;
    }
}
