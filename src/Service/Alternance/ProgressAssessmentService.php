<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\ProgressAssessment;
use App\Entity\User\Student;
use App\Entity\StudentProgress;
use App\Repository\Alternance\ProgressAssessmentRepository;
use App\Repository\StudentProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * ProgressAssessmentService
 * 
 * Handles business logic for progress assessment management including
 * creation, calculation, risk assessment, and progression tracking.
 */
class ProgressAssessmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProgressAssessmentRepository $progressAssessmentRepository,
        private StudentProgressRepository $studentProgressRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a new progress assessment
     */
    public function createProgressAssessment(
        Student $student,
        \DateTimeInterface $period
    ): ProgressAssessment {
        $assessment = new ProgressAssessment();
        $assessment->setStudent($student)
            ->setPeriod($period);

        // Calculate initial progression values
        $this->calculateProgression($assessment);

        $this->entityManager->persist($assessment);
        $this->entityManager->flush();

        $this->logger->info('Progress assessment created', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $student->getId(),
            'period' => $period->format('Y-m-d')
        ]);

        return $assessment;
    }

    /**
     * Calculate progression for an assessment
     */
    public function calculateProgression(ProgressAssessment $assessment): ProgressAssessment
    {
        $student = $assessment->getStudent();
        $studentProgress = $this->studentProgressRepository->findOneBy(['student' => $student]);

        if (!$studentProgress) {
            $this->logger->warning('No student progress found for progression calculation', [
                'student_id' => $student->getId()
            ]);
            return $assessment;
        }

        // Calculate center progression from formation progress
        $centerProgression = (float) $studentProgress->getCompletionPercentage();
        $assessment->setCenterProgression(number_format($centerProgression, 2));

        // Calculate company progression from mission progress
        $companyProgression = $this->calculateCompanyProgression($studentProgress);
        $assessment->setCompanyProgression(number_format($companyProgression, 2));

        // Calculate overall progression
        $assessment->calculateOverallProgression();

        // Update skills matrix
        $this->updateSkillsMatrix($assessment, $studentProgress);

        // Calculate risk level
        $assessment->calculateRiskLevel();

        return $assessment;
    }

    /**
     * Update progress assessment with objectives
     */
    public function updateObjectives(
        ProgressAssessment $assessment,
        array $completedObjectives = [],
        array $pendingObjectives = [],
        array $upcomingObjectives = []
    ): ProgressAssessment {
        foreach ($completedObjectives as $objective) {
            $assessment->addCompletedObjective(
                $objective['category'] ?? 'general',
                $objective['objective'],
                isset($objective['completed_at']) ? new \DateTime($objective['completed_at']) : null
            );
        }

        foreach ($pendingObjectives as $objective) {
            $assessment->addPendingObjective(
                $objective['category'] ?? 'general',
                $objective['objective'],
                $objective['target_date'] ?? null,
                $objective['priority'] ?? 3
            );
        }

        foreach ($upcomingObjectives as $objective) {
            $assessment->addUpcomingObjective(
                $objective['category'] ?? 'general',
                $objective['objective'],
                $objective['start_date'] ?? null
            );
        }

        $this->entityManager->flush();

        return $assessment;
    }

    /**
     * Add difficulties to assessment
     */
    public function addDifficulties(ProgressAssessment $assessment, array $difficulties): ProgressAssessment
    {
        foreach ($difficulties as $difficulty) {
            $assessment->addDifficulty(
                $difficulty['area'] ?? 'general',
                $difficulty['description'],
                $difficulty['severity'] ?? 3
            );
        }

        // Recalculate risk level after adding difficulties
        $assessment->calculateRiskLevel();

        $this->entityManager->flush();

        return $assessment;
    }

    /**
     * Add support needed to assessment
     */
    public function addSupportNeeded(ProgressAssessment $assessment, array $supportItems): ProgressAssessment
    {
        foreach ($supportItems as $support) {
            $assessment->addSupportNeeded(
                $support['type'] ?? 'general',
                $support['description'],
                $support['urgency'] ?? 3
            );
        }

        // Recalculate risk level after adding support needs
        $assessment->calculateRiskLevel();

        $this->entityManager->flush();

        return $assessment;
    }

    /**
     * Generate comprehensive progress report
     */
    public function generateProgressReport(Student $student, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $assessments = $this->progressAssessmentRepository->findByStudentAndDateRange($student, $startDate, $endDate);
        $trend = $this->progressAssessmentRepository->getStudentProgressionTrend($student, 6);

        $report = [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName(),
                'email' => $student->getEmail()
            ],
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_assessments' => count($assessments),
                'current_risk_level' => null,
                'current_progression' => null,
                'progression_trend' => $this->calculateProgressionTrend($trend)
            ],
            'assessments' => [],
            'recommendations' => []
        ];

        if (!empty($assessments)) {
            $latestAssessment = end($assessments);
            $report['summary']['current_risk_level'] = $latestAssessment->getRiskLevel();
            $report['summary']['current_progression'] = (float) $latestAssessment->getOverallProgression();

            foreach ($assessments as $assessment) {
                $report['assessments'][] = [
                    'id' => $assessment->getId(),
                    'period' => $assessment->getPeriod()->format('Y-m-d'),
                    'center_progression' => (float) $assessment->getCenterProgression(),
                    'company_progression' => (float) $assessment->getCompanyProgression(),
                    'overall_progression' => (float) $assessment->getOverallProgression(),
                    'risk_level' => $assessment->getRiskLevel(),
                    'objectives_summary' => $assessment->getObjectivesSummary(),
                    'skills_summary' => $assessment->getSkillsMatrixSummary()
                ];
            }

            // Generate recommendations
            $report['recommendations'] = $this->generateRecommendations($latestAssessment);
        }

        return $report;
    }

    /**
     * Detect students at risk
     */
    public function detectStudentsAtRisk(int $riskThreshold = 3): array
    {
        $atRiskAssessments = $this->progressAssessmentRepository->findStudentsAtRisk($riskThreshold);
        
        $studentsAtRisk = [];
        
        foreach ($atRiskAssessments as $assessment) {
            $student = $assessment->getStudent();
            $studentData = [
                'student' => $student,
                'assessment' => $assessment,
                'risk_level' => $assessment->getRiskLevel(),
                'risk_factors' => $assessment->getRiskFactorsAnalysis(),
                'last_assessment_date' => $assessment->getPeriod(),
                'recommendations' => $this->generateRecommendations($assessment)
            ];
            
            $studentsAtRisk[] = $studentData;
        }

        return $studentsAtRisk;
    }

    /**
     * Generate intervention plan for at-risk student
     */
    public function generateInterventionPlan(ProgressAssessment $assessment): array
    {
        $riskFactors = $assessment->getRiskFactorsAnalysis();
        $interventions = [];

        foreach ($riskFactors as $factor) {
            switch ($factor['factor']) {
                case 'Progression globale faible':
                    $interventions[] = [
                        'type' => 'academic_support',
                        'priority' => 'high',
                        'title' => 'Soutien pédagogique renforcé',
                        'actions' => [
                            'Entretien avec le référent pédagogique',
                            'Plan de rattrapage personnalisé',
                            'Tutorat intensif'
                        ],
                        'timeline' => '2 semaines'
                    ];
                    break;

                case 'Difficultés importantes':
                    $interventions[] = [
                        'type' => 'mentoring',
                        'priority' => 'high',
                        'title' => 'Accompagnement spécialisé',
                        'actions' => [
                            'Rencontre tripartite étudiant-formateur-tuteur',
                            'Adaptation du rythme de formation',
                            'Ressources pédagogiques supplémentaires'
                        ],
                        'timeline' => '1 semaine'
                    ];
                    break;

                case 'Déséquilibre centre-entreprise':
                    $interventions[] = [
                        'type' => 'coordination',
                        'priority' => 'medium',
                        'title' => 'Harmonisation centre-entreprise',
                        'actions' => [
                            'Réunion de coordination tripartite',
                            'Ajustement du planning alternance',
                            'Clarification des objectifs'
                        ],
                        'timeline' => '3 semaines'
                    ];
                    break;
            }
        }

        // Add general intervention if no specific factors found
        if (empty($interventions) && $assessment->getRiskLevel() >= 3) {
            $interventions[] = [
                'type' => 'general_support',
                'priority' => 'medium',
                'title' => 'Accompagnement général',
                'actions' => [
                    'Entretien de situation',
                    'Évaluation des besoins',
                    'Plan d\'accompagnement personnalisé'
                ],
                'timeline' => '2 semaines'
            ];
        }

        return $interventions;
    }

    /**
     * Calculate company progression from student progress
     */
    private function calculateCompanyProgression(StudentProgress $studentProgress): float
    {
        $missionProgress = $studentProgress->getMissionProgress();
        
        if (empty($missionProgress)) {
            return 0.0;
        }

        $totalWeight = 0;
        $weightedCompletion = 0;

        foreach ($missionProgress as $mission) {
            $completionRate = $mission['completion_rate'] ?? 0;
            $weight = 1; // All missions have equal weight for now
            
            $weightedCompletion += $completionRate * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedCompletion / $totalWeight : 0.0;
    }

    /**
     * Update skills matrix in assessment
     */
    private function updateSkillsMatrix(ProgressAssessment $assessment, StudentProgress $studentProgress): void
    {
        $skillsAcquired = $studentProgress->getSkillsAcquired();
        
        foreach ($skillsAcquired as $skillCode => $skillData) {
            $assessment->updateSkillInMatrix(
                $skillCode,
                $skillData['name'] ?? $skillCode,
                $skillData['level'] ?? 0,
                $skillData['acquired_at'] ?? null
            );
        }
    }

    /**
     * Calculate progression trend
     */
    private function calculateProgressionTrend(array $trend): string
    {
        if (count($trend['overall_progression']) < 2) {
            return 'insufficient_data';
        }

        $progressionValues = $trend['overall_progression'];
        $recent = array_slice($progressionValues, -3); // Last 3 assessments
        $older = array_slice($progressionValues, 0, 3); // First 3 assessments

        if (empty($recent) || empty($older)) {
            return 'stable';
        }

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);
        $difference = $recentAvg - $olderAvg;

        if ($difference > 10) {
            return 'improving';
        } elseif ($difference < -10) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * Generate recommendations based on assessment
     */
    private function generateRecommendations(ProgressAssessment $assessment): array
    {
        $recommendations = [];
        $riskLevel = $assessment->getRiskLevel();
        $progressionStatus = $assessment->getProgressionStatus();

        // Risk-based recommendations
        if ($riskLevel >= 4) {
            $recommendations[] = [
                'type' => 'urgent_intervention',
                'priority' => 'high',
                'title' => 'Intervention urgente requise',
                'description' => 'Le niveau de risque critique nécessite une intervention immédiate.'
            ];
        } elseif ($riskLevel >= 3) {
            $recommendations[] = [
                'type' => 'increased_monitoring',
                'priority' => 'medium',
                'title' => 'Surveillance renforcée',
                'description' => 'Augmenter la fréquence des points de suivi.'
            ];
        }

        // Progression-based recommendations
        if ($progressionStatus === 'critical') {
            $recommendations[] = [
                'type' => 'intensive_support',
                'priority' => 'high',
                'title' => 'Soutien intensif nécessaire',
                'description' => 'Mise en place d\'un plan de rattrapage intensif.'
            ];
        } elseif ($progressionStatus === 'needs_improvement') {
            $recommendations[] = [
                'type' => 'additional_support',
                'priority' => 'medium',
                'title' => 'Accompagnement supplémentaire',
                'description' => 'Renforcer l\'accompagnement pédagogique et professionnel.'
            ];
        }

        // Specific recommendations based on objectives and skills
        $objectivesSummary = $assessment->getObjectivesSummary();
        if ($objectivesSummary['completion_rate'] < 50) {
            $recommendations[] = [
                'type' => 'objectives_review',
                'priority' => 'medium',
                'title' => 'Révision des objectifs',
                'description' => 'Revoir et adapter les objectifs pédagogiques.'
            ];
        }

        $skillsSummary = $assessment->getSkillsMatrixSummary();
        if ($skillsSummary['declining_skills'] > 0) {
            $recommendations[] = [
                'type' => 'skills_reinforcement',
                'priority' => 'medium',
                'title' => 'Renforcement des compétences',
                'description' => 'Focus sur les compétences en régression.'
            ];
        }

        return $recommendations;
    }

    /**
     * Update assessment from external data source
     */
    public function updateFromExternalData(ProgressAssessment $assessment, array $externalData): ProgressAssessment
    {
        // This method would be used to update assessments from external systems
        // Implementation depends on the specific external data format
        
        if (isset($externalData['progression'])) {
            $progression = $externalData['progression'];
            if (isset($progression['center'])) {
                $assessment->setCenterProgression(number_format($progression['center'], 2));
            }
            if (isset($progression['company'])) {
                $assessment->setCompanyProgression(number_format($progression['company'], 2));
            }
            $assessment->calculateOverallProgression();
        }

        if (isset($externalData['skills_matrix'])) {
            foreach ($externalData['skills_matrix'] as $skillCode => $skillData) {
                $assessment->updateSkillInMatrix(
                    $skillCode,
                    $skillData['name'],
                    $skillData['level'],
                    $skillData['last_assessed'] ?? null
                );
            }
        }

        $assessment->calculateRiskLevel();
        $this->entityManager->flush();

        return $assessment;
    }

    /**
     * Generate period report for multiple students
     */
    public function generatePeriodReport(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->progressAssessmentRepository->generateProgressionReport($startDate, $endDate);
    }

    /**
     * Export progress data for Qualiopi compliance
     */
    public function exportProgressData(Student $student, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $assessments = $this->progressAssessmentRepository->findByStudentAndDateRange($student, $startDate, $endDate);
        
        $exportData = [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName(),
                'email' => $student->getEmail()
            ],
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'progression_data' => [],
            'compliance_indicators' => []
        ];

        foreach ($assessments as $assessment) {
            $exportData['progression_data'][] = [
                'id' => $assessment->getId(),
                'period' => $assessment->getPeriod()->format('Y-m-d'),
                'center_progression' => (float) $assessment->getCenterProgression(),
                'company_progression' => (float) $assessment->getCompanyProgression(),
                'overall_progression' => (float) $assessment->getOverallProgression(),
                'risk_level' => $assessment->getRiskLevel(),
                'objectives_completion_rate' => $assessment->calculateObjectivesCompletionRate(),
                'skills_mastery_rate' => $assessment->getSkillsMatrixSummary()['mastered_skills'] ?? 0,
                'progression_status' => $assessment->getProgressionStatus()
            ];
        }

        // Add compliance indicators
        if (!empty($assessments)) {
            $latestAssessment = end($assessments);
            $exportData['compliance_indicators'] = [
                'regular_assessment' => count($assessments) >= 4, // Quarterly assessments
                'progression_tracking' => true,
                'risk_monitoring' => $latestAssessment->getRiskLevel() <= 3,
                'skills_development' => !empty($latestAssessment->getSkillsMatrix()),
                'objectives_management' => $latestAssessment->calculateObjectivesCompletionRate() > 0
            ];
        }

        return $exportData;
    }
}
