<?php

namespace App\Service;

use App\Entity\NeedsAnalysisRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Email Notification Service
 * 
 * Handles all email notifications related to needs analysis requests.
 * Manages template rendering, email sending, and notification tracking.
 */
class EmailNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $fromEmail = 'noreply@eprofos.fr',
        private string $fromName = 'EPROFOS - École Professionnelle de Formation Spécialisée',
        private string $adminEmail = 'admin@eprofos.fr'
    ) {
    }

    /**
     * Send needs analysis request to recipient
     */
    public function sendNeedsAnalysisRequest(NeedsAnalysisRequest $request): bool
    {
        try {
            $this->logger->info('Sending needs analysis request email', [
                'request_id' => $request->getId(),
                'recipient_email' => $request->getRecipientEmail(),
                'type' => $request->getType()
            ]);

            // Generate the public URL for the form
            $formUrl = $this->urlGenerator->generate(
                'needs_analysis_form',
                ['token' => $request->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Create email
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($request->getRecipientEmail(), $request->getRecipientName()))
                ->subject($this->getRequestEmailSubject($request))
                ->htmlTemplate('emails/needs_analysis_request.html.twig')
                ->context([
                    'request' => $request,
                    'form_url' => $formUrl,
                    'expires_at' => $request->getExpiresAt(),
                    'company_name' => $request->getCompanyName(),
                    'formation' => $request->getFormation(),
                    'type_label' => $request->getTypeLabel()
                ]);

            $this->mailer->send($email);

            $this->logger->info('Needs analysis request email sent successfully', [
                'request_id' => $request->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send needs analysis request email', [
                'request_id' => $request->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Send notification when analysis is completed
     */
    public function sendAnalysisCompletedNotification(NeedsAnalysisRequest $request): bool
    {
        try {
            $this->logger->info('Sending analysis completed notification', [
                'request_id' => $request->getId()
            ]);

            // Generate admin URL to view the analysis
            $adminUrl = $this->urlGenerator->generate(
                'admin_needs_analysis_show',
                ['id' => $request->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Send to admin
            $adminEmail = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                ->subject($this->getCompletedNotificationSubject($request))
                ->htmlTemplate('emails/analysis_completed_admin.html.twig')
                ->context([
                    'request' => $request,
                    'admin_url' => $adminUrl,
                    'analysis' => $request->getCompanyAnalysis() ?? $request->getIndividualAnalysis()
                ]);

            $this->mailer->send($adminEmail);

            // Send confirmation to recipient
            $confirmationEmail = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($request->getRecipientEmail(), $request->getRecipientName()))
                ->subject('Confirmation de réception - Analyse des besoins EPROFOS')
                ->htmlTemplate('emails/analysis_completed_confirmation.html.twig')
                ->context([
                    'request' => $request,
                    'recipient_name' => $request->getRecipientName(),
                    'company_name' => $request->getCompanyName()
                ]);

            $this->mailer->send($confirmationEmail);

            $this->logger->info('Analysis completed notifications sent successfully', [
                'request_id' => $request->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send analysis completed notification', [
                'request_id' => $request->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Send reminder for expiring requests
     */
    public function sendExpirationReminder(NeedsAnalysisRequest $request): bool
    {
        try {
            $this->logger->info('Sending expiration reminder', [
                'request_id' => $request->getId()
            ]);

            // Generate the public URL for the form
            $formUrl = $this->urlGenerator->generate(
                'needs_analysis_form',
                ['token' => $request->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($request->getRecipientEmail(), $request->getRecipientName()))
                ->subject('Rappel - Analyse des besoins EPROFOS expire bientôt')
                ->htmlTemplate('emails/needs_analysis_reminder.html.twig')
                ->context([
                    'request' => $request,
                    'form_url' => $formUrl,
                    'expires_at' => $request->getExpiresAt(),
                    'days_remaining' => $this->getDaysUntilExpiration($request)
                ]);

            $this->mailer->send($email);

            $this->logger->info('Expiration reminder sent successfully', [
                'request_id' => $request->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send expiration reminder', [
                'request_id' => $request->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send weekly summary to admin
     */
    public function sendWeeklySummary(array $statistics): bool
    {
        try {
            $this->logger->info('Sending weekly summary to admin');

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                ->subject('Résumé hebdomadaire - Analyses des besoins EPROFOS')
                ->htmlTemplate('emails/weekly_summary.html.twig')
                ->context([
                    'statistics' => $statistics,
                    'week_start' => new \DateTimeImmutable('-7 days'),
                    'week_end' => new \DateTimeImmutable()
                ]);

            $this->mailer->send($email);

            $this->logger->info('Weekly summary sent successfully');

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send weekly summary', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send bulk reminders for expiring requests
     */
    public function sendBulkExpirationReminders(array $requests): int
    {
        $sentCount = 0;

        foreach ($requests as $request) {
            if ($this->sendExpirationReminder($request)) {
                $sentCount++;
            }
        }

        $this->logger->info('Bulk expiration reminders sent', [
            'total_requests' => count($requests),
            'sent_count' => $sentCount
        ]);

        return $sentCount;
    }

    /**
     * Test email configuration
     */
    public function sendTestEmail(string $toEmail): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($toEmail)
                ->subject('Test Email - EPROFOS')
                ->htmlTemplate('emails/test_email.html.twig')
                ->context([
                    'sent_at' => new \DateTimeImmutable()
                ]);

            $this->mailer->send($email);

            $this->logger->info('Test email sent successfully', [
                'to_email' => $toEmail
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send test email', [
                'to_email' => $toEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get email subject for request
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
     * Get email subject for completed notification
     */
    private function getCompletedNotificationSubject(NeedsAnalysisRequest $request): string
    {
        $type = $request->getType() === NeedsAnalysisRequest::TYPE_COMPANY ? 'Entreprise' : 'Particulier';
        return "Nouvelle analyse des besoins complétée - {$type} - {$request->getRecipientName()}";
    }

    /**
     * Get days until expiration
     */
    private function getDaysUntilExpiration(NeedsAnalysisRequest $request): int
    {
        $now = new \DateTimeImmutable();
        $expiresAt = $request->getExpiresAt();
        
        if ($expiresAt <= $now) {
            return 0;
        }
        
        return $now->diff($expiresAt)->days;
    }

    /**
     * Set admin email
     */
    public function setAdminEmail(string $adminEmail): void
    {
        $this->adminEmail = $adminEmail;
    }

    /**
     * Set from email
     */
    public function setFromEmail(string $fromEmail, string $fromName = null): void
    {
        $this->fromEmail = $fromEmail;
        if ($fromName) {
            $this->fromName = $fromName;
        }
    }
}