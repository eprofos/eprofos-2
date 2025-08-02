<?php

declare(strict_types=1);

namespace App\Service\Core;

use App\Entity\User\Student;
use App\Repository\User\StudentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Student Service.
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
        private string $fromEmail = 'noreply@eprofos.com',
        private string $fromName = 'EPROFOS - École Professionnelle de Formation Spécialisée',
        private string $adminEmail = 'admin@eprofos.com',
    ) {}

    /**
     * Send welcome email to new student.
     */
    public function sendWelcomeEmail(Student $student, ?string $plainPassword = null): bool
    {
        $this->logger->info('Starting welcome email process', [
            'student_id' => $student->getId(),
            'email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
            'has_plain_password' => $plainPassword !== null,
            'method' => __METHOD__,
        ]);

        try {
            // Validate student data before proceeding
            if (!$student->getEmail()) {
                $this->logger->error('Student email is empty, cannot send welcome email', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
                return false;
            }

            if (!$student->getFullName()) {
                $this->logger->warning('Student full name is empty, proceeding with email only', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'method' => __METHOD__,
                ]);
            }

            $this->logger->debug('Generating login URL for welcome email', [
                'student_id' => $student->getId(),
                'route' => 'student_login',
                'method' => __METHOD__,
            ]);

            // Generate login URL
            $loginUrl = $this->urlGenerator->generate(
                'student_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->debug('Login URL generated successfully', [
                'student_id' => $student->getId(),
                'login_url' => $loginUrl,
                'method' => __METHOD__,
            ]);

            // Prepare email context
            $emailContext = [
                'student' => $student,
                'login_url' => $loginUrl,
                'plain_password' => $plainPassword,
                'has_password' => $plainPassword !== null,
            ];

            $this->logger->debug('Creating welcome email object', [
                'student_id' => $student->getId(),
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'to_email' => $student->getEmail(),
                'to_name' => $student->getFullName(),
                'template' => 'emails/student_welcome.html.twig',
                'context_keys' => array_keys($emailContext),
                'method' => __METHOD__,
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($student->getEmail(), $student->getFullName()))
                ->subject('Bienvenue sur EPROFOS - Votre compte étudiant')
                ->htmlTemplate('emails/student_welcome.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Attempting to send welcome email', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'subject' => 'Bienvenue sur EPROFOS - Votre compte étudiant',
                'method' => __METHOD__,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Welcome email sent successfully', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'login_url_provided' => !empty($loginUrl),
                'password_included' => $plainPassword !== null,
                'method' => __METHOD__,
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return false;
        }
    }

    /**
     * Send password reset email to student.
     */
    public function sendPasswordResetEmail(Student $student): bool
    {
        $this->logger->info('Starting password reset email process', [
            'student_id' => $student->getId(),
            'email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
            'method' => __METHOD__,
        ]);

        try {
            // Validate student data before proceeding
            if (!$student->getEmail()) {
                $this->logger->error('Student email is empty, cannot send password reset email', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
                return false;
            }

            $this->logger->debug('Generating password reset token', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'method' => __METHOD__,
            ]);

            // Generate reset token
            $resetToken = $student->generatePasswordResetToken();
            
            $this->logger->debug('Password reset token generated', [
                'student_id' => $student->getId(),
                'token_length' => strlen($resetToken),
                'token_expires_at' => $student->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Persisting password reset token to database', [
                'student_id' => $student->getId(),
                'method' => __METHOD__,
            ]);

            $this->entityManager->flush();

            $this->logger->debug('Password reset token saved successfully', [
                'student_id' => $student->getId(),
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Generating password reset URL', [
                'student_id' => $student->getId(),
                'route' => 'student_reset_password',
                'token_provided' => !empty($resetToken),
                'method' => __METHOD__,
            ]);

            // Generate reset URL
            $resetUrl = $this->urlGenerator->generate(
                'student_reset_password',
                ['token' => $resetToken],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->debug('Password reset URL generated successfully', [
                'student_id' => $student->getId(),
                'reset_url' => $resetUrl,
                'method' => __METHOD__,
            ]);

            // Prepare email context
            $emailContext = [
                'student' => $student,
                'reset_url' => $resetUrl,
                'expires_at' => $student->getPasswordResetTokenExpiresAt(),
            ];

            $this->logger->debug('Creating password reset email object', [
                'student_id' => $student->getId(),
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'to_email' => $student->getEmail(),
                'to_name' => $student->getFullName(),
                'template' => 'emails/student_password_reset.html.twig',
                'expires_at' => $student->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                'method' => __METHOD__,
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($student->getEmail(), $student->getFullName()))
                ->subject('Réinitialisation de votre mot de passe EPROFOS')
                ->htmlTemplate('emails/student_password_reset.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Attempting to send password reset email', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'subject' => 'Réinitialisation de votre mot de passe EPROFOS',
                'token_expires_at' => $student->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                'method' => __METHOD__,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Password reset email sent successfully', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'reset_url_provided' => !empty($resetUrl),
                'token_expires_at' => $student->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                'method' => __METHOD__,
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Try to rollback the token generation if database flush failed
            try {
                $this->logger->debug('Attempting to rollback password reset token generation', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
                
                $student->clearPasswordResetToken();
                $this->entityManager->flush();
                
                $this->logger->debug('Password reset token rollback successful', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
            } catch (Exception $rollbackException) {
                $this->logger->error('Failed to rollback password reset token', [
                    'student_id' => $student->getId(),
                    'rollback_error' => $rollbackException->getMessage(),
                    'original_error' => $e->getMessage(),
                    'method' => __METHOD__,
                ]);
            }

            return false;
        }
    }

    /**
     * Send email verification link to student.
     */
    public function sendEmailVerification(Student $student): bool
    {
        $this->logger->info('Starting email verification process', [
            'student_id' => $student->getId(),
            'email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
            'is_email_verified' => $student->isEmailVerified(),
            'existing_token' => $student->getEmailVerificationToken() !== null,
            'method' => __METHOD__,
        ]);

        try {
            // Validate student data before proceeding
            if (!$student->getEmail()) {
                $this->logger->error('Student email is empty, cannot send verification email', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
                return false;
            }

            if ($student->isEmailVerified()) {
                $this->logger->warning('Student email is already verified, skipping verification email', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'method' => __METHOD__,
                ]);
                return true;
            }

            $this->logger->debug('Checking for existing email verification token', [
                'student_id' => $student->getId(),
                'has_existing_token' => $student->getEmailVerificationToken() !== null,
                'method' => __METHOD__,
            ]);

            // Generate verification token if not exists
            if (!$student->getEmailVerificationToken()) {
                $this->logger->debug('Generating new email verification token', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);

                $student->generateEmailVerificationToken();
                
                $this->logger->debug('Email verification token generated', [
                    'student_id' => $student->getId(),
                    'token_length' => strlen($student->getEmailVerificationToken() ?? ''),
                    'method' => __METHOD__,
                ]);

                $this->logger->debug('Persisting email verification token to database', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);

                $this->entityManager->flush();

                $this->logger->debug('Email verification token saved successfully', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
            } else {
                $this->logger->debug('Using existing email verification token', [
                    'student_id' => $student->getId(),
                    'token_length' => strlen($student->getEmailVerificationToken()),
                    'method' => __METHOD__,
                ]);
            }

            $this->logger->debug('Generating email verification URL', [
                'student_id' => $student->getId(),
                'route' => 'student_verify_email',
                'token_provided' => $student->getEmailVerificationToken() !== null,
                'method' => __METHOD__,
            ]);

            // Generate verification URL
            $verificationUrl = $this->urlGenerator->generate(
                'student_verify_email',
                ['token' => $student->getEmailVerificationToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->debug('Email verification URL generated successfully', [
                'student_id' => $student->getId(),
                'verification_url' => $verificationUrl,
                'method' => __METHOD__,
            ]);

            // Prepare email context
            $emailContext = [
                'student' => $student,
                'verification_url' => $verificationUrl,
            ];

            $this->logger->debug('Creating email verification email object', [
                'student_id' => $student->getId(),
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'to_email' => $student->getEmail(),
                'to_name' => $student->getFullName(),
                'template' => 'emails/student_email_verification.html.twig',
                'method' => __METHOD__,
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($student->getEmail(), $student->getFullName()))
                ->subject('Vérification de votre adresse email EPROFOS')
                ->htmlTemplate('emails/student_email_verification.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Attempting to send email verification', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'subject' => 'Vérification de votre adresse email EPROFOS',
                'method' => __METHOD__,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Email verification sent successfully', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'verification_url_provided' => !empty($verificationUrl),
                'method' => __METHOD__,
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send email verification', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return false;
        }
    }

    /**
     * Send new password email to student.
     */
    public function sendNewPasswordEmail(Student $student, string $newPassword): bool
    {
        $this->logger->info('Starting new password email process', [
            'student_id' => $student->getId(),
            'email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
            'password_length' => strlen($newPassword),
            'method' => __METHOD__,
        ]);

        try {
            // Validate student data before proceeding
            if (!$student->getEmail()) {
                $this->logger->error('Student email is empty, cannot send new password email', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
                return false;
            }

            if (empty($newPassword)) {
                $this->logger->error('New password is empty, cannot send new password email', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
                return false;
            }

            $this->logger->debug('Generating login URL for new password email', [
                'student_id' => $student->getId(),
                'route' => 'student_login',
                'method' => __METHOD__,
            ]);

            // Generate login URL
            $loginUrl = $this->urlGenerator->generate(
                'student_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->debug('Login URL generated successfully', [
                'student_id' => $student->getId(),
                'login_url' => $loginUrl,
                'method' => __METHOD__,
            ]);

            // Prepare email context
            $emailContext = [
                'student' => $student,
                'new_password' => $newPassword,
                'login_url' => $loginUrl,
            ];

            $this->logger->debug('Creating new password email object', [
                'student_id' => $student->getId(),
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'to_email' => $student->getEmail(),
                'to_name' => $student->getFullName(),
                'template' => 'emails/student_new_password.html.twig',
                'password_length' => strlen($newPassword),
                'method' => __METHOD__,
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($student->getEmail(), $student->getFullName()))
                ->subject('Nouveau mot de passe EPROFOS')
                ->htmlTemplate('emails/student_new_password.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Attempting to send new password email', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'subject' => 'Nouveau mot de passe EPROFOS',
                'method' => __METHOD__,
            ]);

            $this->mailer->send($email);

            $this->logger->info('New password email sent successfully', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'login_url_provided' => !empty($loginUrl),
                'method' => __METHOD__,
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send new password email', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return false;
        }
    }

    /**
     * Generate random password.
     */
    public function generateRandomPassword(int $length = 12): string
    {
        $this->logger->info('Starting random password generation', [
            'requested_length' => $length,
            'method' => __METHOD__,
        ]);

        try {
            if ($length < 4) {
                $this->logger->warning('Password length too short, adjusting to minimum length', [
                    'requested_length' => $length,
                    'adjusted_length' => 4,
                    'method' => __METHOD__,
                ]);
                $length = 4;
            }

            if ($length > 128) {
                $this->logger->warning('Password length too long, adjusting to maximum length', [
                    'requested_length' => $length,
                    'adjusted_length' => 128,
                    'method' => __METHOD__,
                ]);
                $length = 128;
            }

            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            $characterCount = strlen($characters);
            $password = '';

            $this->logger->debug('Generating password characters', [
                'length' => $length,
                'character_set_size' => $characterCount,
                'method' => __METHOD__,
            ]);

            for ($i = 0; $i < $length; $i++) {
                $randomIndex = random_int(0, $characterCount - 1);
                $password .= $characters[$randomIndex];
            }

            $this->logger->info('Random password generated successfully', [
                'generated_length' => strlen($password),
                'requested_length' => $length,
                'contains_uppercase' => preg_match('/[A-Z]/', $password) ? true : false,
                'contains_lowercase' => preg_match('/[a-z]/', $password) ? true : false,
                'contains_digit' => preg_match('/[0-9]/', $password) ? true : false,
                'contains_special' => preg_match('/[!@#$%^&*]/', $password) ? true : false,
                'method' => __METHOD__,
            ]);

            return $password;
        } catch (Exception $e) {
            $this->logger->error('Failed to generate random password', [
                'requested_length' => $length,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'method' => __METHOD__,
            ]);

            // Fallback to a simple password generation
            $this->logger->warning('Using fallback password generation', [
                'method' => __METHOD__,
            ]);

            return 'eprofos' . date('YmdHis');
        }
    }

    /**
     * Export students data to CSV format.
     */
    public function exportToCsv(array $students): string
    {
        $this->logger->info('Starting CSV export process', [
            'student_count' => count($students),
            'method' => __METHOD__,
        ]);

        try {
            if (empty($students)) {
                $this->logger->warning('No students provided for CSV export', [
                    'method' => __METHOD__,
                ]);
            }

            $csvData = [];

            $this->logger->debug('Creating CSV headers', [
                'method' => __METHOD__,
            ]);

            // Headers
            $headers = [
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
                'Dernière connexion',
            ];

            $csvData[] = $headers;

            $this->logger->debug('CSV headers created', [
                'header_count' => count($headers),
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Processing students data for CSV export', [
                'student_count' => count($students),
                'method' => __METHOD__,
            ]);

            // Data rows
            $processedCount = 0;
            $errorCount = 0;

            foreach ($students as $student) {
                try {
                    if (!$student instanceof Student) {
                        $this->logger->warning('Invalid student object in export data', [
                            'student_type' => get_class($student),
                            'student_id' => method_exists($student, 'getId') ? $student->getId() : 'unknown',
                            'method' => __METHOD__,
                        ]);
                        $errorCount++;
                        continue;
                    }

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
                        $student->getLastLoginAt() ? $student->getLastLoginAt()->format('Y-m-d H:i:s') : '',
                    ];

                    $processedCount++;
                } catch (Exception $e) {
                    $this->logger->error('Error processing student for CSV export', [
                        'student_id' => method_exists($student, 'getId') ? $student->getId() : 'unknown',
                        'error_message' => $e->getMessage(),
                        'method' => __METHOD__,
                    ]);
                    $errorCount++;
                }
            }

            $this->logger->debug('Student data processing completed', [
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'total_rows' => count($csvData),
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Converting data to CSV format', [
                'method' => __METHOD__,
            ]);

            // Convert to CSV string
            $output = fopen('php://temp', 'r+');
            if ($output === false) {
                throw new Exception('Failed to create temporary file for CSV output');
            }

            foreach ($csvData as $rowIndex => $row) {
                $result = fputcsv($output, $row, ';');
                if ($result === false) {
                    throw new Exception("Failed to write CSV row at index {$rowIndex}");
                }
            }

            rewind($output);
            $csvString = stream_get_contents($output);
            fclose($output);

            if ($csvString === false) {
                throw new Exception('Failed to read CSV content from temporary file');
            }

            $this->logger->info('CSV export completed successfully', [
                'student_count' => count($students),
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'csv_size_bytes' => strlen($csvString),
                'csv_lines' => count($csvData),
                'method' => __METHOD__,
            ]);

            return $csvString;
        } catch (Exception $e) {
            $this->logger->error('Failed to export students to CSV', [
                'student_count' => count($students),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return empty CSV with headers as fallback
            return "ID;Email;Prénom;Nom;Téléphone;Date de naissance;Adresse;Code postal;Ville;Pays;Niveau d'études;Profession;Entreprise;Actif;Email vérifié;Date de création;Dernière connexion\n";
        }
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStatistics(): array
    {
        $this->logger->info('Starting dashboard statistics collection', [
            'method' => __METHOD__,
        ]);

        try {
            $this->logger->debug('Fetching basic statistics from repository', [
                'method' => __METHOD__,
            ]);

            $statistics = $this->studentRepository->getStatistics();

            $this->logger->debug('Basic statistics retrieved', [
                'statistics_keys' => array_keys($statistics),
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Fetching recent registrations (last 30 days)', [
                'days' => 30,
                'method' => __METHOD__,
            ]);

            // Add additional calculated statistics
            $recentRegistrations = $this->studentRepository->findRecentlyRegistered(30);
            $statistics['recent_registrations'] = $recentRegistrations;

            $this->logger->debug('Recent registrations retrieved', [
                'count' => count($recentRegistrations),
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Fetching unverified emails count', [
                'method' => __METHOD__,
            ]);

            $unverifiedCount = $this->studentRepository->countUnverifiedEmails();
            $statistics['unverified_emails'] = $unverifiedCount;

            $this->logger->debug('Unverified emails count retrieved', [
                'count' => $unverifiedCount,
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Fetching inactive students count', [
                'method' => __METHOD__,
            ]);

            $inactiveCount = $this->studentRepository->countInactive();
            $statistics['inactive_students'] = $inactiveCount;

            $this->logger->debug('Inactive students count retrieved', [
                'count' => $inactiveCount,
                'method' => __METHOD__,
            ]);

            $this->logger->info('Dashboard statistics collection completed successfully', [
                'total_statistics' => count($statistics),
                'statistics_keys' => array_keys($statistics),
                'recent_registrations_count' => count($recentRegistrations),
                'unverified_emails_count' => $unverifiedCount,
                'inactive_students_count' => $inactiveCount,
                'method' => __METHOD__,
            ]);

            return $statistics;
        } catch (Exception $e) {
            $this->logger->error('Failed to collect dashboard statistics', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return fallback statistics
            $fallbackStats = [
                'total_students' => 0,
                'active_students' => 0,
                'recent_registrations' => [],
                'unverified_emails' => 0,
                'inactive_students' => 0,
            ];

            $this->logger->warning('Returning fallback dashboard statistics', [
                'fallback_statistics' => $fallbackStats,
                'method' => __METHOD__,
            ]);

            return $fallbackStats;
        }
    }

    /**
     * Clean up expired password reset tokens.
     */
    public function cleanupExpiredTokens(): int
    {
        $this->logger->info('Starting cleanup of expired password reset tokens', [
            'method' => __METHOD__,
        ]);

        try {
            $this->logger->debug('Creating query builder for token cleanup', [
                'method' => __METHOD__,
            ]);

            $qb = $this->entityManager->createQueryBuilder();
            $qb->update(Student::class, 's')
                ->set('s.passwordResetToken', 'NULL')
                ->set('s.passwordResetTokenExpiresAt', 'NULL')
                ->where('s.passwordResetTokenExpiresAt < :now')
                ->setParameter('now', new DateTimeImmutable())
            ;

            $this->logger->debug('Executing token cleanup query', [
                'query_dql' => $qb->getDQL(),
                'current_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'method' => __METHOD__,
            ]);

            $affected = $qb->getQuery()->execute();

            $this->logger->info('Expired password reset tokens cleaned up successfully', [
                'affected_count' => $affected,
                'method' => __METHOD__,
            ]);

            return $affected;
        } catch (Exception $e) {
            $this->logger->error('Failed to cleanup expired password reset tokens', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return 0;
        }
    }

    /**
     * Send admin notification for new student registration.
     */
    public function sendAdminNotificationForNewStudent(Student $student): bool
    {
        $this->logger->info('Starting admin notification for new student registration', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'student_name' => $student->getFullName(),
            'admin_email' => $this->adminEmail,
            'method' => __METHOD__,
        ]);

        try {
            // Validate student data
            if (!$student->getId()) {
                $this->logger->error('Student ID is missing, cannot send admin notification', [
                    'student_email' => $student->getEmail(),
                    'method' => __METHOD__,
                ]);
                return false;
            }

            if (!$this->adminEmail) {
                $this->logger->error('Admin email is not configured, cannot send admin notification', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
                return false;
            }

            $this->logger->debug('Generating admin URL for student details', [
                'student_id' => $student->getId(),
                'route' => 'admin_student_show',
                'method' => __METHOD__,
            ]);

            $adminUrl = $this->urlGenerator->generate(
                'admin_student_show',
                ['id' => $student->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->debug('Admin URL generated successfully', [
                'student_id' => $student->getId(),
                'admin_url' => $adminUrl,
                'method' => __METHOD__,
            ]);

            // Prepare email context
            $emailContext = [
                'student' => $student,
                'admin_url' => $adminUrl,
            ];

            $this->logger->debug('Creating admin notification email object', [
                'student_id' => $student->getId(),
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'to_email' => $this->adminEmail,
                'to_name' => 'Administration EPROFOS',
                'template' => 'emails/admin_new_student_notification.html.twig',
                'method' => __METHOD__,
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                ->subject('Nouveau compte étudiant créé - EPROFOS')
                ->htmlTemplate('emails/admin_new_student_notification.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Attempting to send admin notification email', [
                'student_id' => $student->getId(),
                'admin_email' => $this->adminEmail,
                'subject' => 'Nouveau compte étudiant créé - EPROFOS',
                'method' => __METHOD__,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Admin notification sent successfully', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'admin_email' => $this->adminEmail,
                'admin_url_provided' => !empty($adminUrl),
                'method' => __METHOD__,
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send admin notification', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'admin_email' => $this->adminEmail,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return false;
        }
    }

    /**
     * Validate student data.
     */
    public function validateStudentData(Student $student): array
    {
        $this->logger->info('Starting student data validation', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'method' => __METHOD__,
        ]);

        $errors = [];

        try {
            $this->logger->debug('Validating email uniqueness', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'method' => __METHOD__,
            ]);

            // Check email uniqueness
            $existingStudent = $this->studentRepository->findByEmail($student->getEmail());
            if ($existingStudent && $existingStudent->getId() !== $student->getId()) {
                $errors['email'] = 'Cette adresse email est déjà utilisée.';
                $this->logger->warning('Email already exists for different student', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'existing_student_id' => $existingStudent->getId(),
                    'method' => __METHOD__,
                ]);
            } else {
                $this->logger->debug('Email uniqueness validation passed', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'method' => __METHOD__,
                ]);
            }

            $this->logger->debug('Validating email format', [
                'student_id' => $student->getId(),
                'email' => $student->getEmail(),
                'method' => __METHOD__,
            ]);

            // Validate email format
            if (!filter_var($student->getEmail(), FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Format d\'email invalide.';
                $this->logger->warning('Invalid email format', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'method' => __METHOD__,
                ]);
            } else {
                $this->logger->debug('Email format validation passed', [
                    'student_id' => $student->getId(),
                    'email' => $student->getEmail(),
                    'method' => __METHOD__,
                ]);
            }

            $this->logger->debug('Validating required fields', [
                'student_id' => $student->getId(),
                'first_name' => $student->getFirstName(),
                'last_name' => $student->getLastName(),
                'method' => __METHOD__,
            ]);

            // Validate required fields
            if (empty($student->getFirstName())) {
                $errors['firstName'] = 'Le prénom est obligatoire.';
                $this->logger->warning('First name is empty', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
            }

            if (empty($student->getLastName())) {
                $errors['lastName'] = 'Le nom est obligatoire.';
                $this->logger->warning('Last name is empty', [
                    'student_id' => $student->getId(),
                    'method' => __METHOD__,
                ]);
            }

            $this->logger->info('Student data validation completed', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'validation_errors_count' => count($errors),
                'validation_errors' => array_keys($errors),
                'is_valid' => empty($errors),
                'method' => __METHOD__,
            ]);

            return $errors;
        } catch (Exception $e) {
            $this->logger->error('Failed to validate student data', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return validation errors indicating system error
            return [
                'system' => 'Erreur de validation. Veuillez réessayer plus tard.',
            ];
        }
    }

    /**
     * Set configuration parameters.
     */
    public function setEmailConfig(string $fromEmail, string $fromName, string $adminEmail): void
    {
        $this->logger->info('Updating email configuration parameters', [
            'old_from_email' => $this->fromEmail,
            'new_from_email' => $fromEmail,
            'old_from_name' => $this->fromName,
            'new_from_name' => $fromName,
            'old_admin_email' => $this->adminEmail,
            'new_admin_email' => $adminEmail,
            'method' => __METHOD__,
        ]);

        try {
            // Validate email addresses
            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $this->logger->error('Invalid from email format provided', [
                    'from_email' => $fromEmail,
                    'method' => __METHOD__,
                ]);
                throw new Exception("Invalid from email format: {$fromEmail}");
            }

            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $this->logger->error('Invalid admin email format provided', [
                    'admin_email' => $adminEmail,
                    'method' => __METHOD__,
                ]);
                throw new Exception("Invalid admin email format: {$adminEmail}");
            }

            // Validate from name is not empty
            if (empty($fromName)) {
                $this->logger->warning('Empty from name provided, using default', [
                    'provided_from_name' => $fromName,
                    'method' => __METHOD__,
                ]);
                $fromName = 'EPROFOS';
            }

            $this->logger->debug('Email configuration validation passed', [
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'admin_email' => $adminEmail,
                'method' => __METHOD__,
            ]);

            $this->fromEmail = $fromEmail;
            $this->fromName = $fromName;
            $this->adminEmail = $adminEmail;

            $this->logger->info('Email configuration updated successfully', [
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'admin_email' => $this->adminEmail,
                'method' => __METHOD__,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update email configuration', [
                'provided_from_email' => $fromEmail,
                'provided_from_name' => $fromName,
                'provided_admin_email' => $adminEmail,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                'method' => __METHOD__,
            ]);

            // Re-throw the exception to let the caller handle it
            throw $e;
        }
    }
}
