<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\CompanyMission;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Repository\CompanyMissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing company missions
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
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->missionRepository = $missionRepository;
        $this->logger = $logger;
    }

    /**
     * Create a new company mission
     *
     * @param array $data
     * @param Mentor $supervisor
     * @return CompanyMission
     */
    public function createMission(array $data, Mentor $supervisor): CompanyMission
    {
        $mission = new CompanyMission();
        $mission->setSupervisor($supervisor);

        // Set basic properties
        $mission->setTitle($data['title']);
        $mission->setDescription($data['description']);
        $mission->setContext($data['context']);
        $mission->setDuration($data['duration']);
        $mission->setComplexity($data['complexity']);
        $mission->setTerm($data['term']);
        $mission->setDepartment($data['department']);

        // Set arrays
        $mission->setObjectives($data['objectives'] ?? []);
        $mission->setRequiredSkills($data['requiredSkills'] ?? []);
        $mission->setSkillsToAcquire($data['skillsToAcquire'] ?? []);
        $mission->setPrerequisites($data['prerequisites'] ?? []);
        $mission->setEvaluationCriteria($data['evaluationCriteria'] ?? []);

        // Auto-calculate order index
        if (!isset($data['orderIndex'])) {
            $orderIndex = $this->missionRepository->getNextOrderIndex($data['term'], $data['complexity']);
            $mission->setOrderIndex($orderIndex);
        } else {
            $mission->setOrderIndex($data['orderIndex']);
        }

        $this->entityManager->persist($mission);
        $this->entityManager->flush();

        $this->logger->info('Company mission created', [
            'mission_id' => $mission->getId(),
            'title' => $mission->getTitle(),
            'supervisor_id' => $supervisor->getId(),
            'complexity' => $mission->getComplexity(),
            'term' => $mission->getTerm(),
        ]);

        return $mission;
    }

    /**
     * Update an existing mission
     *
     * @param CompanyMission $mission
     * @param array $data
     * @return CompanyMission
     */
    public function updateMission(CompanyMission $mission, array $data): CompanyMission
    {
        $originalData = [
            'title' => $mission->getTitle(),
            'complexity' => $mission->getComplexity(),
            'term' => $mission->getTerm(),
        ];

        // Update basic properties
        if (isset($data['title'])) {
            $mission->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $mission->setDescription($data['description']);
        }
        if (isset($data['context'])) {
            $mission->setContext($data['context']);
        }
        if (isset($data['duration'])) {
            $mission->setDuration($data['duration']);
        }
        if (isset($data['department'])) {
            $mission->setDepartment($data['department']);
        }

        // Handle complexity/term changes that might affect order
        $needsReordering = false;
        if (isset($data['complexity']) && $data['complexity'] !== $mission->getComplexity()) {
            $mission->setComplexity($data['complexity']);
            $needsReordering = true;
        }
        if (isset($data['term']) && $data['term'] !== $mission->getTerm()) {
            $mission->setTerm($data['term']);
            $needsReordering = true;
        }

        // Recalculate order index if needed
        if ($needsReordering && !isset($data['orderIndex'])) {
            $orderIndex = $this->missionRepository->getNextOrderIndex($mission->getTerm(), $mission->getComplexity());
            $mission->setOrderIndex($orderIndex);
        } elseif (isset($data['orderIndex'])) {
            $mission->setOrderIndex($data['orderIndex']);
        }

        // Update arrays
        if (isset($data['objectives'])) {
            $mission->setObjectives($data['objectives']);
        }
        if (isset($data['requiredSkills'])) {
            $mission->setRequiredSkills($data['requiredSkills']);
        }
        if (isset($data['skillsToAcquire'])) {
            $mission->setSkillsToAcquire($data['skillsToAcquire']);
        }
        if (isset($data['prerequisites'])) {
            $mission->setPrerequisites($data['prerequisites']);
        }
        if (isset($data['evaluationCriteria'])) {
            $mission->setEvaluationCriteria($data['evaluationCriteria']);
        }

        $this->entityManager->flush();

        $this->logger->info('Company mission updated', [
            'mission_id' => $mission->getId(),
            'original_data' => $originalData,
            'updated_fields' => array_keys($data),
        ]);

        return $mission;
    }

    /**
     * Delete a mission (soft delete by setting inactive)
     *
     * @param CompanyMission $mission
     * @return void
     */
    public function deleteMission(CompanyMission $mission): void
    {
        // Check if mission has active assignments
        $activeAssignments = $mission->getActiveAssignmentsCount();
        
        if ($activeAssignments > 0) {
            throw new \RuntimeException('Cannot delete mission with active assignments. Complete or suspend assignments first.');
        }

        $mission->setIsActive(false);
        $this->entityManager->flush();

        $this->logger->info('Company mission deactivated', [
            'mission_id' => $mission->getId(),
            'title' => $mission->getTitle(),
        ]);
    }

    /**
     * Find recommended missions for a student based on their progress
     *
     * @param Student $student
     * @param int $limit
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
     * Find suitable missions for a student based on complexity level
     *
     * @param Student $student
     * @param string $complexity
     * @param int $limit
     * @return CompanyMission[]
     */
    public function findSuitableMissions(Student $student, string $complexity = 'debutant', int $limit = 10): array
    {
        return $this->missionRepository->findSuitableMissionsForStudent($student, $complexity, $limit);
    }

    /**
     * Get mission progression path for a student
     *
     * @param Student $student
     * @return array
     */
    public function getMissionProgressionPath(Student $student): array
    {
        // This would analyze completed missions and suggest a progression path
        // For now, return a structured progression
        
        $progressionPath = [
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

        return $progressionPath;
    }

    /**
     * Validate mission data before creation/update
     *
     * @param array $data
     * @return array Array of validation errors (empty if valid)
     */
    public function validateMissionData(array $data): array
    {
        $errors = [];

        // Required fields
        $requiredFields = ['title', 'description', 'context', 'complexity', 'term', 'department', 'duration'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Le champ '$field' est obligatoire.";
            }
        }

        // Validate complexity
        if (isset($data['complexity']) && !in_array($data['complexity'], ['debutant', 'intermediaire', 'avance'])) {
            $errors[] = 'Niveau de complexité invalide.';
        }

        // Validate term
        if (isset($data['term']) && !in_array($data['term'], ['court', 'moyen', 'long'])) {
            $errors[] = 'Terme de mission invalide.';
        }

        // Validate arrays
        $arrayFields = ['objectives', 'requiredSkills', 'skillsToAcquire', 'evaluationCriteria'];
        foreach ($arrayFields as $field) {
            if (isset($data[$field])) {
                if (!is_array($data[$field])) {
                    $errors[] = "Le champ '$field' doit être un tableau.";
                } elseif (empty($data[$field])) {
                    $errors[] = "Le champ '$field' ne peut pas être vide.";
                }
            }
        }

        return $errors;
    }

    /**
     * Clone a mission for reuse
     *
     * @param CompanyMission $originalMission
     * @param array $overrides
     * @return CompanyMission
     */
    public function cloneMission(CompanyMission $originalMission, array $overrides = []): CompanyMission
    {
        $clonedMission = new CompanyMission();
        
        // Copy basic properties
        $clonedMission->setTitle($overrides['title'] ?? $originalMission->getTitle() . ' (Copie)');
        $clonedMission->setDescription($originalMission->getDescription());
        $clonedMission->setContext($originalMission->getContext());
        $clonedMission->setDuration($originalMission->getDuration());
        $clonedMission->setComplexity($overrides['complexity'] ?? $originalMission->getComplexity());
        $clonedMission->setTerm($overrides['term'] ?? $originalMission->getTerm());
        $clonedMission->setDepartment($overrides['department'] ?? $originalMission->getDepartment());
        $clonedMission->setSupervisor($overrides['supervisor'] ?? $originalMission->getSupervisor());

        // Copy arrays
        $clonedMission->setObjectives($originalMission->getObjectives());
        $clonedMission->setRequiredSkills($originalMission->getRequiredSkills());
        $clonedMission->setSkillsToAcquire($originalMission->getSkillsToAcquire());
        $clonedMission->setPrerequisites($originalMission->getPrerequisites());
        $clonedMission->setEvaluationCriteria($originalMission->getEvaluationCriteria());

        // Set new order index
        $orderIndex = $this->missionRepository->getNextOrderIndex($clonedMission->getTerm(), $clonedMission->getComplexity());
        $clonedMission->setOrderIndex($orderIndex);

        $this->entityManager->persist($clonedMission);
        $this->entityManager->flush();

        $this->logger->info('Company mission cloned', [
            'original_mission_id' => $originalMission->getId(),
            'cloned_mission_id' => $clonedMission->getId(),
            'overrides' => array_keys($overrides),
        ]);

        return $clonedMission;
    }

    /**
     * Get mission statistics for a mentor
     *
     * @param Mentor $mentor
     * @return array
     */
    public function getMentorMissionStats(Mentor $mentor): array
    {
        return $this->missionRepository->getMissionStatsByMentor($mentor);
    }

    /**
     * Reorder missions within the same complexity and term
     *
     * @param string $term
     * @param string $complexity
     * @param array $missionIds Ordered array of mission IDs
     * @return void
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
     * Search missions with filters
     *
     * @param string $keywords
     * @param array $filters
     * @return CompanyMission[]
     */
    public function searchMissions(string $keywords, array $filters = []): array
    {
        return $this->missionRepository->searchMissions($keywords, $filters);
    }

    /**
     * Get missions requiring attention (no assignments, old missions, etc.)
     *
     * @param Mentor|null $mentor
     * @return array
     */
    public function getMissionsRequiringAttention(?Mentor $mentor = null): array
    {
        $unassignedMissions = $this->missionRepository->findUnassignedMissions();
        
        if ($mentor) {
            $unassignedMissions = array_filter($unassignedMissions, function($mission) use ($mentor) {
                return $mission->getSupervisor() === $mentor;
            });
        }

        return [
            'unassigned' => $unassignedMissions,
        ];
    }

    /**
     * Bulk update mission status
     *
     * @param array $missionIds
     * @param bool $isActive
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
     * Export mission data for reporting
     *
     * @param array $filters
     * @return array
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
}
