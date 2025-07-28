<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\MissionAssignment;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Repository\Alternance\MissionAssignmentRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service for managing mission evaluations.
 *
 * Handles bidirectional evaluation system between mentors and students,
 * evaluation analytics, and compliance reporting for Qualiopi requirements.
 */
class MissionEvaluationService
{
    private EntityManagerInterface $entityManager;

    private MissionAssignmentRepository $assignmentRepository;

    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        MissionAssignmentRepository $assignmentRepository,
        LoggerInterface $logger,
    ) {
        $this->entityManager = $entityManager;
        $this->assignmentRepository = $assignmentRepository;
        $this->logger = $logger;
    }

    /**
     * Submit mentor evaluation for a completed assignment.
     */
    public function submitMentorEvaluation(MissionAssignment $assignment, array $evaluationData): MissionAssignment
    {
        if ($assignment->getStatus() !== 'terminee') {
            throw new RuntimeException('Only completed assignments can be evaluated.');
        }

        // Validate evaluation data
        $this->validateMentorEvaluationData($evaluationData);

        $assignment->setMentorRating($evaluationData['rating']);
        $assignment->setMentorFeedback($evaluationData['feedback']);

        if (isset($evaluationData['competenciesAcquired'])) {
            $assignment->setCompetenciesAcquired($evaluationData['competenciesAcquired']);
        }

        if (isset($evaluationData['achievements'])) {
            $existingAchievements = $assignment->getAchievements();
            $newAchievements = array_merge($existingAchievements, $evaluationData['achievements']);
            $assignment->setAchievements($newAchievements);
        }

        $this->entityManager->flush();

        $this->logger->info('Mentor evaluation submitted', [
            'assignment_id' => $assignment->getId(),
            'mentor_id' => $assignment->getMission()->getSupervisor()->getId(),
            'rating' => $evaluationData['rating'],
            'competencies_count' => count($evaluationData['competenciesAcquired'] ?? []),
        ]);

        return $assignment;
    }

    /**
     * Submit student feedback for a completed assignment.
     */
    public function submitStudentFeedback(MissionAssignment $assignment, array $feedbackData): MissionAssignment
    {
        if ($assignment->getStatus() !== 'terminee') {
            throw new RuntimeException('Only completed assignments can receive student feedback.');
        }

        // Validate feedback data
        $this->validateStudentFeedbackData($feedbackData);

        $assignment->setStudentSatisfaction($feedbackData['satisfaction']);
        $assignment->setStudentFeedback($feedbackData['feedback']);

        if (isset($feedbackData['difficulties'])) {
            $existingDifficulties = $assignment->getDifficulties();
            $newDifficulties = array_merge($existingDifficulties, $feedbackData['difficulties']);
            $assignment->setDifficulties($newDifficulties);
        }

        $this->entityManager->flush();

        $this->logger->info('Student feedback submitted', [
            'assignment_id' => $assignment->getId(),
            'student_id' => $assignment->getStudent()->getId(),
            'satisfaction' => $feedbackData['satisfaction'],
        ]);

        return $assignment;
    }

    /**
     * Get evaluation statistics for a mentor.
     */
    public function getMentorEvaluationStats(Mentor $mentor, ?DateTimeInterface $startDate = null, ?DateTimeInterface $endDate = null): array
    {
        $assignments = $this->assignmentRepository->findByMentor($mentor, 'terminee');

        if ($startDate && $endDate) {
            $assignments = array_filter($assignments, static fn ($assignment) => $assignment->getEndDate() >= $startDate && $assignment->getEndDate() <= $endDate);
        }

        $totalAssignments = count($assignments);
        $evaluatedAssignments = 0;
        $totalRating = 0;
        $totalSatisfaction = 0;
        $ratingCount = 0;
        $satisfactionCount = 0;
        $ratingDistribution = array_fill(1, 10, 0);
        $satisfactionDistribution = array_fill(1, 10, 0);

        foreach ($assignments as $assignment) {
            if ($assignment->getMentorRating() !== null) {
                $evaluatedAssignments++;
                $rating = $assignment->getMentorRating();
                $totalRating += $rating;
                $ratingCount++;
                $ratingDistribution[$rating]++;
            }

            if ($assignment->getStudentSatisfaction() !== null) {
                $satisfaction = $assignment->getStudentSatisfaction();
                $totalSatisfaction += $satisfaction;
                $satisfactionCount++;
                $satisfactionDistribution[$satisfaction]++;
            }
        }

        return [
            'total_assignments' => $totalAssignments,
            'evaluated_assignments' => $evaluatedAssignments,
            'evaluation_rate' => $totalAssignments > 0 ? ($evaluatedAssignments / $totalAssignments) * 100 : 0,
            'average_rating_given' => $ratingCount > 0 ? $totalRating / $ratingCount : 0,
            'average_satisfaction_received' => $satisfactionCount > 0 ? $totalSatisfaction / $satisfactionCount : 0,
            'rating_distribution' => $ratingDistribution,
            'satisfaction_distribution' => $satisfactionDistribution,
        ];
    }

    /**
     * Get evaluation statistics for a student.
     */
    public function getStudentEvaluationStats(Student $student, ?DateTimeInterface $startDate = null, ?DateTimeInterface $endDate = null): array
    {
        $assignments = $this->assignmentRepository->findBy(['student' => $student, 'status' => 'terminee']);

        if ($startDate && $endDate) {
            $assignments = array_filter($assignments, static fn ($assignment) => $assignment->getEndDate() >= $startDate && $assignment->getEndDate() <= $endDate);
        }

        $totalAssignments = count($assignments);
        $evaluatedByMentor = 0;
        $feedbackGiven = 0;
        $totalRatingReceived = 0;
        $totalSatisfactionGiven = 0;
        $ratingReceivedCount = 0;
        $satisfactionGivenCount = 0;
        $competenciesAcquired = [];

        foreach ($assignments as $assignment) {
            if ($assignment->getMentorRating() !== null) {
                $evaluatedByMentor++;
                $totalRatingReceived += $assignment->getMentorRating();
                $ratingReceivedCount++;
            }

            if ($assignment->getStudentSatisfaction() !== null) {
                $feedbackGiven++;
                $totalSatisfactionGiven += $assignment->getStudentSatisfaction();
                $satisfactionGivenCount++;
            }

            // Collect all competencies acquired
            $assignmentCompetencies = $assignment->getCompetenciesAcquired();
            foreach ($assignmentCompetencies as $competency) {
                if (!in_array($competency, $competenciesAcquired, true)) {
                    $competenciesAcquired[] = $competency;
                }
            }
        }

        return [
            'total_assignments' => $totalAssignments,
            'evaluated_by_mentor' => $evaluatedByMentor,
            'feedback_given' => $feedbackGiven,
            'evaluation_received_rate' => $totalAssignments > 0 ? ($evaluatedByMentor / $totalAssignments) * 100 : 0,
            'feedback_given_rate' => $totalAssignments > 0 ? ($feedbackGiven / $totalAssignments) * 100 : 0,
            'average_rating_received' => $ratingReceivedCount > 0 ? $totalRatingReceived / $ratingReceivedCount : 0,
            'average_satisfaction_given' => $satisfactionGivenCount > 0 ? $totalSatisfactionGiven / $satisfactionGivenCount : 0,
            'total_competencies_acquired' => count($competenciesAcquired),
            'competencies_list' => $competenciesAcquired,
        ];
    }

    /**
     * Find assignments requiring evaluation.
     */
    public function findAssignmentsRequiringEvaluation(?Mentor $mentor = null, ?int $daysOld = null): array
    {
        $assignments = $this->assignmentRepository->findRequiringFeedback($mentor);

        if ($daysOld) {
            $cutoffDate = new DateTime('-' . $daysOld . ' days');
            $assignments = array_filter($assignments, static fn ($assignment) => $assignment->getEndDate() <= $cutoffDate);
        }

        return [
            'assignments' => $assignments,
            'count' => count($assignments),
            'by_priority' => $this->categorizeByPriority($assignments),
        ];
    }

    /**
     * Find assignments requiring student feedback.
     */
    public function findAssignmentsRequiringStudentFeedback(?Student $student = null, ?int $daysOld = null): array
    {
        $qb = $this->assignmentRepository->createAssignmentQueryBuilder()
            ->where('ma.status = :completed')
            ->andWhere('(ma.studentFeedback IS NULL OR ma.studentSatisfaction IS NULL)')
            ->setParameter('completed', 'terminee')
        ;

        if ($student) {
            $qb->andWhere('ma.student = :student')
                ->setParameter('student', $student)
            ;
        }

        if ($daysOld) {
            $cutoffDate = new DateTime('-' . $daysOld . ' days');
            $qb->andWhere('ma.endDate <= :cutoffDate')
                ->setParameter('cutoffDate', $cutoffDate)
            ;
        }

        $assignments = $qb->orderBy('ma.endDate', 'ASC')->getQuery()->getResult();

        return [
            'assignments' => $assignments,
            'count' => count($assignments),
            'by_priority' => $this->categorizeByPriority($assignments),
        ];
    }

    /**
     * Generate evaluation report for Qualiopi compliance.
     */
    public function generateQualiopiEvaluationReport(DateTimeInterface $startDate, DateTimeInterface $endDate, ?Mentor $mentor = null): array
    {
        $assignments = $this->assignmentRepository->findByDateRange($startDate, $endDate, [
            'status' => 'terminee',
            'mentor' => $mentor,
        ]);

        $totalAssignments = count($assignments);
        $bidirectionalEvaluations = 0;
        $mentorEvaluations = 0;
        $studentFeedbacks = 0;
        $competenciesTracked = [];
        $satisfactionScores = [];
        $ratingScores = [];

        foreach ($assignments as $assignment) {
            $hasMentorEvaluation = $assignment->getMentorRating() !== null && !empty($assignment->getMentorFeedback());
            $hasStudentFeedback = $assignment->getStudentSatisfaction() !== null && !empty($assignment->getStudentFeedback());

            if ($hasMentorEvaluation) {
                $mentorEvaluations++;
                $ratingScores[] = $assignment->getMentorRating();
            }

            if ($hasStudentFeedback) {
                $studentFeedbacks++;
                $satisfactionScores[] = $assignment->getStudentSatisfaction();
            }

            if ($hasMentorEvaluation && $hasStudentFeedback) {
                $bidirectionalEvaluations++;
            }

            // Track competencies
            foreach ($assignment->getCompetenciesAcquired() as $competency) {
                $competenciesTracked[] = $competency;
            }
        }

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'total_assignments' => $totalAssignments,
            'evaluation_compliance' => [
                'mentor_evaluation_rate' => $totalAssignments > 0 ? ($mentorEvaluations / $totalAssignments) * 100 : 0,
                'student_feedback_rate' => $totalAssignments > 0 ? ($studentFeedbacks / $totalAssignments) * 100 : 0,
                'bidirectional_evaluation_rate' => $totalAssignments > 0 ? ($bidirectionalEvaluations / $totalAssignments) * 100 : 0,
            ],
            'quality_indicators' => [
                'average_mentor_rating' => !empty($ratingScores) ? array_sum($ratingScores) / count($ratingScores) : 0,
                'average_student_satisfaction' => !empty($satisfactionScores) ? array_sum($satisfactionScores) / count($satisfactionScores) : 0,
                'total_competencies_tracked' => count(array_unique($competenciesTracked)),
            ],
            'compliance_status' => [
                'meets_evaluation_requirement' => ($mentorEvaluations / max($totalAssignments, 1)) >= 0.8, // 80% threshold
                'meets_feedback_requirement' => ($studentFeedbacks / max($totalAssignments, 1)) >= 0.7, // 70% threshold
                'competency_tracking_adequate' => count(array_unique($competenciesTracked)) >= ($totalAssignments * 0.5), // At least 0.5 competencies per assignment
            ],
        ];
    }

    /**
     * Calculate evaluation trends over time.
     */
    public function calculateEvaluationTrends(?Mentor $mentor = null, int $months = 12): array
    {
        $trends = [];

        for ($i = $months; $i > 0; $i--) {
            $startDate = new DateTime("first day of -{$i} months");
            $endDate = new DateTime("last day of -{$i} months");

            $monthlyStats = $this->getMentorEvaluationStats($mentor, $startDate, $endDate);

            $trends[] = [
                'month' => $startDate->format('Y-m'),
                'evaluation_rate' => $monthlyStats['evaluation_rate'],
                'average_rating' => $monthlyStats['average_rating_given'],
                'average_satisfaction' => $monthlyStats['average_satisfaction_received'],
                'total_assignments' => $monthlyStats['total_assignments'],
            ];
        }

        return $trends;
    }

    /**
     * Get evaluation summary for dashboard.
     */
    public function getEvaluationSummary(Mentor $mentor): array
    {
        $stats = $this->getMentorEvaluationStats($mentor);
        $pendingEvaluations = $this->findAssignmentsRequiringEvaluation($mentor);

        return [
            'pending_evaluations' => $pendingEvaluations['count'],
            'evaluation_rate' => $stats['evaluation_rate'],
            'average_rating_given' => $stats['average_rating_given'],
            'average_satisfaction_received' => $stats['average_satisfaction_received'],
            'total_evaluated' => $stats['evaluated_assignments'],
        ];
    }

    /**
     * Validate mentor evaluation data.
     *
     * @throws InvalidArgumentException
     */
    private function validateMentorEvaluationData(array $data): void
    {
        if (!isset($data['rating']) || $data['rating'] < 1 || $data['rating'] > 10) {
            throw new InvalidArgumentException('Rating must be between 1 and 10.');
        }

        if (!isset($data['feedback']) || trim($data['feedback']) === '') {
            throw new InvalidArgumentException('Feedback is required.');
        }

        if (isset($data['competenciesAcquired']) && !is_array($data['competenciesAcquired'])) {
            throw new InvalidArgumentException('Competencies acquired must be an array.');
        }
    }

    /**
     * Validate student feedback data.
     *
     * @throws InvalidArgumentException
     */
    private function validateStudentFeedbackData(array $data): void
    {
        if (!isset($data['satisfaction']) || $data['satisfaction'] < 1 || $data['satisfaction'] > 10) {
            throw new InvalidArgumentException('Satisfaction rating must be between 1 and 10.');
        }

        if (!isset($data['feedback']) || trim($data['feedback']) === '') {
            throw new InvalidArgumentException('Feedback is required.');
        }

        if (isset($data['difficulties']) && !is_array($data['difficulties'])) {
            throw new InvalidArgumentException('Difficulties must be an array.');
        }
    }

    /**
     * Categorize assignments by evaluation priority.
     */
    private function categorizeByPriority(array $assignments): array
    {
        $high = [];
        $medium = [];
        $low = [];

        $now = new DateTime();

        foreach ($assignments as $assignment) {
            $daysSinceCompletion = $now->diff($assignment->getEndDate())->days;

            if ($daysSinceCompletion > 7) {
                $high[] = $assignment;
            } elseif ($daysSinceCompletion > 3) {
                $medium[] = $assignment;
            } else {
                $low[] = $assignment;
            }
        }

        return [
            'high' => $high,
            'medium' => $medium,
            'low' => $low,
        ];
    }
}
