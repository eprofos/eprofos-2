<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\ProgressAssessment;
use App\Entity\Core\StudentProgress;
use App\Entity\User\Student;
use App\Repository\Alternance\ProgressAssessmentRepository;
use App\Repository\Core\StudentProgressRepository;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * RiskAssessmentService.
 *
 * Specialized service for detecting alternance students at risk of dropout
 * and generating intervention recommendations.
 */
class RiskAssessmentService
{
    // Risk thresholds
    private const LOW_RISK_THRESHOLD = 2;

    private const MODERATE_RISK_THRESHOLD = 3;

    private const HIGH_RISK_THRESHOLD = 4;

    private const CRITICAL_RISK_THRESHOLD = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProgressAssessmentRepository $progressAssessmentRepository,
        private StudentProgressRepository $studentProgressRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Assess risk level for a student.
     */
    public function assessStudentRisk(Student $student): array
    {
        $latestProgress = $this->progressAssessmentRepository->findLatestByStudent($student);
        $studentProgress = $this->studentProgressRepository->findOneBy(['student' => $student]);

        if (!$latestProgress || !$studentProgress) {
            return $this->createDefaultRiskAssessment($student);
        }

        $riskFactors = $this->analyzeRiskFactors($student, $latestProgress, $studentProgress);
        $riskLevel = $this->calculateRiskLevel($riskFactors);
        $interventions = $this->generateInterventions($riskLevel, $riskFactors);

        return [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName(),
                'email' => $student->getEmail(),
            ],
            'risk_level' => $riskLevel,
            'risk_category' => $this->getRiskCategory($riskLevel),
            'risk_factors' => $riskFactors,
            'risk_score' => $this->calculateRiskScore($riskFactors),
            'assessment_date' => new DateTime(),
            'interventions' => $interventions,
            'monitoring_frequency' => $this->determineMonitoringFrequency($riskLevel),
            'early_warning_indicators' => $this->getEarlyWarningIndicators($latestProgress, $studentProgress),
        ];
    }

    /**
     * Get all students at risk above specified threshold.
     */
    public function getStudentsAtRisk(int $minimumRiskLevel = self::MODERATE_RISK_THRESHOLD): array
    {
        $atRiskStudents = $this->progressAssessmentRepository->findStudentsAtRisk($minimumRiskLevel);
        $results = [];

        foreach ($atRiskStudents as $assessment) {
            $student = $assessment->getStudent();
            $riskAssessment = $this->assessStudentRisk($student);

            if ($riskAssessment['risk_level'] >= $minimumRiskLevel) {
                $results[] = $riskAssessment;
            }
        }

        // Sort by risk level (highest first)
        usort($results, static fn ($a, $b) => $b['risk_level'] - $a['risk_level']);

        return $results;
    }

    /**
     * Generate comprehensive risk report.
     */
    public function generateRiskReport(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $allStudents = $this->studentProgressRepository->findStudentsWithAlternanceContract();
        $riskData = [];
        $riskDistribution = [
            'low' => 0,
            'moderate' => 0,
            'high' => 0,
            'critical' => 0,
        ];

        foreach ($allStudents as $studentProgress) {
            $student = $studentProgress->getStudent();
            $riskAssessment = $this->assessStudentRisk($student);

            $riskData[] = $riskAssessment;

            $category = $riskAssessment['risk_category'];
            if (isset($riskDistribution[$category])) {
                $riskDistribution[$category]++;
            }
        }

        return [
            'report_period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_students' => count($allStudents),
            'risk_distribution' => $riskDistribution,
            'students_needing_immediate_attention' => array_filter($riskData, static fn ($student) => $student['risk_level'] >= self::HIGH_RISK_THRESHOLD),
            'common_risk_factors' => $this->analyzeCommonRiskFactors($riskData),
            'intervention_recommendations' => $this->generateGlobalInterventions($riskData),
            'monitoring_schedule' => $this->createMonitoringSchedule($riskData),
        ];
    }

    /**
     * Monitor risk evolution over time.
     */
    public function monitorRiskEvolution(Student $student, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $assessments = $this->progressAssessmentRepository->findByStudentAndDateRange($student, $startDate, $endDate);
        $evolution = [];

        foreach ($assessments as $assessment) {
            $riskLevel = $assessment->getRiskLevel();
            $evolution[] = [
                'date' => $assessment->getPeriod()->format('Y-m-d'),
                'risk_level' => $riskLevel,
                'risk_category' => $this->getRiskCategory($riskLevel),
                'risk_factors' => $assessment->getRiskFactorsAnalysis(),
                'interventions_applied' => $this->getInterventionsForPeriod($assessment),
            ];
        }

        return [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName(),
            ],
            'monitoring_period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'risk_evolution' => $evolution,
            'trend_analysis' => $this->analyzeTrend($evolution),
            'effectiveness_assessment' => $this->assessInterventionEffectiveness($evolution),
        ];
    }

    /**
     * Predict future risk based on current trajectory.
     */
    public function predictFutureRisk(Student $student, int $weeksAhead = 4): array
    {
        $recentAssessments = $this->progressAssessmentRepository->findBy(
            ['student' => $student],
            ['period' => 'DESC'],
            5, // Last 5 assessments
        );

        if (count($recentAssessments) < 2) {
            return [
                'prediction_available' => false,
                'reason' => 'Insufficient historical data',
            ];
        }

        $trend = $this->calculateRiskTrend($recentAssessments);
        $currentRisk = $recentAssessments[0]->getRiskLevel();
        $predictedRisk = min(5, max(1, $currentRisk + ($trend * $weeksAhead / 4)));

        return [
            'prediction_available' => true,
            'current_risk' => $currentRisk,
            'predicted_risk' => round($predictedRisk, 1),
            'weeks_ahead' => $weeksAhead,
            'trend' => $trend > 0 ? 'increasing' : ($trend < 0 ? 'decreasing' : 'stable'),
            'confidence_level' => $this->calculatePredictionConfidence($recentAssessments),
            'early_intervention_window' => $this->determineInterventionWindow($predictedRisk),
            'recommended_actions' => $this->getPreventiveActions($predictedRisk, $trend),
        ];
    }

    /**
     * Analyze risk factors for a student.
     */
    private function analyzeRiskFactors(Student $student, ProgressAssessment $latestProgress, StudentProgress $studentProgress): array
    {
        $factors = [];

        // Academic progression factors
        $overallProgression = (float) $latestProgress->getOverallProgression();
        if ($overallProgression < 30) {
            $factors[] = [
                'category' => 'academic',
                'factor' => 'low_overall_progression',
                'severity' => 'high',
                'value' => $overallProgression,
                'description' => "Progression globale faible ({$overallProgression}%)",
            ];
        }

        // Center vs Company progression gap
        $centerProgression = (float) $latestProgress->getCenterProgression();
        $companyProgression = (float) $latestProgress->getCompanyProgression();
        $gap = abs($centerProgression - $companyProgression);

        if ($gap > 20) {
            $factors[] = [
                'category' => 'academic',
                'factor' => 'progression_gap',
                'severity' => 'medium',
                'value' => $gap,
                'description' => "Écart important entre centre ({$centerProgression}%) et entreprise ({$companyProgression}%)",
            ];
        }

        // Difficulties analysis
        $difficulties = $latestProgress->getDifficulties();
        if (!empty($difficulties)) {
            $severeDifficulties = array_filter($difficulties, static fn ($difficulty) => ($difficulty['severity'] ?? 0) >= 4);

            if (!empty($severeDifficulties)) {
                $factors[] = [
                    'category' => 'behavioral',
                    'factor' => 'severe_difficulties',
                    'severity' => 'high',
                    'value' => count($severeDifficulties),
                    'description' => 'Difficultés importantes identifiées',
                ];
            }
        }

        // Support needs analysis
        $supportNeeded = $latestProgress->getSupportNeeded();
        if (!empty($supportNeeded)) {
            $urgentSupport = array_filter($supportNeeded, static fn ($support) => ($support['urgency'] ?? 0) >= 4);

            if (!empty($urgentSupport)) {
                $factors[] = [
                    'category' => 'support',
                    'factor' => 'urgent_support_needed',
                    'severity' => 'high',
                    'value' => count($urgentSupport),
                    'description' => 'Accompagnement urgent nécessaire',
                ];
            }
        }

        // Attendance factors (if available)
        $engagementScore = $studentProgress->calculateEngagementScore();
        if ($engagementScore < 60) {
            $factors[] = [
                'category' => 'engagement',
                'factor' => 'low_engagement',
                'severity' => 'medium',
                'value' => $engagementScore,
                'description' => "Score d'engagement faible ({$engagementScore}%)",
            ];
        }

        // Mission completion factors (for alternance)
        if ($studentProgress->getAlternanceContract()) {
            $missionProgress = $studentProgress->getMissionProgress() ?? [];
            if (!empty($missionProgress)) {
                $completedMissions = array_filter($missionProgress, static fn ($mission) => ($mission['completion_rate'] ?? 0) >= 80);

                $completionRate = count($missionProgress) > 0 ?
                    (count($completedMissions) / count($missionProgress)) * 100 : 0;

                if ($completionRate < 50) {
                    $factors[] = [
                        'category' => 'professional',
                        'factor' => 'low_mission_completion',
                        'severity' => 'medium',
                        'value' => $completionRate,
                        'description' => "Taux de completion des missions faible ({$completionRate}%)",
                    ];
                }
            }
        }

        return $factors;
    }

    /**
     * Calculate overall risk level based on factors.
     */
    private function calculateRiskLevel(array $riskFactors): int
    {
        if (empty($riskFactors)) {
            return 1; // No risk
        }

        $totalWeight = 0;
        $severityWeights = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
        ];

        foreach ($riskFactors as $factor) {
            $weight = $severityWeights[$factor['severity']] ?? 1;
            $totalWeight += $weight;
        }

        $averageWeight = $totalWeight / count($riskFactors);

        // Convert to 1-5 scale
        if ($averageWeight <= 1.2) {
            return 1;
        }
        if ($averageWeight <= 1.7) {
            return 2;
        }
        if ($averageWeight <= 2.3) {
            return 3;
        }
        if ($averageWeight <= 2.8) {
            return 4;
        }

        return 5;
    }

    /**
     * Calculate numerical risk score.
     */
    private function calculateRiskScore(array $riskFactors): int
    {
        $score = 0;
        $weights = [
            'academic' => 30,
            'behavioral' => 25,
            'professional' => 20,
            'engagement' => 15,
            'support' => 10,
        ];

        foreach ($riskFactors as $factor) {
            $categoryWeight = $weights[$factor['category']] ?? 10;
            $severityMultiplier = match ($factor['severity']) {
                'high' => 3,
                'medium' => 2,
                'low' => 1,
                default => 1
            };

            $score += $categoryWeight * $severityMultiplier;
        }

        return min(100, $score);
    }

    /**
     * Get risk category name.
     */
    private function getRiskCategory(int $riskLevel): string
    {
        return match ($riskLevel) {
            1, 2 => 'low',
            3 => 'moderate',
            4 => 'high',
            5 => 'critical',
            default => 'unknown'
        };
    }

    /**
     * Generate interventions based on risk level and factors.
     */
    private function generateInterventions(int $riskLevel, array $riskFactors): array
    {
        $interventions = [];

        // Immediate interventions based on risk level
        if ($riskLevel >= self::CRITICAL_RISK_THRESHOLD) {
            $interventions[] = [
                'type' => 'immediate',
                'priority' => 'critical',
                'title' => 'Intervention d\'urgence',
                'description' => 'Convocation immédiate pour entretien de situation',
                'timeline' => '48 heures',
                'responsible' => 'Responsable pédagogique + Tuteur entreprise',
            ];
        } elseif ($riskLevel >= self::HIGH_RISK_THRESHOLD) {
            $interventions[] = [
                'type' => 'urgent',
                'priority' => 'high',
                'title' => 'Plan d\'accompagnement renforcé',
                'description' => 'Mise en place d\'un suivi hebdomadaire personnalisé',
                'timeline' => '1 semaine',
                'responsible' => 'Formateur référent',
            ];
        } elseif ($riskLevel >= self::MODERATE_RISK_THRESHOLD) {
            $interventions[] = [
                'type' => 'preventive',
                'priority' => 'medium',
                'title' => 'Surveillance accrue',
                'description' => 'Augmentation de la fréquence des points de suivi',
                'timeline' => '2 semaines',
                'responsible' => 'Équipe pédagogique',
            ];
        }

        // Specific interventions based on risk factors
        foreach ($riskFactors as $factor) {
            switch ($factor['factor']) {
                case 'low_overall_progression':
                    $interventions[] = [
                        'type' => 'academic_support',
                        'priority' => 'high',
                        'title' => 'Soutien pédagogique intensif',
                        'description' => 'Cours de rattrapage et tutorat personnalisé',
                        'timeline' => '1 mois',
                        'responsible' => 'Équipe pédagogique',
                    ];
                    break;

                case 'progression_gap':
                    $interventions[] = [
                        'type' => 'coordination',
                        'priority' => 'medium',
                        'title' => 'Harmonisation centre-entreprise',
                        'description' => 'Réunion tripartite pour aligner les attentes',
                        'timeline' => '2 semaines',
                        'responsible' => 'Coordinateur alternance',
                    ];
                    break;

                case 'severe_difficulties':
                    $interventions[] = [
                        'type' => 'psychological_support',
                        'priority' => 'high',
                        'title' => 'Accompagnement spécialisé',
                        'description' => 'Orientation vers un conseiller spécialisé',
                        'timeline' => '1 semaine',
                        'responsible' => 'Service social',
                    ];
                    break;

                case 'low_engagement':
                    $interventions[] = [
                        'type' => 'motivation',
                        'priority' => 'medium',
                        'title' => 'Remotivation',
                        'description' => 'Entretien de motivation et redéfinition des objectifs',
                        'timeline' => '10 jours',
                        'responsible' => 'Formateur référent',
                    ];
                    break;
            }
        }

        return $interventions;
    }

    /**
     * Determine monitoring frequency based on risk level.
     */
    private function determineMonitoringFrequency(int $riskLevel): string
    {
        return match ($riskLevel) {
            5 => 'daily',
            4 => 'twice_weekly',
            3 => 'weekly',
            2 => 'bi_weekly',
            1 => 'monthly',
            default => 'monthly'
        };
    }

    /**
     * Get early warning indicators.
     */
    private function getEarlyWarningIndicators(ProgressAssessment $latestProgress, StudentProgress $studentProgress): array
    {
        $indicators = [];

        // Declining progression trend
        $overallProgression = (float) $latestProgress->getOverallProgression();
        if ($overallProgression < 40) {
            $indicators[] = [
                'type' => 'academic',
                'indicator' => 'Progression globale en deçà des attentes',
                'value' => $overallProgression,
                'threshold' => 40,
                'status' => 'warning',
            ];
        }

        // High number of pending objectives
        $pendingObjectives = $latestProgress->getPendingObjectives();
        if (count($pendingObjectives) > 5) {
            $indicators[] = [
                'type' => 'academic',
                'indicator' => 'Accumulation d\'objectifs en attente',
                'value' => count($pendingObjectives),
                'threshold' => 5,
                'status' => 'warning',
            ];
        }

        // Low engagement score
        $engagementScore = $studentProgress->calculateEngagementScore();
        if ($engagementScore < 70) {
            $indicators[] = [
                'type' => 'behavioral',
                'indicator' => 'Score d\'engagement en baisse',
                'value' => $engagementScore,
                'threshold' => 70,
                'status' => 'warning',
            ];
        }

        return $indicators;
    }

    /**
     * Create default risk assessment when data is insufficient.
     */
    private function createDefaultRiskAssessment(Student $student): array
    {
        return [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName(),
                'email' => $student->getEmail(),
            ],
            'risk_level' => 1,
            'risk_category' => 'low',
            'risk_factors' => [],
            'risk_score' => 0,
            'assessment_date' => new DateTime(),
            'interventions' => [],
            'monitoring_frequency' => 'monthly',
            'early_warning_indicators' => [],
            'note' => 'Données insuffisantes pour une évaluation complète',
        ];
    }

    // Additional helper methods for comprehensive analysis
    private function analyzeCommonRiskFactors(array $riskData): array
    {
        $factorCounts = [];

        foreach ($riskData as $student) {
            foreach ($student['risk_factors'] as $factor) {
                $key = $factor['factor'];
                if (!isset($factorCounts[$key])) {
                    $factorCounts[$key] = 0;
                }
                $factorCounts[$key]++;
            }
        }

        arsort($factorCounts);

        return array_slice($factorCounts, 0, 5, true);
    }

    private function generateGlobalInterventions(array $riskData): array
    {
        $highRiskCount = count(array_filter($riskData, static fn ($student) => $student['risk_level'] >= self::HIGH_RISK_THRESHOLD));

        $interventions = [];

        if ($highRiskCount > 5) {
            $interventions[] = [
                'type' => 'systemic',
                'title' => 'Formation équipe pédagogique',
                'description' => 'Formation sur la détection précoce des signaux de décrochage',
                'priority' => 'high',
            ];
        }

        return $interventions;
    }

    private function createMonitoringSchedule(array $riskData): array
    {
        $schedule = [];

        foreach ($riskData as $student) {
            if ($student['risk_level'] >= self::MODERATE_RISK_THRESHOLD) {
                $schedule[] = [
                    'student_id' => $student['student']['id'],
                    'student_name' => $student['student']['name'],
                    'frequency' => $student['monitoring_frequency'],
                    'next_check' => $this->calculateNextCheckDate($student['monitoring_frequency']),
                    'responsible' => $this->determineResponsible($student['risk_level']),
                ];
            }
        }

        return $schedule;
    }

    private function calculateNextCheckDate(string $frequency): string
    {
        $interval = match ($frequency) {
            'daily' => 'P1D',
            'twice_weekly' => 'P3D',
            'weekly' => 'P7D',
            'bi_weekly' => 'P14D',
            'monthly' => 'P1M',
            default => 'P7D'
        };

        return (new DateTime())->add(new DateInterval($interval))->format('Y-m-d');
    }

    private function determineResponsible(int $riskLevel): string
    {
        return match ($riskLevel) {
            5 => 'Directeur pédagogique',
            4 => 'Responsable formation',
            3 => 'Formateur référent',
            default => 'Équipe pédagogique'
        };
    }

    private function getInterventionsForPeriod(ProgressAssessment $assessment): array
    {
        // This would typically be stored separately and retrieved from a database
        // For now, we'll generate some based on the assessment data
        return [];
    }

    private function analyzeTrend(array $evolution): array
    {
        if (count($evolution) < 2) {
            return ['trend' => 'insufficient_data'];
        }

        $first = reset($evolution);
        $last = end($evolution);

        $riskChange = $last['risk_level'] - $first['risk_level'];

        return [
            'trend' => $riskChange > 0 ? 'increasing' : ($riskChange < 0 ? 'decreasing' : 'stable'),
            'change_magnitude' => abs($riskChange),
            'timespan_days' => (new DateTime($last['date']))->diff(new DateTime($first['date']))->days,
        ];
    }

    private function assessInterventionEffectiveness(array $evolution): array
    {
        // Analyze whether interventions led to risk reduction
        $effectiveness = [];

        for ($i = 1; $i < count($evolution); $i++) {
            $previous = $evolution[$i - 1];
            $current = $evolution[$i];

            if (!empty($previous['interventions_applied'])) {
                $riskChange = $current['risk_level'] - $previous['risk_level'];
                $effectiveness[] = [
                    'period' => $current['date'],
                    'intervention_count' => count($previous['interventions_applied']),
                    'risk_change' => $riskChange,
                    'effective' => $riskChange <= 0,
                ];
            }
        }

        return $effectiveness;
    }

    private function calculateRiskTrend(array $recentAssessments): float
    {
        if (count($recentAssessments) < 2) {
            return 0;
        }

        $riskLevels = array_map(static fn ($assessment) => $assessment->getRiskLevel(), array_reverse($recentAssessments)); // Reverse for chronological order

        // Simple linear trend calculation
        $n = count($riskLevels);
        $sumX = array_sum(range(1, $n));
        $sumY = array_sum($riskLevels);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $riskLevels[$i];
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        return $slope;
    }

    private function calculatePredictionConfidence(array $recentAssessments): string
    {
        $count = count($recentAssessments);

        if ($count >= 5) {
            return 'high';
        }
        if ($count >= 3) {
            return 'medium';
        }

        return 'low';
    }

    private function determineInterventionWindow(float $predictedRisk): int
    {
        if ($predictedRisk >= 4) {
            return 1;
        } // 1 week
        if ($predictedRisk >= 3) {
            return 2;
        } // 2 weeks

        return 4; // 1 month
    }

    private function getPreventiveActions(float $predictedRisk, float $trend): array
    {
        $actions = [];

        if ($trend > 0.5) { // Increasing risk
            $actions[] = 'Surveillance renforcée immédiate';
            $actions[] = 'Entretien préventif programmé';
        }

        if ($predictedRisk >= 3.5) {
            $actions[] = 'Préparation plan d\'intervention';
            $actions[] = 'Coordination avec tuteur entreprise';
        }

        return $actions;
    }
}
