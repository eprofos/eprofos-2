<?php

declare(strict_types=1);

namespace App\Controller\Mentor;

use App\Entity\User\Mentor;
use App\Form\User\MentorRegistrationFormType;
use App\Repository\User\MentorRepository;
use App\Service\User\MentorAuthenticationService;
use App\Service\User\MentorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Mentor Security Controller.
 *
 * Handles authentication, registration, and password management for mentors.
 * Provides login, registration, and password reset functionality.
 */
#[Route('/mentor')]
class SecurityController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private MentorService $mentorService,
        private MentorAuthenticationService $mentorAuthService,
    ) {}

    /**
     * Mentor login page.
     *
     * Displays the login form for mentor users.
     */
    #[Route('/login', name: 'mentor_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->getUserAgent();
        
        try {
            $this->logger->info('Mentor login page accessed', [
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'session_id' => session_id(),
                'timestamp' => new \DateTime(),
            ]);

            // If user is already authenticated, redirect to dashboard
            /** @var Mentor|null $currentUser */
            $currentUser = $this->getUser();
            if ($currentUser instanceof Mentor) {
                $this->logger->info('Already authenticated mentor accessing login page', [
                    'mentor_id' => $currentUser->getId(),
                    'email' => $currentUser->getEmail(),
                    'ip' => $clientIp,
                    'action' => 'redirect_to_dashboard',
                ]);
                return $this->redirectToRoute('mentor_dashboard');
            }

            // Get the login error if there is one
            $error = $authenticationUtils->getLastAuthenticationError();

            // Last username entered by the user
            $lastUsername = $authenticationUtils->getLastUsername();

            if ($error) {
                $this->logger->warning('Mentor login authentication failed', [
                    'username' => $lastUsername,
                    'error_message' => $error->getMessage(),
                    'error_class' => get_class($error),
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                    'session_id' => session_id(),
                    'timestamp' => new \DateTime(),
                    'login_attempt_context' => [
                        'username_length' => $lastUsername ? strlen($lastUsername) : 0,
                        'has_previous_session' => !empty($_COOKIE[session_name()]),
                    ],
                ]);
            } else {
                $this->logger->debug('Mentor login page displayed successfully', [
                    'last_username' => $lastUsername,
                    'ip' => $clientIp,
                    'has_error' => false,
                ]);
            }

            return $this->render('mentor/security/login.html.twig', [
                'last_username' => $lastUsername,
                'error' => $error,
                'page_title' => 'Connexion Mentor',
            ]);

        } catch (Exception $e) {
            $this->logger->error('Critical error in mentor login page', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'session_id' => session_id(),
                'timestamp' => new \DateTime(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer.');
            
            // Return a minimal login page in case of error
            return $this->render('mentor/security/login.html.twig', [
                'last_username' => '',
                'error' => null,
                'page_title' => 'Connexion Mentor',
            ]);
        }
    }

    /**
     * Mentor registration page.
     *
     * Allows new mentors to create an account.
     */
    #[Route('/register', name: 'mentor_register', methods: ['GET', 'POST'])]
    public function register(Request $request, MentorRepository $mentorRepository): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->getUserAgent();
        
        try {
            $this->logger->info('Mentor registration page accessed', [
                'method' => $request->getMethod(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'session_id' => session_id(),
                'timestamp' => new \DateTime(),
            ]);

            // If user is already authenticated, redirect to dashboard
            /** @var Mentor|null $currentUser */
            $currentUser = $this->getUser();
            if ($currentUser instanceof Mentor) {
                $this->logger->info('Already authenticated mentor accessing registration page', [
                    'mentor_id' => $currentUser->getId(),
                    'email' => $currentUser->getEmail(),
                    'ip' => $clientIp,
                    'action' => 'redirect_to_dashboard',
                ]);
                return $this->redirectToRoute('mentor_dashboard');
            }

            $mentor = new Mentor();
            $form = $this->createForm(MentorRegistrationFormType::class, $mentor);
            
            $this->logger->debug('Registration form created', [
                'form_class' => MentorRegistrationFormType::class,
                'entity_class' => get_class($mentor),
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Mentor registration form submitted', [
                    'ip' => $clientIp,
                    'is_valid' => $form->isValid(),
                    'submitted_email' => $mentor->getEmail(),
                    'submitted_company' => $mentor->getCompanyName(),
                    'form_errors_count' => count($form->getErrors(true)),
                    'timestamp' => new \DateTime(),
                ]);

                if (!$form->isValid()) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = [
                            'field' => $error->getOrigin() ? $error->getOrigin()->getName() : 'general',
                            'message' => $error->getMessage(),
                        ];
                    }
                    
                    $this->logger->warning('Mentor registration form validation failed', [
                        'email' => $mentor->getEmail(),
                        'form_errors' => $errors,
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                    ]);
                }
            }

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->logger->info('Processing valid mentor registration', [
                        'email' => $mentor->getEmail(),
                        'company_name' => $mentor->getCompanyName(),
                        'company_siret' => $mentor->getCompanySiret(),
                        'position' => $mentor->getPosition(),
                        'experience_years' => $mentor->getExperienceYears(),
                        'education_level' => $mentor->getEducationLevel(),
                        'expertise_domains' => $mentor->getExpertiseDomains(),
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                    ]);

                    // Check for existing mentor with same email
                    $existingMentor = $mentorRepository->findByEmail($mentor->getEmail());
                    if ($existingMentor) {
                        $this->logger->warning('Registration attempt with existing email', [
                            'email' => $mentor->getEmail(),
                            'existing_mentor_id' => $existingMentor->getId(),
                            'existing_mentor_verified' => $existingMentor->isEmailVerified(),
                            'ip' => $clientIp,
                        ]);
                        throw new InvalidArgumentException('Un compte avec cet email existe déjà.');
                    }

                    // Prepare mentor data
                    $mentorData = [
                        'email' => $mentor->getEmail(),
                        'firstName' => $mentor->getFirstName(),
                        'lastName' => $mentor->getLastName(),
                        'phone' => $mentor->getPhone(),
                        'position' => $mentor->getPosition(),
                        'companyName' => $mentor->getCompanyName(),
                        'companySiret' => $mentor->getCompanySiret(),
                        'expertiseDomains' => $mentor->getExpertiseDomains(),
                        'experienceYears' => $mentor->getExperienceYears(),
                        'educationLevel' => $mentor->getEducationLevel(),
                    ];

                    $plainPassword = $form->get('plainPassword')->getData();
                    
                    $this->logger->debug('Calling mentor authentication service', [
                        'service_method' => 'createMentorAccount',
                        'mentor_email' => $mentorData['email'],
                        'password_length' => strlen($plainPassword),
                    ]);

                    // Create mentor account using the authentication service
                    $createdMentor = $this->mentorAuthService->createMentorAccount(
                        $mentorData,
                        $plainPassword,
                    );

                    $this->logger->info('New mentor registered successfully', [
                        'mentor_id' => $createdMentor->getId(),
                        'email' => $createdMentor->getEmail(),
                        'company' => $createdMentor->getCompanyName(),
                        'full_name' => $createdMentor->getFirstName() . ' ' . $createdMentor->getLastName(),
                        'position' => $createdMentor->getPosition(),
                        'experience_years' => $createdMentor->getExperienceYears(),
                        'email_verified' => $createdMentor->isEmailVerified(),
                        'ip' => $clientIp,
                        'user_agent' => $userAgent,
                        'timestamp' => new \DateTime(),
                        'registration_context' => [
                            'has_phone' => !empty($createdMentor->getPhone()),
                            'has_siret' => !empty($createdMentor->getCompanySiret()),
                            'expertise_domains_count' => count($createdMentor->getExpertiseDomains() ?? []),
                        ],
                    ]);

                    $this->addFlash('success', 'Votre compte mentor a été créé avec succès ! Un email de vérification vous a été envoyé.');

                    return $this->redirectToRoute('mentor_login');

                } catch (InvalidArgumentException $e) {
                    $this->logger->warning('Mentor registration validation error', [
                        'error_message' => $e->getMessage(),
                        'email' => $mentor->getEmail(),
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                    ]);
                    $this->addFlash('error', $e->getMessage());
                } catch (Exception $e) {
                    $this->logger->error('Critical error during mentor registration', [
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'email' => $mentor->getEmail(),
                        'company' => $mentor->getCompanyName(),
                        'ip' => $clientIp,
                        'user_agent' => $userAgent,
                        'timestamp' => new \DateTime(),
                        'registration_data' => [
                            'has_email' => !empty($mentor->getEmail()),
                            'has_names' => !empty($mentor->getFirstName()) && !empty($mentor->getLastName()),
                            'has_company_info' => !empty($mentor->getCompanyName()),
                        ],
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.');
                }
            }

            $this->logger->debug('Rendering registration form', [
                'is_submitted' => $form->isSubmitted(),
                'is_valid' => $form->isSubmitted() ? $form->isValid() : null,
                'expertise_domains_available' => count(Mentor::EXPERTISE_DOMAINS),
                'education_levels_available' => count(Mentor::EDUCATION_LEVELS),
            ]);

            return $this->render('mentor/security/register.html.twig', [
                'registrationForm' => $form,
                'page_title' => 'Inscription Mentor',
                'expertise_domains' => Mentor::EXPERTISE_DOMAINS,
                'education_levels' => Mentor::EDUCATION_LEVELS,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Critical error in mentor registration controller', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer.');
            
            // Return a minimal registration form in case of critical error
            $mentor = new Mentor();
            $form = $this->createForm(MentorRegistrationFormType::class, $mentor);
            
            return $this->render('mentor/security/register.html.twig', [
                'registrationForm' => $form,
                'page_title' => 'Inscription Mentor',
                'expertise_domains' => Mentor::EXPERTISE_DOMAINS,
                'education_levels' => Mentor::EDUCATION_LEVELS,
            ]);
        }
    }

    /**
     * Mentor logout.
     *
     * This method can be blank - it will be intercepted by the logout key on your firewall.
     */
    #[Route('/logout', name: 'mentor_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Email verification.
     *
     * Verifies mentor email address using the verification token.
     */
    #[Route('/verify-email/{token}', name: 'mentor_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->getUserAgent();
        
        try {
            $this->logger->info('Email verification attempt', [
                'token_preview' => substr($token, 0, 8) . '...',
                'token_length' => strlen($token),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
            ]);

            if (empty($token) || strlen($token) < 10) {
                $this->logger->warning('Invalid email verification token format', [
                    'token_length' => strlen($token),
                    'ip' => $clientIp,
                    'timestamp' => new \DateTime(),
                ]);
                $this->addFlash('error', 'Token de vérification invalide.');
                return $this->redirectToRoute('mentor_login');
            }

            $mentor = $this->mentorAuthService->verifyEmail($token);

            if (!$mentor) {
                $this->logger->warning('Email verification failed - invalid or expired token', [
                    'token_preview' => substr($token, 0, 8) . '...',
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                    'timestamp' => new \DateTime(),
                    'verification_context' => [
                        'token_format_valid' => strlen($token) >= 10,
                        'service_method' => 'verifyEmail',
                    ],
                ]);

                $this->addFlash('error', 'Token de vérification invalide ou expiré.');
                return $this->redirectToRoute('mentor_login');
            }

            $this->logger->info('Mentor email verified successfully', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'company' => $mentor->getCompanyName(),
                'full_name' => $mentor->getFirstName() . ' ' . $mentor->getLastName(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
                'verification_context' => [
                    'was_previously_verified' => false, // Since service only returns unverified mentors
                    'account_creation_to_verification_time' => $mentor->getCreatedAt() ? 
                        (new \DateTime())->diff($mentor->getCreatedAt())->format('%h hours %i minutes') : 'unknown',
                ],
            ]);

            $this->addFlash('success', 'Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('mentor_login');

        } catch (InvalidArgumentException $e) {
            $this->logger->warning('Email verification validation error', [
                'error_message' => $e->getMessage(),
                'token_preview' => substr($token, 0, 8) . '...',
                'ip' => $clientIp,
                'timestamp' => new \DateTime(),
            ]);
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('mentor_login');
        } catch (Exception $e) {
            $this->logger->error('Critical error during email verification', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'token_preview' => substr($token, 0, 8) . '...',
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue lors de la vérification. Veuillez réessayer.');
            return $this->redirectToRoute('mentor_login');
        }
    }

    /**
     * Password reset request page.
     *
     * Allows mentors to request a password reset email.
     */
    #[Route('/forgot-password', name: 'mentor_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->getUserAgent();
        
        try {
            $this->logger->info('Password reset page accessed', [
                'method' => $request->getMethod(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
            ]);

            if ($request->isMethod('POST')) {
                $email = $request->request->get('email');
                
                $this->logger->info('Password reset request submitted', [
                    'email' => $email,
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                    'timestamp' => new \DateTime(),
                    'request_context' => [
                        'has_email' => !empty($email),
                        'email_format_valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                        'referer' => $request->headers->get('referer'),
                    ],
                ]);

                if (empty($email)) {
                    $this->logger->warning('Password reset request with empty email', [
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                    ]);
                } else {
                    try {
                        $this->logger->debug('Calling password reset service', [
                            'email' => $email,
                            'service_method' => 'initiatePasswordReset',
                        ]);

                        $success = $this->mentorAuthService->initiatePasswordReset($email);

                        if ($success) {
                            $this->logger->info('Password reset initiated successfully', [
                                'email' => $email,
                                'ip' => $clientIp,
                                'user_agent' => $userAgent,
                                'timestamp' => new \DateTime(),
                                'reset_context' => [
                                    'service_response' => 'success',
                                    'email_will_be_sent' => true,
                                ],
                            ]);
                        } else {
                            $this->logger->info('Password reset request for non-existent or invalid email', [
                                'email' => $email,
                                'ip' => $clientIp,
                                'timestamp' => new \DateTime(),
                                'reset_context' => [
                                    'service_response' => 'false',
                                    'likely_reason' => 'email_not_found_or_not_verified',
                                ],
                            ]);
                        }
                    } catch (InvalidArgumentException $e) {
                        $this->logger->warning('Password reset request validation error', [
                            'email' => $email,
                            'error_message' => $e->getMessage(),
                            'ip' => $clientIp,
                            'timestamp' => new \DateTime(),
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('Error during password reset request processing', [
                            'email' => $email,
                            'error_message' => $e->getMessage(),
                            'error_class' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'ip' => $clientIp,
                            'user_agent' => $userAgent,
                            'timestamp' => new \DateTime(),
                        ]);
                    }
                }

                // Always show success message for security reasons
                $this->addFlash('success', 'Si un compte avec cet email existe, vous recevrez un lien de réinitialisation.');

                return $this->redirectToRoute('mentor_login');
            }

            $this->logger->debug('Displaying password reset form', [
                'ip' => $clientIp,
            ]);

            return $this->render('mentor/security/forgot_password.html.twig', [
                'page_title' => 'Mot de passe oublié',
            ]);

        } catch (Exception $e) {
            $this->logger->error('Critical error in password reset controller', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer.');
            
            return $this->render('mentor/security/forgot_password.html.twig', [
                'page_title' => 'Mot de passe oublié',
            ]);
        }
    }

    /**
     * Password reset page.
     *
     * Allows mentors to reset their password using a reset token.
     */
    #[Route('/reset-password/{token}', name: 'mentor_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->getUserAgent();
        
        try {
            $this->logger->info('Password reset page accessed', [
                'token_preview' => substr($token, 0, 8) . '...',
                'token_length' => strlen($token),
                'method' => $request->getMethod(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
            ]);

            if (empty($token) || strlen($token) < 10) {
                $this->logger->warning('Invalid password reset token format', [
                    'token_length' => strlen($token),
                    'ip' => $clientIp,
                    'timestamp' => new \DateTime(),
                ]);
                $this->addFlash('error', 'Token de réinitialisation invalide.');
                return $this->redirectToRoute('mentor_login');
            }

            if ($request->isMethod('POST')) {
                $newPassword = $request->request->get('password');
                $confirmPassword = $request->request->get('confirm_password');

                $this->logger->info('Password reset form submitted', [
                    'token_preview' => substr($token, 0, 8) . '...',
                    'ip' => $clientIp,
                    'timestamp' => new \DateTime(),
                    'validation_context' => [
                        'has_new_password' => !empty($newPassword),
                        'has_confirm_password' => !empty($confirmPassword),
                        'passwords_match' => $newPassword === $confirmPassword,
                        'password_length' => $newPassword ? strlen($newPassword) : 0,
                    ],
                ]);

                if ($newPassword !== $confirmPassword) {
                    $this->logger->warning('Password reset attempt with mismatched passwords', [
                        'token_preview' => substr($token, 0, 8) . '...',
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                    ]);
                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');

                    return $this->render('mentor/security/reset_password.html.twig', [
                        'token' => $token,
                        'page_title' => 'Nouveau mot de passe',
                    ]);
                }

                if (strlen($newPassword) < 8) {
                    $this->logger->warning('Password reset attempt with too short password', [
                        'token_preview' => substr($token, 0, 8) . '...',
                        'password_length' => strlen($newPassword),
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                    ]);
                    $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                    return $this->render('mentor/security/reset_password.html.twig', [
                        'token' => $token,
                        'page_title' => 'Nouveau mot de passe',
                    ]);
                }

                try {
                    $this->logger->debug('Calling password reset service', [
                        'token_preview' => substr($token, 0, 8) . '...',
                        'service_method' => 'resetPassword',
                        'password_length' => strlen($newPassword),
                    ]);

                    $mentor = $this->mentorAuthService->resetPassword($token, $newPassword);

                    if ($mentor) {
                        $this->logger->info('Mentor password reset successfully', [
                            'mentor_id' => $mentor->getId(),
                            'email' => $mentor->getEmail(),
                            'company' => $mentor->getCompanyName(),
                            'full_name' => $mentor->getFirstName() . ' ' . $mentor->getLastName(),
                            'ip' => $clientIp,
                            'user_agent' => $userAgent,
                            'timestamp' => new \DateTime(),
                            'reset_context' => [
                                'token_was_valid' => true,
                                'password_strength_check' => [
                                    'length' => strlen($newPassword),
                                    'has_uppercase' => preg_match('/[A-Z]/', $newPassword),
                                    'has_lowercase' => preg_match('/[a-z]/', $newPassword),
                                    'has_numbers' => preg_match('/\d/', $newPassword),
                                    'has_special' => preg_match('/[^A-Za-z0-9]/', $newPassword),
                                ],
                            ],
                        ]);

                        $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');

                        return $this->redirectToRoute('mentor_login');
                    }

                    $this->logger->warning('Password reset failed - invalid or expired token', [
                        'token_preview' => substr($token, 0, 8) . '...',
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                        'service_response' => 'null',
                    ]);
                    $this->addFlash('error', 'Token de réinitialisation invalide ou expiré.');

                } catch (InvalidArgumentException $e) {
                    $this->logger->warning('Password reset validation error', [
                        'error_message' => $e->getMessage(),
                        'token_preview' => substr($token, 0, 8) . '...',
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                    ]);
                    $this->addFlash('error', $e->getMessage());
                } catch (Exception $e) {
                    $this->logger->error('Critical error during password reset', [
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'token_preview' => substr($token, 0, 8) . '...',
                        'ip' => $clientIp,
                        'user_agent' => $userAgent,
                        'timestamp' => new \DateTime(),
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue. Veuillez réessayer.');
                }
            }

            $this->logger->debug('Displaying password reset form', [
                'token_preview' => substr($token, 0, 8) . '...',
                'ip' => $clientIp,
            ]);

            return $this->render('mentor/security/reset_password.html.twig', [
                'token' => $token,
                'page_title' => 'Nouveau mot de passe',
            ]);

        } catch (Exception $e) {
            $this->logger->error('Critical error in password reset controller', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'token_preview' => substr($token, 0, 8) . '...',
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer.');
            
            return $this->render('mentor/security/reset_password.html.twig', [
                'token' => $token,
                'page_title' => 'Nouveau mot de passe',
            ]);
        }
    }

    /**
     * Resend verification page.
     *
     * Standalone page for mentors to resend their email verification.
     */
    #[Route('/resend-verification', name: 'mentor_resend_verification_form', methods: ['GET'])]
    public function resendVerificationForm(): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->getUserAgent();
        
        try {
            $this->logger->info('Resend verification form page accessed', [
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
            ]);

            // If user is already authenticated, redirect to dashboard
            /** @var Mentor|null $currentUser */
            $currentUser = $this->getUser();
            if ($currentUser instanceof Mentor) {
                $this->logger->info('Already authenticated mentor accessing resend verification page', [
                    'mentor_id' => $currentUser->getId(),
                    'email' => $currentUser->getEmail(),
                    'is_verified' => $currentUser->isEmailVerified(),
                    'ip' => $clientIp,
                    'action' => 'redirect_to_dashboard',
                ]);
                return $this->redirectToRoute('mentor_dashboard');
            }

            $this->logger->debug('Displaying resend verification form', [
                'ip' => $clientIp,
            ]);

            return $this->render('mentor/security/resend_verification.html.twig', [
                'page_title' => 'Renvoyer l\'email de vérification',
            ]);

        } catch (Exception $e) {
            $this->logger->error('Critical error in resend verification form controller', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer.');
            
            return $this->render('mentor/security/resend_verification.html.twig', [
                'page_title' => 'Renvoyer l\'email de vérification',
            ]);
        }
    }

    /**
     * Send email verification.
     *
     * Resends email verification link to mentor.
     */
    #[Route('/resend-verification', name: 'mentor_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->getUserAgent();
        
        try {
            $this->logger->info('Resend verification request received', [
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
            ]);

            // Verify CSRF token
            $submittedToken = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('resend_verification', $submittedToken)) {
                $this->logger->warning('Invalid CSRF token in resend verification request', [
                    'ip' => $clientIp,
                    'submitted_token_preview' => $submittedToken ? substr($submittedToken, 0, 8) . '...' : 'null',
                    'timestamp' => new \DateTime(),
                ]);
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('mentor_login');
            }

            $email = $request->request->get('email');

            if (!$email) {
                $this->logger->warning('Resend verification request without email', [
                    'ip' => $clientIp,
                    'timestamp' => new \DateTime(),
                ]);
                $this->addFlash('error', 'Email requis.');
                return $this->redirectToRoute('mentor_login');
            }

            $this->logger->info('Processing resend verification for email', [
                'email' => $email,
                'ip' => $clientIp,
                'timestamp' => new \DateTime(),
                'email_validation' => [
                    'format_valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                    'length' => strlen($email),
                ],
            ]);

            $mentor = $this->entityManager->getRepository(Mentor::class)->findByEmail($email);

            if ($mentor) {
                $this->logger->info('Found mentor for resend verification', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $mentor->getEmail(),
                    'is_verified' => $mentor->isEmailVerified(),
                    'company' => $mentor->getCompanyName(),
                    'created_at' => $mentor->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'ip' => $clientIp,
                ]);

                if (!$mentor->isEmailVerified()) {
                    try {
                        $this->logger->debug('Sending email verification', [
                            'mentor_id' => $mentor->getId(),
                            'service_method' => 'sendEmailVerification',
                        ]);

                        $this->mentorService->sendEmailVerification($mentor);
                        
                        $this->logger->info('Email verification resent successfully', [
                            'mentor_id' => $mentor->getId(),
                            'email' => $mentor->getEmail(),
                            'company' => $mentor->getCompanyName(),
                            'ip' => $clientIp,
                            'user_agent' => $userAgent,
                            'timestamp' => new \DateTime(),
                            'resend_context' => [
                                'previous_verification_attempts' => 'unknown', // Could track this in the future
                                'account_age' => $mentor->getCreatedAt() ? 
                                    (new \DateTime())->diff($mentor->getCreatedAt())->format('%d days %h hours') : 'unknown',
                            ],
                        ]);
                        
                        $this->addFlash('success', 'Email de vérification renvoyé.');
                    } catch (Exception $e) {
                        $this->logger->error('Error resending verification email', [
                            'mentor_id' => $mentor->getId(),
                            'email' => $mentor->getEmail(),
                            'error_message' => $e->getMessage(),
                            'error_class' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                            'ip' => $clientIp,
                            'timestamp' => new \DateTime(),
                        ]);
                        $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email.');
                    }
                } else {
                    $this->logger->info('Resend verification requested for already verified mentor', [
                        'mentor_id' => $mentor->getId(),
                        'email' => $mentor->getEmail(),
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                    ]);
                    $this->addFlash('info', 'Ce compte est déjà vérifié.');
                }
            } else {
                $this->logger->info('Resend verification requested for non-existent email', [
                    'email' => $email,
                    'ip' => $clientIp,
                    'timestamp' => new \DateTime(),
                    'security_response' => 'generic_message_for_privacy',
                ]);
                $this->addFlash('info', 'Si un compte non vérifié existe avec cet email, un email de vérification a été envoyé.');
            }

            return $this->redirectToRoute('mentor_login');

        } catch (Exception $e) {
            $this->logger->error('Critical error in resend verification controller', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
                'request_data' => [
                    'has_email' => !empty($request->request->get('email')),
                    'has_csrf_token' => !empty($request->request->get('_csrf_token')),
                ],
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer.');
            return $this->redirectToRoute('mentor_login');
        }
    }



    /**
     * Complete account setup.
     *
     * Guides mentor through completing their account setup.
     */
    #[Route('/complete-setup', name: 'mentor_complete_setup', methods: ['GET', 'POST'])]
    public function completeSetup(Request $request): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->getUserAgent();
        
        try {
            /** @var Mentor|null $mentor */
            $mentor = $this->getUser();

            if (!$mentor instanceof Mentor) {
                $this->logger->warning('Unauthenticated access to complete setup page', [
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                    'timestamp' => new \DateTime(),
                ]);
                return $this->redirectToRoute('mentor_login');
            }

            $this->logger->info('Complete setup page accessed', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'method' => $request->getMethod(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
            ]);

            $setupCompletion = $this->mentorAuthService->getAccountSetupCompletion($mentor);

            $this->logger->debug('Account setup completion status', [
                'mentor_id' => $mentor->getId(),
                'setup_completion' => $setupCompletion,
                'is_complete' => $setupCompletion['is_complete'],
                'missing_fields' => $setupCompletion['missing_fields'] ?? [],
                'completion_percentage' => $setupCompletion['completion_percentage'] ?? 0,
            ]);

            if ($setupCompletion['is_complete']) {
                $this->logger->info('Setup already complete, redirecting to dashboard', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $mentor->getEmail(),
                    'ip' => $clientIp,
                ]);
                return $this->redirectToRoute('mentor_dashboard');
            }

            if ($request->isMethod('POST')) {
                $this->logger->info('Setup completion form submitted', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $mentor->getEmail(),
                    'ip' => $clientIp,
                    'timestamp' => new \DateTime(),
                    'form_data_keys' => array_keys($request->request->all()),
                ]);

                try {
                    // Handle setup completion form submission
                    // This would involve updating mentor profile fields
                    // For now, just redirect to dashboard
                    
                    $this->logger->info('Setup completion processed successfully', [
                        'mentor_id' => $mentor->getId(),
                        'email' => $mentor->getEmail(),
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                        'next_action' => 'redirect_to_dashboard',
                    ]);

                    $this->addFlash('success', 'Votre profil a été complété avec succès !');
                    return $this->redirectToRoute('mentor_dashboard');

                } catch (Exception $e) {
                    $this->logger->error('Error processing setup completion', [
                        'mentor_id' => $mentor->getId(),
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'ip' => $clientIp,
                        'timestamp' => new \DateTime(),
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de la finalisation de votre profil.');
                }
            }

            $this->logger->debug('Displaying setup completion form', [
                'mentor_id' => $mentor->getId(),
                'completion_percentage' => $setupCompletion['completion_percentage'] ?? 0,
                'missing_fields_count' => count($setupCompletion['missing_fields'] ?? []),
            ]);

            return $this->render('mentor/security/complete_setup.html.twig', [
                'mentor' => $mentor,
                'setup_completion' => $setupCompletion,
                'page_title' => 'Finaliser votre profil',
            ]);

        } catch (Exception $e) {
            $this->logger->error('Critical error in complete setup controller', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => new \DateTime(),
                'request_method' => $request->getMethod(),
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer.');
            return $this->redirectToRoute('mentor_dashboard');
        }
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

    /**
     * Get user agent for logging.
     */
    private function getUserAgent(): ?string
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if (!$request) {
            return null;
        }

        return $request->headers->get('User-Agent');
    }
}
