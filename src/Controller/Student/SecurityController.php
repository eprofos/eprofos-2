<?php

namespace App\Controller\Student;

use App\Entity\Student;
use App\Form\StudentRegistrationFormType;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Student Security Controller
 * 
 * Handles authentication, registration, and password management for students.
 * Provides login, registration, and password reset functionality.
 */
#[Route('/student', name: 'student_')]
class SecurityController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Student login page
     * 
     * Displays the login form for student users.
     */
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already authenticated, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('student_dashboard');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error) {
            $this->logger->warning('Student login failed', [
                'username' => $lastUsername,
                'error' => $error->getMessage(),
                'ip' => $this->getClientIp()
            ]);
        }

        return $this->render('student/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'page_title' => 'Connexion Étudiant'
        ]);
    }

    /**
     * Student registration page
     * 
     * Allows new students to create an account.
     */
    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request, StudentRepository $studentRepository): Response
    {
        // If user is already authenticated, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('student_dashboard');
        }

        $student = new Student();
        $form = $this->createForm(StudentRegistrationFormType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword(
                $student,
                $form->get('plainPassword')->getData()
            );
            $student->setPassword($hashedPassword);

            // Generate email verification token
            $student->generateEmailVerificationToken();

            // Save the student
            $this->entityManager->persist($student);
            $this->entityManager->flush();

            $this->logger->info('New student registered', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'ip' => $this->getClientIp()
            ]);

            // TODO: Send email verification email
            $this->addFlash('success', 'Votre compte a été créé avec succès ! Un email de vérification vous a été envoyé.');

            return $this->redirectToRoute('student_login');
        }

        return $this->render('student/security/register.html.twig', [
            'registrationForm' => $form,
            'page_title' => 'Inscription Étudiant'
        ]);
    }

    /**
     * Student logout
     * 
     * This method can be blank - it will be intercepted by the logout key on your firewall.
     */
    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Email verification
     * 
     * Verifies student email address using the verification token.
     */
    #[Route('/verify-email/{token}', name: 'verify_email', methods: ['GET'])]
    public function verifyEmail(string $token, StudentRepository $studentRepository): Response
    {
        $student = $studentRepository->findByEmailVerificationToken($token);

        if (!$student) {
            $this->addFlash('error', 'Token de vérification invalide ou expiré.');
            return $this->redirectToRoute('student_login');
        }

        $student->verifyEmail();
        $this->entityManager->flush();

        $this->logger->info('Student email verified', [
            'student_id' => $student->getId(),
            'email' => $student->getEmail()
        ]);

        $this->addFlash('success', 'Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('student_login');
    }

    /**
     * Password reset request page
     * 
     * Allows students to request a password reset email.
     */
    #[Route('/forgot-password', name: 'forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, StudentRepository $studentRepository): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $student = $studentRepository->findByEmail($email);

            if ($student && $student->isActive()) {
                $student->generatePasswordResetToken();
                $this->entityManager->flush();

                $this->logger->info('Password reset requested', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'ip' => $this->getClientIp()
                ]);

                // TODO: Send password reset email
            }

            // Always show success message for security reasons
            $this->addFlash('success', 'Si un compte avec cet email existe, vous recevrez un lien de réinitialisation.');
            return $this->redirectToRoute('student_login');
        }

        return $this->render('student/security/forgot_password.html.twig', [
            'page_title' => 'Mot de passe oublié'
        ]);
    }

    /**
     * Password reset page
     * 
     * Allows students to reset their password using a reset token.
     */
    #[Route('/reset-password/{token}', name: 'reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request, StudentRepository $studentRepository): Response
    {
        $student = $studentRepository->findByPasswordResetToken($token);

        if (!$student || !$student->isPasswordResetTokenValid()) {
            $this->addFlash('error', 'Token de réinitialisation invalide ou expiré.');
            return $this->redirectToRoute('student_login');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('student/security/reset_password.html.twig', [
                    'token' => $token,
                    'page_title' => 'Nouveau mot de passe'
                ]);
            }

            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
                return $this->render('student/security/reset_password.html.twig', [
                    'token' => $token,
                    'page_title' => 'Nouveau mot de passe'
                ]);
            }

            $hashedPassword = $this->passwordHasher->hashPassword($student, $newPassword);
            $student->setPassword($hashedPassword);
            $student->clearPasswordResetToken();

            $this->entityManager->flush();

            $this->logger->info('Student password reset', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'ip' => $this->getClientIp()
            ]);

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
            return $this->redirectToRoute('student_login');
        }

        return $this->render('student/security/reset_password.html.twig', [
            'token' => $token,
            'page_title' => 'Nouveau mot de passe'
        ]);
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
