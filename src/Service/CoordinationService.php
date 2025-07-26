<?php

namespace App\Service;

use App\Entity\Alternance\CoordinationMeeting;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Repository\CoordinationMeetingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for managing coordination between training center and companies
 * 
 * Handles coordination meetings, scheduling, and communication workflows
 * for apprenticeship programs, ensuring Qualiopi compliance.
 */
class CoordinationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CoordinationMeetingRepository $coordinationMeetingRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a new coordination meeting
     */
    public function createMeeting(
        Student $student,
        Teacher $pedagogicalSupervisor,
        Mentor $mentor,
        \DateTimeInterface $date,
        string $type,
        string $location,
        array $agenda = []
    ): CoordinationMeeting {
        $meeting = new CoordinationMeeting();
        $meeting->setStudent($student)
                ->setPedagogicalSupervisor($pedagogicalSupervisor)
                ->setMentor($mentor)
                ->setDate($date)
                ->setType($type)
                ->setLocation($location)
                ->setAgenda($agenda);

        $this->entityManager->persist($meeting);
        $this->entityManager->flush();

        $this->logger->info('Coordination meeting created', [
            'meeting_id' => $meeting->getId(),
            'student_id' => $student->getId(),
            'date' => $date->format('Y-m-d H:i:s'),
            'type' => $type
        ]);

        return $meeting;
    }

    /**
     * Schedule regular follow-up meetings for a student
     */
    public function scheduleRegularFollowUps(
        Student $student,
        Teacher $pedagogicalSupervisor,
        Mentor $mentor,
        \DateTimeInterface $startDate,
        int $intervalWeeks = 4,
        int $numberOfMeetings = 6
    ): array {
        $meetings = [];
        $currentDate = \DateTime::createFromInterface($startDate);

        for ($i = 0; $i < $numberOfMeetings; $i++) {
            $meeting = $this->createMeeting(
                $student,
                $pedagogicalSupervisor,
                $mentor,
                clone $currentDate,
                CoordinationMeeting::TYPE_FOLLOW_UP,
                CoordinationMeeting::LOCATION_VIDEO_CONFERENCE,
                $this->getDefaultFollowUpAgenda()
            );

            $meetings[] = $meeting;
            $currentDate->add(new \DateInterval('P' . $intervalWeeks . 'W'));
        }

        $this->logger->info('Regular follow-up meetings scheduled', [
            'student_id' => $student->getId(),
            'number_of_meetings' => $numberOfMeetings,
            'interval_weeks' => $intervalWeeks
        ]);

        return $meetings;
    }

    /**
     * Complete a coordination meeting with report
     */
    public function completeMeeting(
        CoordinationMeeting $meeting,
        array $discussionPoints,
        array $decisions,
        array $actionPlan,
        string $meetingReport,
        ?int $duration = null,
        ?int $satisfactionRating = null,
        ?\DateTimeInterface $nextMeetingDate = null
    ): CoordinationMeeting {
        if (!$meeting->canBeEdited()) {
            throw new \InvalidArgumentException('Meeting cannot be edited in its current status');
        }

        $meeting->setDiscussionPoints($discussionPoints)
                ->setDecisions($decisions)
                ->setActionPlan($actionPlan)
                ->setMeetingReport($meetingReport)
                ->setStatus(CoordinationMeeting::STATUS_COMPLETED);

        if ($duration) {
            $meeting->setDuration($duration);
        }

        if ($satisfactionRating) {
            $meeting->setSatisfactionRating($satisfactionRating);
        }

        if ($nextMeetingDate) {
            $meeting->setNextMeetingDate($nextMeetingDate);
        }

        $this->entityManager->flush();

        $this->logger->info('Coordination meeting completed', [
            'meeting_id' => $meeting->getId(),
            'satisfaction_rating' => $satisfactionRating,
            'next_meeting_scheduled' => $nextMeetingDate !== null
        ]);

        // Schedule next meeting if requested
        if ($nextMeetingDate) {
            $this->createMeeting(
                $meeting->getStudent(),
                $meeting->getPedagogicalSupervisor(),
                $meeting->getMentor(),
                $nextMeetingDate,
                CoordinationMeeting::TYPE_FOLLOW_UP,
                $meeting->getLocation(),
                $this->getDefaultFollowUpAgenda()
            );
        }

        return $meeting;
    }

    /**
     * Cancel a coordination meeting
     */
    public function cancelMeeting(CoordinationMeeting $meeting, string $reason = ''): CoordinationMeeting
    {
        if (!$meeting->canBeEdited()) {
            throw new \InvalidArgumentException('Meeting cannot be cancelled in its current status');
        }

        $meeting->setStatus(CoordinationMeeting::STATUS_CANCELLED);
        
        if ($reason) {
            $meeting->setNotes($reason);
        }

        $this->entityManager->flush();

        $this->logger->info('Coordination meeting cancelled', [
            'meeting_id' => $meeting->getId(),
            'reason' => $reason
        ]);

        return $meeting;
    }

    /**
     * Postpone a coordination meeting
     */
    public function postponeMeeting(CoordinationMeeting $meeting, \DateTimeInterface $newDate): CoordinationMeeting
    {
        if (!$meeting->canBeEdited()) {
            throw new \InvalidArgumentException('Meeting cannot be postponed in its current status');
        }

        $oldDate = $meeting->getDate();
        $meeting->postpone($newDate);

        $this->entityManager->flush();

        $this->logger->info('Coordination meeting postponed', [
            'meeting_id' => $meeting->getId(),
            'old_date' => $oldDate?->format('Y-m-d H:i:s'),
            'new_date' => $newDate->format('Y-m-d H:i:s')
        ]);

        return $meeting;
    }

    /**
     * Get upcoming meetings for a student
     */
    public function getUpcomingMeetings(Student $student): array
    {
        return $this->coordinationMeetingRepository->findUpcomingByStudent($student);
    }

    /**
     * Get meetings requiring follow-up
     */
    public function getMeetingsRequiringFollowUp(): array
    {
        return $this->coordinationMeetingRepository->findRequiringFollowUp();
    }

    /**
     * Get missed meetings that need rescheduling
     */
    public function getMissedMeetings(): array
    {
        return $this->coordinationMeetingRepository->findMissedMeetings();
    }

    /**
     * Get coordination statistics for reporting
     */
    public function getCoordinationStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->coordinationMeetingRepository->getCoordinationStatistics($startDate, $endDate);
    }

    /**
     * Find students needing coordination meetings
     */
    public function getStudentsNeedingMeetings(int $daysSinceLastMeeting = 30): array
    {
        return $this->coordinationMeetingRepository->findStudentsNeedingMeetings($daysSinceLastMeeting);
    }

    /**
     * Send meeting reminders
     */
    public function sendMeetingReminders(int $hoursBeforeMeeting = 24): void
    {
        $reminderDate = new \DateTime('+' . $hoursBeforeMeeting . ' hours');
        
        $upcomingMeetings = $this->coordinationMeetingRepository->createQueryBuilder('cm')
            ->andWhere('cm.status = :status')
            ->andWhere('cm.date <= :reminderDate')
            ->andWhere('cm.date > :now')
            ->setParameter('status', CoordinationMeeting::STATUS_PLANNED)
            ->setParameter('reminderDate', $reminderDate)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        foreach ($upcomingMeetings as $meeting) {
            // TODO: Send email/notification reminders
            $this->logger->info('Meeting reminder should be sent', [
                'meeting_id' => $meeting->getId(),
                'scheduled_date' => $meeting->getDate()->format('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Get default follow-up agenda
     */
    private function getDefaultFollowUpAgenda(): array
    {
        return [
            'Bilan de la période écoulée',
            'Progression sur les objectifs pédagogiques',
            'Difficultés rencontrées et solutions',
            'Projets et missions en cours',
            'Évaluation des compétences acquises',
            'Planification de la période suivante',
            'Points d\'amélioration et actions correctives',
            'Préparation des évaluations à venir'
        ];
    }

    /**
     * Generate meeting report template
     */
    public function generateMeetingReportTemplate(CoordinationMeeting $meeting): array
    {
        return [
            'meeting_info' => [
                'date' => $meeting->getDate()?->format('d/m/Y à H:i'),
                'type' => $meeting->getTypeLabel(),
                'location' => $meeting->getLocationLabel(),
                'participants' => [
                    'student' => $meeting->getStudent()?->getFullName(),
                    'pedagogical_supervisor' => $meeting->getPedagogicalSupervisor()?->getFullName(),
                    'mentor' => $meeting->getMentor()?->getFullName(),
                    'company' => $meeting->getMentor()?->getCompanyName()
                ]
            ],
            'agenda' => $meeting->getAgenda(),
            'discussion_points' => [],
            'decisions' => [],
            'action_plan' => [],
            'next_steps' => '',
            'satisfaction_evaluation' => null,
            'next_meeting_date' => null
        ];
    }

    /**
     * Auto-schedule coordination meetings based on alternance calendar
     */
    public function autoScheduleCoordinationMeetings(Student $student): array
    {
        // This would integrate with the alternance program calendar
        // to automatically schedule meetings at key transition points
        
        $meetings = [];
        
        // TODO: Implement auto-scheduling logic based on:
        // - Alternance program phases
        // - Company/center transitions
        // - Evaluation periods
        // - Critical milestones
        
        return $meetings;
    }
}
