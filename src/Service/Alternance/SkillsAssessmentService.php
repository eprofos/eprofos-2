<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\MissionAssignment;
use App\Entity\Alternance\SkillsAssessment;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Repository\Alternance\SkillsAssessmentRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * SkillsAssessmentService.
 *
 * Handles business logic for skills assessment management including
 * creation, evaluation, cross-evaluation, and progression tracking.
 */
class SkillsAssessmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SkillsAssessmentRepository $skillsAssessmentRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Create a new skills assessment.
     */
    public function createSkillsAssessment(
        Student $student,
        string $assessmentType,
        string $context,
        DateTimeInterface $assessmentDate,
        ?Teacher $centerEvaluator = null,
        ?Mentor $mentorEvaluator = null,
    ): SkillsAssessment {
        $this->logger->info('Starting skills assessment creation', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'assessment_type' => $assessmentType,
            'context' => $context,
            'assessment_date' => $assessmentDate->format('Y-m-d H:i:s'),
            'center_evaluator_id' => $centerEvaluator?->getId(),
            'mentor_evaluator_id' => $mentorEvaluator?->getId(),
        ]);

        try {
            $assessment = new SkillsAssessment();
            $assessment->setStudent($student)
                ->setAssessmentType($assessmentType)
                ->setContext($context)
                ->setAssessmentDate($assessmentDate)
                ->setCenterEvaluator($centerEvaluator)
                ->setMentorEvaluator($mentorEvaluator)
            ;

            $this->logger->debug('Skills assessment entity created successfully', [
                'assessment_type' => $assessmentType,
                'context' => $context,
                'has_center_evaluator' => $centerEvaluator !== null,
                'has_mentor_evaluator' => $mentorEvaluator !== null,
            ]);

            $this->entityManager->persist($assessment);
            $this->entityManager->flush();

            $this->logger->info('Skills assessment created and persisted successfully', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $student->getId(),
                'student_name' => $student->getFullName(),
                'type' => $assessmentType,
                'context' => $context,
                'assessment_date' => $assessmentDate->format('Y-m-d'),
                'center_evaluator' => $centerEvaluator?->getFullName(),
                'mentor_evaluator' => $mentorEvaluator?->getFullName(),
            ]);

            return $assessment;
        } catch (Exception $e) {
            $this->logger->error('Failed to create skills assessment', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'assessment_type' => $assessmentType,
                'context' => $context,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to create skills assessment for student {$student->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Add skill evaluation to assessment.
     */
    public function addSkillEvaluation(
        SkillsAssessment $assessment,
        string $skillCode,
        string $skillName,
        ?float $centerScore = null,
        ?float $companyScore = null,
        ?string $comments = null,
    ): SkillsAssessment {
        $this->logger->info('Adding skill evaluation to assessment', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()->getId(),
            'skill_code' => $skillCode,
            'skill_name' => $skillName,
            'center_score' => $centerScore,
            'company_score' => $companyScore,
            'has_comments' => $comments !== null,
            'comments_length' => $comments ? strlen($comments) : 0,
        ]);

        try {
            // Validate scores
            if ($centerScore !== null && ($centerScore < 0 || $centerScore > 20)) {
                throw new InvalidArgumentException("Center score must be between 0 and 20, got: {$centerScore}");
            }

            if ($companyScore !== null && ($companyScore < 0 || $companyScore > 20)) {
                throw new InvalidArgumentException("Company score must be between 0 and 20, got: {$companyScore}");
            }

            $this->logger->debug('Skill evaluation validation passed', [
                'skill_code' => $skillCode,
                'center_score_valid' => $centerScore === null || ($centerScore >= 0 && $centerScore <= 20),
                'company_score_valid' => $companyScore === null || ($companyScore >= 0 && $companyScore <= 20),
            ]);

            $assessment->addSkillEvaluation($skillCode, $skillName, $centerScore, $companyScore);

            if ($comments && $centerScore !== null) {
                $currentComments = $assessment->getCenterComments() ?? '';
                $newComments = $currentComments . "\n" . $skillCode . ': ' . $comments;
                $assessment->setCenterComments($newComments);

                $this->logger->debug('Added center comments for skill', [
                    'skill_code' => $skillCode,
                    'comments_added' => true,
                    'total_comments_length' => strlen($newComments),
                ]);
            }

            if ($comments && $companyScore !== null) {
                $currentComments = $assessment->getMentorComments() ?? '';
                $newComments = $currentComments . "\n" . $skillCode . ': ' . $comments;
                $assessment->setMentorComments($newComments);

                $this->logger->debug('Added mentor comments for skill', [
                    'skill_code' => $skillCode,
                    'comments_added' => true,
                    'total_comments_length' => strlen($newComments),
                ]);
            }

            $this->entityManager->flush();

            $this->logger->info('Skill evaluation added successfully', [
                'assessment_id' => $assessment->getId(),
                'skill_code' => $skillCode,
                'skill_name' => $skillName,
                'center_score' => $centerScore,
                'company_score' => $companyScore,
                'evaluation_complete' => $centerScore !== null && $companyScore !== null,
                'total_skills_evaluated' => count($assessment->getSkillsEvaluated()),
            ]);

            return $assessment;
        } catch (Exception $e) {
            $this->logger->error('Failed to add skill evaluation', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'skill_code' => $skillCode,
                'skill_name' => $skillName,
                'center_score' => $centerScore,
                'company_score' => $companyScore,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to add skill evaluation '{$skillCode}' to assessment {$assessment->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Complete assessment with overall rating.
     */
    public function completeAssessment(
        SkillsAssessment $assessment,
        string $overallRating,
        ?string $centerComments = null,
        ?string $mentorComments = null,
    ): SkillsAssessment {
        $this->logger->info('Starting assessment completion', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()->getId(),
            'student_name' => $assessment->getStudent()->getFullName(),
            'overall_rating' => $overallRating,
            'has_center_comments' => $centerComments !== null,
            'has_mentor_comments' => $mentorComments !== null,
            'skills_evaluated_count' => count($assessment->getSkillsEvaluated()),
            'assessment_type' => $assessment->getAssessmentType(),
            'context' => $assessment->getContext(),
        ]);

        try {
            // Validate overall rating
            $validRatings = ['excellent', 'good', 'satisfactory', 'needs_improvement', 'insufficient'];
            if (!in_array($overallRating, $validRatings, true)) {
                throw new InvalidArgumentException("Invalid overall rating: {$overallRating}. Must be one of: " . implode(', ', $validRatings));
            }

            $this->logger->debug('Overall rating validation passed', [
                'overall_rating' => $overallRating,
                'valid_ratings' => $validRatings,
            ]);

            $assessment->setOverallRating($overallRating);

            if ($centerComments) {
                $assessment->setCenterComments($centerComments);
                $this->logger->debug('Center comments set', [
                    'comments_length' => strlen($centerComments),
                    'assessment_id' => $assessment->getId(),
                ]);
            }

            if ($mentorComments) {
                $assessment->setMentorComments($mentorComments);
                $this->logger->debug('Mentor comments set', [
                    'comments_length' => strlen($mentorComments),
                    'assessment_id' => $assessment->getId(),
                ]);
            }

            $this->logger->info('Generating development plan for assessment', [
                'assessment_id' => $assessment->getId(),
                'overall_rating' => $overallRating,
            ]);

            // Generate development plan based on assessment results
            $this->generateDevelopmentPlan($assessment);

            $this->entityManager->flush();

            $completionStatus = [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'overall_rating' => $overallRating,
                'is_complete' => $assessment->isComplete(),
                'has_cross_evaluation' => $assessment->hasCrossEvaluation(),
                'skills_count' => count($assessment->getSkillsEvaluated()),
                'development_plan_items' => count($assessment->getDevelopmentPlan()),
                'competency_gaps_count' => count($assessment->getCompetencyGaps()),
            ];

            $this->logger->info('Skills assessment completed successfully', $completionStatus);

            return $assessment;
        } catch (Exception $e) {
            $this->logger->error('Failed to complete skills assessment', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'overall_rating' => $overallRating,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to complete skills assessment {$assessment->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Generate development plan based on assessment results.
     */
    public function generateDevelopmentPlan(SkillsAssessment $assessment): void
    {
        $this->logger->info('Starting development plan generation', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()->getId(),
            'student_name' => $assessment->getStudent()->getFullName(),
            'skills_evaluated' => count($assessment->getSkillsEvaluated()),
        ]);

        try {
            $competencyGaps = $assessment->getCompetencyGaps();
            $centerScores = $assessment->getCenterScores();
            $companyScores = $assessment->getCompanyScores();
            $planItemsAdded = 0;

            $this->logger->debug('Retrieved assessment data for plan generation', [
                'competency_gaps_count' => count($competencyGaps),
                'center_scores_count' => count($centerScores),
                'company_scores_count' => count($companyScores),
            ]);

            foreach ($competencyGaps as $skillCode => $gap) {
                $skillName = $assessment->getSkillsEvaluated()[$skillCode]['name'] ?? $skillCode;

                $this->logger->debug('Processing competency gap', [
                    'skill_code' => $skillCode,
                    'skill_name' => $skillName,
                    'gap_value' => $gap['gap'],
                    'center_score' => $gap['center_score'],
                    'company_score' => $gap['company_score'],
                ]);

                // Generate development action based on gap
                if ($gap['gap'] > 5) {
                    $objective = "Harmoniser l'évaluation de la compétence '{$skillName}'";
                    $actions = "Revoir les critères d'évaluation et organiser une session de calibrage entre centre et entreprise";

                    $this->logger->debug('High gap detected - harmonization needed', [
                        'skill_code' => $skillCode,
                        'gap_value' => $gap['gap'],
                        'objective' => $objective,
                    ]);
                } else {
                    $lowerScore = min($gap['center_score'], $gap['company_score']);
                    $objective = "Améliorer la compétence '{$skillName}' (niveau actuel: {$lowerScore}/20)";
                    $actions = 'Formation complémentaire et mise en pratique encadrée';

                    $this->logger->debug('Low score detected - improvement needed', [
                        'skill_code' => $skillCode,
                        'lower_score' => $lowerScore,
                        'objective' => $objective,
                    ]);
                }

                $assessment->addDevelopmentPlanItem(
                    $skillCode,
                    $objective,
                    $actions,
                    (new DateTime('+3 months'))->format('Y-m-d'),
                );

                $planItemsAdded++;
            }

            // Add development items for low-scoring skills
            foreach ($centerScores as $skillCode => $scoreData) {
                $score = $scoreData['value'] ?? 0;
                if ($score < 10) { // Below 50%
                    $skillName = $assessment->getSkillsEvaluated()[$skillCode]['name'] ?? $skillCode;

                    $this->logger->debug('Low center score detected', [
                        'skill_code' => $skillCode,
                        'skill_name' => $skillName,
                        'center_score' => $score,
                        'threshold' => 10,
                    ]);

                    $assessment->addDevelopmentPlanItem(
                        $skillCode,
                        "Renforcer la compétence '{$skillName}'",
                        'Formation intensive et accompagnement personnalisé',
                        (new DateTime('+2 months'))->format('Y-m-d'),
                    );

                    $planItemsAdded++;
                }
            }

            $this->logger->info('Development plan generated successfully', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'plan_items_added' => $planItemsAdded,
                'total_plan_items' => count($assessment->getDevelopmentPlan()),
                'competency_gaps_processed' => count($competencyGaps),
                'low_scores_processed' => count(array_filter($centerScores, static fn ($scoreData) => ($scoreData['value'] ?? 0) < 10)),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to generate development plan', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to generate development plan for assessment {$assessment->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Create cross-evaluation assessment.
     */
    public function createCrossEvaluation(
        Student $student,
        Teacher $centerEvaluator,
        Mentor $mentorEvaluator,
        DateTimeInterface $assessmentDate,
        ?MissionAssignment $relatedMission = null,
    ): SkillsAssessment {
        $this->logger->info('Creating cross-evaluation assessment', [
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'center_evaluator_id' => $centerEvaluator->getId(),
            'center_evaluator_name' => $centerEvaluator->getFullName(),
            'mentor_evaluator_id' => $mentorEvaluator->getId(),
            'mentor_evaluator_name' => $mentorEvaluator->getFullName(),
            'assessment_date' => $assessmentDate->format('Y-m-d H:i:s'),
            'has_related_mission' => $relatedMission !== null,
            'related_mission_id' => $relatedMission?->getId(),
        ]);

        try {
            $assessment = $this->createSkillsAssessment(
                $student,
                'sommative',
                'mixte',
                $assessmentDate,
                $centerEvaluator,
                $mentorEvaluator,
            );

            $this->logger->debug('Base assessment created for cross-evaluation', [
                'assessment_id' => $assessment->getId(),
                'assessment_type' => $assessment->getAssessmentType(),
                'context' => $assessment->getContext(),
            ]);

            if ($relatedMission) {
                $assessment->setRelatedMission($relatedMission);
                $this->entityManager->flush();

                $this->logger->debug('Related mission assigned to assessment', [
                    'assessment_id' => $assessment->getId(),
                    'mission_id' => $relatedMission->getId(),
                    'mission_title' => $relatedMission->getMission()?->getTitle() ?? 'No title',
                ]);
            }

            $this->logger->info('Cross-evaluation assessment created successfully', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $student->getId(),
                'center_evaluator_id' => $centerEvaluator->getId(),
                'mentor_evaluator_id' => $mentorEvaluator->getId(),
                'has_related_mission' => $relatedMission !== null,
                'assessment_type' => 'sommative',
                'context' => 'mixte',
            ]);

            return $assessment;
        } catch (Exception $e) {
            $this->logger->error('Failed to create cross-evaluation assessment', [
                'student_id' => $student->getId(),
                'center_evaluator_id' => $centerEvaluator->getId(),
                'mentor_evaluator_id' => $mentorEvaluator->getId(),
                'assessment_date' => $assessmentDate->format('Y-m-d H:i:s'),
                'related_mission_id' => $relatedMission?->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to create cross-evaluation assessment for student {$student->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Get skills progression for a student.
     */
    public function getSkillsProgression(Student $student): array
    {
        $this->logger->info('Retrieving skills progression for student', [
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'student_email' => $student->getEmail(),
        ]);

        try {
            $progressionData = $this->skillsAssessmentRepository->getSkillsProgressionData($student);

            $this->logger->info('Skills progression data retrieved successfully', [
                'student_id' => $student->getId(),
                'progression_entries' => count($progressionData),
                'skills_tracked' => count(array_unique(array_column($progressionData, 'skill_code'))),
                'assessment_periods' => count(array_unique(array_column($progressionData, 'assessment_date'))),
            ]);

            return $progressionData;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve skills progression', [
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to retrieve skills progression for student {$student->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Get competency matrix for a student.
     */
    public function getCompetencyMatrix(Student $student): array
    {
        $this->logger->info('Generating competency matrix for student', [
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'student_email' => $student->getEmail(),
        ]);

        try {
            $assessments = $this->skillsAssessmentRepository->findByStudentAndPeriod(
                $student,
                new DateTime('-1 year'),
                new DateTime(),
            );

            $this->logger->debug('Retrieved assessments for competency matrix', [
                'student_id' => $student->getId(),
                'assessments_count' => count($assessments),
                'period_start' => (new DateTime('-1 year'))->format('Y-m-d'),
                'period_end' => (new DateTime())->format('Y-m-d'),
            ]);

            $matrix = [];

            foreach (SkillsAssessment::STANDARD_SKILLS as $categoryCode => $category) {
                $matrix[$categoryCode] = [
                    'name' => $category['name'],
                    'skills' => [],
                ];

                foreach ($category['subcategories'] as $skillCode => $skillName) {
                    $matrix[$categoryCode]['skills'][$skillCode] = [
                        'name' => $skillName,
                        'center_score' => null,
                        'company_score' => null,
                        'last_assessed' => null,
                        'trend' => 'not_assessed',
                    ];
                }
            }

            $this->logger->debug('Initialized competency matrix structure', [
                'categories_count' => count($matrix),
                'total_skills' => array_sum(array_map(static fn ($cat) => count($cat['skills']), $matrix)),
            ]);

            $assessedSkillsCount = 0;
            $masteredSkillsCount = 0;
            $needsWorkSkillsCount = 0;

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
                                $masteredSkillsCount++;
                            } elseif ($avgScore >= 12) {
                                $matrix[$categoryCode]['skills'][$skillCode]['trend'] = 'developing';
                            } else {
                                $matrix[$categoryCode]['skills'][$skillCode]['trend'] = 'needs_work';
                                $needsWorkSkillsCount++;
                            }
                            $assessedSkillsCount++;
                        }
                    }
                }
            }

            $this->logger->info('Competency matrix generated successfully', [
                'student_id' => $student->getId(),
                'categories_count' => count($matrix),
                'assessed_skills_count' => $assessedSkillsCount,
                'mastered_skills_count' => $masteredSkillsCount,
                'needs_work_skills_count' => $needsWorkSkillsCount,
                'assessments_processed' => count($assessments),
            ]);

            return $matrix;
        } catch (Exception $e) {
            $this->logger->error('Failed to generate competency matrix', [
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to generate competency matrix for student {$student->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Generate assessment recommendations for a student.
     */
    public function generateAssessmentRecommendations(Student $student): array
    {
        $this->logger->info('Generating assessment recommendations for student', [
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'student_email' => $student->getEmail(),
        ]);

        try {
            $recommendations = [];
            $lastAssessment = $this->skillsAssessmentRepository->findLatestByStudent($student);

            $this->logger->debug('Retrieved latest assessment for recommendations', [
                'student_id' => $student->getId(),
                'has_assessment' => $lastAssessment !== null,
                'last_assessment_id' => $lastAssessment?->getId(),
                'last_assessment_date' => $lastAssessment?->getAssessmentDate()->format('Y-m-d'),
            ]);

            if (!$lastAssessment) {
                $recommendations[] = [
                    'type' => 'initial_assessment',
                    'priority' => 'high',
                    'title' => 'Évaluation initiale requise',
                    'description' => 'Aucune évaluation de compétences n\'a été réalisée pour cet alternant.',
                ];

                $this->logger->info('Generated initial assessment recommendation', [
                    'student_id' => $student->getId(),
                    'recommendations_count' => count($recommendations),
                    'reason' => 'no_previous_assessment',
                ]);

                return $recommendations;
            }

            $daysSinceLastAssessment = (new DateTime())->diff($lastAssessment->getAssessmentDate())->days;

            $this->logger->debug('Calculated days since last assessment', [
                'student_id' => $student->getId(),
                'days_since_last_assessment' => $daysSinceLastAssessment,
                'last_assessment_date' => $lastAssessment->getAssessmentDate()->format('Y-m-d'),
                'current_date' => (new DateTime())->format('Y-m-d'),
            ]);

            // Recommend regular assessment
            if ($daysSinceLastAssessment > 90) {
                $recommendations[] = [
                    'type' => 'regular_assessment',
                    'priority' => 'medium',
                    'title' => 'Évaluation périodique recommandée',
                    'description' => "Dernière évaluation datant de {$daysSinceLastAssessment} jours.",
                ];

                $this->logger->debug('Added regular assessment recommendation', [
                    'student_id' => $student->getId(),
                    'days_since_last' => $daysSinceLastAssessment,
                    'threshold' => 90,
                ]);
            }

            // Recommend cross-evaluation if missing
            if ($lastAssessment->getContext() !== 'mixte' && $daysSinceLastAssessment > 60) {
                $recommendations[] = [
                    'type' => 'cross_evaluation',
                    'priority' => 'high',
                    'title' => 'Évaluation croisée recommandée',
                    'description' => 'Une évaluation croisée centre-entreprise devrait être réalisée.',
                ];

                $this->logger->debug('Added cross-evaluation recommendation', [
                    'student_id' => $student->getId(),
                    'last_context' => $lastAssessment->getContext(),
                    'days_since_last' => $daysSinceLastAssessment,
                    'threshold' => 60,
                ]);
            }

            // Recommend development plan follow-up
            $developmentPlan = $lastAssessment->getDevelopmentPlan();
            if (!empty($developmentPlan)) {
                $recommendations[] = [
                    'type' => 'development_followup',
                    'priority' => 'medium',
                    'title' => 'Suivi du plan de développement',
                    'description' => 'Vérifier l\'avancement du plan de développement établi.',
                ];

                $this->logger->debug('Added development plan follow-up recommendation', [
                    'student_id' => $student->getId(),
                    'development_plan_items' => count($developmentPlan),
                ]);
            }

            $this->logger->info('Assessment recommendations generated successfully', [
                'student_id' => $student->getId(),
                'recommendations_count' => count($recommendations),
                'has_previous_assessment' => true,
                'days_since_last_assessment' => $daysSinceLastAssessment,
                'last_assessment_context' => $lastAssessment->getContext(),
                'development_plan_items' => count($developmentPlan),
            ]);

            return $recommendations;
        } catch (Exception $e) {
            $this->logger->error('Failed to generate assessment recommendations', [
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to generate assessment recommendations for student {$student->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Get assessment statistics for a period.
     */
    public function getAssessmentStatistics(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $this->logger->info('Retrieving assessment statistics for period', [
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
            'period_days' => $startDate->diff($endDate)->days,
        ]);

        try {
            $statistics = $this->skillsAssessmentRepository->getAssessmentStatistics($startDate, $endDate);

            $this->logger->info('Assessment statistics retrieved successfully', [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_assessments' => $statistics['total_assessments'] ?? 0,
                'unique_students' => $statistics['unique_students'] ?? 0,
                'cross_evaluations' => $statistics['cross_evaluations'] ?? 0,
                'completed_assessments' => $statistics['completed_assessments'] ?? 0,
                'average_rating' => $statistics['average_rating'] ?? 0,
            ]);

            return $statistics;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve assessment statistics', [
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s'),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to retrieve assessment statistics for period {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Detect students requiring assessment.
     */
    public function detectStudentsRequiringAssessment(): array
    {
        $cutoffDate = new DateTime('-3 months');

        $this->logger->info('Detecting students requiring assessment', [
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            'cutoff_months' => 3,
        ]);

        try {
            $studentsRequiringAssessment = $this->skillsAssessmentRepository->findStudentsRequiringAssessment($cutoffDate);

            $this->logger->info('Students requiring assessment detected', [
                'cutoff_date' => $cutoffDate->format('Y-m-d'),
                'students_requiring_assessment' => count($studentsRequiringAssessment),
                'students_details' => array_map(static fn ($student) => [
                    'id' => $student['id'],
                    'name' => $student['name'],
                    'last_assessment' => $student['last_assessment_date'] ?? 'never',
                ], $studentsRequiringAssessment),
            ]);

            return $studentsRequiringAssessment;
        } catch (Exception $e) {
            $this->logger->error('Failed to detect students requiring assessment', [
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to detect students requiring assessment: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Import assessment data from external source.
     */
    public function importAssessmentData(array $assessmentData): SkillsAssessment
    {
        $this->logger->info('Starting assessment data import from external source', [
            'data_size' => count($assessmentData),
            'data_keys' => array_keys($assessmentData),
            'has_student_data' => isset($assessmentData['student']),
            'has_assessment_data' => isset($assessmentData['assessment']),
            'has_skills_data' => isset($assessmentData['skills']),
        ]);

        try {
            // Validate imported data structure
            $requiredFields = ['student_id', 'assessment_type', 'assessment_date', 'skills'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (!isset($assessmentData[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                throw new InvalidArgumentException('Missing required fields in import data: ' . implode(', ', $missingFields));
            }

            $this->logger->debug('Import data validation passed', [
                'required_fields' => $requiredFields,
                'data_structure_valid' => true,
                'student_id' => $assessmentData['student_id'],
                'assessment_type' => $assessmentData['assessment_type'],
                'skills_count' => count($assessmentData['skills'] ?? []),
            ]);

            // Placeholder for future implementation
            // This would involve:
            // 1. Validating student exists
            // 2. Creating assessment entity
            // 3. Processing skills data
            // 4. Saving to database

            $this->logger->warning('Assessment import feature not yet implemented', [
                'import_data_received' => true,
                'data_size' => count($assessmentData),
                'implementation_status' => 'placeholder',
                'next_steps' => 'Implement full import logic based on specific format requirements',
            ]);

            // Return placeholder assessment for now
            return new SkillsAssessment();
        } catch (Exception $e) {
            $this->logger->error('Failed to import assessment data', [
                'data_size' => count($assessmentData),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to import assessment data: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Export assessment data for Qualiopi compliance.
     */
    public function exportAssessmentData(Student $student, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $this->logger->info('Starting assessment data export for Qualiopi compliance', [
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'student_email' => $student->getEmail(),
            'export_start_date' => $startDate->format('Y-m-d H:i:s'),
            'export_end_date' => $endDate->format('Y-m-d H:i:s'),
            'export_period_days' => $startDate->diff($endDate)->days,
        ]);

        try {
            $assessments = $this->skillsAssessmentRepository->findByStudentAndPeriod($student, $startDate, $endDate);

            $this->logger->debug('Retrieved assessments for export', [
                'student_id' => $student->getId(),
                'assessments_count' => count($assessments),
                'period_start' => $startDate->format('Y-m-d'),
                'period_end' => $endDate->format('Y-m-d'),
            ]);

            $exportData = [
                'student' => [
                    'id' => $student->getId(),
                    'name' => $student->getFullName(),
                    'email' => $student->getEmail(),
                ],
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'assessments' => [],
            ];

            $totalSkillsEvaluated = 0;
            $completedAssessments = 0;
            $crossEvaluations = 0;

            foreach ($assessments as $assessment) {
                $assessmentData = [
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
                    'has_cross_evaluation' => $assessment->hasCrossEvaluation(),
                ];

                $exportData['assessments'][] = $assessmentData;

                // Update statistics
                $totalSkillsEvaluated += count($assessment->getSkillsEvaluated());
                if ($assessment->isComplete()) {
                    $completedAssessments++;
                }
                if ($assessment->hasCrossEvaluation()) {
                    $crossEvaluations++;
                }
            }

            $this->logger->info('Assessment data exported successfully for Qualiopi compliance', [
                'student_id' => $student->getId(),
                'export_period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
                'total_assessments' => count($assessments),
                'completed_assessments' => $completedAssessments,
                'cross_evaluations' => $crossEvaluations,
                'total_skills_evaluated' => $totalSkillsEvaluated,
                'export_data_size' => strlen(json_encode($exportData)),
                'compliance_indicators' => [
                    'has_assessments' => count($assessments) > 0,
                    'has_completed_assessments' => $completedAssessments > 0,
                    'has_cross_evaluations' => $crossEvaluations > 0,
                    'skills_coverage' => $totalSkillsEvaluated > 0,
                ],
            ]);

            return $exportData;
        } catch (Exception $e) {
            $this->logger->error('Failed to export assessment data for Qualiopi compliance', [
                'student_id' => $student->getId(),
                'export_start_date' => $startDate->format('Y-m-d H:i:s'),
                'export_end_date' => $endDate->format('Y-m-d H:i:s'),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to export assessment data for student {$student->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Analyze skills assessment and provide detailed insights.
     */
    public function analyzeSkillsAssessment(SkillsAssessment $assessment): array
    {
        $this->logger->info('Starting comprehensive skills assessment analysis', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()->getId(),
            'student_name' => $assessment->getStudent()->getFullName(),
            'assessment_type' => $assessment->getAssessmentType(),
            'context' => $assessment->getContext(),
            'assessment_date' => $assessment->getAssessmentDate()->format('Y-m-d'),
            'is_complete' => $assessment->isComplete(),
            'has_cross_evaluation' => $assessment->hasCrossEvaluation(),
            'skills_evaluated_count' => count($assessment->getSkillsEvaluated()),
        ]);

        try {
            $analysisStartTime = microtime(true);

            $analysisResults = [
                'overall_status' => $this->getOverallSkillsStatus($assessment),
                'skills_analysis' => $this->analyzeSkillsPerformance($assessment),
                'cross_evaluation_analysis' => $this->analyzeCrossEvaluation($assessment),
                'development_recommendations' => $this->generateDevelopmentRecommendations($assessment),
                'strengths_and_weaknesses' => $this->identifyStrengthsAndWeaknesses($assessment),
                'progression_tracking' => $this->trackSkillsProgression($assessment),
            ];

            $analysisEndTime = microtime(true);
            $analysisTime = ($analysisEndTime - $analysisStartTime) * 1000; // Convert to milliseconds

            $this->logger->info('Comprehensive skills assessment analysis completed successfully', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'analysis_time_ms' => round($analysisTime, 2),
                'overall_status' => $analysisResults['overall_status']['status'],
                'overall_score' => $analysisResults['overall_status']['overall_score'],
                'skills_analyzed' => count($analysisResults['skills_analysis']),
                'strengths_identified' => count($analysisResults['strengths_and_weaknesses']['strengths']),
                'weaknesses_identified' => count($analysisResults['strengths_and_weaknesses']['weaknesses']),
                'development_recommendations' => count($analysisResults['development_recommendations']),
                'has_cross_evaluation_analysis' => $analysisResults['cross_evaluation_analysis']['has_cross_evaluation'],
                'progression_data_available' => $analysisResults['progression_tracking']['has_progression_data'],
                'analysis_quality_indicators' => [
                    'complete_assessment' => $assessment->isComplete(),
                    'has_cross_evaluation' => $assessment->hasCrossEvaluation(),
                    'sufficient_skills_data' => count($assessment->getSkillsEvaluated()) >= 5,
                    'has_development_plan' => count($assessment->getDevelopmentPlan()) > 0,
                ],
            ]);

            return $analysisResults;
        } catch (Exception $e) {
            $this->logger->error('Failed to analyze skills assessment', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'assessment_type' => $assessment->getAssessmentType(),
                'context' => $assessment->getContext(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to analyze skills assessment {$assessment->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Approve a skills assessment.
     */
    public function approveAssessment(SkillsAssessment $assessment, string $comments = ''): SkillsAssessment
    {
        $this->logger->info('Starting skills assessment approval', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()->getId(),
            'student_name' => $assessment->getStudent()->getFullName(),
            'assessment_type' => $assessment->getAssessmentType(),
            'context' => $assessment->getContext(),
            'overall_rating' => $assessment->getOverallRating(),
            'is_complete' => $assessment->isComplete(),
            'has_cross_evaluation' => $assessment->hasCrossEvaluation(),
            'has_comments' => !empty($comments),
            'comments_length' => strlen($comments),
        ]);

        try {
            // Add approval note to development plan
            $currentPlan = $assessment->getDevelopmentPlan();
            $approvalNote = [
                'type' => 'validation',
                'date' => (new DateTime())->format('Y-m-d H:i:s'),
                'status' => 'approved',
                'comments' => $comments ?: 'Évaluation approuvée',
                'action' => 'approval',
            ];

            $this->logger->debug('Creating approval note for development plan', [
                'assessment_id' => $assessment->getId(),
                'approval_date' => $approvalNote['date'],
                'approval_comments' => $approvalNote['comments'],
                'current_plan_items' => count($currentPlan),
            ]);

            $currentPlan[] = $approvalNote;
            $assessment->setDevelopmentPlan($currentPlan);

            $this->entityManager->flush();

            $this->logger->info('Skills assessment approved successfully', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'student_name' => $assessment->getStudent()->getFullName(),
                'approval_date' => $approvalNote['date'],
                'approval_comments' => $comments,
                'development_plan_items_total' => count($currentPlan),
                'assessment_details' => [
                    'type' => $assessment->getAssessmentType(),
                    'context' => $assessment->getContext(),
                    'overall_rating' => $assessment->getOverallRating(),
                    'skills_evaluated' => count($assessment->getSkillsEvaluated()),
                    'is_complete' => $assessment->isComplete(),
                    'has_cross_evaluation' => $assessment->hasCrossEvaluation(),
                ],
            ]);

            return $assessment;
        } catch (Exception $e) {
            $this->logger->error('Failed to approve skills assessment', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'comments' => $comments,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to approve skills assessment {$assessment->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Reject a skills assessment.
     */
    public function rejectAssessment(SkillsAssessment $assessment, string $comments = ''): SkillsAssessment
    {
        $this->logger->info('Starting skills assessment rejection', [
            'assessment_id' => $assessment->getId(),
            'student_id' => $assessment->getStudent()->getId(),
            'student_name' => $assessment->getStudent()->getFullName(),
            'assessment_type' => $assessment->getAssessmentType(),
            'context' => $assessment->getContext(),
            'overall_rating' => $assessment->getOverallRating(),
            'is_complete' => $assessment->isComplete(),
            'has_cross_evaluation' => $assessment->hasCrossEvaluation(),
            'has_comments' => !empty($comments),
            'comments_length' => strlen($comments),
        ]);

        try {
            // Add rejection note to development plan
            $currentPlan = $assessment->getDevelopmentPlan();
            $rejectionNote = [
                'type' => 'validation',
                'date' => (new DateTime())->format('Y-m-d H:i:s'),
                'status' => 'rejected',
                'comments' => $comments ?: 'Évaluation rejetée - révision nécessaire',
                'action' => 'rejection',
            ];

            $this->logger->debug('Creating rejection note for development plan', [
                'assessment_id' => $assessment->getId(),
                'rejection_date' => $rejectionNote['date'],
                'rejection_comments' => $rejectionNote['comments'],
                'current_plan_items' => count($currentPlan),
                'rejection_reason' => $comments ?: 'No specific reason provided',
            ]);

            $currentPlan[] = $rejectionNote;
            $assessment->setDevelopmentPlan($currentPlan);

            $this->entityManager->flush();

            $this->logger->warning('Skills assessment rejected', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'student_name' => $assessment->getStudent()->getFullName(),
                'rejection_date' => $rejectionNote['date'],
                'rejection_comments' => $comments,
                'development_plan_items_total' => count($currentPlan),
                'assessment_details' => [
                    'type' => $assessment->getAssessmentType(),
                    'context' => $assessment->getContext(),
                    'overall_rating' => $assessment->getOverallRating(),
                    'skills_evaluated' => count($assessment->getSkillsEvaluated()),
                    'is_complete' => $assessment->isComplete(),
                    'has_cross_evaluation' => $assessment->hasCrossEvaluation(),
                ],
                'action_required' => 'Assessment needs revision and resubmission',
            ]);

            return $assessment;
        } catch (Exception $e) {
            $this->logger->error('Failed to reject skills assessment', [
                'assessment_id' => $assessment->getId(),
                'student_id' => $assessment->getStudent()->getId(),
                'comments' => $comments,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "Failed to reject skills assessment {$assessment->getId()}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Get skill category from skill code.
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
            'leadership' => 'professional',
        ];

        return $categoryMap[$skillCode] ?? 'technical';
    }

    /**
     * Get overall status of skills assessment.
     */
    private function getOverallSkillsStatus(SkillsAssessment $assessment): array
    {
        $overallScore = $assessment->getOverallAverageScore();

        if ($overallScore >= 4) {
            $status = 'excellent';
            $message = 'Compétences excellentes, autonomie confirmée';
        } elseif ($overallScore >= 3) {
            $status = 'good';
            $message = 'Compétences satisfaisantes, progression normale';
        } elseif ($overallScore >= 2) {
            $status = 'average';
            $message = 'Compétences moyennes, accompagnement recommandé';
        } else {
            $status = 'poor';
            $message = 'Compétences insuffisantes, soutien renforcé nécessaire';
        }

        return [
            'status' => $status,
            'message' => $message,
            'overall_score' => $overallScore,
            'is_complete' => $assessment->isComplete(),
            'has_cross_evaluation' => $assessment->hasCrossEvaluation(),
        ];
    }

    /**
     * Analyze skills performance.
     */
    private function analyzeSkillsPerformance(SkillsAssessment $assessment): array
    {
        $centerScores = $assessment->getCenterScores();
        $companyScores = $assessment->getCompanyScores();

        $skillsAnalysis = [];

        foreach ($assessment->getSkillsEvaluated() as $skill) {
            $centerScore = $centerScores[$skill] ?? 0;
            $companyScore = $companyScores[$skill] ?? 0;
            $gap = abs($centerScore - $companyScore);

            $skillsAnalysis[$skill] = [
                'center_score' => $centerScore,
                'company_score' => $companyScore,
                'average_score' => ($centerScore + $companyScore) / 2,
                'score_gap' => $gap,
                'gap_level' => $gap > 2 ? 'high' : ($gap > 1 ? 'medium' : 'low'),
                'status' => $this->getSkillStatus(($centerScore + $companyScore) / 2),
            ];
        }

        return $skillsAnalysis;
    }

    /**
     * Analyze cross evaluation.
     */
    private function analyzeCrossEvaluation(SkillsAssessment $assessment): array
    {
        if (!$assessment->hasCrossEvaluation()) {
            return ['has_cross_evaluation' => false];
        }

        $centerScores = $assessment->getCenterScores();
        $companyScores = $assessment->getCompanyScores();

        $totalGap = 0;
        $skillsCount = count($assessment->getSkillsEvaluated());

        foreach ($assessment->getSkillsEvaluated() as $skill) {
            $centerScore = $centerScores[$skill] ?? 0;
            $companyScore = $companyScores[$skill] ?? 0;
            $totalGap += abs($centerScore - $companyScore);
        }

        $averageGap = $skillsCount > 0 ? $totalGap / $skillsCount : 0;

        return [
            'has_cross_evaluation' => true,
            'average_gap' => $averageGap,
            'gap_level' => $averageGap > 2 ? 'high' : ($averageGap > 1 ? 'medium' : 'low'),
            'alignment_status' => $averageGap <= 1 ? 'aligned' : 'needs_review',
        ];
    }

    /**
     * Generate development recommendations.
     */
    private function generateDevelopmentRecommendations(SkillsAssessment $assessment): array
    {
        $recommendations = [];
        $skillsAnalysis = $this->analyzeSkillsPerformance($assessment);

        foreach ($skillsAnalysis as $skill => $analysis) {
            if ($analysis['average_score'] < 3) {
                $recommendations[] = [
                    'skill' => $skill,
                    'priority' => 'high',
                    'type' => 'improvement',
                    'recommendation' => "Renforcement urgent nécessaire pour {$skill}",
                    'actions' => [
                        'Formation spécialisée',
                        'Mentorat renforcé',
                        'Pratique supervisée',
                    ],
                ];
            } elseif ($analysis['gap_level'] === 'high') {
                $recommendations[] = [
                    'skill' => $skill,
                    'priority' => 'medium',
                    'type' => 'alignment',
                    'recommendation' => "Harmonisation centre-entreprise pour {$skill}",
                    'actions' => [
                        'Réunion tripartite',
                        'Clarification des attentes',
                        'Ajustement des objectifs',
                    ],
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Identify strengths and weaknesses.
     */
    private function identifyStrengthsAndWeaknesses(SkillsAssessment $assessment): array
    {
        $skillsAnalysis = $this->analyzeSkillsPerformance($assessment);

        $strengths = [];
        $weaknesses = [];

        foreach ($skillsAnalysis as $skill => $analysis) {
            if ($analysis['average_score'] >= 4) {
                $strengths[] = [
                    'skill' => $skill,
                    'score' => $analysis['average_score'],
                    'description' => "Maîtrise excellente de {$skill}",
                ];
            } elseif ($analysis['average_score'] < 2) {
                $weaknesses[] = [
                    'skill' => $skill,
                    'score' => $analysis['average_score'],
                    'description' => "Compétence à développer : {$skill}",
                ];
            }
        }

        return [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
        ];
    }

    /**
     * Track skills progression.
     */
    private function trackSkillsProgression(SkillsAssessment $assessment): array
    {
        // This would compare with previous assessments
        $student = $assessment->getStudent();
        $previousAssessments = $this->skillsAssessmentRepository->findBy(
            ['student' => $student],
            ['assessmentDate' => 'DESC'],
            5,
        );

        return [
            'has_progression_data' => count($previousAssessments) > 1,
            'assessment_count' => count($previousAssessments),
            'progression_trend' => 'stable', // Would be calculated from actual data
        ];
    }

    /**
     * Get skill status based on score.
     */
    private function getSkillStatus(float $score): string
    {
        if ($score >= 4) {
            return 'mastered';
        }
        if ($score >= 3) {
            return 'competent';
        }
        if ($score >= 2) {
            return 'developing';
        }

        return 'needs_work';
    }
}
