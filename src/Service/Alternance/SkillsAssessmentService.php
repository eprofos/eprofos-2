<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\SkillsAssessment;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Entity\User\Mentor;
use App\Entity\Alternance\MissionAssignment;
use App\Repository\Alternance\SkillsAssessmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * SkillsAssessmentService
 * 
 * Handles business logic for skills assessment management including
 * creation, evaluation, cross-evaluation, and progression tracking.
 */
class SkillsAssessmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SkillsAssessmentRepository $skillsAssessmentRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a new skills assessment
     */
    public function createSkillsAssessment(
        Student $student,
        string $assessmentType,
        string $context,
        \DateTimeInterface $assessmentDate,
        ?Teacher $centerEvaluator = null,
        ?Mentor $mentorEvaluator = null
    ): SkillsAssessment {
        $assessment = new SkillsAssessment();
        $assessment->setStudent($student)
            ->setAssessmentType($assessmentType)
            ->setContext($context)
            ->setAssessmentDate($assessmentDate)
            ->setCenterEvaluator($centerEvaluator)
            ->setMentorEvaluator($mentorEvaluator);

        $this->entityManager->persist($assessment);
        $this->entityManager->flush();

        $this->logger->info('Skills assessment created', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $student->getId(),
            'type' => $assessmentType,
            'context' => $context
        ]);

        return $assessment;
    }

    /**
     * Add skill evaluation to assessment
     */
    public function addSkillEvaluation(
        SkillsAssessment $assessment,
        string $skillCode,
        string $skillName,
        ?float $centerScore = null,
        ?float $companyScore = null,
        ?string $comments = null
    ): SkillsAssessment {
        $assessment->addSkillEvaluation($skillCode, $skillName, $centerScore, $companyScore);

        if ($comments && $centerScore !== null) {
            $assessment->setCenterComments($assessment->getCenterComments() . "\n" . $skillCode . ": " . $comments);
        }

        if ($comments && $companyScore !== null) {
            $assessment->setMentorComments($assessment->getMentorComments() . "\n" . $skillCode . ": " . $comments);
        }

        $this->entityManager->flush();

        return $assessment;
    }

    /**
     * Complete assessment with overall rating
     */
    public function completeAssessment(
        SkillsAssessment $assessment,
        string $overallRating,
        ?string $centerComments = null,
        ?string $mentorComments = null
    ): SkillsAssessment {
        $assessment->setOverallRating($overallRating);

        if ($centerComments) {
            $assessment->setCenterComments($centerComments);
        }

        if ($mentorComments) {
            $assessment->setMentorComments($mentorComments);
        }

        // Generate development plan based on assessment results
        $this->generateDevelopmentPlan($assessment);

        $this->entityManager->flush();

        $this->logger->info('Skills assessment completed', [
            'assessment_id' => $assessment->getId(),
            'overall_rating' => $overallRating,
            'is_complete' => $assessment->isComplete()
        ]);

        return $assessment;
    }

    /**
     * Generate development plan based on assessment results
     */
    public function generateDevelopmentPlan(SkillsAssessment $assessment): void
    {
        $competencyGaps = $assessment->getCompetencyGaps();
        $centerScores = $assessment->getCenterScores();
        $companyScores = $assessment->getCompanyScores();

        foreach ($competencyGaps as $skillCode => $gap) {
            $skillName = $assessment->getSkillsEvaluated()[$skillCode]['name'] ?? $skillCode;
            
            // Generate development action based on gap
            if ($gap['gap'] > 5) {
                $objective = "Harmoniser l'évaluation de la compétence '{$skillName}'";
                $actions = "Revoir les critères d'évaluation et organiser une session de calibrage entre centre et entreprise";
            } else {
                $lowerScore = min($gap['center_score'], $gap['company_score']);
                $objective = "Améliorer la compétence '{$skillName}' (niveau actuel: {$lowerScore}/20)";
                $actions = "Formation complémentaire et mise en pratique encadrée";
            }

            $assessment->addDevelopmentPlanItem(
                $skillCode,
                $objective,
                $actions,
                (new \DateTime('+3 months'))->format('Y-m-d')
            );
        }

        // Add development items for low-scoring skills
        foreach ($centerScores as $skillCode => $scoreData) {
            $score = $scoreData['value'] ?? 0;
            if ($score < 10) { // Below 50%
                $skillName = $assessment->getSkillsEvaluated()[$skillCode]['name'] ?? $skillCode;
                $assessment->addDevelopmentPlanItem(
                    $skillCode,
                    "Renforcer la compétence '{$skillName}'",
                    "Formation intensive et accompagnement personnalisé",
                    (new \DateTime('+2 months'))->format('Y-m-d')
                );
            }
        }
    }

    /**
     * Create cross-evaluation assessment
     */
    public function createCrossEvaluation(
        Student $student,
        Teacher $centerEvaluator,
        Mentor $mentorEvaluator,
        \DateTimeInterface $assessmentDate,
        ?MissionAssignment $relatedMission = null
    ): SkillsAssessment {
        $assessment = $this->createSkillsAssessment(
            $student,
            'sommative',
            'mixte',
            $assessmentDate,
            $centerEvaluator,
            $mentorEvaluator
        );

        if ($relatedMission) {
            $assessment->setRelatedMission($relatedMission);
            $this->entityManager->flush();
        }

        return $assessment;
    }

    /**
     * Get skills progression for a student
     */
    public function getSkillsProgression(Student $student): array
    {
        return $this->skillsAssessmentRepository->getSkillsProgressionData($student);
    }

    /**
     * Get competency matrix for a student
     */
    public function getCompetencyMatrix(Student $student): array
    {
        $assessments = $this->skillsAssessmentRepository->findByStudentAndPeriod(
            $student,
            new \DateTime('-1 year'),
            new \DateTime()
        );

        $matrix = [];

        foreach (SkillsAssessment::STANDARD_SKILLS as $categoryCode => $category) {
            $matrix[$categoryCode] = [
                'name' => $category['name'],
                'skills' => []
            ];

            foreach ($category['subcategories'] as $skillCode => $skillName) {
                $matrix[$categoryCode]['skills'][$skillCode] = [
                    'name' => $skillName,
                    'center_score' => null,
                    'company_score' => null,
                    'last_assessed' => null,
                    'trend' => 'not_assessed'
                ];
            }
        }

        // Fill matrix with actual assessment data
        foreach ($assessments as $assessment) {
            foreach ($assessment->getSkillsEvaluated() as $skillCode => $skillInfo) {
                $categoryCode = $this->getSkillCategory($skillCode);
                
                if (isset($matrix[$categoryCode]['skills'][$skillCode])) {
                    $centerScore = $assessment->getCenterScores()[$skillCode]['value'] ?? null;
                    $companyScore = $assessment->getCompanyScores()[$skillCode]['value'] ?? null;
                    
                    $matrix[$categoryCode]['skills'][$skillCode]['center_score'] = $centerScore;
                    $matrix[$categoryCode]['skills'][$skillCode]['company_score'] = $companyScore;
                    $matrix[$categoryCode]['skills'][$skillCode]['last_assessed'] = $assessment->getAssessmentDate();
                    
                    // Calculate trend (simplified)
                    if ($centerScore !== null && $companyScore !== null) {
                        $avgScore = ($centerScore + $companyScore) / 2;
                        if ($avgScore >= 16) {
                            $matrix[$categoryCode]['skills'][$skillCode]['trend'] = 'mastered';
                        } elseif ($avgScore >= 12) {
                            $matrix[$categoryCode]['skills'][$skillCode]['trend'] = 'developing';
                        } else {
                            $matrix[$categoryCode]['skills'][$skillCode]['trend'] = 'needs_work';
                        }
                    }
                }
            }
        }

        return $matrix;
    }

    /**
     * Generate assessment recommendations for a student
     */
    public function generateAssessmentRecommendations(Student $student): array
    {
        $recommendations = [];
        $lastAssessment = $this->skillsAssessmentRepository->findLatestByStudent($student);

        if (!$lastAssessment) {
            $recommendations[] = [
                'type' => 'initial_assessment',
                'priority' => 'high',
                'title' => 'Évaluation initiale requise',
                'description' => 'Aucune évaluation de compétences n\'a été réalisée pour cet alternant.'
            ];
            return $recommendations;
        }

        $daysSinceLastAssessment = (new \DateTime())->diff($lastAssessment->getAssessmentDate())->days;

        // Recommend regular assessment
        if ($daysSinceLastAssessment > 90) {
            $recommendations[] = [
                'type' => 'regular_assessment',
                'priority' => 'medium',
                'title' => 'Évaluation périodique recommandée',
                'description' => "Dernière évaluation datant de {$daysSinceLastAssessment} jours."
            ];
        }

        // Recommend cross-evaluation if missing
        if ($lastAssessment->getContext() !== 'mixte' && $daysSinceLastAssessment > 60) {
            $recommendations[] = [
                'type' => 'cross_evaluation',
                'priority' => 'high',
                'title' => 'Évaluation croisée recommandée',
                'description' => 'Une évaluation croisée centre-entreprise devrait être réalisée.'
            ];
        }

        // Recommend development plan follow-up
        $developmentPlan = $lastAssessment->getDevelopmentPlan();
        if (!empty($developmentPlan)) {
            $recommendations[] = [
                'type' => 'development_followup',
                'priority' => 'medium',
                'title' => 'Suivi du plan de développement',
                'description' => 'Vérifier l\'avancement du plan de développement établi.'
            ];
        }

        return $recommendations;
    }

    /**
     * Get assessment statistics for a period
     */
    public function getAssessmentStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->skillsAssessmentRepository->getAssessmentStatistics($startDate, $endDate);
    }

    /**
     * Detect students requiring assessment
     */
    public function detectStudentsRequiringAssessment(): array
    {
        $cutoffDate = new \DateTime('-3 months');
        return $this->skillsAssessmentRepository->findStudentsRequiringAssessment($cutoffDate);
    }

    /**
     * Get skill category from skill code
     */
    private function getSkillCategory(string $skillCode): string
    {
        // Map skill codes to categories
        $categoryMap = [
            'programming' => 'technical',
            'database' => 'technical',
            'networks' => 'technical',
            'security' => 'technical',
            'tools' => 'technical',
            'communication' => 'transversal',
            'teamwork' => 'transversal',
            'autonomy' => 'transversal',
            'problem_solving' => 'transversal',
            'time_management' => 'transversal',
            'project_management' => 'professional',
            'client_relation' => 'professional',
            'quality' => 'professional',
            'innovation' => 'professional',
            'leadership' => 'professional'
        ];

        return $categoryMap[$skillCode] ?? 'technical';
    }

    /**
     * Import assessment data from external source
     */
    public function importAssessmentData(array $assessmentData): SkillsAssessment
    {
        // Validate and create assessment from imported data
        $assessment = new SkillsAssessment();
        
        // Implementation would depend on the specific import format
        // This is a placeholder for future implementation
        
        return $assessment;
    }

    /**
     * Export assessment data for Qualiopi compliance
     */
    public function exportAssessmentData(Student $student, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $assessments = $this->skillsAssessmentRepository->findByStudentAndPeriod($student, $startDate, $endDate);
        
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
            'assessments' => []
        ];

        foreach ($assessments as $assessment) {
            $exportData['assessments'][] = [
                'id' => $assessment->getId(),
                'type' => $assessment->getAssessmentType(),
                'context' => $assessment->getContext(),
                'date' => $assessment->getAssessmentDate()->format('Y-m-d'),
                'overall_rating' => $assessment->getOverallRating(),
                'skills_evaluated' => $assessment->getSkillsEvaluated(),
                'center_scores' => $assessment->getCenterScores(),
                'company_scores' => $assessment->getCompanyScores(),
                'development_plan' => $assessment->getDevelopmentPlan(),
                'is_complete' => $assessment->isComplete(),
                'has_cross_evaluation' => $assessment->hasCrossEvaluation()
            ];
        }

        return $exportData;
    }
}
