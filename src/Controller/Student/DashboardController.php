<?php

namespace App\Controller\Student;

use App\Entity\User\Student;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student Dashboard Controller
 * 
 * Main dashboard for authenticated students to access their learning resources,
 * training progress, and account information.
 */
#[Route('/student', name: 'student_')]
#[IsGranted('ROLE_STUDENT')]
class DashboardController extends AbstractController
{
    /**
     * Student dashboard homepage
     * 
     * Displays an overview of the student's training progress, upcoming sessions,
     * recent activities, and quick access to learning resources.
     */
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // TODO: Add real data once training sessions and enrollments are implemented
        $dashboardData = [
            'student' => $student,
            'stats' => [
                'enrolled_courses' => 0, // TODO: Count actual enrollments
                'completed_courses' => 0, // TODO: Count completed courses
                'hours_studied' => 0, // TODO: Calculate from progress tracking
                'certificates_earned' => 0, // TODO: Count certificates
            ],
            'recent_activities' => [], // TODO: Get recent learning activities
            'upcoming_sessions' => [], // TODO: Get upcoming training sessions
            'available_courses' => [], // TODO: Get available/recommended courses
            'notifications' => [], // TODO: Get student notifications
        ];

        return $this->render('student/dashboard/index.html.twig', $dashboardData);
    }

    /**
     * Student profile page
     * 
     * Displays and allows editing of student personal information and preferences.
     */
    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function profile(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        return $this->render('student/dashboard/profile.html.twig', [
            'student' => $student,
            'page_title' => 'Mon Profil'
        ]);
    }

    /**
     * Student courses page
     * 
     * Displays enrolled courses, available courses, and course history.
     */
    #[Route('/courses', name: 'courses', methods: ['GET'])]
    public function courses(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // TODO: Implement when course enrollment system is ready
        $coursesData = [
            'student' => $student,
            'enrolled_courses' => [], // TODO: Get enrolled courses
            'available_courses' => [], // TODO: Get available courses
            'completed_courses' => [], // TODO: Get completed courses
            'page_title' => 'Mes Formations'
        ];

        return $this->render('student/dashboard/courses.html.twig', $coursesData);
    }

    /**
     * Student progress page
     * 
     * Displays detailed training progress, learning analytics, and achievements.
     */
    #[Route('/progress', name: 'progress', methods: ['GET'])]
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
            'page_title' => 'Ma Progression'
        ];

        return $this->render('student/dashboard/progress.html.twig', $progressData);
    }

    /**
     * Student certificates page
     * 
     * Displays earned certificates and available certifications.
     */
    #[Route('/certificates', name: 'certificates', methods: ['GET'])]
    public function certificates(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // TODO: Implement when certificate system is ready
        $certificatesData = [
            'student' => $student,
            'earned_certificates' => [], // TODO: Get earned certificates
            'available_certifications' => [], // TODO: Get available certifications
            'page_title' => 'Mes Certificats'
        ];

        return $this->render('student/dashboard/certificates.html.twig', $certificatesData);
    }

    /**
     * Student notifications page
     * 
     * Displays system notifications, course updates, and messages.
     */
    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // TODO: Implement when notification system is ready
        $notificationsData = [
            'student' => $student,
            'unread_notifications' => [], // TODO: Get unread notifications
            'all_notifications' => [], // TODO: Get all notifications
            'page_title' => 'Notifications'
        ];

        return $this->render('student/dashboard/notifications.html.twig', $notificationsData);
    }

    /**
     * Student settings page
     * 
     * Allows students to modify account settings, preferences, and privacy options.
     */
    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        return $this->render('student/dashboard/settings.html.twig', [
            'student' => $student,
            'page_title' => 'Paramètres'
        ]);
    }
}
