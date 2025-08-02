<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\Mentor;
use App\Repository\User\MentorRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mentor Service.
 *
 * Handles business logic for mentor management including email notifications,
 * password reset functionality, invitation system, and data export capabilities.
 */
class MentorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MentorRepository $mentorRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $fromEmail = 'noreply@eprofos.com',
        private string $fromName = 'EPROFOS - École Professionnelle de Formation Spécialisée',
        private string $adminEmail = 'admin@eprofos.com',
    ) {}

    /**
     * Send welcome email to new mentor.
     */
    public function sendWelcomeEmail(Mentor $mentor, ?string $plainPassword = null): bool
    {
        $startTime = microtime(true);

        try {
            $this->logger->info('Sending welcome email to mentor', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'has_plain_password' => $plainPassword !== null,
                'operation' => 'welcome_email',
            ]);

            // Validate email configuration
            $this->validateEmailConfiguration();

            // Log comprehensive context
            $this->logMentorOperationContext('welcome_email', $mentor, [
                'has_plain_password' => $plainPassword !== null,
                'password_length' => $plainPassword ? strlen($plainPassword) : 0,
            ]);

            // Validate input parameters
            if (empty($mentor->getEmail())) {
                throw new InvalidArgumentException('Mentor email cannot be empty');
            }

            if (!filter_var($mentor->getEmail(), FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid mentor email format: {$mentor->getEmail()}");
            }

            if ($plainPassword !== null && strlen($plainPassword) < 8) {
                $this->logger->warning('Plain password length below recommended minimum', [
                    'mentor_id' => $mentor->getId(),
                    'password_length' => strlen($plainPassword),
                    'recommended_minimum' => 8,
                ]);
            }

            $this->logger->debug('Welcome email parameters validated', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mentor_full_name' => $mentor->getFullName(),
                'has_plain_password' => $plainPassword !== null,
            ]);

            // Generate login URL
            try {
                $loginUrl = $this->urlGenerator->generate(
                    'mentor_login',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $this->logger->debug('Login URL generated for welcome email', [
                    'mentor_id' => $mentor->getId(),
                    'url_length' => strlen($loginUrl),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate login URL for welcome email', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to generate login URL: ' . $e->getMessage());
            }

            // Create and send email
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                    ->subject('Bienvenue sur EPROFOS - Votre compte mentor')
                    ->htmlTemplate('emails/mentor_welcome.html.twig')
                    ->context([
                        'mentor' => $mentor,
                        'login_url' => $loginUrl,
                        'plain_password' => $plainPassword,
                        'has_password' => $plainPassword !== null,
                    ])
                ;

                $this->logger->debug('Welcome email message prepared', [
                    'mentor_id' => $mentor->getId(),
                    'recipient' => $mentor->getEmail(),
                    'template' => 'emails/mentor_welcome.html.twig',
                    'has_password_in_context' => $plainPassword !== null,
                ]);

                $this->mailer->send($email);
                $this->logger->debug('Welcome email sent to mailer', [
                    'mentor_id' => $mentor->getId(),
                    'recipient' => $mentor->getEmail(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to send welcome email', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $mentor->getEmail(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to send welcome email: ' . $e->getMessage());
            }

            $this->logPerformanceMetrics('welcome_email', $startTime, [
                'mentor_id' => $mentor->getId(),
                'has_plain_password' => $plainPassword !== null,
            ]);

            $this->logger->info('Welcome email sent successfully', [
                'mentor_id' => $mentor->getId(),
                'operation_result' => 'success',
            ]);

            return true;
        } catch (Exception $e) {
            $this->logPerformanceMetrics('welcome_email_failed', $startTime, [
                'mentor_id' => $mentor->getId(),
                'error_type' => get_class($e),
            ]);

            $this->logger->error('Failed to send welcome email', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operation' => 'welcome_email',
            ]);

            return false;
        }
    }

    /**
     * Send invitation email to potential mentor.
     */
    public function sendInvitationEmail(string $email, string $companyName, array $invitationData = []): bool
    {
        try {
            $this->logger->info('Sending invitation email to potential mentor', [
                'email' => $email,
                'company' => $companyName,
                'invitation_data_keys' => array_keys($invitationData),
                'operation' => 'mentor_invitation',
            ]);

            // Validate input parameters
            if (empty($email)) {
                throw new InvalidArgumentException('Email address is required');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid email format: {$email}");
            }

            if (empty($companyName)) {
                throw new InvalidArgumentException('Company name is required');
            }

            $this->logger->debug('Invitation parameters validated', [
                'email' => $email,
                'company_name' => $companyName,
                'invitation_data_count' => count($invitationData),
            ]);

            // Generate registration URL
            try {
                $registrationUrl = $this->urlGenerator->generate(
                    'mentor_register',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $this->logger->debug('Registration URL generated', [
                    'url_length' => strlen($registrationUrl),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate registration URL', [
                    'error' => $e->getMessage(),
                    'email' => $email,
                ]);

                throw new Exception('Failed to generate registration URL: ' . $e->getMessage());
            }

            // Create and send email
            try {
                $emailMessage = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($email))
                    ->subject('Invitation à rejoindre EPROFOS en tant que mentor entreprise')
                    ->htmlTemplate('emails/mentor_invitation.html.twig')
                    ->context([
                        'company_name' => $companyName,
                        'registration_url' => $registrationUrl,
                        'invitation_data' => $invitationData,
                    ])
                ;

                $this->mailer->send($emailMessage);
                $this->logger->debug('Invitation email sent to mailer', [
                    'recipient' => $email,
                    'company_name' => $companyName,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to send invitation email', [
                    'email' => $email,
                    'company_name' => $companyName,
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to send invitation email: ' . $e->getMessage());
            }

            $this->logger->info('Invitation email sent successfully', [
                'email' => $email,
                'company_name' => $companyName,
                'operation_result' => 'success',
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send invitation email', [
                'email' => $email,
                'company_name' => $companyName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operation' => 'mentor_invitation',
            ]);

            return false;
        }
    }

    /**
     * Send password reset email to mentor.
     */
    public function sendPasswordResetEmail(Mentor $mentor): bool
    {
        try {
            $this->logger->info('Sending password reset email to mentor', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'operation' => 'password_reset_email',
            ]);

            // Generate reset token
            try {
                $resetToken = $mentor->generatePasswordResetToken();
                $this->logger->debug('Password reset token generated', [
                    'mentor_id' => $mentor->getId(),
                    'token_length' => strlen($resetToken),
                    'expires_at' => $mentor->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate password reset token', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to generate reset token: ' . $e->getMessage());
            }

            // Persist the token to database
            try {
                $this->entityManager->flush();
                $this->logger->debug('Password reset token persisted to database', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to persist password reset token to database', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to save reset token: ' . $e->getMessage());
            }

            // Generate reset URL
            try {
                $resetUrl = $this->urlGenerator->generate(
                    'mentor_reset_password',
                    ['token' => $resetToken],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $this->logger->debug('Password reset URL generated', [
                    'mentor_id' => $mentor->getId(),
                    'url_length' => strlen($resetUrl),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate password reset URL', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to generate reset URL: ' . $e->getMessage());
            }

            // Create and send email
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                    ->subject('Réinitialisation de votre mot de passe EPROFOS')
                    ->htmlTemplate('emails/mentor_password_reset.html.twig')
                    ->context([
                        'mentor' => $mentor,
                        'reset_url' => $resetUrl,
                        'expires_at' => $mentor->getPasswordResetTokenExpiresAt(),
                    ])
                ;

                $this->mailer->send($email);
                $this->logger->debug('Password reset email sent to mailer', [
                    'mentor_id' => $mentor->getId(),
                    'recipient' => $mentor->getEmail(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to send password reset email', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $mentor->getEmail(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to send reset email: ' . $e->getMessage());
            }

            $this->logger->info('Password reset email sent successfully', [
                'mentor_id' => $mentor->getId(),
                'operation_result' => 'success',
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operation' => 'password_reset_email',
            ]);

            return false;
        }
    }

    /**
     * Send email verification link to mentor.
     */
    public function sendEmailVerification(Mentor $mentor): bool
    {
        try {
            $this->logger->info('Sending email verification to mentor', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'operation' => 'email_verification',
            ]);

            // Generate verification token if not exists
            if (!$mentor->getEmailVerificationToken()) {
                try {
                    $mentor->generateEmailVerificationToken();
                    $this->logger->debug('Email verification token generated', [
                        'mentor_id' => $mentor->getId(),
                        'token_length' => strlen($mentor->getEmailVerificationToken()),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to generate email verification token', [
                        'mentor_id' => $mentor->getId(),
                        'error' => $e->getMessage(),
                    ]);

                    throw new Exception('Failed to generate verification token: ' . $e->getMessage());
                }

                try {
                    $this->entityManager->flush();
                    $this->logger->debug('Email verification token persisted to database', [
                        'mentor_id' => $mentor->getId(),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to persist email verification token to database', [
                        'mentor_id' => $mentor->getId(),
                        'error' => $e->getMessage(),
                    ]);

                    throw new Exception('Failed to save verification token: ' . $e->getMessage());
                }
            } else {
                $this->logger->debug('Using existing email verification token', [
                    'mentor_id' => $mentor->getId(),
                    'token_length' => strlen($mentor->getEmailVerificationToken()),
                ]);
            }

            // Generate verification URL
            try {
                $verificationUrl = $this->urlGenerator->generate(
                    'mentor_verify_email',
                    ['token' => $mentor->getEmailVerificationToken()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $this->logger->debug('Email verification URL generated', [
                    'mentor_id' => $mentor->getId(),
                    'url_length' => strlen($verificationUrl),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate email verification URL', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to generate verification URL: ' . $e->getMessage());
            }

            // Create and send email
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                    ->subject('Vérification de votre adresse email EPROFOS')
                    ->htmlTemplate('emails/mentor_email_verification.html.twig')
                    ->context([
                        'mentor' => $mentor,
                        'verification_url' => $verificationUrl,
                    ])
                ;

                $this->mailer->send($email);
                $this->logger->debug('Email verification sent to mailer', [
                    'mentor_id' => $mentor->getId(),
                    'recipient' => $mentor->getEmail(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to send email verification', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $mentor->getEmail(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to send verification email: ' . $e->getMessage());
            }

            $this->logger->info('Email verification sent successfully', [
                'mentor_id' => $mentor->getId(),
                'operation_result' => 'success',
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send email verification', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operation' => 'email_verification',
            ]);

            return false;
        }
    }

    /**
     * Send new password email to mentor.
     */
    public function sendNewPasswordEmail(Mentor $mentor, string $newPassword): bool
    {
        try {
            $this->logger->info('Sending new password email to mentor', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'operation' => 'new_password_email',
                'password_length' => strlen($newPassword),
            ]);

            // Validate input parameters
            if (empty($newPassword)) {
                throw new InvalidArgumentException('New password cannot be empty');
            }

            if (strlen($newPassword) < 8) {
                $this->logger->warning('New password length is below recommended minimum', [
                    'mentor_id' => $mentor->getId(),
                    'password_length' => strlen($newPassword),
                    'recommended_minimum' => 8,
                ]);
            }

            $this->logger->debug('New password email parameters validated', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mentor_full_name' => $mentor->getFullName(),
            ]);

            // Generate login URL
            try {
                $loginUrl = $this->urlGenerator->generate(
                    'mentor_login',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $this->logger->debug('Login URL generated for new password email', [
                    'mentor_id' => $mentor->getId(),
                    'url_length' => strlen($loginUrl),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate login URL for new password email', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to generate login URL: ' . $e->getMessage());
            }

            // Create and send email
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                    ->subject('Nouveau mot de passe EPROFOS')
                    ->htmlTemplate('emails/mentor_new_password.html.twig')
                    ->context([
                        'mentor' => $mentor,
                        'new_password' => $newPassword,
                        'login_url' => $loginUrl,
                    ])
                ;

                $this->logger->debug('New password email message prepared', [
                    'mentor_id' => $mentor->getId(),
                    'recipient' => $mentor->getEmail(),
                    'template' => 'emails/mentor_new_password.html.twig',
                ]);

                $this->mailer->send($email);
                $this->logger->debug('New password email sent to mailer', [
                    'mentor_id' => $mentor->getId(),
                    'recipient' => $mentor->getEmail(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to send new password email', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $mentor->getEmail(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to send new password email: ' . $e->getMessage());
            }

            $this->logger->info('New password email sent successfully', [
                'mentor_id' => $mentor->getId(),
                'operation_result' => 'success',
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send new password email', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operation' => 'new_password_email',
            ]);

            return false;
        }
    }

    /**
     * Generate random password.
     */
    public function generateRandomPassword(int $length = 12): string
    {
        try {
            $this->logger->info('Generating random password', [
                'requested_length' => $length,
                'operation' => 'password_generation',
            ]);

            if ($length < 8) {
                $this->logger->warning('Password length too short, adjusting to minimum 8 characters', [
                    'requested_length' => $length,
                    'adjusted_length' => 8,
                ]);
                $length = 8;
            }

            if ($length > 128) {
                $this->logger->warning('Password length too long, adjusting to maximum 128 characters', [
                    'requested_length' => $length,
                    'adjusted_length' => 128,
                ]);
                $length = 128;
            }

            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            $charactersLength = strlen($characters);
            $password = '';

            $this->logger->debug('Starting password character generation', [
                'character_set_length' => $charactersLength,
                'target_length' => $length,
            ]);

            for ($i = 0; $i < $length; $i++) {
                $randomIndex = random_int(0, $charactersLength - 1);
                $password .= $characters[$randomIndex];
            }

            $this->logger->info('Random password generated successfully', [
                'final_length' => strlen($password),
                'operation_result' => 'success',
            ]);

            return $password;
        } catch (Exception $e) {
            $this->logger->error('Failed to generate random password', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'requested_length' => $length,
                'operation' => 'password_generation',
            ]);

            // Fallback to a simpler method
            $fallbackPassword = bin2hex(random_bytes($length / 2));
            $this->logger->warning('Using fallback password generation method', [
                'fallback_length' => strlen($fallbackPassword),
            ]);

            return $fallbackPassword;
        }
    }

    /**
     * Export mentors data to CSV format.
     */
    public function exportToCsv(array $mentors): string
    {
        $startTime = microtime(true);

        try {
            $this->logger->info('Starting CSV export for mentors', [
                'mentors_count' => count($mentors),
                'operation' => 'csv_export',
                'memory_before_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            if (empty($mentors)) {
                $this->logger->warning('No mentors provided for CSV export', [
                    'mentors_count' => 0,
                ]);
            }

            $csvData = [];

            // Headers
            $headers = [
                'ID',
                'Email',
                'Prénom',
                'Nom',
                'Téléphone',
                'Poste',
                'Entreprise',
                'SIRET',
                'Domaines d\'expertise',
                'Années d\'expérience',
                'Niveau de formation',
                'Actif',
                'Email vérifié',
                'Date de création',
                'Dernière connexion',
            ];
            $csvData[] = $headers;

            $this->logger->debug('CSV headers prepared', [
                'headers_count' => count($headers),
                'headers' => $headers,
            ]);

            // Data rows
            $processedCount = 0;
            $errorCount = 0;
            $startProcessingTime = microtime(true);

            foreach ($mentors as $index => $mentor) {
                try {
                    // Validate mentor object
                    if (!$mentor instanceof Mentor) {
                        throw new InvalidArgumentException("Invalid mentor object at index {$index}");
                    }

                    $row = [
                        $mentor->getId() ?? '',
                        $mentor->getEmail() ?? '',
                        $mentor->getFirstName() ?? '',
                        $mentor->getLastName() ?? '',
                        $mentor->getPhone() ?? '',
                        $mentor->getPosition() ?? '',
                        $mentor->getCompanyName() ?? '',
                        $mentor->getCompanySiret() ?? '',
                        $mentor->getExpertiseDomainsLabels() ? implode(', ', $mentor->getExpertiseDomainsLabels()) : '',
                        $mentor->getExperienceYears() ?? 0,
                        $mentor->getEducationLevelLabel() ?? '',
                        $mentor->isActive() ? 'Oui' : 'Non',
                        $mentor->isEmailVerified() ? 'Oui' : 'Non',
                        $mentor->getCreatedAt() ? $mentor->getCreatedAt()->format('Y-m-d H:i:s') : '',
                        $mentor->getLastLoginAt() ? $mentor->getLastLoginAt()->format('Y-m-d H:i:s') : '',
                    ];
                    $csvData[] = $row;
                    $processedCount++;

                    if ($processedCount % 50 === 0) {
                        $this->logger->debug('CSV export progress', [
                            'processed_count' => $processedCount,
                            'total_count' => count($mentors),
                            'progress_percentage' => round(($processedCount / count($mentors)) * 100, 2),
                            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                        ]);
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $this->logger->warning('Failed to process mentor for CSV export', [
                        'mentor_index' => $index,
                        'mentor_id' => $mentor->getId() ?? 'unknown',
                        'error' => $e->getMessage(),
                        'skipping_mentor' => true,
                    ]);

                    continue;
                }
            }

            $processingTime = round((microtime(true) - $startProcessingTime) * 1000, 2);

            $this->logger->info('Mentor data processed for CSV', [
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'total_rows' => count($csvData),
                'processing_time_ms' => $processingTime,
            ]);

            if ($errorCount > 0) {
                $this->logger->warning('Some mentors could not be processed during CSV export', [
                    'error_count' => $errorCount,
                    'success_count' => $processedCount,
                    'error_rate_percentage' => round(($errorCount / count($mentors)) * 100, 2),
                ]);
            }

            // Convert to CSV string
            $csvStartTime = microtime(true);

            $output = fopen('php://temp', 'r+');
            if ($output === false) {
                throw new Exception('Failed to open temporary file for CSV generation');
            }

            $this->logger->debug('Temporary file opened for CSV generation', [
                'csv_rows_count' => count($csvData),
            ]);

            $rowsWritten = 0;
            foreach ($csvData as $rowIndex => $row) {
                if (fputcsv($output, $row, ';') === false) {
                    throw new Exception("Failed to write CSV row at index {$rowIndex}");
                }
                $rowsWritten++;

                if ($rowsWritten % 1000 === 0) {
                    $this->logger->debug('CSV writing progress', [
                        'rows_written' => $rowsWritten,
                        'total_rows' => count($csvData),
                        'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    ]);
                }
            }

            rewind($output);
            $csvString = stream_get_contents($output);
            fclose($output);

            if ($csvString === false) {
                throw new Exception('Failed to read CSV content from temporary file');
            }

            $csvGenerationTime = round((microtime(true) - $csvStartTime) * 1000, 2);

            $this->logPerformanceMetrics('csv_export', $startTime, [
                'mentors_count' => count($mentors),
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'csv_size_bytes' => strlen($csvString),
                'csv_generation_time_ms' => $csvGenerationTime,
            ]);

            $this->logger->info('CSV export completed successfully', [
                'rows_written' => $rowsWritten,
                'csv_size_bytes' => strlen($csvString),
                'csv_size_kb' => round(strlen($csvString) / 1024, 2),
                'operation_result' => 'success',
                'error_count' => $errorCount,
                'success_rate_percentage' => round(($processedCount / count($mentors)) * 100, 2),
            ]);

            return $csvString;
        } catch (Exception $e) {
            $this->handleCriticalError('csv_export', $e, [
                'mentors_count' => count($mentors),
                'operation' => 'csv_export',
            ]);

            $this->logPerformanceMetrics('csv_export_failed', $startTime, [
                'mentors_count' => count($mentors),
                'error_type' => get_class($e),
            ]);

            $this->logger->error('Failed to export mentors to CSV', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mentors_count' => count($mentors),
                'operation' => 'csv_export',
            ]);

            throw new Exception('CSV export failed: ' . $e->getMessage());
        }
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStatistics(): array
    {
        try {
            $this->logger->info('Retrieving dashboard statistics', [
                'operation' => 'dashboard_statistics',
            ]);

            // Get base statistics from repository
            $statistics = $this->mentorRepository->getStatistics();
            $this->logger->debug('Base statistics retrieved from repository', [
                'base_stats_keys' => array_keys($statistics),
            ]);

            // Add additional calculated statistics
            try {
                $recentRegistrations = $this->mentorRepository->findRecentlyRegistered(30);
                $statistics['recent_registrations'] = $recentRegistrations;
                $this->logger->debug('Recent registrations retrieved', [
                    'count' => count($recentRegistrations),
                    'period_days' => 30,
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Failed to retrieve recent registrations', [
                    'error' => $e->getMessage(),
                    'fallback_value' => [],
                ]);
                $statistics['recent_registrations'] = [];
            }

            try {
                $unverifiedEmails = $this->mentorRepository->countUnverifiedEmails();
                $statistics['unverified_emails'] = $unverifiedEmails;
                $this->logger->debug('Unverified emails count retrieved', [
                    'count' => $unverifiedEmails,
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Failed to count unverified emails', [
                    'error' => $e->getMessage(),
                    'fallback_value' => 0,
                ]);
                $statistics['unverified_emails'] = 0;
            }

            try {
                $inactiveMentors = $this->mentorRepository->countInactive();
                $statistics['inactive_mentors'] = $inactiveMentors;
                $this->logger->debug('Inactive mentors count retrieved', [
                    'count' => $inactiveMentors,
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Failed to count inactive mentors', [
                    'error' => $e->getMessage(),
                    'fallback_value' => 0,
                ]);
                $statistics['inactive_mentors'] = 0;
            }

            try {
                $experienceStats = $this->mentorRepository->getExperienceStatistics();
                $statistics['experience_stats'] = $experienceStats;
                $this->logger->debug('Experience statistics retrieved', [
                    'stats_keys' => array_keys($experienceStats),
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Failed to retrieve experience statistics', [
                    'error' => $e->getMessage(),
                    'fallback_value' => [],
                ]);
                $statistics['experience_stats'] = [];
            }

            try {
                $educationDistribution = $this->mentorRepository->getEducationLevelDistribution();
                $statistics['education_distribution'] = $educationDistribution;
                $this->logger->debug('Education distribution retrieved', [
                    'distribution_keys' => array_keys($educationDistribution),
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Failed to retrieve education distribution', [
                    'error' => $e->getMessage(),
                    'fallback_value' => [],
                ]);
                $statistics['education_distribution'] = [];
            }

            $this->logger->info('Dashboard statistics retrieved successfully', [
                'statistics_keys' => array_keys($statistics),
                'operation_result' => 'success',
            ]);

            return $statistics;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve dashboard statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operation' => 'dashboard_statistics',
            ]);

            // Return minimal fallback statistics
            $fallbackStats = [
                'total_mentors' => 0,
                'active_mentors' => 0,
                'recent_registrations' => [],
                'unverified_emails' => 0,
                'inactive_mentors' => 0,
                'experience_stats' => [],
                'education_distribution' => [],
            ];

            $this->logger->warning('Returning fallback statistics due to error', [
                'fallback_stats_keys' => array_keys($fallbackStats),
            ]);

            return $fallbackStats;
        }
    }

    /**
     * Clean up expired password reset tokens.
     */
    public function cleanupExpiredTokens(): int
    {
        try {
            $this->logger->info('Starting cleanup of expired password reset tokens', [
                'operation' => 'token_cleanup',
                'current_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $qb = $this->entityManager->createQueryBuilder();
            $qb->update(Mentor::class, 'm')
                ->set('m.passwordResetToken', 'NULL')
                ->set('m.passwordResetTokenExpiresAt', 'NULL')
                ->where('m.passwordResetTokenExpiresAt < :now')
                ->setParameter('now', new DateTimeImmutable())
            ;

            $affected = $qb->getQuery()->execute();

            if ($affected > 0) {
                $this->logger->info('Expired password reset tokens cleaned up successfully', [
                    'affected_count' => $affected,
                    'operation_result' => 'success',
                ]);
            } else {
                $this->logger->debug('No expired tokens found to cleanup', [
                    'affected_count' => 0,
                ]);
            }

            return $affected;
        } catch (Exception $e) {
            $this->logger->error('Failed to cleanup expired password reset tokens', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operation' => 'token_cleanup',
            ]);

            throw new Exception('Token cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Send admin notification for new mentor registration.
     */
    public function sendAdminNotificationForNewMentor(Mentor $mentor): bool
    {
        try {
            $this->logger->info('Sending admin notification for new mentor', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mentor_company' => $mentor->getCompanyName(),
                'operation' => 'admin_notification_new_mentor',
            ]);

            // Validate mentor data before sending notification
            if (!$mentor->getId()) {
                throw new InvalidArgumentException('Mentor must have an ID');
            }

            if (empty($mentor->getEmail())) {
                throw new InvalidArgumentException('Mentor must have an email address');
            }

            $this->logger->debug('Mentor data validation passed for admin notification', [
                'mentor_id' => $mentor->getId(),
                'has_email' => !empty($mentor->getEmail()),
                'has_company' => !empty($mentor->getCompanyName()),
            ]);

            // Generate admin URL
            try {
                $adminUrl = $this->urlGenerator->generate(
                    'admin_mentor_show',
                    ['id' => $mentor->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $this->logger->debug('Admin URL generated for mentor notification', [
                    'mentor_id' => $mentor->getId(),
                    'url_length' => strlen($adminUrl),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate admin URL for mentor notification', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to generate admin URL: ' . $e->getMessage());
            }

            // Create and send email
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                    ->subject('Nouveau compte mentor créé - EPROFOS')
                    ->htmlTemplate('emails/admin_new_mentor_notification.html.twig')
                    ->context([
                        'mentor' => $mentor,
                        'admin_url' => $adminUrl,
                    ])
                ;

                $this->logger->debug('Admin notification email prepared', [
                    'mentor_id' => $mentor->getId(),
                    'admin_email' => $this->adminEmail,
                    'template' => 'emails/admin_new_mentor_notification.html.twig',
                ]);

                $this->mailer->send($email);
                $this->logger->debug('Admin notification email sent to mailer', [
                    'mentor_id' => $mentor->getId(),
                    'admin_email' => $this->adminEmail,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to send admin notification email', [
                    'mentor_id' => $mentor->getId(),
                    'admin_email' => $this->adminEmail,
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to send admin notification: ' . $e->getMessage());
            }

            $this->logger->info('Admin notification sent successfully', [
                'mentor_id' => $mentor->getId(),
                'admin_email' => $this->adminEmail,
                'operation_result' => 'success',
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send admin notification', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'admin_email' => $this->adminEmail,
                'operation' => 'admin_notification_new_mentor',
            ]);

            return false;
        }
    }

    /**
     * Validate mentor data.
     */
    public function validateMentorData(Mentor $mentor): array
    {
        try {
            $this->logger->info('Starting mentor data validation', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'operation' => 'mentor_validation',
            ]);

            $errors = [];
            $validationContext = [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'company_name' => $mentor->getCompanyName(),
            ];

            // Check email uniqueness
            try {
                $existingMentor = $this->mentorRepository->findByEmail($mentor->getEmail());
                if ($existingMentor && $existingMentor->getId() !== $mentor->getId()) {
                    $errors['email'] = 'Cette adresse email est déjà utilisée.';
                    $this->logger->warning('Email already exists for different mentor', [
                        'existing_mentor_id' => $existingMentor->getId(),
                        'current_mentor_id' => $mentor->getId(),
                        'email' => $mentor->getEmail(),
                    ]);
                }
            } catch (Exception $e) {
                $this->logger->error('Failed to check email uniqueness', [
                    'error' => $e->getMessage(),
                    'email' => $mentor->getEmail(),
                ]);
                $errors['email'] = 'Erreur lors de la vérification de l\'email.';
            }

            // Validate email format
            if (!filter_var($mentor->getEmail(), FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Format d\'email invalide.';
                $this->logger->warning('Invalid email format', [
                    'email' => $mentor->getEmail(),
                    'mentor_id' => $mentor->getId(),
                ]);
            }

            // Check SIRET uniqueness and format
            if ($mentor->getCompanySiret()) {
                try {
                    $existingSiret = $this->mentorRepository->findByCompanySiret($mentor->getCompanySiret());
                    if ($existingSiret && $existingSiret->getId() !== $mentor->getId()) {
                        $errors['companySiret'] = 'Ce SIRET est déjà utilisé par un autre mentor.';
                        $this->logger->warning('SIRET already exists for different mentor', [
                            'existing_mentor_id' => $existingSiret->getId(),
                            'current_mentor_id' => $mentor->getId(),
                            'siret' => $mentor->getCompanySiret(),
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Failed to check SIRET uniqueness', [
                        'error' => $e->getMessage(),
                        'siret' => $mentor->getCompanySiret(),
                    ]);
                    $errors['companySiret'] = 'Erreur lors de la vérification du SIRET.';
                }

                if (!preg_match('/^\d{14}$/', $mentor->getCompanySiret())) {
                    $errors['companySiret'] = 'Le SIRET doit contenir exactement 14 chiffres.';
                    $this->logger->warning('Invalid SIRET format', [
                        'siret' => $mentor->getCompanySiret(),
                        'mentor_id' => $mentor->getId(),
                    ]);
                }
            }

            // Validate required fields
            $requiredFields = [
                'firstName' => [$mentor->getFirstName(), 'Le prénom est obligatoire.'],
                'lastName' => [$mentor->getLastName(), 'Le nom est obligatoire.'],
                'position' => [$mentor->getPosition(), 'Le poste est obligatoire.'],
                'companyName' => [$mentor->getCompanyName(), 'Le nom de l\'entreprise est obligatoire.'],
                'educationLevel' => [$mentor->getEducationLevel(), 'Le niveau de formation est obligatoire.'],
            ];

            foreach ($requiredFields as $fieldName => [$value, $errorMessage]) {
                if (empty($value)) {
                    $errors[$fieldName] = $errorMessage;
                    $this->logger->warning('Required field is empty', [
                        'field' => $fieldName,
                        'mentor_id' => $mentor->getId(),
                    ]);
                }
            }

            // Validate expertise domains
            if (empty($mentor->getExpertiseDomains())) {
                $errors['expertiseDomains'] = 'Au moins un domaine d\'expertise doit être sélectionné.';
                $this->logger->warning('No expertise domains selected', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } else {
                $this->logger->debug('Expertise domains validated', [
                    'domains_count' => count($mentor->getExpertiseDomains()),
                    'domains' => $mentor->getExpertiseDomains(),
                ]);
            }

            // Validate experience years
            if ($mentor->getExperienceYears() === null || $mentor->getExperienceYears() < 0) {
                $errors['experienceYears'] = 'L\'expérience en années est obligatoire.';
                $this->logger->warning('Invalid experience years', [
                    'experience_years' => $mentor->getExperienceYears(),
                    'mentor_id' => $mentor->getId(),
                ]);
            } elseif ($mentor->getExperienceYears() > 60) {
                $this->logger->warning('Very high experience years value', [
                    'experience_years' => $mentor->getExperienceYears(),
                    'mentor_id' => $mentor->getId(),
                ]);
            }

            $this->logger->info('Mentor data validation completed', [
                'mentor_id' => $mentor->getId(),
                'errors_count' => count($errors),
                'validation_result' => count($errors) === 0 ? 'valid' : 'invalid',
                'error_fields' => array_keys($errors),
            ]);

            return $errors;
        } catch (Exception $e) {
            $this->logger->error('Failed to validate mentor data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => $mentor->getId(),
                'operation' => 'mentor_validation',
            ]);

            return ['general' => 'Erreur lors de la validation des données.'];
        }
    }

    /**
     * Find available mentors for apprentice matching.
     */
    public function findAvailableMentorsForMatching(array $criteria = [], int $maxApprenticesPerMentor = 3): array
    {
        try {
            $this->logger->info('Starting mentor matching process', [
                'criteria' => $criteria,
                'max_apprentices_per_mentor' => $maxApprenticesPerMentor,
                'operation' => 'mentor_matching',
            ]);

            // For now, return active mentors since Alternant entity doesn't exist yet
            $mentors = $this->mentorRepository->findActive();
            $this->logger->debug('Active mentors retrieved', [
                'active_mentors_count' => count($mentors),
            ]);

            $initialCount = count($mentors);

            // Filter by criteria if provided
            if (!empty($criteria['expertise_domains'])) {
                $this->logger->debug('Filtering by expertise domains', [
                    'required_domains' => $criteria['expertise_domains'],
                ]);

                $mentors = array_filter($mentors, static function (Mentor $mentor) use ($criteria) {
                    foreach ($criteria['expertise_domains'] as $domain) {
                        if ($mentor->hasExpertiseDomain($domain)) {
                            return true;
                        }
                    }

                    return false;
                });

                $this->logger->debug('Filtered by expertise domains', [
                    'before_count' => $initialCount,
                    'after_count' => count($mentors),
                    'filtered_out' => $initialCount - count($mentors),
                ]);
            }

            if (!empty($criteria['min_experience'])) {
                $this->logger->debug('Filtering by minimum experience', [
                    'min_experience' => $criteria['min_experience'],
                ]);

                $beforeExperienceFilter = count($mentors);
                $mentors = array_filter($mentors, static fn (Mentor $mentor) => $mentor->getExperienceYears() >= $criteria['min_experience']);

                $this->logger->debug('Filtered by minimum experience', [
                    'before_count' => $beforeExperienceFilter,
                    'after_count' => count($mentors),
                    'filtered_out' => $beforeExperienceFilter - count($mentors),
                ]);
            }

            if (!empty($criteria['education_level'])) {
                $this->logger->debug('Filtering by education level', [
                    'required_education_level' => $criteria['education_level'],
                ]);

                $beforeEducationFilter = count($mentors);
                $mentors = array_filter($mentors, static fn (Mentor $mentor) => $mentor->getEducationLevel() === $criteria['education_level']);

                $this->logger->debug('Filtered by education level', [
                    'before_count' => $beforeEducationFilter,
                    'after_count' => count($mentors),
                    'filtered_out' => $beforeEducationFilter - count($mentors),
                ]);
            }

            if (!empty($criteria['company_name'])) {
                $this->logger->debug('Filtering by company name', [
                    'company_name' => $criteria['company_name'],
                ]);

                $beforeCompanyFilter = count($mentors);
                $mentors = array_filter($mentors, static fn (Mentor $mentor) => $mentor->getCompanyName() === $criteria['company_name']);

                $this->logger->debug('Filtered by company name', [
                    'before_count' => $beforeCompanyFilter,
                    'after_count' => count($mentors),
                    'filtered_out' => $beforeCompanyFilter - count($mentors),
                ]);
            }

            $finalMentors = array_values($mentors);

            $this->logger->info('Mentor matching completed', [
                'initial_count' => $initialCount,
                'final_count' => count($finalMentors),
                'total_filtered_out' => $initialCount - count($finalMentors),
                'matching_criteria_applied' => array_keys(array_filter($criteria)),
                'operation_result' => 'success',
            ]);

            // Log matched mentor details for debugging
            if (count($finalMentors) > 0) {
                $matchedMentorsInfo = array_map(static fn ($mentor) => [
                    'id' => $mentor->getId(),
                    'company' => $mentor->getCompanyName(),
                    'experience_years' => $mentor->getExperienceYears(),
                    'expertise_domains' => $mentor->getExpertiseDomains(),
                ], array_slice($finalMentors, 0, 5)); // Log first 5 for brevity

                $this->logger->debug('Sample of matched mentors', [
                    'sample_mentors' => $matchedMentorsInfo,
                    'showing_first' => min(5, count($finalMentors)),
                    'total_matched' => count($finalMentors),
                ]);
            }

            return $finalMentors;
        } catch (Exception $e) {
            $this->logger->error('Failed to find available mentors for matching', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'criteria' => $criteria,
                'max_apprentices_per_mentor' => $maxApprenticesPerMentor,
                'operation' => 'mentor_matching',
            ]);

            // Return empty array as fallback
            return [];
        }
    }

    /**
     * Get mentor statistics for specific company.
     */
    public function getCompanyStatistics(string $companyName): array
    {
        try {
            $this->logger->info('Retrieving company statistics', [
                'company_name' => $companyName,
                'operation' => 'company_statistics',
            ]);

            if (empty($companyName)) {
                throw new InvalidArgumentException('Company name cannot be empty');
            }

            $mentors = $this->mentorRepository->findByCompanyName($companyName);
            $mentorsCount = count($mentors);

            $this->logger->debug('Mentors found for company', [
                'company_name' => $companyName,
                'mentors_count' => $mentorsCount,
            ]);

            if ($mentorsCount === 0) {
                $this->logger->warning('No mentors found for company', [
                    'company_name' => $companyName,
                ]);

                return [
                    'total_mentors' => 0,
                    'active_mentors' => 0,
                    'verified_mentors' => 0,
                    'average_experience' => 0,
                    'expertise_domains' => [],
                ];
            }

            $activeMentors = array_filter($mentors, static fn ($m) => $m->isActive());
            $verifiedMentors = array_filter($mentors, static fn ($m) => $m->isEmailVerified());

            $experienceSum = array_sum(array_map(static fn ($m) => $m->getExperienceYears(), $mentors));
            $averageExperience = $mentorsCount > 0 ? $experienceSum / $mentorsCount : 0;

            $expertiseDomains = $this->getCompanyExpertiseDomains($mentors);

            $statistics = [
                'total_mentors' => $mentorsCount,
                'active_mentors' => count($activeMentors),
                'verified_mentors' => count($verifiedMentors),
                'average_experience' => round($averageExperience, 2),
                'expertise_domains' => $expertiseDomains,
            ];

            $this->logger->info('Company statistics calculated successfully', [
                'company_name' => $companyName,
                'statistics' => $statistics,
                'operation_result' => 'success',
            ]);

            return $statistics;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve company statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'company_name' => $companyName,
                'operation' => 'company_statistics',
            ]);

            // Return empty statistics as fallback
            return [
                'total_mentors' => 0,
                'active_mentors' => 0,
                'verified_mentors' => 0,
                'average_experience' => 0,
                'expertise_domains' => [],
            ];
        }
    }

    /**
     * Check if mentor can supervise new apprentice.
     */
    public function canSuperviseNewApprentice(Mentor $mentor, int $maxApprenticesPerMentor = 3): bool
    {
        try {
            $this->logger->info('Checking if mentor can supervise new apprentice', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'max_apprentices_per_mentor' => $maxApprenticesPerMentor,
                'operation' => 'apprentice_supervision_check',
            ]);

            // Check basic mentor requirements
            if (!$mentor->isActive()) {
                $this->logger->warning('Mentor is not active', [
                    'mentor_id' => $mentor->getId(),
                    'is_active' => false,
                ]);

                return false;
            }

            if (!$mentor->isEmailVerified()) {
                $this->logger->warning('Mentor email is not verified', [
                    'mentor_id' => $mentor->getId(),
                    'is_email_verified' => false,
                ]);

                return false;
            }

            // TODO: Implement when Alternant entity is created
            // For now, always return true since we can't count current apprentices
            $canSupervise = true;

            $this->logger->info('Mentor supervision check completed', [
                'mentor_id' => $mentor->getId(),
                'can_supervise' => $canSupervise,
                'is_active' => $mentor->isActive(),
                'is_email_verified' => $mentor->isEmailVerified(),
                'operation_result' => 'success',
            ]);

            return $canSupervise;
        } catch (Exception $e) {
            $this->logger->error('Failed to check if mentor can supervise apprentice', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => $mentor->getId(),
                'max_apprentices_per_mentor' => $maxApprenticesPerMentor,
                'operation' => 'apprentice_supervision_check',
            ]);

            // Return false as safe fallback
            return false;
        }
    }

    /**
     * Send apprentice assignment notification to mentor.
     */
    public function sendApprenticeAssignmentNotification(Mentor $mentor, array $apprenticeData): bool
    {
        try {
            $this->logger->info('Sending apprentice assignment notification to mentor', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'apprentice_data_keys' => array_keys($apprenticeData),
                'operation' => 'apprentice_assignment_notification',
            ]);

            // Validate mentor
            if (!$mentor->getId()) {
                throw new InvalidArgumentException('Mentor must have an ID');
            }

            if (empty($mentor->getEmail())) {
                throw new InvalidArgumentException('Mentor must have an email address');
            }

            if (!$mentor->isActive()) {
                $this->logger->warning('Sending notification to inactive mentor', [
                    'mentor_id' => $mentor->getId(),
                    'is_active' => false,
                ]);
            }

            // Validate apprentice data
            if (empty($apprenticeData)) {
                throw new InvalidArgumentException('Apprentice data cannot be empty');
            }

            $requiredFields = ['name', 'formation'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($apprenticeData[$field]) || empty($apprenticeData[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $this->logger->warning('Missing required apprentice data fields', [
                    'mentor_id' => $mentor->getId(),
                    'missing_fields' => $missingFields,
                    'provided_fields' => array_keys($apprenticeData),
                ]);
            }

            $this->logger->debug('Apprentice assignment notification data validated', [
                'mentor_id' => $mentor->getId(),
                'apprentice_data_size' => count($apprenticeData),
                'apprentice_name' => $apprenticeData['name'] ?? 'unknown',
                'apprentice_formation' => $apprenticeData['formation'] ?? 'unknown',
            ]);

            // Generate dashboard URL
            try {
                $dashboardUrl = $this->urlGenerator->generate(
                    'mentor_dashboard',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $this->logger->debug('Dashboard URL generated for apprentice assignment notification', [
                    'mentor_id' => $mentor->getId(),
                    'url_length' => strlen($dashboardUrl),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate dashboard URL for apprentice assignment', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to generate dashboard URL: ' . $e->getMessage());
            }

            // Create and send email
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                    ->subject('Nouvel alternant assigné - EPROFOS')
                    ->htmlTemplate('emails/mentor_apprentice_assignment.html.twig')
                    ->context([
                        'mentor' => $mentor,
                        'apprentice_data' => $apprenticeData,
                        'dashboard_url' => $dashboardUrl,
                    ])
                ;

                $this->logger->debug('Apprentice assignment notification email prepared', [
                    'mentor_id' => $mentor->getId(),
                    'recipient' => $mentor->getEmail(),
                    'template' => 'emails/mentor_apprentice_assignment.html.twig',
                    'apprentice_name' => $apprenticeData['name'] ?? 'unknown',
                ]);

                $this->mailer->send($email);
                $this->logger->debug('Apprentice assignment notification sent to mailer', [
                    'mentor_id' => $mentor->getId(),
                    'recipient' => $mentor->getEmail(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to send apprentice assignment notification email', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $mentor->getEmail(),
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to send apprentice assignment notification: ' . $e->getMessage());
            }

            $this->logger->info('Apprentice assignment notification sent successfully', [
                'mentor_id' => $mentor->getId(),
                'apprentice_name' => $apprenticeData['name'] ?? 'unknown',
                'operation_result' => 'success',
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send apprentice assignment notification', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'apprentice_data_keys' => array_keys($apprenticeData),
                'operation' => 'apprentice_assignment_notification',
            ]);

            return false;
        }
    }

    /**
     * Set configuration parameters.
     */
    public function setEmailConfig(string $fromEmail, string $fromName, string $adminEmail): void
    {
        try {
            $this->logger->info('Updating email configuration', [
                'previous_from_email' => $this->fromEmail,
                'previous_admin_email' => $this->adminEmail,
                'new_from_email' => $fromEmail,
                'new_admin_email' => $adminEmail,
                'operation' => 'email_config_update',
            ]);

            // Validate email addresses
            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid from email format: {$fromEmail}");
            }

            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid admin email format: {$adminEmail}");
            }

            // Validate from name
            if (empty(trim($fromName))) {
                throw new InvalidArgumentException('From name cannot be empty');
            }

            $this->fromEmail = $fromEmail;
            $this->fromName = $fromName;
            $this->adminEmail = $adminEmail;

            $this->logger->info('Email configuration updated successfully', [
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'admin_email' => $this->adminEmail,
                'operation_result' => 'success',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update email configuration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempted_from_email' => $fromEmail,
                'attempted_admin_email' => $adminEmail,
                'operation' => 'email_config_update',
            ]);

            throw $e;
        }
    }

    /**
     * Calculate performance metrics for a mentor.
     */
    public function calculatePerformanceMetrics(Mentor $mentor): array
    {
        try {
            $this->logger->info('Calculating performance metrics for mentor', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'operation' => 'performance_metrics_calculation',
            ]);

            // Placeholder implementation until we have proper relationships
            $metrics = [
                'alternants_count' => 0,
                'active_contracts' => 0,
                'missions_count' => 0,
                'evaluations_count' => 0,
                'satisfaction_rate' => 0,
                'success_rate' => 0,
            ];

            $this->logger->debug('Performance metrics calculated (placeholder)', [
                'mentor_id' => $mentor->getId(),
                'metrics' => $metrics,
                'note' => 'Using placeholder values until proper relationships are implemented',
            ]);

            // TODO: Implement actual calculations when relationships are available:
            // - Count associated alternants
            // - Count active contracts
            // - Count assigned missions
            // - Calculate satisfaction rates from evaluations
            // - Calculate success rates based on completed objectives

            $this->logger->info('Performance metrics calculation completed', [
                'mentor_id' => $mentor->getId(),
                'metrics_keys' => array_keys($metrics),
                'operation_result' => 'success',
            ]);

            return $metrics;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate performance metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => $mentor->getId(),
                'operation' => 'performance_metrics_calculation',
            ]);

            // Return default metrics as fallback
            return [
                'alternants_count' => 0,
                'active_contracts' => 0,
                'missions_count' => 0,
                'evaluations_count' => 0,
                'satisfaction_rate' => 0,
                'success_rate' => 0,
            ];
        }
    }

    /**
     * Get recent activity for a mentor.
     */
    public function getRecentActivity(Mentor $mentor): array
    {
        try {
            $this->logger->info('Retrieving recent activity for mentor', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'operation' => 'recent_activity_retrieval',
            ]);

            // Placeholder implementation - return empty array for now
            $activities = [];

            $this->logger->debug('Recent activity retrieved (placeholder)', [
                'mentor_id' => $mentor->getId(),
                'activities_count' => count($activities),
                'note' => 'Using placeholder implementation until activity tracking is implemented',
            ]);

            // TODO: Implement actual activity retrieval when activity entities are available:
            // - Recent login activities
            // - Recent apprentice interactions
            // - Recent mission assignments
            // - Recent evaluations submitted
            // - Recent profile updates

            $this->logger->info('Recent activity retrieval completed', [
                'mentor_id' => $mentor->getId(),
                'activities_count' => count($activities),
                'operation_result' => 'success',
            ]);

            return $activities;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve recent activity for mentor', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => $mentor->getId(),
                'operation' => 'recent_activity_retrieval',
            ]);

            // Return empty array as fallback
            return [];
        }
    }

    /**
     * Get detailed performance metrics for a mentor over a period.
     */
    public function getDetailedPerformance(Mentor $mentor, int $months): array
    {
        try {
            $this->logger->info('Retrieving detailed performance metrics for mentor', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'period_months' => $months,
                'operation' => 'detailed_performance_retrieval',
            ]);

            if ($months <= 0) {
                throw new InvalidArgumentException('Period in months must be positive');
            }

            if ($months > 60) {
                $this->logger->warning('Very long period requested for performance metrics', [
                    'mentor_id' => $mentor->getId(),
                    'requested_months' => $months,
                    'note' => 'Consider shorter periods for better performance',
                ]);
            }

            // Placeholder implementation - return minimal structure
            $performance = [
                'alternants_count' => 0,
                'success_rate' => 0,
                'satisfaction_rate' => 0,
                'evaluations_count' => 0,
            ];

            $this->logger->debug('Detailed performance metrics retrieved (placeholder)', [
                'mentor_id' => $mentor->getId(),
                'period_months' => $months,
                'performance_keys' => array_keys($performance),
                'note' => 'Using placeholder values until detailed metrics are implemented',
            ]);

            // TODO: Implement actual detailed performance calculation:
            // - Count alternants supervised in the given period
            // - Calculate success rates based on completed objectives
            // - Calculate satisfaction rates from evaluation feedback
            // - Count total evaluations submitted
            // - Include trend analysis over the period

            $this->logger->info('Detailed performance retrieval completed', [
                'mentor_id' => $mentor->getId(),
                'period_months' => $months,
                'performance_metrics_count' => count($performance),
                'operation_result' => 'success',
            ]);

            return $performance;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve detailed performance metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mentor_id' => $mentor->getId(),
                'period_months' => $months,
                'operation' => 'detailed_performance_retrieval',
            ]);

            // Return minimal fallback structure
            return [
                'alternants_count' => 0,
                'success_rate' => 0,
                'satisfaction_rate' => 0,
                'evaluations_count' => 0,
            ];
        }
    }

    /**
     * Export mentors to various formats.
     */
    public function exportMentors(array $mentors, string $format): string
    {
        try {
            $this->logger->info('Starting mentor export', [
                'mentors_count' => count($mentors),
                'format' => $format,
                'operation' => 'mentor_export',
            ]);

            if (empty($mentors)) {
                $this->logger->warning('No mentors provided for export', [
                    'format' => $format,
                ]);
            }

            $supportedFormats = ['csv', 'xlsx'];
            if (!in_array(strtolower($format), $supportedFormats, true)) {
                throw new InvalidArgumentException("Unsupported export format: {$format}. Supported formats: " . implode(', ', $supportedFormats));
            }

            $this->logger->debug('Export format validated', [
                'requested_format' => $format,
                'supported_formats' => $supportedFormats,
            ]);

            $result = match (strtolower($format)) {
                'csv' => $this->exportToCsv($mentors),
                'xlsx' => $this->exportToExcel($mentors),
                default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
            };

            $this->logger->info('Mentor export completed successfully', [
                'mentors_count' => count($mentors),
                'format' => $format,
                'result_size_bytes' => strlen($result),
                'operation_result' => 'success',
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to export mentors', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mentors_count' => count($mentors),
                'format' => $format,
                'operation' => 'mentor_export',
            ]);

            throw $e;
        }
    }

    /**
     * Log comprehensive mentor operation context.
     */
    private function logMentorOperationContext(string $operation, Mentor $mentor, array $additionalContext = []): void
    {
        try {
            $baseContext = [
                'operation' => $operation,
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mentor_full_name' => $mentor->getFullName(),
                'mentor_company' => $mentor->getCompanyName(),
                'mentor_is_active' => $mentor->isActive(),
                'mentor_is_email_verified' => $mentor->isEmailVerified(),
                'mentor_created_at' => $mentor->getCreatedAt()?->format('Y-m-d H:i:s'),
                'mentor_last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ];

            $fullContext = array_merge($baseContext, $additionalContext);

            $this->logger->debug('Mentor operation context', $fullContext);
        } catch (Exception $e) {
            $this->logger->warning('Failed to log mentor operation context', [
                'operation' => $operation,
                'mentor_id' => $mentor->getId() ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate email configuration before sending emails.
     */
    private function validateEmailConfiguration(): void
    {
        try {
            $this->logger->debug('Validating email configuration', [
                'from_email' => $this->fromEmail,
                'from_name_length' => strlen($this->fromName),
                'admin_email' => $this->adminEmail,
                'operation' => 'email_config_validation',
            ]);

            if (empty($this->fromEmail) || !filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid from email configuration: {$this->fromEmail}");
            }

            if (empty($this->fromName)) {
                throw new InvalidArgumentException('From name is not configured');
            }

            if (empty($this->adminEmail) || !filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid admin email configuration: {$this->adminEmail}");
            }

            $this->logger->debug('Email configuration validation passed', [
                'from_email_valid' => true,
                'from_name_configured' => !empty($this->fromName),
                'admin_email_valid' => true,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Email configuration validation failed', [
                'error' => $e->getMessage(),
                'from_email' => $this->fromEmail,
                'admin_email' => $this->adminEmail,
                'operation' => 'email_config_validation',
            ]);

            throw $e;
        }
    }

    /**
     * Log system performance metrics for debugging.
     */
    private function logPerformanceMetrics(string $operation, float $startTime, array $additionalMetrics = []): void
    {
        try {
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
            $memoryUsage = memory_get_usage(true);
            $peakMemoryUsage = memory_get_peak_usage(true);

            $performanceContext = [
                'operation' => $operation,
                'execution_time_ms' => $executionTime,
                'memory_usage_bytes' => $memoryUsage,
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'peak_memory_usage_bytes' => $peakMemoryUsage,
                'peak_memory_usage_mb' => round($peakMemoryUsage / 1024 / 1024, 2),
                'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ];

            $fullContext = array_merge($performanceContext, $additionalMetrics);

            // Log as info for operations taking longer than 1 second
            if ($executionTime > 1000) {
                $this->logger->info('Performance metrics (slow operation)', $fullContext);
            } else {
                $this->logger->debug('Performance metrics', $fullContext);
            }
        } catch (Exception $e) {
            $this->logger->warning('Failed to log performance metrics', [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle critical errors and send notifications to administrators.
     */
    private function handleCriticalError(string $operation, Exception $exception, array $context = []): void
    {
        try {
            $errorContext = [
                'operation' => $operation,
                'error_type' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine(),
                'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ];

            $fullContext = array_merge($errorContext, $context);

            // Log critical error
            $this->logger->critical('Critical error in MentorService', $fullContext);

            // For critical operations, also try to send email notification to admin
            $criticalOperations = [
                'welcome_email',
                'password_reset_email',
                'email_verification',
                'mentor_validation',
                'csv_export',
            ];

            if (in_array($operation, $criticalOperations, true)) {
                $this->sendCriticalErrorNotificationToAdmin($operation, $exception, $fullContext);
            }
        } catch (Exception $e) {
            // Even if error handling fails, we should log it
            $this->logger->emergency('Failed to handle critical error', [
                'original_operation' => $operation,
                'original_error' => $exception->getMessage(),
                'error_handling_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send critical error notification to administrators.
     */
    private function sendCriticalErrorNotificationToAdmin(string $operation, Exception $exception, array $context): void
    {
        try {
            $this->logger->debug('Attempting to send critical error notification to admin', [
                'operation' => $operation,
                'admin_email' => $this->adminEmail,
            ]);

            // Only send if we have a valid admin email and mailer service
            if (empty($this->adminEmail) || !filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Cannot send critical error notification: invalid admin email', [
                    'admin_email' => $this->adminEmail,
                ]);

                return;
            }

            // Create simplified error email
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                ->subject("Erreur critique - MentorService - {$operation}")
                ->text("Une erreur critique s'est produite dans MentorService.\n\n" .
                       "Opération: {$operation}\n" .
                       "Erreur: {$exception->getMessage()}\n" .
                       "Fichier: {$exception->getFile()}:{$exception->getLine()}\n" .
                       'Timestamp: ' . (new DateTimeImmutable())->format('Y-m-d H:i:s'))
            ;

            $this->mailer->send($email);

            $this->logger->info('Critical error notification sent to admin', [
                'operation' => $operation,
                'admin_email' => $this->adminEmail,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to send critical error notification to admin', [
                'operation' => $operation,
                'original_error' => $exception->getMessage(),
                'notification_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get expertise domains for a company's mentors.
     */
    private function getCompanyExpertiseDomains(array $mentors): array
    {
        try {
            $this->logger->debug('Calculating expertise domains for company mentors', [
                'mentors_count' => count($mentors),
                'operation' => 'expertise_domains_calculation',
            ]);

            $domains = [];
            $processedMentors = 0;

            foreach ($mentors as $mentor) {
                try {
                    $mentorDomains = $mentor->getExpertiseDomains();
                    foreach ($mentorDomains as $domain) {
                        if (!isset($domains[$domain])) {
                            $domains[$domain] = 0;
                        }
                        $domains[$domain]++;
                    }
                    $processedMentors++;
                } catch (Exception $e) {
                    $this->logger->warning('Failed to process mentor domains', [
                        'mentor_id' => $mentor->getId() ?? 'unknown',
                        'error' => $e->getMessage(),
                        'skipping_mentor' => true,
                    ]);

                    continue;
                }
            }

            arsort($domains);

            $this->logger->debug('Expertise domains calculation completed', [
                'processed_mentors' => $processedMentors,
                'unique_domains' => count($domains),
                'top_domain' => !empty($domains) ? array_key_first($domains) : null,
                'top_domain_count' => !empty($domains) ? reset($domains) : 0,
            ]);

            return $domains;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate expertise domains', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mentors_count' => count($mentors),
                'operation' => 'expertise_domains_calculation',
            ]);

            return [];
        }
    }

    /**
     * Export mentors to Excel format.
     */
    private function exportToExcel(array $mentors): string
    {
        try {
            $this->logger->info('Starting Excel export for mentors', [
                'mentors_count' => count($mentors),
                'operation' => 'excel_export',
            ]);

            // For now, return CSV format as Excel is not yet implemented
            $this->logger->warning('Excel export not yet implemented, falling back to CSV', [
                'mentors_count' => count($mentors),
                'fallback_format' => 'csv',
            ]);

            $result = $this->exportToCsv($mentors);

            $this->logger->info('Excel export completed (using CSV fallback)', [
                'mentors_count' => count($mentors),
                'result_size_bytes' => strlen($result),
                'operation_result' => 'success_with_fallback',
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to export mentors to Excel', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mentors_count' => count($mentors),
                'operation' => 'excel_export',
            ]);

            throw new Exception('Excel export failed: ' . $e->getMessage());
        }
    }
}
