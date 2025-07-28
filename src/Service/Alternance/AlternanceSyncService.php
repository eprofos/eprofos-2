<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\AlternanceCalendar;
use App\Entity\User\Student;
use App\Service\Alternance\AlternanceCalendarService;
use Psr\Log\LoggerInterface;

/**
 * Service for synchronizing alternance calendars with external systems
 * 
 * Handles iCal export, CSV export, and potential integration with
 * Google Calendar, Outlook, and other calendar systems.
 */
class AlternanceSyncService
{
    public function __construct(
        private AlternanceCalendarService $calendarService,
        private LoggerInterface $logger
    ) {}

    /**
     * Export calendar to iCal format
     */
    public function exportToICal(
        Student $student,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $options = []
    ): string {
        $calendar = $this->calendarService->getStudentCalendar($student, $startDate, $endDate);
        
        $ical = [];
        $ical[] = 'BEGIN:VCALENDAR';
        $ical[] = 'VERSION:2.0';
        $ical[] = 'PRODID:-//EPROFOS//Alternance Calendar//FR';
        $ical[] = 'CALSCALE:GREGORIAN';
        $ical[] = 'METHOD:PUBLISH';
        $ical[] = 'X-WR-CALNAME:' . $this->escapeICalValue('Planning Alternance - ' . $student->getFullName());
        $ical[] = 'X-WR-CALDESC:' . $this->escapeICalValue('Planning d\'alternance centre/entreprise');
        $ical[] = 'X-WR-TIMEZONE:Europe/Paris';
        
        foreach ($calendar as $entry) {
            $weekRange = $entry->getWeekDateRange();
            $startDateTime = $weekRange['start'];
            $endDateTime = $weekRange['end'];
            
            // Create main week event
            $ical[] = 'BEGIN:VEVENT';
            $ical[] = 'UID:' . 'alternance-' . $entry->getId() . '@eprofos.com';
            $ical[] = 'DTSTART;VALUE=DATE:' . $startDateTime->format('Ymd');
            $ical[] = 'DTEND;VALUE=DATE:' . $endDateTime->modify('+1 day')->format('Ymd');
            $ical[] = 'SUMMARY:' . $this->escapeICalValue($entry->getLocationLabel() . ' - Semaine ' . $entry->getWeek());
            $ical[] = 'DESCRIPTION:' . $this->escapeICalValue($this->buildEventDescription($entry));
            $ical[] = 'LOCATION:' . $this->escapeICalValue($entry->getLocationLabel());
            $ical[] = 'CATEGORIES:' . $this->escapeICalValue('ALTERNANCE,' . strtoupper($entry->getLocation()));
            $ical[] = 'STATUS:' . ($entry->isConfirmed() ? 'CONFIRMED' : 'TENTATIVE');
            $ical[] = 'TRANSP:TRANSPARENT';
            $ical[] = 'CREATED:' . $entry->getCreatedAt()->format('Ymd\THis\Z');
            $ical[] = 'LAST-MODIFIED:' . ($entry->getUpdatedAt() ?? $entry->getCreatedAt())->format('Ymd\THis\Z');
            $ical[] = 'END:VEVENT';
            
            // Add individual activities if requested
            if ($options['include_activities'] ?? false) {
                $activities = $entry->getAllActivities();
                foreach ($activities as $activity) {
                    $ical = array_merge($ical, $this->createActivityEvent($entry, $activity));
                }
            }
        }
        
        $ical[] = 'END:VCALENDAR';
        
        $content = implode("\r\n", $ical) . "\r\n";
        
        $this->logger->info('iCal export generated', [
            'student_id' => $student->getId(),
            'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            'entries_count' => count($calendar)
        ]);
        
        return $content;
    }

