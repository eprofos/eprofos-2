<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Entity\User\Student;
use App\Form\User\StudentRegistrationFormType;
use App\Repository\User\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\FormInterface;

/**
 * Student Security Controller.
 *
 * Handles authentication, registration, and password management for students.
 * Provides login, registration, and password reset functionality.
 */
#[Route('/student')]
class SecurityController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private RequestStack $requestStack,
    ) {}

    /**
     * Student login page.
     *
     * Displays the login form for student users.
     */
    #[Route('/login', name: 'student_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        try {
            $request = $this->requestStack->getCurrentRequest();
            
            $this->logger->info('Student login page accessed', [
                'ip' => $this->getClientIp(),
                'user_agent' => $request?->headers->get('User-Agent'),
                'method' => $request?->getMethod(),
            ]);

            // If user is already authenticated, redirect to dashboard
            if ($this->getUser()) {
                $user = $this->getUser();
                $this->logger->info('Already authenticated student redirected to dashboard', [
                    'user_id' => $user instanceof Student ? $user->getId() : 'unknown',
                    'email' => $user->getUserIdentifier(),
                    'ip' => $this->getClientIp(),
                ]);
                return $this->redirectToRoute('student_dashboard');
            }

            // Get the login error if there is one
            $error = $authenticationUtils->getLastAuthenticationError();

            // Last username entered by the user
            $lastUsername = $authenticationUtils->getLastUsername();

            if ($error) {
                $this->logger->warning('Student login authentication failed', [
                    'username' => $lastUsername,
                    'error_message' => $error->getMessage(),
                    'error_class' => get_class($error),
                    'ip' => $this->getClientIp(),
                    'user_agent' => $request?->headers->get('User-Agent'),
                    'timestamp' => new \DateTimeImmutable(),
                ]);
            } else {
                $this->logger->debug('Student login form displayed', [
                    'last_username' => $lastUsername,
                    'ip' => $this->getClientIp(),
                ]);
            }

            $this->logger->info('Student login page rendered successfully', [
                'has_error' => $error !== null,
                'last_username' => $lastUsername,
                'ip' => $this->getClientIp(),
            ]);

            return $this->render('student/security/login.html.twig', [
                'last_username' => $lastUsername,
                'error' => $error,
                'page_title' => 'Connexion Étudiant',
            ]);
        } catch (\Exception $e) {
            $request = $this->requestStack->getCurrentRequest();
            
            $this->logger->error('Exception occurred in student login', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'ip' => $this->getClientIp(),
                'user_agent' => $request?->headers->get('User-Agent'),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer.');
            
            // Fallback to basic login template
            return $this->render('student/security/login.html.twig', [
                'last_username' => '',
                'error' => null,
                'page_title' => 'Connexion Étudiant',
            ]);
        }
    }

    /**
     * Student registration page.
     *
     * Allows new students to create an account.
     */
    #[Route('/register', name: 'student_register', methods: ['GET', 'POST'])]
    public function register(Request $request, StudentRepository $studentRepository): Response
    {
        try {
            $this->logger->info('Student registration page accessed', [
                'method' => $request->getMethod(),
                'ip' => $this->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'referer' => $request->headers->get('Referer'),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            // If user is already authenticated, redirect to dashboard
            if ($this->getUser()) {
                $user = $this->getUser();
                $this->logger->info('Already authenticated student redirected from registration', [
                    'user_id' => $user instanceof Student ? $user->getId() : 'unknown',
                    'email' => $user->getUserIdentifier(),
                    'ip' => $this->getClientIp(),
                ]);
                return $this->redirectToRoute('student_dashboard');
            }

            $student = new Student();
            $form = $this->createForm(StudentRegistrationFormType::class, $student);
            
            $this->logger->debug('Student registration form created', [
                'form_name' => $form->getName(),
                'ip' => $this->getClientIp(),
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Student registration form submitted', [
                    'is_valid' => $form->isValid(),
                    'submitted_email' => $form->get('email')->getData(),
                    'ip' => $this->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'form_errors' => $form->isValid() ? [] : $this->getFormErrorsAsArray($form),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Starting student registration process', [
                        'email' => $student->getEmail(),
                        'first_name' => $student->getFirstName(),
                        'last_name' => $student->getLastName(),
                        'ip' => $this->getClientIp(),
                    ]);

                    // Check if student already exists
                    $existingStudent = $studentRepository->findByEmail($student->getEmail());
                    if ($existingStudent) {
                        $this->logger->warning('Registration attempt with existing email', [
                            'email' => $student->getEmail(),
                            'existing_student_id' => $existingStudent->getId(),
                            'ip' => $this->getClientIp(),
                        ]);
                        
                        $this->addFlash('error', 'Un compte avec cette adresse email existe déjà.');
                        
                        return $this->render('student/security/register.html.twig', [
                            'registrationForm' => $form,
                            'page_title' => 'Inscription Étudiant',
                        ]);
                    }

                    // Hash the password
                    $plainPassword = $form->get('plainPassword')->getData();
                    $this->logger->debug('Hashing password for new student', [
                        'email' => $student->getEmail(),
                        'password_length' => strlen($plainPassword),
                    ]);

                    $hashedPassword = $this->passwordHasher->hashPassword($student, $plainPassword);
                    $student->setPassword($hashedPassword);

                    // Generate email verification token
                    $student->generateEmailVerificationToken();
                    $this->logger->debug('Email verification token generated', [
                        'email' => $student->getEmail(),
                        'token_length' => strlen($student->getEmailVerificationToken() ?? ''),
                    ]);

                    // Save the student
                    $this->entityManager->beginTransaction();
                    
                    try {
                        $this->entityManager->persist($student);
                        $this->entityManager->flush();
                        $this->entityManager->commit();

                        $this->logger->info('New student registered successfully', [
                            'student_id' => $student->getId(),
                            'email' => $student->getEmail(),
                            'first_name' => $student->getFirstName(),
                            'last_name' => $student->getLastName(),
                            'ip' => $this->getClientIp(),
                            'user_agent' => $request->headers->get('User-Agent'),
                            'timestamp' => new \DateTimeImmutable(),
                        ]);

                        // TODO: Send email verification email
                        $this->logger->info('Email verification email should be sent', [
                            'student_id' => $student->getId(),
                            'email' => $student->getEmail(),
                            'verification_token' => $student->getEmailVerificationToken(),
                        ]);

                        $this->addFlash('success', 'Votre compte a été créé avec succès ! Un email de vérification vous a été envoyé.');

                        return $this->redirectToRoute('student_login');
                        
                    } catch (\Exception $e) {
                        $this->entityManager->rollback();
                        
                        $this->logger->error('Database error during student registration', [
                            'exception_message' => $e->getMessage(),
                            'exception_class' => get_class($e),
                            'exception_file' => $e->getFile(),
                            'exception_line' => $e->getLine(),
                            'email' => $student->getEmail(),
                            'ip' => $this->getClientIp(),
                        ]);
                        
                        $this->addFlash('error', 'Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.');
                        
                        return $this->render('student/security/register.html.twig', [
                            'registrationForm' => $form,
                            'page_title' => 'Inscription Étudiant',
                        ]);
                    }
                }
            } else {
                $this->logger->debug('Student registration form displayed', [
                    'ip' => $this->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);
            }

            return $this->render('student/security/register.html.twig', [
                'registrationForm' => $form,
                'page_title' => 'Inscription Étudiant',
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred in student registration', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'ip' => $this->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer plus tard.');
            
            // Create a new form for fallback
            $student = new Student();
            $form = $this->createForm(StudentRegistrationFormType::class, $student);
            
            return $this->render('student/security/register.html.twig', [
                'registrationForm' => $form,
                'page_title' => 'Inscription Étudiant',
            ]);
        }
    }

    /**
     * Student logout.
     *
     * This method can be blank - it will be intercepted by the logout key on your firewall.
     */
    #[Route('/logout', name: 'student_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Email verification.
     *
     * Verifies student email address using the verification token.
     */
    #[Route('/verify-email/{token}', name: 'student_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token, StudentRepository $studentRepository): Response
    {
        try {
            $request = $this->requestStack->getCurrentRequest();
            
            $this->logger->info('Email verification attempt', [
                'token' => $token,
                'token_length' => strlen($token),
                'ip' => $this->getClientIp(),
                'user_agent' => $request?->headers->get('User-Agent'),
                'referer' => $request?->headers->get('Referer'),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $student = $studentRepository->findByEmailVerificationToken($token);

            if (!$student) {
                $this->logger->warning('Email verification failed - invalid or expired token', [
                    'token' => $token,
                    'ip' => $this->getClientIp(),
                    'user_agent' => $request?->headers->get('User-Agent'),
                ]);

                $this->addFlash('error', 'Token de vérification invalide ou expiré.');
                return $this->redirectToRoute('student_login');
            }

            $this->logger->info('Valid verification token found for student', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'token' => $token,
                'was_verified' => $student->isEmailVerified(),
                'ip' => $this->getClientIp(),
            ]);

            // Begin transaction for email verification
            $this->entityManager->beginTransaction();
            
            try {
                $student->verifyEmail();
                $this->entityManager->flush();
                $this->entityManager->commit();

                $this->logger->info('Student email verified successfully', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'ip' => $this->getClientIp(),
                    'user_agent' => $request?->headers->get('User-Agent'),
                    'verification_timestamp' => new \DateTimeImmutable(),
                ]);

                $this->addFlash('success', 'Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('student_login');
                
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                
                $this->logger->error('Database error during email verification', [
                    'exception_message' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'token' => $token,
                    'ip' => $this->getClientIp(),
                ]);
                
                $this->addFlash('error', 'Une erreur est survenue lors de la vérification. Veuillez réessayer.');
                return $this->redirectToRoute('student_login');
            }
            
        } catch (\Exception $e) {
            $request = $this->requestStack->getCurrentRequest();
            
            $this->logger->error('Exception occurred in email verification', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'token' => $token,
                'ip' => $this->getClientIp(),
                'user_agent' => $request?->headers->get('User-Agent'),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer plus tard.');
            return $this->redirectToRoute('student_login');
        }
    }

    /**
     * Password reset request page.
     *
     * Allows students to request a password reset email.
     */
    #[Route('/forgot-password', name: 'student_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, StudentRepository $studentRepository): Response
    {
        try {
            $this->logger->info('Forgot password page accessed', [
                'method' => $request->getMethod(),
                'ip' => $this->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'referer' => $request->headers->get('Referer'),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            if ($request->isMethod('POST')) {
                $email = $request->request->get('email');
                
                $this->logger->info('Password reset request submitted', [
                    'email' => $email,
                    'ip' => $this->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);

                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->logger->warning('Invalid email format in password reset request', [
                        'email' => $email,
                        'ip' => $this->getClientIp(),
                    ]);
                } else {
                    $student = $studentRepository->findByEmail($email);

                    if ($student) {
                        if ($student->isActive()) {
                            $this->logger->info('Valid student found for password reset', [
                                'student_id' => $student->getId(),
                                'email' => $student->getEmail(),
                                'is_email_verified' => $student->isEmailVerified(),
                                'ip' => $this->getClientIp(),
                            ]);

                            $this->entityManager->beginTransaction();
                            
                            try {
                                $student->generatePasswordResetToken();
                                $this->entityManager->flush();
                                $this->entityManager->commit();

                                $this->logger->info('Password reset token generated successfully', [
                                    'student_id' => $student->getId(),
                                    'email' => $student->getEmail(),
                                    'token_length' => strlen($student->getPasswordResetToken() ?? ''),
                                    'token_expiry' => $student->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                                    'ip' => $this->getClientIp(),
                                ]);

                                // TODO: Send password reset email
                                $this->logger->info('Password reset email should be sent', [
                                    'student_id' => $student->getId(),
                                    'email' => $student->getEmail(),
                                    'reset_token' => $student->getPasswordResetToken(),
                                ]);
                                
                            } catch (\Exception $e) {
                                $this->entityManager->rollback();
                                
                                $this->logger->error('Database error during password reset token generation', [
                                    'exception_message' => $e->getMessage(),
                                    'exception_class' => get_class($e),
                                    'student_id' => $student->getId(),
                                    'email' => $student->getEmail(),
                                    'ip' => $this->getClientIp(),
                                ]);
                            }
                        } else {
                            $this->logger->warning('Password reset requested for inactive student', [
                                'student_id' => $student->getId(),
                                'email' => $student->getEmail(),
                                'is_active' => $student->isActive(),
                                'ip' => $this->getClientIp(),
                            ]);
                        }
                    } else {
                        $this->logger->warning('Password reset requested for non-existent email', [
                            'email' => $email,
                            'ip' => $this->getClientIp(),
                            'user_agent' => $request->headers->get('User-Agent'),
                        ]);
                    }
                }

                // Always show success message for security reasons
                $this->logger->info('Password reset success message displayed', [
                    'email' => $email,
                    'ip' => $this->getClientIp(),
                ]);
                
                $this->addFlash('success', 'Si un compte avec cet email existe, vous recevrez un lien de réinitialisation.');
                return $this->redirectToRoute('student_login');
            }

            $this->logger->debug('Forgot password form displayed', [
                'ip' => $this->getClientIp(),
            ]);

            return $this->render('student/security/forgot_password.html.twig', [
                'page_title' => 'Mot de passe oublié',
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred in forgot password', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'ip' => $this->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer plus tard.');
            
            return $this->render('student/security/forgot_password.html.twig', [
                'page_title' => 'Mot de passe oublié',
            ]);
        }
    }

    /**
     * Password reset page.
     *
     * Allows students to reset their password using a reset token.
     */
    #[Route('/reset-password/{token}', name: 'student_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request, StudentRepository $studentRepository): Response
    {
        try {
            $this->logger->info('Password reset page accessed', [
                'token' => $token,
                'token_length' => strlen($token),
                'method' => $request->getMethod(),
                'ip' => $this->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'referer' => $request->headers->get('Referer'),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $student = $studentRepository->findByPasswordResetToken($token);

            if (!$student) {
                $this->logger->warning('Password reset attempted with invalid token - student not found', [
                    'token' => $token,
                    'ip' => $this->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);

                $this->addFlash('error', 'Token de réinitialisation invalide ou expiré.');
                return $this->redirectToRoute('student_login');
            }

            if (!$student->isPasswordResetTokenValid()) {
                $this->logger->warning('Password reset attempted with expired token', [
                    'token' => $token,
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'token_expiry' => $student->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                    'ip' => $this->getClientIp(),
                ]);

                $this->addFlash('error', 'Token de réinitialisation invalide ou expiré.');
                return $this->redirectToRoute('student_login');
            }

            $this->logger->info('Valid password reset token found', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'token_expiry' => $student->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                'ip' => $this->getClientIp(),
            ]);

            if ($request->isMethod('POST')) {
                $newPassword = $request->request->get('password');
                $confirmPassword = $request->request->get('confirm_password');

                $this->logger->info('Password reset form submitted', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'password_length' => $newPassword ? strlen($newPassword) : 0,
                    'passwords_match' => $newPassword === $confirmPassword,
                    'ip' => $this->getClientIp(),
                ]);

                if ($newPassword !== $confirmPassword) {
                    $this->logger->warning('Password reset failed - passwords do not match', [
                        'student_id' => $student->getId(),
                        'email' => $student->getEmail(),
                        'ip' => $this->getClientIp(),
                    ]);

                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');

                    return $this->render('student/security/reset_password.html.twig', [
                        'token' => $token,
                        'page_title' => 'Nouveau mot de passe',
                    ]);
                }

                if (strlen($newPassword) < 6) {
                    $this->logger->warning('Password reset failed - password too short', [
                        'student_id' => $student->getId(),
                        'email' => $student->getEmail(),
                        'password_length' => strlen($newPassword),
                        'ip' => $this->getClientIp(),
                    ]);

                    $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');

                    return $this->render('student/security/reset_password.html.twig', [
                        'token' => $token,
                        'page_title' => 'Nouveau mot de passe',
                    ]);
                }

                $this->logger->debug('Hashing new password for reset', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'password_length' => strlen($newPassword),
                ]);

                $this->entityManager->beginTransaction();
                
                try {
                    $hashedPassword = $this->passwordHasher->hashPassword($student, $newPassword);
                    $student->setPassword($hashedPassword);
                    $student->clearPasswordResetToken();

                    $this->entityManager->flush();
                    $this->entityManager->commit();

                    $this->logger->info('Student password reset successfully', [
                        'student_id' => $student->getId(),
                        'email' => $student->getEmail(),
                        'ip' => $this->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent'),
                        'reset_timestamp' => new \DateTimeImmutable(),
                    ]);

                    $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');

                    return $this->redirectToRoute('student_login');
                    
                } catch (\Exception $e) {
                    $this->entityManager->rollback();
                    
                    $this->logger->error('Database error during password reset', [
                        'exception_message' => $e->getMessage(),
                        'exception_class' => get_class($e),
                        'student_id' => $student->getId(),
                        'email' => $student->getEmail(),
                        'ip' => $this->getClientIp(),
                    ]);
                    
                    $this->addFlash('error', 'Une erreur est survenue lors de la réinitialisation. Veuillez réessayer.');
                    
                    return $this->render('student/security/reset_password.html.twig', [
                        'token' => $token,
                        'page_title' => 'Nouveau mot de passe',
                    ]);
                }
            }

            $this->logger->debug('Password reset form displayed', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'ip' => $this->getClientIp(),
            ]);

            return $this->render('student/security/reset_password.html.twig', [
                'token' => $token,
                'page_title' => 'Nouveau mot de passe',
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred in password reset', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'token' => $token,
                'ip' => $this->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur technique est survenue. Veuillez réessayer plus tard.');
            return $this->redirectToRoute('student_login');
        }
    }

    /**
     * Get client IP address for logging.
     */
    private function getClientIp(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        return $request->getClientIp();
    }

    /**
     * Extract form errors as an array for logging.
     */
    private function getFormErrorsAsArray(FormInterface $form): array
    {
        $errors = [];
        
        // Get form errors
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }
        
        // Get field errors
        foreach ($form->all() as $fieldName => $formField) {
            foreach ($formField->getErrors() as $error) {
                $errors[$fieldName] = $error->getMessage();
            }
        }
        
        return $errors;
    }
}
