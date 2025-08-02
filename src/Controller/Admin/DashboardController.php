<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User\Admin;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * Admin Dashboard Controller.
 *
 * Handles the main admin dashboard interface with Tabler CSS.
 * Provides a simple admin interface for EPROFOS platform management.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * Admin dashboard homepage.
     *
     * Displays the main admin dashboard with basic information
     * and navigation to different admin sections.
     */
    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier() ?? 'anonymous';
        $clientIp = $this->getClientIp($request);
        $sessionId = $request->getSession()->getId();
        $userAgent = $request->headers->get('User-Agent', 'unknown');

        $this->logger->info('Admin dashboard access initiated', [
            'user' => $userIdentifier,
            'ip' => $clientIp,
            'session_id' => $sessionId,
            'user_agent' => $userAgent,
            'method' => $request->getMethod(),
            'route' => 'admin_dashboard',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Log user authentication details
            $admin = $this->getUser();
            if ($admin) {
                $logData = [
                    'user_email' => $admin->getUserIdentifier(),
                    'roles' => $admin->getRoles(),
                    'session_id' => $sessionId,
                ];

                // Add Admin-specific data if available
                if ($admin instanceof Admin) {
                    $logData['user_id'] = $admin->getId();
                    $logData['is_active'] = $admin->isActive();
                    $logData['full_name'] = $admin->getFullName();
                    $logData['last_login'] = $admin->getLastLoginAt()?->format('Y-m-d H:i:s');
                    $logData['created_at'] = $admin->getCreatedAt()?->format('Y-m-d H:i:s');
                }

                $this->logger->info('Admin user authenticated successfully', $logData);
            } else {
                $this->logger->warning('Admin dashboard accessed without authenticated user', [
                    'ip' => $clientIp,
                    'session_id' => $sessionId,
                ]);
            }

            // Log security context
            $this->logger->debug('Security context details', [
                'is_authenticated' => $this->isGranted('IS_AUTHENTICATED_FULLY'),
                'has_admin_role' => $this->isGranted('ROLE_ADMIN'),
                'has_super_admin_role' => $this->isGranted('ROLE_SUPER_ADMIN'),
                'user' => $userIdentifier,
            ]);

            // Prepare template data
            $templateData = [
                'user' => $admin,
                'page_title' => 'Dashboard',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => null],
                ],
            ];

            $this->logger->debug('Template data prepared for admin dashboard', [
                'user' => $userIdentifier,
                'page_title' => $templateData['page_title'],
                'breadcrumb_items' => count($templateData['breadcrumb']),
            ]);

            // Render template
            $this->logger->debug('Rendering admin dashboard template', [
                'template' => 'admin/dashboard/index.html.twig',
                'user' => $userIdentifier,
            ]);

            $response = $this->render('admin/dashboard/index.html.twig', $templateData);

            // Log successful response
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Admin dashboard rendered successfully', [
                'user' => $userIdentifier,
                'status_code' => $response->getStatusCode(),
                'execution_time_ms' => $executionTime,
                'response_size' => strlen($response->getContent()),
                'ip' => $clientIp,
                'session_id' => $sessionId,
            ]);

            return $response;

        } catch (Throwable $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->error('Error occurred while rendering admin dashboard', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => $executionTime,
                'ip' => $clientIp,
                'session_id' => $sessionId,
                'user_agent' => $userAgent,
                'request_uri' => $request->getRequestUri(),
                'request_method' => $request->getMethod(),
            ]);

            // Log additional context for debugging
            $this->logger->debug('Request context during error', [
                'query_parameters' => $request->query->all(),
                'request_headers' => $request->headers->all(),
                'server_parameters' => array_filter($request->server->all(), function($key) {
                    // Filter sensitive server parameters
                    return !in_array(strtolower($key), ['php_auth_pw', 'auth_password', 'password']);
                }, ARRAY_FILTER_USE_KEY),
            ]);

            // Create error response
            if ($this->container->get('kernel')->getEnvironment() === 'dev') {
                // In development, let the exception bubble up for detailed error page
                throw $e;
            } else {
                // In production, log error and show user-friendly message
                $this->logger->critical('Admin dashboard critical error in production', [
                    'user' => $userIdentifier,
                    'error_message' => $e->getMessage(),
                    'ip' => $clientIp,
                ]);

                // Return error page
                return $this->render('admin/error/500.html.twig', [
                    'error_message' => 'Une erreur est survenue lors du chargement du dashboard.',
                    'error_code' => 'ADMIN_DASHBOARD_ERROR',
                ], new Response('', Response::HTTP_INTERNAL_SERVER_ERROR));
            }
        }
    }

    /**
     * Get client IP address for logging.
     */
    private function getClientIp(Request $request): ?string
    {
        return $request->getClientIp();
    }
}
