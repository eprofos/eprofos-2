<?php

namespace App\Service\Alternance;

use App\Entity\User\Student;
use App\Repository\Alternance\CoordinationMeetingRepository;
use App\Repository\Alternance\CompanyVisitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for generating coordination and communication reports
 * 
 * Provides comprehensive reporting for coordination activities, communication
 * effectiveness, and Qualiopi compliance metrics for apprenticeship programs.
 */
class AlternanceReportingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CoordinationMeetingRepository $coordinationMeetingRepository,
        private CompanyVisitRepository $companyVisitRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Generate comprehensive coordination report for a period
     */
    public function generateCoordinationReport(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $filters = []
    ): array {
        $meetingStats = $this->coordinationMeetingRepository->getCoordinationStatistics($startDate, $endDate);
        $visitStats = $this->companyVisitRepository->getVisitStatistics($startDate, $endDate);

        return [
            'report_period' => [
                'start_date' => $startDate->format('d/m/Y'),
                'end_date' => $endDate->format('d/m/Y'),
                'duration_days' => $startDate->diff($endDate)->days
            ],
            'coordination_meetings' => $meetingStats,
            'company_visits' => $visitStats,
            'overall_metrics' => $this->calculateOverallMetrics($meetingStats, $visitStats),
            'quality_indicators' => $this->generateQualityIndicators($meetingStats, $visitStats),
            'compliance_status' => $this->assessQualiopiCompliance($meetingStats, $visitStats),
            'recommendations' => $this->generateRecommendations($meetingStats, $visitStats)
        ];
    }

    /**
     * Generate student-specific coordination report
     */
    public function generateStudentCoordinationReport(
        Student $student,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $meetings = $this->coordinationMeetingRepository->createQueryBuilder('cm')
            ->andWhere('cm.student = :student')
            ->andWhere('cm.date >= :startDate')
            ->andWhere('cm.date <= :endDate')
            ->setParameter('student', $student)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('cm.date', 'ASC')
            ->getQuery()
            ->getResult();

        $visits = $this->companyVisitRepository->findByStudentAndDateRange($student, $startDate, $endDate);

        return [
            'student_info' => [
                'name' => $student->getFullName(),
                'student_id' => $student->getId()
            ],
            'report_period' => [
                'start_date' => $startDate->format('d/m/Y'),
                'end_date' => $endDate->format('d/m/Y')
            ],
            'coordination_summary' => [
                'total_meetings' => count($meetings),
                'completed_meetings' => count(array_filter($meetings, fn($m) => $m->isCompleted())),
                'total_visits' => count($visits),
                'average_meeting_satisfaction' => $this->calculateAverageSatisfaction($meetings),
                'average_visit_rating' => $this->calculateAverageVisitRating($visits)
            ],
            'coordination_timeline' => $this->buildCoordinationTimeline($meetings, $visits),
            'progress_indicators' => $this->assessStudentProgress($student, $meetings, $visits),
            'alerts_and_issues' => $this->identifyStudentIssues($meetings, $visits),
            'next_steps' => $this->suggestNextSteps($student, $meetings, $visits)
        ];
    }

    /**
     * Generate Qualiopi compliance report
     */
    public function generateQualiopiComplianceReport(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $meetingStats = $this->coordinationMeetingRepository->getCoordinationStatistics($startDate, $endDate);
        $visitStats = $this->companyVisitRepository->getVisitStatistics($startDate, $endDate);

        return [
            'compliance_overview' => [
                'report_period' => [
                    'start_date' => $startDate->format('d/m/Y'),
                    'end_date' => $endDate->format('d/m/Y')
                ],
                'overall_compliance_score' => $this->calculateComplianceScore($meetingStats, $visitStats),
                'compliance_status' => $this->determineComplianceStatus($meetingStats, $visitStats)
            ],
            'qualiopi_criteria' => [
                'criterion_12' => $this->assessCriterion12($meetingStats, $visitStats), // Coordination
                'criterion_13' => $this->assessCriterion13($visitStats), // Company supervision
                'criterion_14' => $this->assessCriterion14($meetingStats), // Follow-up quality
            ],
            'evidence_documentation' => [
                'meeting_reports_count' => $meetingStats['completed_meetings'],
                'visit_reports_count' => $visitStats['total_visits'],
                'communication_logs_count' => $this->getCommunicationLogsCount($startDate, $endDate),
                'student_progress_records' => $this->getProgressRecordsCount($startDate, $endDate)
            ],
            'improvement_areas' => $this->identifyImprovementAreas($meetingStats, $visitStats),
            'action_plan' => $this->generateComplianceActionPlan($meetingStats, $visitStats)
        ];
    }

    /**
     * Generate communication effectiveness report
     */
    public function generateCommunicationReport(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return [
            'communication_volume' => [
                'coordination_meetings' => $this->coordinationMeetingRepository->createQueryBuilder('cm')
                    ->select('COUNT(cm.id)')
                    ->andWhere('cm.date >= :startDate')
                    ->andWhere('cm.date <= :endDate')
                    ->setParameter('startDate', $startDate)
                    ->setParameter('endDate', $endDate)
                    ->getQuery()
                    ->getSingleScalarResult(),
                'company_visits' => $this->companyVisitRepository->createQueryBuilder('cv')
                    ->select('COUNT(cv.id)')
                    ->andWhere('cv.visitDate >= :startDate')
                    ->andWhere('cv.visitDate <= :endDate')
                    ->setParameter('startDate', $startDate)
                    ->setParameter('endDate', $endDate)
                    ->getQuery()
                    ->getSingleScalarResult(),
                // TODO: Add other communication channels when implemented
                'email_communications' => 0,
                'liaison_book_entries' => 0
            ],
            'response_metrics' => [
                'average_response_time' => null, // TODO: Implement when communication tracking is added
                'mentor_engagement_rate' => null,
                'supervisor_engagement_rate' => null
            ],
            'effectiveness_indicators' => [
                'issue_resolution_rate' => $this->calculateIssueResolutionRate($startDate, $endDate),
                'proactive_communication_rate' => $this->calculateProactiveCommunicationRate($startDate, $endDate),
                'stakeholder_satisfaction' => $this->calculateStakeholderSatisfaction($startDate, $endDate)
            ],
            'communication_trends' => $this->analyzeCommunicationTrends($startDate, $endDate)
        ];
    }

    /**
     * Calculate overall coordination metrics
     */
    private function calculateOverallMetrics(array $meetingStats, array $visitStats): array
    {
        $totalActivities = $meetingStats['total_meetings'] + $visitStats['total_visits'];
        $completedActivities = $meetingStats['completed_meetings'] + $visitStats['total_visits'];

        return [
            'total_coordination_activities' => $totalActivities,
            'completed_activities' => $completedActivities,
            'overall_completion_rate' => $totalActivities > 0 ? 
                round(($completedActivities / $totalActivities) * 100, 2) : 0,
            'average_activity_duration' => $this->calculateAverageActivityDuration($meetingStats, $visitStats),
            'coordination_frequency' => $this->calculateCoordinationFrequency($totalActivities),
            'stakeholder_engagement' => $this->calculateStakeholderEngagement($meetingStats, $visitStats)
        ];
    }

    /**
     * Generate quality indicators
     */
    private function generateQualityIndicators(array $meetingStats, array $visitStats): array
    {
        return [
            'meeting_quality' => [
                'completion_rate' => $meetingStats['completion_rate'],
                'average_satisfaction' => $meetingStats['avg_satisfaction'],
                'follow_up_rate' => $this->calculateMeetingFollowUpRate($meetingStats)
            ],
            'visit_quality' => [
                'average_rating' => $visitStats['avg_overall_rating'],
                'positive_outcome_rate' => $visitStats['success_rate'],
                'follow_up_rate' => $visitStats['follow_up_rate']
            ],
            'overall_quality_score' => $this->calculateOverallQualityScore($meetingStats, $visitStats)
        ];
    }

    /**
     * Assess Qualiopi compliance
     */
    private function assessQualiopiCompliance(array $meetingStats, array $visitStats): array
    {
        $complianceScore = $this->calculateComplianceScore($meetingStats, $visitStats);

        return [
            'compliance_score' => $complianceScore,
            'compliance_level' => $this->determineComplianceLevel($complianceScore),
            'critical_requirements_met' => $this->checkCriticalRequirements($meetingStats, $visitStats),
            'areas_needing_attention' => $this->identifyComplianceGaps($meetingStats, $visitStats)
        ];
    }

    /**
     * Generate recommendations based on data
     */
    private function generateRecommendations(array $meetingStats, array $visitStats): array
    {
        $recommendations = [];

        // Meeting-related recommendations
        if ($meetingStats['completion_rate'] < 80) {
            $recommendations[] = [
                'type' => 'meeting_completion',
                'priority' => 'high',
                'description' => 'Améliorer le taux de réalisation des réunions de coordination',
                'suggested_actions' => [
                    'Renforcer les rappels automatiques',
                    'Optimiser la planification des créneaux',
                    'Former les acteurs sur l\'importance de la coordination'
                ]
            ];
        }

        if ($meetingStats['avg_satisfaction'] && $meetingStats['avg_satisfaction'] < 3.5) {
            $recommendations[] = [
                'type' => 'meeting_satisfaction',
                'priority' => 'medium',
                'description' => 'Améliorer la qualité et l\'efficacité des réunions',
                'suggested_actions' => [
                    'Structurer davantage les ordres du jour',
                    'Limiter la durée des réunions',
                    'Améliorer les supports de réunion'
                ]
            ];
        }

        // Visit-related recommendations
        if ($visitStats['success_rate'] < 70) {
            $recommendations[] = [
                'type' => 'visit_outcomes',
                'priority' => 'high',
                'description' => 'Améliorer les résultats des visites en entreprise',
                'suggested_actions' => [
                    'Mieux préparer les visites en amont',
                    'Renforcer la formation des mentors',
                    'Adapter les critères d\'évaluation'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Build coordination timeline for student
     */
    private function buildCoordinationTimeline(array $meetings, array $visits): array
    {
        $timeline = [];

        foreach ($meetings as $meeting) {
            $timeline[] = [
                'date' => $meeting->getDate(),
                'type' => 'meeting',
                'activity' => $meeting,
                'summary' => $meeting->getMeetingSummary()
            ];
        }

        foreach ($visits as $visit) {
            $timeline[] = [
                'date' => $visit->getVisitDate(),
                'type' => 'visit',
                'activity' => $visit,
                'summary' => $visit->getVisitSummary()
            ];
        }

        // Sort by date
        usort($timeline, fn($a, $b) => $a['date'] <=> $b['date']);

        return $timeline;
    }

    /**
     * Helper methods for calculations
     */
    private function calculateAverageSatisfaction(array $meetings): ?float
    {
        $ratings = array_filter(array_map(fn($m) => $m->getSatisfactionRating(), $meetings));
        return !empty($ratings) ? round(array_sum($ratings) / count($ratings), 2) : null;
    }

    private function calculateAverageVisitRating(array $visits): ?float
    {
        $ratings = array_filter(array_map(fn($v) => $v->getAverageRating(), $visits));
        return !empty($ratings) ? round(array_sum($ratings) / count($ratings), 2) : null;
    }

    private function calculateComplianceScore(array $meetingStats, array $visitStats): float
    {
        // Simplified compliance scoring - would be more comprehensive in practice
        $score = 0;
        
        if ($meetingStats['completion_rate'] >= 80) $score += 25;
        if ($visitStats['success_rate'] >= 70) $score += 25;
        if ($meetingStats['avg_satisfaction'] && $meetingStats['avg_satisfaction'] >= 3.5) $score += 25;
        if ($visitStats['avg_overall_rating'] && $visitStats['avg_overall_rating'] >= 7) $score += 25;

        return $score;
    }

    private function determineComplianceStatus(array $meetingStats, array $visitStats): string
    {
        $score = $this->calculateComplianceScore($meetingStats, $visitStats);
        
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'satisfactory',
            $score >= 60 => 'needs_improvement',
            default => 'non_compliant'
        };
    }

    // Placeholder methods for features to be implemented
    private function assessCriterion12($meetingStats, $visitStats): array { return ['status' => 'compliant']; }
    private function assessCriterion13($visitStats): array { return ['status' => 'compliant']; }
    private function assessCriterion14($meetingStats): array { return ['status' => 'compliant']; }
    private function getCommunicationLogsCount($startDate, $endDate): int { return 0; }
    private function getProgressRecordsCount($startDate, $endDate): int { return 0; }
    private function identifyImprovementAreas($meetingStats, $visitStats): array { return []; }
    private function generateComplianceActionPlan($meetingStats, $visitStats): array { return []; }
    private function assessStudentProgress($student, $meetings, $visits): array { return []; }
    private function identifyStudentIssues($meetings, $visits): array { return []; }
    private function suggestNextSteps($student, $meetings, $visits): array { return []; }
    private function calculateAverageActivityDuration($meetingStats, $visitStats): ?int { return null; }
    private function calculateCoordinationFrequency($totalActivities): string { return 'adequate'; }
    private function calculateStakeholderEngagement($meetingStats, $visitStats): string { return 'good'; }
    private function calculateMeetingFollowUpRate($meetingStats): float { return 0; }
    private function calculateOverallQualityScore($meetingStats, $visitStats): float { return 0; }
    private function determineComplianceLevel($score): string { return 'satisfactory'; }
    private function checkCriticalRequirements($meetingStats, $visitStats): bool { return true; }
    private function identifyComplianceGaps($meetingStats, $visitStats): array { return []; }
    private function calculateIssueResolutionRate($startDate, $endDate): float { return 0; }
    private function calculateProactiveCommunicationRate($startDate, $endDate): float { return 0; }
    private function calculateStakeholderSatisfaction($startDate, $endDate): float { return 0; }
    private function analyzeCommunicationTrends($startDate, $endDate): array { return []; }
}