    /**
     * Create iCal event for individual activity
     */
    private function createActivityEvent(AlternanceCalendar $entry, array $activity): array
    {
        $weekRange = $entry->getWeekDateRange();
        $activityDate = $weekRange['start'];
        
        // Adjust date if activity has specific day
        if (isset($activity['day'])) {
            $dayOffset = $activity['day'] - 1; // Monday = 0
            $activityDate->modify("+{$dayOffset} days");
        }
        
        $event = [];
        $event[] = 'BEGIN:VEVENT';
        $event[] = 'UID:' . 'activity-' . $entry->getId() . '-' . md5(json_encode($activity)) . '@eprofos.com';
        
        if (isset($activity['time'])) {
            $startTime = new \DateTime($activityDate->format('Y-m-d') . ' ' . $activity['time']);
            $endTime = clone $startTime;
            $endTime->modify('+' . ($activity['duration'] ?? 60) . ' minutes');
            
            $event[] = 'DTSTART:' . $startTime->format('Ymd\THis');
            $event[] = 'DTEND:' . $endTime->format('Ymd\THis');
        } else {
            $event[] = 'DTSTART;VALUE=DATE:' . $activityDate->format('Ymd');
            $event[] = 'DTEND;VALUE=DATE:' . $activityDate->modify('+1 day')->format('Ymd');
        }
        
        $event[] = 'SUMMARY:' . $this->escapeICalValue($activity['title'] ?? 'Activité');
        $event[] = 'DESCRIPTION:' . $this->escapeICalValue($activity['description'] ?? '');
        $event[] = 'CATEGORIES:' . $this->escapeICalValue('ALTERNANCE,ACTIVITY,' . strtoupper($activity['type']));
        $event[] = 'END:VEVENT';
        
        return $event;
    }

    /**
     * Build event description for calendar entry
     */
    private function buildEventDescription(AlternanceCalendar $entry): string
    {
        $description = [];
        $description[] = 'Semaine d\'alternance: ' . $entry->getFormattedWeekPeriod();
        $description[] = 'Lieu: ' . $entry->getLocationLabel();
        $description[] = 'Statut: ' . $entry->getValidationStatus();
        
        if ($entry->getNotes()) {
            $description[] = '';
            $description[] = 'Notes: ' . $entry->getNotes();
        }
        
        $activities = $entry->getAllActivities();
        if (!empty($activities)) {
            $description[] = '';
            $description[] = 'Activités prévues:';
            foreach ($activities as $activity) {
                $description[] = '- ' . ($activity['title'] ?? 'Activité');
            }
        }
        
        return implode('\\n', $description);
    }

    /**
     * Escape iCal values
     */
    private function escapeICalValue(string $value): string
    {
        return str_replace(
            ["\n", "\r", ";", ",", "\\"],
            ["\\n", "\\r", "\\;", "\\,", "\\\\"],
            $value
        );
    }

    /**
     * Export calendar to CSV format
     */
    public function exportToCSV(
        Student $student,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $options = []
    ): string {
        $calendar = $this->calendarService->getStudentCalendar($student, $startDate, $endDate);
        
        $csv = [];
        
        // CSV Header
        $headers = [
            'Semaine',
            'Année',
            'Date début',
            'Date fin',
            'Lieu',
            'Confirmé',
            'Notes',
            'Activités',
            'Évaluations',
            'Réunions',
            'Jours fériés'
        ];
        
        if ($options['include_detailed_activities'] ?? false) {
            $headers = array_merge($headers, [
                'Sessions centre',
                'Activités entreprise'
            ]);
        }
        
        $csv[] = $this->arrayToCSVLine($headers);
        
        // CSV Data
        foreach ($calendar as $entry) {
            $weekRange = $entry->getWeekDateRange();
            
            $row = [
                $entry->getWeek(),
                $entry->getYear(),
                $weekRange['start']->format('d/m/Y'),
                $weekRange['end']->format('d/m/Y'),
                $entry->getLocationLabel(),
                $entry->isConfirmed() ? 'Oui' : 'Non',
                $entry->getNotes() ?? '',
                $this->formatActivitiesForCSV($entry->getAllActivities()),
                $this->formatArrayForCSV($entry->getEvaluations()),
                $this->formatArrayForCSV($entry->getMeetings()),
                $this->formatArrayForCSV($entry->getHolidays())
            ];
            
            if ($options['include_detailed_activities'] ?? false) {
                $row[] = $this->formatArrayForCSV($entry->getCenterSessions());
                $row[] = $this->formatArrayForCSV($entry->getCompanyActivities());
            }
            
            $csv[] = $this->arrayToCSVLine($row);
        }
        
        $content = implode("\n", $csv);
        
        $this->logger->info('CSV export generated', [
            'student_id' => $student->getId(),
            'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            'entries_count' => count($calendar)
        ]);
        
        return $content;
    }

    /**
     * Convert array to CSV line
     */
    private function arrayToCSVLine(array $fields): string
    {
        $escaped = array_map(function($field) {
            $field = str_replace('"', '""', (string) $field);
            return '"' . $field . '"';
        }, $fields);
        
        return implode(';', $escaped);
    }

