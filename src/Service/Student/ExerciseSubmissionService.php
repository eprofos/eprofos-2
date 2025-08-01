<?php

declare(strict_types=1);

namespace App\Service\Student;

use App\Entity\Student\ExerciseSubmission;
use App\Entity\Training\Exercise;
use App\Entity\User\Student;
use App\Repository\Student\ExerciseSubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for handling exercise submissions and grading.
 */
class ExerciseSubmissionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExerciseSubmissionRepository $submissionRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create or get existing draft submission for a student and exercise.
     */
    public function getOrCreateSubmission(Student $student, Exercise $exercise): ExerciseSubmission
    {
        $this->logger->info('Starting getOrCreateSubmission process', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'exercise_id' => $exercise->getId(),
            'exercise_title' => $exercise->getTitle(),
            'exercise_type' => $exercise->getType()
        ]);

        try {
            // Check for existing draft submission
            $this->logger->debug('Searching for existing submission', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId()
            ]);

            $submission = $this->submissionRepository->findByStudentAndExercise($student, $exercise);
            
            if ($submission && $submission->canBeEdited()) {
                $this->logger->info('Found existing editable submission', [
                    'submission_id' => $submission->getId(),
                    'submission_status' => $submission->getStatus(),
                    'attempt_number' => $submission->getAttemptNumber(),
                    'created_at' => $submission->getCreatedAt()?->format('Y-m-d H:i:s')
                ]);
                return $submission;
            }

            // Create new submission
            $this->logger->info('Creating new submission', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'existing_submission_found' => $submission !== null,
                'existing_submission_editable' => $submission?->canBeEdited() ?? false
            ]);

            $submission = new ExerciseSubmission();
            $submission->setStudent($student);
            $submission->setExercise($exercise);

            // Get next attempt number
            $attemptNumber = $this->submissionRepository->getNextAttemptNumber($student, $exercise);
            $submission->setAttemptNumber($attemptNumber);

            $this->logger->debug('Set attempt number for new submission', [
                'attempt_number' => $attemptNumber,
                'submission_id' => $submission->getId()
            ]);

            // Set submission type based on exercise type
            $submissionType = $this->determineSubmissionType($exercise);
            $submission->setType($submissionType);

            $this->logger->debug('Determined submission type', [
                'exercise_type' => $exercise->getType(),
                'submission_type' => $submissionType
            ]);

            $this->entityManager->persist($submission);
            $this->entityManager->flush();

            $this->logger->info('Successfully created new submission', [
                'submission_id' => $submission->getId(),
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'submission_type' => $submissionType,
                'attempt_number' => $attemptNumber
            ]);

            return $submission;

        } catch (\Exception $e) {
            $this->logger->error('Error in getOrCreateSubmission', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException(
                sprintf(
                    'Failed to create or retrieve submission for student %d and exercise %d: %s',
                    $student->getId(),
                    $exercise->getId(),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Save submission data (auto-save functionality).
     */
    public function saveSubmissionData(ExerciseSubmission $submission, array $data): void
    {
        $this->logger->info('Starting saveSubmissionData process', [
            'submission_id' => $submission->getId(),
            'student_id' => $submission->getStudent()->getId(),
            'exercise_id' => $submission->getExercise()->getId(),
            'submission_status' => $submission->getStatus(),
            'data_keys' => array_keys($data),
            'data_size' => count($data)
        ]);

        try {
            if (!$submission->canBeEdited()) {
                $this->logger->warning('Attempt to save data to non-editable submission', [
                    'submission_id' => $submission->getId(),
                    'submission_status' => $submission->getStatus(),
                    'student_id' => $submission->getStudent()->getId(),
                    'exercise_id' => $submission->getExercise()->getId()
                ]);

                throw new \InvalidArgumentException('This submission cannot be edited anymore.');
            }

            $this->logger->debug('Validating submission data before save', [
                'submission_id' => $submission->getId(),
                'current_data' => $submission->getSubmissionData(),
                'new_data' => $data
            ]);

            $submission->updateSubmissionData($data);
            $this->entityManager->flush();

            $this->logger->info('Successfully saved submission data', [
                'submission_id' => $submission->getId(),
                'student_id' => $submission->getStudent()->getId(),
                'exercise_id' => $submission->getExercise()->getId(),
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Validation error in saveSubmissionData', [
                'submission_id' => $submission->getId(),
                'error_message' => $e->getMessage(),
                'submission_status' => $submission->getStatus()
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Error in saveSubmissionData', [
                'submission_id' => $submission->getId(),
                'student_id' => $submission->getStudent()->getId(),
                'exercise_id' => $submission->getExercise()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException(
                sprintf(
                    'Failed to save submission data for submission %d: %s',
                    $submission->getId(),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Submit an exercise for grading.
     */
    public function submitExercise(ExerciseSubmission $submission): void
    {
        $this->logger->info('Starting submitExercise process', [
            'submission_id' => $submission->getId(),
            'student_id' => $submission->getStudent()->getId(),
            'exercise_id' => $submission->getExercise()->getId(),
            'exercise_title' => $submission->getExercise()->getTitle(),
            'submission_status' => $submission->getStatus(),
            'attempt_number' => $submission->getAttemptNumber()
        ]);

        try {
            if (!$submission->canBeEdited()) {
                $this->logger->warning('Attempt to submit non-editable submission', [
                    'submission_id' => $submission->getId(),
                    'submission_status' => $submission->getStatus(),
                    'student_id' => $submission->getStudent()->getId(),
                    'exercise_id' => $submission->getExercise()->getId()
                ]);

                throw new \InvalidArgumentException('This submission cannot be submitted anymore.');
            }

            $this->logger->debug('Submitting exercise for grading', [
                'submission_id' => $submission->getId(),
                'current_status' => $submission->getStatus(),
                'submission_data' => $submission->getSubmissionData()
            ]);

            $submission->submit();
            
            $this->logger->info('Exercise submitted, attempting auto-grading', [
                'submission_id' => $submission->getId(),
                'new_status' => $submission->getStatus(),
                'exercise_type' => $submission->getExercise()->getType(),
                'submission_type' => $submission->getType()
            ]);

            // Auto-grade if possible
            $this->attemptAutoGrading($submission);
            
            $this->entityManager->flush();

            $this->logger->info('Successfully submitted exercise', [
                'submission_id' => $submission->getId(),
                'student_id' => $submission->getStudent()->getId(),
                'exercise_id' => $submission->getExercise()->getId(),
                'final_status' => $submission->getStatus(),
                'score' => $submission->getScore(),
                'auto_score' => $submission->getAutoScore(),
                'submitted_at' => $submission->getSubmittedAt()?->format('Y-m-d H:i:s')
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Validation error in submitExercise', [
                'submission_id' => $submission->getId(),
                'error_message' => $e->getMessage(),
                'submission_status' => $submission->getStatus()
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Error in submitExercise', [
                'submission_id' => $submission->getId(),
                'student_id' => $submission->getStudent()->getId(),
                'exercise_id' => $submission->getExercise()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException(
                sprintf(
                    'Failed to submit exercise for submission %d: %s',
                    $submission->getId(),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Grade a submission manually.
     */
    public function gradeSubmission(ExerciseSubmission $submission, int $score, ?string $feedback = null): void
    {
        $this->logger->info('Starting gradeSubmission process', [
            'submission_id' => $submission->getId(),
            'student_id' => $submission->getStudent()->getId(),
            'exercise_id' => $submission->getExercise()->getId(),
            'exercise_title' => $submission->getExercise()->getTitle(),
            'proposed_score' => $score,
            'feedback_provided' => $feedback !== null,
            'feedback_length' => $feedback ? strlen($feedback) : 0,
            'current_status' => $submission->getStatus(),
            'current_score' => $submission->getScore()
        ]);

        try {
            if (!$submission->canBeGraded()) {
                $this->logger->warning('Attempt to grade non-gradable submission', [
                    'submission_id' => $submission->getId(),
                    'submission_status' => $submission->getStatus(),
                    'student_id' => $submission->getStudent()->getId(),
                    'exercise_id' => $submission->getExercise()->getId()
                ]);

                throw new \InvalidArgumentException('This submission cannot be graded.');
            }

            $exercise = $submission->getExercise();
            $maxPoints = $exercise->getMaxPoints();
            
            $this->logger->debug('Validating score against exercise constraints', [
                'submission_id' => $submission->getId(),
                'proposed_score' => $score,
                'max_points' => $maxPoints,
                'exercise_type' => $exercise->getType()
            ]);

            if ($maxPoints !== null && $score > $maxPoints) {
                $this->logger->warning('Score exceeds maximum points', [
                    'submission_id' => $submission->getId(),
                    'proposed_score' => $score,
                    'max_points' => $maxPoints,
                    'exercise_id' => $exercise->getId()
                ]);

                throw new \InvalidArgumentException('Score cannot exceed maximum points.');
            }

            $previousScore = $submission->getScore();
            $submission->grade($score, $feedback);
            
            $this->entityManager->flush();

            $this->logger->info('Successfully graded submission', [
                'submission_id' => $submission->getId(),
                'student_id' => $submission->getStudent()->getId(),
                'exercise_id' => $submission->getExercise()->getId(),
                'previous_score' => $previousScore,
                'new_score' => $score,
                'feedback_provided' => $feedback !== null,
                'graded_at' => $submission->getGradedAt()?->format('Y-m-d H:i:s'),
                'final_status' => $submission->getStatus()
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Validation error in gradeSubmission', [
                'submission_id' => $submission->getId(),
                'proposed_score' => $score,
                'error_message' => $e->getMessage(),
                'submission_status' => $submission->getStatus()
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Error in gradeSubmission', [
                'submission_id' => $submission->getId(),
                'student_id' => $submission->getStudent()->getId(),
                'exercise_id' => $submission->getExercise()->getId(),
                'proposed_score' => $score,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException(
                sprintf(
                    'Failed to grade submission %d: %s',
                    $submission->getId(),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Check if student can attempt an exercise.
     */
    public function canStudentAttempt(Student $student, Exercise $exercise): bool
    {
        $this->logger->debug('Checking if student can attempt exercise', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'exercise_id' => $exercise->getId(),
            'exercise_title' => $exercise->getTitle(),
            'exercise_type' => $exercise->getType()
        ]);

        try {
            // Check if there's already a draft submission
            $existingSubmission = $this->submissionRepository->findByStudentAndExercise($student, $exercise);
            
            $canAttempt = $existingSubmission === null || $existingSubmission->canBeEdited();

            $this->logger->info('Student attempt check completed', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'can_attempt' => $canAttempt,
                'existing_submission_id' => $existingSubmission?->getId(),
                'existing_submission_status' => $existingSubmission?->getStatus(),
                'existing_submission_editable' => $existingSubmission?->canBeEdited()
            ]);

            return $canAttempt;

        } catch (\Exception $e) {
            $this->logger->error('Error checking student attempt eligibility', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            // Return false on error to be safe
            return false;
        }
    }

    /**
     * Get all submissions for a student and exercise.
     */
    public function getStudentSubmissions(Student $student, Exercise $exercise): array
    {
        $this->logger->debug('Retrieving student submissions', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'exercise_id' => $exercise->getId(),
            'exercise_title' => $exercise->getTitle()
        ]);

        try {
            $submissions = $this->submissionRepository->findAllByStudentAndExercise($student, $exercise);

            $this->logger->info('Successfully retrieved student submissions', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'submissions_count' => count($submissions),
                'submission_ids' => array_map(fn($s) => $s->getId(), $submissions)
            ]);

            return $submissions;

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving student submissions', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            throw new \RuntimeException(
                sprintf(
                    'Failed to retrieve submissions for student %d and exercise %d: %s',
                    $student->getId(),
                    $exercise->getId(),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Get student's best score for an exercise.
     */
    public function getStudentBestScore(Student $student, Exercise $exercise): ?int
    {
        $this->logger->debug('Retrieving student best score', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'exercise_id' => $exercise->getId(),
            'exercise_title' => $exercise->getTitle()
        ]);

        try {
            $bestScore = $this->submissionRepository->getBestScore($student, $exercise);

            $this->logger->info('Successfully retrieved student best score', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'best_score' => $bestScore
            ]);

            return $bestScore;

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving student best score', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            throw new \RuntimeException(
                sprintf(
                    'Failed to retrieve best score for student %d and exercise %d: %s',
                    $student->getId(),
                    $exercise->getId(),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Check if student has passed an exercise.
     */
    public function hasStudentPassed(Student $student, Exercise $exercise): bool
    {
        $this->logger->debug('Checking if student has passed exercise', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'exercise_id' => $exercise->getId(),
            'exercise_title' => $exercise->getTitle(),
            'exercise_max_points' => $exercise->getMaxPoints()
        ]);

        try {
            $hasPassed = $this->submissionRepository->hasStudentPassed($student, $exercise);

            $this->logger->info('Student pass status determined', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'has_passed' => $hasPassed,
                'exercise_max_points' => $exercise->getMaxPoints()
            ]);

            return $hasPassed;

        } catch (\Exception $e) {
            $this->logger->error('Error checking student pass status', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            throw new \RuntimeException(
                sprintf(
                    'Failed to check pass status for student %d and exercise %d: %s',
                    $student->getId(),
                    $exercise->getId(),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Get exercise statistics.
     */
    public function getExerciseStatistics(Exercise $exercise): array
    {
        $this->logger->debug('Retrieving exercise statistics', [
            'exercise_id' => $exercise->getId(),
            'exercise_title' => $exercise->getTitle(),
            'exercise_type' => $exercise->getType(),
            'exercise_max_points' => $exercise->getMaxPoints()
        ]);

        try {
            $statistics = $this->submissionRepository->getExerciseStatistics($exercise);

            $this->logger->info('Successfully retrieved exercise statistics', [
                'exercise_id' => $exercise->getId(),
                'statistics_keys' => array_keys($statistics),
                'total_submissions' => $statistics['total_submissions'] ?? 0,
                'average_score' => $statistics['average_score'] ?? null,
                'completion_rate' => $statistics['completion_rate'] ?? null
            ]);

            return $statistics;

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving exercise statistics', [
                'exercise_id' => $exercise->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            throw new \RuntimeException(
                sprintf(
                    'Failed to retrieve statistics for exercise %d: %s',
                    $exercise->getId(),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Determine submission type based on exercise type.
     */
    private function determineSubmissionType(Exercise $exercise): string
    {
        $this->logger->debug('Determining submission type from exercise type', [
            'exercise_id' => $exercise->getId(),
            'exercise_type' => $exercise->getType(),
            'exercise_title' => $exercise->getTitle()
        ]);

        try {
            $submissionType = match ($exercise->getType()) {
                Exercise::TYPE_PRACTICAL => ExerciseSubmission::TYPE_PRACTICAL,
                Exercise::TYPE_CASE_STUDY => ExerciseSubmission::TYPE_FILE,
                default => ExerciseSubmission::TYPE_TEXT,
            };

            $this->logger->info('Successfully determined submission type', [
                'exercise_id' => $exercise->getId(),
                'exercise_type' => $exercise->getType(),
                'submission_type' => $submissionType
            ]);

            return $submissionType;

        } catch (\Exception $e) {
            $this->logger->error('Error determining submission type', [
                'exercise_id' => $exercise->getId(),
                'exercise_type' => $exercise->getType(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);

            // Default to text type on error
            $defaultType = ExerciseSubmission::TYPE_TEXT;
            $this->logger->warning('Falling back to default submission type', [
                'exercise_id' => $exercise->getId(),
                'default_type' => $defaultType
            ]);

            return $defaultType;
        }
    }

    /**
     * Attempt automatic grading for objective exercises.
     */
    private function attemptAutoGrading(ExerciseSubmission $submission): void
    {
        $exercise = $submission->getExercise();
        
        $this->logger->info('Attempting automatic grading', [
            'submission_id' => $submission->getId(),
            'exercise_id' => $exercise->getId(),
            'exercise_type' => $exercise->getType(),
            'submission_type' => $submission->getType(),
            'exercise_max_points' => $exercise->getMaxPoints()
        ]);

        try {
            // Only auto-grade simple exercises for now
            if ($submission->getType() === ExerciseSubmission::TYPE_TEXT) {
                $this->logger->debug('Calculating auto score for text submission', [
                    'submission_id' => $submission->getId(),
                    'submission_data' => $submission->getSubmissionData()
                ]);

                $autoScore = $this->calculateAutoScore($submission);
                
                if ($autoScore !== null) {
                    $submission->setAutoScore($autoScore);
                    
                    $this->logger->info('Auto score calculated', [
                        'submission_id' => $submission->getId(),
                        'auto_score' => $autoScore,
                        'exercise_max_points' => $exercise->getMaxPoints()
                    ]);

                    // For simple exercises, auto-score becomes the final score
                    if ($exercise->getType() === Exercise::TYPE_THEORETICAL) {
                        $submission->grade($autoScore, 'Évaluation automatique');
                        
                        $this->logger->info('Automatic grading completed with final score', [
                            'submission_id' => $submission->getId(),
                            'final_score' => $autoScore,
                            'exercise_type' => $exercise->getType()
                        ]);
                    }
                } else {
                    $this->logger->warning('Auto score calculation returned null', [
                        'submission_id' => $submission->getId(),
                        'exercise_id' => $exercise->getId()
                    ]);
                }
            } else {
                $this->logger->debug('Skipping auto-grading for non-text submission type', [
                    'submission_id' => $submission->getId(),
                    'submission_type' => $submission->getType()
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error during automatic grading attempt', [
                'submission_id' => $submission->getId(),
                'exercise_id' => $exercise->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            // Don't throw exception for auto-grading failures, just log and continue
            $this->logger->warning('Auto-grading failed, submission will require manual grading', [
                'submission_id' => $submission->getId(),
                'exercise_id' => $exercise->getId()
            ]);
        }
    }

    /**
     * Calculate automatic score based on objective criteria.
     */
    private function calculateAutoScore(ExerciseSubmission $submission): ?int
    {
        $exercise = $submission->getExercise();
        $submissionData = $submission->getSubmissionData();
        
        $this->logger->debug('Starting auto score calculation', [
            'submission_id' => $submission->getId(),
            'exercise_id' => $exercise->getId(),
            'exercise_max_points' => $exercise->getMaxPoints(),
            'submission_data_keys' => array_keys($submissionData)
        ]);

        try {
            if (!isset($submissionData['content'])) {
                $this->logger->warning('No content found in submission data for auto scoring', [
                    'submission_id' => $submission->getId(),
                    'submission_data' => $submissionData
                ]);
                return 0;
            }

            $content = $submissionData['content'];
            $maxPoints = $exercise->getMaxPoints() ?? 10;
            
            $this->logger->debug('Content analysis for auto scoring', [
                'submission_id' => $submission->getId(),
                'content_length' => strlen($content),
                'max_points' => $maxPoints
            ]);

            // Simple scoring based on content length and keywords
            $score = 0;
            
            // Basic length check (minimum effort)
            $wordCount = str_word_count($content);
            if ($wordCount >= 50) {
                $lengthScore = (int) ($maxPoints * 0.3); // 30% for minimum length
                $score += $lengthScore;
                
                $this->logger->debug('Length score calculated', [
                    'submission_id' => $submission->getId(),
                    'word_count' => $wordCount,
                    'length_score' => $lengthScore
                ]);
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
            
            $this->logger->debug('Keyword analysis completed', [
                'submission_id' => $submission->getId(),
                'total_keywords' => count($keywords),
                'found_keywords' => $foundKeywords,
                'keywords' => $keywords
            ]);

            if (count($keywords) > 0) {
                $keywordScore = ($foundKeywords / count($keywords)) * ($maxPoints * 0.7);
                $score += (int) $keywordScore;
                
                $this->logger->debug('Keyword score calculated', [
                    'submission_id' => $submission->getId(),
                    'keyword_score' => (int) $keywordScore,
                    'keyword_ratio' => $foundKeywords / count($keywords)
                ]);
            }
            
            $finalScore = min($score, $maxPoints);

            $this->logger->info('Auto score calculation completed', [
                'submission_id' => $submission->getId(),
                'exercise_id' => $exercise->getId(),
                'raw_score' => $score,
                'final_score' => $finalScore,
                'max_points' => $maxPoints,
                'word_count' => $wordCount,
                'keywords_found' => $foundKeywords,
                'total_keywords' => count($keywords)
            ]);

            return $finalScore;

        } catch (\Exception $e) {
            $this->logger->error('Error calculating auto score', [
                'submission_id' => $submission->getId(),
                'exercise_id' => $exercise->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'submission_data' => $submissionData
            ]);

            return null;
        }
    }

    /**
     * Extract keywords from exercise description for auto-scoring.
     */
    private function extractKeywords(string $text): array
    {
        $this->logger->debug('Extracting keywords from text', [
            'text_length' => strlen($text),
            'text_preview' => substr($text, 0, 100)
        ]);

        try {
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
            
            $uniqueKeywords = array_unique($keywords);

            $this->logger->debug('Keywords extraction completed', [
                'total_words' => count($words),
                'filtered_keywords' => count($keywords),
                'unique_keywords' => count($uniqueKeywords),
                'keywords_sample' => array_slice($uniqueKeywords, 0, 10)
            ]);

            return $uniqueKeywords;

        } catch (\Exception $e) {
            $this->logger->error('Error extracting keywords', [
                'text_length' => strlen($text),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            // Return empty array on error
            return [];
        }
    }
}
