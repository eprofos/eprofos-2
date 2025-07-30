<?php

declare(strict_types=1);

namespace App\Service\Student;

use App\Entity\Student\ExerciseSubmission;
use App\Entity\Training\Exercise;
use App\Entity\User\Student;
use App\Repository\Student\ExerciseSubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for handling exercise submissions and grading.
 */
class ExerciseSubmissionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExerciseSubmissionRepository $submissionRepository
    ) {
    }

    /**
     * Create or get existing draft submission for a student and exercise.
     */
    public function getOrCreateSubmission(Student $student, Exercise $exercise): ExerciseSubmission
    {
        // Check for existing draft submission
        $submission = $this->submissionRepository->findByStudentAndExercise($student, $exercise);
        
        if ($submission && $submission->canBeEdited()) {
            return $submission;
        }

        // Create new submission
        $submission = new ExerciseSubmission();
        $submission->setStudent($student);
        $submission->setExercise($exercise);
        $submission->setAttemptNumber(
            $this->submissionRepository->getNextAttemptNumber($student, $exercise)
        );

        // Set submission type based on exercise type
        $submissionType = $this->determineSubmissionType($exercise);
        $submission->setType($submissionType);

        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        return $submission;
    }

    /**
     * Save submission data (auto-save functionality).
     */
    public function saveSubmissionData(ExerciseSubmission $submission, array $data): void
    {
        if (!$submission->canBeEdited()) {
            throw new \InvalidArgumentException('This submission cannot be edited anymore.');
        }

        $submission->updateSubmissionData($data);
        $this->entityManager->flush();
    }

    /**
     * Submit an exercise for grading.
     */
    public function submitExercise(ExerciseSubmission $submission): void
    {
        if (!$submission->canBeEdited()) {
            throw new \InvalidArgumentException('This submission cannot be submitted anymore.');
        }

        $submission->submit();
        
        // Auto-grade if possible
        $this->attemptAutoGrading($submission);
        
        $this->entityManager->flush();
    }

    /**
     * Grade a submission manually.
     */
    public function gradeSubmission(ExerciseSubmission $submission, int $score, ?string $feedback = null): void
    {
        if (!$submission->canBeGraded()) {
            throw new \InvalidArgumentException('This submission cannot be graded.');
        }

        $exercise = $submission->getExercise();
        $maxPoints = $exercise->getMaxPoints();
        
        if ($maxPoints !== null && $score > $maxPoints) {
            throw new \InvalidArgumentException('Score cannot exceed maximum points.');
        }

        $submission->grade($score, $feedback);
        $this->entityManager->flush();
    }

    /**
     * Check if student can attempt an exercise.
     */
    public function canStudentAttempt(Student $student, Exercise $exercise): bool
    {
        // Check if there's already a draft submission
        $existingSubmission = $this->submissionRepository->findByStudentAndExercise($student, $exercise);
        
        // Allow if no submission exists or current submission is editable
        return $existingSubmission === null || $existingSubmission->canBeEdited();
    }

    /**
     * Get all submissions for a student and exercise.
     */
    public function getStudentSubmissions(Student $student, Exercise $exercise): array
    {
        return $this->submissionRepository->findAllByStudentAndExercise($student, $exercise);
    }

    /**
     * Get student's best score for an exercise.
     */
    public function getStudentBestScore(Student $student, Exercise $exercise): ?int
    {
        return $this->submissionRepository->getBestScore($student, $exercise);
    }

    /**
     * Check if student has passed an exercise.
     */
    public function hasStudentPassed(Student $student, Exercise $exercise): bool
    {
        return $this->submissionRepository->hasStudentPassed($student, $exercise);
    }

    /**
     * Get exercise statistics.
     */
    public function getExerciseStatistics(Exercise $exercise): array
    {
        return $this->submissionRepository->getExerciseStatistics($exercise);
    }

    /**
     * Determine submission type based on exercise type.
     */
    private function determineSubmissionType(Exercise $exercise): string
    {
        return match ($exercise->getType()) {
            Exercise::TYPE_PRACTICAL => ExerciseSubmission::TYPE_PRACTICAL,
            Exercise::TYPE_CASE_STUDY => ExerciseSubmission::TYPE_FILE,
            default => ExerciseSubmission::TYPE_TEXT,
        };
    }

    /**
     * Attempt automatic grading for objective exercises.
     */
    private function attemptAutoGrading(ExerciseSubmission $submission): void
    {
        $exercise = $submission->getExercise();
        
        // Only auto-grade simple exercises for now
        if ($submission->getType() === ExerciseSubmission::TYPE_TEXT) {
            $autoScore = $this->calculateAutoScore($submission);
            if ($autoScore !== null) {
                $submission->setAutoScore($autoScore);
                
                // For simple exercises, auto-score becomes the final score
                if ($exercise->getType() === Exercise::TYPE_THEORETICAL) {
                    $submission->grade($autoScore, 'Évaluation automatique');
                }
            }
        }
    }

    /**
     * Calculate automatic score based on objective criteria.
     */
    private function calculateAutoScore(ExerciseSubmission $submission): ?int
    {
        $exercise = $submission->getExercise();
        $submissionData = $submission->getSubmissionData();
        
        if (!isset($submissionData['content'])) {
            return 0;
        }

        $content = $submissionData['content'];
        $maxPoints = $exercise->getMaxPoints() ?? 10;
        
        // Simple scoring based on content length and keywords
        $score = 0;
        
        // Basic length check (minimum effort)
        $wordCount = str_word_count($content);
        if ($wordCount >= 50) {
            $score += (int) ($maxPoints * 0.3); // 30% for minimum length
        }
        
        // Check for key concepts from exercise description
        $description = strtolower($exercise->getDescription());
        $contentLower = strtolower($content);
        
        // Extract keywords from exercise description
        $keywords = $this->extractKeywords($description);
        $foundKeywords = 0;
        
        foreach ($keywords as $keyword) {
            if (str_contains($contentLower, $keyword)) {
                $foundKeywords++;
            }
        }
        
        if (count($keywords) > 0) {
            $keywordScore = ($foundKeywords / count($keywords)) * ($maxPoints * 0.7);
            $score += (int) $keywordScore;
        }
        
        return min($score, $maxPoints);
    }

    /**
     * Extract keywords from exercise description for auto-scoring.
     */
    private function extractKeywords(string $text): array
    {
        // Simple keyword extraction - remove common words
        $stopWords = ['le', 'la', 'les', 'de', 'du', 'des', 'et', 'ou', 'un', 'une', 'dans', 'sur', 'pour', 'avec', 'par'];
        $words = str_word_count($text, 1, 'àáâäèéêëïîôöùûüç');
        
        $keywords = [];
        foreach ($words as $word) {
            $word = strtolower($word);
            if (strlen($word) > 3 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
}
