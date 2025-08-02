<?php

declare(strict_types=1);

namespace App\Service\Analysis;

use App\Entity\Analysis\NeedsAnalysisRequest;
use App\Entity\CRM\ContactRequest;
use App\Entity\Training\SessionRegistration;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Analysis Email Notification Service.
 *
 * Handles all email notifications related to needs analysis requests.
 * Manages template rendering, email sending, and notification tracking.
 */
class AnalysisEmailNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $fromEmail = 'noreply@eprofos.com',
        private string $fromName = 'EPROFOS - École Professionnelle de Formation Spécialisée',
        private string $adminEmail = 'admin@eprofos.com',
    ) {}

    /**
     * Send needs analysis request to recipient.
     */
    public function sendNeedsAnalysisRequest(NeedsAnalysisRequest $request): bool
    {
        $requestId = $request->getId();
        $recipientEmail = $request->getRecipientEmail();
        $recipientName = $request->getRecipientName();
        $requestType = $request->getType();

        try {
            $this->logger->info('Starting needs analysis request email process', [
                'request_id' => $requestId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'type' => $requestType,
                'company_name' => $request->getCompanyName(),
                'formation_id' => $request->getFormation()?->getId(),
                'formation_title' => $request->getFormation()?->getTitle(),
                'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
                'token' => substr($request->getToken(), 0, 8) . '...',
                'created_at' => $request->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);

            // Validate request data before processing
            if (empty($recipientEmail)) {
                throw new Exception('Recipient email is required');
            }

            if (empty($recipientName)) {
                throw new Exception('Recipient name is required');
            }

            if (empty($request->getToken())) {
                throw new Exception('Request token is required');
            }

            $this->logger->debug('Request validation passed', [
                'request_id' => $requestId,
            ]);

            // Generate the public URL for the form
            $this->logger->debug('Generating form URL', [
                'request_id' => $requestId,
                'token' => substr($request->getToken(), 0, 8) . '...',
            ]);

            $formUrl = $this->urlGenerator->generate(
                'needs_analysis_public_form',
                ['token' => $request->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->info('Form URL generated successfully', [
                'request_id' => $requestId,
                'form_url_length' => strlen($formUrl),
                'url_host' => parse_url($formUrl, PHP_URL_HOST),
            ]);

            // Prepare email context
            $emailContext = [
                'request' => $request,
                'form_url' => $formUrl,
                'expires_at' => $request->getExpiresAt(),
                'company_name' => $request->getCompanyName(),
                'formation' => $request->getFormation(),
                'type_label' => $request->getTypeLabel(),
            ];

            $this->logger->debug('Email context prepared', [
                'request_id' => $requestId,
                'context_keys' => array_keys($emailContext),
                'has_formation' => $request->getFormation() !== null,
                'has_company_name' => !empty($request->getCompanyName()),
            ]);

            // Generate email subject
            $emailSubject = $this->getRequestEmailSubject($request);
            $this->logger->debug('Email subject generated', [
                'request_id' => $requestId,
                'subject' => $emailSubject,
                'subject_length' => strlen($emailSubject),
            ]);

            // Create email
            $this->logger->debug('Creating email object', [
                'request_id' => $requestId,
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'to_email' => $recipientEmail,
                'to_name' => $recipientName,
                'template' => 'emails/needs_analysis_sent.html.twig',
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipientEmail, $recipientName))
                ->subject($emailSubject)
                ->htmlTemplate('emails/needs_analysis_sent.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Email object created successfully', [
                'request_id' => $requestId,
                'email_class' => get_class($email),
            ]);

            // Send email
            $this->logger->info('Attempting to send email via mailer', [
                'request_id' => $requestId,
                'mailer_class' => get_class($this->mailer),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Needs analysis request email sent successfully', [
                'request_id' => $requestId,
                'recipient_email' => $recipientEmail,
                'subject' => $emailSubject,
                'form_url_host' => parse_url($formUrl, PHP_URL_HOST),
                'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
                'processing_time' => 'completed',
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send needs analysis request email', [
                'request_id' => $requestId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'request_type' => $requestType,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'company_name' => $request->getCompanyName(),
                'formation_id' => $request->getFormation()?->getId(),
                'token_exists' => !empty($request->getToken()),
                'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
            ]);

            // Log specific error types for better debugging
            if (strpos($e->getMessage(), 'Connection') !== false) {
                $this->logger->critical('Email connection error detected', [
                    'request_id' => $requestId,
                    'error_type' => 'connection_error',
                    'mailer_class' => get_class($this->mailer),
                ]);
            } elseif (strpos($e->getMessage(), 'template') !== false || strpos($e->getMessage(), 'twig') !== false) {
                $this->logger->critical('Template rendering error detected', [
                    'request_id' => $requestId,
                    'error_type' => 'template_error',
                    'template' => 'emails/needs_analysis_sent.html.twig',
                ]);
            } elseif (strpos($e->getMessage(), 'Address') !== false || strpos($e->getMessage(), 'email') !== false) {
                $this->logger->critical('Email address validation error detected', [
                    'request_id' => $requestId,
                    'error_type' => 'address_validation_error',
                    'recipient_email' => $recipientEmail,
                    'from_email' => $this->fromEmail,
                ]);
            }

            return false;
        }
    }

    /**
     * Send notification when analysis is completed.
     */
    public function sendAnalysisCompletedNotification(NeedsAnalysisRequest $request): bool
    {
        $requestId = $request->getId();
        $recipientEmail = $request->getRecipientEmail();
        $recipientName = $request->getRecipientName();
        
        try {
            $this->logger->info('Starting analysis completed notification process', [
                'request_id' => $requestId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'type' => $request->getType(),
                'company_name' => $request->getCompanyName(),
                'formation_id' => $request->getFormation()?->getId(),
                'has_company_analysis' => $request->getCompanyAnalysis() !== null,
                'has_individual_analysis' => $request->getIndividualAnalysis() !== null,
                'completed_at' => $request->getCompletedAt()?->format('Y-m-d H:i:s'),
            ]);

            // Validate required data
            if (empty($recipientEmail)) {
                throw new Exception('Recipient email is required for completed notification');
            }

            if (!$request->getCompanyAnalysis() && !$request->getIndividualAnalysis()) {
                throw new Exception('Analysis data is required to send completion notification');
            }

            $this->logger->debug('Validation passed for completed notification', [
                'request_id' => $requestId,
            ]);

            // Generate admin URL to view the analysis
            $this->logger->debug('Generating admin URL for analysis view', [
                'request_id' => $requestId,
            ]);

            $adminUrl = $this->urlGenerator->generate(
                'admin_needs_analysis_show',
                ['id' => $requestId],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->info('Admin URL generated successfully', [
                'request_id' => $requestId,
                'admin_url_length' => strlen($adminUrl),
                'url_host' => parse_url($adminUrl, PHP_URL_HOST),
            ]);

            // Prepare analysis data
            $analysisData = $request->getCompanyAnalysis() ?? $request->getIndividualAnalysis();
            $this->logger->debug('Analysis data prepared', [
                'request_id' => $requestId,
                'analysis_type' => $request->getCompanyAnalysis() ? 'company' : 'individual',
                'analysis_data_size' => is_array($analysisData) ? count($analysisData) : (is_string($analysisData) ? strlen($analysisData) : 'unknown'),
            ]);

            // Send to admin
            $this->logger->info('Preparing admin notification email', [
                'request_id' => $requestId,
                'admin_email' => $this->adminEmail,
            ]);

            $adminSubject = $this->getCompletedNotificationSubject($request);
            $this->logger->debug('Admin email subject generated', [
                'request_id' => $requestId,
                'admin_subject' => $adminSubject,
            ]);

            $adminEmail = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                ->subject($adminSubject)
                ->htmlTemplate('emails/analysis_completed_admin.html.twig')
                ->context([
                    'request' => $request,
                    'admin_url' => $adminUrl,
                    'analysis' => $analysisData,
                ])
            ;

            $this->logger->info('Sending admin notification email', [
                'request_id' => $requestId,
                'template' => 'emails/analysis_completed_admin.html.twig',
            ]);

            $this->mailer->send($adminEmail);

            $this->logger->info('Admin notification email sent successfully', [
                'request_id' => $requestId,
                'admin_email' => $this->adminEmail,
            ]);

            // Send confirmation to recipient
            $this->logger->info('Preparing recipient confirmation email', [
                'request_id' => $requestId,
                'recipient_email' => $recipientEmail,
            ]);

            $confirmationEmail = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipientEmail, $recipientName))
                ->subject('Confirmation de réception - Analyse des besoins EPROFOS')
                ->htmlTemplate('emails/analysis_completed_confirmation.html.twig')
                ->context([
                    'request' => $request,
                    'recipient_name' => $recipientName,
                    'company_name' => $request->getCompanyName(),
                ])
            ;

            $this->logger->info('Sending recipient confirmation email', [
                'request_id' => $requestId,
                'template' => 'emails/analysis_completed_confirmation.html.twig',
            ]);

            $this->mailer->send($confirmationEmail);

            $this->logger->info('Analysis completed notifications sent successfully', [
                'request_id' => $requestId,
                'admin_email_sent' => true,
                'confirmation_email_sent' => true,
                'total_emails_sent' => 2,
                'processing_completed' => true,
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send analysis completed notification', [
                'request_id' => $requestId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'admin_email' => $this->adminEmail,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_type' => $request->getType(),
                'has_company_analysis' => $request->getCompanyAnalysis() !== null,
                'has_individual_analysis' => $request->getIndividualAnalysis() !== null,
                'completed_at' => $request->getCompletedAt()?->format('Y-m-d H:i:s'),
            ]);

            // Log specific error context
            if (strpos($e->getMessage(), 'admin_needs_analysis_show') !== false) {
                $this->logger->critical('Admin route generation error', [
                    'request_id' => $requestId,
                    'error_type' => 'route_generation_error',
                    'route_name' => 'admin_needs_analysis_show',
                ]);
            }

            return false;
        }
    }

    /**
     * Send reminder for expiring requests.
     */
    public function sendExpirationReminder(NeedsAnalysisRequest $request): bool
    {
        $requestId = $request->getId();
        $recipientEmail = $request->getRecipientEmail();
        $recipientName = $request->getRecipientName();
        
        try {
            $this->logger->info('Starting expiration reminder process', [
                'request_id' => $requestId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
                'current_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'type' => $request->getType(),
                'company_name' => $request->getCompanyName(),
            ]);

            // Validate expiration data
            $expiresAt = $request->getExpiresAt();
            if (!$expiresAt) {
                throw new Exception('Request expiration date is required for reminder');
            }

            $now = new DateTimeImmutable();
            if ($expiresAt <= $now) {
                $this->logger->warning('Attempting to send reminder for already expired request', [
                    'request_id' => $requestId,
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                    'current_time' => $now->format('Y-m-d H:i:s'),
                ]);
                throw new Exception('Cannot send reminder for expired request');
            }

            $daysRemaining = $this->getDaysUntilExpiration($request);
            $this->logger->debug('Expiration validation passed', [
                'request_id' => $requestId,
                'days_remaining' => $daysRemaining,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            // Validate email data
            if (empty($recipientEmail)) {
                throw new Exception('Recipient email is required for reminder');
            }

            if (empty($request->getToken())) {
                throw new Exception('Request token is required for reminder URL');
            }

            // Generate the public URL for the form
            $this->logger->debug('Generating reminder form URL', [
                'request_id' => $requestId,
                'token' => substr($request->getToken(), 0, 8) . '...',
            ]);

            $formUrl = $this->urlGenerator->generate(
                'needs_analysis_public_form',
                ['token' => $request->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->info('Reminder form URL generated successfully', [
                'request_id' => $requestId,
                'form_url_length' => strlen($formUrl),
                'url_host' => parse_url($formUrl, PHP_URL_HOST),
            ]);

            // Prepare email context
            $emailContext = [
                'request' => $request,
                'form_url' => $formUrl,
                'expires_at' => $expiresAt,
                'days_remaining' => $daysRemaining,
            ];

            $this->logger->debug('Reminder email context prepared', [
                'request_id' => $requestId,
                'context_keys' => array_keys($emailContext),
                'days_remaining' => $daysRemaining,
            ]);

            $this->logger->info('Creating reminder email object', [
                'request_id' => $requestId,
                'from_email' => $this->fromEmail,
                'to_email' => $recipientEmail,
                'template' => 'emails/needs_analysis_reminder.html.twig',
                'days_remaining' => $daysRemaining,
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipientEmail, $recipientName))
                ->subject('Rappel - Analyse des besoins EPROFOS expire bientôt')
                ->htmlTemplate('emails/needs_analysis_reminder.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Sending reminder email via mailer', [
                'request_id' => $requestId,
                'mailer_class' => get_class($this->mailer),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Expiration reminder sent successfully', [
                'request_id' => $requestId,
                'recipient_email' => $recipientEmail,
                'days_remaining' => $daysRemaining,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'form_url_host' => parse_url($formUrl, PHP_URL_HOST),
                'processing_completed' => true,
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send expiration reminder', [
                'request_id' => $requestId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
                'current_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'token_exists' => !empty($request->getToken()),
                'request_type' => $request->getType(),
            ]);

            // Log specific error types
            if (strpos($e->getMessage(), 'expired') !== false) {
                $this->logger->warning('Reminder attempted for expired request', [
                    'request_id' => $requestId,
                    'error_type' => 'expired_request_error',
                ]);
            } elseif (strpos($e->getMessage(), 'token') !== false) {
                $this->logger->critical('Token-related error in reminder', [
                    'request_id' => $requestId,
                    'error_type' => 'token_error',
                    'has_token' => !empty($request->getToken()),
                ]);
            }

            return false;
        }
    }

    /**
     * Send weekly summary to admin.
     */
    public function sendWeeklySummary(array $statistics): bool
    {
        try {
            $this->logger->info('Starting weekly summary email process', [
                'admin_email' => $this->adminEmail,
                'statistics_keys' => array_keys($statistics),
                'statistics_count' => count($statistics),
                'week_start' => (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s'),
                'week_end' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            // Validate statistics data
            if (empty($statistics)) {
                $this->logger->warning('Empty statistics provided for weekly summary', [
                    'statistics_count' => 0,
                ]);
                // Continue anyway as empty summary might be valid
            }

            // Validate admin email
            if (empty($this->adminEmail)) {
                throw new Exception('Admin email is required for weekly summary');
            }

            $this->logger->debug('Weekly summary validation passed', [
                'admin_email' => $this->adminEmail,
                'has_statistics' => !empty($statistics),
            ]);

            // Prepare date range
            $weekStart = new DateTimeImmutable('-7 days');
            $weekEnd = new DateTimeImmutable();

            $this->logger->debug('Date range prepared for summary', [
                'week_start' => $weekStart->format('Y-m-d H:i:s'),
                'week_end' => $weekEnd->format('Y-m-d H:i:s'),
                'days_covered' => $weekStart->diff($weekEnd)->days,
            ]);

            // Log detailed statistics for debugging
            foreach ($statistics as $key => $value) {
                $this->logger->debug('Weekly summary statistic', [
                    'statistic_key' => $key,
                    'statistic_value' => $value,
                    'value_type' => gettype($value),
                ]);
            }

            // Prepare email context
            $emailContext = [
                'statistics' => $statistics,
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
            ];

            $this->logger->info('Creating weekly summary email', [
                'admin_email' => $this->adminEmail,
                'template' => 'emails/weekly_summary.html.twig',
                'context_keys' => array_keys($emailContext),
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                ->subject('Résumé hebdomadaire - Analyses des besoins EPROFOS')
                ->htmlTemplate('emails/weekly_summary.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Sending weekly summary email via mailer', [
                'admin_email' => $this->adminEmail,
                'mailer_class' => get_class($this->mailer),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Weekly summary sent successfully', [
                'admin_email' => $this->adminEmail,
                'statistics_count' => count($statistics),
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'processing_completed' => true,
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send weekly summary', [
                'admin_email' => $this->adminEmail,
                'statistics_count' => count($statistics ?? []),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'week_start' => (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s'),
                'week_end' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            // Log specific error context
            if (strpos($e->getMessage(), 'Admin email') !== false) {
                $this->logger->critical('Admin email configuration error', [
                    'error_type' => 'admin_email_missing',
                    'admin_email' => $this->adminEmail,
                ]);
            } elseif (strpos($e->getMessage(), 'template') !== false) {
                $this->logger->critical('Weekly summary template error', [
                    'error_type' => 'template_error',
                    'template' => 'emails/weekly_summary.html.twig',
                ]);
            }

            return false;
        }
    }

    /**
     * Send bulk reminders for expiring requests.
     */
    public function sendBulkExpirationReminders(array $requests): int
    {
        $sentCount = 0;
        $failedCount = 0;
        $totalRequests = count($requests);

        try {
            $this->logger->info('Starting bulk expiration reminders process', [
                'total_requests' => $totalRequests,
                'process_start_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            // Validate requests array
            if (empty($requests)) {
                $this->logger->warning('Empty requests array provided for bulk reminders', [
                    'total_requests' => 0,
                ]);
                return 0;
            }

            // Log requests details for debugging
            foreach ($requests as $index => $request) {
                if (!$request instanceof NeedsAnalysisRequest) {
                    $this->logger->error('Invalid request object in bulk reminders', [
                        'index' => $index,
                        'object_type' => get_class($request),
                        'expected_type' => NeedsAnalysisRequest::class,
                    ]);
                    $failedCount++;
                    continue;
                }

                $this->logger->debug('Processing bulk reminder request', [
                    'index' => $index,
                    'request_id' => $request->getId(),
                    'recipient_email' => $request->getRecipientEmail(),
                    'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
                    'type' => $request->getType(),
                ]);

                try {
                    $success = $this->sendExpirationReminder($request);
                    
                    if ($success) {
                        $sentCount++;
                        $this->logger->debug('Bulk reminder sent successfully', [
                            'index' => $index,
                            'request_id' => $request->getId(),
                            'recipient_email' => $request->getRecipientEmail(),
                        ]);
                    } else {
                        $failedCount++;
                        $this->logger->warning('Failed to send bulk reminder', [
                            'index' => $index,
                            'request_id' => $request->getId(),
                            'recipient_email' => $request->getRecipientEmail(),
                        ]);
                    }

                } catch (Exception $e) {
                    $failedCount++;
                    $this->logger->error('Exception during bulk reminder sending', [
                        'index' => $index,
                        'request_id' => $request->getId(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                    ]);
                }

                // Log progress every 10 requests
                if (($index + 1) % 10 === 0 || $index === $totalRequests - 1) {
                    $this->logger->info('Bulk reminders progress update', [
                        'processed' => $index + 1,
                        'total_requests' => $totalRequests,
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'progress_percentage' => round((($index + 1) / $totalRequests) * 100, 2),
                    ]);
                }
            }

            $this->logger->info('Bulk expiration reminders process completed', [
                'total_requests' => $totalRequests,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'success_rate' => $totalRequests > 0 ? round(($sentCount / $totalRequests) * 100, 2) : 0,
                'process_end_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

        } catch (Exception $e) {
            $this->logger->error('Fatal error during bulk expiration reminders process', [
                'total_requests' => $totalRequests,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $sentCount;
    }

    /**
     * Test email configuration.
     */
    public function sendTestEmail(string $toEmail): bool
    {
        try {
            $this->logger->info('Starting test email process', [
                'to_email' => $toEmail,
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'mailer_class' => get_class($this->mailer),
                'test_start_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            // Validate email address
            if (empty($toEmail)) {
                throw new Exception('Recipient email address is required for test email');
            }

            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address format: ' . $toEmail);
            }

            $this->logger->debug('Test email validation passed', [
                'to_email' => $toEmail,
                'email_format_valid' => true,
            ]);

            // Validate sender configuration
            if (empty($this->fromEmail)) {
                throw new Exception('From email address is not configured');
            }

            if (!filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid from email address format: ' . $this->fromEmail);
            }

            $this->logger->debug('Sender configuration validation passed', [
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
            ]);

            $sendTime = new DateTimeImmutable();
            $emailContext = [
                'sent_at' => $sendTime,
            ];

            $this->logger->info('Creating test email object', [
                'to_email' => $toEmail,
                'template' => 'emails/test_email.html.twig',
                'sent_at' => $sendTime->format('Y-m-d H:i:s'),
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($toEmail)
                ->subject('Test Email - EPROFOS')
                ->htmlTemplate('emails/test_email.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Sending test email via mailer', [
                'to_email' => $toEmail,
                'mailer_class' => get_class($this->mailer),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Test email sent successfully', [
                'to_email' => $toEmail,
                'from_email' => $this->fromEmail,
                'subject' => 'Test Email - EPROFOS',
                'template' => 'emails/test_email.html.twig',
                'sent_at' => $sendTime->format('Y-m-d H:i:s'),
                'processing_completed' => true,
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send test email', [
                'to_email' => $toEmail,
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'mailer_class' => get_class($this->mailer),
            ]);

            // Log specific error types for test emails
            if (strpos($e->getMessage(), 'email address') !== false) {
                $this->logger->critical('Email address validation error in test', [
                    'error_type' => 'email_validation_error',
                    'to_email' => $toEmail,
                    'from_email' => $this->fromEmail,
                ]);
            } elseif (strpos($e->getMessage(), 'Connection') !== false) {
                $this->logger->critical('Email connection error in test', [
                    'error_type' => 'connection_error',
                    'mailer_class' => get_class($this->mailer),
                ]);
            } elseif (strpos($e->getMessage(), 'template') !== false) {
                $this->logger->critical('Template error in test email', [
                    'error_type' => 'template_error',
                    'template' => 'emails/test_email.html.twig',
                ]);
            }

            return false;
        }
    }

    /**
     * Send legal documents delivery notification to participant.
     */
    public function sendDocumentDelivery(SessionRegistration $registration, array $documents): bool
    {
        $registrationId = $registration->getId();
        $recipientEmail = $registration->getEmail();
        $recipientName = $registration->getFullName();
        
        try {
            $this->logger->info('Starting document delivery notification process', [
                'registration_id' => $registrationId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'documents_count' => count($documents),
                'session_id' => $registration->getSession()?->getId(),
                'formation_id' => $registration->getSession()?->getFormation()?->getId(),
                'formation_title' => $registration->getSession()?->getFormation()?->getTitle(),
                'has_acknowledgment_token' => !empty($registration->getDocumentAcknowledgmentToken()),
            ]);

            // Validate registration data
            if (empty($recipientEmail)) {
                throw new Exception('Registration email is required for document delivery');
            }

            if (!$registration->getSession()) {
                throw new Exception('Registration session is required for document delivery');
            }

            if (!$registration->getSession()->getFormation()) {
                throw new Exception('Session formation is required for document delivery');
            }

            $session = $registration->getSession();
            $formation = $session->getFormation();

            $this->logger->debug('Registration validation passed', [
                'registration_id' => $registrationId,
                'session_id' => $session->getId(),
                'formation_id' => $formation->getId(),
            ]);

            // Validate documents
            if (empty($documents)) {
                $this->logger->warning('Empty documents array for delivery notification', [
                    'registration_id' => $registrationId,
                    'documents_count' => 0,
                ]);
                // Continue anyway as notification might still be relevant
            }

            // Log document details
            foreach ($documents as $index => $document) {
                $this->logger->debug('Document for delivery', [
                    'registration_id' => $registrationId,
                    'document_index' => $index,
                    'document_type' => gettype($document),
                    'document_info' => is_object($document) ? get_class($document) : (is_array($document) ? 'array' : $document),
                ]);
            }

            // Validate acknowledgment token
            $acknowledgmentToken = $registration->getDocumentAcknowledgmentToken();
            if (empty($acknowledgmentToken)) {
                $this->logger->warning('Missing document acknowledgment token', [
                    'registration_id' => $registrationId,
                ]);
                // This might be acceptable depending on implementation
            }

            // Generate acknowledgment URL
            $this->logger->debug('Generating acknowledgment URL', [
                'registration_id' => $registrationId,
                'token' => $acknowledgmentToken ? (substr($acknowledgmentToken, 0, 8) . '...') : 'missing',
            ]);

            $acknowledgmentUrl = $this->urlGenerator->generate(
                'app_document_acknowledgment',
                ['token' => $acknowledgmentToken],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->info('Acknowledgment URL generated successfully', [
                'registration_id' => $registrationId,
                'url_length' => strlen($acknowledgmentUrl),
                'url_host' => parse_url($acknowledgmentUrl, PHP_URL_HOST),
            ]);

            // Prepare email context
            $emailContext = [
                'registration' => $registration,
                'session' => $session,
                'formation' => $formation,
                'documents' => $documents,
                'acknowledgment_url' => $acknowledgmentUrl,
            ];

            $this->logger->debug('Document delivery email context prepared', [
                'registration_id' => $registrationId,
                'context_keys' => array_keys($emailContext),
                'documents_count' => count($documents),
            ]);

            $this->logger->info('Creating document delivery email', [
                'registration_id' => $registrationId,
                'recipient_email' => $recipientEmail,
                'template' => 'emails/document_delivery.html.twig',
                'formation_title' => $formation->getTitle(),
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipientEmail, $recipientName))
                ->subject('Documents obligatoires - Formation EPROFOS')
                ->htmlTemplate('emails/document_delivery.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Sending document delivery email via mailer', [
                'registration_id' => $registrationId,
                'mailer_class' => get_class($this->mailer),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Document delivery notification sent successfully', [
                'registration_id' => $registrationId,
                'recipient_email' => $recipientEmail,
                'formation_title' => $formation->getTitle(),
                'documents_count' => count($documents),
                'acknowledgment_url_host' => parse_url($acknowledgmentUrl, PHP_URL_HOST),
                'processing_completed' => true,
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send document delivery notification', [
                'registration_id' => $registrationId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'documents_count' => count($documents ?? []),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'session_id' => $registration->getSession()?->getId(),
                'formation_id' => $registration->getSession()?->getFormation()?->getId(),
                'has_acknowledgment_token' => !empty($registration->getDocumentAcknowledgmentToken()),
            ]);

            // Log specific error types
            if (strpos($e->getMessage(), 'app_document_acknowledgment') !== false) {
                $this->logger->critical('Document acknowledgment route generation error', [
                    'registration_id' => $registrationId,
                    'error_type' => 'route_generation_error',
                    'route_name' => 'app_document_acknowledgment',
                ]);
            } elseif (strpos($e->getMessage(), 'session') !== false || strpos($e->getMessage(), 'formation') !== false) {
                $this->logger->critical('Registration data validation error', [
                    'registration_id' => $registrationId,
                    'error_type' => 'data_validation_error',
                    'has_session' => $registration->getSession() !== null,
                    'has_formation' => $registration->getSession()?->getFormation() !== null,
                ]);
            }

            return false;
        }
    }

    /**
     * Send document acknowledgment confirmation.
     */
    public function sendDocumentAcknowledgmentConfirmation(SessionRegistration $registration): bool
    {
        $registrationId = $registration->getId();
        $recipientEmail = $registration->getEmail();
        $recipientName = $registration->getFullName();
        
        try {
            $this->logger->info('Starting document acknowledgment confirmation process', [
                'registration_id' => $registrationId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'session_id' => $registration->getSession()?->getId(),
                'formation_id' => $registration->getSession()?->getFormation()?->getId(),
                'formation_title' => $registration->getSession()?->getFormation()?->getTitle(),
                'acknowledgment_date' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            // Validate registration data
            if (empty($recipientEmail)) {
                throw new Exception('Registration email is required for acknowledgment confirmation');
            }

            if (!$registration->getSession()) {
                throw new Exception('Registration session is required for acknowledgment confirmation');
            }

            if (!$registration->getSession()->getFormation()) {
                throw new Exception('Session formation is required for acknowledgment confirmation');
            }

            $session = $registration->getSession();
            $formation = $session->getFormation();

            $this->logger->debug('Registration validation passed for acknowledgment confirmation', [
                'registration_id' => $registrationId,
                'session_id' => $session->getId(),
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
            ]);

            // Prepare email context
            $emailContext = [
                'registration' => $registration,
                'session' => $session,
                'formation' => $formation,
            ];

            $this->logger->debug('Acknowledgment confirmation email context prepared', [
                'registration_id' => $registrationId,
                'context_keys' => array_keys($emailContext),
                'formation_title' => $formation->getTitle(),
            ]);

            $this->logger->info('Creating document acknowledgment confirmation email', [
                'registration_id' => $registrationId,
                'recipient_email' => $recipientEmail,
                'template' => 'emails/document_acknowledgment_confirmation.html.twig',
                'formation_title' => $formation->getTitle(),
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipientEmail, $recipientName))
                ->subject('Confirmation de réception - Documents EPROFOS')
                ->htmlTemplate('emails/document_acknowledgment_confirmation.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Sending acknowledgment confirmation email via mailer', [
                'registration_id' => $registrationId,
                'mailer_class' => get_class($this->mailer),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Document acknowledgment confirmation sent successfully', [
                'registration_id' => $registrationId,
                'recipient_email' => $recipientEmail,
                'formation_title' => $formation->getTitle(),
                'session_id' => $session->getId(),
                'processing_completed' => true,
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send document acknowledgment confirmation', [
                'registration_id' => $registrationId,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'session_id' => $registration->getSession()?->getId(),
                'formation_id' => $registration->getSession()?->getFormation()?->getId(),
            ]);

            // Log specific error types
            if (strpos($e->getMessage(), 'session') !== false || strpos($e->getMessage(), 'formation') !== false) {
                $this->logger->critical('Registration data validation error in acknowledgment confirmation', [
                    'registration_id' => $registrationId,
                    'error_type' => 'data_validation_error',
                    'has_session' => $registration->getSession() !== null,
                    'has_formation' => $registration->getSession()?->getFormation() !== null,
                ]);
            } elseif (strpos($e->getMessage(), 'template') !== false) {
                $this->logger->critical('Template error in acknowledgment confirmation', [
                    'registration_id' => $registrationId,
                    'error_type' => 'template_error',
                    'template' => 'emails/document_acknowledgment_confirmation.html.twig',
                ]);
            }

            return false;
        }
    }

    /**
     * Send accessibility request notification to admin.
     */
    public function sendAccessibilityRequestNotification(ContactRequest $contactRequest): bool
    {
        $contactRequestId = $contactRequest->getId();
        $requestorEmail = $contactRequest->getEmail();
        $requestorName = $contactRequest->getFullName();
        
        try {
            $this->logger->info('Starting accessibility request notification to admin', [
                'contact_request_id' => $contactRequestId,
                'requestor_email' => $requestorEmail,
                'requestor_name' => $requestorName,
                'admin_email' => $this->adminEmail,
                'request_type' => $contactRequest->getType(),
                'subject' => $contactRequest->getSubject(),
                'created_at' => $contactRequest->getCreatedAt()?->format('Y-m-d H:i:s'),
                'has_message' => !empty($contactRequest->getMessage()),
            ]);

            // Validate contact request data
            if (empty($requestorEmail)) {
                throw new Exception('Contact request email is required for accessibility notification');
            }

            if (empty($requestorName)) {
                throw new Exception('Contact request name is required for accessibility notification');
            }

            if (empty($this->adminEmail)) {
                throw new Exception('Admin email is required for accessibility notification');
            }

            $this->logger->debug('Contact request validation passed for accessibility notification', [
                'contact_request_id' => $contactRequestId,
                'requestor_email' => $requestorEmail,
                'admin_email' => $this->adminEmail,
            ]);

            // Generate admin URL to view the contact request
            $this->logger->debug('Generating admin URL for contact request view', [
                'contact_request_id' => $contactRequestId,
            ]);

            $adminUrl = $this->urlGenerator->generate(
                'admin_contact_request_show',
                ['id' => $contactRequestId],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->info('Admin URL generated for accessibility request', [
                'contact_request_id' => $contactRequestId,
                'admin_url_length' => strlen($adminUrl),
                'url_host' => parse_url($adminUrl, PHP_URL_HOST),
            ]);

            // Prepare email context
            $emailContext = [
                'contactRequest' => $contactRequest,
                'admin_url' => $adminUrl,
            ];

            $this->logger->debug('Accessibility request email context prepared', [
                'contact_request_id' => $contactRequestId,
                'context_keys' => array_keys($emailContext),
                'request_type' => $contactRequest->getType(),
            ]);

            $this->logger->info('Creating accessibility request notification email', [
                'contact_request_id' => $contactRequestId,
                'admin_email' => $this->adminEmail,
                'template' => 'emails/accessibility_request_admin.html.twig',
                'requestor_name' => $requestorName,
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                ->subject('Nouvelle demande d\'adaptation handicap - EPROFOS')
                ->htmlTemplate('emails/accessibility_request_admin.html.twig')
                ->context($emailContext)
            ;

            $this->logger->info('Sending accessibility request notification via mailer', [
                'contact_request_id' => $contactRequestId,
                'mailer_class' => get_class($this->mailer),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Accessibility request notification sent to admin successfully', [
                'contact_request_id' => $contactRequestId,
                'admin_email' => $this->adminEmail,
                'requestor_email' => $requestorEmail,
                'requestor_name' => $requestorName,
                'admin_url_host' => parse_url($adminUrl, PHP_URL_HOST),
                'processing_completed' => true,
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send accessibility request notification to admin', [
                'contact_request_id' => $contactRequestId,
                'requestor_email' => $requestorEmail,
                'requestor_name' => $requestorName,
                'admin_email' => $this->adminEmail,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_type' => $contactRequest->getType(),
                'created_at' => $contactRequest->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);

            // Log specific error types
            if (strpos($e->getMessage(), 'admin_contact_request_show') !== false) {
                $this->logger->critical('Admin contact request route generation error', [
                    'contact_request_id' => $contactRequestId,
                    'error_type' => 'route_generation_error',
                    'route_name' => 'admin_contact_request_show',
                ]);
            } elseif (strpos($e->getMessage(), 'Admin email') !== false) {
                $this->logger->critical('Admin email configuration error for accessibility request', [
                    'contact_request_id' => $contactRequestId,
                    'error_type' => 'admin_email_missing',
                    'admin_email' => $this->adminEmail,
                ]);
            }

            return false;
        }
    }

    /**
     * Set admin email.
     */
    public function setAdminEmail(string $adminEmail): void
    {
        try {
            $this->logger->info('Setting admin email configuration', [
                'old_admin_email' => $this->adminEmail,
                'new_admin_email' => $adminEmail,
                'change_timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            // Validate email format
            if (empty($adminEmail)) {
                throw new Exception('Admin email cannot be empty');
            }

            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid admin email format: ' . $adminEmail);
            }

            $this->logger->debug('Admin email validation passed', [
                'admin_email' => $adminEmail,
                'email_format_valid' => true,
            ]);

            $this->adminEmail = $adminEmail;

            $this->logger->info('Admin email configuration updated successfully', [
                'admin_email' => $this->adminEmail,
                'configuration_active' => true,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to set admin email configuration', [
                'attempted_admin_email' => $adminEmail,
                'current_admin_email' => $this->adminEmail,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw exception to maintain original behavior
            throw $e;
        }
    }

    /**
     * Set from email.
     */
    public function setFromEmail(string $fromEmail, ?string $fromName = null): void
    {
        try {
            $this->logger->info('Setting from email configuration', [
                'old_from_email' => $this->fromEmail,
                'old_from_name' => $this->fromName,
                'new_from_email' => $fromEmail,
                'new_from_name' => $fromName,
                'change_timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            // Validate email format
            if (empty($fromEmail)) {
                throw new Exception('From email cannot be empty');
            }

            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid from email format: ' . $fromEmail);
            }

            $this->logger->debug('From email validation passed', [
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'email_format_valid' => true,
            ]);

            $this->fromEmail = $fromEmail;
            
            if ($fromName) {
                $this->fromName = $fromName;
                $this->logger->debug('From name updated', [
                    'from_name' => $this->fromName,
                ]);
            }

            $this->logger->info('From email configuration updated successfully', [
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'configuration_active' => true,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to set from email configuration', [
                'attempted_from_email' => $fromEmail,
                'attempted_from_name' => $fromName,
                'current_from_email' => $this->fromEmail,
                'current_from_name' => $this->fromName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw exception to maintain original behavior
            throw $e;
        }
    }

    /**
     * Get email subject for request.
     */
    private function getRequestEmailSubject(NeedsAnalysisRequest $request): string
    {
        $baseSubject = 'Analyse des besoins de formation - EPROFOS';

        if ($request->getFormation()) {
            return $baseSubject . ' - ' . $request->getFormation()->getTitle();
        }

        if ($request->getCompanyName()) {
            return $baseSubject . ' - ' . $request->getCompanyName();
        }

        return $baseSubject;
    }

    /**
     * Get email subject for completed notification.
     */
    private function getCompletedNotificationSubject(NeedsAnalysisRequest $request): string
    {
        $type = $request->getType() === NeedsAnalysisRequest::TYPE_COMPANY ? 'Entreprise' : 'Particulier';

        return "Nouvelle analyse des besoins complétée - {$type} - {$request->getRecipientName()}";
    }

    /**
     * Get days until expiration.
     */
    private function getDaysUntilExpiration(NeedsAnalysisRequest $request): int
    {
        $now = new DateTimeImmutable();
        $expiresAt = $request->getExpiresAt();

        if ($expiresAt <= $now) {
            return 0;
        }

        return $now->diff($expiresAt)->days;
    }
}
