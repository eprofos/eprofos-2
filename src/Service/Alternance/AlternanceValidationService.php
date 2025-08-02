<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Entity\Alternance\AlternanceProgram;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service for validating Qualiopi compliance for alternance.
 *
 * Ensures that alternance contracts and programs meet
 * French quality standards for professional training.
 */
class AlternanceValidationService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * Validate contract for Qualiopi compliance.
     *
     * @return array Array of validation results with errors and warnings
     */
    public function validateContract(AlternanceContract $contract): array
    {
        $this->logger->info('Starting contract validation for Qualiopi compliance', [
            'contract_id' => $contract->getId(),
            'contract_type' => $contract->getContractType(),
            'company_siret' => $contract->getCompanySiret(),
            'student_id' => $contract->getStudent()?->getId(),
        ]);

        try {
            $errors = [];
            $warnings = [];

            $this->logger->debug('Validating mandatory fields for contract', [
                'contract_id' => $contract->getId(),
            ]);

            // Check mandatory fields
            if (!$contract->getCompanySiret()) {
                $error = 'Le SIRET de l\'entreprise est obligatoire pour la conformité Qualiopi.';
                $errors[] = $error;
                $this->logger->warning('Contract validation failed: Missing company SIRET', [
                    'contract_id' => $contract->getId(),
                    'error' => $error,
                ]);
            } else {
                $this->logger->debug('Company SIRET validation passed', [
                    'contract_id' => $contract->getId(),
                    'siret' => $contract->getCompanySiret(),
                ]);
            }

            if (empty($contract->getLearningObjectives())) {
                $error = 'Les objectifs pédagogiques sont obligatoires (Qualiopi 2.1).';
                $errors[] = $error;
                $this->logger->warning('Contract validation failed: Missing learning objectives', [
                    'contract_id' => $contract->getId(),
                    'error' => $error,
                    'qualiopi_ref' => '2.1',
                ]);
            } else {
                $this->logger->debug('Learning objectives validation passed', [
                    'contract_id' => $contract->getId(),
                    'objectives_count' => count($contract->getLearningObjectives()),
                ]);
            }

            if (empty($contract->getCompanyObjectives())) {
                $error = 'Les objectifs en entreprise sont obligatoires (Qualiopi 2.1).';
                $errors[] = $error;
                $this->logger->warning('Contract validation failed: Missing company objectives', [
                    'contract_id' => $contract->getId(),
                    'error' => $error,
                    'qualiopi_ref' => '2.1',
                ]);
            } else {
                $this->logger->debug('Company objectives validation passed', [
                    'contract_id' => $contract->getId(),
                    'objectives_count' => count($contract->getCompanyObjectives()),
                ]);
            }

            $this->logger->debug('Validating contract duration compliance', [
                'contract_id' => $contract->getId(),
                'contract_type' => $contract->getContractType(),
            ]);

            // Check contract duration
            try {
                $durationWeeks = $contract->getDurationInWeeks();
                $this->logger->debug('Contract duration calculated', [
                    'contract_id' => $contract->getId(),
                    'duration_weeks' => $durationWeeks,
                    'contract_type' => $contract->getContractType(),
                ]);

                if ($contract->getContractType() === 'apprentissage' && $durationWeeks < 26) {
                    $error = 'Un contrat d\'apprentissage doit durer au minimum 6 mois (Qualiopi 2.3).';
                    $errors[] = $error;
                    $this->logger->error('Contract validation failed: Apprentissage duration too short', [
                        'contract_id' => $contract->getId(),
                        'duration_weeks' => $durationWeeks,
                        'minimum_required' => 26,
                        'error' => $error,
                        'qualiopi_ref' => '2.3',
                    ]);
                }

                if ($contract->getContractType() === 'professionnalisation' && $durationWeeks < 12) {
                    $error = 'Un contrat de professionnalisation doit durer au minimum 3 mois (Qualiopi 2.3).';
                    $errors[] = $error;
                    $this->logger->error('Contract validation failed: Professionnalisation duration too short', [
                        'contract_id' => $contract->getId(),
                        'duration_weeks' => $durationWeeks,
                        'minimum_required' => 12,
                        'error' => $error,
                        'qualiopi_ref' => '2.3',
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Erreur lors du calcul de la durée du contrat.';
                $errors[] = $error;
                $this->logger->error('Error calculating contract duration', [
                    'contract_id' => $contract->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating weekly hours distribution', [
                'contract_id' => $contract->getId(),
            ]);

            // Check weekly hours distribution
            try {
                $totalHours = $contract->getTotalWeeklyHours();
                $this->logger->debug('Weekly hours calculated', [
                    'contract_id' => $contract->getId(),
                    'total_weekly_hours' => $totalHours,
                    'center_hours' => $contract->getWeeklyCenterHours(),
                    'company_hours' => $contract->getWeeklyCompanyHours(),
                ]);

                if ($totalHours < 20) {
                    $warning = 'Le volume horaire hebdomadaire semble faible (moins de 20h).';
                    $warnings[] = $warning;
                    $this->logger->warning('Contract validation warning: Low weekly hours', [
                        'contract_id' => $contract->getId(),
                        'total_hours' => $totalHours,
                        'warning' => $warning,
                    ]);
                }

                if ($totalHours > 35) {
                    $error = 'Le volume horaire hebdomadaire ne peut pas dépasser 35h pour un alternant.';
                    $errors[] = $error;
                    $this->logger->error('Contract validation failed: Excessive weekly hours', [
                        'contract_id' => $contract->getId(),
                        'total_hours' => $totalHours,
                        'maximum_allowed' => 35,
                        'error' => $error,
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Erreur lors du calcul des heures hebdomadaires.';
                $errors[] = $error;
                $this->logger->error('Error calculating weekly hours', [
                    'contract_id' => $contract->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating supervision requirements', [
                'contract_id' => $contract->getId(),
            ]);

            // Check supervision
            if (!$contract->getMentor()) {
                $error = 'Un tuteur entreprise doit être désigné (Qualiopi 2.4).';
                $errors[] = $error;
                $this->logger->warning('Contract validation failed: Missing mentor', [
                    'contract_id' => $contract->getId(),
                    'error' => $error,
                    'qualiopi_ref' => '2.4',
                ]);
            } else {
                $this->logger->debug('Mentor validation passed', [
                    'contract_id' => $contract->getId(),
                    'mentor_id' => $contract->getMentor()->getId(),
                ]);
            }

            if (!$contract->getPedagogicalSupervisor()) {
                $error = 'Un référent pédagogique doit être désigné (Qualiopi 2.4).';
                $errors[] = $error;
                $this->logger->warning('Contract validation failed: Missing pedagogical supervisor', [
                    'contract_id' => $contract->getId(),
                    'error' => $error,
                    'qualiopi_ref' => '2.4',
                ]);
            } else {
                $this->logger->debug('Pedagogical supervisor validation passed', [
                    'contract_id' => $contract->getId(),
                    'supervisor_id' => $contract->getPedagogicalSupervisor()->getId(),
                ]);
            }

            $this->logger->debug('Validating job description quality', [
                'contract_id' => $contract->getId(),
            ]);

            // Check job description quality
            if ($contract->getJobDescription() && strlen($contract->getJobDescription()) < 100) {
                $warning = 'La description du poste pourrait être plus détaillée pour une meilleure traçabilité.';
                $warnings[] = $warning;
                $this->logger->info('Contract validation warning: Short job description', [
                    'contract_id' => $contract->getId(),
                    'description_length' => strlen($contract->getJobDescription()),
                    'recommended_minimum' => 100,
                    'warning' => $warning,
                ]);
            }

            // Check remuneration field
            if (!$contract->getRemuneration()) {
                $warning = 'La rémunération devrait être précisée pour la transparence.';
                $warnings[] = $warning;
                $this->logger->info('Contract validation warning: Missing remuneration', [
                    'contract_id' => $contract->getId(),
                    'warning' => $warning,
                ]);
            } else {
                $this->logger->debug('Remuneration field validation passed', [
                    'contract_id' => $contract->getId(),
                    'remuneration' => $contract->getRemuneration(),
                ]);
            }

            $result = [
                'errors' => $errors,
                'warnings' => $warnings,
                'is_compliant' => empty($errors),
            ];

            $this->logger->info('Contract validation completed', [
                'contract_id' => $contract->getId(),
                'is_compliant' => $result['is_compliant'],
                'errors_count' => count($errors),
                'warnings_count' => count($warnings),
                'validation_result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during contract validation', [
                'contract_id' => $contract->getId(),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                sprintf(
                    'Erreur inattendue lors de la validation du contrat %s: %s',
                    $contract->getId() ?: 'nouveau',
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }

    /**
     * Validate program for Qualiopi compliance.
     *
     * @return array Array of validation results with errors and warnings
     */
    public function validateProgram(AlternanceProgram $program): array
    {
        $this->logger->info('Starting program validation for Qualiopi compliance', [
            'program_id' => $program->getId(),
            'total_duration' => $program->getTotalDuration(),
        ]);

        try {
            $errors = [];
            $warnings = [];

            $this->logger->debug('Validating program duration consistency', [
                'program_id' => $program->getId(),
            ]);

            // Check duration consistency
            try {
                if (!$program->hasConsistentDurations()) {
                    $error = 'La répartition des durées centre/entreprise doit être cohérente (Qualiopi 2.2).';
                    $errors[] = $error;
                    $this->logger->error('Program validation failed: Inconsistent durations', [
                        'program_id' => $program->getId(),
                        'error' => $error,
                        'qualiopi_ref' => '2.2',
                    ]);
                } else {
                    $this->logger->debug('Duration consistency validation passed', [
                        'program_id' => $program->getId(),
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de la vérification de la cohérence des durées.';
                $errors[] = $error;
                $this->logger->error('Error checking duration consistency', [
                    'program_id' => $program->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating minimum program duration', [
                'program_id' => $program->getId(),
            ]);

            // Check minimum program duration
            try {
                $totalDuration = $program->getTotalDuration();
                if ($totalDuration < 26) {
                    $warning = 'La durée totale du programme semble courte pour un parcours d\'alternance.';
                    $warnings[] = $warning;
                    $this->logger->warning('Program validation warning: Short total duration', [
                        'program_id' => $program->getId(),
                        'total_duration' => $totalDuration,
                        'recommended_minimum' => 26,
                        'warning' => $warning,
                    ]);
                } else {
                    $this->logger->debug('Minimum duration validation passed', [
                        'program_id' => $program->getId(),
                        'total_duration' => $totalDuration,
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de la vérification de la durée totale du programme.';
                $errors[] = $error;
                $this->logger->error('Error checking total duration', [
                    'program_id' => $program->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating center/company balance', [
                'program_id' => $program->getId(),
            ]);

            // Check center/company balance
            try {
                $centerPercentage = $program->getCenterDurationPercentage();
                $this->logger->debug('Center duration percentage calculated', [
                    'program_id' => $program->getId(),
                    'center_percentage' => $centerPercentage,
                ]);

                if ($centerPercentage < 20) {
                    $warning = 'Le temps en centre de formation semble insuffisant (moins de 20%).';
                    $warnings[] = $warning;
                    $this->logger->warning('Program validation warning: Insufficient center time', [
                        'program_id' => $program->getId(),
                        'center_percentage' => $centerPercentage,
                        'minimum_recommended' => 20,
                        'warning' => $warning,
                    ]);
                }

                if ($centerPercentage > 80) {
                    $warning = 'Le temps en entreprise semble insuffisant (moins de 20%).';
                    $warnings[] = $warning;
                    $this->logger->warning('Program validation warning: Insufficient company time', [
                        'program_id' => $program->getId(),
                        'center_percentage' => $centerPercentage,
                        'company_percentage' => 100 - $centerPercentage,
                        'minimum_recommended' => 20,
                        'warning' => $warning,
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Erreur lors du calcul de la répartition centre/entreprise.';
                $errors[] = $error;
                $this->logger->error('Error calculating center/company balance', [
                    'program_id' => $program->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating program structure requirements', [
                'program_id' => $program->getId(),
            ]);

            // Check program structure
            try {
                if (!$program->hasCenterModules()) {
                    $error = 'Des modules en centre de formation doivent être définis (Qualiopi 2.1).';
                    $errors[] = $error;
                    $this->logger->error('Program validation failed: Missing center modules', [
                        'program_id' => $program->getId(),
                        'error' => $error,
                        'qualiopi_ref' => '2.1',
                    ]);
                } else {
                    $this->logger->debug('Center modules validation passed', [
                        'program_id' => $program->getId(),
                    ]);
                }

                if (!$program->hasCompanyModules()) {
                    $error = 'Des modules en entreprise doivent être définis (Qualiopi 2.1).';
                    $errors[] = $error;
                    $this->logger->error('Program validation failed: Missing company modules', [
                        'program_id' => $program->getId(),
                        'error' => $error,
                        'qualiopi_ref' => '2.1',
                    ]);
                } else {
                    $this->logger->debug('Company modules validation passed', [
                        'program_id' => $program->getId(),
                    ]);
                }

                if (!$program->hasCoordinationPoints()) {
                    $error = 'Des points de coordination doivent être prévus (Qualiopi 2.4).';
                    $errors[] = $error;
                    $this->logger->error('Program validation failed: Missing coordination points', [
                        'program_id' => $program->getId(),
                        'error' => $error,
                        'qualiopi_ref' => '2.4',
                    ]);
                } else {
                    $this->logger->debug('Coordination points validation passed', [
                        'program_id' => $program->getId(),
                    ]);
                }

                if (!$program->hasAssessmentPeriods()) {
                    $error = 'Des périodes d\'évaluation doivent être définies (Qualiopi 2.5).';
                    $errors[] = $error;
                    $this->logger->error('Program validation failed: Missing assessment periods', [
                        'program_id' => $program->getId(),
                        'error' => $error,
                        'qualiopi_ref' => '2.5',
                    ]);
                } else {
                    $this->logger->debug('Assessment periods validation passed', [
                        'program_id' => $program->getId(),
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de la vérification de la structure du programme.';
                $errors[] = $error;
                $this->logger->error('Error checking program structure', [
                    'program_id' => $program->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating coordination frequency', [
                'program_id' => $program->getId(),
            ]);

            // Check coordination frequency
            if ($program->hasCoordinationPoints()) {
                try {
                    $coordinationPoints = $program->getCoordinationPoints();
                    $hasRegularCoordination = false;

                    foreach ($coordinationPoints as $point) {
                        if (isset($point['frequency'])
                            && in_array(strtolower($point['frequency']), ['mensuelle', 'bimensuelle', 'hebdomadaire'], true)) {
                            $hasRegularCoordination = true;
                            break;
                        }
                    }

                    if (!$hasRegularCoordination) {
                        $warning = 'Une coordination régulière (au moins mensuelle) est recommandée (Qualiopi 2.4).';
                        $warnings[] = $warning;
                        $this->logger->warning('Program validation warning: Irregular coordination', [
                            'program_id' => $program->getId(),
                            'coordination_points_count' => count($coordinationPoints),
                            'warning' => $warning,
                            'qualiopi_ref' => '2.4',
                        ]);
                    } else {
                        $this->logger->debug('Coordination frequency validation passed', [
                            'program_id' => $program->getId(),
                            'has_regular_coordination' => true,
                        ]);
                    }
                } catch (Exception $e) {
                    $warning = 'Erreur lors de la vérification de la fréquence de coordination.';
                    $warnings[] = $warning;
                    $this->logger->error('Error checking coordination frequency', [
                        'program_id' => $program->getId(),
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ]);
                }
            }

            $this->logger->debug('Validating learning progression', [
                'program_id' => $program->getId(),
            ]);

            // Check learning progression
            try {
                if (empty($program->getLearningProgression())) {
                    $warning = 'Une progression pédagogique structurée améliore la traçabilité (Qualiopi 2.2).';
                    $warnings[] = $warning;
                    $this->logger->info('Program validation warning: Missing learning progression', [
                        'program_id' => $program->getId(),
                        'warning' => $warning,
                        'qualiopi_ref' => '2.2',
                    ]);
                } else {
                    $this->logger->debug('Learning progression validation passed', [
                        'program_id' => $program->getId(),
                        'progression_items_count' => count($program->getLearningProgression()),
                    ]);
                }
            } catch (Exception $e) {
                $warning = 'Erreur lors de la vérification de la progression pédagogique.';
                $warnings[] = $warning;
                $this->logger->error('Error checking learning progression', [
                    'program_id' => $program->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $result = [
                'errors' => $errors,
                'warnings' => $warnings,
                'is_compliant' => empty($errors),
            ];

            $this->logger->info('Program validation completed', [
                'program_id' => $program->getId(),
                'is_compliant' => $result['is_compliant'],
                'errors_count' => count($errors),
                'warnings_count' => count($warnings),
                'validation_result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during program validation', [
                'program_id' => $program->getId(),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                sprintf(
                    'Erreur inattendue lors de la validation du programme %s: %s',
                    $program->getId() ?: 'nouveau',
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }

    /**
     * Validate session for alternance.
     *
     * @param object $session Session entity
     *
     * @return array Array of validation results
     */
    public function validateSessionForAlternance($session): array
    {
        $this->logger->info('Starting session validation for alternance', [
            'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
            'session_class' => get_class($session),
        ]);

        try {
            $errors = [];
            $warnings = [];

            $this->logger->debug('Checking if session is configured for alternance', [
                'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
            ]);

            if (!$session->isAlternanceSession()) {
                $error = 'La session doit être configurée en mode alternance.';
                $errors[] = $error;
                $this->logger->error('Session validation failed: Not configured for alternance', [
                    'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                    'error' => $error,
                ]);

                $result = ['errors' => $errors, 'warnings' => $warnings, 'is_compliant' => false];

                $this->logger->warning('Session validation terminated early due to alternance configuration', [
                    'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                    'validation_result' => $result,
                ]);

                return $result;
            }

            $this->logger->debug('Validating alternance type specification', [
                'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
            ]);

            // Check alternance type
            try {
                if (!$session->getAlternanceType()) {
                    $error = 'Le type d\'alternance doit être spécifié (apprentissage ou professionnalisation).';
                    $errors[] = $error;
                    $this->logger->warning('Session validation failed: Missing alternance type', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'error' => $error,
                    ]);
                } else {
                    $this->logger->debug('Alternance type validation passed', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'alternance_type' => $session->getAlternanceType(),
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de la vérification du type d\'alternance.';
                $errors[] = $error;
                $this->logger->error('Error checking alternance type', [
                    'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating minimum alternance duration', [
                'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
            ]);

            // Check minimum duration
            try {
                if ($session->getMinimumAlternanceDuration() && $session->getMinimumAlternanceDuration() < 12) {
                    $warning = 'La durée minimale semble courte pour un parcours d\'alternance.';
                    $warnings[] = $warning;
                    $this->logger->warning('Session validation warning: Short minimum duration', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'minimum_duration' => $session->getMinimumAlternanceDuration(),
                        'recommended_minimum' => 12,
                        'warning' => $warning,
                    ]);
                } else {
                    $this->logger->debug('Minimum duration validation passed', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'minimum_duration' => $session->getMinimumAlternanceDuration(),
                    ]);
                }
            } catch (Exception $e) {
                $warning = 'Erreur lors de la vérification de la durée minimale.';
                $warnings[] = $warning;
                $this->logger->error('Error checking minimum duration', [
                    'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating alternance percentages consistency', [
                'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
            ]);

            // Check percentage consistency
            try {
                if (!$session->hasValidAlternancePercentages()) {
                    $error = 'Les pourcentages centre/entreprise doivent totaliser 100%.';
                    $errors[] = $error;
                    $this->logger->error('Session validation failed: Invalid alternance percentages', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'error' => $error,
                    ]);
                } else {
                    $this->logger->debug('Alternance percentages validation passed', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de la vérification des pourcentages d\'alternance.';
                $errors[] = $error;
                $this->logger->error('Error checking alternance percentages', [
                    'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating alternance rhythm definition', [
                'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
            ]);

            // Check rhythm definition
            try {
                if (!$session->getAlternanceRhythm()) {
                    $warning = 'Le rythme d\'alternance devrait être précisé.';
                    $warnings[] = $warning;
                    $this->logger->info('Session validation warning: Missing alternance rhythm', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'warning' => $warning,
                    ]);
                } else {
                    $this->logger->debug('Alternance rhythm validation passed', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'rhythm' => $session->getAlternanceRhythm(),
                    ]);
                }
            } catch (Exception $e) {
                $warning = 'Erreur lors de la vérification du rythme d\'alternance.';
                $warnings[] = $warning;
                $this->logger->error('Error checking alternance rhythm', [
                    'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $this->logger->debug('Validating alternance prerequisites', [
                'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
            ]);

            // Check prerequisites
            try {
                $prerequisites = $session->getFormattedAlternancePrerequisites();
                if (empty($prerequisites)) {
                    $warning = 'Des prérequis spécifiques à l\'alternance pourraient être définis.';
                    $warnings[] = $warning;
                    $this->logger->info('Session validation warning: Missing alternance prerequisites', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'warning' => $warning,
                    ]);
                } else {
                    $this->logger->debug('Alternance prerequisites validation passed', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'prerequisites_count' => count($prerequisites),
                    ]);
                }
            } catch (Exception $e) {
                $warning = 'Erreur lors de la vérification des prérequis d\'alternance.';
                $warnings[] = $warning;
                $this->logger->error('Error checking alternance prerequisites', [
                    'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);
            }

            $result = [
                'errors' => $errors,
                'warnings' => $warnings,
                'is_compliant' => empty($errors),
            ];

            $this->logger->info('Session validation completed', [
                'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                'is_compliant' => $result['is_compliant'],
                'errors_count' => count($errors),
                'warnings_count' => count($warnings),
                'validation_result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during session validation for alternance', [
                'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                'session_class' => get_class($session),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                sprintf(
                    'Erreur inattendue lors de la validation de la session pour l\'alternance: %s',
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }

    /**
     * Generate comprehensive validation report.
     *
     * @return array Complete validation report
     */
    public function generateValidationReport(
        ?AlternanceContract $contract = null,
        ?AlternanceProgram $program = null,
        ?object $session = null,
    ): array {
        $this->logger->info('Starting comprehensive validation report generation', [
            'has_contract' => $contract !== null,
            'has_program' => $program !== null,
            'has_session' => $session !== null,
            'contract_id' => $contract?->getId(),
            'program_id' => $program?->getId(),
            'session_id' => method_exists($session, 'getId') ? $session?->getId() : 'unknown',
        ]);

        try {
            $report = [
                'overall_compliance' => true,
                'sections' => [],
                'summary' => [
                    'total_errors' => 0,
                    'total_warnings' => 0,
                    'compliant_sections' => 0,
                    'total_sections' => 0,
                ],
            ];

            if ($contract) {
                $this->logger->debug('Validating contract for comprehensive report', [
                    'contract_id' => $contract->getId(),
                ]);

                try {
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

                    $this->logger->debug('Contract validation completed for report', [
                        'contract_id' => $contract->getId(),
                        'is_compliant' => $contractValidation['is_compliant'],
                        'errors_count' => count($contractValidation['errors']),
                        'warnings_count' => count($contractValidation['warnings']),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error during contract validation in comprehensive report', [
                        'contract_id' => $contract->getId(),
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ]);

                    $report['sections']['contract'] = [
                        'errors' => ['Erreur lors de la validation du contrat: ' . $e->getMessage()],
                        'warnings' => [],
                        'is_compliant' => false,
                    ];
                    $report['summary']['total_errors']++;
                    $report['summary']['total_sections']++;
                    $report['overall_compliance'] = false;
                }
            }

            if ($program) {
                $this->logger->debug('Validating program for comprehensive report', [
                    'program_id' => $program->getId(),
                ]);

                try {
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

                    $this->logger->debug('Program validation completed for report', [
                        'program_id' => $program->getId(),
                        'is_compliant' => $programValidation['is_compliant'],
                        'errors_count' => count($programValidation['errors']),
                        'warnings_count' => count($programValidation['warnings']),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error during program validation in comprehensive report', [
                        'program_id' => $program->getId(),
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ]);

                    $report['sections']['program'] = [
                        'errors' => ['Erreur lors de la validation du programme: ' . $e->getMessage()],
                        'warnings' => [],
                        'is_compliant' => false,
                    ];
                    $report['summary']['total_errors']++;
                    $report['summary']['total_sections']++;
                    $report['overall_compliance'] = false;
                }
            }

            if ($session) {
                $this->logger->debug('Validating session for comprehensive report', [
                    'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                    'session_class' => get_class($session),
                ]);

                try {
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

                    $this->logger->debug('Session validation completed for report', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'is_compliant' => $sessionValidation['is_compliant'],
                        'errors_count' => count($sessionValidation['errors']),
                        'warnings_count' => count($sessionValidation['warnings']),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error during session validation in comprehensive report', [
                        'session_id' => method_exists($session, 'getId') ? $session->getId() : 'unknown',
                        'session_class' => get_class($session),
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ]);

                    $report['sections']['session'] = [
                        'errors' => ['Erreur lors de la validation de la session: ' . $e->getMessage()],
                        'warnings' => [],
                        'is_compliant' => false,
                    ];
                    $report['summary']['total_errors']++;
                    $report['summary']['total_sections']++;
                    $report['overall_compliance'] = false;
                }
            }

            // Calculate compliance percentage
            $report['summary']['compliance_percentage'] = $report['summary']['total_sections'] > 0
                ? round(($report['summary']['compliant_sections'] / $report['summary']['total_sections']) * 100, 1)
                : 0;

            $this->logger->info('Comprehensive validation report generated successfully', [
                'overall_compliance' => $report['overall_compliance'],
                'compliance_percentage' => $report['summary']['compliance_percentage'],
                'total_sections' => $report['summary']['total_sections'],
                'compliant_sections' => $report['summary']['compliant_sections'],
                'total_errors' => $report['summary']['total_errors'],
                'total_warnings' => $report['summary']['total_warnings'],
                'sections_validated' => array_keys($report['sections']),
            ]);

            return $report;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during comprehensive validation report generation', [
                'contract_id' => $contract?->getId(),
                'program_id' => $program?->getId(),
                'session_id' => method_exists($session, 'getId') ? $session?->getId() : 'unknown',
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                sprintf(
                    'Erreur inattendue lors de la génération du rapport de validation: %s',
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }

    /**
     * Get Qualiopi requirements checklist for alternance.
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
                    'Progression pédagogique structurée',
                ],
            ],
            '2.2' => [
                'title' => 'Modalités pédagogiques',
                'description' => 'Les modalités de mise en œuvre de l\'alternance doivent être précisées.',
                'items' => [
                    'Répartition temps centre/entreprise',
                    'Rythme d\'alternance défini',
                    'Cohérence des durées',
                ],
            ],
            '2.3' => [
                'title' => 'Durée et organisation',
                'description' => 'La durée et l\'organisation doivent respecter la réglementation.',
                'items' => [
                    'Durée minimale respectée',
                    'Volume horaire adapté',
                    'Planning défini',
                ],
            ],
            '2.4' => [
                'title' => 'Encadrement et suivi',
                'description' => 'L\'encadrement et le suivi pédagogique doivent être organisés.',
                'items' => [
                    'Tuteur entreprise désigné',
                    'Référent pédagogique assigné',
                    'Points de coordination réguliers',
                ],
            ],
            '2.5' => [
                'title' => 'Évaluation',
                'description' => 'Les modalités d\'évaluation doivent être définies.',
                'items' => [
                    'Périodes d\'évaluation planifiées',
                    'Critères d\'évaluation explicites',
                    'Outils d\'évaluation adaptés',
                ],
            ],
        ];
    }

    /**
     * Check if contract meets legal minimum requirements.
     */
    public function meetsLegalMinimums(AlternanceContract $contract): bool
    {
        $this->logger->info('Checking legal minimum requirements for contract', [
            'contract_id' => $contract->getId(),
            'contract_type' => $contract->getContractType(),
            'company_siret' => $contract->getCompanySiret(),
        ]);

        try {
            // Check SIRET format
            $siret = $contract->getCompanySiret();
            $this->logger->debug('Validating SIRET format', [
                'contract_id' => $contract->getId(),
                'siret' => $siret,
            ]);

            if (!preg_match('/^\d{14}$/', $siret)) {
                $this->logger->warning('Legal minimum check failed: Invalid SIRET format', [
                    'contract_id' => $contract->getId(),
                    'siret' => $siret,
                    'expected_format' => '14 digits',
                ]);

                return false;
            }

            // Check duration minimums
            try {
                $weeks = $contract->getDurationInWeeks();
                $contractType = $contract->getContractType();

                $this->logger->debug('Validating duration minimums', [
                    'contract_id' => $contract->getId(),
                    'duration_weeks' => $weeks,
                    'contract_type' => $contractType,
                ]);

                if ($contractType === 'apprentissage' && $weeks < 26) {
                    $this->logger->warning('Legal minimum check failed: Apprentissage duration too short', [
                        'contract_id' => $contract->getId(),
                        'duration_weeks' => $weeks,
                        'minimum_required' => 26,
                        'contract_type' => $contractType,
                    ]);

                    return false;
                }

                if ($contractType === 'professionnalisation' && $weeks < 12) {
                    $this->logger->warning('Legal minimum check failed: Professionnalisation duration too short', [
                        'contract_id' => $contract->getId(),
                        'duration_weeks' => $weeks,
                        'minimum_required' => 12,
                        'contract_type' => $contractType,
                    ]);

                    return false;
                }
            } catch (Exception $e) {
                $this->logger->error('Error calculating contract duration for legal minimums', [
                    'contract_id' => $contract->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);

                return false;
            }

            // Check supervision
            $mentor = $contract->getMentor();
            $pedagogicalSupervisor = $contract->getPedagogicalSupervisor();

            $this->logger->debug('Validating supervision requirements', [
                'contract_id' => $contract->getId(),
                'has_mentor' => $mentor !== null,
                'has_pedagogical_supervisor' => $pedagogicalSupervisor !== null,
                'mentor_id' => $mentor?->getId(),
                'supervisor_id' => $pedagogicalSupervisor?->getId(),
            ]);

            if (!$mentor || !$pedagogicalSupervisor) {
                $this->logger->warning('Legal minimum check failed: Missing supervision', [
                    'contract_id' => $contract->getId(),
                    'has_mentor' => $mentor !== null,
                    'has_pedagogical_supervisor' => $pedagogicalSupervisor !== null,
                ]);

                return false;
            }

            // Check weekly hours
            try {
                $totalHours = $contract->getTotalWeeklyHours();

                $this->logger->debug('Validating weekly hours requirements', [
                    'contract_id' => $contract->getId(),
                    'total_weekly_hours' => $totalHours,
                    'center_hours' => $contract->getWeeklyCenterHours(),
                    'company_hours' => $contract->getWeeklyCompanyHours(),
                ]);

                if ($totalHours < 20 || $totalHours > 35) {
                    $this->logger->warning('Legal minimum check failed: Invalid weekly hours', [
                        'contract_id' => $contract->getId(),
                        'total_hours' => $totalHours,
                        'minimum_required' => 20,
                        'maximum_allowed' => 35,
                    ]);

                    return false;
                }
            } catch (Exception $e) {
                $this->logger->error('Error calculating weekly hours for legal minimums', [
                    'contract_id' => $contract->getId(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);

                return false;
            }

            $this->logger->info('Legal minimum requirements check passed', [
                'contract_id' => $contract->getId(),
                'contract_type' => $contract->getContractType(),
                'duration_weeks' => $weeks,
                'total_weekly_hours' => $totalHours,
                'has_complete_supervision' => true,
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during legal minimums check', [
                'contract_id' => $contract->getId(),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // For legal compliance checks, we should be conservative and return false on errors
            return false;
        }
    }
}
