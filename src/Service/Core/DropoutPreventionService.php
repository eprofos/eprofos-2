<?php

declare(strict_types=1);

namespace App\Service\Core;

use App\Entity\Core\StudentProgress;
use App\Entity\Training\Formation;
use App\Entity\User\Student;
use App\Repository\Core\AttendanceRecordRepository;
use App\Repository\Core\StudentProgressRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * DropoutPreventionService - Critical for Qualiopi Criterion 12 compliance.
 *
 * Provides automated risk detection, engagement analysis, and intervention
 * recommendations to prevent student dropouts and ensure training success.
 */
class DropoutPreventionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentProgressRepository $progressRepository,
        private AttendanceRecordRepository $attendanceRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Detect students at risk of dropout (Qualiopi requirement).
     */
    public function detectAtRiskStudents(): array
    {
        $atRiskStudents = [];

        // Get all active student progress records
        $activeProgress = $this->progressRepository->createQueryBuilder('sp')
            ->where('sp.completedAt IS NULL')
            ->getQuery()
            ->getResult()
        ;

        foreach ($activeProgress as $progress) {
            $riskFactors = $this->analyzeRiskFactors($progress);
            $riskScore = $this->calculateRiskScore($riskFactors);

            // Update the progress record with current risk assessment
            $progress->setRiskScore(number_format($riskScore, 2));
            $progress->setAtRiskOfDropout($riskScore >= 40); // 40% threshold
            $progress->setDifficultySignals(array_keys($riskFactors));
            $progress->setLastRiskAssessment(new DateTime());

            if ($riskScore >= 40) {
                $atRiskStudents[] = [
                    'student' => $progress->getStudent(),
                    'formation' => $progress->getFormation(),
                    'progress' => $progress,
                    'riskScore' => $riskScore,
                    'riskFactors' => $riskFactors,
                    'recommendations' => $this->generateInterventionRecommendations($riskFactors),
                ];
            }
        }

        // Save all updates
        $this->entityManager->flush();

        // Sort by risk score (highest first)
        usort($atRiskStudents, static fn ($a, $b) => $b['riskScore'] <=> $a['riskScore']);

        $this->logger->info('Dropout risk analysis completed', [
            'total_analyzed' => count($activeProgress),
            'at_risk_count' => count($atRiskStudents),
        ]);

        return $atRiskStudents;
    }

    /**
     * Calculate overall engagement score for a student.
     */
    public function calculateEngagementScore(Student $student): float
    {
        $progressRecords = $this->progressRepository->findByStudent($student);

        if (empty($progressRecords)) {
            return 50.0; // Neutral score for new students
        }

        $totalScore = 0;
        $totalWeight = 0;

        foreach ($progressRecords as $progress) {
            // Update engagement score for this progress
            $engagementScore = $progress->calculateEngagementScore();

            // Weight by formation importance (more recent = higher weight)
            $daysSinceStart = (new DateTime())->diff($progress->getStartedAt())->days;
            $weight = max(1, 30 - $daysSinceStart); // Newer formations have higher weight

            $totalScore += $engagementScore * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $totalScore / $totalWeight : 50.0;
    }

    /**
     * Trigger intervention alert for at-risk student.
     */
    public function triggerInterventionAlert(Student $student): void
    {
        $progressRecords = $this->progressRepository->findByStudent($student);
        $overallEngagement = $this->calculateEngagementScore($student);

        foreach ($progressRecords as $progress) {
            if ($progress->isAtRiskOfDropout()) {
                $this->logger->warning('Student intervention alert triggered', [
                    'student_id' => $student->getId(),
                    'student_name' => $student->getFullName(),
                    'formation_id' => $progress->getFormation()?->getId(),
                    'formation_title' => $progress->getFormation()?->getTitle(),
                    'risk_score' => $progress->getRiskScore(),
                    'engagement_score' => $progress->getEngagementScore(),
                    'overall_engagement' => $overallEngagement,
                    'difficulty_signals' => $progress->getDifficultySignals(),
                ]);

                // Here you would typically send notifications to instructors/administrators
                // For now, we'll just log the alert
            }
        }
    }

    /**
     * Generate retention report for Qualiopi compliance.
     */
    public function generateRetentionReport(): array
    {
        try {
            $stats = $this->progressRepository->getRetentionStats();
        } catch (Exception $e) {
            // Fallback to simple stats if complex query fails
            $allProgress = $this->progressRepository->findAll();
            $totalStudents = count($allProgress);
            $atRiskCount = count(array_filter($allProgress, static fn ($p) => $p->isAtRiskOfDropout()));
            $completedCount = count(array_filter($allProgress, static fn ($p) => $p->getCompletedAt() !== null));

            $stats = [
                'totalEnrollments' => $totalStudents,
                'completionRate' => $totalStudents > 0 ? ($completedCount / $totalStudents) * 100 : 0,
                'dropoutRate' => $totalStudents > 0 ? ($atRiskCount / $totalStudents) * 100 : 0,
                'averageAttendance' => 85.0, // Default value
            ];
        }

        try {
            $attendanceStats = $this->attendanceRepository->getAttendanceStats();
        } catch (Exception $e) {
            $attendanceStats = ['total_records' => 0, 'present_count' => 0];
        }

        $atRiskStudents = $this->detectAtRiskStudents();

        return [
            'total_formations' => $this->countFormations(),
            'total_students' => $stats['totalEnrollments'] ?? 0,
            'at_risk_count' => count($atRiskStudents),
            'risk_rate' => $stats['dropoutRate'] ?? 0,
            'avg_engagement' => $this->calculateAverageEngagement(),
            'completion_rate' => $stats['completionRate'] ?? 0,
            'average_attendance' => $stats['averageAttendance'] ?? 85.0,
            'report_generated_at' => new DateTime(),
            'attendance_stats' => $attendanceStats,
            'at_risk_students' => $atRiskStudents,
        ];
    }

    /**
     * Analyze dropout patterns for continuous improvement.
     */
    public function analyzeDropoutPatterns(): array
    {
        // Get students who dropped out (high risk + inactive for 30+ days)
        $droppedOut = $this->progressRepository->createQueryBuilder('sp')
            ->where('sp.atRiskOfDropout = true')
            ->andWhere('sp.lastActivity < :threshold')
            ->setParameter('threshold', new DateTime('-30 days'))
            ->getQuery()
            ->getResult()
        ;

        $patterns = [
            'common_signals' => [],
            'risk_factors' => [],
            'formation_analysis' => [],
            'timing_analysis' => [],
        ];

        foreach ($droppedOut as $progress) {
            // Analyze common difficulty signals
            foreach ($progress->getDifficultySignals() as $signal) {
                // Ensure signal is a string that can be used as an array key
                $signalKey = is_string($signal) ? $signal : (string) $signal;
                if (!empty($signalKey)) {
                    $patterns['common_signals'][$signalKey] = ($patterns['common_signals'][$signalKey] ?? 0) + 1;
                }
            }

            // Formation-specific analysis
            $formationId = $progress->getFormation()?->getId();
            if ($formationId) {
                $patterns['formation_analysis'][$formationId] = ($patterns['formation_analysis'][$formationId] ?? 0) + 1;
            }

            // Timing analysis (when did they start showing risk signals)
            if ($progress->getStartedAt() && $progress->getLastRiskAssessment()) {
                $daysBeforeRisk = $progress->getStartedAt()->diff($progress->getLastRiskAssessment())->days;
                $patterns['timing_analysis'][] = $daysBeforeRisk;
            }
        }

        // Calculate averages and insights
        if (!empty($patterns['timing_analysis'])) {
            $patterns['average_days_before_risk'] = array_sum($patterns['timing_analysis']) / count($patterns['timing_analysis']);
        }

        // Sort common signals by frequency
        arsort($patterns['common_signals']);

        return $patterns;
    }

    /**
     * Recommend interventions based on risk factors.
     */
    public function recommendInterventions(Student $student): array
    {
        $progressRecords = $this->progressRepository->findByStudent($student);
        $recommendations = [];

        foreach ($progressRecords as $progress) {
            if ($progress->isAtRiskOfDropout()) {
                $riskFactors = $this->analyzeRiskFactors($progress);
                $interventions = $this->generateInterventionRecommendations($riskFactors);

                $recommendations[] = [
                    'formation' => $progress->getFormation(),
                    'progress' => $progress,
                    'interventions' => $interventions,
                    'priority' => $this->calculateInterventionPriority($riskFactors),
                ];
            }
        }

        // Sort by priority (highest first)
        usort($recommendations, static fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $recommendations;
    }

    /**
     * Export retention data for reports (PDF/Excel/CSV compatible).
     */
    public function exportRetentionData(string $format = 'array'): array
    {
        $report = $this->generateRetentionReport();

        // Format data for export
        $exportData = [
            'metadata' => [
                'report_title' => 'Rapport de Rétention - Critère Qualiopi 12',
                'generated_at' => $report['report_generated_at']->format('Y-m-d H:i:s'),
                'period' => 'Derniers 30 jours',
            ],
            'summary_stats' => [
                'total_formations' => $report['total_formations'],
                'total_students' => $report['total_students'],
                'at_risk_count' => $report['at_risk_count'],
                'risk_rate' => $report['risk_rate'],
                'avg_engagement' => $report['avg_engagement'],
                'completion_rate' => $report['completion_rate'],
                'average_attendance' => $report['average_attendance'],
            ],
            'at_risk_students' => array_map(static fn ($item) => [
                'student_name' => $item['student']->getFullName(),
                'student_email' => $item['student']->getEmail(),
                'formation' => $item['formation']->getTitle(),
                'risk_score' => $item['riskScore'],
                'engagement_score' => $item['progress']->getEngagementScore(),
                'completion_percentage' => $item['progress']->getCompletionPercentage(),
                'attendance_rate' => $item['progress']->getAttendanceRate(),
                'last_activity' => $item['progress']->getLastActivity()?->format('Y-m-d H:i:s'),
                'difficulty_signals' => implode(', ', $item['progress']->getDifficultySignals()),
                'recommendations' => implode('; ', $item['recommendations']),
            ], $report['at_risk_students']),
            'compliance_indicators' => [
                'retention_rate' => 100 - $report['risk_rate'],
                'engagement_threshold_met' => $report['avg_engagement'] >= 60,
                'attendance_threshold_met' => $report['average_attendance'] >= 70,
                'intervention_system_active' => count($report['at_risk_students']) > 0,
            ],
        ];

        return $exportData;
    }

    /**
     * Get all student progress records for analysis.
     */
    public function getAllStudentProgress(): array
    {
        return $this->progressRepository->findAll();
    }

    /**
     * Get attendance statistics for compliance reporting.
     */
    public function getAttendanceStatistics(): array
    {
        $qb = $this->attendanceRepository->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->groupBy('a.status')
        ;

        $results = $qb->getQuery()->getResult();

        $total = array_sum(array_column($results, 'count'));
        $statistics = [];

        foreach ($results as $result) {
            $statistics[$result['status']] = [
                'count' => (int) $result['count'],
                'percentage' => $total > 0 ? ($result['count'] / $total) * 100 : 0,
            ];
        }

        return $statistics;
    }

    /**
     * Analyze risk factors for a student progress record.
     */
    private function analyzeRiskFactors(StudentProgress $progress): array
    {
        $factors = [];

        // Check engagement score
        if ($progress->getEngagementScore() < 40) {
            $factors['low_engagement'] = $progress->getEngagementScore();
        }

        // Check activity patterns
        if ($progress->getLastActivity()) {
            $daysSinceActivity = (new DateTime())->diff($progress->getLastActivity())->days;
            if ($daysSinceActivity > 7) {
                $factors['prolonged_inactivity'] = $daysSinceActivity;
            }
        }

        // Check attendance rate
        $attendanceRate = (float) $progress->getAttendanceRate();
        if ($attendanceRate < 70) {
            $factors['poor_attendance'] = $attendanceRate;
        }

        // Check progress rate
        $completionRate = (float) $progress->getCompletionPercentage();
        if ($progress->getStartedAt()) {
            $daysSinceStart = (new DateTime())->diff($progress->getStartedAt())->days;
            if ($daysSinceStart > 0) {
                $expectedProgress = min(100, ($daysSinceStart / 30) * 50); // Rough estimate
                if ($completionRate < ($expectedProgress * 0.5)) {
                    $factors['slow_progress'] = [
                        'current' => $completionRate,
                        'expected' => $expectedProgress,
                    ];
                }
            }
        }

        // Check missed sessions
        if ($progress->getMissedSessions() >= 2) {
            $factors['frequent_absences'] = $progress->getMissedSessions();
        }

        return $factors;
    }

    /**
     * Calculate overall risk score from risk factors.
     */
    private function calculateRiskScore(array $riskFactors): float
    {
        $score = 0;

        foreach ($riskFactors as $factor => $value) {
            switch ($factor) {
                case 'low_engagement':
                    $score += (60 - $value) * 0.5; // Max 30 points
                    break;

                case 'prolonged_inactivity':
                    $score += min(25, $value * 2); // Max 25 points
                    break;

                case 'poor_attendance':
                    $score += (80 - $value) * 0.3; // Max 24 points for 0% attendance
                    break;

                case 'slow_progress':
                    $expected = $value['expected'] ?? 50;
                    $current = $value['current'] ?? 0;
                    $score += ($expected - $current) * 0.2; // Variable points
                    break;

                case 'frequent_absences':
                    $score += $value * 5; // 5 points per missed session
                    break;
            }
        }

        return min(100, max(0, $score));
    }

    /**
     * Generate intervention recommendations based on risk factors.
     */
    private function generateInterventionRecommendations(array $riskFactors): array
    {
        $recommendations = [];

        foreach ($riskFactors as $factor => $value) {
            switch ($factor) {
                case 'low_engagement':
                    $recommendations[] = 'Entretien individuel de motivation';
                    $recommendations[] = 'Révision des objectifs pédagogiques';
                    break;

                case 'prolonged_inactivity':
                    $recommendations[] = 'Relance téléphonique immédiate';
                    $recommendations[] = 'Proposition de séance de rattrapage';
                    break;

                case 'poor_attendance':
                    $recommendations[] = 'Analyse des contraintes personnelles';
                    $recommendations[] = 'Adaptation du planning si possible';
                    break;

                case 'slow_progress':
                    $recommendations[] = 'Soutien pédagogique personnalisé';
                    $recommendations[] = 'Ressources complémentaires';
                    break;

                case 'frequent_absences':
                    $recommendations[] = 'Entretien sur les difficultés rencontrées';
                    $recommendations[] = 'Plan de rattrapage personnalisé';
                    break;
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Calculate intervention priority.
     */
    private function calculateInterventionPriority(array $riskFactors): int
    {
        $priority = 0;

        // Higher priority for certain factors
        if (isset($riskFactors['prolonged_inactivity'])) {
            $priority += 10;
        }
        if (isset($riskFactors['frequent_absences'])) {
            $priority += 8;
        }
        if (isset($riskFactors['poor_attendance'])) {
            $priority += 6;
        }
        if (isset($riskFactors['low_engagement'])) {
            $priority += 4;
        }
        if (isset($riskFactors['slow_progress'])) {
            $priority += 2;
        }

        return $priority;
    }

    /**
     * Generate system-level recommendations.
     */
    private function generateSystemRecommendations(array $stats, array $atRiskStudents): array
    {
        $recommendations = [];

        if ($stats['dropoutRate'] > 15) {
            $recommendations[] = 'Taux d\'abandon élevé - Revoir la stratégie d\'engagement global';
        }

        if ($stats['averageAttendance'] < 75) {
            $recommendations[] = 'Assiduité insuffisante - Améliorer la flexibilité des horaires';
        }

        if (count($atRiskStudents) > 10) {
            $recommendations[] = 'Nombre élevé d\'étudiants à risque - Renforcer le suivi préventif';
        }

        return $recommendations;
    }

    /**
     * Count total formations for reporting.
     */
    private function countFormations(): int
    {
        return $this->progressRepository->createQueryBuilder('sp')
            ->select('COUNT(DISTINCT sp.formation)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Calculate average engagement score across all students.
     */
    private function calculateAverageEngagement(): float
    {
        $result = $this->progressRepository->createQueryBuilder('sp')
            ->select('AVG(sp.engagementScore)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $result ? (float) $result : 0.0;
    }
}
