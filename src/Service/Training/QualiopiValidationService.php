<?php

declare(strict_types=1);

namespace App\Service\Training;

use App\Entity\Training\Formation;

/**
 * Service for validating Qualiopi compliance.
 *
 * Provides methods to check if formations meet Qualiopi requirements,
 * particularly focusing on criterion 2.5 for operational and evaluable objectives.
 */
class QualiopiValidationService
{
    /**
     * Validate that a formation meets Qualiopi 2.5 requirements for objectives.
     */
    public function validateObjectives(Formation $formation): array
    {
        $errors = [];

        // Check operational objectives (2.5 requirement)
        if (empty($formation->getOperationalObjectives())) {
            $errors[] = 'Objectifs opérationnels manquants (requis Qualiopi 2.5)';
        } elseif (count($formation->getOperationalObjectives()) < 2) {
            $errors[] = 'Au moins 2 objectifs opérationnels sont requis (Qualiopi 2.5)';
        }

        // Check evaluable objectives (2.5 requirement)
        if (empty($formation->getEvaluableObjectives())) {
            $errors[] = 'Objectifs évaluables manquants (requis Qualiopi 2.5)';
        } elseif (count($formation->getEvaluableObjectives()) < 2) {
            $errors[] = 'Au moins 2 objectifs évaluables sont requis (Qualiopi 2.5)';
        }

        // Check evaluation criteria
        if (empty($formation->getEvaluationCriteria())) {
            $errors[] = 'Critères d\'évaluation manquants (requis Qualiopi 2.5)';
        } elseif (count($formation->getEvaluationCriteria()) < 2) {
            $errors[] = 'Au moins 2 critères d\'évaluation sont requis (Qualiopi 2.5)';
        }

        // Check success indicators
        if (empty($formation->getSuccessIndicators())) {
            $errors[] = 'Indicateurs de réussite manquants (requis Qualiopi 2.5)';
        } elseif (count($formation->getSuccessIndicators()) < 2) {
            $errors[] = 'Au moins 2 indicateurs de réussite sont requis (Qualiopi 2.5)';
        }

        return $errors;
    }

    /**
     * Check if a formation is compliant with Qualiopi 2.5.
     */
    public function isCompliantWithCriteria25(Formation $formation): bool
    {
        $errors = $this->validateObjectives($formation);

        return empty($errors);
    }

    /**
     * Generate a complete Qualiopi compliance report for a formation.
     */
    public function generateQualiopiReport(Formation $formation): array
    {
        $objectivesErrors = $this->validateObjectives($formation);

        return [
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'critere_2_5' => [
                'compliant' => empty($objectivesErrors),
                'errors' => $objectivesErrors,
                'operational_objectives' => $formation->getOperationalObjectives() ?? [],
                'evaluable_objectives' => $formation->getEvaluableObjectives() ?? [],
                'evaluation_criteria' => $formation->getEvaluationCriteria() ?? [],
                'success_indicators' => $formation->getSuccessIndicators() ?? [],
                'score' => $this->calculateObjectivesScore($formation),
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
            'overall_compliance' => $this->calculateOverallCompliance($formation),
        ];
    }

    /**
     * Get suggestions for improving Qualiopi compliance.
     */
    public function getComplianceSuggestions(Formation $formation): array
    {
        $suggestions = [];

        // Check each structured objective type
        if (empty($formation->getOperationalObjectives())) {
            $suggestions[] = 'Ajoutez des objectifs opérationnels décrivant ce que les participants seront capables de faire après la formation';
        }

        if (empty($formation->getEvaluableObjectives())) {
            $suggestions[] = 'Définissez des objectifs évaluables avec des critères mesurables et quantifiables';
        }

        if (empty($formation->getEvaluationCriteria())) {
            $suggestions[] = 'Précisez les critères d\'évaluation pour mesurer l\'atteinte des objectifs';
        }

        if (empty($formation->getSuccessIndicators())) {
            $suggestions[] = 'Établissez des indicateurs de réussite pour suivre l\'efficacité de la formation';
        }

        // Check basic Qualiopi fields
        if (empty($formation->getTargetAudience())) {
            $suggestions[] = 'Décrivez le public cible de la formation';
        }

        if (empty($formation->getAccessModalities())) {
            $suggestions[] = 'Précisez les modalités d\'accès à la formation';
        }

        if (empty($formation->getHandicapAccessibility())) {
            $suggestions[] = 'Indiquez les dispositions pour l\'accessibilité aux personnes en situation de handicap';
        }

        return $suggestions;
    }

    /**
     * Calculate a score for objectives completeness (0-100).
     */
    private function calculateObjectivesScore(Formation $formation): int
    {
        $score = 0;

        // Operational objectives (25 points)
        if (!empty($formation->getOperationalObjectives())) {
            $score += min(25, count($formation->getOperationalObjectives()) * 5);
        }

        // Evaluable objectives (25 points)
        if (!empty($formation->getEvaluableObjectives())) {
            $score += min(25, count($formation->getEvaluableObjectives()) * 5);
        }

        // Evaluation criteria (25 points)
        if (!empty($formation->getEvaluationCriteria())) {
            $score += min(25, count($formation->getEvaluationCriteria()) * 5);
        }

        // Success indicators (25 points)
        if (!empty($formation->getSuccessIndicators())) {
            $score += min(25, count($formation->getSuccessIndicators()) * 5);
        }

        return $score;
    }

    /**
     * Calculate overall Qualiopi compliance percentage.
     */
    private function calculateOverallCompliance(Formation $formation): float
    {
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

        $filledFields = 0;
        foreach ($requiredFields as $field => $value) {
            if (!empty($value)) {
                $filledFields++;
            }
        }

        $basicCompliance = ($filledFields / count($requiredFields)) * 80; // 80% for basic fields
        $objectivesCompliance = ($this->calculateObjectivesScore($formation) / 100) * 20; // 20% for objectives

        return round($basicCompliance + $objectivesCompliance, 1);
    }
}
