<?php

declare(strict_types=1);

namespace App\Controller\Mentor;

use App\Entity\User\Mentor;
use App\Service\User\MentorAuthenticationService;
use App\Service\User\MentorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mentor Dashboard Controller.
 *
 * Main dashboard for authenticated mentors to manage their apprentices,
 * create missions, and access company training resources.
 */
#[Route('/mentor', name: 'mentor_')]
#[IsGranted('ROLE_MENTOR')]
class DashboardController extends AbstractController
{
    public function __construct(
        private MentorService $mentorService,
        private MentorAuthenticationService $mentorAuthService,
    ) {}

    /**
     * Mentor dashboard homepage.
     *
     * Displays an overview of supervised apprentices, active missions,
     * recent activities, and quick access to mentor tools.
     */
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Check account setup completion
        $setupCompletion = $this->mentorAuthService->getAccountSetupCompletion($mentor);

        if (!$setupCompletion['is_complete']) {
            $this->addFlash('warning', 'Veuillez compléter votre profil pour accéder à toutes les fonctionnalités.');
        }

        // TODO: Add real data once Alternant and Mission entities are implemented
        $dashboardData = [
            'mentor' => $mentor,
            'setup_completion' => $setupCompletion,
            'stats' => [
                'supervised_apprentices' => $mentor->getAlternantsCount(), // Currently returns 0
                'active_missions' => $mentor->getMissionsCount(), // Currently returns 0
                'completed_missions' => 0, // TODO: Count completed missions
                'mentor_score' => 0, // TODO: Calculate mentor performance score
            ],
            'recent_activities' => [], // TODO: Get recent mentor activities
            'apprentices' => [], // TODO: Get supervised apprentices
            'upcoming_evaluations' => [], // TODO: Get upcoming apprentice evaluations
            'pending_reports' => [], // TODO: Get pending mission reports
            'notifications' => [], // TODO: Get mentor notifications
            'company_stats' => $this->mentorService->getCompanyStatistics($mentor->getCompanyName()),
        ];

        return $this->render('mentor/dashboard/index.html.twig', $dashboardData);
    }

    /**
     * Mentor profile page.
     *
     * Displays and allows editing of mentor professional information and expertise.
     */
    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function profile(): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        $setupCompletion = $this->mentorAuthService->getAccountSetupCompletion($mentor);

        return $this->render('mentor/dashboard/profile.html.twig', [
            'mentor' => $mentor,
            'setup_completion' => $setupCompletion,
            'expertise_domains' => Mentor::EXPERTISE_DOMAINS,
            'education_levels' => Mentor::EDUCATION_LEVELS,
            'page_title' => 'Mon Profil Mentor',
        ]);
    }

    /**
     * Apprentices management page.
     *
     * Redirects to the assignments page since this is where alternance management happens.
     */
    #[Route('/apprentices', name: 'apprentices', methods: ['GET'])]
    public function apprentices(): Response
    {
        // Redirect to assignments since that's where we manage alternance students
        return $this->redirectToRoute('mentor_assignments_index');
    }

    /**
     * Evaluations and reports page.
     *
     * Displays apprentice evaluations, progress reports, and assessment tools.
     */
    #[Route('/evaluations', name: 'evaluations', methods: ['GET'])]
    public function evaluations(): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // TODO: Implement when evaluation system is created
        $evaluationsData = [
            'mentor' => $mentor,
            'pending_evaluations' => [], // TODO: Get pending evaluations
            'completed_evaluations' => [], // TODO: Get completed evaluations
            'evaluation_templates' => [], // TODO: Get evaluation templates
            'progress_reports' => [], // TODO: Get progress reports
            'page_title' => 'Évaluations & Rapports',
        ];

        return $this->render('mentor/dashboard/evaluations.html.twig', $evaluationsData);
    }

    /**
     * Mentor notifications page.
     *
     * Displays system notifications, apprentice updates, and important announcements.
     */
    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // TODO: Implement when notification system is ready
        $notificationsData = [
            'mentor' => $mentor,
            'unread_notifications' => [], // TODO: Get unread notifications
            'apprentice_updates' => [], // TODO: Get apprentice progress updates
            'system_announcements' => [], // TODO: Get system announcements
            'evaluation_reminders' => [], // TODO: Get evaluation reminders
            'page_title' => 'Notifications',
        ];

        return $this->render('mentor/dashboard/notifications.html.twig', $notificationsData);
    }

    /**
     * Mentor settings page.
     *
     * Allows mentors to modify account settings, notification preferences, and privacy options.
     */
    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        $securityIssues = $this->mentorAuthService->performSecurityCheck($mentor);

        return $this->render('mentor/dashboard/settings.html.twig', [
            'mentor' => $mentor,
            'security_issues' => $securityIssues,
            'page_title' => 'Paramètres Mentor',
        ]);
    }

    /**
     * Mentor help page.
     *
     * Displays help documentation, FAQs, and support contact information.
     */
    #[Route('/help', name: 'help', methods: ['GET'])]
    public function help(): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        return $this->render('mentor/dashboard/help.html.twig', [
            'mentor' => $mentor,
            'page_title' => 'Aide & Support',
        ]);
    }

    // PLACEHOLDER ROUTES - These will be implemented when the corresponding features are added

    /**
     * Meetings management page.
     *
     * Displays upcoming meetings, meeting history, and scheduling tools.
     */
    #[Route('/meetings', name: 'meetings', methods: ['GET'])]
    public function meetings(): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // TODO: Implement when meeting system is ready
        $meetingsData = [
            'mentor' => $mentor,
            'upcoming_meetings' => [], // TODO: Get upcoming meetings
            'past_meetings' => [], // TODO: Get past meetings
            'meeting_requests' => [], // TODO: Get meeting requests from apprentices
            'available_slots' => [], // TODO: Get available time slots
            'page_title' => 'Rendez-vous',
        ];

        return $this->render('mentor/dashboard/meetings.html.twig', $meetingsData);
    }

    /**
     * Reports management page.
     *
     * Displays performance reports, progress reports, and analytics.
     */
    #[Route('/reports', name: 'reports', methods: ['GET'])]
    public function reports(): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // TODO: Implement when reporting system is ready
        $reportsData = [
            'mentor' => $mentor,
            'apprentice_reports' => [], // TODO: Get apprentice progress reports
            'performance_reports' => [], // TODO: Get performance reports
            'company_reports' => [], // TODO: Get company-wide reports
            'custom_reports' => [], // TODO: Get custom reports
            'page_title' => 'Rapports',
        ];

        return $this->render('mentor/dashboard/reports.html.twig', $reportsData);
    }
}
