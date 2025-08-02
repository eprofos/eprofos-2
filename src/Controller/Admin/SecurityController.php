<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Admin Security Controller.
 *
 * Handles authentication for the admin interface.
 * Provides login and logout functionality for admin users with comprehensive logging.
 * 
 * Security Features:
 * - Detailed logging of all authentication attempts
 * - IP address and user agent tracking
 * - Session monitoring
 * - Exception handling with secure error responses
 * - Failed login attempt tracking
 */
#[Route('/admin')]
class SecurityController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
        // Log controller instantiation for debugging
        $this->logger->debug('Admin SecurityController instantiated', [
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'class' => self::class,
        ]);
    }

    /**
     * Admin login page.
     *
     * Displays the login form for admin admins using Tabler CSS.
     */
    #[Route('/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->getUserAgent();
        $requestMethod = $this->getRequestMethod();
        
        try {
            $this->logger->info('Admin login page accessed', [
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'method' => $requestMethod,
                'route' => 'admin_login',
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            // If admin is already authenticated, redirect to dashboard
            $admin = $this->getUser();
            if ($admin) {
                $this->logger->info('Admin already authenticated, redirecting to dashboard', [
                    'admin_username' => $admin->getUserIdentifier(),
                    'admin_class' => get_class($admin),
                    'ip' => $clientIp,
                    'redirect_to' => 'admin_dashboard',
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);
                
                return $this->redirectToRoute('admin_dashboard');
            }

            // Get the login error if there is one
            $error = $authenticationUtils->getLastAuthenticationError();

            // Last username entered by the admin
            $lastAdminUsername = $authenticationUtils->getLastUsername();

            if ($error) {
                $securityContext = $this->getSecurityContext($clientIp, $userAgent, $lastAdminUsername);
                
                $this->logger->warning('Admin login authentication failed', [
                    'username' => $lastAdminUsername,
                    'error_message' => $error->getMessage(),
                    'error_class' => get_class($error),
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                    'attempt_timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'session_id' => $this->getSessionId(),
                    'security_context' => $securityContext,
                    'referer' => $this->getReferer(),
                ]);

                // Log potential security threats
                if ($securityContext['is_suspicious']) {
                    $this->logger->alert('Suspicious admin login attempt detected', [
                        'username' => $lastAdminUsername,
                        'ip' => $clientIp,
                        'user_agent' => $userAgent,
                        'threat_indicators' => $securityContext['threat_indicators'],
                        'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ]);
                }
            } else {
                $this->logger->debug('Admin login form displayed', [
                    'last_username' => $lastAdminUsername,
                    'ip' => $clientIp,
                    'session_id' => $this->getSessionId(),
                    'referer' => $this->getReferer(),
                ]);
            }

            $this->logger->debug('Rendering admin login template', [
                'template' => 'admin/security/login.html.twig',
                'has_error' => $error !== null,
                'last_username' => $lastAdminUsername,
            ]);

            return $this->render('admin/security/login.html.twig', [
                'last_username' => $lastAdminUsername,
                'error' => $error,
                'page_title' => 'Connexion Admin',
            ]);

        } catch (\Exception $e) {
            $this->logger->critical('Critical error in admin login process', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            // Return a generic error page to avoid exposing sensitive information
            return $this->render('admin/security/login.html.twig', [
                'last_username' => '',
                'error' => null,
                'page_title' => 'Connexion Admin',
                'system_error' => 'Une erreur système est survenue. Veuillez réessayer.',
            ]);
        }
    }

    /**
     * Admin logout.
     *
     * This method can be blank - it will be intercepted by the logout key on your firewall.
     */
    #[Route('/logout', name: 'admin_logout', methods: ['GET'])]
    public function logout(): void
    {
        try {
            $admin = $this->getUser();
            $clientIp = $this->getClientIp();
            
            $this->logger->info('Admin logout initiated', [
                'admin_username' => $admin ? $admin->getUserIdentifier() : 'unknown',
                'ip' => $clientIp,
                'user_agent' => $this->getUserAgent(),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                'session_id' => $this->getSessionId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error during admin logout logging', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'ip' => $this->getClientIp(),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }

        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Get client IP address for logging.
     */
    private function getClientIp(): ?string
    {
        try {
            $request = $this->container->get('request_stack')->getCurrentRequest();

            if (!$request) {
                $this->logger->debug('No current request found for IP detection');
                return null;
            }

            $ip = $request->getClientIp();
            
            $this->logger->debug('Client IP detected', [
                'ip' => $ip,
                'forwarded_for' => $request->headers->get('X-Forwarded-For'),
                'real_ip' => $request->headers->get('X-Real-IP'),
            ]);

            return $ip;

        } catch (\Exception $e) {
            $this->logger->error('Error getting client IP', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            
            return null;
        }
    }

    /**
     * Get user agent for logging.
     */
    private function getUserAgent(): ?string
    {
        try {
            $request = $this->container->get('request_stack')->getCurrentRequest();

            if (!$request) {
                return null;
            }

            return $request->headers->get('User-Agent');

        } catch (\Exception $e) {
            $this->logger->error('Error getting user agent', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            
            return null;
        }
    }

    /**
     * Get request method for logging.
     */
    private function getRequestMethod(): ?string
    {
        try {
            $request = $this->container->get('request_stack')->getCurrentRequest();

            if (!$request) {
                return null;
            }

            return $request->getMethod();

        } catch (\Exception $e) {
            $this->logger->error('Error getting request method', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            
            return null;
        }
    }

    /**
     * Get session ID for logging.
     */
    private function getSessionId(): ?string
    {
        try {
            $request = $this->container->get('request_stack')->getCurrentRequest();

            if (!$request || !$request->hasSession()) {
                return null;
            }

            $session = $request->getSession();
            
            if (!$session->isStarted()) {
                return null;
            }

            return $session->getId();

        } catch (\Exception $e) {
            $this->logger->error('Error getting session ID', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            
            return null;
        }
    }

    /**
     * Get referer for logging.
     */
    private function getReferer(): ?string
    {
        try {
            $request = $this->container->get('request_stack')->getCurrentRequest();

            if (!$request) {
                return null;
            }

            return $request->headers->get('Referer');

        } catch (\Exception $e) {
            $this->logger->error('Error getting referer', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            
            return null;
        }
    }

    /**
     * Get security context for threat analysis.
     */
    private function getSecurityContext(?string $ip, ?string $userAgent, ?string $username): array
    {
        try {
            $context = [
                'is_suspicious' => false,
                'threat_indicators' => [],
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];

            // Check for suspicious IP patterns
            if ($ip) {
                // Check for common bot/scanner IP patterns
                if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $ip)) {
                    $context['threat_indicators'][] = 'private_ip_range';
                }

                // Check for known problematic IP ranges (this is a basic example)
                if (preg_match('/^(127\.|0\.|255\.)/', $ip)) {
                    $context['threat_indicators'][] = 'invalid_ip_range';
                    $context['is_suspicious'] = true;
                }
            }

            // Check for suspicious user agents
            if ($userAgent) {
                $suspiciousPatterns = [
                    'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python',
                    'automated', 'scanner', 'exploit', 'nikto', 'sqlmap'
                ];

                foreach ($suspiciousPatterns as $pattern) {
                    if (stripos($userAgent, $pattern) !== false) {
                        $context['threat_indicators'][] = "suspicious_user_agent:$pattern";
                        $context['is_suspicious'] = true;
                        break;
                    }
                }

                // Check for extremely short or long user agents
                if (strlen($userAgent) < 10) {
                    $context['threat_indicators'][] = 'user_agent_too_short';
                    $context['is_suspicious'] = true;
                } elseif (strlen($userAgent) > 500) {
                    $context['threat_indicators'][] = 'user_agent_too_long';
                    $context['is_suspicious'] = true;
                }
            }

            // Check for suspicious usernames
            if ($username) {
                $suspiciousUsernames = [
                    'admin', 'administrator', 'root', 'test', 'guest', 'user',
                    'demo', 'default', '123', 'password', 'null', 'undefined'
                ];

                if (in_array(strtolower($username), $suspiciousUsernames)) {
                    $context['threat_indicators'][] = 'common_username_attempt';
                    $context['is_suspicious'] = true;
                }

                // Check for SQL injection attempts in username
                if (preg_match('/[\'";\\\\]|union|select|drop|insert|update|delete/i', $username)) {
                    $context['threat_indicators'][] = 'sql_injection_attempt';
                    $context['is_suspicious'] = true;
                }

                // Check for script injection attempts
                if (preg_match('/<script|javascript:|data:/i', $username)) {
                    $context['threat_indicators'][] = 'script_injection_attempt';
                    $context['is_suspicious'] = true;
                }
            }

            return $context;

        } catch (\Exception $e) {
            $this->logger->error('Error analyzing security context', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'ip' => $ip,
                'user_agent' => $userAgent,
                'username' => $username,
            ]);

            return [
                'is_suspicious' => false,
                'threat_indicators' => ['analysis_error'],
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];
        }
    }
}
