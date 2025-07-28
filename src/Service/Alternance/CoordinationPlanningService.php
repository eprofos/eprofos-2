<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\CoordinationMeeting;
use App\Entity\User\Student;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for automated planning and scheduling of coordination activities.
 *
 * Handles automatic scheduling of meetings, visits, and coordination points
 * based on alternance program calendars and business rules for Qualiopi compliance.
 */
class CoordinationPlanningService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CoordinationService $coordinationService,
        private CompanyVisitService $companyVisitService,
        private LoggerInterface $logger,
    ) {}

    /**
     * Auto-plan coordination activities for a student's alternance program.
     */
    public function planCoordinationActivities(Student $student): array
    {
        $activities = [];

        // TODO: Get alternance contract and program details
        // $alternanceContract = $student->getAlternanceContract();
        // if (!$alternanceContract) {
        //     throw new \InvalidArgumentException('Student has no alternance contract');
        // }

        // Plan integration meeting (first week)
        $integrationMeeting = $this->planIntegrationMeeting($student);
        if ($integrationMeeting) {
            $activities['integration_meeting'] = $integrationMeeting;
        }

        // Plan regular follow-up meetings
        $followUpMeetings = $this->planRegularFollowUps($student);
        $activities['follow_up_meetings'] = $followUpMeetings;

        // Plan company visits
        $companyVisits = $this->planCompanyVisits($student);
        $activities['company_visits'] = $companyVisits;

        // Plan evaluation meetings
        $evaluationMeetings = $this->planEvaluationMeetings($student);
        $activities['evaluation_meetings'] = $evaluationMeetings;

        $this->logger->info('Coordination activities planned for student', [
            'student_id' => $student->getId(),
            'total_activities' => array_sum(array_map('count', $activities)),
        ]);

        return $activities;
    }

    /**
     * Generate coordination calendar for a period.
     */
    public function generateCoordinationCalendar(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $students = [],
    ): array {
        $calendar = [];

        // If no students specified, get all students in alternance
        if (empty($students)) {
            $students = $this->getAlternanceStudents();
        }

        foreach ($students as $student) {
            $studentActivities = $this->getPlannedActivitiesForPeriod($student, $startDate, $endDate);

            foreach ($studentActivities as $activity) {
                $date = $activity['date']->format('Y-m-d');

                if (!isset($calendar[$date])) {
                    $calendar[$date] = [];
                }

                $calendar[$date][] = [
                    'type' => $activity['type'],
                    'student' => $student,
                    'activity' => $activity,
                    'priority' => $this->getActivityPriority($activity['type']),
                    'duration' => $activity['estimated_duration'] ?? 60,
                ];
            }
        }

        // Sort activities by priority and time
        foreach ($calendar as $date => &$activities) {
            usort($activities, static fn ($a, $b) => $a['priority'] <=> $b['priority']);
        }

        return $calendar;
    }

    /**
     * Detect coordination conflicts and suggest resolutions.
     */
    public function detectCoordinationConflicts(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $conflicts = [];
        $calendar = $this->generateCoordinationCalendar($startDate, $endDate);

        foreach ($calendar as $date => $activities) {
            // Check for same-day conflicts
            $sameDayConflicts = $this->detectSameDayConflicts($activities);
            if (!empty($sameDayConflicts)) {
                $conflicts[$date] = $sameDayConflicts;
            }

            // Check for resource conflicts (same mentor/supervisor on multiple activities)
            $resourceConflicts = $this->detectResourceConflicts($activities);
            if (!empty($resourceConflicts)) {
                $conflicts[$date] = array_merge($conflicts[$date] ?? [], $resourceConflicts);
            }
        }

        return $conflicts;
    }

    /**
     * Optimize coordination schedule to minimize conflicts.
     */
    public function optimizeCoordinationSchedule(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $constraints = [],
    ): array {
        $originalCalendar = $this->generateCoordinationCalendar($startDate, $endDate);
        $conflicts = $this->detectCoordinationConflicts($startDate, $endDate);

        $optimizations = [];

        foreach ($conflicts as $date => $dayConflicts) {
            foreach ($dayConflicts as $conflict) {
                $resolution = $this->suggestConflictResolution($conflict, $constraints);
                if ($resolution) {
                    $optimizations[] = $resolution;
                }
            }
        }

        return [
            'original_calendar' => $originalCalendar,
            'detected_conflicts' => $conflicts,
            'suggested_optimizations' => $optimizations,
            'optimization_summary' => $this->generateOptimizationSummary($optimizations),
        ];
    }

    /**
     * Plan integration meeting for new alternance student.
     */
    private function planIntegrationMeeting(Student $student): ?array
    {
        // TODO: Get alternance start date from contract
        // $startDate = $alternanceContract->getStartDate();
        // $integrationDate = (clone $startDate)->add(new \DateInterval('P1W')); // One week after start

        // For now, use a placeholder date
        $integrationDate = new DateTime('+1 week');

        return [
            'type' => 'integration_meeting',
            'date' => $integrationDate,
            'meeting_type' => CoordinationMeeting::TYPE_PREPARATORY,
            'priority' => 'high',
            'estimated_duration' => 90,
            'agenda' => [
                'Présentation du programme d\'alternance',
                'Définition des objectifs pédagogiques',
                'Planification des missions entreprise',
                'Organisation du suivi et de l\'évaluation',
                'Calendrier de coordination',
            ],
        ];
    }

    /**
     * Plan regular follow-up meetings.
     */
    private function planRegularFollowUps(Student $student): array
    {
        $followUps = [];

        // TODO: Get alternance duration and rhythm from contract
        $numberOfFollowUps = 6; // Placeholder
        $intervalWeeks = 4; // Every 4 weeks

        $currentDate = new DateTime('+2 weeks'); // Start 2 weeks after integration

        for ($i = 0; $i < $numberOfFollowUps; $i++) {
            $followUps[] = [
                'type' => 'follow_up_meeting',
                'date' => clone $currentDate,
                'meeting_type' => CoordinationMeeting::TYPE_FOLLOW_UP,
                'priority' => 'medium',
                'estimated_duration' => 60,
                'sequence_number' => $i + 1,
            ];

            $currentDate->add(new DateInterval('P' . $intervalWeeks . 'W'));
        }

        return $followUps;
    }

    /**
     * Plan company visits.
     */
    private function planCompanyVisits(Student $student): array
    {
        $visits = [];

        // Integration visit (first month)
        $visits[] = [
            'type' => 'company_visit',
            'date' => new DateTime('+1 month'),
            'visit_type' => 'integration',
            'priority' => 'high',
            'estimated_duration' => 120,
        ];

        // Mid-term visit (middle of alternance)
        $visits[] = [
            'type' => 'company_visit',
            'date' => new DateTime('+6 months'),
            'visit_type' => 'evaluation',
            'priority' => 'medium',
            'estimated_duration' => 90,
        ];

        // Final visit (end of alternance)
        $visits[] = [
            'type' => 'company_visit',
            'date' => new DateTime('+11 months'),
            'visit_type' => 'final_assessment',
            'priority' => 'high',
            'estimated_duration' => 120,
        ];

        return $visits;
    }

    /**
     * Plan evaluation meetings.
     */
    private function planEvaluationMeetings(Student $student): array
    {
        $evaluations = [];

        // Mid-term evaluation
        $evaluations[] = [
            'type' => 'evaluation_meeting',
            'date' => new DateTime('+6 months'),
            'meeting_type' => CoordinationMeeting::TYPE_EVALUATION,
            'priority' => 'high',
            'estimated_duration' => 90,
            'evaluation_type' => 'mid_term',
        ];

        // Final evaluation
        $evaluations[] = [
            'type' => 'evaluation_meeting',
            'date' => new DateTime('+12 months'),
            'meeting_type' => CoordinationMeeting::TYPE_EVALUATION,
            'priority' => 'high',
            'estimated_duration' => 120,
            'evaluation_type' => 'final',
        ];

        return $evaluations;
    }

    /**
     * Get all students in alternance programs.
     */
    private function getAlternanceStudents(): array
    {
        // TODO: Implement query to get students with active alternance contracts
        // For now, return empty array
        return [];
    }

    /**
     * Get planned activities for student in date range.
     */
    private function getPlannedActivitiesForPeriod(
        Student $student,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
    ): array {
        // TODO: Query database for existing planned activities
        // For now, return planned activities from this service
        return array_merge(
            $this->planRegularFollowUps($student),
            $this->planCompanyVisits($student),
            $this->planEvaluationMeetings($student),
        );
    }

    /**
     * Get activity priority level.
     */
    private function getActivityPriority(string $activityType): int
    {
        return match ($activityType) {
            'integration_meeting', 'evaluation_meeting' => 1, // Highest priority
            'company_visit' => 2,
            'follow_up_meeting' => 3,
            default => 4
        };
    }

    /**
     * Detect same-day conflicts.
     */
    private function detectSameDayConflicts(array $activities): array
    {
        $conflicts = [];

        if (count($activities) > 3) { // More than 3 activities in one day
            $conflicts[] = [
                'type' => 'overload',
                'description' => 'Too many coordination activities scheduled for the same day',
                'severity' => 'medium',
                'activities_count' => count($activities),
            ];
        }

        return $conflicts;
    }

    /**
     * Detect resource conflicts.
     */
    private function detectResourceConflicts(array $activities): array
    {
        $conflicts = [];
        $mentorSchedule = [];
        $supervisorSchedule = [];

        foreach ($activities as $activity) {
            // TODO: Extract mentor and supervisor from activity
            // Check for scheduling conflicts
        }

        return $conflicts;
    }

    /**
     * Suggest conflict resolution.
     */
    private function suggestConflictResolution(array $conflict, array $constraints): ?array
    {
        return match ($conflict['type']) {
            'overload' => [
                'type' => 'reschedule',
                'description' => 'Reschedule some activities to adjacent days',
                'suggested_action' => 'move_non_critical_activities',
            ],
            'resource_conflict' => [
                'type' => 'resource_reallocation',
                'description' => 'Assign different resources or adjust timing',
                'suggested_action' => 'stagger_meeting_times',
            ],
            default => null
        };
    }

    /**
     * Generate optimization summary.
     */
    private function generateOptimizationSummary(array $optimizations): array
    {
        return [
            'total_optimizations' => count($optimizations),
            'optimization_types' => array_count_values(array_column($optimizations, 'type')),
            'estimated_time_saved' => $this->calculateTimeSaved($optimizations),
            'coordination_efficiency_improvement' => $this->calculateEfficiencyImprovement($optimizations),
        ];
    }

    /**
     * Calculate estimated time saved through optimizations.
     */
    private function calculateTimeSaved(array $optimizations): int
    {
        // Simple calculation - would be more sophisticated in real implementation
        return count($optimizations) * 30; // 30 minutes saved per optimization
    }

    /**
     * Calculate coordination efficiency improvement.
     */
    private function calculateEfficiencyImprovement(array $optimizations): float
    {
        // Simple calculation - would be more sophisticated in real implementation
        return min(count($optimizations) * 5, 25); // Max 25% improvement
    }
}
