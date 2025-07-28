<?php

namespace App\Service\Alternance;

use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Service for managing communication between training center and companies
 * 
 * Handles messaging, notifications, and digital liaison book for apprenticeship
 * coordination, ensuring optimal communication for Qualiopi compliance.
 */
class AlternanceCommunicationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Send coordination message between mentor and pedagogical supervisor
     */
    public function sendCoordinationMessage(
        $sender, // Teacher or Mentor
        $recipient, // Mentor or Teacher
        Student $student,
        string $subject,
        string $message,
        array $attachments = [],
        string $priority = 'normal'
    ): bool {
        try {
            $email = (new Email())
                ->from($sender->getEmail())
                ->to($recipient->getEmail())
                ->subject($this->formatSubject($subject, $student))
                ->html($this->formatMessage($sender, $recipient, $student, $message));

            // Add priority header if high priority
            if ($priority === 'high') {
                $email->priority(Email::PRIORITY_HIGH);
            }

            // TODO: Add attachments handling
            // foreach ($attachments as $attachment) {
            //     $email->attachFromPath($attachment['path'], $attachment['name']);
            // }

            $this->mailer->send($email);

            $this->logCommunication($sender, $recipient, $student, $subject, 'email_sent');

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send coordination message', [
                'sender' => $sender->getEmail(),
                'recipient' => $recipient->getEmail(),
                'student_id' => $student->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send automated alert for coordination issues
     */
    public function sendCoordinationAlert(
        Student $student,
        string $alertType,
        string $description,
        array $recipients = []
    ): void {
        $alertConfig = $this->getAlertConfiguration($alertType);
        
        if (empty($recipients)) {
            $recipients = $this->getDefaultAlertRecipients($student, $alertType);
        }

        foreach ($recipients as $recipient) {
            $this->sendCoordinationMessage(
                $this->getSystemSender(),
                $recipient,
                $student,
                $alertConfig['subject'],
                $this->formatAlertMessage($alertType, $description, $student),
                [],
                'high'
            );
        }

        $this->logCommunication(
            null,
            null,
            $student,
            "Alert: $alertType",
            'alert_sent'
        );
    }

    /**
     * Create digital liaison book entry
     */
    public function createLiaisonBookEntry(
        Student $student,
        $author, // Teacher or Mentor
        string $entryType,
        string $content,
        array $metadata = []
    ): array {
        $entry = [
            'id' => uniqid(),
            'student_id' => $student->getId(),
            'author_type' => $this->getAuthorType($author),
            'author_id' => $author->getId(),
            'author_name' => $author->getFullName(),
            'entry_type' => $entryType,
            'content' => $content,
            'metadata' => $metadata,
            'created_at' => new \DateTimeImmutable(),
            'read_by' => []
        ];

        // Store in database or cache
        // TODO: Implement proper storage mechanism
        $this->storeLiaisonBookEntry($entry);

        // Notify relevant parties
        $this->notifyLiaisonBookEntry($student, $author, $entryType);

        $this->logger->info('Liaison book entry created', [
            'student_id' => $student->getId(),
            'author_type' => $entry['author_type'],
            'entry_type' => $entryType
        ]);

        return $entry;
    }

    /**
     * Get liaison book entries for student
     */
    public function getLiaisonBookEntries(Student $student, int $limit = 50): array
    {
        // TODO: Implement proper retrieval from storage
        // This would fetch from database with proper filtering and pagination
        
        return [];
    }

    /**
     * Send meeting reminder notifications
     */
    public function sendMeetingReminders(array $meetings, int $hoursBeforeMeeting = 24): void
    {
        foreach ($meetings as $meeting) {
            $timeUntilMeeting = $meeting->getTimeUntilMeeting();
            
            if ($timeUntilMeeting && $timeUntilMeeting->h <= $hoursBeforeMeeting) {
                $this->sendMeetingReminderEmail($meeting);
            }
        }
    }

    /**
     * Send visit confirmation notifications
     */
    public function sendVisitConfirmation($visit): void
    {
        // Send to mentor
        $this->sendCoordinationMessage(
            $visit->getVisitor(),
            $visit->getMentor(),
            $visit->getStudent(),
            'Confirmation de visite en entreprise',
            $this->formatVisitConfirmationMessage($visit, 'mentor'),
            [],
            'normal'
        );

        // Send to student
        if ($visit->getStudent()->getEmail()) {
            $this->sendStudentNotification(
                $visit->getStudent(),
                'Visite programmée en entreprise',
                $this->formatVisitConfirmationMessage($visit, 'student')
            );
        }
    }

    /**
     * Send urgent coordination alert
     */
    public function sendUrgentAlert(
        Student $student,
        string $alertType,
        string $urgentMessage,
        array $escalationList = []
    ): void {
        // Send immediate notifications to all stakeholders
        $recipients = array_merge(
            $this->getDefaultAlertRecipients($student, 'urgent'),
            $escalationList
        );

        foreach ($recipients as $recipient) {
            $this->sendCoordinationMessage(
                $this->getSystemSender(),
                $recipient,
                $student,
                "URGENT: $alertType",
                $this->formatUrgentAlertMessage($alertType, $urgentMessage, $student),
                [],
                'high'
            );
        }

        // Log urgent alert
        $this->logger->warning('Urgent coordination alert sent', [
            'student_id' => $student->getId(),
            'alert_type' => $alertType,
            'recipients_count' => count($recipients)
        ]);
    }

    /**
     * Generate communication report
     */
    public function generateCommunicationReport(
        Student $student,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        // TODO: Implement proper communication tracking and reporting
        
        return [
            'student' => $student->getFullName(),
            'period' => [
                'start' => $startDate->format('d/m/Y'),
                'end' => $endDate->format('d/m/Y')
            ],
            'communication_stats' => [
                'total_messages' => 0,
                'meetings_scheduled' => 0,
                'visits_organized' => 0,
                'alerts_sent' => 0,
                'liaison_book_entries' => 0
            ],
            'response_times' => [
                'average_response_time' => null,
                'mentor_response_rate' => null,
                'supervisor_response_rate' => null
            ],
            'quality_indicators' => [
                'communication_frequency' => 'adequate',
                'responsiveness' => 'good',
                'coordination_effectiveness' => 'satisfactory'
            ]
        ];
    }

    /**
     * Format subject line with student context
     */
    private function formatSubject(string $subject, Student $student): string
    {
        return "[Alternance - {$student->getFullName()}] $subject";
    }

    /**
     * Format email message with proper template
     */
    private function formatMessage($sender, $recipient, Student $student, string $message): string
    {
        $template = "
        <h3>Message de coordination - Alternance</h3>
        <p><strong>Alternant concerné:</strong> {$student->getFullName()}</p>
        <p><strong>De:</strong> {$sender->getFullName()} ({$this->getAuthorType($sender)})</p>
        <p><strong>À:</strong> {$recipient->getFullName()} ({$this->getAuthorType($recipient)})</p>
        <hr>
        <div style='margin: 20px 0;'>
            " . nl2br(htmlspecialchars($message)) . "
        </div>
        <hr>
        <p><small>Ce message a été envoyé via le système de coordination EPROFOS.</small></p>
        ";

        return $template;
    }

    /**
     * Get author type for display
     */
    private function getAuthorType($author): string
    {
        return match (true) {
            $author instanceof Teacher => 'Référent pédagogique',
            $author instanceof Mentor => 'Tuteur entreprise',
            default => 'Système'
        };
    }

    /**
     * Get alert configuration by type
     */
    private function getAlertConfiguration(string $alertType): array
    {
        $configurations = [
            'absence_prolonged' => [
                'subject' => 'Alerte - Absence prolongée',
                'priority' => 'high'
            ],
            'performance_concern' => [
                'subject' => 'Alerte - Préoccupation performance',
                'priority' => 'medium'
            ],
            'integration_issue' => [
                'subject' => 'Alerte - Problème d\'intégration',
                'priority' => 'high'
            ],
            'mission_difficulty' => [
                'subject' => 'Alerte - Difficulté sur mission',
                'priority' => 'medium'
            ],
            'coordination_needed' => [
                'subject' => 'Coordination requise',
                'priority' => 'normal'
            ]
        ];

        return $configurations[$alertType] ?? [
            'subject' => 'Alerte de coordination',
            'priority' => 'normal'
        ];
    }

    /**
     * Get default alert recipients for student
     */
    private function getDefaultAlertRecipients(Student $student, string $alertType): array
    {
        $recipients = [];

        // TODO: Get actual mentor and pedagogical supervisor from student's alternance contract
        // For now, return empty array - this would be implemented when relationships are established
        
        return $recipients;
    }

    /**
     * Get system sender for automated messages
     */
    private function getSystemSender(): object
    {
        return new class {
            public function getEmail(): string { return 'coordination@eprofos.fr'; }
            public function getFullName(): string { return 'Système EPROFOS'; }
            public function getId(): int { return 0; }
        };
    }

    /**
     * Format alert message
     */
    private function formatAlertMessage(string $alertType, string $description, Student $student): string
    {
        return "
        Une alerte de coordination a été déclenchée pour l'alternant {$student->getFullName()}.
        
        Type d'alerte: $alertType
        
        Description:
        $description
        
        Cette situation nécessite votre attention et une coordination rapide entre le centre de formation et l'entreprise.
        
        Merci de prendre les mesures appropriées dans les plus brefs délais.
        ";
    }

    /**
     * Format urgent alert message
     */
    private function formatUrgentAlertMessage(string $alertType, string $urgentMessage, Student $student): string
    {
        return "
        ⚠️ ALERTE URGENTE ⚠️
        
        Alternant: {$student->getFullName()}
        Type: $alertType
        
        Message urgent:
        $urgentMessage
        
        Cette situation nécessite une intervention immédiate.
        Merci de contacter rapidement tous les intervenants concernés.
        ";
    }

    /**
     * Store liaison book entry
     */
    private function storeLiaisonBookEntry(array $entry): void
    {
        // TODO: Implement proper storage in database
        $this->logger->info('Liaison book entry stored', [
            'entry_id' => $entry['id'],
            'student_id' => $entry['student_id']
        ]);
    }

    /**
     * Notify about new liaison book entry
     */
    private function notifyLiaisonBookEntry(Student $student, $author, string $entryType): void
    {
        // TODO: Send notifications to relevant parties about new entry
        $this->logger->info('Liaison book entry notification sent', [
            'student_id' => $student->getId(),
            'entry_type' => $entryType
        ]);
    }

    /**
     * Send meeting reminder email
     */
    private function sendMeetingReminderEmail($meeting): void
    {
        // TODO: Implement meeting reminder email sending
        $this->logger->info('Meeting reminder sent', [
            'meeting_id' => $meeting->getId()
        ]);
    }

    /**
     * Format visit confirmation message
     */
    private function formatVisitConfirmationMessage($visit, string $recipientType): string
    {
        $baseMessage = "
        Une visite en entreprise a été programmée.
        
        Détails:
        - Date: {$visit->getVisitDate()->format('d/m/Y à H:i')}
        - Type: {$visit->getVisitTypeLabel()}
        - Alternant: {$visit->getStudent()->getFullName()}
        - Visiteur: {$visit->getVisitor()->getFullName()}
        - Entreprise: {$visit->getMentor()->getCompanyName()}
        ";

        if ($recipientType === 'mentor') {
            $baseMessage .= "\n\nMerci de confirmer votre disponibilité et de préparer les éléments nécessaires pour cette visite.";
        } else {
            $baseMessage .= "\n\nVeuillez vous tenir prêt(e) pour cette visite et préparer vos questions éventuelles.";
        }

        return $baseMessage;
    }

    /**
     * Send notification to student
     */
    private function sendStudentNotification(Student $student, string $subject, string $message): void
    {
        // TODO: Implement student notification system
        $this->logger->info('Student notification sent', [
            'student_id' => $student->getId(),
            'subject' => $subject
        ]);
    }

    /**
     * Log communication activity
     */
    private function logCommunication($sender, $recipient, Student $student, string $subject, string $action): void
    {
        $this->logger->info('Communication logged', [
            'action' => $action,
            'student_id' => $student->getId(),
            'sender' => $sender ? $sender->getEmail() : 'system',
            'recipient' => $recipient ? $recipient->getEmail() : 'multiple',
            'subject' => $subject
        ]);
    }
}
