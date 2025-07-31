<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Entity\User\Student;
use App\Service\Security\ContentAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        private readonly ContentAccessService $contentAccessService
    ) {
    }

    /**
     * Student dashboard homepage.
     *
     * Displays an overview of the student's training progress, upcoming sessions,
     * recent activities, and quick access to learning resources.
     */
    #[Route('/', name: 'student_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get student's accessible formations and enrollments
        $accessibleFormations = $this->contentAccessService->getAccessibleFormations($student);
        $enrollments = $this->contentAccessService->getStudentEnrollments($student);

        // Calculate basic statistics
        $stats = [
            'enrolled_courses' => count($enrollments),
            'completed_courses' => count(array_filter($enrollments, fn($e) => $e->isCompleted())),
            'active_enrollments' => count(array_filter($enrollments, fn($e) => $e->isActive())),
            'accessible_formations' => count($accessibleFormations),
        ];

        // Prepare recent activities based on enrollments
        $recentActivities = [];
        foreach (array_slice($enrollments, 0, 5) as $enrollment) {
            $recentActivities[] = (object) [
                'title' => 'Inscription à ' . $enrollment->getFormation()->getTitle(),
                'description' => 'Formation ' . $enrollment->getFormation()->getTitle(),
                'createdAt' => $enrollment->getCreatedAt(),
            ];
        }

        $dashboardData = [
            'student' => $student,
            'stats' => $stats,
            'available_courses' => array_slice($accessibleFormations, 0, 6), // Show first 6 for grid layout
            'recent_activities' => $recentActivities,
            'upcoming_sessions' => [], // TODO: Implement when session system is ready
            'page_title' => 'Tableau de bord',
        ];

        return $this->render('student/dashboard/index.html.twig', $dashboardData);
    }

    /**
     * Student profile page.
     *
     * Displays and allows editing of student personal information and preferences.
     */
    #[Route('/profile', name: 'student_profile', methods: ['GET'])]
    public function profile(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        return $this->render('student/dashboard/profile.html.twig', [
            'student' => $student,
            'page_title' => 'Mon Profil',
        ]);
    }

    /**
     * Student courses page - Redirect to formations.
     *
     * Redirects to the formations index since that's the main content access point.
     */
    #[Route('/courses', name: 'student_courses', methods: ['GET'])]
    public function courses(): Response
    {
        return $this->redirectToRoute('student_formation_index');
    }

    /**
     * Student progress page.
     *
     * Displays detailed training progress, learning analytics, and achievements.
     */
    #[Route('/progress', name: 'student_progress', methods: ['GET'])]
    public function progress(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get student's enrollments and progress data
        $enrollments = $this->contentAccessService->getStudentEnrollments($student);
        
        // Calculate overall progress
        $totalProgress = 0;
        $enrollmentCount = count($enrollments);
        $courseProgress = [];
        
        foreach ($enrollments as $enrollment) {
            $progress = $enrollment->getProgress();
            if ($progress) {
                $totalProgress += $progress->getCompletionPercentage();
                
                // Build course progress data
                $courseProgress[] = [
                    'title' => $enrollment->getFormation()->getTitle(),
                    'description' => $enrollment->getFormation()->getDescription() ?: 'Formation en cours',
                    'progress' => $progress->getCompletionPercentage(),
                    'completed_modules' => count(array_filter($progress->getModuleProgress(), fn($m) => $m['completed'] ?? false)),
                    'total_modules' => count($progress->getModuleProgress()),
                    'time_spent' => $this->formatTimeSpent($progress->getTotalTimeSpent()),
                    'last_activity' => $progress->getLastActivity(),
                    'modules' => $this->buildModuleProgressData($progress)
                ];
            } else {
                // No progress data yet
                $courseProgress[] = [
                    'title' => $enrollment->getFormation()->getTitle(),
                    'description' => $enrollment->getFormation()->getDescription() ?: 'Formation en cours',
                    'progress' => 0,
                    'completed_modules' => 0,
                    'total_modules' => $enrollment->getFormation()->getModules()->count(),
                    'time_spent' => '0h 0min',
                    'last_activity' => null,
                    'modules' => []
                ];
            }
        }
        
        $overallProgress = $enrollmentCount > 0 ? ($totalProgress / $enrollmentCount) : 0;
        
        // Build study time statistics with proper structure
        $studyTimeStats = $this->buildStudyTimeStats($enrollments);
        
        // Build recent achievements
        $recentAchievements = $this->buildRecentAchievements($enrollments);

        $progressData = [
            'student' => $student,
            'overall_progress' => round($overallProgress, 1),
            'course_progress' => $courseProgress,
            'recent_achievements' => $recentAchievements,
            'study_time_stats' => $studyTimeStats,
            'page_title' => 'Ma Progression',
        ];

        return $this->render('student/dashboard/progress.html.twig', $progressData);
    }

    /**
     * Format time spent in hours and minutes.
     */
    private function formatTimeSpent(int $minutes): string
    {
        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return sprintf('%dh %dmin', $hours, $remainingMinutes);
    }

    /**
     * Build module progress data for a given student progress.
     */
    private function buildModuleProgressData(\App\Entity\Core\StudentProgress $progress): array
    {
        $moduleProgress = $progress->getModuleProgress();
        $modules = [];
        
        foreach ($moduleProgress as $moduleId => $moduleData) {
            $modules[] = [
                'title' => "Module {$moduleId}",
                'progress' => $moduleData['percentage'] ?? 0,
                'completed' => $moduleData['completed'] ?? false
            ];
        }
        
        return $modules;
    }

    /**
     * Build study time statistics structure.
     */
    private function buildStudyTimeStats(array $enrollments): array
    {
        $totalMinutes = 0;
        $completedSessions = 0;
        $loginCount = 0;
        $engagementScores = [];
        
        foreach ($enrollments as $enrollment) {
            $progress = $enrollment->getProgress();
            if ($progress) {
                $totalMinutes += $progress->getTotalTimeSpent();
                $completedSessions += count(array_filter($progress->getCourseProgress(), fn($c) => $c['completed'] ?? false));
                $loginCount += $progress->getLoginCount();
                $engagementScores[] = $progress->getEngagementScore();
            }
        }
        
        $hours = intval($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        $averageEngagement = !empty($engagementScores) ? array_sum($engagementScores) / count($engagementScores) : 75;
        $consistency = min(100, $loginCount * 5); // Rough calculation
        $retentionRate = count($enrollments) > 0 ? (count(array_filter($enrollments, fn($e) => $e->getStatus() !== 'cancelled')) / count($enrollments)) * 100 : 85;
        $globalScore = ($averageEngagement + $consistency + $retentionRate) / 3;
        $averagePerDay = $loginCount > 0 ? round($totalMinutes / max(1, $loginCount)) : 0;
        $streakDays = 0;
        
        // Calculate streak days from most recent progress
        if (!empty($enrollments)) {
            $latestProgress = null;
            foreach ($enrollments as $enrollment) {
                $progress = $enrollment->getProgress();
                if ($progress && (!$latestProgress || $progress->getLastActivity() > $latestProgress->getLastActivity())) {
                    $latestProgress = $progress;
                }
            }
            if ($latestProgress) {
                $streakDays = $latestProgress->getStreakDays();
            }
        }
        
        return [
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
                'values' => [30, 45, 60, 20, 50, 35, 40] // Mock data for now
            ]
        ];
    }

    /**
     * Build recent achievements data.
     */
    private function buildRecentAchievements(array $enrollments): array
    {
        $achievements = [];
        
        foreach ($enrollments as $enrollment) {
            $progress = $enrollment->getProgress();
            if ($progress) {
                $milestones = $progress->getMilestones();
                
                // Convert milestones to achievements format
                foreach ($milestones as $key => $milestone) {
                    if (is_array($milestone)) {
                        $achievements[] = [
                            'title' => $milestone['title'] ?? 'Accomplissement',
                            'description' => $milestone['description'] ?? 'Nouveau succès débloqué!',
                            'type' => $this->getMilestoneType($key),
                            'date' => new \DateTime($milestone['achieved_at'] ?? 'now')
                        ];
                    }
                }
            }
        }
        
        // Sort by date (most recent first) and limit to 5
        usort($achievements, fn($a, $b) => $b['date'] <=> $a['date']);
        
        return array_slice($achievements, 0, 5);
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

    /**
     * Student certificates page.
     *
     * Displays earned certificates and available certifications.
     */
    #[Route('/certificates', name: 'student_certificates', methods: ['GET'])]
    public function certificates(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // TODO: Implement when certificate system is ready
        $certificatesData = [
            'student' => $student,
            'earned_certificates' => [], // TODO: Get earned certificates
            'available_certifications' => [], // TODO: Get available certifications
            'page_title' => 'Mes Certificats',
        ];

        return $this->render('student/dashboard/certificates.html.twig', $certificatesData);
    }

    /**
     * Student notifications page.
     *
     * Displays system notifications, course updates, and messages.
     */
    #[Route('/notifications', name: 'student_notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // TODO: Implement when notification system is ready
        $notificationsData = [
            'student' => $student,
            'unread_notifications' => [], // TODO: Get unread notifications
            'all_notifications' => [], // TODO: Get all notifications
            'page_title' => 'Notifications',
        ];

        return $this->render('student/dashboard/notifications.html.twig', $notificationsData);
    }

    /**
     * Student settings page.
     *
     * Allows students to modify account settings, preferences, and privacy options.
     */
    #[Route('/settings', name: 'student_settings', methods: ['GET'])]
    public function settings(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        return $this->render('student/dashboard/settings.html.twig', [
            'student' => $student,
            'page_title' => 'Paramètres',
        ]);
    }

    /**
     * Update progress tracking for content consumption.
     * 
     * API endpoint for Stimulus controllers to track learning progress.
     */
    #[Route('/progress/update', name: 'student_progress_update', methods: ['POST'])]
    public function updateProgress(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $this->getUser();

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $contentId = $data['contentId'] ?? null;
        $contentType = $data['contentType'] ?? null;
        $action = $data['action'] ?? 'view';

        if (!$contentId || !$contentType) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        // TODO: Implement actual progress tracking when StudentProgress system is enhanced
        // For now, just log the progress update
        $this->contentAccessService->logContentAccess($student, (object) [
            'getId' => fn() => $contentId,
            'getTitle' => fn() => "Content {$contentId}"
        ], true);

        return new JsonResponse([
            'success' => true,
            'message' => 'Progress updated',
            'contentId' => $contentId,
            'contentType' => $contentType,
            'action' => $action
        ]);
    }
}
