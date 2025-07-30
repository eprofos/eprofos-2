<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Entity\User\Student;
use App\Service\Security\ContentAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    #[Route('/dashboard', name: 'student_dashboard', methods: ['GET'])]
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

        $dashboardData = [
            'student' => $student,
            'stats' => $stats,
            'accessible_formations' => array_slice($accessibleFormations, 0, 3), // Show first 3
            'recent_enrollments' => array_slice($enrollments, 0, 5), // Show latest 5
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

        // TODO: Implement when progress tracking system is ready
        $progressData = [
            'student' => $student,
            'overall_progress' => 0, // TODO: Calculate overall progress percentage
            'course_progress' => [], // TODO: Get progress for each enrolled course
            'recent_achievements' => [], // TODO: Get recent achievements/badges
            'study_time_stats' => [], // TODO: Get study time statistics
            'page_title' => 'Ma Progression',
        ];

        return $this->render('student/dashboard/progress.html.twig', $progressData);
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
}
