<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Psr\Log\LoggerInterface;

/**
 * Admin Security Controller
 * 
 * Handles authentication for the admin interface.
 * Provides login and logout functionality for admin users.
 */
#[Route('/admin', name: 'admin_')]
class SecurityController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Admin login page
     * 
     * Displays the login form for admin users using Tabler CSS.
     */
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already authenticated, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error) {
            $this->logger->warning('Admin login failed', [
                'username' => $lastUsername,
                'error' => $error->getMessage(),
                'ip' => $this->getClientIp()
            ]);
        }

        return $this->render('admin/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'page_title' => 'Connexion Admin'
        ]);
    }

    /**
     * Admin logout
     * 
     * This method can be blank - it will be intercepted by the logout key on your firewall.
     */
    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Get client IP address for logging
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