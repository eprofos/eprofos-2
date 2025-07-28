<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\Teacher;
use App\Repository\User\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Teacher Service.
 *
 * Handles teacher-related business logic including email notifications,
 * password management, and account operations.
 */
class TeacherService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeacherRepository $teacherRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger,
    ) {}

    /**
     * Send password reset email to teacher.
     */
    public function sendPasswordResetEmail(Teacher $teacher): bool
    {
        try {
            // Generate password reset token
            $teacher->generatePasswordResetToken();
            $this->entityManager->flush();

            // Create email
            $email = (new Email())
                ->from('noreply@eprofos.fr')
                ->to($teacher->getEmail())
                ->subject('Réinitialisation de votre mot de passe - EPROFOS')
                ->html(
                    $this->twig->render('emails/teacher/password_reset.html.twig', [
                        'teacher' => $teacher,
                        'reset_url' => $this->urlGenerator->generate(
                            'teacher_password_reset_confirm',
                            ['token' => $teacher->getPasswordResetToken()],
                            UrlGeneratorInterface::ABSOLUTE_URL,
                        ),
                    ]),
                )
            ;

            $this->mailer->send($email);

            $this->logger->info('Password reset email sent to teacher', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send password reset email to teacher', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email verification to teacher.
     */
    public function sendEmailVerification(Teacher $teacher): bool
    {
        try {
            // Generate email verification token if not exists
            if (!$teacher->getEmailVerificationToken()) {
                $teacher->generateEmailVerificationToken();
                $this->entityManager->flush();
            }

            // Create email
            $email = (new Email())
                ->from('noreply@eprofos.fr')
                ->to($teacher->getEmail())
                ->subject('Vérifiez votre adresse email - EPROFOS')
                ->html(
                    $this->twig->render('emails/teacher/email_verification.html.twig', [
                        'teacher' => $teacher,
                        'verification_url' => $this->urlGenerator->generate(
                            'teacher_email_verify',
                            ['token' => $teacher->getEmailVerificationToken()],
                            UrlGeneratorInterface::ABSOLUTE_URL,
                        ),
                    ]),
                )
            ;

            $this->mailer->send($email);

            $this->logger->info('Email verification sent to teacher', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send email verification to teacher', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send welcome email to new teacher.
     */
    public function sendWelcomeEmail(Teacher $teacher, ?string $tempPassword = null): bool
    {
        try {
            $email = (new Email())
                ->from('noreply@eprofos.fr')
                ->to($teacher->getEmail())
                ->subject('Bienvenue chez EPROFOS - Accès formateur')
                ->html(
                    $this->twig->render('emails/teacher/welcome.html.twig', [
                        'teacher' => $teacher,
                        'temp_password' => $tempPassword,
                        'login_url' => $this->urlGenerator->generate(
                            'teacher_login',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL,
                        ),
                    ]),
                )
            ;

            $this->mailer->send($email);

            $this->logger->info('Welcome email sent to teacher', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send welcome email to teacher', [
                'teacher_id' => $teacher->getId(),
                'email' => $teacher->getEmail(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create teacher account.
     */
    public function createTeacher(array $data): Teacher
    {
        $teacher = new Teacher();

        $teacher->setFirstName($data['firstName']);
        $teacher->setLastName($data['lastName']);
        $teacher->setEmail($data['email']);

        if (isset($data['phone'])) {
            $teacher->setPhone($data['phone']);
        }

        if (isset($data['specialty'])) {
            $teacher->setSpecialty($data['specialty']);
        }

        if (isset($data['title'])) {
            $teacher->setTitle($data['title']);
        }

        if (isset($data['yearsOfExperience'])) {
            $teacher->setYearsOfExperience($data['yearsOfExperience']);
        }

        if (isset($data['biography'])) {
            $teacher->setBiography($data['biography']);
        }

        if (isset($data['qualifications'])) {
            $teacher->setQualifications($data['qualifications']);
        }

        // Generate temporary password if not provided
        $password = $data['password'] ?? bin2hex(random_bytes(8));
        $hashedPassword = $this->passwordHasher->hashPassword($teacher, $password);
        $teacher->setPassword($hashedPassword);

        $this->entityManager->persist($teacher);
        $this->entityManager->flush();

        $this->logger->info('Teacher account created', [
            'teacher_id' => $teacher->getId(),
            'email' => $teacher->getEmail(),
        ]);

        return $teacher;
    }

    /**
     * Update teacher profile.
     */
    public function updateTeacher(Teacher $teacher, array $data): Teacher
    {
        if (isset($data['firstName'])) {
            $teacher->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $teacher->setLastName($data['lastName']);
        }

        if (isset($data['phone'])) {
            $teacher->setPhone($data['phone']);
        }

        if (isset($data['specialty'])) {
            $teacher->setSpecialty($data['specialty']);
        }

        if (isset($data['title'])) {
            $teacher->setTitle($data['title']);
        }

        if (isset($data['yearsOfExperience'])) {
            $teacher->setYearsOfExperience($data['yearsOfExperience']);
        }

        if (isset($data['biography'])) {
            $teacher->setBiography($data['biography']);
        }

        if (isset($data['qualifications'])) {
            $teacher->setQualifications($data['qualifications']);
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($teacher, $data['password']);
            $teacher->setPassword($hashedPassword);
        }

        $this->entityManager->flush();

        $this->logger->info('Teacher profile updated', [
            'teacher_id' => $teacher->getId(),
            'email' => $teacher->getEmail(),
        ]);

        return $teacher;
    }

    /**
     * Deactivate teacher account.
     */
    public function deactivateTeacher(Teacher $teacher): void
    {
        $teacher->setIsActive(false);
        $this->entityManager->flush();

        $this->logger->info('Teacher account deactivated', [
            'teacher_id' => $teacher->getId(),
            'email' => $teacher->getEmail(),
        ]);
    }

    /**
     * Activate teacher account.
     */
    public function activateTeacher(Teacher $teacher): void
    {
        $teacher->setIsActive(true);
        $this->entityManager->flush();

        $this->logger->info('Teacher account activated', [
            'teacher_id' => $teacher->getId(),
            'email' => $teacher->getEmail(),
        ]);
    }

    /**
     * Generate temporary password for teacher.
     */
    public function generateTemporaryPassword(Teacher $teacher): string
    {
        $tempPassword = bin2hex(random_bytes(8));
        $hashedPassword = $this->passwordHasher->hashPassword($teacher, $tempPassword);
        $teacher->setPassword($hashedPassword);
        $this->entityManager->flush();

        $this->logger->info('Temporary password generated for teacher', [
            'teacher_id' => $teacher->getId(),
            'email' => $teacher->getEmail(),
        ]);

        return $tempPassword;
    }

    /**
     * Get teacher statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->teacherRepository->countTotal(),
            'active' => $this->teacherRepository->countActive(),
            'verified' => $this->teacherRepository->countVerified(),
        ];
    }

    /**
     * Find teachers by criteria.
     */
    public function findTeachersByCriteria(array $criteria): array
    {
        return $this->teacherRepository->findWithFilters($criteria);
    }

    /**
     * Get teacher by email.
     */
    public function findByEmail(string $email): ?Teacher
    {
        return $this->teacherRepository->findByEmail($email);
    }

    /**
     * Check if email exists.
     */
    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }
}
