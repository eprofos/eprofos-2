<?php

declare(strict_types=1);

namespace App\Service\Student;

use App\Entity\Student\QCMAttempt;
use App\Entity\Training\QCM;
use App\Entity\User\Student;
use App\Repository\Student\QCMAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for handling QCM attempts and scoring.
 */
class QCMAttemptService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QCMAttemptRepository $attemptRepository
    ) {
    }

    /**
     * Start a new QCM attempt.
     */
    public function startAttempt(Student $student, QCM $qcm): QCMAttempt
    {
        if (!$this->canStudentAttempt($student, $qcm)) {
            throw new \InvalidArgumentException('Student cannot start a new attempt for this QCM.');
        }

        $attempt = new QCMAttempt();
        $attempt->setStudent($student);
        $attempt->setQcm($qcm);
        $attempt->setAttemptNumber(
            $this->attemptRepository->getNextAttemptNumber($student, $qcm)
        );

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        return $attempt;
    }

    /**
     * Get or create active attempt for a student and QCM.
     */
    public function getOrCreateActiveAttempt(Student $student, QCM $qcm): QCMAttempt
    {
        $activeAttempt = $this->attemptRepository->findActiveAttempt($student, $qcm);
        
        if ($activeAttempt) {
            // Check if expired
            if ($activeAttempt->hasExpired()) {
                $this->expireAttempt($activeAttempt);
                $activeAttempt = null;
            }
        }
        
        if (!$activeAttempt) {
            $activeAttempt = $this->startAttempt($student, $qcm);
        }

        return $activeAttempt;
    }

    /**
     * Save answer for a question.
     */
    public function saveAnswer(QCMAttempt $attempt, int $questionIndex, array $answerIndices): void
    {
        if (!$attempt->isActive()) {
            throw new \InvalidArgumentException('This attempt is not active anymore.');
        }

        $attempt->setAnswerForQuestion($questionIndex, $answerIndices);
        $this->entityManager->flush();
    }

    /**
     * Submit QCM attempt.
     */
    public function submitAttempt(QCMAttempt $attempt): void
    {
        if (!$attempt->isActive()) {
            throw new \InvalidArgumentException('This attempt is not active anymore.');
        }

        // Calculate score
        $attempt->calculateScore();
        
        // Complete the attempt
        $attempt->complete();
        
        $this->entityManager->flush();
    }

    /**
     * Abandon a QCM attempt.
     */
    public function abandonAttempt(QCMAttempt $attempt): void
    {
        if ($attempt->getStatus() !== QCMAttempt::STATUS_IN_PROGRESS) {
            throw new \InvalidArgumentException('Only in-progress attempts can be abandoned.');
        }

        $attempt->abandon();
        $this->entityManager->flush();
    }

    /**
     * Expire a QCM attempt.
     */
    public function expireAttempt(QCMAttempt $attempt): void
    {
        if ($attempt->getStatus() !== QCMAttempt::STATUS_IN_PROGRESS) {
            return; // Already processed
        }

        $attempt->expire();
        $this->entityManager->flush();
    }

    /**
     * Check if student can attempt a QCM.
     */
    public function canStudentAttempt(Student $student, QCM $qcm): bool
    {
        return $this->attemptRepository->canStartNewAttempt($student, $qcm);
    }

    /**
     * Get all attempts for a student and QCM.
     */
    public function getStudentAttempts(Student $student, QCM $qcm): array
    {
        return $this->attemptRepository->findAllByStudentAndQCM($student, $qcm);
    }

    /**
     * Get student's best score for a QCM.
     */
    public function getStudentBestScore(Student $student, QCM $qcm): ?int
    {
        return $this->attemptRepository->getBestScore($student, $qcm);
    }

    /**
     * Check if student has passed a QCM.
     */
    public function hasStudentPassed(Student $student, QCM $qcm): bool
    {
        return $this->attemptRepository->hasStudentPassed($student, $qcm);
    }

    /**
     * Get QCM statistics.
     */
    public function getQCMStatistics(QCM $qcm): array
    {
        return $this->attemptRepository->getQCMStatistics($qcm);
    }

    /**
     * Get question statistics for a QCM.
     */
    public function getQuestionStatistics(QCM $qcm): array
    {
        return $this->attemptRepository->getQuestionStatistics($qcm);
    }

    /**
     * Get remaining attempts for a student.
     */
    public function getRemainingAttempts(Student $student, QCM $qcm): int
    {
        $completedAttempts = $this->attemptRepository->countCompletedAttempts($student, $qcm);
        return max(0, $qcm->getMaxAttempts() - $completedAttempts);
    }

    /**
     * Process expired attempts (should be called via cron job).
     */
    public function processExpiredAttempts(): int
    {
        $expiredAttempts = $this->attemptRepository->findExpiredAttempts();
        
        foreach ($expiredAttempts as $attempt) {
            $this->expireAttempt($attempt);
        }

        return count($expiredAttempts);
    }

    /**
     * Get randomized questions for display.
     */
    public function getRandomizedQuestions(QCM $qcm): array
    {
        $questions = $qcm->getQuestions();
        
        if ($qcm->isRandomizeQuestions()) {
            shuffle($questions);
        }
        
        // Randomize answers if enabled
        if ($qcm->isRandomizeAnswers()) {
            foreach ($questions as &$question) {
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
                        if (in_array($oldIndex, $correctAnswers)) {
                            $newCorrectAnswers[] = $newIndex;
                        }
                    }
                    
                    $question['answers'] = $newAnswers;
                    $question['correct_answers'] = $newCorrectAnswers;
                    $question['_answer_mapping'] = array_flip($indices); // Store mapping for answer processing
                }
            }
        }
        
        return $questions;
    }

    /**
     * Get the current active attempt or null.
     */
    public function getActiveAttempt(Student $student, QCM $qcm): ?QCMAttempt
    {
        $attempt = $this->attemptRepository->findActiveAttempt($student, $qcm);
        
        if ($attempt && $attempt->hasExpired()) {
            $this->expireAttempt($attempt);
            return null;
        }
        
        return $attempt;
    }

    /**
     * Get the latest completed attempt.
     */
    public function getLatestAttempt(Student $student, QCM $qcm): ?QCMAttempt
    {
        return $this->attemptRepository->findLatestAttempt($student, $qcm);
    }
}