    /**
     * Format activities array for CSV
     */
    private function formatActivitiesForCSV(?array $activities): string
    {
        if (empty($activities)) {
            return '';
        }
        
        $formatted = [];
        foreach ($activities as $activity) {
            $formatted[] = ($activity['title'] ?? 'Activité') . 
                          (isset($activity['time']) ? ' (' . $activity['time'] . ')' : '');
        }
        
        return implode(' | ', $formatted);
    }

    /**
     * Format array for CSV display
     */
    private function formatArrayForCSV(?array $items): string
    {
        if (empty($items)) {
            return '';
        }
        
        $formatted = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $formatted[] = $item['title'] ?? $item['name'] ?? 'Item';
            } else {
                $formatted[] = (string) $item;
            }
        }
        
        return implode(' | ', $formatted);
    }

    /**
     * Generate calendar URL for external subscription
     */
    public function generateCalendarURL(Student $student, string $token): string
    {
        // This would be implemented with proper routing
        return sprintf(
            '%s/alternance/calendar/%d/ical?token=%s',
            $_ENV['APP_URL'] ?? 'https://eprofos.com',
            $student->getId(),
            $token
        );
    }

    /**
     * Generate webhook URL for calendar updates
     */
    public function generateWebhookURL(Student $student, string $token): string
    {
        return sprintf(
            '%s/api/alternance/calendar/%d/webhook?token=%s',
            $_ENV['APP_URL'] ?? 'https://eprofos.com',
            $student->getId(),
            $token
        );
    }

    /**
     * Validate external calendar data
     */
    public function validateExternalCalendarData(array $data): array
    {
        $errors = [];
        
        if (!isset($data['title']) || empty($data['title'])) {
            $errors[] = 'Le titre est obligatoire';
        }
        
        if (!isset($data['start_date']) || !$this->isValidDate($data['start_date'])) {
            $errors[] = 'La date de début est invalide';
        }
        
        if (!isset($data['end_date']) || !$this->isValidDate($data['end_date'])) {
            $errors[] = 'La date de fin est invalide';
        }
        
        if (isset($data['start_date'], $data['end_date'])) {
            $start = new \DateTime($data['start_date']);
            $end = new \DateTime($data['end_date']);
            if ($start >= $end) {
                $errors[] = 'La date de fin doit être postérieure à la date de début';
            }
        }
        
        return $errors;
    }

    /**
     * Check if date string is valid
     */
    private function isValidDate(string $date): bool
    {
        try {
            new \DateTime($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate sync report for student calendar
     */
    public function generateSyncReport(Student $student): array
    {
        $now = new \DateTime();
        $monthAgo = (clone $now)->modify('-1 month');
        $monthAhead = (clone $now)->modify('+1 month');
        
        $calendar = $this->calendarService->getStudentCalendar($student, $monthAgo, $monthAhead);
        
        $report = [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName()
            ],
            'period' => [
                'start' => $monthAgo->format('Y-m-d'),
                'end' => $monthAhead->format('Y-m-d')
            ],
            'statistics' => [
                'total_weeks' => count($calendar),
                'confirmed_weeks' => count(array_filter($calendar, fn($c) => $c->isConfirmed())),
                'center_weeks' => count(array_filter($calendar, fn($c) => $c->getLocation() === 'center')),
                'company_weeks' => count(array_filter($calendar, fn($c) => $c->getLocation() === 'company')),
                'weeks_with_activities' => count(array_filter($calendar, fn($c) => !empty($c->getAllActivities()))),
                'weeks_with_notes' => count(array_filter($calendar, fn($c) => !empty($c->getNotes())))
            ],
            'export_formats' => [
                'ical_available' => true,
                'csv_available' => true,
                'json_available' => true
            ],
            'last_updated' => $now->format('Y-m-d H:i:s')
        ];
        
        return $report;
    }

    /**
     * Export calendar to JSON format
     */
    public function exportToJSON(
        Student $student,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): string {
        $data = $this->calendarService->exportCalendarData($student, $startDate, $endDate);
        
        $export = [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName(),
                'email' => $student->getEmail()
            ],
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ],
            'calendar' => $data,
            'exported_at' => (new \DateTime())->format('c'),
            'total_weeks' => count($data)
        ];
        
        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
