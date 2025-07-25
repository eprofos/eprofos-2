<?php

namespace App\Controller\Teacher;

use App\Entity\User\Teacher;
use App\Form\TeacherRegistrationFormType;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Teacher Security Controller
 * 
 * Handles authentication, registration, and password management for teachers.
 * Provides login, registration, and password reset functionality.
 */
#[Route('/teacher', name: 'teacher_')]
class SecurityController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Teacher login page
     * 
     * Displays the login form for teacher users.
     */
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already authenticated, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('teacher_dashboard');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error) {
            $this->logger->warning('Teacher login failed', [
                'username' => $lastUsername,
                'error' => $error->getMessage(),
                'ip' => $this->getClientIp()
            ]);
        }

        return $this->render('teacher/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'page_title' => 'Connexion Formateur'
        ]);
    }

    /**
     * Teacher registration page
     * 
     * Allows new teachers to create an account.
     */
    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request, TeacherRepository $teacherRepository): Response
    {
        // If user is already authenticated, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('teacher_dashboard');
        }

        $teacher = new Teacher();
        $form = $this->createForm(TeacherRegistrationFormType::class, $teacher);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword(
                $teacher,
                $form->get('plainPassword')->getData()
            );
            $teacher->setPassword($hashedPassword);

            // Generate email verification token
            $teacher->generateEmailVerificationToken();

            // Save the teacher
            $this->entityManager->persist($teacher);
            $this->entityManager->flush();

            $this->logger->info('New teacher registered', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'ip' => $this->getClientIp()
            ]);

            // TODO: Send email verification email
            $this->addFlash('success', 'Votre compte formateur a été créé avec succès ! Un email de vérification vous a été envoyé.');

            return $this->redirectToRoute('teacher_login');
        }

        return $this->render('teacher/security/register.html.twig', [
            'registrationForm' => $form,
            'page_title' => 'Inscription Formateur'
        ]);
    }

    /**
     * Teacher logout
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
     * Verifies teacher email address using the verification token.
     */
    #[Route('/verify-email/{token}', name: 'verify_email', methods: ['GET'])]
    public function verifyEmail(string $token, TeacherRepository $teacherRepository): Response
    {
        $teacher = $teacherRepository->findByEmailVerificationToken($token);

        if (!$teacher) {
            $this->addFlash('error', 'Token de vérification invalide ou expiré.');
            return $this->redirectToRoute('teacher_login');
        }

        $teacher->verifyEmail();
        $this->entityManager->flush();

        $this->logger->info('Teacher email verified', [
            'teacher_id' => $teacher->getId(),
            'email' => $teacher->getEmail()
        ]);

        $this->addFlash('success', 'Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('teacher_login');
    }

    /**
     * Password reset request page
     * 
     * Allows teachers to request a password reset email.
     */
    #[Route('/forgot-password', name: 'forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, TeacherRepository $teacherRepository): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $teacher = $teacherRepository->findByEmail($email);

            if ($teacher && $teacher->isActive()) {
                $teacher->generatePasswordResetToken();
                $this->entityManager->flush();

                $this->logger->info('Teacher password reset requested', [
                    'teacher_id' => $teacher->getId(),
                    'email' => $teacher->getEmail(),
                    'ip' => $this->getClientIp()
                ]);

                // TODO: Send password reset email
            }

            // Always show success message for security reasons
            $this->addFlash('success', 'Si un compte avec cet email existe, vous recevrez un lien de réinitialisation.');
            return $this->redirectToRoute('teacher_login');
        }

        return $this->render('teacher/security/forgot_password.html.twig', [
            'page_title' => 'Mot de passe oublié'
        ]);
    }

    /**
     * Password reset page
     * 
     * Allows teachers to reset their password using a reset token.
     */
    #[Route('/reset-password/{token}', name: 'reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request, TeacherRepository $teacherRepository): Response
    {
        $teacher = $teacherRepository->findByPasswordResetToken($token);

        if (!$teacher || !$teacher->isPasswordResetTokenValid()) {
            $this->addFlash('error', 'Token de réinitialisation invalide ou expiré.');
            return $this->redirectToRoute('teacher_login');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('teacher/security/reset_password.html.twig', [
                    'token' => $token,
                    'page_title' => 'Nouveau mot de passe'
                ]);
            }

            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
                return $this->render('teacher/security/reset_password.html.twig', [
                    'token' => $token,
                    'page_title' => 'Nouveau mot de passe'
                ]);
            }

            $hashedPassword = $this->passwordHasher->hashPassword($teacher, $newPassword);
            $teacher->setPassword($hashedPassword);
            $teacher->clearPasswordResetToken();

            $this->entityManager->flush();

            $this->logger->info('Teacher password reset', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'ip' => $this->getClientIp()
            ]);

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
            return $this->redirectToRoute('teacher_login');
        }

        return $this->render('teacher/security/reset_password.html.twig', [
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
