<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\CompanyMission;
use App\Entity\Alternance\MissionAssignment;
use App\Entity\User\Student;
use App\Repository\CompanyMissionRepository;
use App\Repository\MissionAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing mission progression logic
 * 
 * Handles the logic of recommending next missions based on student progress,
 * complexity progression, and Qualiopi compliance requirements.
 */
class MissionProgressionService
{
    private EntityManagerInterface $entityManager;
    private CompanyMissionRepository $missionRepository;
    private MissionAssignmentRepository $assignmentRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        CompanyMissionRepository $missionRepository,
        MissionAssignmentRepository $assignmentRepository,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->missionRepository = $missionRepository;
        $this->assignmentRepository = $assignmentRepository;
        $this->logger = $logger;
    }

    /**
     * Get recommended next missions for a student
     *
     * @param Student $student
     * @param int $limit
     * @return array
     */
    public function getRecommendedMissions(Student $student, int $limit = 10): array
    {
        $studentLevel = $this->assessStudentLevel($student);
        $completedMissions = $this->getCompletedMissionsByStudent($student);
        
        // Determine progression path
        $progressionPath = $this->calculateProgressionPath($student, $completedMissions);
        
        // Find suitable missions based on progression
        $recommendedMissions = $this->findMissionsByProgression($progressionPath, $studentLevel, $limit);
        
        return [
            'student_level' => $studentLevel,
            'progression_path' => $progressionPath,
            'recommended_missions' => $recommendedMissions,
            'completion_stats' => $this->getStudentCompletionStats($student),
        ];
    }

    /**
     * Assess the current level of a student based on completed missions
     *
     * @param Student $student
     * @return array
     */
    public function assessStudentLevel(Student $student): array
    {
        $stats = $this->assignmentRepository->calculateCompletionStats($student);
        $progressionByComplexity = $this->assignmentRepository->findStudentProgressionByComplexity($student);
        
        $level = 'debutant';
        $experience = 0;
        
        // Determine level based on completed missions
        if (isset($progressionByComplexity['avance']) && $progressionByComplexity['avance']['completed'] >= 2) {
            $level = 'avance';
            $experience = 3;
        } elseif (isset($progressionByComplexity['intermediaire']) && $progressionByComplexity['intermediaire']['completed'] >= 3) {
            $level = 'intermediaire';
            $experience = 2;
        } elseif (isset($progressionByComplexity['debutant']) && $progressionByComplexity['debutant']['completed'] >= 2) {
            $level = 'debutant';
            $experience = 1;
        }

        return [
            'current_level' => $level,
            'experience_score' => $experience,
            'completion_stats' => $stats,
            'progression_by_complexity' => $progressionByComplexity,
            'can_progress_to' => $this->determineNextLevel($level, $progressionByComplexity),
        ];
    }

    /**
     * Calculate the progression path for a student
     *
     * @param Student $student
     * @param array $completedMissions
     * @return array
     */
    public function calculateProgressionPath(Student $student, array $completedMissions): array
    {
        $progressionPath = [
            'current_phase' => 'court_terme',
            'current_complexity' => 'debutant',
            'next_phase' => null,
            'next_complexity' => null,
            'recommended_order' => [],
        ];

        // Analyze completed missions to determine current position
        $completedByTerm = [];
        $completedByComplexity = [];

        foreach ($completedMissions as $assignment) {
            $mission = $assignment->getMission();
            $term = $mission->getTerm();
            $complexity = $mission->getComplexity();
            
            $completedByTerm[$term] = ($completedByTerm[$term] ?? 0) + 1;
            $completedByComplexity[$complexity] = ($completedByComplexity[$complexity] ?? 0) + 1;
        }

        // Determine current phase and complexity
        if (($completedByTerm['long'] ?? 0) >= 1) {
            $progressionPath['current_phase'] = 'long_terme';
        } elseif (($completedByTerm['moyen'] ?? 0) >= 2) {
            $progressionPath['current_phase'] = 'moyen_terme';
        } else {
            $progressionPath['current_phase'] = 'court_terme';
        }

        if (($completedByComplexity['avance'] ?? 0) >= 1) {
            $progressionPath['current_complexity'] = 'avance';
        } elseif (($completedByComplexity['intermediaire'] ?? 0) >= 2) {
            $progressionPath['current_complexity'] = 'intermediaire';
        } else {
            $progressionPath['current_complexity'] = 'debutant';
        }

        // Determine next steps
        $progressionPath['next_complexity'] = $this->getNextComplexity($progressionPath['current_complexity'], $completedByComplexity);
        $progressionPath['next_phase'] = $this->getNextPhase($progressionPath['current_phase'], $completedByTerm);

        // Generate recommended order
        $progressionPath['recommended_order'] = $this->generateRecommendedOrder($progressionPath);

        return $progressionPath;
    }

    /**
     * Find missions based on progression path
     *
     * @param array $progressionPath
     * @param array $studentLevel
     * @param int $limit
     * @return array
     */
    public function findMissionsByProgression(array $progressionPath, array $studentLevel, int $limit): array
    {
        $recommendations = [];
        
        // Get missions for current level
        $currentMissions = $this->missionRepository->findByTermAndComplexity(
            $progressionPath['current_phase'],
            $progressionPath['current_complexity']
        );

        // Get missions for next level
        $nextMissions = [];
        if ($progressionPath['next_complexity']) {
            $nextMissions = $this->missionRepository->findByTermAndComplexity(
                $progressionPath['current_phase'],
                $progressionPath['next_complexity']
            );
        }

        // Get missions for next phase
        $nextPhaseMissions = [];
        if ($progressionPath['next_phase']) {
            $nextPhaseMissions = $this->missionRepository->findByTermAndComplexity(
                $progressionPath['next_phase'],
                'debutant'
            );
        }

        // Prioritize recommendations
        $recommendations = array_merge(
            array_slice($currentMissions, 0, $limit / 2),
            array_slice($nextMissions, 0, $limit / 4),
            array_slice($nextPhaseMissions, 0, $limit / 4)
        );

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Get completed missions by student
     *
     * @param Student $student
     * @return MissionAssignment[]
     */
    private function getCompletedMissionsByStudent(Student $student): array
    {
        return $this->assignmentRepository->findBy([
            'student' => $student,
            'status' => 'terminee'
        ], ['endDate' => 'DESC']);
    }

    /**
     * Get student completion statistics
     *
     * @param Student $student
     * @return array
     */
    private function getStudentCompletionStats(Student $student): array
    {
        $stats = $this->assignmentRepository->calculateCompletionStats($student);
        $progressionByComplexity = $this->assignmentRepository->findStudentProgressionByComplexity($student);

        return [
            'total_assignments' => $stats['total'],
            'completed_assignments' => $stats['completed'],
            'completion_percentage' => $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 2) : 0,
            'average_rating' => $stats['mentor_rating'],
            'progression_by_complexity' => $progressionByComplexity,
        ];
    }

    /**
     * Determine the next level based on current progression
     *
     * @param string $currentLevel
     * @param array $progressionByComplexity
     * @return string|null
     */
    private function determineNextLevel(string $currentLevel, array $progressionByComplexity): ?string
    {
        switch ($currentLevel) {
            case 'debutant':
                if (isset($progressionByComplexity['debutant']) && $progressionByComplexity['debutant']['completed'] >= 3) {
                    return 'intermediaire';
                }
                break;
            case 'intermediaire':
                if (isset($progressionByComplexity['intermediaire']) && $progressionByComplexity['intermediaire']['completed'] >= 3) {
                    return 'avance';
                }
                break;
            case 'avance':
                return null; // Max level reached
        }

        return null;
    }

    /**
     * Get the next complexity level
     *
     * @param string $currentComplexity
     * @param array $completedByComplexity
     * @return string|null
     */
    private function getNextComplexity(string $currentComplexity, array $completedByComplexity): ?string
    {
        $requiredCompletions = 3; // Number of missions required before progressing

        switch ($currentComplexity) {
            case 'debutant':
                if (($completedByComplexity['debutant'] ?? 0) >= $requiredCompletions) {
                    return 'intermediaire';
                }
                break;
            case 'intermediaire':
                if (($completedByComplexity['intermediaire'] ?? 0) >= $requiredCompletions) {
                    return 'avance';
                }
                break;
            case 'avance':
                return null; // Max complexity reached
        }

        return null;
    }

    /**
     * Get the next phase/term
     *
     * @param string $currentPhase
     * @param array $completedByTerm
     * @return string|null
     */
    private function getNextPhase(string $currentPhase, array $completedByTerm): ?string
    {
        $requiredCompletions = 2; // Number of missions required before progressing

        switch ($currentPhase) {
            case 'court':
                if (($completedByTerm['court'] ?? 0) >= $requiredCompletions) {
                    return 'moyen';
                }
                break;
            case 'moyen':
                if (($completedByTerm['moyen'] ?? 0) >= $requiredCompletions) {
                    return 'long';
                }
                break;
            case 'long':
                return null; // Max phase reached
        }

        return null;
    }

    /**
     * Generate recommended mission order
     *
     * @param array $progressionPath
     * @return array
     */
    private function generateRecommendedOrder(array $progressionPath): array
    {
        $order = [];

        // Current level missions
        $order[] = [
            'phase' => $progressionPath['current_phase'],
            'complexity' => $progressionPath['current_complexity'],
            'priority' => 'high',
            'description' => 'Continuer au niveau actuel'
        ];

        // Next complexity if available
        if ($progressionPath['next_complexity']) {
            $order[] = [
                'phase' => $progressionPath['current_phase'],
                'complexity' => $progressionPath['next_complexity'],
                'priority' => 'medium',
                'description' => 'Progression en complexité'
            ];
        }

        // Next phase if available
        if ($progressionPath['next_phase']) {
            $order[] = [
                'phase' => $progressionPath['next_phase'],
                'complexity' => 'debutant',
                'priority' => 'medium',
                'description' => 'Progression vers le ' . $progressionPath['next_phase']
            ];
        }

        return $order;
    }

    /**
     * Validate progression compliance with Qualiopi requirements
     *
     * @param Student $student
     * @return array
     */
    public function validateQualiopiCompliance(Student $student): array
    {
        $completedMissions = $this->getCompletedMissionsByStudent($student);
        $compliance = [
            'valid' => true,
            'issues' => [],
            'recommendations' => [],
        ];

        // Check if all terms are covered (Qualiopi requirement for progressivity)
        $termsCovered = [];
        foreach ($completedMissions as $assignment) {
            $term = $assignment->getMission()->getTerm();
            $termsCovered[$term] = true;
        }

        $requiredTerms = ['court', 'moyen', 'long'];
        $missingTerms = array_diff($requiredTerms, array_keys($termsCovered));

        if (!empty($missingTerms)) {
            $compliance['valid'] = false;
            $compliance['issues'][] = 'Termes manquants: ' . implode(', ', $missingTerms);
            $compliance['recommendations'][] = 'Assigner des missions ' . implode(' et ', $missingTerms) . ' terme';
        }

        // Check complexity progression
        $complexitiesCovered = [];
        foreach ($completedMissions as $assignment) {
            $complexity = $assignment->getMission()->getComplexity();
            $complexitiesCovered[$complexity] = true;
        }

        if (count($completedMissions) >= 5 && !isset($complexitiesCovered['intermediaire'])) {
            $compliance['issues'][] = 'Progression en complexité insuffisante';
            $compliance['recommendations'][] = 'Introduire des missions de niveau intermédiaire';
        }

        return $compliance;
    }

    /**
     * Get progression statistics for reporting
     *
     * @param Student $student
     * @return array
     */
    public function getProgressionStatistics(Student $student): array
    {
        $completedMissions = $this->getCompletedMissionsByStudent($student);
        $totalDuration = 0;
        $termBreakdown = ['court' => 0, 'moyen' => 0, 'long' => 0];
        $complexityBreakdown = ['debutant' => 0, 'intermediaire' => 0, 'avance' => 0];
        $averageRating = 0;
        $ratingCount = 0;

        foreach ($completedMissions as $assignment) {
            $mission = $assignment->getMission();
            $totalDuration += $assignment->getDurationInDays();
            $termBreakdown[$mission->getTerm()]++;
            $complexityBreakdown[$mission->getComplexity()]++;

            if ($assignment->getMentorRating()) {
                $averageRating += $assignment->getMentorRating();
                $ratingCount++;
            }
        }

        return [
            'total_missions' => count($completedMissions),
            'total_duration_days' => $totalDuration,
            'average_duration' => count($completedMissions) > 0 ? $totalDuration / count($completedMissions) : 0,
            'term_breakdown' => $termBreakdown,
            'complexity_breakdown' => $complexityBreakdown,
            'average_rating' => $ratingCount > 0 ? $averageRating / $ratingCount : 0,
            'progression_rate' => $this->calculateProgressionRate($student),
        ];
    }

    /**
     * Calculate the progression rate (missions per month)
     *
     * @param Student $student
     * @return float
     */
    private function calculateProgressionRate(Student $student): float
    {
        $completedMissions = $this->getCompletedMissionsByStudent($student);
        
        if (empty($completedMissions)) {
            return 0.0;
        }

        $firstMission = end($completedMissions);
        $lastMission = reset($completedMissions);
        
        $startDate = $firstMission->getStartDate();
        $endDate = $lastMission->getEndDate();
        
        $interval = $startDate->diff($endDate);
        $months = $interval->y * 12 + $interval->m + ($interval->d / 30);
        
        return $months > 0 ? count($completedMissions) / $months : 0.0;
    }
}
