<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Entity\Alternance\AlternanceProgram;

/**
 * Service for validating Qualiopi compliance for alternance
 * 
 * Ensures that alternance contracts and programs meet
 * French quality standards for professional training.
 */
class AlternanceValidationService
{
    /**
     * Validate contract for Qualiopi compliance
     *
     * @param AlternanceContract $contract
     * @return array Array of validation results with errors and warnings
     */
    public function validateContract(AlternanceContract $contract): array
    {
        $errors = [];
        $warnings = [];

        // Check mandatory fields
        if (!$contract->getCompanySiret()) {
            $errors[] = 'Le SIRET de l\'entreprise est obligatoire pour la conformité Qualiopi.';
        }

        if (empty($contract->getLearningObjectives())) {
            $errors[] = 'Les objectifs pédagogiques sont obligatoires (Qualiopi 2.1).';
        }

        if (empty($contract->getCompanyObjectives())) {
            $errors[] = 'Les objectifs en entreprise sont obligatoires (Qualiopi 2.1).';
        }

        // Check contract duration
        $durationWeeks = $contract->getDurationInWeeks();
        if ($contract->getContractType() === 'apprentissage' && $durationWeeks < 26) {
            $errors[] = 'Un contrat d\'apprentissage doit durer au minimum 6 mois (Qualiopi 2.3).';
        }

        if ($contract->getContractType() === 'professionnalisation' && $durationWeeks < 12) {
            $errors[] = 'Un contrat de professionnalisation doit durer au minimum 3 mois (Qualiopi 2.3).';
        }

        // Check weekly hours distribution
        $totalHours = $contract->getTotalWeeklyHours();
        if ($totalHours < 20) {
            $warnings[] = 'Le volume horaire hebdomadaire semble faible (moins de 20h).';
        }

        if ($totalHours > 35) {
            $errors[] = 'Le volume horaire hebdomadaire ne peut pas dépasser 35h pour un alternant.';
        }

        // Check supervision
        if (!$contract->getMentor()) {
            $errors[] = 'Un tuteur entreprise doit être désigné (Qualiopi 2.4).';
        }

        if (!$contract->getPedagogicalSupervisor()) {
            $errors[] = 'Un référent pédagogique doit être désigné (Qualiopi 2.4).';
        }

        // Check job description quality
        if ($contract->getJobDescription() && strlen($contract->getJobDescription()) < 100) {
            $warnings[] = 'La description du poste pourrait être plus détaillée pour une meilleure traçabilité.';
        }

        // Check remuneration field
        if (!$contract->getRemuneration()) {
            $warnings[] = 'La rémunération devrait être précisée pour la transparence.';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_compliant' => empty($errors)
        ];
    }

