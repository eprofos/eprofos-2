<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\AlternanceCalendar;
use App\Entity\Alternance\AlternanceContract;
use App\Entity\User\Student;
use App\Repository\Alternance\AlternanceCalendarRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing alternance calendars
 * 
 * Handles CRUD operations, validation, and business logic for
 * alternance planning and scheduling.
 */
class AlternanceCalendarService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlternanceCalendarRepository $calendarRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Create a new calendar entry
     */
    public function createCalendarEntry(
        Student $student,
        AlternanceContract $contract,
        int $week,
        int $year,
        string $location,
        ?string $modifiedBy = null
    ): AlternanceCalendar {
        // Check for existing entry
        $existing = $this->findByStudentWeekYear($student, $week, $year);
        if ($existing) {
            throw new \InvalidArgumentException(
                sprintf('Une entrée existe déjà pour l\'alternant %s en semaine %d de %d', 
                    $student->getFullName(), $week, $year)
            );
        }

        $calendar = new AlternanceCalendar();
        $calendar->setStudent($student)
                 ->setContract($contract)
                 ->setWeek($week)
                 ->setYear($year)
                 ->setLocation($location)
                 ->setIsConfirmed(false)
                 ->setModifiedBy($modifiedBy);

        $this->entityManager->persist($calendar);
        $this->entityManager->flush();

        $this->logger->info('Calendar entry created', [
            'student_id' => $student->getId(),
            'week' => $week,
            'year' => $year,
            'location' => $location
        ]);

        return $calendar;
    }

    /**
     * Update an existing calendar entry
     */
    public function updateCalendarEntry(
        AlternanceCalendar $calendar,
        array $data,
        ?string $modifiedBy = null
    ): AlternanceCalendar {
        $oldLocation = $calendar->getLocation();
        $oldConfirmed = $calendar->isConfirmed();

        if (isset($data['location'])) {
            $calendar->setLocation($data['location']);
        }
        
        if (isset($data['centerSessions'])) {
            $calendar->setCenterSessions($data['centerSessions']);
        }
        
        if (isset($data['companyActivities'])) {
            $calendar->setCompanyActivities($data['companyActivities']);
        }
        
        if (isset($data['evaluations'])) {
            $calendar->setEvaluations($data['evaluations']);
        }
        
        if (isset($data['meetings'])) {
            $calendar->setMeetings($data['meetings']);
        }
        
        if (isset($data['holidays'])) {
            $calendar->setHolidays($data['holidays']);
        }
        
        if (isset($data['notes'])) {
            $calendar->setNotes($data['notes']);
        }
        
        if (isset($data['isConfirmed'])) {
            $calendar->setIsConfirmed($data['isConfirmed']);
        }

        $calendar->setModifiedBy($modifiedBy);

        $this->entityManager->flush();

        $this->logger->info('Calendar entry updated', [
            'calendar_id' => $calendar->getId(),
            'student_id' => $calendar->getStudent()->getId(),
            'old_location' => $oldLocation,
            'new_location' => $calendar->getLocation(),
            'old_confirmed' => $oldConfirmed,
            'new_confirmed' => $calendar->isConfirmed()
        ]);

        return $calendar;
    }

    /**
     * Delete a calendar entry
     */
    public function deleteCalendarEntry(AlternanceCalendar $calendar): void
    {
        $this->logger->info('Calendar entry deleted', [
            'calendar_id' => $calendar->getId(),
            'student_id' => $calendar->getStudent()->getId(),
            'week' => $calendar->getWeek(),
            'year' => $calendar->getYear()
        ]);

        $this->entityManager->remove($calendar);
        $this->entityManager->flush();
    }

    /**
     * Find calendar entry by student, week and year
     */
    public function findByStudentWeekYear(Student $student, int $week, int $year): ?AlternanceCalendar
    {
        return $this->calendarRepository->findOneBy([
            'student' => $student,
            'week' => $week,
            'year' => $year
        ]);
    }

    /**
     * Get calendar for a student within a date range
     */
    public function getStudentCalendar(
        Student $student, 
        \DateTimeInterface $startDate, 
        \DateTimeInterface $endDate
    ): array {
        return $this->calendarRepository->findByStudentAndPeriod($student, $startDate, $endDate);
    }

    /**
     * Get calendar statistics for a period
     */
    public function getCalendarStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->calendarRepository->getCalendarStatistics($startDate, $endDate);
    }

    /**
     * Detect and return calendar conflicts for a student
     */
    public function detectConflicts(Student $student): array
    {
        return $this->calendarRepository->findConflicts($student);
    }

    /**
     * Confirm a calendar entry
     */
    public function confirmCalendarEntry(AlternanceCalendar $calendar, ?string $confirmedBy = null): AlternanceCalendar
    {
        $calendar->setIsConfirmed(true);
        $calendar->setModifiedBy($confirmedBy);

        $this->entityManager->flush();

        $this->logger->info('Calendar entry confirmed', [
            'calendar_id' => $calendar->getId(),
            'confirmed_by' => $confirmedBy
        ]);

        return $calendar;
    }

    /**
     * Bulk confirm multiple calendar entries
     */
    public function bulkConfirmEntries(array $calendars, ?string $confirmedBy = null): int
    {
        $count = 0;
        foreach ($calendars as $calendar) {
            if ($calendar instanceof AlternanceCalendar && !$calendar->isConfirmed()) {
                $calendar->setIsConfirmed(true);
                $calendar->setModifiedBy($confirmedBy);
                $count++;
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Bulk calendar confirmation', [
            'confirmed_count' => $count,
            'confirmed_by' => $confirmedBy
        ]);

        return $count;
    }

    /**
     * Get unconfirmed calendar entries
     */
    public function getUnconfirmedEntries(): array
    {
        return $this->calendarRepository->findUnconfirmed();
    }

    /**
     * Add activity to a calendar entry
     */
    public function addActivity(
        AlternanceCalendar $calendar,
        string $type,
        array $activityData,
        ?string $modifiedBy = null
    ): AlternanceCalendar {
        switch ($type) {
            case 'center_session':
                $calendar->addCenterSession($activityData);
                break;
            case 'company_activity':
                $calendar->addCompanyActivity($activityData);
                break;
            case 'evaluation':
                $calendar->addEvaluation($activityData);
                break;
            case 'meeting':
                $calendar->addMeeting($activityData);
                break;
            case 'holiday':
                $calendar->addHoliday($activityData);
                break;
            default:
                throw new \InvalidArgumentException("Type d'activité non supporté: $type");
        }

        $calendar->setModifiedBy($modifiedBy);
        $this->entityManager->flush();

        $this->logger->info('Activity added to calendar', [
            'calendar_id' => $calendar->getId(),
            'activity_type' => $type,
            'modified_by' => $modifiedBy
        ]);

        return $calendar;
    }

    /**
     * Get activities for a specific week and location
     */
    public function getActivitiesForWeek(string $location, int $week, int $year): array
    {
        $calendars = $this->calendarRepository->findByLocationAndWeek($location, $week, $year);
        
        $activities = [];
        foreach ($calendars as $calendar) {
            $studentActivities = $calendar->getAllActivities();
            if (!empty($studentActivities)) {
                $activities[] = [
                    'student' => $calendar->getStudent(),
                    'activities' => $studentActivities,
                    'calendar' => $calendar
                ];
            }
        }
        
        return $activities;
    }

    /**
     * Export calendar data to array format
     */
    public function exportCalendarData(
        Student $student,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $calendar = $this->getStudentCalendar($student, $startDate, $endDate);
        
        $export = [];
        foreach ($calendar as $entry) {
            $weekRange = $entry->getWeekDateRange();
            
            $export[] = [
                'week' => $entry->getWeek(),
                'year' => $entry->getYear(),
                'start_date' => $weekRange['start']->format('Y-m-d'),
                'end_date' => $weekRange['end']->format('Y-m-d'),
                'location' => $entry->getLocation(),
                'location_label' => $entry->getLocationLabel(),
                'confirmed' => $entry->isConfirmed(),
                'center_sessions' => $entry->getCenterSessions(),
                'company_activities' => $entry->getCompanyActivities(),
                'evaluations' => $entry->getEvaluations(),
                'meetings' => $entry->getMeetings(),
                'holidays' => $entry->getHolidays(),
                'notes' => $entry->getNotes(),
                'all_activities' => $entry->getAllActivities()
            ];
        }
        
        return $export;
    }

    /**
     * Get calendar overview for multiple students
     */
    public function getMultiStudentOverview(
        array $students,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $overview = [];
        
        foreach ($students as $student) {
            if ($student instanceof Student) {
                $calendar = $this->getStudentCalendar($student, $startDate, $endDate);
                $rhythm = $this->calendarRepository->getRhythmAnalysis($student, $startDate, $endDate);
                
                $overview[] = [
                    'student' => $student,
                    'calendar' => $calendar,
                    'rhythm_analysis' => $rhythm,
                    'total_weeks' => count($calendar),
                    'confirmed_weeks' => count(array_filter($calendar, fn($c) => $c->isConfirmed())),
                    'unconfirmed_weeks' => count(array_filter($calendar, fn($c) => !$c->isConfirmed()))
                ];
            }
        }
        
        return $overview;
    }

    /**
     * Validate calendar entry data
     */
    public function validateCalendarData(array $data): array
    {
        $errors = [];
        
        if (!isset($data['week']) || $data['week'] < 1 || $data['week'] > 53) {
            $errors[] = 'Le numéro de semaine doit être entre 1 et 53.';
        }
        
        if (!isset($data['year']) || $data['year'] < 2020 || $data['year'] > 2040) {
            $errors[] = 'L\'année doit être comprise entre 2020 et 2040.';
        }
        
        if (!isset($data['location']) || !in_array($data['location'], ['center', 'company'])) {
            $errors[] = 'Le lieu doit être "center" ou "company".';
        }
        
        return $errors;
    }
}
