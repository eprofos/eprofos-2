<?php

declare(strict_types=1);

namespace App\Service\Student;

use App\Entity\Student\QCMAttempt;
use App\Entity\Training\QCM;
use App\Entity\User\Student;
use App\Repository\Student\QCMAttemptRepository;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Service for handling QCM attempts and scoring.
 */
class QCMAttemptService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QCMAttemptRepository $attemptRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Start a new QCM attempt.
     */
    public function startAttempt(Student $student, QCM $qcm): QCMAttempt
    {
        $this->logger->info('Starting new QCM attempt', [
            'student_id' => $student->getId(),
            'student_email' => $student->getEmail(),
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'max_attempts' => $qcm->getMaxAttempts(),
            'time_limit_minutes' => $qcm->getTimeLimitMinutes(),
        ]);

        try {
            if (!$this->canStudentAttempt($student, $qcm)) {
                $this->logger->warning('Student cannot start new QCM attempt - maximum attempts reached or other restriction', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'existing_attempts' => $this->attemptRepository->countCompletedAttempts($student, $qcm),
                    'max_attempts' => $qcm->getMaxAttempts(),
                ]);

                throw new InvalidArgumentException('Student cannot start a new attempt for this QCM.');
            }

            $nextAttemptNumber = $this->attemptRepository->getNextAttemptNumber($student, $qcm);

            $this->logger->info('Creating new QCM attempt entity', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'attempt_number' => $nextAttemptNumber,
            ]);

            $attempt = new QCMAttempt();
            $attempt->setStudent($student);
            $attempt->setQcm($qcm);
            $attempt->setAttemptNumber($nextAttemptNumber);

            $this->entityManager->persist($attempt);
            $this->entityManager->flush();

            $this->logger->info('QCM attempt successfully started', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'attempt_number' => $nextAttemptNumber,
                'started_at' => $attempt->getStartedAt()?->format('Y-m-d H:i:s'),
                'expires_at' => $attempt->getExpiresAt()?->format('Y-m-d H:i:s'),
            ]);

            return $attempt;
        } catch (DBALException $e) {
            $this->logger->error('Database error while starting QCM attempt', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to start QCM attempt due to database error: ' . $e->getMessage(), 0, $e);
        } catch (InvalidArgumentException $e) {
            // Re-throw validation errors without wrapping
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error while starting QCM attempt', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Unexpected error while starting QCM attempt: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get or create active attempt for a student and QCM.
     */
    public function getOrCreateActiveAttempt(Student $student, QCM $qcm): QCMAttempt
    {
        $this->logger->info('Getting or creating active QCM attempt', [
            'student_id' => $student->getId(),
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
        ]);

        try {
            $activeAttempt = $this->attemptRepository->findActiveAttempt($student, $qcm);

            if ($activeAttempt) {
                $this->logger->info('Found existing active attempt', [
                    'attempt_id' => $activeAttempt->getId(),
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'started_at' => $activeAttempt->getStartedAt()?->format('Y-m-d H:i:s'),
                    'expires_at' => $activeAttempt->getExpiresAt()?->format('Y-m-d H:i:s'),
                    'has_expired' => $activeAttempt->hasExpired(),
                ]);

                // Check if expired
                if ($activeAttempt->hasExpired()) {
                    $this->logger->warning('Active attempt has expired, expiring it', [
                        'attempt_id' => $activeAttempt->getId(),
                        'student_id' => $student->getId(),
                        'qcm_id' => $qcm->getId(),
                        'expires_at' => $activeAttempt->getExpiresAt()?->format('Y-m-d H:i:s'),
                    ]);
                    $this->expireAttempt($activeAttempt);
                    $activeAttempt = null;
                }
            } else {
                $this->logger->info('No active attempt found', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                ]);
            }

            if (!$activeAttempt) {
                $this->logger->info('Creating new attempt as no active attempt available', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                ]);
                $activeAttempt = $this->startAttempt($student, $qcm);
            }

            $this->logger->info('Returning active attempt', [
                'attempt_id' => $activeAttempt->getId(),
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'status' => $activeAttempt->getStatus(),
            ]);

            return $activeAttempt;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Validation error while getting or creating active attempt', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error while getting or creating active attempt', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to get or create active QCM attempt: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Save answer for a question.
     */
    public function saveAnswer(QCMAttempt $attempt, int $questionIndex, array $answerIndices): void
    {
        $this->logger->info('Saving answer for QCM question', [
            'attempt_id' => $attempt->getId(),
            'student_id' => $attempt->getStudent()->getId(),
            'qcm_id' => $attempt->getQcm()->getId(),
            'question_index' => $questionIndex,
            'answer_indices' => $answerIndices,
            'attempt_status' => $attempt->getStatus(),
        ]);

        try {
            if (!$attempt->isActive()) {
                $this->logger->warning('Attempted to save answer for inactive attempt', [
                    'attempt_id' => $attempt->getId(),
                    'student_id' => $attempt->getStudent()->getId(),
                    'qcm_id' => $attempt->getQcm()->getId(),
                    'attempt_status' => $attempt->getStatus(),
                    'question_index' => $questionIndex,
                ]);

                throw new InvalidArgumentException('This attempt is not active anymore.');
            }

            $attempt->setAnswerForQuestion($questionIndex, $answerIndices);
            $this->entityManager->flush();

            $this->logger->info('Answer successfully saved', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'question_index' => $questionIndex,
                'answer_count' => count($answerIndices),
            ]);
        } catch (DBALException $e) {
            $this->logger->error('Database error while saving QCM answer', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'question_index' => $questionIndex,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            throw new RuntimeException('Failed to save answer due to database error: ' . $e->getMessage(), 0, $e);
        } catch (InvalidArgumentException $e) {
            // Re-throw validation errors without wrapping
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error while saving QCM answer', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'question_index' => $questionIndex,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Unexpected error while saving answer: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Submit QCM attempt.
     */
    public function submitAttempt(QCMAttempt $attempt): void
    {
        $this->logger->info('Submitting QCM attempt', [
            'attempt_id' => $attempt->getId(),
            'student_id' => $attempt->getStudent()->getId(),
            'qcm_id' => $attempt->getQcm()->getId(),
            'attempt_status' => $attempt->getStatus(),
            'started_at' => $attempt->getStartedAt()?->format('Y-m-d H:i:s'),
            'answers_count' => count($attempt->getAnswers() ?? []),
        ]);

        try {
            if (!$attempt->isActive()) {
                $this->logger->warning('Attempted to submit inactive attempt', [
                    'attempt_id' => $attempt->getId(),
                    'student_id' => $attempt->getStudent()->getId(),
                    'qcm_id' => $attempt->getQcm()->getId(),
                    'attempt_status' => $attempt->getStatus(),
                ]);

                throw new InvalidArgumentException('This attempt is not active anymore.');
            }

            $this->logger->info('Calculating score for attempt', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
            ]);

            // Calculate score
            $attempt->calculateScore();

            $this->logger->info('Score calculated, completing attempt', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'calculated_score' => $attempt->getScore(),
                'passing_score' => $attempt->getQcm()->getPassingScore(),
            ]);

            // Complete the attempt
            $attempt->complete();

            $this->entityManager->flush();

            $this->logger->info('QCM attempt successfully submitted', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'final_score' => $attempt->getScore(),
                'is_passed' => $attempt->isPassed(),
                'completed_at' => $attempt->getCompletedAt()?->format('Y-m-d H:i:s'),
                'duration_seconds' => $attempt->getTimeSpent(),
                'duration_minutes' => $attempt->getTimeSpent() ? round($attempt->getTimeSpent() / 60, 2) : null,
            ]);
        } catch (DBALException $e) {
            $this->logger->error('Database error while submitting QCM attempt', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            throw new RuntimeException('Failed to submit attempt due to database error: ' . $e->getMessage(), 0, $e);
        } catch (InvalidArgumentException $e) {
            // Re-throw validation errors without wrapping
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error while submitting QCM attempt', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Unexpected error while submitting attempt: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Abandon a QCM attempt.
     */
    public function abandonAttempt(QCMAttempt $attempt): void
    {
        $this->logger->info('Abandoning QCM attempt', [
            'attempt_id' => $attempt->getId(),
            'student_id' => $attempt->getStudent()->getId(),
            'qcm_id' => $attempt->getQcm()->getId(),
            'current_status' => $attempt->getStatus(),
            'started_at' => $attempt->getStartedAt()?->format('Y-m-d H:i:s'),
            'time_spent_seconds' => $attempt->getTimeSpent(),
        ]);

        try {
            if ($attempt->getStatus() !== QCMAttempt::STATUS_IN_PROGRESS) {
                $this->logger->warning('Attempted to abandon non-active attempt', [
                    'attempt_id' => $attempt->getId(),
                    'student_id' => $attempt->getStudent()->getId(),
                    'qcm_id' => $attempt->getQcm()->getId(),
                    'current_status' => $attempt->getStatus(),
                ]);

                throw new InvalidArgumentException('Only in-progress attempts can be abandoned.');
            }

            $attempt->abandon();
            $this->entityManager->flush();

            $this->logger->info('QCM attempt successfully abandoned', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'final_time_spent_seconds' => $attempt->getTimeSpent(),
                'final_time_spent_minutes' => $attempt->getTimeSpent() ? round($attempt->getTimeSpent() / 60, 2) : null,
            ]);
        } catch (DBALException $e) {
            $this->logger->error('Database error while abandoning QCM attempt', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            throw new RuntimeException('Failed to abandon attempt due to database error: ' . $e->getMessage(), 0, $e);
        } catch (InvalidArgumentException $e) {
            // Re-throw validation errors without wrapping
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error while abandoning QCM attempt', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Unexpected error while abandoning attempt: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Expire a QCM attempt.
     */
    public function expireAttempt(QCMAttempt $attempt): void
    {
        $this->logger->info('Expiring QCM attempt', [
            'attempt_id' => $attempt->getId(),
            'student_id' => $attempt->getStudent()->getId(),
            'qcm_id' => $attempt->getQcm()->getId(),
            'current_status' => $attempt->getStatus(),
            'started_at' => $attempt->getStartedAt()?->format('Y-m-d H:i:s'),
            'expires_at' => $attempt->getExpiresAt()?->format('Y-m-d H:i:s'),
            'has_expired' => $attempt->hasExpired(),
        ]);

        try {
            if ($attempt->getStatus() !== QCMAttempt::STATUS_IN_PROGRESS) {
                $this->logger->info('Attempt already processed, skipping expiration', [
                    'attempt_id' => $attempt->getId(),
                    'student_id' => $attempt->getStudent()->getId(),
                    'qcm_id' => $attempt->getQcm()->getId(),
                    'current_status' => $attempt->getStatus(),
                ]);

                return; // Already processed
            }

            $attempt->expire();
            $this->entityManager->flush();

            $this->logger->info('QCM attempt successfully expired', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'final_time_spent_seconds' => $attempt->getTimeSpent(),
                'final_time_spent_minutes' => $attempt->getTimeSpent() ? round($attempt->getTimeSpent() / 60, 2) : null,
            ]);
        } catch (DBALException $e) {
            $this->logger->error('Database error while expiring QCM attempt', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            throw new RuntimeException('Failed to expire attempt due to database error: ' . $e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error while expiring QCM attempt', [
                'attempt_id' => $attempt->getId(),
                'student_id' => $attempt->getStudent()->getId(),
                'qcm_id' => $attempt->getQcm()->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Unexpected error while expiring attempt: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if student can attempt a QCM.
     */
    public function canStudentAttempt(Student $student, QCM $qcm): bool
    {
        $this->logger->debug('Checking if student can attempt QCM', [
            'student_id' => $student->getId(),
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'max_attempts' => $qcm->getMaxAttempts(),
        ]);

        try {
            $canAttempt = $this->attemptRepository->canStartNewAttempt($student, $qcm);

            $this->logger->debug('Student attempt check result', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'can_attempt' => $canAttempt,
                'completed_attempts' => $this->attemptRepository->countCompletedAttempts($student, $qcm),
                'max_attempts' => $qcm->getMaxAttempts(),
            ]);

            return $canAttempt;
        } catch (Throwable $e) {
            $this->logger->error('Error checking if student can attempt QCM', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            // Return false on error to be safe
            return false;
        }
    }

    /**
     * Get all attempts for a student and QCM.
     */
    public function getStudentAttempts(Student $student, QCM $qcm): array
    {
        $this->logger->debug('Getting student attempts for QCM', [
            'student_id' => $student->getId(),
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
        ]);

        try {
            $attempts = $this->attemptRepository->findAllByStudentAndQCM($student, $qcm);

            $this->logger->debug('Retrieved student attempts', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'attempts_count' => count($attempts),
                'attempt_ids' => array_map(static fn ($attempt) => $attempt->getId(), $attempts),
            ]);

            return $attempts;
        } catch (Throwable $e) {
            $this->logger->error('Error getting student attempts for QCM', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw new RuntimeException('Failed to retrieve student attempts: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get student's best score for a QCM.
     */
    public function getStudentBestScore(Student $student, QCM $qcm): ?int
    {
        $this->logger->debug('Getting student best score for QCM', [
            'student_id' => $student->getId(),
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
        ]);

        try {
            $bestScore = $this->attemptRepository->getBestScore($student, $qcm);

            $this->logger->debug('Retrieved student best score', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'best_score' => $bestScore,
                'passing_score' => $qcm->getPassingScore(),
            ]);

            return $bestScore;
        } catch (Throwable $e) {
            $this->logger->error('Error getting student best score for QCM', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw new RuntimeException('Failed to retrieve student best score: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if student has passed a QCM.
     */
    public function hasStudentPassed(Student $student, QCM $qcm): bool
    {
        $this->logger->debug('Checking if student has passed QCM', [
            'student_id' => $student->getId(),
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'passing_score' => $qcm->getPassingScore(),
        ]);

        try {
            $hasPassed = $this->attemptRepository->hasStudentPassed($student, $qcm);
            $bestScore = $this->attemptRepository->getBestScore($student, $qcm);

            $this->logger->debug('Student pass check result', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'has_passed' => $hasPassed,
                'best_score' => $bestScore,
                'passing_score' => $qcm->getPassingScore(),
            ]);

            return $hasPassed;
        } catch (Throwable $e) {
            $this->logger->error('Error checking if student has passed QCM', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw new RuntimeException('Failed to check if student has passed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get QCM statistics.
     */
    public function getQCMStatistics(QCM $qcm): array
    {
        $this->logger->info('Getting QCM statistics', [
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
        ]);

        try {
            $statistics = $this->attemptRepository->getQCMStatistics($qcm);

            $this->logger->info('Retrieved QCM statistics', [
                'qcm_id' => $qcm->getId(),
                'statistics' => $statistics,
            ]);

            return $statistics;
        } catch (Throwable $e) {
            $this->logger->error('Error getting QCM statistics', [
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to retrieve QCM statistics: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get question statistics for a QCM.
     */
    public function getQuestionStatistics(QCM $qcm): array
    {
        $this->logger->info('Getting question statistics for QCM', [
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'questions_count' => count($qcm->getQuestions() ?? []),
        ]);

        try {
            $statistics = $this->attemptRepository->getQuestionStatistics($qcm);

            $this->logger->info('Retrieved question statistics', [
                'qcm_id' => $qcm->getId(),
                'statistics_count' => count($statistics),
            ]);

            return $statistics;
        } catch (Throwable $e) {
            $this->logger->error('Error getting question statistics for QCM', [
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to retrieve question statistics: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get remaining attempts for a student.
     */
    public function getRemainingAttempts(Student $student, QCM $qcm): int
    {
        $this->logger->debug('Getting remaining attempts for student', [
            'student_id' => $student->getId(),
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'max_attempts' => $qcm->getMaxAttempts(),
        ]);

        try {
            $completedAttempts = $this->attemptRepository->countCompletedAttempts($student, $qcm);
            $remainingAttempts = max(0, $qcm->getMaxAttempts() - $completedAttempts);

            $this->logger->debug('Calculated remaining attempts', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'completed_attempts' => $completedAttempts,
                'max_attempts' => $qcm->getMaxAttempts(),
                'remaining_attempts' => $remainingAttempts,
            ]);

            return $remainingAttempts;
        } catch (Throwable $e) {
            $this->logger->error('Error getting remaining attempts for student', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw new RuntimeException('Failed to calculate remaining attempts: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Process expired attempts (should be called via cron job).
     */
    public function processExpiredAttempts(): int
    {
        $this->logger->info('Processing expired QCM attempts');

        try {
            $expiredAttempts = $this->attemptRepository->findExpiredAttempts();

            $this->logger->info('Found expired attempts to process', [
                'expired_attempts_count' => count($expiredAttempts),
                'expired_attempt_ids' => array_map(static fn ($attempt) => $attempt->getId(), $expiredAttempts),
            ]);

            $processedCount = 0;

            foreach ($expiredAttempts as $attempt) {
                try {
                    $this->logger->debug('Processing expired attempt', [
                        'attempt_id' => $attempt->getId(),
                        'student_id' => $attempt->getStudent()->getId(),
                        'qcm_id' => $attempt->getQcm()->getId(),
                        'expires_at' => $attempt->getExpiresAt()?->format('Y-m-d H:i:s'),
                    ]);

                    $this->expireAttempt($attempt);
                    $processedCount++;
                } catch (Throwable $attemptError) {
                    $this->logger->error('Error processing individual expired attempt', [
                        'attempt_id' => $attempt->getId(),
                        'student_id' => $attempt->getStudent()->getId(),
                        'qcm_id' => $attempt->getQcm()->getId(),
                        'error_message' => $attemptError->getMessage(),
                        'error_class' => get_class($attemptError),
                    ]);
                    // Continue processing other attempts
                }
            }

            $this->logger->info('Completed processing expired QCM attempts', [
                'total_found' => count($expiredAttempts),
                'successfully_processed' => $processedCount,
                'failed_processing' => count($expiredAttempts) - $processedCount,
            ]);

            return $processedCount;
        } catch (Throwable $e) {
            $this->logger->error('Error processing expired QCM attempts', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to process expired attempts: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get randomized questions for display.
     */
    public function getRandomizedQuestions(QCM $qcm): array
    {
        $this->logger->info('Getting randomized questions for QCM', [
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'randomize_questions' => $qcm->isRandomizeQuestions(),
            'randomize_answers' => $qcm->isRandomizeAnswers(),
            'questions_count' => count($qcm->getQuestions() ?? []),
        ]);

        try {
            $questions = $qcm->getQuestions();

            if ($qcm->isRandomizeQuestions()) {
                $this->logger->debug('Randomizing question order', [
                    'qcm_id' => $qcm->getId(),
                    'original_questions_count' => count($questions),
                ]);
                shuffle($questions);
            }

            // Randomize answers if enabled
            if ($qcm->isRandomizeAnswers()) {
                $this->logger->debug('Randomizing answer order for each question', [
                    'qcm_id' => $qcm->getId(),
                    'questions_count' => count($questions),
                ]);

                foreach ($questions as $questionIndex => &$question) {
                    if (isset($question['answers'])) {
                        $originalAnswers = $question['answers'];
                        $correctAnswers = $question['correct_answers'] ?? [];

                        // Create mapping of old indices to new indices
                        $indices = array_keys($originalAnswers);
                        shuffle($indices);

                        $newAnswers = [];
                        $newCorrectAnswers = [];

                        foreach ($indices as $newIndex => $oldIndex) {
                            $newAnswers[$newIndex] = $originalAnswers[$oldIndex];

                            // Update correct answer indices
                            if (in_array($oldIndex, $correctAnswers, true)) {
                                $newCorrectAnswers[] = $newIndex;
                            }
                        }

                        $question['answers'] = $newAnswers;
                        $question['correct_answers'] = $newCorrectAnswers;
                        $question['_answer_mapping'] = array_flip($indices); // Store mapping for answer processing

                        $this->logger->debug('Randomized answers for question', [
                            'qcm_id' => $qcm->getId(),
                            'question_index' => $questionIndex,
                            'original_answers_count' => count($originalAnswers),
                            'new_answers_count' => count($newAnswers),
                            'original_correct_answers' => $correctAnswers,
                            'new_correct_answers' => $newCorrectAnswers,
                        ]);
                    }
                }
            }

            $this->logger->info('Successfully randomized questions', [
                'qcm_id' => $qcm->getId(),
                'final_questions_count' => count($questions),
                'randomized_questions' => $qcm->isRandomizeQuestions(),
                'randomized_answers' => $qcm->isRandomizeAnswers(),
            ]);

            return $questions;
        } catch (Throwable $e) {
            $this->logger->error('Error randomizing questions for QCM', [
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to randomize questions: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the current active attempt or null.
     */
    public function getActiveAttempt(Student $student, QCM $qcm): ?QCMAttempt
    {
        $this->logger->debug('Getting active attempt for student', [
            'student_id' => $student->getId(),
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
        ]);

        try {
            $attempt = $this->attemptRepository->findActiveAttempt($student, $qcm);

            if ($attempt) {
                $this->logger->debug('Found active attempt, checking expiration', [
                    'attempt_id' => $attempt->getId(),
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'started_at' => $attempt->getStartedAt()?->format('Y-m-d H:i:s'),
                    'expires_at' => $attempt->getExpiresAt()?->format('Y-m-d H:i:s'),
                    'has_expired' => $attempt->hasExpired(),
                ]);

                if ($attempt->hasExpired()) {
                    $this->logger->info('Active attempt has expired, expiring it', [
                        'attempt_id' => $attempt->getId(),
                        'student_id' => $student->getId(),
                        'qcm_id' => $qcm->getId(),
                    ]);
                    $this->expireAttempt($attempt);

                    return null;
                }
            } else {
                $this->logger->debug('No active attempt found for student', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                ]);
            }

            return $attempt;
        } catch (Throwable $e) {
            $this->logger->error('Error getting active attempt for student', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw new RuntimeException('Failed to get active attempt: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the latest completed attempt.
     */
    public function getLatestAttempt(Student $student, QCM $qcm): ?QCMAttempt
    {
        $this->logger->debug('Getting latest attempt for student', [
            'student_id' => $student->getId(),
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
        ]);

        try {
            $attempt = $this->attemptRepository->findLatestAttempt($student, $qcm);

            if ($attempt) {
                $this->logger->debug('Found latest attempt', [
                    'attempt_id' => $attempt->getId(),
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'attempt_status' => $attempt->getStatus(),
                    'attempt_score' => $attempt->getScore(),
                    'completed_at' => $attempt->getCompletedAt()?->format('Y-m-d H:i:s'),
                ]);
            } else {
                $this->logger->debug('No latest attempt found for student', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                ]);
            }

            return $attempt;
        } catch (Throwable $e) {
            $this->logger->error('Error getting latest attempt for student', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw new RuntimeException('Failed to get latest attempt: ' . $e->getMessage(), 0, $e);
        }
    }
}