    /**
     * Validate program for Qualiopi compliance
     *
     * @param AlternanceProgram $program
     * @return array Array of validation results with errors and warnings
     */
    public function validateProgram(AlternanceProgram $program): array
    {
        $errors = [];
        $warnings = [];

        // Check duration consistency
        if (!$program->hasConsistentDurations()) {
            $errors[] = 'La répartition des durées centre/entreprise doit être cohérente (Qualiopi 2.2).';
        }

        // Check minimum program duration
        if ($program->getTotalDuration() < 26) {
            $warnings[] = 'La durée totale du programme semble courte pour un parcours d\'alternance.';
        }

        // Check center/company balance
        $centerPercentage = $program->getCenterDurationPercentage();
        if ($centerPercentage < 20) {
            $warnings[] = 'Le temps en centre de formation semble insuffisant (moins de 20%).';
        }

        if ($centerPercentage > 80) {
            $warnings[] = 'Le temps en entreprise semble insuffisant (moins de 20%).';
        }

        // Check program structure
        if (!$program->hasCenterModules()) {
            $errors[] = 'Des modules en centre de formation doivent être définis (Qualiopi 2.1).';
        }

        if (!$program->hasCompanyModules()) {
            $errors[] = 'Des modules en entreprise doivent être définis (Qualiopi 2.1).';
        }

        if (!$program->hasCoordinationPoints()) {
            $errors[] = 'Des points de coordination doivent être prévus (Qualiopi 2.4).';
        }

        if (!$program->hasAssessmentPeriods()) {
            $errors[] = 'Des périodes d\'évaluation doivent être définies (Qualiopi 2.5).';
        }

        // Check coordination frequency
        if ($program->hasCoordinationPoints()) {
            $coordinationPoints = $program->getCoordinationPoints();
            $hasRegularCoordination = false;
            
            foreach ($coordinationPoints as $point) {
                if (isset($point['frequency']) && 
                    in_array(strtolower($point['frequency']), ['mensuelle', 'bimensuelle', 'hebdomadaire'])) {
                    $hasRegularCoordination = true;
                    break;
                }
            }
            
            if (!$hasRegularCoordination) {
                $warnings[] = 'Une coordination régulière (au moins mensuelle) est recommandée (Qualiopi 2.4).';
            }
        }

        // Check learning progression
        if (empty($program->getLearningProgression())) {
            $warnings[] = 'Une progression pédagogique structurée améliore la traçabilité (Qualiopi 2.2).';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_compliant' => empty($errors)
        ];
    }

    /**
     * Validate session for alternance
     *
     * @param object $session Session entity
     * @return array Array of validation results
     */
    public function validateSessionForAlternance($session): array
    {
        $errors = [];
        $warnings = [];

        if (!$session->isAlternanceSession()) {
            $errors[] = 'La session doit être configurée en mode alternance.';
            return ['errors' => $errors, 'warnings' => $warnings, 'is_compliant' => false];
        }

        // Check alternance type
        if (!$session->getAlternanceType()) {
            $errors[] = 'Le type d\'alternance doit être spécifié (apprentissage ou professionnalisation).';
        }

        // Check minimum duration
        if ($session->getMinimumAlternanceDuration() && $session->getMinimumAlternanceDuration() < 12) {
            $warnings[] = 'La durée minimale semble courte pour un parcours d\'alternance.';
        }

        // Check percentage consistency
        if (!$session->hasValidAlternancePercentages()) {
            $errors[] = 'Les pourcentages centre/entreprise doivent totaliser 100%.';
        }

        // Check rhythm definition
        if (!$session->getAlternanceRhythm()) {
            $warnings[] = 'Le rythme d\'alternance devrait être précisé.';
        }

        // Check prerequisites
        $prerequisites = $session->getFormattedAlternancePrerequisites();
        if (empty($prerequisites)) {
            $warnings[] = 'Des prérequis spécifiques à l\'alternance pourraient être définis.';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_compliant' => empty($errors)
        ];
    }

