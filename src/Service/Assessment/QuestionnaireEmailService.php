<?php

declare(strict_types=1);

namespace App\Service\Assessment;

use App\Entity\Assessment\QuestionnaireResponse;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\Error as TwigError;

/**
 * Service for sending questionnaire-related emails.
 */
class QuestionnaireEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $fromEmail = 'noreply@eprofos.com',
    ) {}

    /**
     * Send questionnaire link to user.
     */
    public function sendQuestionnaireLink(QuestionnaireResponse $response): void
    {
        try {
            $this->logger->info('Starting to send questionnaire link', [
                'response_id' => $response->getId(),
                'response_token' => $response->getToken(),
                'recipient_email' => $response->getEmail(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
            ]);

            $questionnaire = $response->getQuestionnaire();
            if (!$questionnaire) {
                $this->logger->error('Cannot send questionnaire link: questionnaire not found', [
                    'response_id' => $response->getId(),
                ]);

                throw new InvalidArgumentException('Questionnaire not found for response');
            }

            $this->logger->debug('Generating questionnaire URL', [
                'questionnaire_id' => $questionnaire->getId(),
                'questionnaire_title' => $questionnaire->getTitle(),
                'response_token' => $response->getToken(),
            ]);

            $questionnaireUrl = $this->urlGenerator->generate('questionnaire_complete', [
                'token' => $response->getToken(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $this->logger->debug('Generated questionnaire URL', [
                'url' => $questionnaireUrl,
            ]);

            $subject = $questionnaire->getEmailSubject() ?:
                'Questionnaire de positionnement - ' . $questionnaire->getTitle();

            $this->logger->debug('Preparing email templates', [
                'subject' => $subject,
                'questionnaire_has_custom_subject' => !empty($questionnaire->getEmailSubject()),
            ]);

            $htmlBody = $this->twig->render('emails/questionnaire/send_link.html.twig', [
                'response' => $response,
                'questionnaire' => $questionnaire,
                'questionnaire_url' => $questionnaireUrl,
            ]);

            $textBody = $this->twig->render('emails/questionnaire/send_link.txt.twig', [
                'response' => $response,
                'questionnaire' => $questionnaire,
                'questionnaire_url' => $questionnaireUrl,
            ]);

            $this->logger->debug('Templates rendered successfully', [
                'html_body_length' => strlen($htmlBody),
                'text_body_length' => strlen($textBody),
            ]);

            $email = (new Email())
                ->from($this->fromEmail)
                ->to($response->getEmail())
                ->subject($subject)
                ->text($textBody)
                ->html($htmlBody)
            ;

            $this->logger->debug('Email object created, attempting to send', [
                'from' => $this->fromEmail,
                'to' => $response->getEmail(),
                'subject' => $subject,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Questionnaire link email sent successfully', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $questionnaire->getId(),
                'recipient_email' => $response->getEmail(),
                'subject' => $subject,
            ]);
        } catch (TwigError $e) {
            $this->logger->error('Failed to render email templates for questionnaire link', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            throw new RuntimeException('Failed to render email templates: ' . $e->getMessage(), 0, $e);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send questionnaire link email due to transport error', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'recipient_email' => $response->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            throw new RuntimeException('Failed to send email: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->logger->error('Unexpected error while sending questionnaire link email', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'recipient_email' => $response->getEmail(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Send questionnaire reminder.
     */
    public function sendQuestionnaireReminder(QuestionnaireResponse $response): void
    {
        try {
            $this->logger->info('Starting to send questionnaire reminder', [
                'response_id' => $response->getId(),
                'response_token' => $response->getToken(),
                'recipient_email' => $response->getEmail(),
                'is_completed' => $response->isCompleted(),
            ]);

            if ($response->isCompleted()) {
                $this->logger->warning('Attempted to send reminder for already completed questionnaire', [
                    'response_id' => $response->getId(),
                    'completion_date' => $response->getCompletedAt()?->format('Y-m-d H:i:s'),
                ]);

                return;
            }

            $questionnaire = $response->getQuestionnaire();
            if (!$questionnaire) {
                $this->logger->error('Cannot send questionnaire reminder: questionnaire not found', [
                    'response_id' => $response->getId(),
                ]);

                throw new InvalidArgumentException('Questionnaire not found for response');
            }

            $this->logger->debug('Generating reminder URL', [
                'questionnaire_id' => $questionnaire->getId(),
                'questionnaire_title' => $questionnaire->getTitle(),
                'response_token' => $response->getToken(),
            ]);

            $questionnaireUrl = $this->urlGenerator->generate('questionnaire_complete', [
                'token' => $response->getToken(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $subject = 'Rappel - Questionnaire de positionnement - ' . $questionnaire->getTitle();

            $this->logger->debug('Preparing reminder email templates', [
                'subject' => $subject,
                'questionnaire_id' => $questionnaire->getId(),
            ]);

            $htmlBody = $this->twig->render('emails/questionnaire/reminder.html.twig', [
                'response' => $response,
                'questionnaire' => $questionnaire,
                'questionnaire_url' => $questionnaireUrl,
            ]);

            $textBody = $this->twig->render('emails/questionnaire/reminder.txt.twig', [
                'response' => $response,
                'questionnaire' => $questionnaire,
                'questionnaire_url' => $questionnaireUrl,
            ]);

            $this->logger->debug('Reminder templates rendered successfully', [
                'html_body_length' => strlen($htmlBody),
                'text_body_length' => strlen($textBody),
            ]);

            $email = (new Email())
                ->from($this->fromEmail)
                ->to($response->getEmail())
                ->subject($subject)
                ->text($textBody)
                ->html($htmlBody)
            ;

            $this->logger->debug('Reminder email object created, attempting to send', [
                'from' => $this->fromEmail,
                'to' => $response->getEmail(),
                'subject' => $subject,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Questionnaire reminder email sent successfully', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $questionnaire->getId(),
                'recipient_email' => $response->getEmail(),
                'subject' => $subject,
            ]);
        } catch (TwigError $e) {
            $this->logger->error('Failed to render email templates for questionnaire reminder', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            throw new RuntimeException('Failed to render reminder email templates: ' . $e->getMessage(), 0, $e);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send questionnaire reminder email due to transport error', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'recipient_email' => $response->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            throw new RuntimeException('Failed to send reminder email: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->logger->error('Unexpected error while sending questionnaire reminder email', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'recipient_email' => $response->getEmail(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Send evaluation results to user.
     */
    public function sendEvaluationResults(QuestionnaireResponse $response): void
    {
        try {
            $this->logger->info('Starting to send evaluation results', [
                'response_id' => $response->getId(),
                'recipient_email' => $response->getEmail(),
                'is_evaluated' => $response->isEvaluated(),
            ]);

            if (!$response->isEvaluated()) {
                $this->logger->warning('Attempted to send evaluation results for non-evaluated questionnaire', [
                    'response_id' => $response->getId(),
                    'completed_at' => $response->getCompletedAt()?->format('Y-m-d H:i:s'),
                ]);

                return;
            }

            $questionnaire = $response->getQuestionnaire();
            if (!$questionnaire) {
                $this->logger->error('Cannot send evaluation results: questionnaire not found', [
                    'response_id' => $response->getId(),
                ]);

                throw new InvalidArgumentException('Questionnaire not found for response');
            }

            $subject = 'Résultats de votre évaluation - ' . $questionnaire->getTitle();

            $this->logger->debug('Preparing evaluation results email templates', [
                'subject' => $subject,
                'questionnaire_id' => $questionnaire->getId(),
                'evaluation_date' => $response->getEvaluatedAt()?->format('Y-m-d H:i:s'),
            ]);

            $htmlBody = $this->twig->render('emails/questionnaire/evaluation_results.html.twig', [
                'response' => $response,
                'questionnaire' => $questionnaire,
            ]);

            $textBody = $this->twig->render('emails/questionnaire/evaluation_results.txt.twig', [
                'response' => $response,
                'questionnaire' => $questionnaire,
            ]);

            $this->logger->debug('Evaluation results templates rendered successfully', [
                'html_body_length' => strlen($htmlBody),
                'text_body_length' => strlen($textBody),
            ]);

            $email = (new Email())
                ->from($this->fromEmail)
                ->to($response->getEmail())
                ->subject($subject)
                ->text($textBody)
                ->html($htmlBody)
            ;

            $this->logger->debug('Evaluation results email object created, attempting to send', [
                'from' => $this->fromEmail,
                'to' => $response->getEmail(),
                'subject' => $subject,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Evaluation results email sent successfully', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $questionnaire->getId(),
                'recipient_email' => $response->getEmail(),
                'subject' => $subject,
            ]);
        } catch (TwigError $e) {
            $this->logger->error('Failed to render email templates for evaluation results', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            throw new RuntimeException('Failed to render evaluation results email templates: ' . $e->getMessage(), 0, $e);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send evaluation results email due to transport error', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'recipient_email' => $response->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            throw new RuntimeException('Failed to send evaluation results email: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->logger->error('Unexpected error while sending evaluation results email', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'recipient_email' => $response->getEmail(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Send notification to admin when questionnaire is completed.
     */
    public function sendAdminNotification(QuestionnaireResponse $response): void
    {
        try {
            $this->logger->info('Starting to send admin notification for completed questionnaire', [
                'response_id' => $response->getId(),
                'response_token' => $response->getToken(),
                'respondent_email' => $response->getEmail(),
                'completed_at' => $response->getCompletedAt()?->format('Y-m-d H:i:s'),
            ]);

            $questionnaire = $response->getQuestionnaire();
            if (!$questionnaire) {
                $this->logger->error('Cannot send admin notification: questionnaire not found', [
                    'response_id' => $response->getId(),
                ]);

                throw new InvalidArgumentException('Questionnaire not found for response');
            }

            $subject = 'Nouveau questionnaire complété - ' . $questionnaire->getTitle();

            $this->logger->debug('Generating admin URL for questionnaire response', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $questionnaire->getId(),
            ]);

            $adminUrl = $this->urlGenerator->generate('admin_questionnaire_response_show', [
                'id' => $response->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $this->logger->debug('Generated admin URL', [
                'admin_url' => $adminUrl,
            ]);

            $this->logger->debug('Preparing admin notification email templates', [
                'subject' => $subject,
                'questionnaire_id' => $questionnaire->getId(),
                'questionnaire_title' => $questionnaire->getTitle(),
            ]);

            $htmlBody = $this->twig->render('emails/questionnaire/admin_notification.html.twig', [
                'response' => $response,
                'questionnaire' => $questionnaire,
                'admin_url' => $adminUrl,
            ]);

            $textBody = $this->twig->render('emails/questionnaire/admin_notification.txt.twig', [
                'response' => $response,
                'questionnaire' => $questionnaire,
                'admin_url' => $adminUrl,
            ]);

            $this->logger->debug('Admin notification templates rendered successfully', [
                'html_body_length' => strlen($htmlBody),
                'text_body_length' => strlen($textBody),
            ]);

            $adminEmail = 'admin@eprofos.com'; // Configure this as needed

            $email = (new Email())
                ->from($this->fromEmail)
                ->to($adminEmail)
                ->subject($subject)
                ->text($textBody)
                ->html($htmlBody)
            ;

            $this->logger->debug('Admin notification email object created, attempting to send', [
                'from' => $this->fromEmail,
                'to' => $adminEmail,
                'subject' => $subject,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Admin notification email sent successfully', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $questionnaire->getId(),
                'admin_email' => $adminEmail,
                'respondent_email' => $response->getEmail(),
                'subject' => $subject,
            ]);
        } catch (TwigError $e) {
            $this->logger->error('Failed to render email templates for admin notification', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            throw new RuntimeException('Failed to render admin notification email templates: ' . $e->getMessage(), 0, $e);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send admin notification email due to transport error', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'admin_email' => 'admin@eprofos.com',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            throw new RuntimeException('Failed to send admin notification email: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->logger->error('Unexpected error while sending admin notification email', [
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()?->getId(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
