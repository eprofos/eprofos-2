<?php

declare(strict_types=1);

namespace App\Service\Assessment;

use App\Entity\Assessment\QuestionnaireResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Service for sending questionnaire-related emails.
 */
class QuestionnaireEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private string $fromEmail = 'noreply@eprofos.com',
    ) {}

    /**
     * Send questionnaire link to user.
     */
    public function sendQuestionnaireLink(QuestionnaireResponse $response): void
    {
        $questionnaire = $response->getQuestionnaire();

        $questionnaireUrl = $this->urlGenerator->generate('questionnaire_complete', [
            'token' => $response->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $subject = $questionnaire->getEmailSubject() ?:
            'Questionnaire de positionnement - ' . $questionnaire->getTitle();

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

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($response->getEmail())
            ->subject($subject)
            ->text($textBody)
            ->html($htmlBody)
        ;

        $this->mailer->send($email);
    }

    /**
     * Send questionnaire reminder.
     */
    public function sendQuestionnaireReminder(QuestionnaireResponse $response): void
    {
        if ($response->isCompleted()) {
            return;
        }

        $questionnaire = $response->getQuestionnaire();

        $questionnaireUrl = $this->urlGenerator->generate('questionnaire_complete', [
            'token' => $response->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $subject = 'Rappel - Questionnaire de positionnement - ' . $questionnaire->getTitle();

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

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($response->getEmail())
            ->subject($subject)
            ->text($textBody)
            ->html($htmlBody)
        ;

        $this->mailer->send($email);
    }

    /**
     * Send evaluation results to user.
     */
    public function sendEvaluationResults(QuestionnaireResponse $response): void
    {
        if (!$response->isEvaluated()) {
            return;
        }

        $questionnaire = $response->getQuestionnaire();

        $subject = 'Résultats de votre évaluation - ' . $questionnaire->getTitle();

        $htmlBody = $this->twig->render('emails/questionnaire/evaluation_results.html.twig', [
            'response' => $response,
            'questionnaire' => $questionnaire,
        ]);

        $textBody = $this->twig->render('emails/questionnaire/evaluation_results.txt.twig', [
            'response' => $response,
            'questionnaire' => $questionnaire,
        ]);

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($response->getEmail())
            ->subject($subject)
            ->text($textBody)
            ->html($htmlBody)
        ;

        $this->mailer->send($email);
    }

    /**
     * Send notification to admin when questionnaire is completed.
     */
    public function sendAdminNotification(QuestionnaireResponse $response): void
    {
        $questionnaire = $response->getQuestionnaire();

        $subject = 'Nouveau questionnaire complété - ' . $questionnaire->getTitle();

        $adminUrl = $this->urlGenerator->generate('admin_questionnaire_response_show', [
            'id' => $response->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

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

        $email = (new Email())
            ->from($this->fromEmail)
            ->to('admin@eprofos.com') // Configure this as needed
            ->subject($subject)
            ->text($textBody)
            ->html($htmlBody)
        ;

        $this->mailer->send($email);
    }
}