    /**
     * Generate comprehensive validation report
     *
     * @param AlternanceContract|null $contract
     * @param AlternanceProgram|null $program
     * @param object|null $session
     * @return array Complete validation report
     */
    public function generateValidationReport(
        ?AlternanceContract $contract = null,
        ?AlternanceProgram $program = null,
        ?object $session = null
    ): array {
        $report = [
            'overall_compliance' => true,
            'sections' => [],
            'summary' => [
                'total_errors' => 0,
                'total_warnings' => 0,
                'compliant_sections' => 0,
                'total_sections' => 0
            ]
        ];

        if ($contract) {
            $contractValidation = $this->validateContract($contract);
            $report['sections']['contract'] = $contractValidation;
            $report['summary']['total_errors'] += count($contractValidation['errors']);
            $report['summary']['total_warnings'] += count($contractValidation['warnings']);
            $report['summary']['total_sections']++;
            
            if ($contractValidation['is_compliant']) {
                $report['summary']['compliant_sections']++;
            } else {
                $report['overall_compliance'] = false;
            }
        }

        if ($program) {
            $programValidation = $this->validateProgram($program);
            $report['sections']['program'] = $programValidation;
            $report['summary']['total_errors'] += count($programValidation['errors']);
            $report['summary']['total_warnings'] += count($programValidation['warnings']);
            $report['summary']['total_sections']++;
            
            if ($programValidation['is_compliant']) {
                $report['summary']['compliant_sections']++;
            } else {
                $report['overall_compliance'] = false;
            }
        }

        if ($session) {
            $sessionValidation = $this->validateSessionForAlternance($session);
            $report['sections']['session'] = $sessionValidation;
            $report['summary']['total_errors'] += count($sessionValidation['errors']);
            $report['summary']['total_warnings'] += count($sessionValidation['warnings']);
            $report['summary']['total_sections']++;
            
            if ($sessionValidation['is_compliant']) {
                $report['summary']['compliant_sections']++;
            } else {
                $report['overall_compliance'] = false;
            }
        }

        // Calculate compliance percentage
        $report['summary']['compliance_percentage'] = $report['summary']['total_sections'] > 0 
            ? round(($report['summary']['compliant_sections'] / $report['summary']['total_sections']) * 100, 1)
            : 0;

        return $report;
    }

    /**
     * Get Qualiopi requirements checklist for alternance
     *
     * @return array List of requirements with descriptions
     */
    public function getQualiopiRequirements(): array
    {
        return [
            '2.1' => [
                'title' => 'Objectifs pédagogiques',
                'description' => 'Les objectifs pédagogiques et professionnels doivent être clairement définis.',
                'items' => [
                    'Objectifs d\'apprentissage formalisés',
                    'Objectifs en entreprise définis',
                    'Progression pédagogique structurée'
                ]
            ],
            '2.2' => [
                'title' => 'Modalités pédagogiques',
                'description' => 'Les modalités de mise en œuvre de l\'alternance doivent être précisées.',
                'items' => [
                    'Répartition temps centre/entreprise',
                    'Rythme d\'alternance défini',
                    'Cohérence des durées'
                ]
            ],
            '2.3' => [
                'title' => 'Durée et organisation',
                'description' => 'La durée et l\'organisation doivent respecter la réglementation.',
                'items' => [
                    'Durée minimale respectée',
                    'Volume horaire adapté',
                    'Planning défini'
                ]
            ],
            '2.4' => [
                'title' => 'Encadrement et suivi',
                'description' => 'L\'encadrement et le suivi pédagogique doivent être organisés.',
                'items' => [
                    'Tuteur entreprise désigné',
                    'Référent pédagogique assigné',
                    'Points de coordination réguliers'
                ]
            ],
            '2.5' => [
                'title' => 'Évaluation',
                'description' => 'Les modalités d\'évaluation doivent être définies.',
                'items' => [
                    'Périodes d\'évaluation planifiées',
                    'Critères d\'évaluation explicites',
                    'Outils d\'évaluation adaptés'
                ]
            ]
        ];
    }

    /**
     * Check if contract meets legal minimum requirements
     *
     * @param AlternanceContract $contract
     * @return bool
     */
    public function meetsLegalMinimums(AlternanceContract $contract): bool
    {
        // Check SIRET format
        if (!preg_match('/^\d{14}$/', $contract->getCompanySiret())) {
            return false;
        }

        // Check duration minimums
        $weeks = $contract->getDurationInWeeks();
        if ($contract->getContractType() === 'apprentissage' && $weeks < 26) {
            return false;
        }

        if ($contract->getContractType() === 'professionnalisation' && $weeks < 12) {
            return false;
        }

        // Check supervision
        if (!$contract->getMentor() || !$contract->getPedagogicalSupervisor()) {
            return false;
        }

        // Check weekly hours
        $totalHours = $contract->getTotalWeeklyHours();
        if ($totalHours < 20 || $totalHours > 35) {
            return false;
        }

        return true;
    }
}
