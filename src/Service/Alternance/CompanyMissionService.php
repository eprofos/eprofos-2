<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\CompanyMission;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Repository\Alternance\CompanyMissionRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service for managing company missions.
 *
 * Handles CRUD operations, progression logic, and business rules
 * for company missions in the alternance system.
 */
class CompanyMissionService
{
    private EntityManagerInterface $entityManager;

    private CompanyMissionRepository $missionRepository;

    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        CompanyMissionRepository $missionRepository,
        LoggerInterface $logger,
    ) {
        $this->entityManager = $entityManager;
        $this->missionRepository = $missionRepository;
        $this->logger = $logger;
    }

    /**
     * Create a new company mission.
     */
    public function createMission(array $data, Mentor $supervisor): CompanyMission
    {
        $this->logger->info('Starting mission creation process', [
            'supervisor_id' => $supervisor->getId(),
            'supervisor_name' => $supervisor->getFullName(),
            'data_keys' => array_keys($data),
            'data_size' => count($data),
        ]);

        try {
            // Validate input data first
            $this->logger->debug('Validating mission data', [
                'title' => $data['title'] ?? 'not_provided',
                'complexity' => $data['complexity'] ?? 'not_provided',
                'term' => $data['term'] ?? 'not_provided',
                'department' => $data['department'] ?? 'not_provided',
            ]);

            $validationErrors = $this->validateMissionData($data);
            if (!empty($validationErrors)) {
                $this->logger->error('Mission data validation failed', [
                    'errors' => $validationErrors,
                    'provided_data' => array_keys($data),
                    'supervisor_id' => $supervisor->getId(),
                ]);

                throw new RuntimeException('Validation failed: ' . implode(', ', $validationErrors));
            }

            $this->logger->debug('Mission data validation successful');

            $mission = new CompanyMission();
            $this->logger->debug('Created new CompanyMission entity', [
                'entity_id' => spl_object_id($mission),
            ]);

            // Set supervisor
            $mission->setSupervisor($supervisor);
            $this->logger->debug('Set mission supervisor', [
                'supervisor_id' => $supervisor->getId(),
                'supervisor_name' => $supervisor->getFullName(),
            ]);

            // Set basic properties with detailed logging
            $mission->setTitle($data['title']);
            $this->logger->debug('Set mission title', ['title' => $data['title']]);

            $mission->setDescription($data['description']);
            $this->logger->debug('Set mission description', ['description_length' => strlen($data['description'])]);

            $mission->setContext($data['context']);
            $this->logger->debug('Set mission context', ['context_length' => strlen($data['context'])]);

            $mission->setDuration($data['duration']);
            $this->logger->debug('Set mission duration', ['duration' => $data['duration']]);

            $mission->setComplexity($data['complexity']);
            $this->logger->debug('Set mission complexity', ['complexity' => $data['complexity']]);

            $mission->setTerm($data['term']);
            $this->logger->debug('Set mission term', ['term' => $data['term']]);

            $mission->setDepartment($data['department']);
            $this->logger->debug('Set mission department', ['department' => $data['department']]);

            // Set arrays with detailed logging
            $objectives = $data['objectives'] ?? [];
            $mission->setObjectives($objectives);
            $this->logger->debug('Set mission objectives', [
                'objectives_count' => count($objectives),
                'objectives' => $objectives,
            ]);

            $requiredSkills = $data['requiredSkills'] ?? [];
            $mission->setRequiredSkills($requiredSkills);
            $this->logger->debug('Set required skills', [
                'required_skills_count' => count($requiredSkills),
                'required_skills' => $requiredSkills,
            ]);

            $skillsToAcquire = $data['skillsToAcquire'] ?? [];
            $mission->setSkillsToAcquire($skillsToAcquire);
            $this->logger->debug('Set skills to acquire', [
                'skills_to_acquire_count' => count($skillsToAcquire),
                'skills_to_acquire' => $skillsToAcquire,
            ]);

            $prerequisites = $data['prerequisites'] ?? [];
            $mission->setPrerequisites($prerequisites);
            $this->logger->debug('Set prerequisites', [
                'prerequisites_count' => count($prerequisites),
                'prerequisites' => $prerequisites,
            ]);

            $evaluationCriteria = $data['evaluationCriteria'] ?? [];
            $mission->setEvaluationCriteria($evaluationCriteria);
            $this->logger->debug('Set evaluation criteria', [
                'evaluation_criteria_count' => count($evaluationCriteria),
                'evaluation_criteria' => $evaluationCriteria,
            ]);

            // Auto-calculate order index
            if (!isset($data['orderIndex'])) {
                $this->logger->debug('Calculating next order index', [
                    'term' => $data['term'],
                    'complexity' => $data['complexity'],
                ]);

                $orderIndex = $this->missionRepository->getNextOrderIndex($data['term'], $data['complexity']);
                $mission->setOrderIndex($orderIndex);

                $this->logger->debug('Set auto-calculated order index', [
                    'order_index' => $orderIndex,
                    'term' => $data['term'],
                    'complexity' => $data['complexity'],
                ]);
            } else {
                $mission->setOrderIndex($data['orderIndex']);
                $this->logger->debug('Set provided order index', [
                    'order_index' => $data['orderIndex'],
                ]);
            }

            // Persist and flush
            $this->logger->debug('Persisting mission entity');
            $this->entityManager->persist($mission);

            $this->logger->debug('Flushing entity manager');
            $this->entityManager->flush();

            $this->logger->info('Company mission created successfully', [
                'mission_id' => $mission->getId(),
                'title' => $mission->getTitle(),
                'supervisor_id' => $supervisor->getId(),
                'supervisor_name' => $supervisor->getFullName(),
                'complexity' => $mission->getComplexity(),
                'term' => $mission->getTerm(),
                'department' => $mission->getDepartment(),
                'order_index' => $mission->getOrderIndex(),
                'objectives_count' => count($mission->getObjectives()),
                'skills_count' => count($mission->getSkillsToAcquire()),
                'duration' => $mission->getDuration(),
                'created_at' => $mission->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

            return $mission;
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime exception during mission creation', [
                'error_message' => $e->getMessage(),
                'supervisor_id' => $supervisor->getId(),
                'data_provided' => array_keys($data),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected exception during mission creation', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'supervisor_id' => $supervisor->getId(),
                'data_provided' => array_keys($data),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to create mission: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update an existing mission.
     */
    public function updateMission(CompanyMission $mission, array $data): CompanyMission
    {
        $this->logger->info('Starting mission update process', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
            'data_keys' => array_keys($data),
            'data_size' => count($data),
        ]);

        try {
            $originalData = [
                'title' => $mission->getTitle(),
                'complexity' => $mission->getComplexity(),
                'term' => $mission->getTerm(),
                'department' => $mission->getDepartment(),
                'duration' => $mission->getDuration(),
            ];

            $this->logger->debug('Captured original mission data', [
                'mission_id' => $mission->getId(),
                'original_data' => $originalData,
            ]);

            // Validate input data if significant changes
            if (isset($data['title']) || isset($data['complexity']) || isset($data['term'])) {
                $this->logger->debug('Validating updated mission data');
                $validationData = array_merge($originalData, $data);
                $validationErrors = $this->validateMissionData($validationData);

                if (!empty($validationErrors)) {
                    $this->logger->error('Mission update validation failed', [
                        'mission_id' => $mission->getId(),
                        'errors' => $validationErrors,
                        'provided_updates' => array_keys($data),
                    ]);

                    throw new RuntimeException('Validation failed: ' . implode(', ', $validationErrors));
                }
                $this->logger->debug('Mission update validation successful');
            }

            // Update basic properties with detailed logging
            if (isset($data['title'])) {
                $oldTitle = $mission->getTitle();
                $mission->setTitle($data['title']);
                $this->logger->debug('Updated mission title', [
                    'mission_id' => $mission->getId(),
                    'old_title' => $oldTitle,
                    'new_title' => $data['title'],
                ]);
            }

            if (isset($data['description'])) {
                $oldDescriptionLength = strlen($mission->getDescription());
                $mission->setDescription($data['description']);
                $this->logger->debug('Updated mission description', [
                    'mission_id' => $mission->getId(),
                    'old_description_length' => $oldDescriptionLength,
                    'new_description_length' => strlen($data['description']),
                ]);
            }

            if (isset($data['context'])) {
                $oldContextLength = strlen($mission->getContext());
                $mission->setContext($data['context']);
                $this->logger->debug('Updated mission context', [
                    'mission_id' => $mission->getId(),
                    'old_context_length' => $oldContextLength,
                    'new_context_length' => strlen($data['context']),
                ]);
            }

            if (isset($data['duration'])) {
                $oldDuration = $mission->getDuration();
                $mission->setDuration($data['duration']);
                $this->logger->debug('Updated mission duration', [
                    'mission_id' => $mission->getId(),
                    'old_duration' => $oldDuration,
                    'new_duration' => $data['duration'],
                ]);
            }

            if (isset($data['department'])) {
                $oldDepartment = $mission->getDepartment();
                $mission->setDepartment($data['department']);
                $this->logger->debug('Updated mission department', [
                    'mission_id' => $mission->getId(),
                    'old_department' => $oldDepartment,
                    'new_department' => $data['department'],
                ]);
            }

            // Handle complexity/term changes that might affect order
            $needsReordering = false;
            if (isset($data['complexity']) && $data['complexity'] !== $mission->getComplexity()) {
                $oldComplexity = $mission->getComplexity();
                $mission->setComplexity($data['complexity']);
                $needsReordering = true;
                $this->logger->debug('Updated mission complexity', [
                    'mission_id' => $mission->getId(),
                    'old_complexity' => $oldComplexity,
                    'new_complexity' => $data['complexity'],
                    'needs_reordering' => true,
                ]);
            }

            if (isset($data['term']) && $data['term'] !== $mission->getTerm()) {
                $oldTerm = $mission->getTerm();
                $mission->setTerm($data['term']);
                $needsReordering = true;
                $this->logger->debug('Updated mission term', [
                    'mission_id' => $mission->getId(),
                    'old_term' => $oldTerm,
                    'new_term' => $data['term'],
                    'needs_reordering' => true,
                ]);
            }

            // Recalculate order index if needed
            if ($needsReordering && !isset($data['orderIndex'])) {
                $this->logger->debug('Recalculating order index due to complexity/term change', [
                    'mission_id' => $mission->getId(),
                    'term' => $mission->getTerm(),
                    'complexity' => $mission->getComplexity(),
                ]);

                $orderIndex = $this->missionRepository->getNextOrderIndex($mission->getTerm(), $mission->getComplexity());
                $oldOrderIndex = $mission->getOrderIndex();
                $mission->setOrderIndex($orderIndex);

                $this->logger->debug('Order index recalculated', [
                    'mission_id' => $mission->getId(),
                    'old_order_index' => $oldOrderIndex,
                    'new_order_index' => $orderIndex,
                ]);
            } elseif (isset($data['orderIndex'])) {
                $oldOrderIndex = $mission->getOrderIndex();
                $mission->setOrderIndex($data['orderIndex']);
                $this->logger->debug('Order index manually updated', [
                    'mission_id' => $mission->getId(),
                    'old_order_index' => $oldOrderIndex,
                    'new_order_index' => $data['orderIndex'],
                ]);
            }

            // Update arrays with detailed logging
            if (isset($data['objectives'])) {
                $oldObjectivesCount = count($mission->getObjectives());
                $mission->setObjectives($data['objectives']);
                $this->logger->debug('Updated mission objectives', [
                    'mission_id' => $mission->getId(),
                    'old_objectives_count' => $oldObjectivesCount,
                    'new_objectives_count' => count($data['objectives']),
                    'new_objectives' => $data['objectives'],
                ]);
            }

            if (isset($data['requiredSkills'])) {
                $oldSkillsCount = count($mission->getRequiredSkills());
                $mission->setRequiredSkills($data['requiredSkills']);
                $this->logger->debug('Updated required skills', [
                    'mission_id' => $mission->getId(),
                    'old_skills_count' => $oldSkillsCount,
                    'new_skills_count' => count($data['requiredSkills']),
                    'new_skills' => $data['requiredSkills'],
                ]);
            }

            if (isset($data['skillsToAcquire'])) {
                $oldSkillsToAcquireCount = count($mission->getSkillsToAcquire());
                $mission->setSkillsToAcquire($data['skillsToAcquire']);
                $this->logger->debug('Updated skills to acquire', [
                    'mission_id' => $mission->getId(),
                    'old_skills_to_acquire_count' => $oldSkillsToAcquireCount,
                    'new_skills_to_acquire_count' => count($data['skillsToAcquire']),
                    'new_skills_to_acquire' => $data['skillsToAcquire'],
                ]);
            }

            if (isset($data['prerequisites'])) {
                $oldPrerequisitesCount = count($mission->getPrerequisites());
                $mission->setPrerequisites($data['prerequisites']);
                $this->logger->debug('Updated prerequisites', [
                    'mission_id' => $mission->getId(),
                    'old_prerequisites_count' => $oldPrerequisitesCount,
                    'new_prerequisites_count' => count($data['prerequisites']),
                    'new_prerequisites' => $data['prerequisites'],
                ]);
            }

            if (isset($data['evaluationCriteria'])) {
                $oldCriteriaCount = count($mission->getEvaluationCriteria());
                $mission->setEvaluationCriteria($data['evaluationCriteria']);
                $this->logger->debug('Updated evaluation criteria', [
                    'mission_id' => $mission->getId(),
                    'old_criteria_count' => $oldCriteriaCount,
                    'new_criteria_count' => count($data['evaluationCriteria']),
                    'new_criteria' => $data['evaluationCriteria'],
                ]);
            }

            $this->logger->debug('Flushing entity manager for mission update');
            $this->entityManager->flush();

            $this->logger->info('Company mission updated successfully', [
                'mission_id' => $mission->getId(),
                'mission_title' => $mission->getTitle(),
                'original_data' => $originalData,
                'updated_fields' => array_keys($data),
                'needs_reordering' => $needsReordering,
                'final_complexity' => $mission->getComplexity(),
                'final_term' => $mission->getTerm(),
                'final_order_index' => $mission->getOrderIndex(),
            ]);

            return $mission;
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime exception during mission update', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'data_provided' => array_keys($data),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected exception during mission update', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'data_provided' => array_keys($data),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to update mission: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a mission (soft delete by setting inactive).
     */
    public function deleteMission(CompanyMission $mission): void
    {
        $this->logger->info('Starting mission deletion process', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
            'current_status' => $mission->isActive() ? 'active' : 'inactive',
        ]);

        try {
            // Check if mission has active assignments
            $this->logger->debug('Checking for active assignments', [
                'mission_id' => $mission->getId(),
            ]);

            $activeAssignments = $mission->getActiveAssignmentsCount();

            $this->logger->debug('Active assignments check result', [
                'mission_id' => $mission->getId(),
                'active_assignments_count' => $activeAssignments,
            ]);

            if ($activeAssignments > 0) {
                $this->logger->warning('Mission deletion blocked - has active assignments', [
                    'mission_id' => $mission->getId(),
                    'mission_title' => $mission->getTitle(),
                    'active_assignments_count' => $activeAssignments,
                ]);

                throw new RuntimeException('Cannot delete mission with active assignments. Complete or suspend assignments first.');
            }

            $this->logger->debug('Setting mission as inactive', [
                'mission_id' => $mission->getId(),
            ]);

            $mission->setIsActive(false);

            $this->logger->debug('Flushing entity manager for mission deletion');
            $this->entityManager->flush();

            $this->logger->info('Company mission deactivated successfully', [
                'mission_id' => $mission->getId(),
                'title' => $mission->getTitle(),
                'supervisor_id' => $mission->getSupervisor()->getId(),
                'supervisor_name' => $mission->getSupervisor()->getFullName(),
                'deactivated_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime exception during mission deletion', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected exception during mission deletion', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to delete mission: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Find recommended missions for a student based on their progress.
     *
     * @return CompanyMission[]
     */
    public function findRecommendedMissions(Student $student, int $limit = 10): array
    {
        // This would typically analyze the student's completed missions,
        // current skill level, and learning progression to recommend next missions

        // For now, we'll use the repository method
        return $this->missionRepository->findNextRecommendedMissions($student, $limit);
    }

    /**
     * Find suitable missions for a student based on complexity level.
     *
     * @return CompanyMission[]
     */
    public function findSuitableMissions(Student $student, string $complexity = 'debutant', int $limit = 10): array
    {
        return $this->missionRepository->findSuitableMissionsForStudent($student, $complexity, $limit);
    }

    /**
     * Get mission progression path for a student.
     */
    public function getMissionProgressionPath(Student $student): array
    {
        // This would analyze completed missions and suggest a progression path
        // For now, return a structured progression

        return [
            'court_terme' => [
                'debutant' => $this->missionRepository->findByTermAndComplexity('court', 'debutant'),
                'intermediaire' => $this->missionRepository->findByTermAndComplexity('court', 'intermediaire'),
                'avance' => $this->missionRepository->findByTermAndComplexity('court', 'avance'),
            ],
            'moyen_terme' => [
                'debutant' => $this->missionRepository->findByTermAndComplexity('moyen', 'debutant'),
                'intermediaire' => $this->missionRepository->findByTermAndComplexity('moyen', 'intermediaire'),
                'avance' => $this->missionRepository->findByTermAndComplexity('moyen', 'avance'),
            ],
            'long_terme' => [
                'debutant' => $this->missionRepository->findByTermAndComplexity('long', 'debutant'),
                'intermediaire' => $this->missionRepository->findByTermAndComplexity('long', 'intermediaire'),
                'avance' => $this->missionRepository->findByTermAndComplexity('long', 'avance'),
            ],
        ];
    }

    /**
     * Validate mission data before creation/update.
     *
     * @return array Array of validation errors (empty if valid)
     */
    public function validateMissionData(array $data): array
    {
        $errors = [];

        // Required fields
        $requiredFields = ['title', 'description', 'context', 'complexity', 'term', 'department', 'duration'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Le champ '{$field}' est obligatoire.";
            }
        }

        // Validate complexity
        if (isset($data['complexity']) && !in_array($data['complexity'], ['debutant', 'intermediaire', 'avance'], true)) {
            $errors[] = 'Niveau de complexité invalide.';
        }

        // Validate term
        if (isset($data['term']) && !in_array($data['term'], ['court', 'moyen', 'long'], true)) {
            $errors[] = 'Terme de mission invalide.';
        }

        // Validate arrays
        $arrayFields = ['objectives', 'requiredSkills', 'skillsToAcquire', 'evaluationCriteria'];
        foreach ($arrayFields as $field) {
            if (isset($data[$field])) {
                if (!is_array($data[$field])) {
                    $errors[] = "Le champ '{$field}' doit être un tableau.";
                } elseif (empty($data[$field])) {
                    $errors[] = "Le champ '{$field}' ne peut pas être vide.";
                }
            }
        }

        return $errors;
    }

    /**
     * Clone a mission for reuse.
     */
    public function cloneMission(CompanyMission $originalMission, array $overrides = []): CompanyMission
    {
        $this->logger->info('Starting mission cloning process', [
            'original_mission_id' => $originalMission->getId(),
            'original_title' => $originalMission->getTitle(),
            'overrides_provided' => array_keys($overrides),
            'overrides_count' => count($overrides),
        ]);

        try {
            $clonedMission = new CompanyMission();
            $this->logger->debug('Created new CompanyMission entity for cloning', [
                'entity_id' => spl_object_id($clonedMission),
                'original_mission_id' => $originalMission->getId(),
            ]);

            // Copy basic properties with overrides
            $newTitle = $overrides['title'] ?? $originalMission->getTitle() . ' (Copie)';
            $clonedMission->setTitle($newTitle);
            $this->logger->debug('Set cloned mission title', [
                'original_title' => $originalMission->getTitle(),
                'new_title' => $newTitle,
                'title_overridden' => isset($overrides['title']),
            ]);

            $clonedMission->setDescription($originalMission->getDescription());
            $this->logger->debug('Copied mission description', [
                'description_length' => strlen($originalMission->getDescription()),
            ]);

            $clonedMission->setContext($originalMission->getContext());
            $this->logger->debug('Copied mission context', [
                'context_length' => strlen($originalMission->getContext()),
            ]);

            $clonedMission->setDuration($originalMission->getDuration());
            $this->logger->debug('Copied mission duration', [
                'duration' => $originalMission->getDuration(),
            ]);

            $newComplexity = $overrides['complexity'] ?? $originalMission->getComplexity();
            $clonedMission->setComplexity($newComplexity);
            $this->logger->debug('Set cloned mission complexity', [
                'original_complexity' => $originalMission->getComplexity(),
                'new_complexity' => $newComplexity,
                'complexity_overridden' => isset($overrides['complexity']),
            ]);

            $newTerm = $overrides['term'] ?? $originalMission->getTerm();
            $clonedMission->setTerm($newTerm);
            $this->logger->debug('Set cloned mission term', [
                'original_term' => $originalMission->getTerm(),
                'new_term' => $newTerm,
                'term_overridden' => isset($overrides['term']),
            ]);

            $newDepartment = $overrides['department'] ?? $originalMission->getDepartment();
            $clonedMission->setDepartment($newDepartment);
            $this->logger->debug('Set cloned mission department', [
                'original_department' => $originalMission->getDepartment(),
                'new_department' => $newDepartment,
                'department_overridden' => isset($overrides['department']),
            ]);

            $newSupervisor = $overrides['supervisor'] ?? $originalMission->getSupervisor();
            $clonedMission->setSupervisor($newSupervisor);
            $this->logger->debug('Set cloned mission supervisor', [
                'original_supervisor_id' => $originalMission->getSupervisor()->getId(),
                'new_supervisor_id' => $newSupervisor->getId(),
                'supervisor_overridden' => isset($overrides['supervisor']),
            ]);

            // Copy arrays
            $objectives = $originalMission->getObjectives();
            $clonedMission->setObjectives($objectives);
            $this->logger->debug('Copied mission objectives', [
                'objectives_count' => count($objectives),
                'objectives' => $objectives,
            ]);

            $requiredSkills = $originalMission->getRequiredSkills();
            $clonedMission->setRequiredSkills($requiredSkills);
            $this->logger->debug('Copied required skills', [
                'required_skills_count' => count($requiredSkills),
                'required_skills' => $requiredSkills,
            ]);

            $skillsToAcquire = $originalMission->getSkillsToAcquire();
            $clonedMission->setSkillsToAcquire($skillsToAcquire);
            $this->logger->debug('Copied skills to acquire', [
                'skills_to_acquire_count' => count($skillsToAcquire),
                'skills_to_acquire' => $skillsToAcquire,
            ]);

            $prerequisites = $originalMission->getPrerequisites();
            $clonedMission->setPrerequisites($prerequisites);
            $this->logger->debug('Copied prerequisites', [
                'prerequisites_count' => count($prerequisites),
                'prerequisites' => $prerequisites,
            ]);

            $evaluationCriteria = $originalMission->getEvaluationCriteria();
            $clonedMission->setEvaluationCriteria($evaluationCriteria);
            $this->logger->debug('Copied evaluation criteria', [
                'evaluation_criteria_count' => count($evaluationCriteria),
                'evaluation_criteria' => $evaluationCriteria,
            ]);

            // Set new order index
            $this->logger->debug('Calculating order index for cloned mission', [
                'term' => $clonedMission->getTerm(),
                'complexity' => $clonedMission->getComplexity(),
            ]);

            $orderIndex = $this->missionRepository->getNextOrderIndex($clonedMission->getTerm(), $clonedMission->getComplexity());
            $clonedMission->setOrderIndex($orderIndex);

            $this->logger->debug('Set order index for cloned mission', [
                'order_index' => $orderIndex,
            ]);

            $this->logger->debug('Persisting cloned mission');
            $this->entityManager->persist($clonedMission);

            $this->logger->debug('Flushing entity manager for mission cloning');
            $this->entityManager->flush();

            $this->logger->info('Company mission cloned successfully', [
                'original_mission_id' => $originalMission->getId(),
                'original_title' => $originalMission->getTitle(),
                'cloned_mission_id' => $clonedMission->getId(),
                'cloned_title' => $clonedMission->getTitle(),
                'overrides' => array_keys($overrides),
                'cloned_complexity' => $clonedMission->getComplexity(),
                'cloned_term' => $clonedMission->getTerm(),
                'cloned_order_index' => $clonedMission->getOrderIndex(),
                'cloned_at' => $clonedMission->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

            return $clonedMission;
        } catch (Exception $e) {
            $this->logger->error('Exception during mission cloning', [
                'original_mission_id' => $originalMission->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'overrides_provided' => array_keys($overrides),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to clone mission: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get mission statistics for a mentor.
     */
    public function getMentorMissionStats(Mentor $mentor): array
    {
        return $this->missionRepository->getMissionStatsByMentor($mentor);
    }

    /**
     * Reorder missions within the same complexity and term.
     *
     * @param array $missionIds Ordered array of mission IDs
     */
    public function reorderMissions(string $term, string $complexity, array $missionIds): void
    {
        $orderIndex = 1;

        foreach ($missionIds as $missionId) {
            $mission = $this->missionRepository->find($missionId);
            if ($mission && $mission->getTerm() === $term && $mission->getComplexity() === $complexity) {
                $mission->setOrderIndex($orderIndex);
                $orderIndex++;
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Missions reordered', [
            'term' => $term,
            'complexity' => $complexity,
            'mission_ids' => $missionIds,
        ]);
    }

    /**
     * Search missions with filters.
     *
     * @return CompanyMission[]
     */
    public function searchMissions(string $keywords, array $filters = []): array
    {
        return $this->missionRepository->searchMissions($keywords, $filters);
    }

    /**
     * Get missions requiring attention (no assignments, old missions, etc.).
     */
    public function getMissionsRequiringAttention(?Mentor $mentor = null): array
    {
        $unassignedMissions = $this->missionRepository->findUnassignedMissions();

        if ($mentor) {
            $unassignedMissions = array_filter($unassignedMissions, static fn ($mission) => $mission->getSupervisor() === $mentor);
        }

        return [
            'unassigned' => $unassignedMissions,
        ];
    }

    /**
     * Bulk update mission status.
     *
     * @return int Number of updated missions
     */
    public function bulkUpdateMissionStatus(array $missionIds, bool $isActive): int
    {
        $updatedCount = 0;

        foreach ($missionIds as $missionId) {
            $mission = $this->missionRepository->find($missionId);
            if ($mission) {
                $mission->setIsActive($isActive);
                $updatedCount++;
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Bulk mission status update', [
            'mission_ids' => $missionIds,
            'is_active' => $isActive,
            'updated_count' => $updatedCount,
        ]);

        return $updatedCount;
    }

    /**
     * Export mission data for reporting.
     */
    public function exportMissionData(array $filters = []): array
    {
        // This would generate data suitable for export to CSV, Excel, etc.
        $missions = $this->missionRepository->findBy(['isActive' => true]);

        $exportData = [];
        foreach ($missions as $mission) {
            $exportData[] = [
                'id' => $mission->getId(),
                'title' => $mission->getTitle(),
                'complexity' => $mission->getComplexityLabel(),
                'term' => $mission->getTermLabel(),
                'department' => $mission->getDepartmentLabel(),
                'supervisor' => $mission->getSupervisor()->getFullName(),
                'objectives_count' => count($mission->getObjectives()),
                'skills_count' => count($mission->getSkillsToAcquire()),
                'assignments_count' => $mission->getAssignments()->count(),
                'created_at' => $mission->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $exportData;
    }

    /**
     * Get mission progress data.
     */
    public function getMissionProgressData(CompanyMission $mission): array
    {
        $assignments = $mission->getAssignments();

        $statusCounts = [
            'assigned' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        $totalAssignments = $assignments->count();

        foreach ($assignments as $assignment) {
            $status = $assignment->getStatus();
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        $completionRate = $totalAssignments > 0 ?
            round(($statusCounts['completed'] / $totalAssignments) * 100, 1) : 0;

        return [
            'total_assignments' => $totalAssignments,
            'status_counts' => $statusCounts,
            'completion_rate' => $completionRate,
            'average_duration' => $this->calculateAverageAssignmentDuration($assignments),
        ];
    }

    /**
     * Calculate average assignment duration.
     *
     * @param mixed $assignments
     */
    private function calculateAverageAssignmentDuration($assignments): float
    {
        $completedAssignments = $assignments->filter(static fn ($a) => $a->getStatus() === 'completed');

        if ($completedAssignments->isEmpty()) {
            return 0;
        }

        $totalDuration = 0;
        foreach ($completedAssignments as $assignment) {
            if ($assignment->getStartDate() && $assignment->getCompletedAt()) {
                $duration = $assignment->getStartDate()->diff($assignment->getCompletedAt())->days;
                $totalDuration += $duration;
            }
        }

        return $completedAssignments->count() > 0 ?
            round($totalDuration / $completedAssignments->count(), 1) : 0;
    }
}
