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
        // If user is already authenticated, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('mentor_dashboard');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error) {
            $this->logger->warning('Mentor login failed', [
                'username' => $lastUsername,
                'error' => $error->getMessage(),
                'ip' => $this->getClientIp(),
            ]);
        }

        return $this->render('mentor/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'page_title' => 'Connexion Mentor',
        ]);
    }

    /**
     * Mentor registration page.
     *
     * Allows new mentors to create an account.
     */
    #[Route('/register', name: 'mentor_register', methods: ['GET', 'POST'])]
    public function register(Request $request, MentorRepository $mentorRepository): Response
    {
        // If user is already authenticated, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('mentor_dashboard');
        }

        $mentor = new Mentor();
        $form = $this->createForm(MentorRegistrationFormType::class, $mentor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
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

                // Create mentor account using the authentication service
                $createdMentor = $this->mentorAuthService->createMentorAccount(
                    $mentorData,
                    $form->get('plainPassword')->getData(),
                );

                $this->logger->info('New mentor registered', [
                    'mentor_id' => $createdMentor->getId(),
                    'email' => $createdMentor->getEmail(),
                    'company' => $createdMentor->getCompanyName(),
                    'ip' => $this->getClientIp(),
                ]);

                $this->addFlash('success', 'Votre compte mentor a été créé avec succès ! Un email de vérification vous a été envoyé.');

                return $this->redirectToRoute('mentor_login');
            } catch (InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (Exception $e) {
                $this->logger->error('Error during mentor registration', [
                    'error' => $e->getMessage(),
                    'ip' => $this->getClientIp(),
                ]);
                $this->addFlash('error', 'Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.');
            }
        }

        return $this->render('mentor/security/register.html.twig', [
            'registrationForm' => $form,
            'page_title' => 'Inscription Mentor',
            'expertise_domains' => Mentor::EXPERTISE_DOMAINS,
            'education_levels' => Mentor::EDUCATION_LEVELS,
        ]);
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
        $mentor = $this->mentorAuthService->verifyEmail($token);

        if (!$mentor) {
            $this->addFlash('error', 'Token de vérification invalide ou expiré.');

            return $this->redirectToRoute('mentor_login');
        }

        $this->logger->info('Mentor email verified', [
            'mentor_id' => $mentor->getId(),
            'email' => $mentor->getEmail(),
        ]);

        $this->addFlash('success', 'Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('mentor_login');
    }

    /**
     * Password reset request page.
     *
     * Allows mentors to request a password reset email.
     */
    #[Route('/forgot-password', name: 'mentor_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            try {
                $success = $this->mentorAuthService->initiatePasswordReset($email);

                if ($success) {
                    $this->logger->info('Password reset requested for mentor', [
                        'email' => $email,
                        'ip' => $this->getClientIp(),
                    ]);
                }
            } catch (Exception $e) {
                $this->logger->error('Error during password reset request', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'ip' => $this->getClientIp(),
                ]);
            }

            // Always show success message for security reasons
            $this->addFlash('success', 'Si un compte avec cet email existe, vous recevrez un lien de réinitialisation.');

            return $this->redirectToRoute('mentor_login');
        }

        return $this->render('mentor/security/forgot_password.html.twig', [
            'page_title' => 'Mot de passe oublié',
        ]);
    }

    /**
     * Password reset page.
     *
     * Allows mentors to reset their password using a reset token.
     */
    #[Route('/reset-password/{token}', name: 'mentor_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');

                return $this->render('mentor/security/reset_password.html.twig', [
                    'token' => $token,
                    'page_title' => 'Nouveau mot de passe',
                ]);
            }

            if (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                return $this->render('mentor/security/reset_password.html.twig', [
                    'token' => $token,
                    'page_title' => 'Nouveau mot de passe',
                ]);
            }

            try {
                $mentor = $this->mentorAuthService->resetPassword($token, $newPassword);

                if ($mentor) {
                    $this->logger->info('Mentor password reset', [
                        'mentor_id' => $mentor->getId(),
                        'email' => $mentor->getEmail(),
                        'ip' => $this->getClientIp(),
                    ]);

                    $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');

                    return $this->redirectToRoute('mentor_login');
                }
                $this->addFlash('error', 'Token de réinitialisation invalide ou expiré.');
            } catch (InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (Exception $e) {
                $this->logger->error('Error during password reset', [
                    'token' => substr($token, 0, 8) . '...',
                    'error' => $e->getMessage(),
                    'ip' => $this->getClientIp(),
                ]);
                $this->addFlash('error', 'Une erreur est survenue. Veuillez réessayer.');
            }
        }

        return $this->render('mentor/security/reset_password.html.twig', [
            'token' => $token,
            'page_title' => 'Nouveau mot de passe',
        ]);
    }

    /**
     * Send email verification.
     *
     * Resends email verification link to mentor.
     */
    #[Route('/resend-verification', name: 'mentor_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): Response
    {
        $email = $request->request->get('email');

        if (!$email) {
            $this->addFlash('error', 'Email requis.');

            return $this->redirectToRoute('mentor_login');
        }

        $mentor = $this->entityManager->getRepository(Mentor::class)->findByEmail($email);

        if ($mentor && !$mentor->isEmailVerified()) {
            try {
                $this->mentorService->sendEmailVerification($mentor);
                $this->addFlash('success', 'Email de vérification renvoyé.');
            } catch (Exception $e) {
                $this->logger->error('Error resending verification email', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email.');
            }
        } else {
            $this->addFlash('info', 'Si un compte non vérifié existe avec cet email, un email de vérification a été envoyé.');
        }

        return $this->redirectToRoute('mentor_login');
    }

    /**
     * Account setup completion check.
     *
     * Checks if mentor account setup is complete and redirects accordingly.
     */
    #[Route('/setup-check', name: 'mentor_setup_check', methods: ['GET'])]
    public function setupCheck(): Response
    {
        /** @var Mentor|null $mentor */
        $mentor = $this->getUser();

        if (!$mentor) {
            return $this->redirectToRoute('mentor_login');
        }

        $setupCompletion = $this->mentorAuthService->getAccountSetupCompletion($mentor);

        if (!$setupCompletion['is_complete']) {
            return $this->redirectToRoute('mentor_complete_setup');
        }

        return $this->redirectToRoute('mentor_dashboard');
    }

    /**
     * Complete account setup.
     *
     * Guides mentor through completing their account setup.
     */
    #[Route('/complete-setup', name: 'mentor_complete_setup', methods: ['GET', 'POST'])]
    public function completeSetup(Request $request): Response
    {
        /** @var Mentor|null $mentor */
        $mentor = $this->getUser();

        if (!$mentor) {
            return $this->redirectToRoute('mentor_login');
        }

        $setupCompletion = $this->mentorAuthService->getAccountSetupCompletion($mentor);

        if ($setupCompletion['is_complete']) {
            return $this->redirectToRoute('mentor_dashboard');
        }

        if ($request->isMethod('POST')) {
            // Handle setup completion form submission
            // This would involve updating mentor profile fields
            // For now, just redirect to dashboard
            return $this->redirectToRoute('mentor_dashboard');
        }

        return $this->render('mentor/security/complete_setup.html.twig', [
            'mentor' => $mentor,
            'setup_completion' => $setupCompletion,
            'page_title' => 'Finaliser votre profil',
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
