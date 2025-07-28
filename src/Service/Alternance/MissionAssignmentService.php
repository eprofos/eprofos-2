<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\MissionAssignment;
use App\Entity\Alternance\CompanyMission;
use App\Entity\User\Student;
use App\Entity\User\Mentor;
use App\Repository\Alternance\MissionAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing mission assignments
 * 
 * Handles assignment creation, progress tracking, evaluation,
 * and business logic for mission assignments in the alternance system.
 */
class MissionAssignmentService
{
    private EntityManagerInterface $entityManager;
    private MissionAssignmentRepository $assignmentRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        MissionAssignmentRepository $assignmentRepository,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->assignmentRepository = $assignmentRepository;
        $this->logger = $logger;
    }

    /**
     * Create a new mission assignment
     *
     * @param CompanyMission $mission
     * @param Student $student
     * @param array $data
     * @return MissionAssignment
     */
    public function createAssignment(CompanyMission $mission, Student $student, array $data): MissionAssignment
    {
        // Validate prerequisites
        $this->validateAssignmentPrerequisites($mission, $student);

        $assignment = new MissionAssignment();
        $assignment->setMission($mission);
        $assignment->setStudent($student);
        $assignment->setStartDate($data['startDate']);
        $assignment->setEndDate($data['endDate']);
        $assignment->setStatus($data['status'] ?? 'planifiee');

        // Set intermediate objectives if provided
        if (isset($data['intermediateObjectives'])) {
            $assignment->setIntermediateObjectives($data['intermediateObjectives']);
        } else {
            // Auto-generate intermediate objectives based on mission objectives
            $intermediateObjectives = $this->generateIntermediateObjectives($mission);
            $assignment->setIntermediateObjectives($intermediateObjectives);
        }

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        $this->logger->info('Mission assignment created', [
            'assignment_id' => $assignment->getId(),
            'mission_id' => $mission->getId(),
            'student_id' => $student->getId(),
            'start_date' => $assignment->getStartDate()->format('Y-m-d'),
            'end_date' => $assignment->getEndDate()->format('Y-m-d'),
        ]);

        return $assignment;
    }

    /**
     * Update an existing assignment
     *
     * @param MissionAssignment $assignment
     * @param array $data
     * @return MissionAssignment
     */
    public function updateAssignment(MissionAssignment $assignment, array $data): MissionAssignment
    {
        $originalStatus = $assignment->getStatus();

        // Update basic properties
        if (isset($data['startDate'])) {
            $assignment->setStartDate($data['startDate']);
        }
        if (isset($data['endDate'])) {
            $assignment->setEndDate($data['endDate']);
        }
        if (isset($data['status'])) {
            $assignment->setStatus($data['status']);
        }
        if (isset($data['completionRate'])) {
            $assignment->updateProgress($data['completionRate']);
        }

        // Update feedback and ratings
        if (isset($data['mentorFeedback'])) {
            $assignment->setMentorFeedback($data['mentorFeedback']);
        }
        if (isset($data['studentFeedback'])) {
            $assignment->setStudentFeedback($data['studentFeedback']);
        }
        if (isset($data['mentorRating'])) {
            $assignment->setMentorRating($data['mentorRating']);
        }
        if (isset($data['studentSatisfaction'])) {
            $assignment->setStudentSatisfaction($data['studentSatisfaction']);
        }

        // Update arrays
        if (isset($data['intermediateObjectives'])) {
            $assignment->setIntermediateObjectives($data['intermediateObjectives']);
        }
        if (isset($data['difficulties'])) {
            $assignment->setDifficulties($data['difficulties']);
        }
        if (isset($data['achievements'])) {
            $assignment->setAchievements($data['achievements']);
        }
        if (isset($data['competenciesAcquired'])) {
            $assignment->setCompetenciesAcquired($data['competenciesAcquired']);
        }

        $this->entityManager->flush();

        $this->logger->info('Mission assignment updated', [
            'assignment_id' => $assignment->getId(),
            'original_status' => $originalStatus,
            'new_status' => $assignment->getStatus(),
            'updated_fields' => array_keys($data),
        ]);

        return $assignment;
    }

    /**
     * Start an assignment (change status to in progress)
     *
     * @param MissionAssignment $assignment
     * @return void
     */
    public function startAssignment(MissionAssignment $assignment): void
    {
        if ($assignment->getStatus() !== 'planifiee') {
            throw new \RuntimeException('Only planned assignments can be started.');
        }

        $assignment->start();
        $this->entityManager->flush();

        $this->logger->info('Mission assignment started', [
            'assignment_id' => $assignment->getId(),
            'student_id' => $assignment->getStudent()->getId(),
            'mission_id' => $assignment->getMission()->getId(),
        ]);
    }

    /**
     * Complete an assignment
     *
     * @param MissionAssignment $assignment
     * @param array $completionData
     * @return void
     */
    public function completeAssignment(MissionAssignment $assignment, array $completionData = []): void
    {
        if (!in_array($assignment->getStatus(), ['planifiee', 'en_cours'])) {
            throw new \RuntimeException('Only planned or in-progress assignments can be completed.');
        }

        $assignment->complete();

        // Update completion data if provided
        if (isset($completionData['achievements'])) {
            $assignment->setAchievements($completionData['achievements']);
        }
        if (isset($completionData['competenciesAcquired'])) {
            $assignment->setCompetenciesAcquired($completionData['competenciesAcquired']);
        }
        if (isset($completionData['studentFeedback'])) {
            $assignment->setStudentFeedback($completionData['studentFeedback']);
        }

        $this->entityManager->flush();

        $this->logger->info('Mission assignment completed', [
            'assignment_id' => $assignment->getId(),
            'student_id' => $assignment->getStudent()->getId(),
            'mission_id' => $assignment->getMission()->getId(),
            'completion_rate' => $assignment->getCompletionRate(),
        ]);
    }

    /**
     * Suspend an assignment
     *
     * @param MissionAssignment $assignment
     * @param string $reason
     * @return void
     */
    public function suspendAssignment(MissionAssignment $assignment, string $reason): void
    {
        if (!in_array($assignment->getStatus(), ['planifiee', 'en_cours'])) {
            throw new \RuntimeException('Only planned or in-progress assignments can be suspended.');
        }

        $assignment->suspend();

        // Add suspension reason to difficulties
        $difficulties = $assignment->getDifficulties();
        $difficulties[] = [
            'type' => 'suspension',
            'reason' => $reason,
            'date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $assignment->setDifficulties($difficulties);

        $this->entityManager->flush();

        $this->logger->info('Mission assignment suspended', [
            'assignment_id' => $assignment->getId(),
            'reason' => $reason,
        ]);
    }

    /**
     * Resume a suspended assignment
     *
     * @param MissionAssignment $assignment
     * @return void
     */
    public function resumeAssignment(MissionAssignment $assignment): void
    {
        if ($assignment->getStatus() !== 'suspendue') {
            throw new \RuntimeException('Only suspended assignments can be resumed.');
        }

        $assignment->resume();
        $this->entityManager->flush();

        $this->logger->info('Mission assignment resumed', [
            'assignment_id' => $assignment->getId(),
        ]);
    }

    /**
     * Update assignment progress
     *
     * @param MissionAssignment $assignment
     * @param float $completionRate
     * @param array $progressData
     * @return void
     */
    public function updateProgress(MissionAssignment $assignment, float $completionRate, array $progressData = []): void
    {
        $assignment->updateProgress($completionRate);

        // Update intermediate objectives progress
        if (isset($progressData['intermediateObjectives'])) {
            $assignment->setIntermediateObjectives($progressData['intermediateObjectives']);
        }

        // Add achievements
        if (isset($progressData['achievements'])) {
            $existingAchievements = $assignment->getAchievements();
            $newAchievements = array_merge($existingAchievements, $progressData['achievements']);
            $assignment->setAchievements($newAchievements);
        }

        // Add difficulties if any
        if (isset($progressData['difficulties'])) {
            $existingDifficulties = $assignment->getDifficulties();
            $newDifficulties = array_merge($existingDifficulties, $progressData['difficulties']);
            $assignment->setDifficulties($newDifficulties);
        }

        $this->entityManager->flush();

        $this->logger->info('Mission assignment progress updated', [
            'assignment_id' => $assignment->getId(),
            'completion_rate' => $completionRate,
        ]);
    }

    /**
     * Add mentor evaluation
     *
     * @param MissionAssignment $assignment
     * @param int $rating
     * @param string $feedback
     * @param array $competenciesAcquired
     * @return void
     */
    public function addMentorEvaluation(MissionAssignment $assignment, int $rating, string $feedback, array $competenciesAcquired = []): void
    {
        if ($assignment->getStatus() !== 'terminee') {
            throw new \RuntimeException('Only completed assignments can be evaluated.');
        }

        $assignment->setMentorRating($rating);
        $assignment->setMentorFeedback($feedback);
        
        if (!empty($competenciesAcquired)) {
            $assignment->setCompetenciesAcquired($competenciesAcquired);
        }

        $this->entityManager->flush();

        $this->logger->info('Mentor evaluation added', [
            'assignment_id' => $assignment->getId(),
            'rating' => $rating,
            'competencies_count' => count($competenciesAcquired),
        ]);
    }

    /**
     * Add student satisfaction feedback
     *
     * @param MissionAssignment $assignment
     * @param int $satisfaction
     * @param string $feedback
     * @return void
     */
    public function addStudentFeedback(MissionAssignment $assignment, int $satisfaction, string $feedback): void
    {
        $assignment->setStudentSatisfaction($satisfaction);
        $assignment->setStudentFeedback($feedback);

        $this->entityManager->flush();

        $this->logger->info('Student feedback added', [
            'assignment_id' => $assignment->getId(),
            'satisfaction' => $satisfaction,
        ]);
    }

    /**
     * Get assignment statistics for a student
     *
     * @param Student $student
     * @return array
     */
    public function getStudentAssignmentStats(Student $student): array
    {
        return $this->assignmentRepository->calculateCompletionStats($student);
    }

    /**
     * Get assignment statistics for a mentor
     *
     * @param Mentor $mentor
     * @return array
     */
    public function getMentorAssignmentStats(Mentor $mentor): array
    {
        return $this->assignmentRepository->getAssignmentStatsByMentor($mentor);
    }

    /**
     * Find assignments needing attention
     *
     * @param Mentor|null $mentor
     * @return array
     */
    public function findAssignmentsNeedingAttention(?Mentor $mentor = null): array
    {
        return [
            'overdue' => $this->assignmentRepository->findOverdueAssignments($mentor),
            'low_progress' => $this->assignmentRepository->findLowCompletionAssignments(50.0, $mentor),
            'need_feedback' => $this->assignmentRepository->findRequiringFeedback($mentor),
            'need_attention' => $this->assignmentRepository->findAssignmentsNeedingAttention($mentor),
        ];
    }

    /**
     * Get dashboard data for a mentor
     *
     * @param Mentor $mentor
     * @return array
     */
    public function getMentorDashboard(Mentor $mentor): array
    {
        return $this->assignmentRepository->getMentorDashboardData($mentor);
    }

    /**
     * Validate assignment prerequisites
     *
     * @param CompanyMission $mission
     * @param Student $student
     * @return void
     * @throws \RuntimeException
     */
    private function validateAssignmentPrerequisites(CompanyMission $mission, Student $student): void
    {
        // Check if student already has an active assignment for this mission
        $existingAssignment = $this->assignmentRepository->findOneBy([
            'mission' => $mission,
            'student' => $student,
            'status' => ['planifiee', 'en_cours']
        ]);

        if ($existingAssignment) {
            throw new \RuntimeException('Student already has an active assignment for this mission.');
        }

        // Check mission prerequisites (would need to implement prerequisite logic)
        $prerequisites = $mission->getPrerequisites();
        if (!empty($prerequisites)) {
            // This would check if the student meets the prerequisites
            // For now, we'll just log it
            $this->logger->info('Mission has prerequisites that should be validated', [
                'mission_id' => $mission->getId(),
                'student_id' => $student->getId(),
                'prerequisites' => $prerequisites,
            ]);
        }
    }

    /**
     * Generate intermediate objectives based on mission objectives
     *
     * @param CompanyMission $mission
     * @return array
     */
    private function generateIntermediateObjectives(CompanyMission $mission): array
    {
        $objectives = $mission->getObjectives();
        $intermediateObjectives = [];

        foreach ($objectives as $index => $objective) {
            $intermediateObjectives[] = [
                'id' => $index + 1,
                'title' => $objective,
                'completed' => false,
                'completion_date' => null,
                'notes' => '',
            ];
        }

        return $intermediateObjectives;
    }

    /**
     * Bulk update assignment status
     *
     * @param array $assignmentIds
     * @param string $status
     * @return int Number of updated assignments
     */
    public function bulkUpdateAssignmentStatus(array $assignmentIds, string $status): int
    {
        $updatedCount = 0;
        
        foreach ($assignmentIds as $assignmentId) {
            $assignment = $this->assignmentRepository->find($assignmentId);
            if ($assignment) {
                $assignment->setStatus($status);
                $updatedCount++;
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Bulk assignment status update', [
            'assignment_ids' => $assignmentIds,
            'status' => $status,
            'updated_count' => $updatedCount,
        ]);

        return $updatedCount;
    }

    /**
     * Export assignment data for reporting
     *
     * @param array $filters
     * @return array
     */
    public function exportAssignmentData(array $filters = []): array
    {
        $assignments = $this->assignmentRepository->findAll();
        
        $exportData = [];
        foreach ($assignments as $assignment) {
            $exportData[] = [
                'id' => $assignment->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'student_name' => $assignment->getStudent()->getFullName(),
                'mentor_name' => $assignment->getMission()->getSupervisor()->getFullName(),
                'status' => $assignment->getStatusLabel(),
                'start_date' => $assignment->getStartDate()->format('Y-m-d'),
                'end_date' => $assignment->getEndDate()->format('Y-m-d'),
                'completion_rate' => $assignment->getCompletionRate(),
                'mentor_rating' => $assignment->getMentorRating(),
                'student_satisfaction' => $assignment->getStudentSatisfaction(),
                'created_at' => $assignment->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $exportData;
    }

    /**
     * Calculate assignment duration statistics
     *
     * @param array $filters
     * @return array
     */
    public function calculateDurationStatistics(array $filters = []): array
    {
        $assignments = $this->assignmentRepository->findByStatus('terminee');
        
        $durations = [];
        $totalDuration = 0;
        
        foreach ($assignments as $assignment) {
            $duration = $assignment->getDurationInDays();
            $durations[] = $duration;
            $totalDuration += $duration;
        }

        if (empty($durations)) {
            return [
                'count' => 0,
                'average' => 0,
                'min' => 0,
                'max' => 0,
            ];
        }

        sort($durations);
        $count = count($durations);

        return [
            'count' => $count,
            'average' => round($totalDuration / $count, 2),
            'min' => min($durations),
            'max' => max($durations),
            'median' => $count % 2 === 0 
                ? ($durations[$count/2 - 1] + $durations[$count/2]) / 2
                : $durations[floor($count/2)],
        ];
    }

    /**
     * Assign mission to contract
     */
    public function assignMissionToContract(CompanyMission $mission, $contract): MissionAssignment
    {
        $assignment = new MissionAssignment();
        $assignment->setMission($mission);
        $assignment->setStudent($contract->getStudent());
        $assignment->setStartDate(new \DateTime());
        $assignment->setEndDate($this->calculateExpectedEndDate($mission));
        $assignment->setStatus('planifiee');
        
        $this->entityManager->persist($assignment);
        $this->entityManager->flush();
        
        $this->logger->info('Mission assigned to contract', [
            'mission_id' => $mission->getId(),
            'contract_id' => $contract->getId(),
            'assignment_id' => $assignment->getId(),
        ]);

        return $assignment;
    }

    /**
     * Calculate expected end date based on mission duration
     */
    private function calculateExpectedEndDate(CompanyMission $mission): \DateTime
    {
        $duration = $mission->getDuration() ?? 30; // Default 30 days
        return (new \DateTime())->add(new \DateInterval("P{$duration}D"));
    }
}
