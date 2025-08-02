<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Entity\Core\StudentProgress;
use App\Entity\User\Student;
use App\Service\Security\ContentAccessService;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * Student Dashboard Controller.
 *
 * Main dashboard for authenticated students to access their learning resources,
 * training progress, and account information.
 */
#[Route('/student')]
#[IsGranted('ROLE_STUDENT')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ContentAccessService $contentAccessService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Student dashboard homepage.
     *
     * Displays an overview of the student's training progress, upcoming sessions,
     * recent activities, and quick access to learning resources.
     */
    #[Route('/', name: 'student_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student dashboard access', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'timestamp' => new DateTime(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            // Get student's accessible formations and enrollments
            $this->logger->debug('Fetching accessible formations for student', [
                'student_id' => $student->getId(),
            ]);

            $accessibleFormations = $this->contentAccessService->getAccessibleFormations($student);

            $this->logger->debug('Accessible formations retrieved', [
                'student_id' => $student->getId(),
                'formations_count' => count($accessibleFormations),
                'formation_ids' => array_map(static fn ($f) => $f->getId(), $accessibleFormations),
            ]);

            $this->logger->debug('Fetching student enrollments', [
                'student_id' => $student->getId(),
            ]);

            $enrollments = $this->contentAccessService->getStudentEnrollments($student);

            $this->logger->debug('Student enrollments retrieved', [
                'student_id' => $student->getId(),
                'enrollments_count' => count($enrollments),
                'enrollment_ids' => array_map(static fn ($e) => $e->getId(), $enrollments),
            ]);

            // Calculate basic statistics
            $completedEnrollments = array_filter($enrollments, static fn ($e) => $e->isCompleted());
            $activeEnrollments = array_filter($enrollments, static fn ($e) => $e->isActive());

            $stats = [
                'enrolled_courses' => count($enrollments),
                'completed_courses' => count($completedEnrollments),
                'active_enrollments' => count($activeEnrollments),
                'accessible_formations' => count($accessibleFormations),
            ];

            $this->logger->info('Student dashboard statistics calculated', [
                'student_id' => $student->getId(),
                'stats' => $stats,
            ]);

            // Prepare recent activities based on enrollments
            $recentActivities = [];
            $recentEnrollments = array_slice($enrollments, 0, 5);

            foreach ($recentEnrollments as $enrollment) {
                $activity = (object) [
                    'title' => 'Inscription à ' . $enrollment->getFormation()->getTitle(),
                    'description' => 'Formation ' . $enrollment->getFormation()->getTitle(),
                    'createdAt' => $enrollment->getCreatedAt(),
                ];
                $recentActivities[] = $activity;
            }

            $this->logger->debug('Recent activities prepared', [
                'student_id' => $student->getId(),
                'activities_count' => count($recentActivities),
            ]);

            $availableCourses = array_slice($accessibleFormations, 0, 6);

            $dashboardData = [
                'student' => $student,
                'stats' => $stats,
                'available_courses' => $availableCourses,
                'recent_activities' => $recentActivities,
                'upcoming_sessions' => [], // TODO: Implement when session system is ready
                'page_title' => 'Tableau de bord',
            ];

            $this->logger->info('Student dashboard data prepared successfully', [
                'student_id' => $student->getId(),
                'available_courses_count' => count($availableCourses),
                'recent_activities_count' => count($recentActivities),
            ]);

            return $this->render('student/dashboard/index.html.twig', $dashboardData);
        } catch (Throwable $e) {
            $student = $this->getUser();
            $studentId = $student instanceof Student ? $student->getId() : null;

            $this->logger->error('Error in student dashboard index', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du tableau de bord.');

            // Return minimal dashboard view with error state
            return $this->render('student/dashboard/index.html.twig', [
                'student' => $this->getUser(),
                'stats' => [
                    'enrolled_courses' => 0,
                    'completed_courses' => 0,
                    'active_enrollments' => 0,
                    'accessible_formations' => 0,
                ],
                'available_courses' => [],
                'recent_activities' => [],
                'upcoming_sessions' => [],
                'page_title' => 'Tableau de bord',
                'error_state' => true,
            ]);
        }
    }

    /**
     * Student profile page.
     *
     * Displays and allows editing of student personal information and preferences.
     */
    #[Route('/profile', name: 'student_profile', methods: ['GET'])]
    public function profile(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student profile page accessed', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'timestamp' => new DateTime(),
            ]);

            $profileData = [
                'student' => $student,
                'page_title' => 'Mon Profil',
            ];

            $this->logger->debug('Student profile data prepared', [
                'student_id' => $student->getId(),
                'profile_data_keys' => array_keys($profileData),
            ]);

            return $this->render('student/dashboard/profile.html.twig', $profileData);
        } catch (Throwable $e) {
            $student = $this->getUser();
            $studentId = $student instanceof Student ? $student->getId() : null;

            $this->logger->error('Error in student profile page', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du profil.');

            return $this->render('student/dashboard/profile.html.twig', [
                'student' => $this->getUser(),
                'page_title' => 'Mon Profil',
                'error_state' => true,
            ]);
        }
    }

    /**
     * Student courses page - Redirect to formations.
     *
     * Redirects to the formations index since that's the main content access point.
     */
    #[Route('/courses', name: 'student_courses', methods: ['GET'])]
    public function courses(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student courses page accessed - redirecting to formations', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'redirect_to' => 'student_formation_index',
                'timestamp' => new DateTime(),
            ]);

            return $this->redirectToRoute('student_formation_index');
        } catch (Throwable $e) {
            $student = $this->getUser();
            $studentId = $student instanceof Student ? $student->getId() : null;

            $this->logger->error('Error in student courses redirect', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la redirection.');

            // Fallback to dashboard
            return $this->redirectToRoute('student_dashboard');
        }
    }

    /**
     * Student progress page.
     *
     * Displays detailed training progress, learning analytics, and achievements.
     */
    #[Route('/progress', name: 'student_progress', methods: ['GET'])]
    public function progress(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student progress page accessed', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'timestamp' => new DateTime(),
            ]);

            // Get student's enrollments and progress data
            $this->logger->debug('Fetching student enrollments for progress calculation', [
                'student_id' => $student->getId(),
            ]);

            $enrollments = $this->contentAccessService->getStudentEnrollments($student);

            $this->logger->debug('Student enrollments retrieved for progress', [
                'student_id' => $student->getId(),
                'enrollments_count' => count($enrollments),
            ]);

            // Calculate overall progress
            $totalProgress = 0;
            $enrollmentCount = count($enrollments);
            $courseProgress = [];

            $this->logger->debug('Starting progress calculation', [
                'student_id' => $student->getId(),
                'enrollment_count' => $enrollmentCount,
            ]);

            foreach ($enrollments as $index => $enrollment) {
                $this->logger->debug('Processing enrollment progress', [
                    'student_id' => $student->getId(),
                    'enrollment_id' => $enrollment->getId(),
                    'formation_title' => $enrollment->getFormation()->getTitle(),
                    'enrollment_index' => $index,
                ]);

                try {
                    $progress = $enrollment->getProgress();
                    if ($progress) {
                        $completionPercentage = $progress->getCompletionPercentage();
                        $totalProgress += $completionPercentage;

                        $moduleProgress = $progress->getModuleProgress();
                        $completedModules = array_filter($moduleProgress, static fn ($m) => $m['completed'] ?? false);

                        // Build course progress data
                        $courseProgressItem = [
                            'title' => $enrollment->getFormation()->getTitle(),
                            'description' => $enrollment->getFormation()->getDescription() ?: 'Formation en cours',
                            'progress' => $completionPercentage,
                            'completed_modules' => count($completedModules),
                            'total_modules' => count($moduleProgress),
                            'time_spent' => $this->formatTimeSpent($progress->getTotalTimeSpent()),
                            'last_activity' => $progress->getLastActivity(),
                            'modules' => $this->buildModuleProgressData($progress),
                        ];

                        $courseProgress[] = $courseProgressItem;

                        $this->logger->debug('Progress data calculated for enrollment', [
                            'student_id' => $student->getId(),
                            'enrollment_id' => $enrollment->getId(),
                            'completion_percentage' => $completionPercentage,
                            'completed_modules' => count($completedModules),
                            'total_modules' => count($moduleProgress),
                            'time_spent_minutes' => $progress->getTotalTimeSpent(),
                        ]);
                    } else {
                        // No progress data yet
                        $totalModules = $enrollment->getFormation()->getModules()->count();
                        $courseProgressItem = [
                            'title' => $enrollment->getFormation()->getTitle(),
                            'description' => $enrollment->getFormation()->getDescription() ?: 'Formation en cours',
                            'progress' => 0,
                            'completed_modules' => 0,
                            'total_modules' => $totalModules,
                            'time_spent' => '0h 0min',
                            'last_activity' => null,
                            'modules' => [],
                        ];

                        $courseProgress[] = $courseProgressItem;

                        $this->logger->debug('No progress data found for enrollment', [
                            'student_id' => $student->getId(),
                            'enrollment_id' => $enrollment->getId(),
                            'total_modules' => $totalModules,
                        ]);
                    }
                } catch (Throwable $progressError) {
                    $this->logger->warning('Error processing individual enrollment progress', [
                        'student_id' => $student->getId(),
                        'enrollment_id' => $enrollment->getId(),
                        'error_message' => $progressError->getMessage(),
                        'error_line' => $progressError->getLine(),
                    ]);

                    // Add fallback progress data
                    $courseProgress[] = [
                        'title' => $enrollment->getFormation()->getTitle(),
                        'description' => 'Erreur lors du calcul de progression',
                        'progress' => 0,
                        'completed_modules' => 0,
                        'total_modules' => 0,
                        'time_spent' => '0h 0min',
                        'last_activity' => null,
                        'modules' => [],
                    ];
                }
            }

            $overallProgress = $enrollmentCount > 0 ? ($totalProgress / $enrollmentCount) : 0;

            $this->logger->info('Overall progress calculated', [
                'student_id' => $student->getId(),
                'overall_progress' => $overallProgress,
                'total_progress_sum' => $totalProgress,
                'enrollment_count' => $enrollmentCount,
            ]);

            // Build study time statistics with proper structure
            $this->logger->debug('Building study time statistics', [
                'student_id' => $student->getId(),
            ]);

            $studyTimeStats = $this->buildStudyTimeStats($enrollments);

            $this->logger->debug('Study time statistics calculated', [
                'student_id' => $student->getId(),
                'total_hours' => $studyTimeStats['total_hours'],
                'completed_sessions' => $studyTimeStats['completed_sessions'],
                'global_score' => $studyTimeStats['global_score'],
            ]);

            // Build recent achievements
            $this->logger->debug('Building recent achievements', [
                'student_id' => $student->getId(),
            ]);

            $recentAchievements = $this->buildRecentAchievements($enrollments);

            $this->logger->debug('Recent achievements calculated', [
                'student_id' => $student->getId(),
                'achievements_count' => count($recentAchievements),
            ]);

            $progressData = [
                'student' => $student,
                'overall_progress' => round($overallProgress, 1),
                'course_progress' => $courseProgress,
                'recent_achievements' => $recentAchievements,
                'study_time_stats' => $studyTimeStats,
                'page_title' => 'Ma Progression',
            ];

            $this->logger->info('Student progress page data prepared successfully', [
                'student_id' => $student->getId(),
                'course_progress_count' => count($courseProgress),
                'overall_progress' => round($overallProgress, 1),
                'achievements_count' => count($recentAchievements),
            ]);

            return $this->render('student/dashboard/progress.html.twig', $progressData);
        } catch (Throwable $e) {
            $student = $this->getUser();
            $studentId = $student instanceof Student ? $student->getId() : null;

            $this->logger->error('Critical error in student progress page', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de votre progression.');

            // Return minimal progress view with error state
            return $this->render('student/dashboard/progress.html.twig', [
                'student' => $this->getUser(),
                'overall_progress' => 0,
                'course_progress' => [],
                'recent_achievements' => [],
                'study_time_stats' => [
                    'total_hours' => 0,
                    'total_minutes' => 0,
                    'completed_sessions' => 0,
                    'streak_days' => 0,
                    'average_per_day' => 0,
                    'engagement_level' => 0,
                    'consistency' => 0,
                    'retention_rate' => 0,
                    'global_score' => 0,
                    'weekly_data' => [
                        'labels' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                        'values' => [0, 0, 0, 0, 0, 0, 0],
                    ],
                ],
                'page_title' => 'Ma Progression',
                'error_state' => true,
            ]);
        }
    }

    /**
     * Student certificates page.
     *
     * Displays earned certificates and available certifications.
     */
    #[Route('/certificates', name: 'student_certificates', methods: ['GET'])]
    public function certificates(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student certificates page accessed', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'timestamp' => new DateTime(),
            ]);

            // TODO: Implement when certificate system is ready
            $certificatesData = [
                'student' => $student,
                'earned_certificates' => [], // TODO: Get earned certificates
                'available_certifications' => [], // TODO: Get available certifications
                'page_title' => 'Mes Certificats',
            ];

            $this->logger->debug('Certificates page data prepared', [
                'student_id' => $student->getId(),
                'earned_certificates_count' => count($certificatesData['earned_certificates']),
                'available_certifications_count' => count($certificatesData['available_certifications']),
            ]);

            return $this->render('student/dashboard/certificates.html.twig', $certificatesData);
        } catch (Throwable $e) {
            $student = $this->getUser();
            $studentId = $student instanceof Student ? $student->getId() : null;

            $this->logger->error('Error in student certificates page', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des certificats.');

            return $this->render('student/dashboard/certificates.html.twig', [
                'student' => $this->getUser(),
                'earned_certificates' => [],
                'available_certifications' => [],
                'page_title' => 'Mes Certificats',
                'error_state' => true,
            ]);
        }
    }

    /**
     * Student notifications page.
     *
     * Displays system notifications, course updates, and messages.
     */
    #[Route('/notifications', name: 'student_notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student notifications page accessed', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'timestamp' => new DateTime(),
            ]);

            // TODO: Implement when notification system is ready
            $notificationsData = [
                'student' => $student,
                'unread_notifications' => [], // TODO: Get unread notifications
                'all_notifications' => [], // TODO: Get all notifications
                'page_title' => 'Notifications',
            ];

            $this->logger->debug('Notifications page data prepared', [
                'student_id' => $student->getId(),
                'unread_notifications_count' => count($notificationsData['unread_notifications']),
                'all_notifications_count' => count($notificationsData['all_notifications']),
            ]);

            return $this->render('student/dashboard/notifications.html.twig', $notificationsData);
        } catch (Throwable $e) {
            $student = $this->getUser();
            $studentId = $student instanceof Student ? $student->getId() : null;

            $this->logger->error('Error in student notifications page', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des notifications.');

            return $this->render('student/dashboard/notifications.html.twig', [
                'student' => $this->getUser(),
                'unread_notifications' => [],
                'all_notifications' => [],
                'page_title' => 'Notifications',
                'error_state' => true,
            ]);
        }
    }

    /**
     * Student settings page.
     *
     * Allows students to modify account settings, preferences, and privacy options.
     */
    #[Route('/settings', name: 'student_settings', methods: ['GET'])]
    public function settings(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student settings page accessed', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'timestamp' => new DateTime(),
            ]);

            $settingsData = [
                'student' => $student,
                'page_title' => 'Paramètres',
            ];

            $this->logger->debug('Settings page data prepared', [
                'student_id' => $student->getId(),
            ]);

            return $this->render('student/dashboard/settings.html.twig', $settingsData);
        } catch (Throwable $e) {
            $student = $this->getUser();
            $studentId = $student instanceof Student ? $student->getId() : null;

            $this->logger->error('Error in student settings page', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des paramètres.');

            return $this->render('student/dashboard/settings.html.twig', [
                'student' => $this->getUser(),
                'page_title' => 'Paramètres',
                'error_state' => true,
            ]);
        }
    }

    /**
     * Update progress tracking for content consumption.
     *
     * API endpoint for Stimulus controllers to track learning progress.
     */
    #[Route('/progress/update', name: 'student_progress_update', methods: ['POST'])]
    public function updateProgress(Request $request): JsonResponse
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Progress update request received', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'request_method' => $request->getMethod(),
                'content_type' => $request->headers->get('Content-Type'),
                'timestamp' => new DateTime(),
            ]);

            $rawContent = $request->getContent();
            $this->logger->debug('Raw request content received', [
                'student_id' => $student->getId(),
                'content_length' => strlen($rawContent),
                'raw_content' => $rawContent,
            ]);

            $data = json_decode($rawContent, true);

            if (!$data) {
                $this->logger->warning('Invalid JSON data in progress update request', [
                    'student_id' => $student->getId(),
                    'raw_content' => $rawContent,
                    'json_last_error' => json_last_error_msg(),
                ]);

                return new JsonResponse([
                    'error' => 'Invalid data',
                    'details' => 'JSON decode failed: ' . json_last_error_msg(),
                ], 400);
            }

            $contentId = $data['contentId'] ?? null;
            $contentType = $data['contentType'] ?? null;
            $action = $data['action'] ?? 'view';

            $this->logger->debug('Progress update data parsed', [
                'student_id' => $student->getId(),
                'content_id' => $contentId,
                'content_type' => $contentType,
                'action' => $action,
                'full_data' => $data,
            ]);

            if (!$contentId || !$contentType) {
                $this->logger->warning('Missing required fields in progress update', [
                    'student_id' => $student->getId(),
                    'content_id' => $contentId,
                    'content_type' => $contentType,
                    'received_data' => $data,
                ]);

                return new JsonResponse([
                    'error' => 'Missing required fields',
                    'required' => ['contentId', 'contentType'],
                    'received' => array_keys($data),
                ], 400);
            }

            $this->logger->info('Processing progress update', [
                'student_id' => $student->getId(),
                'content_id' => $contentId,
                'content_type' => $contentType,
                'action' => $action,
            ]);

            // TODO: Implement actual progress tracking when StudentProgress system is enhanced
            // For now, just log the progress update
            try {
                $contentObject = (object) [
                    'getId' => static fn () => $contentId,
                    'getTitle' => static fn () => "Content {$contentId}",
                ];

                $this->contentAccessService->logContentAccess($student, $contentObject, true);

                $this->logger->info('Content access logged successfully', [
                    'student_id' => $student->getId(),
                    'content_id' => $contentId,
                    'content_type' => $contentType,
                    'action' => $action,
                ]);
            } catch (Throwable $serviceError) {
                $this->logger->error('Error logging content access in ContentAccessService', [
                    'student_id' => $student->getId(),
                    'content_id' => $contentId,
                    'content_type' => $contentType,
                    'service_error_message' => $serviceError->getMessage(),
                    'service_error_line' => $serviceError->getLine(),
                ]);

                // Continue with success response even if logging fails
            }

            $response = [
                'success' => true,
                'message' => 'Progress updated',
                'contentId' => $contentId,
                'contentType' => $contentType,
                'action' => $action,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ];

            $this->logger->info('Progress update completed successfully', [
                'student_id' => $student->getId(),
                'response' => $response,
            ]);

            return new JsonResponse($response);
        } catch (Throwable $e) {
            $student = $this->getUser();
            $studentId = $student instanceof Student ? $student->getId() : null;

            $this->logger->error('Critical error in progress update endpoint', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_content' => $request->getContent(),
            ]);

            return new JsonResponse([
                'error' => 'Internal server error',
                'message' => 'Une erreur interne est survenue lors de la mise à jour de progression',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ], 500);
        }
    }

    /**
     * Format time spent in hours and minutes.
     */
    private function formatTimeSpent(int $minutes): string
    {
        $hours = (int) ($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh %dmin', $hours, $remainingMinutes);
    }

    /**
     * Build module progress data for a given student progress.
     */
    private function buildModuleProgressData(StudentProgress $progress): array
    {
        try {
            $this->logger->debug('Building module progress data', [
                'progress_id' => $progress->getId(),
                'student_id' => $progress->getStudent()->getId(),
            ]);

            $moduleProgress = $progress->getModuleProgress();
            $modules = [];

            foreach ($moduleProgress as $moduleId => $moduleData) {
                $moduleItem = [
                    'title' => "Module {$moduleId}",
                    'progress' => $moduleData['percentage'] ?? 0,
                    'completed' => $moduleData['completed'] ?? false,
                ];
                $modules[] = $moduleItem;

                $this->logger->debug('Module progress item processed', [
                    'progress_id' => $progress->getId(),
                    'module_id' => $moduleId,
                    'percentage' => $moduleData['percentage'] ?? 0,
                    'completed' => $moduleData['completed'] ?? false,
                ]);
            }

            $this->logger->debug('Module progress data built successfully', [
                'progress_id' => $progress->getId(),
                'modules_count' => count($modules),
            ]);

            return $modules;
        } catch (Throwable $e) {
            $this->logger->error('Error building module progress data', [
                'progress_id' => $progress->getId(),
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
            ]);

            // Return empty array as fallback
            return [];
        }
    }

    /**
     * Build study time statistics structure.
     */
    private function buildStudyTimeStats(array $enrollments): array
    {
        try {
            $this->logger->debug('Building study time statistics', [
                'enrollments_count' => count($enrollments),
            ]);

            $totalMinutes = 0;
            $completedSessions = 0;
            $loginCount = 0;
            $engagementScores = [];

            foreach ($enrollments as $index => $enrollment) {
                $this->logger->debug('Processing enrollment for study time stats', [
                    'enrollment_id' => $enrollment->getId(),
                    'enrollment_index' => $index,
                ]);

                try {
                    $progress = $enrollment->getProgress();
                    if ($progress) {
                        $timeSpent = $progress->getTotalTimeSpent();
                        $courseProgress = $progress->getCourseProgress();
                        $loginCountForProgress = $progress->getLoginCount();
                        $engagementScore = $progress->getEngagementScore();

                        $totalMinutes += $timeSpent;
                        $completedSessions += count(array_filter($courseProgress, static fn ($c) => $c['completed'] ?? false));
                        $loginCount += $loginCountForProgress;
                        $engagementScores[] = $engagementScore;

                        $this->logger->debug('Progress data processed for study time stats', [
                            'enrollment_id' => $enrollment->getId(),
                            'time_spent' => $timeSpent,
                            'completed_sessions_for_enrollment' => count(array_filter($courseProgress, static fn ($c) => $c['completed'] ?? false)),
                            'login_count' => $loginCountForProgress,
                            'engagement_score' => $engagementScore,
                        ]);
                    } else {
                        $this->logger->debug('No progress data for enrollment in study time stats', [
                            'enrollment_id' => $enrollment->getId(),
                        ]);
                    }
                } catch (Throwable $progressError) {
                    $this->logger->warning('Error processing enrollment progress for study time stats', [
                        'enrollment_id' => $enrollment->getId(),
                        'error_message' => $progressError->getMessage(),
                    ]);
                }
            }

            $hours = (int) ($totalMinutes / 60);
            $minutes = $totalMinutes % 60;
            $averageEngagement = !empty($engagementScores) ? array_sum($engagementScores) / count($engagementScores) : 75;
            $consistency = min(100, $loginCount * 5); // Rough calculation
            $retentionRate = count($enrollments) > 0 ? (count(array_filter($enrollments, static fn ($e) => $e->getStatus() !== 'cancelled')) / count($enrollments)) * 100 : 85;
            $globalScore = ($averageEngagement + $consistency + $retentionRate) / 3;
            $averagePerDay = $loginCount > 0 ? round($totalMinutes / max(1, $loginCount)) : 0;
            $streakDays = 0;

            // Calculate streak days from most recent progress
            if (!empty($enrollments)) {
                $latestProgress = null;
                foreach ($enrollments as $enrollment) {
                    try {
                        $progress = $enrollment->getProgress();
                        if ($progress && (!$latestProgress || $progress->getLastActivity() > $latestProgress->getLastActivity())) {
                            $latestProgress = $progress;
                        }
                    } catch (Throwable $streakError) {
                        $this->logger->debug('Error getting progress for streak calculation', [
                            'enrollment_id' => $enrollment->getId(),
                            'error_message' => $streakError->getMessage(),
                        ]);
                    }
                }
                if ($latestProgress) {
                    $streakDays = $latestProgress->getStreakDays();
                }
            }

            $stats = [
                'total_hours' => $hours,
                'total_minutes' => $minutes,
                'completed_sessions' => $completedSessions,
                'streak_days' => $streakDays,
                'average_per_day' => $averagePerDay,
                'engagement_level' => round($averageEngagement),
                'consistency' => round($consistency),
                'retention_rate' => round($retentionRate),
                'global_score' => round($globalScore),
                'weekly_data' => [
                    'labels' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                    'values' => [30, 45, 60, 20, 50, 35, 40], // Mock data for now
                ],
            ];

            $this->logger->info('Study time statistics calculated successfully', [
                'total_minutes' => $totalMinutes,
                'total_hours' => $hours,
                'completed_sessions' => $completedSessions,
                'login_count' => $loginCount,
                'engagement_scores_count' => count($engagementScores),
                'average_engagement' => round($averageEngagement),
                'global_score' => round($globalScore),
            ]);

            return $stats;
        } catch (Throwable $e) {
            $this->logger->error('Error building study time statistics', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_line' => $e->getLine(),
                'enrollments_count' => count($enrollments),
            ]);

            // Return fallback statistics
            return [
                'total_hours' => 0,
                'total_minutes' => 0,
                'completed_sessions' => 0,
                'streak_days' => 0,
                'average_per_day' => 0,
                'engagement_level' => 0,
                'consistency' => 0,
                'retention_rate' => 0,
                'global_score' => 0,
                'weekly_data' => [
                    'labels' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                    'values' => [0, 0, 0, 0, 0, 0, 0],
                ],
            ];
        }
    }

    /**
     * Build recent achievements data.
     */
    private function buildRecentAchievements(array $enrollments): array
    {
        try {
            $this->logger->debug('Building recent achievements', [
                'enrollments_count' => count($enrollments),
            ]);

            $achievements = [];

            foreach ($enrollments as $index => $enrollment) {
                $this->logger->debug('Processing enrollment for achievements', [
                    'enrollment_id' => $enrollment->getId(),
                    'enrollment_index' => $index,
                ]);

                try {
                    $progress = $enrollment->getProgress();
                    if ($progress) {
                        $milestones = $progress->getMilestones();

                        $this->logger->debug('Processing milestones for achievements', [
                            'enrollment_id' => $enrollment->getId(),
                            'milestones_count' => count($milestones),
                        ]);

                        // Convert milestones to achievements format
                        foreach ($milestones as $key => $milestone) {
                            try {
                                if (is_array($milestone)) {
                                    $achievement = [
                                        'title' => $milestone['title'] ?? 'Accomplissement',
                                        'description' => $milestone['description'] ?? 'Nouveau succès débloqué!',
                                        'type' => $this->getMilestoneType($key),
                                        'date' => new DateTime($milestone['achieved_at'] ?? 'now'),
                                    ];
                                    $achievements[] = $achievement;

                                    $this->logger->debug('Achievement processed', [
                                        'enrollment_id' => $enrollment->getId(),
                                        'milestone_key' => $key,
                                        'achievement_title' => $achievement['title'],
                                        'achievement_type' => $achievement['type'],
                                    ]);
                                }
                            } catch (Throwable $milestoneError) {
                                $this->logger->warning('Error processing individual milestone', [
                                    'enrollment_id' => $enrollment->getId(),
                                    'milestone_key' => $key,
                                    'error_message' => $milestoneError->getMessage(),
                                ]);
                            }
                        }
                    } else {
                        $this->logger->debug('No progress data for enrollment in achievements', [
                            'enrollment_id' => $enrollment->getId(),
                        ]);
                    }
                } catch (Throwable $progressError) {
                    $this->logger->warning('Error processing enrollment progress for achievements', [
                        'enrollment_id' => $enrollment->getId(),
                        'error_message' => $progressError->getMessage(),
                    ]);
                }
            }

            // Sort by date (most recent first) and limit to 5
            usort($achievements, static fn ($a, $b) => $b['date'] <=> $a['date']);
            $recentAchievements = array_slice($achievements, 0, 5);

            $this->logger->info('Recent achievements built successfully', [
                'total_achievements' => count($achievements),
                'recent_achievements_count' => count($recentAchievements),
            ]);

            return $recentAchievements;
        } catch (Throwable $e) {
            $this->logger->error('Error building recent achievements', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_line' => $e->getLine(),
                'enrollments_count' => count($enrollments),
            ]);

            // Return empty array as fallback
            return [];
        }
    }

    /**
     * Get milestone type for styling.
     */
    private function getMilestoneType(string $milestoneKey): string
    {
        return match ($milestoneKey) {
            'first_course_completed', 'module_completed', 'formation_completed' => 'completion',
            'formation_halfway', 'formation_three_quarters' => 'milestone',
            default => 'achievement'
        };
    }
}
