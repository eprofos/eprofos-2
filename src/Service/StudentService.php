<?php

namespace App\Service;

use App\Entity\User\Student;
use App\Repository\User\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Student Service
 * 
 * Handles business logic for student management including email notifications,
 * password reset functionality, and data export capabilities.
 */
class StudentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentRepository $studentRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $fromEmail = 'noreply@eprofos.fr',
        private string $fromName = 'EPROFOS - École Professionnelle de Formation Spécialisée',
        private string $adminEmail = 'admin@eprofos.fr'
    ) {
    }

    /**
     * Send welcome email to new student
     */
    public function sendWelcomeEmail(Student $student, ?string $plainPassword = null): bool
    {
        try {
            $this->logger->info('Sending welcome email to student', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail()
            ]);

            // Generate login URL
            $loginUrl = $this->urlGenerator->generate(
                'student_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($student->getEmail(), $student->getFullName()))
                ->subject('Bienvenue sur EPROFOS - Votre compte étudiant')
                ->htmlTemplate('emails/student_welcome.html.twig')
                ->context([
                    'student' => $student,
                    'login_url' => $loginUrl,
                    'plain_password' => $plainPassword,
                    'has_password' => $plainPassword !== null
                ]);

            $this->mailer->send($email);

            $this->logger->info('Welcome email sent successfully', [
                'student_id' => $student->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'student_id' => $student->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send password reset email to student
     */
    public function sendPasswordResetEmail(Student $student): bool
    {
        try {
            $this->logger->info('Sending password reset email to student', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail()
            ]);

            // Generate reset token
            $resetToken = $student->generatePasswordResetToken();
            $this->entityManager->flush();

            // Generate reset URL
            $resetUrl = $this->urlGenerator->generate(
                'student_reset_password',
                ['token' => $resetToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($student->getEmail(), $student->getFullName()))
                ->subject('Réinitialisation de votre mot de passe EPROFOS')
                ->htmlTemplate('emails/student_password_reset.html.twig')
                ->context([
                    'student' => $student,
                    'reset_url' => $resetUrl,
                    'expires_at' => $student->getPasswordResetTokenExpiresAt()
                ]);

            $this->mailer->send($email);

            $this->logger->info('Password reset email sent successfully', [
                'student_id' => $student->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'student_id' => $student->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send email verification link to student
     */
    public function sendEmailVerification(Student $student): bool
    {
        try {
            $this->logger->info('Sending email verification to student', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail()
            ]);

            // Generate verification token if not exists
            if (!$student->getEmailVerificationToken()) {
                $student->generateEmailVerificationToken();
                $this->entityManager->flush();
            }

            // Generate verification URL
            $verificationUrl = $this->urlGenerator->generate(
                'student_verify_email',
                ['token' => $student->getEmailVerificationToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($student->getEmail(), $student->getFullName()))
                ->subject('Vérification de votre adresse email EPROFOS')
                ->htmlTemplate('emails/student_email_verification.html.twig')
                ->context([
                    'student' => $student,
                    'verification_url' => $verificationUrl
                ]);

            $this->mailer->send($email);

            $this->logger->info('Email verification sent successfully', [
                'student_id' => $student->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send email verification', [
                'student_id' => $student->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send new password email to student
     */
    public function sendNewPasswordEmail(Student $student, string $newPassword): bool
    {
        try {
            $this->logger->info('Sending new password email to student', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail()
            ]);

            // Generate login URL
            $loginUrl = $this->urlGenerator->generate(
                'student_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($student->getEmail(), $student->getFullName()))
                ->subject('Nouveau mot de passe EPROFOS')
                ->htmlTemplate('emails/student_new_password.html.twig')
                ->context([
                    'student' => $student,
                    'new_password' => $newPassword,
                    'login_url' => $loginUrl
                ]);

            $this->mailer->send($email);

            $this->logger->info('New password email sent successfully', [
                'student_id' => $student->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send new password email', [
                'student_id' => $student->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Generate random password
     */
    public function generateRandomPassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }

    /**
     * Export students data to CSV format
     */
    public function exportToCsv(array $students): string
    {
        $csvData = [];
        
        // Headers
        $csvData[] = [
            'ID',
            'Email',
            'Prénom',
            'Nom',
            'Téléphone',
            'Date de naissance',
            'Adresse',
            'Code postal',
            'Ville',
            'Pays',
            'Niveau d\'études',
            'Profession',
            'Entreprise',
            'Actif',
            'Email vérifié',
            'Date de création',
            'Dernière connexion'
        ];

        // Data rows
        foreach ($students as $student) {
            $csvData[] = [
                $student->getId(),
                $student->getEmail(),
                $student->getFirstName(),
                $student->getLastName(),
                $student->getPhone() ?: '',
                $student->getBirthDate() ? $student->getBirthDate()->format('Y-m-d') : '',
                $student->getAddress() ?: '',
                $student->getPostalCode() ?: '',
                $student->getCity() ?: '',
                $student->getCountry() ?: '',
                $student->getEducationLevel() ?: '',
                $student->getProfession() ?: '',
                $student->getCompany() ?: '',
                $student->isActive() ? 'Oui' : 'Non',
                $student->isEmailVerified() ? 'Oui' : 'Non',
                $student->getCreatedAt()->format('Y-m-d H:i:s'),
                $student->getLastLoginAt() ? $student->getLastLoginAt()->format('Y-m-d H:i:s') : ''
            ];
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStatistics(): array
    {
        $statistics = $this->studentRepository->getStatistics();
        
        // Add additional calculated statistics
        $statistics['recent_registrations'] = $this->studentRepository->findRecentlyRegistered(30);
        $statistics['unverified_emails'] = $this->studentRepository->countUnverifiedEmails();
        $statistics['inactive_students'] = $this->studentRepository->countInactive();
        
        return $statistics;
    }

    /**
     * Clean up expired password reset tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $this->logger->info('Cleaning up expired password reset tokens');
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(Student::class, 's')
           ->set('s.passwordResetToken', 'NULL')
           ->set('s.passwordResetTokenExpiresAt', 'NULL')
           ->where('s.passwordResetTokenExpiresAt < :now')
           ->setParameter('now', new \DateTimeImmutable());
        
        $affected = $qb->getQuery()->execute();
        
        $this->logger->info('Expired password reset tokens cleaned up', [
            'affected_count' => $affected
        ]);
        
        return $affected;
    }

    /**
     * Send admin notification for new student registration
     */
    public function sendAdminNotificationForNewStudent(Student $student): bool
    {
        try {
            $this->logger->info('Sending admin notification for new student', [
                'student_id' => $student->getId()
            ]);

            $adminUrl = $this->urlGenerator->generate(
                'admin_student_show',
                ['id' => $student->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                ->subject('Nouveau compte étudiant créé - EPROFOS')
                ->htmlTemplate('emails/admin_new_student_notification.html.twig')
                ->context([
                    'student' => $student,
                    'admin_url' => $adminUrl
                ]);

            $this->mailer->send($email);

            $this->logger->info('Admin notification sent successfully', [
                'student_id' => $student->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send admin notification', [
                'student_id' => $student->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Validate student data
     */
    public function validateStudentData(Student $student): array
    {
        $errors = [];

        // Check email uniqueness
        $existingStudent = $this->studentRepository->findByEmail($student->getEmail());
        if ($existingStudent && $existingStudent->getId() !== $student->getId()) {
            $errors['email'] = 'Cette adresse email est déjà utilisée.';
        }

        // Validate email format
        if (!filter_var($student->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format d\'email invalide.';
        }

        // Validate required fields
        if (empty($student->getFirstName())) {
            $errors['firstName'] = 'Le prénom est obligatoire.';
        }

        if (empty($student->getLastName())) {
            $errors['lastName'] = 'Le nom est obligatoire.';
        }

        return $errors;
    }

    /**
     * Set configuration parameters
     */
    public function setEmailConfig(string $fromEmail, string $fromName, string $adminEmail): void
    {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->adminEmail = $adminEmail;
    }
}
