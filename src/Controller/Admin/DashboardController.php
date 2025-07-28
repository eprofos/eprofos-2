<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Dashboard Controller.
 *
 * Handles the main admin dashboard interface with Tabler CSS.
 * Provides a simple admin interface for EPROFOS platform management.
 */
#[Route('/admin', name: 'admin_')]
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
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $this->logger->info('Admin dashboard accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'ip' => $this->getClientIp(),
        ]);

        // Get current user for display
        $admin = $this->getUser();

        return $this->render('admin/dashboard/index.html.twig', [
            'user' => $admin,
            'page_title' => 'Dashboard',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => null],
            ],
        ]);
    }

    /**
     * Get client IP address for logging.
     */
    private function getClientIp(): ?string
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if (!$request) {
            return null;
        }

        return $request->getClientIp();
    }
}
