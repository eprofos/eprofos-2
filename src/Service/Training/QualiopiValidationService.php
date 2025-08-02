<?php

declare(strict_types=1);

namespace App\Service\Training;

use App\Entity\Training\Formation;
use Psr\Log\LoggerInterface;

/**
 * Service for validating Qualiopi compliance.
 *
 * Provides methods to check if formations meet Qualiopi requirements,
 * particularly focusing on criterion 2.5 for operational and evaluable objectives.
 */
class QualiopiValidationService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Validate that a formation meets Qualiopi 2.5 requirements for objectives.
     */
    public function validateObjectives(Formation $formation): array
    {
        $this->logger->info('Starting Qualiopi 2.5 objectives validation', [
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'method' => 'validateObjectives'
        ]);

        $errors = [];

        try {
            // Check operational objectives (2.5 requirement)
            $this->logger->debug('Validating operational objectives', [
                'formation_id' => $formation->getId(),
                'operational_objectives_count' => $formation->getOperationalObjectives() ? count($formation->getOperationalObjectives()) : 0,
                'operational_objectives' => $formation->getOperationalObjectives()
            ]);

            if (empty($formation->getOperationalObjectives())) {
                $error = 'Objectifs opérationnels manquants (requis Qualiopi 2.5)';
                $errors[] = $error;
                $this->logger->warning('Missing operational objectives', [
                    'formation_id' => $formation->getId(),
                    'error' => $error,
                    'qualiopi_criterion' => '2.5'
                ]);
            } elseif (count($formation->getOperationalObjectives()) < 2) {
                $error = 'Au moins 2 objectifs opérationnels sont requis (Qualiopi 2.5)';
                $errors[] = $error;
                $this->logger->warning('Insufficient operational objectives', [
                    'formation_id' => $formation->getId(),
                    'current_count' => count($formation->getOperationalObjectives()),
                    'required_minimum' => 2,
                    'error' => $error,
                    'qualiopi_criterion' => '2.5'
                ]);
            } else {
                $this->logger->info('Operational objectives validation passed', [
                    'formation_id' => $formation->getId(),
                    'objectives_count' => count($formation->getOperationalObjectives())
                ]);
            }

            // Check evaluable objectives (2.5 requirement)
            $this->logger->debug('Validating evaluable objectives', [
                'formation_id' => $formation->getId(),
                'evaluable_objectives_count' => $formation->getEvaluableObjectives() ? count($formation->getEvaluableObjectives()) : 0,
                'evaluable_objectives' => $formation->getEvaluableObjectives()
            ]);

            if (empty($formation->getEvaluableObjectives())) {
                $error = 'Objectifs évaluables manquants (requis Qualiopi 2.5)';
                $errors[] = $error;
                $this->logger->warning('Missing evaluable objectives', [
                    'formation_id' => $formation->getId(),
                    'error' => $error,
                    'qualiopi_criterion' => '2.5'
                ]);
            } elseif (count($formation->getEvaluableObjectives()) < 2) {
                $error = 'Au moins 2 objectifs évaluables sont requis (Qualiopi 2.5)';
                $errors[] = $error;
                $this->logger->warning('Insufficient evaluable objectives', [
                    'formation_id' => $formation->getId(),
                    'current_count' => count($formation->getEvaluableObjectives()),
                    'required_minimum' => 2,
                    'error' => $error,
                    'qualiopi_criterion' => '2.5'
                ]);
            } else {
                $this->logger->info('Evaluable objectives validation passed', [
                    'formation_id' => $formation->getId(),
                    'objectives_count' => count($formation->getEvaluableObjectives())
                ]);
            }

            // Check evaluation criteria
            $this->logger->debug('Validating evaluation criteria', [
                'formation_id' => $formation->getId(),
                'evaluation_criteria_count' => $formation->getEvaluationCriteria() ? count($formation->getEvaluationCriteria()) : 0,
                'evaluation_criteria' => $formation->getEvaluationCriteria()
            ]);

            if (empty($formation->getEvaluationCriteria())) {
                $error = 'Critères d\'évaluation manquants (requis Qualiopi 2.5)';
                $errors[] = $error;
                $this->logger->warning('Missing evaluation criteria', [
                    'formation_id' => $formation->getId(),
                    'error' => $error,
                    'qualiopi_criterion' => '2.5'
                ]);
            } elseif (count($formation->getEvaluationCriteria()) < 2) {
                $error = 'Au moins 2 critères d\'évaluation sont requis (Qualiopi 2.5)';
                $errors[] = $error;
                $this->logger->warning('Insufficient evaluation criteria', [
                    'formation_id' => $formation->getId(),
                    'current_count' => count($formation->getEvaluationCriteria()),
                    'required_minimum' => 2,
                    'error' => $error,
                    'qualiopi_criterion' => '2.5'
                ]);
            } else {
                $this->logger->info('Evaluation criteria validation passed', [
                    'formation_id' => $formation->getId(),
                    'criteria_count' => count($formation->getEvaluationCriteria())
                ]);
            }

            // Check success indicators
            $this->logger->debug('Validating success indicators', [
                'formation_id' => $formation->getId(),
                'success_indicators_count' => $formation->getSuccessIndicators() ? count($formation->getSuccessIndicators()) : 0,
                'success_indicators' => $formation->getSuccessIndicators()
            ]);

            if (empty($formation->getSuccessIndicators())) {
                $error = 'Indicateurs de réussite manquants (requis Qualiopi 2.5)';
                $errors[] = $error;
                $this->logger->warning('Missing success indicators', [
                    'formation_id' => $formation->getId(),
                    'error' => $error,
                    'qualiopi_criterion' => '2.5'
                ]);
            } elseif (count($formation->getSuccessIndicators()) < 2) {
                $error = 'Au moins 2 indicateurs de réussite sont requis (Qualiopi 2.5)';
                $errors[] = $error;
                $this->logger->warning('Insufficient success indicators', [
                    'formation_id' => $formation->getId(),
                    'current_count' => count($formation->getSuccessIndicators()),
                    'required_minimum' => 2,
                    'error' => $error,
                    'qualiopi_criterion' => '2.5'
                ]);
            } else {
                $this->logger->info('Success indicators validation passed', [
                    'formation_id' => $formation->getId(),
                    'indicators_count' => count($formation->getSuccessIndicators())
                ]);
            }

            $this->logger->info('Qualiopi 2.5 objectives validation completed', [
                'formation_id' => $formation->getId(),
                'total_errors' => count($errors),
                'errors' => $errors,
                'is_compliant' => empty($errors)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error during Qualiopi objectives validation', [
                'formation_id' => $formation->getId(),
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'method' => 'validateObjectives'
            ]);

            // Re-throw the exception to maintain error handling behavior
            throw $e;
        }

        return $errors;
    }

    /**
     * Check if a formation is compliant with Qualiopi 2.5.
     */
    public function isCompliantWithCriteria25(Formation $formation): bool
    {
        $this->logger->info('Checking Qualiopi 2.5 compliance', [
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'method' => 'isCompliantWithCriteria25'
        ]);

        try {
            $errors = $this->validateObjectives($formation);
            $isCompliant = empty($errors);

            $this->logger->info('Qualiopi 2.5 compliance check completed', [
                'formation_id' => $formation->getId(),
                'is_compliant' => $isCompliant,
                'errors_count' => count($errors),
                'errors' => $errors
            ]);

            return $isCompliant;

        } catch (\Exception $e) {
            $this->logger->error('Error during Qualiopi 2.5 compliance check', [
                'formation_id' => $formation->getId(),
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'method' => 'isCompliantWithCriteria25'
            ]);

            // Re-throw the exception to maintain error handling behavior
            throw $e;
        }
    }

    /**
     * Generate a complete Qualiopi compliance report for a formation.
     */
    public function generateQualiopiReport(Formation $formation): array
    {
        $this->logger->info('Starting Qualiopi compliance report generation', [
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'method' => 'generateQualiopiReport'
        ]);

        try {
            $objectivesErrors = $this->validateObjectives($formation);

            $this->logger->debug('Evaluating general Qualiopi fields', [
                'formation_id' => $formation->getId(),
                'target_audience_filled' => !empty($formation->getTargetAudience()),
                'access_modalities_filled' => !empty($formation->getAccessModalities()),
                'handicap_accessibility_filled' => !empty($formation->getHandicapAccessibility()),
                'teaching_methods_filled' => !empty($formation->getTeachingMethods()),
                'evaluation_methods_filled' => !empty($formation->getEvaluationMethods()),
                'contact_info_filled' => !empty($formation->getContactInfo()),
                'training_location_filled' => !empty($formation->getTrainingLocation()),
                'funding_modalities_filled' => !empty($formation->getFundingModalities())
            ]);

            $objectivesScore = $this->calculateObjectivesScore($formation);
            $overallCompliance = $this->calculateOverallCompliance($formation);

            $this->logger->debug('Calculated compliance metrics', [
                'formation_id' => $formation->getId(),
                'objectives_score' => $objectivesScore,
                'overall_compliance' => $overallCompliance
            ]);

            $report = [
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'critere_2_5' => [
                    'compliant' => empty($objectivesErrors),
                    'errors' => $objectivesErrors,
                    'operational_objectives' => $formation->getOperationalObjectives() ?? [],
                    'evaluable_objectives' => $formation->getEvaluableObjectives() ?? [],
                    'evaluation_criteria' => $formation->getEvaluationCriteria() ?? [],
                    'success_indicators' => $formation->getSuccessIndicators() ?? [],
                    'score' => $objectivesScore,
                ],
                'general_qualiopi_fields' => [
                    'target_audience' => !empty($formation->getTargetAudience()),
                    'access_modalities' => !empty($formation->getAccessModalities()),
                    'handicap_accessibility' => !empty($formation->getHandicapAccessibility()),
                    'teaching_methods' => !empty($formation->getTeachingMethods()),
                    'evaluation_methods' => !empty($formation->getEvaluationMethods()),
                    'contact_info' => !empty($formation->getContactInfo()),
                    'training_location' => !empty($formation->getTrainingLocation()),
                    'funding_modalities' => !empty($formation->getFundingModalities()),
                ],
                'overall_compliance' => $overallCompliance,
            ];

            $this->logger->info('Qualiopi compliance report generated successfully', [
                'formation_id' => $formation->getId(),
                'critere_2_5_compliant' => $report['critere_2_5']['compliant'],
                'objectives_score' => $report['critere_2_5']['score'],
                'overall_compliance' => $report['overall_compliance'],
                'general_fields_filled' => array_sum($report['general_qualiopi_fields']),
                'total_general_fields' => count($report['general_qualiopi_fields'])
            ]);

            return $report;

        } catch (\Exception $e) {
            $this->logger->error('Error during Qualiopi report generation', [
                'formation_id' => $formation->getId(),
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'method' => 'generateQualiopiReport'
            ]);

            // Re-throw the exception to maintain error handling behavior
            throw $e;
        }
    }

    /**
     * Get suggestions for improving Qualiopi compliance.
     */
    public function getComplianceSuggestions(Formation $formation): array
    {
        $this->logger->info('Generating Qualiopi compliance suggestions', [
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'method' => 'getComplianceSuggestions'
        ]);

        $suggestions = [];

        try {
            // Check each structured objective type
            $this->logger->debug('Analyzing formation fields for suggestions', [
                'formation_id' => $formation->getId(),
                'has_operational_objectives' => !empty($formation->getOperationalObjectives()),
                'has_evaluable_objectives' => !empty($formation->getEvaluableObjectives()),
                'has_evaluation_criteria' => !empty($formation->getEvaluationCriteria()),
                'has_success_indicators' => !empty($formation->getSuccessIndicators()),
                'has_target_audience' => !empty($formation->getTargetAudience()),
                'has_access_modalities' => !empty($formation->getAccessModalities()),
                'has_handicap_accessibility' => !empty($formation->getHandicapAccessibility())
            ]);

            if (empty($formation->getOperationalObjectives())) {
                $suggestion = 'Ajoutez des objectifs opérationnels décrivant ce que les participants seront capables de faire après la formation';
                $suggestions[] = $suggestion;
                $this->logger->debug('Added suggestion for operational objectives', [
                    'formation_id' => $formation->getId(),
                    'suggestion' => $suggestion
                ]);
            }

            if (empty($formation->getEvaluableObjectives())) {
                $suggestion = 'Définissez des objectifs évaluables avec des critères mesurables et quantifiables';
                $suggestions[] = $suggestion;
                $this->logger->debug('Added suggestion for evaluable objectives', [
                    'formation_id' => $formation->getId(),
                    'suggestion' => $suggestion
                ]);
            }

            if (empty($formation->getEvaluationCriteria())) {
                $suggestion = 'Précisez les critères d\'évaluation pour mesurer l\'atteinte des objectifs';
                $suggestions[] = $suggestion;
                $this->logger->debug('Added suggestion for evaluation criteria', [
                    'formation_id' => $formation->getId(),
                    'suggestion' => $suggestion
                ]);
            }

            if (empty($formation->getSuccessIndicators())) {
                $suggestion = 'Établissez des indicateurs de réussite pour suivre l\'efficacité de la formation';
                $suggestions[] = $suggestion;
                $this->logger->debug('Added suggestion for success indicators', [
                    'formation_id' => $formation->getId(),
                    'suggestion' => $suggestion
                ]);
            }

            // Check basic Qualiopi fields
            if (empty($formation->getTargetAudience())) {
                $suggestion = 'Décrivez le public cible de la formation';
                $suggestions[] = $suggestion;
                $this->logger->debug('Added suggestion for target audience', [
                    'formation_id' => $formation->getId(),
                    'suggestion' => $suggestion
                ]);
            }

            if (empty($formation->getAccessModalities())) {
                $suggestion = 'Précisez les modalités d\'accès à la formation';
                $suggestions[] = $suggestion;
                $this->logger->debug('Added suggestion for access modalities', [
                    'formation_id' => $formation->getId(),
                    'suggestion' => $suggestion
                ]);
            }

            if (empty($formation->getHandicapAccessibility())) {
                $suggestion = 'Indiquez les dispositions pour l\'accessibilité aux personnes en situation de handicap';
                $suggestions[] = $suggestion;
                $this->logger->debug('Added suggestion for handicap accessibility', [
                    'formation_id' => $formation->getId(),
                    'suggestion' => $suggestion
                ]);
            }

            $this->logger->info('Qualiopi compliance suggestions generated', [
                'formation_id' => $formation->getId(),
                'total_suggestions' => count($suggestions),
                'suggestions' => $suggestions
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error during compliance suggestions generation', [
                'formation_id' => $formation->getId(),
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'method' => 'getComplianceSuggestions'
            ]);

            // Re-throw the exception to maintain error handling behavior
            throw $e;
        }

        return $suggestions;
    }

    /**
     * Calculate a score for objectives completeness (0-100).
     */
    private function calculateObjectivesScore(Formation $formation): int
    {
        $this->logger->debug('Calculating objectives score', [
            'formation_id' => $formation->getId(),
            'method' => 'calculateObjectivesScore'
        ]);

        try {
            $score = 0;

            // Operational objectives (25 points)
            $operationalCount = $formation->getOperationalObjectives() ? count($formation->getOperationalObjectives()) : 0;
            $operationalScore = 0;
            if ($operationalCount > 0) {
                $operationalScore = min(25, $operationalCount * 5);
                $score += $operationalScore;
            }

            $this->logger->debug('Operational objectives score calculated', [
                'formation_id' => $formation->getId(),
                'operational_count' => $operationalCount,
                'operational_score' => $operationalScore
            ]);

            // Evaluable objectives (25 points)
            $evaluableCount = $formation->getEvaluableObjectives() ? count($formation->getEvaluableObjectives()) : 0;
            $evaluableScore = 0;
            if ($evaluableCount > 0) {
                $evaluableScore = min(25, $evaluableCount * 5);
                $score += $evaluableScore;
            }

            $this->logger->debug('Evaluable objectives score calculated', [
                'formation_id' => $formation->getId(),
                'evaluable_count' => $evaluableCount,
                'evaluable_score' => $evaluableScore
            ]);

            // Evaluation criteria (25 points)
            $criteriaCount = $formation->getEvaluationCriteria() ? count($formation->getEvaluationCriteria()) : 0;
            $criteriaScore = 0;
            if ($criteriaCount > 0) {
                $criteriaScore = min(25, $criteriaCount * 5);
                $score += $criteriaScore;
            }

            $this->logger->debug('Evaluation criteria score calculated', [
                'formation_id' => $formation->getId(),
                'criteria_count' => $criteriaCount,
                'criteria_score' => $criteriaScore
            ]);

            // Success indicators (25 points)
            $indicatorsCount = $formation->getSuccessIndicators() ? count($formation->getSuccessIndicators()) : 0;
            $indicatorsScore = 0;
            if ($indicatorsCount > 0) {
                $indicatorsScore = min(25, $indicatorsCount * 5);
                $score += $indicatorsScore;
            }

            $this->logger->debug('Success indicators score calculated', [
                'formation_id' => $formation->getId(),
                'indicators_count' => $indicatorsCount,
                'indicators_score' => $indicatorsScore
            ]);

            $this->logger->info('Objectives score calculation completed', [
                'formation_id' => $formation->getId(),
                'total_score' => $score,
                'breakdown' => [
                    'operational' => $operationalScore,
                    'evaluable' => $evaluableScore,
                    'criteria' => $criteriaScore,
                    'indicators' => $indicatorsScore
                ]
            ]);

            return $score;

        } catch (\Exception $e) {
            $this->logger->error('Error during objectives score calculation', [
                'formation_id' => $formation->getId(),
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'method' => 'calculateObjectivesScore'
            ]);

            // Re-throw the exception to maintain error handling behavior
            throw $e;
        }
    }

    /**
     * Calculate overall Qualiopi compliance percentage.
     */
    private function calculateOverallCompliance(Formation $formation): float
    {
        $this->logger->debug('Calculating overall compliance', [
            'formation_id' => $formation->getId(),
            'method' => 'calculateOverallCompliance'
        ]);

        try {
            $requiredFields = [
                'targetAudience' => $formation->getTargetAudience(),
                'accessModalities' => $formation->getAccessModalities(),
                'handicapAccessibility' => $formation->getHandicapAccessibility(),
                'teachingMethods' => $formation->getTeachingMethods(),
                'evaluationMethods' => $formation->getEvaluationMethods(),
                'contactInfo' => $formation->getContactInfo(),
                'trainingLocation' => $formation->getTrainingLocation(),
                'fundingModalities' => $formation->getFundingModalities(),
            ];

            $this->logger->debug('Required fields status', [
                'formation_id' => $formation->getId(),
                'fields_status' => array_map(function($value) {
                    return !empty($value);
                }, $requiredFields)
            ]);

            $filledFields = 0;
            foreach ($requiredFields as $field => $value) {
                if (!empty($value)) {
                    $filledFields++;
                }
            }

            $this->logger->debug('Fields completion analysis', [
                'formation_id' => $formation->getId(),
                'filled_fields' => $filledFields,
                'total_fields' => count($requiredFields),
                'completion_percentage' => ($filledFields / count($requiredFields)) * 100
            ]);

            $basicCompliance = ($filledFields / count($requiredFields)) * 80; // 80% for basic fields
            $objectivesScore = $this->calculateObjectivesScore($formation);
            $objectivesCompliance = ($objectivesScore / 100) * 20; // 20% for objectives

            $overallCompliance = round($basicCompliance + $objectivesCompliance, 1);

            $this->logger->info('Overall compliance calculation completed', [
                'formation_id' => $formation->getId(),
                'basic_compliance' => round($basicCompliance, 1),
                'objectives_compliance' => round($objectivesCompliance, 1),
                'overall_compliance' => $overallCompliance,
                'objectives_score' => $objectivesScore,
                'filled_fields_ratio' => "{$filledFields}/" . count($requiredFields)
            ]);

            return $overallCompliance;

        } catch (\Exception $e) {
            $this->logger->error('Error during overall compliance calculation', [
                'formation_id' => $formation->getId(),
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'method' => 'calculateOverallCompliance'
            ]);

            // Re-throw the exception to maintain error handling behavior
            throw $e;
        }
    }
}
