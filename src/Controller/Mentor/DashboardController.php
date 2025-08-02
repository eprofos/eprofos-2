<?php

declare(strict_types=1);

namespace App\Controller\Mentor;

use App\Entity\User\Mentor;
use App\Service\User\MentorAuthenticationService;
use App\Service\User\MentorService;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mentor Dashboard Controller.
 *
 * Main dashboard for authenticated mentors to manage their apprentices,
 * create missions, and access company training resources.
 */
#[Route('/mentor')]
#[IsGranted('ROLE_MENTOR')]
class DashboardController extends AbstractController
{
    public function __construct(
        private MentorService $mentorService,
        private MentorAuthenticationService $mentorAuthService,
        private LoggerInterface $logger,
    ) {}

    /**
     * Mentor dashboard homepage.
     *
     * Displays an overview of supervised apprentices, active missions,
     * recent activities, and quick access to mentor tools.
     */
    #[Route('/dashboard', name: 'mentor_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $this->logger->info('Mentor dashboard access initiated');

        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Retrieved mentor user for dashboard', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'company_name' => $mentor->getCompanyName(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            // Check account setup completion
            $this->logger->debug('Starting account setup completion check');
            $setupCompletion = $this->mentorAuthService->getAccountSetupCompletion($mentor);

            $this->logger->info('Account setup completion checked', [
                'mentor_id' => $mentor->getId(),
                'is_complete' => $setupCompletion['is_complete'],
                'completion_percentage' => $setupCompletion['completion_percentage'] ?? 0,
                'missing_fields' => $setupCompletion['missing_fields'] ?? [],
            ]);

            if (!$setupCompletion['is_complete']) {
                $this->logger->warning('Mentor account setup incomplete - displaying warning', [
                    'mentor_id' => $mentor->getId(),
                    'missing_fields' => $setupCompletion['missing_fields'] ?? [],
                ]);
                $this->addFlash('warning', 'Veuillez compléter votre profil pour accéder à toutes les fonctionnalités.');
            }

            // Get company statistics
            $this->logger->debug('Retrieving company statistics');
            $companyStats = $this->mentorService->getCompanyStatistics($mentor->getCompanyName());

