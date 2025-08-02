<?php

declare(strict_types=1);

namespace App\Controller\Teacher;

use App\Entity\User\Teacher;
use App\Form\User\TeacherRegistrationFormType;
use App\Repository\User\TeacherRepository;
use App\Service\User\TeacherService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Teacher Security Controller.
 *
 * Handles authentication, registration, and password management for teachers.
 * Provides login, registration, and password reset functionality.
 */
#[Route('/teacher')]
class SecurityController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private TeacherService $teacherService,
    ) {}

    /**
     * Teacher login page.
     *
     * Displays the login form for teacher users.
     */
    #[Route('/login', name: 'teacher_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent', 'Unknown');

        try {
            $this->logger->info('Teacher login attempt initiated', [
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            // If user is already authenticated, redirect to dashboard
            if ($this->getUser()) {
                /** @var Teacher $teacher */
                $teacher = $this->getUser();
                $this->logger->info('Teacher already authenticated, redirecting to dashboard', [
                    'teacher_id' => $teacher->getId(),
                    'email' => $teacher->getEmail(),
                    'ip' => $clientIp,
                    'redirect_target' => 'teacher_dashboard',
                ]);

                return $this->redirectToRoute('teacher_dashboard');
            }

            // Get the login error if there is one
            $error = $authenticationUtils->getLastAuthenticationError();

            // Last username entered by the user
            $lastUsername = $authenticationUtils->getLastUsername();

            if ($error) {
                $this->logger->warning('Teacher login authentication failed', [
                    'username' => $lastUsername,
                    'error_message' => $error->getMessage(),
                    'error_type' => get_class($error),
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                ]);
            } else {
                $this->logger->debug('Teacher login page displayed', [
                    'last_username' => $lastUsername,
                    'ip' => $clientIp,
                    'has_previous_attempt' => !empty($lastUsername),
                ]);
            }

            $this->logger->info('Teacher login page rendered successfully', [
                'last_username' => $lastUsername,
                'has_error' => $error !== null,
                'ip' => $clientIp,
            ]);

            return $this->render('teacher/security/login.html.twig', [
                'last_username' => $lastUsername,
                'error' => $error,
                'page_title' => 'Connexion Formateur',
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Critical error in teacher login process', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer plus tard.');

            return $this->render('teacher/security/login.html.twig', [
                'last_username' => '',
                'error' => null,
                'page_title' => 'Connexion Formateur',
            ]);
        }
    }

    /**
     * Teacher registration page.
     *
     * Allows new teachers to create an account.
     */
    #[Route('/register', name: 'teacher_register', methods: ['GET', 'POST'])]
    public function register(Request $request, TeacherRepository $teacherRepository): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $request->headers->get('User-Agent', 'Unknown');

        try {
            $this->logger->info('Teacher registration process initiated', [
                'method' => $request->getMethod(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            // If user is already authenticated, redirect to dashboard
            if ($this->getUser()) {
                /** @var Teacher $teacher */
                $teacher = $this->getUser();
                $this->logger->info('Authenticated teacher attempted registration, redirecting', [
                    'teacher_id' => $teacher->getId(),
                    'email' => $teacher->getEmail(),
                    'ip' => $clientIp,
                    'redirect_target' => 'teacher_dashboard',
                ]);

                return $this->redirectToRoute('teacher_dashboard');
            }

            $teacher = new Teacher();
            $form = $this->createForm(TeacherRegistrationFormType::class, $teacher);

            $this->logger->debug('Teacher registration form created', [
                'form_class' => TeacherRegistrationFormType::class,
                'ip' => $clientIp,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Teacher registration form submitted', [
                    'form_valid' => $form->isValid(),
                    'ip' => $clientIp,
                    'submitted_email' => $teacher->getEmail(),
                    'form_errors_count' => count($form->getErrors(true)),
                ]);

                if ($form->isValid()) {
                    // Check if email already exists
                    $existingTeacher = $teacherRepository->findByEmail($teacher->getEmail());
                    if ($existingTeacher) {
                        $this->logger->warning('Teacher registration attempted with existing email', [
                            'email' => $teacher->getEmail(),
                            'existing_teacher_id' => $existingTeacher->getId(),
                            'ip' => $clientIp,
                            'user_agent' => $userAgent,
                        ]);

                        $this->addFlash('error', 'Un compte avec cette adresse email existe déjà.');

                        return $this->render('teacher/security/register.html.twig', [
                            'registrationForm' => $form,
                            'page_title' => 'Inscription Formateur',
                        ]);
                    }

                    // Hash the password
                    $plainPassword = $form->get('plainPassword')->getData();
                    $hashedPassword = $this->passwordHasher->hashPassword($teacher, $plainPassword);
                    $teacher->setPassword($hashedPassword);

                    $this->logger->debug('Teacher password hashed successfully', [
                        'email' => $teacher->getEmail(),
                        'password_length' => strlen($plainPassword),
                        'hash_algorithm' => 'bcrypt',
                    ]);

                    // Generate email verification token
                    $teacher->generateEmailVerificationToken();

                    $this->logger->debug('Email verification token generated', [
                        'email' => $teacher->getEmail(),
                        'token_generated' => !empty($teacher->getEmailVerificationToken()),
                    ]);

                    // Save the teacher
                    $this->entityManager->persist($teacher);
                    $this->entityManager->flush();

                    $this->logger->info('New teacher registered successfully', [
                        'teacher_id' => $teacher->getId(),
                        'email' => $teacher->getEmail(),
                        'first_name' => $teacher->getFirstName(),
                        'last_name' => $teacher->getLastName(),
                        'specialty' => $teacher->getSpecialty(),
                        'ip' => $clientIp,
                        'user_agent' => $userAgent,
                        'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                    ]);

                    // Send email verification email
                    try {
                        $emailSent = $this->teacherService->sendEmailVerification($teacher);

                        $this->logger->info('Email verification sending attempted', [
                            'teacher_id' => $teacher->getId(),
                            'email' => $teacher->getEmail(),
                            'email_sent' => $emailSent,
                            'service_class' => get_class($this->teacherService),
                        ]);

                        if ($emailSent) {
                            $this->addFlash('success', 'Votre compte formateur a été créé avec succès ! Un email de vérification vous a été envoyé.');
                        } else {
                            $this->logger->warning('Email verification failed to send', [
                                'teacher_id' => $teacher->getId(),
                                'email' => $teacher->getEmail(),
                            ]);
                            $this->addFlash('warning', 'Votre compte formateur a été créé avec succès ! Cependant, nous n\'avons pas pu envoyer l\'email de vérification. Contactez le support si nécessaire.');
                        }
                    } catch (Exception $emailException) {
                        $this->logger->error('Exception occurred while sending verification email', [
                            'teacher_id' => $teacher->getId(),
                            'email' => $teacher->getEmail(),
                            'exception_message' => $emailException->getMessage(),
                            'exception_class' => get_class($emailException),
                            'file' => $emailException->getFile(),
                            'line' => $emailException->getLine(),
                        ]);

                        $this->addFlash('warning', 'Votre compte formateur a été créé avec succès ! Cependant, nous n\'avons pas pu envoyer l\'email de vérification. Contactez le support si nécessaire.');
                    }

                    $this->logger->info('Teacher registration completed, redirecting to login', [
                        'teacher_id' => $teacher->getId(),
                        'redirect_target' => 'teacher_login',
                    ]);

                    return $this->redirectToRoute('teacher_login');
                }
                $formErrors = [];
                foreach ($form->getErrors(true) as $error) {
                    $formErrors[] = $error->getMessage();
                }

                $this->logger->warning('Teacher registration form validation failed', [
                    'email' => $teacher->getEmail(),
                    'form_errors' => $formErrors,
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                ]);
            } else {
                $this->logger->debug('Teacher registration form displayed', [
                    'ip' => $clientIp,
                    'first_visit' => true,
                ]);
            }

            return $this->render('teacher/security/register.html.twig', [
                'registrationForm' => $form,
                'page_title' => 'Inscription Formateur',
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Critical error in teacher registration process', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue lors de l\'inscription. Veuillez réessayer plus tard.');

            $teacher = new Teacher();
            $form = $this->createForm(TeacherRegistrationFormType::class, $teacher);

            return $this->render('teacher/security/register.html.twig', [
                'registrationForm' => $form,
                'page_title' => 'Inscription Formateur',
            ]);
        }
    }

    /**
     * Teacher logout.
     *
     * This method can be blank - it will be intercepted by the logout key on your firewall.
     */
    #[Route('/logout', name: 'teacher_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Email verification.
     *
     * Verifies teacher email address using the verification token.
     */
    #[Route('/verify-email/{token}', name: 'teacher_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token, TeacherRepository $teacherRepository): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent', 'Unknown');

        try {
            $this->logger->info('Teacher email verification attempt initiated', [
                'token' => substr($token, 0, 8) . '...', // Log only first 8 chars for security
                'token_length' => strlen($token),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            $teacher = $teacherRepository->findByEmailVerificationToken($token);

            if (!$teacher) {
                $this->logger->warning('Email verification failed - invalid or expired token', [
                    'token' => substr($token, 0, 8) . '...',
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                    'reason' => 'teacher_not_found',
                ]);

                $this->addFlash('error', 'Token de vérification invalide ou expiré.');

                return $this->redirectToRoute('teacher_login');
            }

            $this->logger->info('Teacher found for email verification', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'already_verified' => $teacher->isEmailVerified(),
                'created_at' => $teacher->getCreatedAt()?->format('Y-m-d H:i:s'),
                'ip' => $clientIp,
            ]);

            // Check if already verified
            if ($teacher->isEmailVerified()) {
                $this->logger->info('Teacher email already verified', [
                    'teacher_id' => $teacher->getId(),
                    'email' => $teacher->getEmail(),
                    'verified_at' => $teacher->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
                    'ip' => $clientIp,
                ]);

                $this->addFlash('info', 'Votre email est déjà vérifié. Vous pouvez vous connecter.');

                return $this->redirectToRoute('teacher_login');
            }

            $teacher->verifyEmail();
            $this->entityManager->flush();

            $this->logger->info('Teacher email verified successfully', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'verified_at' => $teacher->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'time_from_registration' => $teacher->getCreatedAt() ?
                    (new DateTime())->getTimestamp() - $teacher->getCreatedAt()->getTimestamp() . ' seconds' : 'unknown',
            ]);

            $this->addFlash('success', 'Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('teacher_login');
        } catch (Exception $e) {
            $this->logger->critical('Critical error during teacher email verification', [
                'token' => substr($token, 0, 8) . '...',
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue lors de la vérification. Veuillez réessayer ou contacter le support.');

            return $this->redirectToRoute('teacher_login');
        }
    }

    /**
     * Password reset request page.
     *
     * Allows teachers to request a password reset email.
     */
    #[Route('/forgot-password', name: 'teacher_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, TeacherRepository $teacherRepository): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $request->headers->get('User-Agent', 'Unknown');

        try {
            $this->logger->info('Teacher forgot password process initiated', [
                'method' => $request->getMethod(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            if ($request->isMethod('POST')) {
                $email = $request->request->get('email');

                $this->logger->info('Password reset request submitted', [
                    'email' => $email,
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                ]);

                if (empty($email)) {
                    $this->logger->warning('Password reset request with empty email', [
                        'ip' => $clientIp,
                        'user_agent' => $userAgent,
                    ]);

                    $this->addFlash('error', 'Veuillez saisir votre adresse email.');

                    return $this->render('teacher/security/forgot_password.html.twig', [
                        'page_title' => 'Mot de passe oublié',
                    ]);
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->logger->warning('Password reset request with invalid email format', [
                        'invalid_email' => $email,
                        'ip' => $clientIp,
                        'user_agent' => $userAgent,
                    ]);

                    $this->addFlash('error', 'Veuillez saisir une adresse email valide.');

                    return $this->render('teacher/security/forgot_password.html.twig', [
                        'page_title' => 'Mot de passe oublié',
                    ]);
                }

                $teacher = $teacherRepository->findByEmail($email);

                if ($teacher && $teacher->isActive()) {
                    $this->logger->info('Valid teacher found for password reset', [
                        'teacher_id' => $teacher->getId(),
                        'email' => $teacher->getEmail(),
                        'is_active' => $teacher->isActive(),
                        'email_verified' => $teacher->isEmailVerified(),
                        'last_password_reset' => $teacher->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                        'ip' => $clientIp,
                    ]);

                    $teacher->generatePasswordResetToken();
                    $this->entityManager->flush();

                    $this->logger->info('Password reset token generated', [
                        'teacher_id' => $teacher->getId(),
                        'email' => $teacher->getEmail(),
                        'token_expires_at' => $teacher->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                        'ip' => $clientIp,
                    ]);

                    // Send password reset email
                    try {
                        $emailSent = $this->teacherService->sendPasswordResetEmail($teacher);

                        $this->logger->info('Password reset email sending attempted', [
                            'teacher_id' => $teacher->getId(),
                            'email' => $teacher->getEmail(),
                            'email_sent' => $emailSent,
                            'service_class' => get_class($this->teacherService),
                        ]);
                    } catch (Exception $emailException) {
                        $this->logger->error('Exception occurred while sending password reset email', [
                            'teacher_id' => $teacher->getId(),
                            'email' => $teacher->getEmail(),
                            'exception_message' => $emailException->getMessage(),
                            'exception_class' => get_class($emailException),
                            'file' => $emailException->getFile(),
                            'line' => $emailException->getLine(),
                        ]);
                    }
                } else {
                    $this->logger->warning('Password reset requested for non-existent or inactive teacher', [
                        'email' => $email,
                        'teacher_found' => $teacher !== null,
                        'teacher_active' => $teacher?->isActive(),
                        'ip' => $clientIp,
                        'user_agent' => $userAgent,
                    ]);
                }

                // Always show success message for security reasons (don't reveal if email exists)
                $this->logger->info('Password reset request completed with success message', [
                    'email' => $email,
                    'ip' => $clientIp,
                ]);

                $this->addFlash('success', 'Si un compte avec cet email existe, vous recevrez un lien de réinitialisation.');

                return $this->redirectToRoute('teacher_login');
            }

            $this->logger->debug('Forgot password form displayed', [
                'ip' => $clientIp,
            ]);

            return $this->render('teacher/security/forgot_password.html.twig', [
                'page_title' => 'Mot de passe oublié',
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Critical error in teacher forgot password process', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer plus tard.');

            return $this->render('teacher/security/forgot_password.html.twig', [
                'page_title' => 'Mot de passe oublié',
            ]);
        }
    }

    /**
     * Password reset page.
     *
     * Allows teachers to reset their password using a reset token.
     */
    #[Route('/reset-password/{token}', name: 'teacher_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request, TeacherRepository $teacherRepository): Response
    {
        $clientIp = $this->getClientIp();
        $userAgent = $request->headers->get('User-Agent', 'Unknown');

        try {
            $this->logger->info('Teacher password reset process initiated', [
                'token' => substr($token, 0, 8) . '...', // Log only first 8 chars for security
                'token_length' => strlen($token),
                'method' => $request->getMethod(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            $teacher = $teacherRepository->findByPasswordResetToken($token);

            if (!$teacher || !$teacher->isPasswordResetTokenValid()) {
                $invalidReason = 'teacher_not_found';
                if ($teacher && !$teacher->isPasswordResetTokenValid()) {
                    $invalidReason = 'token_expired';
                }

                $this->logger->warning('Password reset failed - invalid or expired token', [
                    'token' => substr($token, 0, 8) . '...',
                    'reason' => $invalidReason,
                    'teacher_found' => $teacher !== null,
                    'token_valid' => $teacher?->isPasswordResetTokenValid(),
                    'token_expires_at' => $teacher?->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                    'current_time' => (new DateTime())->format('Y-m-d H:i:s'),
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                ]);

                $this->addFlash('error', 'Token de réinitialisation invalide ou expiré.');

                return $this->redirectToRoute('teacher_login');
            }

            $this->logger->info('Valid teacher and token found for password reset', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'token_expires_at' => $teacher->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                'ip' => $clientIp,
            ]);

            if ($request->isMethod('POST')) {
                $newPassword = $request->request->get('password');
                $confirmPassword = $request->request->get('confirm_password');

                $this->logger->info('Password reset form submitted', [
                    'teacher_id' => $teacher->getId(),
                    'email' => $teacher->getEmail(),
                    'password_provided' => !empty($newPassword),
                    'confirm_password_provided' => !empty($confirmPassword),
                    'passwords_match' => $newPassword === $confirmPassword,
                    'password_length' => strlen($newPassword ?? ''),
                    'ip' => $clientIp,
                ]);

                if ($newPassword !== $confirmPassword) {
                    $this->logger->warning('Password reset failed - password confirmation mismatch', [
                        'teacher_id' => $teacher->getId(),
                        'email' => $teacher->getEmail(),
                        'ip' => $clientIp,
                    ]);

                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');

                    return $this->render('teacher/security/reset_password.html.twig', [
                        'token' => $token,
                        'page_title' => 'Nouveau mot de passe',
                    ]);
                }

                if (strlen($newPassword) < 6) {
                    $this->logger->warning('Password reset failed - password too short', [
                        'teacher_id' => $teacher->getId(),
                        'email' => $teacher->getEmail(),
                        'password_length' => strlen($newPassword),
                        'minimum_length' => 6,
                        'ip' => $clientIp,
                    ]);

                    $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');

                    return $this->render('teacher/security/reset_password.html.twig', [
                        'token' => $token,
                        'page_title' => 'Nouveau mot de passe',
                    ]);
                }

                // Additional password strength checks
                if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                    $this->logger->warning('Password reset failed - weak password', [
                        'teacher_id' => $teacher->getId(),
                        'email' => $teacher->getEmail(),
                        'has_letters' => preg_match('/[A-Za-z]/', $newPassword) > 0,
                        'has_numbers' => preg_match('/[0-9]/', $newPassword) > 0,
                        'ip' => $clientIp,
                    ]);

                    $this->addFlash('error', 'Le mot de passe doit contenir au moins une lettre et un chiffre.');

                    return $this->render('teacher/security/reset_password.html.twig', [
                        'token' => $token,
                        'page_title' => 'Nouveau mot de passe',
                    ]);
                }

                $hashedPassword = $this->passwordHasher->hashPassword($teacher, $newPassword);
                $teacher->setPassword($hashedPassword);
                $teacher->clearPasswordResetToken();

                $this->logger->debug('Password hashed and token cleared', [
                    'teacher_id' => $teacher->getId(),
                    'email' => $teacher->getEmail(),
                    'hash_algorithm' => 'bcrypt',
                ]);

                $this->entityManager->flush();

                $this->logger->info('Teacher password reset completed successfully', [
                    'teacher_id' => $teacher->getId(),
                    'email' => $teacher->getEmail(),
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                    'reset_at' => (new DateTime())->format('Y-m-d H:i:s'),
                ]);

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');

                return $this->redirectToRoute('teacher_login');
            }

            $this->logger->debug('Password reset form displayed', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'token_expires_at' => $teacher->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                'ip' => $clientIp,
            ]);

            return $this->render('teacher/security/reset_password.html.twig', [
                'token' => $token,
                'page_title' => 'Nouveau mot de passe',
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Critical error in teacher password reset process', [
                'token' => substr($token, 0, 8) . '...',
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue lors de la réinitialisation. Veuillez réessayer ou contacter le support.');

            return $this->redirectToRoute('teacher_login');
        }
    }

    /**
     * Get client IP address for logging.
     *
     * Safely retrieves the client's IP address, handling various proxy configurations.
     */
    private function getClientIp(): ?string
    {
        try {
            $request = $this->container->get('request_stack')->getCurrentRequest();

            if (!$request) {
                return null;
            }

            // Get the real IP, considering proxies and load balancers
            $ip = $request->getClientIp();

            // Log potential security concerns with IP detection
            $forwardedFor = $request->headers->get('X-Forwarded-For');
            $realIp = $request->headers->get('X-Real-IP');

            if ($forwardedFor || $realIp) {
                $this->logger->debug('IP detection with proxy headers', [
                    'detected_ip' => $ip,
                    'x_forwarded_for' => $forwardedFor,
                    'x_real_ip' => $realIp,
                    'remote_addr' => $request->server->get('REMOTE_ADDR'),
                ]);
            }

            return $ip;
        } catch (Exception $e) {
            $this->logger->warning('Failed to get client IP address', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            return 'unknown';
        }
    }

    /**
     * Get user agent for logging.
     */
    private function getUserAgent(): string
    {
        try {
            $request = $this->container->get('request_stack')->getCurrentRequest();

            return $request?->headers->get('User-Agent', 'Unknown') ?? 'Unknown';
        } catch (Exception $e) {
            $this->logger->debug('Failed to get user agent', [
                'exception_message' => $e->getMessage(),
            ]);

            return 'Unknown';
        }
    }
}