            $this->logger->info('Company statistics retrieved successfully', [
                'mentor_id' => $mentor->getId(),
                'company_name' => $mentor->getCompanyName(),
                'stats_count' => count($companyStats),
            ]);

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
                'company_stats' => $companyStats,
            ];

            $this->logger->info('Dashboard data prepared successfully', [
                'mentor_id' => $mentor->getId(),
                'supervised_apprentices' => $dashboardData['stats']['supervised_apprentices'],
                'active_missions' => $dashboardData['stats']['active_missions'],
                'has_company_stats' => !empty($companyStats),
            ]);

            $response = $this->render('mentor/dashboard/index.html.twig', $dashboardData);

            $this->logger->info('Mentor dashboard rendered successfully', [
                'mentor_id' => $mentor->getId(),
                'response_status' => $response->getStatusCode(),
                'template' => 'mentor/dashboard/index.html.twig',
            ]);

            return $response;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided to mentor dashboard', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur de paramétrage s\'est produite. Veuillez contacter le support.');

            return $this->redirectToRoute('mentor_profile');
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime error in mentor dashboard', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Un problème technique temporaire s\'est produit. Veuillez réessayer.');

            throw new ServiceUnavailableHttpException(30, 'Service temporairement indisponible');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in mentor dashboard', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. L\'équipe technique a été notifiée.');

            return $this->redirectToRoute('mentor_help');
        }
    }

    /**
     * Mentor profile page.
     *
     * Displays and allows editing of mentor professional information and expertise.
     */
    #[Route('/profile', name: 'mentor_profile', methods: ['GET'])]
    public function profile(): Response
    {
        $this->logger->info('Mentor profile page access initiated');

        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Retrieved mentor user for profile page', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'has_expertise' => !empty($mentor->getExpertiseDomains()),
                'has_education_level' => !empty($mentor->getEducationLevel()),
            ]);

            $this->logger->debug('Starting account setup completion check for profile');
            $setupCompletion = $this->mentorAuthService->getAccountSetupCompletion($mentor);

            $this->logger->info('Profile setup completion checked', [
                'mentor_id' => $mentor->getId(),
                'is_complete' => $setupCompletion['is_complete'],
                'completion_percentage' => $setupCompletion['completion_percentage'] ?? 0,
            ]);

            $profileData = [
                'mentor' => $mentor,
                'setup_completion' => $setupCompletion,
                'expertise_domains' => Mentor::EXPERTISE_DOMAINS,
                'education_levels' => Mentor::EDUCATION_LEVELS,
                'page_title' => 'Mon Profil Mentor',
            ];

            $this->logger->info('Profile data prepared successfully', [
                'mentor_id' => $mentor->getId(),
                'available_expertise_domains' => count(Mentor::EXPERTISE_DOMAINS),
                'available_education_levels' => count(Mentor::EDUCATION_LEVELS),
            ]);

            $response = $this->render('mentor/dashboard/profile.html.twig', $profileData);

            $this->logger->info('Mentor profile page rendered successfully', [
                'mentor_id' => $mentor->getId(),
                'response_status' => $response->getStatusCode(),
                'template' => 'mentor/dashboard/profile.html.twig',
            ]);

            return $response;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in mentor profile page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Erreur de paramétrage du profil. Veuillez contacter le support.');

            return $this->redirectToRoute('mentor_dashboard');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in mentor profile page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'accès au profil.');

            return $this->redirectToRoute('mentor_dashboard');
        }
    }

    /**
     * Apprentices management page.
     *
     * Redirects to the assignments page since this is where alternance management happens.
     */
    #[Route('/apprentices', name: 'mentor_apprentices', methods: ['GET'])]
    public function apprentices(): Response
    {
        $this->logger->info('Mentor apprentices page access - redirecting to assignments');

        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Redirecting mentor to assignments page', [
                'mentor_id' => $mentor->getId(),
                'from_route' => 'mentor_apprentices',
                'to_route' => 'mentor_assignments_index',
            ]);

            // Redirect to assignments since that's where we manage alternance students
            return $this->redirectToRoute('mentor_assignments_index');
        } catch (Exception $e) {
            $this->logger->error('Error in mentor apprentices redirect', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
            ]);

            $this->addFlash('error', 'Erreur lors de l\'accès à la gestion des apprentis.');

            return $this->redirectToRoute('mentor_dashboard');
        }
    }

    /**
     * Evaluations and reports page.
     *
     * Displays apprentice evaluations, progress reports, and assessment tools.
     */
    #[Route('/evaluations', name: 'mentor_evaluations', methods: ['GET'])]
    public function evaluations(): Response
    {
        $this->logger->info('Mentor evaluations page access initiated');

        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Retrieved mentor user for evaluations page', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'company_name' => $mentor->getCompanyName(),
            ]);

            // TODO: Implement when evaluation system is created
            $evaluationsData = [
                'mentor' => $mentor,
                'pending_evaluations' => [], // TODO: Get pending evaluations
                'completed_evaluations' => [], // TODO: Get completed evaluations
                'evaluation_templates' => [], // TODO: Get evaluation templates
                'progress_reports' => [], // TODO: Get progress reports
                'page_title' => 'Évaluations & Rapports',
            ];

            $this->logger->info('Evaluations data prepared (placeholder)', [
                'mentor_id' => $mentor->getId(),
                'pending_evaluations_count' => count($evaluationsData['pending_evaluations']),
                'completed_evaluations_count' => count($evaluationsData['completed_evaluations']),
                'evaluation_templates_count' => count($evaluationsData['evaluation_templates']),
            ]);

            $response = $this->render('mentor/dashboard/evaluations.html.twig', $evaluationsData);

            $this->logger->info('Mentor evaluations page rendered successfully', [
                'mentor_id' => $mentor->getId(),
                'response_status' => $response->getStatusCode(),
                'template' => 'mentor/dashboard/evaluations.html.twig',
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error in mentor evaluations page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
            ]);

            $this->addFlash('error', 'Erreur lors de l\'accès aux évaluations. Veuillez réessayer.');

            return $this->redirectToRoute('mentor_dashboard');
        }
    }

    /**
     * Mentor notifications page.
     *
     * Displays system notifications, apprentice updates, and important announcements.
     */
    #[Route('/notifications', name: 'mentor_notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        $this->logger->info('Mentor notifications page access initiated');

        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Retrieved mentor user for notifications page', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ]);

            // TODO: Implement when notification system is ready
            $notificationsData = [
                'mentor' => $mentor,
                'unread_notifications' => [], // TODO: Get unread notifications
                'apprentice_updates' => [], // TODO: Get apprentice progress updates
                'system_announcements' => [], // TODO: Get system announcements
                'evaluation_reminders' => [], // TODO: Get evaluation reminders
                'page_title' => 'Notifications',
            ];

            $this->logger->info('Notifications data prepared (placeholder)', [
                'mentor_id' => $mentor->getId(),
                'unread_notifications_count' => count($notificationsData['unread_notifications']),
                'apprentice_updates_count' => count($notificationsData['apprentice_updates']),
                'system_announcements_count' => count($notificationsData['system_announcements']),
                'evaluation_reminders_count' => count($notificationsData['evaluation_reminders']),
            ]);

            $response = $this->render('mentor/dashboard/notifications.html.twig', $notificationsData);

            $this->logger->info('Mentor notifications page rendered successfully', [
                'mentor_id' => $mentor->getId(),
                'response_status' => $response->getStatusCode(),
                'template' => 'mentor/dashboard/notifications.html.twig',
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error in mentor notifications page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
            ]);

            $this->addFlash('error', 'Erreur lors de l\'accès aux notifications. Veuillez réessayer.');

            return $this->redirectToRoute('mentor_dashboard');
        }
    }

    /**
     * Mentor settings page.
     *
     * Allows mentors to modify account settings, notification preferences, and privacy options.
     */
    #[Route('/settings', name: 'mentor_settings', methods: ['GET'])]
    public function settings(): Response
    {
        $this->logger->info('Mentor settings page access initiated');

        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Retrieved mentor user for settings page', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'company_name' => $mentor->getCompanyName(),
                'is_active' => $mentor->isActive(),
            ]);

            $this->logger->debug('Performing security check for mentor settings');
            $securityIssues = $this->mentorAuthService->performSecurityCheck($mentor);

            $this->logger->info('Security check completed for mentor settings', [
                'mentor_id' => $mentor->getId(),
                'security_issues_count' => count($securityIssues),
                'has_security_issues' => !empty($securityIssues),
            ]);

            if (!empty($securityIssues)) {
                $this->logger->warning('Security issues detected for mentor', [
                    'mentor_id' => $mentor->getId(),
                    'security_issues' => $securityIssues,
                ]);
            }

            $settingsData = [
                'mentor' => $mentor,
                'security_issues' => $securityIssues,
                'page_title' => 'Paramètres Mentor',
            ];

            $response = $this->render('mentor/dashboard/settings.html.twig', $settingsData);

            $this->logger->info('Mentor settings page rendered successfully', [
                'mentor_id' => $mentor->getId(),
                'response_status' => $response->getStatusCode(),
                'template' => 'mentor/dashboard/settings.html.twig',
                'has_security_issues' => !empty($securityIssues),
            ]);

            return $response;
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime error in mentor settings page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Problème technique lors de l\'accès aux paramètres. Service temporairement indisponible.');

            return $this->redirectToRoute('mentor_dashboard');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in mentor settings page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
            ]);

            $this->addFlash('error', 'Erreur inattendue lors de l\'accès aux paramètres.');

            return $this->redirectToRoute('mentor_dashboard');
        }
    }

    /**
     * Mentor help page.
     *
     * Displays help documentation, FAQs, and support contact information.
     */
    #[Route('/help', name: 'mentor_help', methods: ['GET'])]
    public function help(): Response
    {
        $this->logger->info('Mentor help page access initiated');

        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Retrieved mentor user for help page', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            $helpData = [
                'mentor' => $mentor,
                'page_title' => 'Aide & Support',
            ];

            $response = $this->render('mentor/dashboard/help.html.twig', $helpData);

            $this->logger->info('Mentor help page rendered successfully', [
                'mentor_id' => $mentor->getId(),
                'response_status' => $response->getStatusCode(),
                'template' => 'mentor/dashboard/help.html.twig',
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error in mentor help page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
            ]);

            // Even if there's an error, try to show a basic help page
            return $this->render('mentor/dashboard/help.html.twig', [
                'mentor' => $this->getUser(),
                'page_title' => 'Aide & Support',
                'error_mode' => true,
            ]);
        }
    }

    // PLACEHOLDER ROUTES - These will be implemented when the corresponding features are added

    /**
     * Meetings management page.
     *
     * Displays upcoming meetings, meeting history, and scheduling tools.
     */
    #[Route('/meetings', name: 'mentor_meetings', methods: ['GET'])]
    public function meetings(): Response
    {
        $this->logger->info('Mentor meetings page access initiated');

        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Retrieved mentor user for meetings page', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'company_name' => $mentor->getCompanyName(),
            ]);

            // TODO: Implement when meeting system is ready
            $meetingsData = [
                'mentor' => $mentor,
                'upcoming_meetings' => [], // TODO: Get upcoming meetings
                'past_meetings' => [], // TODO: Get past meetings
                'meeting_requests' => [], // TODO: Get meeting requests from apprentices
                'available_slots' => [], // TODO: Get available time slots
                'page_title' => 'Rendez-vous',
            ];

            $this->logger->info('Meetings data prepared (placeholder)', [
                'mentor_id' => $mentor->getId(),
                'upcoming_meetings_count' => count($meetingsData['upcoming_meetings']),
                'past_meetings_count' => count($meetingsData['past_meetings']),
                'meeting_requests_count' => count($meetingsData['meeting_requests']),
                'available_slots_count' => count($meetingsData['available_slots']),
            ]);

            $response = $this->render('mentor/dashboard/meetings.html.twig', $meetingsData);

            $this->logger->info('Mentor meetings page rendered successfully', [
                'mentor_id' => $mentor->getId(),
                'response_status' => $response->getStatusCode(),
                'template' => 'mentor/dashboard/meetings.html.twig',
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error in mentor meetings page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
            ]);

            $this->addFlash('error', 'Erreur lors de l\'accès à la gestion des rendez-vous.');

            return $this->redirectToRoute('mentor_dashboard');
        }
    }

    /**
     * Reports management page.
     *
     * Displays performance reports, progress reports, and analytics.
     */
    #[Route('/reports', name: 'mentor_reports', methods: ['GET'])]
    public function reports(): Response
    {
        $this->logger->info('Mentor reports page access initiated');

        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Retrieved mentor user for reports page', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'company_name' => $mentor->getCompanyName(),
                'expertise_domains' => $mentor->getExpertiseDomains(),
            ]);

            // TODO: Implement when reporting system is ready
            $reportsData = [
                'mentor' => $mentor,
                'apprentice_reports' => [], // TODO: Get apprentice progress reports
                'performance_reports' => [], // TODO: Get performance reports
                'company_reports' => [], // TODO: Get company-wide reports
                'custom_reports' => [], // TODO: Get custom reports
                'page_title' => 'Rapports',
            ];

            $this->logger->info('Reports data prepared (placeholder)', [
                'mentor_id' => $mentor->getId(),
                'apprentice_reports_count' => count($reportsData['apprentice_reports']),
                'performance_reports_count' => count($reportsData['performance_reports']),
                'company_reports_count' => count($reportsData['company_reports']),
                'custom_reports_count' => count($reportsData['custom_reports']),
            ]);

            $response = $this->render('mentor/dashboard/reports.html.twig', $reportsData);

            $this->logger->info('Mentor reports page rendered successfully', [
                'mentor_id' => $mentor->getId(),
                'response_status' => $response->getStatusCode(),
                'template' => 'mentor/dashboard/reports.html.twig',
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error in mentor reports page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
            ]);

            $this->addFlash('error', 'Erreur lors de l\'accès aux rapports. Veuillez réessayer.');

            return $this->redirectToRoute('mentor_dashboard');
        }
    }
}
