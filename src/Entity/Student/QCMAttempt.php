<?php

declare(strict_types=1);

namespace App\Entity\Student;

use App\Entity\Training\QCM;
use App\Entity\User\Student;
use App\Repository\Student\QCMAttemptRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * QCMAttempt entity representing a student's attempt at a QCM.
 *
 * Tracks QCM attempts, answers, and scoring with progress integration
 * for Qualiopi compliance and learning analytics.
 */
#[ORM\Entity(repositoryClass: QCMAttemptRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class QCMAttempt
{
    // Constants for attempt statuses
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS => 'En cours',
        self::STATUS_COMPLETED => 'Terminé',
        self::STATUS_ABANDONED => 'Abandonné',
        self::STATUS_EXPIRED => 'Expiré',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?QCM $qcm = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    /**
     * Student's answers to the QCM questions.
     * Structure: [question_index => selected_answer_indices]
     * Example: [0 => [1], 1 => [0, 2], 2 => [3]].
     */
    #[ORM\Column(type: Types::JSON)]
    #[Gedmo\Versioned]
    private ?array $answers = null;

    /**
     * Score achieved for this attempt.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $score = 0;

    /**
     * Maximum possible score for this QCM.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $maxScore = 0;

    /**
     * Time spent on this attempt in seconds.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $timeSpent = 0;

    /**
     * Attempt number for this QCM (1, 2, 3...).
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $attemptNumber = 1;

    /**
     * Current status of the attempt.
     */
    #[ORM\Column(length: 50)]
    #[Gedmo\Versioned]
    private ?string $status = self::STATUS_IN_PROGRESS;

    /**
     * Whether this attempt passed the QCM requirements.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $passed = false;

    /**
     * Detailed scoring information per question.
     * Structure: [question_index => ['correct' => bool, 'points' => int, 'max_points' => int]].
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $questionScores = null;

    /**
     * When the attempt was started.
     */
    #[ORM\Column]
    private ?DateTimeImmutable $startedAt = null;

    /**
     * When the attempt was completed (submitted).
     */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    /**
     * When the attempt expires (for time-limited QCMs).
     */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->answers = [];
        $this->questionScores = [];
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->startedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (#%d)',
            $this->qcm?->getTitle() ?? 'QCM',
            $this->student?->getFullName() ?? 'Student',
            $this->attemptNumber,
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQcm(): ?QCM
    {
        return $this->qcm;
    }

    public function setQcm(?QCM $qcm): static
    {
        $this->qcm = $qcm;

        // Set max score from QCM
        if ($qcm) {
            $this->maxScore = $qcm->getMaxScore();

            // Set expiration time if QCM has time limit
            if ($qcm->getTimeLimitMinutes()) {
                $this->expiresAt = $this->startedAt?->modify('+' . $qcm->getTimeLimitMinutes() . ' minutes');
            }
        }

        return $this;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getAnswers(): ?array
    {
        return $this->answers;
    }

    public function setAnswers(array $answers): static
    {
        $this->answers = $answers;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getMaxScore(): ?int
    {
        return $this->maxScore;
    }

    public function setMaxScore(int $maxScore): static
    {
        $this->maxScore = $maxScore;

        return $this;
    }

    public function getTimeSpent(): ?int
    {
        return $this->timeSpent;
    }

    public function setTimeSpent(int $timeSpent): static
    {
        $this->timeSpent = $timeSpent;

        return $this;
    }

    public function getAttemptNumber(): ?int
    {
        return $this->attemptNumber;
    }

    public function setAttemptNumber(int $attemptNumber): static
    {
        $this->attemptNumber = $attemptNumber;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPassed(): ?bool
    {
        return $this->passed;
    }

    public function setPassed(bool $passed): static
    {
        $this->passed = $passed;

        return $this;
    }

    public function getQuestionScores(): ?array
    {
        return $this->questionScores;
    }

    public function setQuestionScores(?array $questionScores): static
    {
        $this->questionScores = $questionScores;

        return $this;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get status label.
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Calculate score percentage.
     */
    public function getScorePercentage(): float
    {
        if ($this->maxScore === 0) {
            return 0;
        }

        return ($this->score / $this->maxScore) * 100;
    }

    /**
     * Get formatted time spent.
     */
    public function getFormattedTimeSpent(): string
    {
        if ($this->timeSpent === 0) {
            return '0s';
        }

        $minutes = (int) ($this->timeSpent / 60);
        $seconds = $this->timeSpent % 60;

        if ($minutes === 0) {
            return $seconds . 's';
        }

        if ($seconds === 0) {
            return $minutes . 'min';
        }

        return $minutes . 'min ' . $seconds . 's';
    }

    /**
     * Get remaining time in seconds.
     */
    public function getRemainingTimeSeconds(): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        if ($now >= $this->expiresAt) {
            return 0;
        }

        return $this->expiresAt->getTimestamp() - $now->getTimestamp();
    }

    /**
     * Check if the attempt has expired.
     */
    public function hasExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return new DateTimeImmutable() >= $this->expiresAt;
    }

    /**
     * Check if the attempt is active (in progress and not expired).
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS && !$this->hasExpired();
    }

    /**
     * Set answer for a specific question.
     */
    public function setAnswerForQuestion(int $questionIndex, array $answerIndices): static
    {
        if ($this->answers === null) {
            $this->answers = [];
        }

        $this->answers[$questionIndex] = $answerIndices;

        return $this;
    }

    /**
     * Get answer for a specific question.
     */
    public function getAnswerForQuestion(int $questionIndex): array
    {
        return $this->answers[$questionIndex] ?? [];
    }

    /**
     * Complete the attempt.
     */
    public function complete(): static
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new DateTimeImmutable();

        // Calculate final time spent
        if ($this->startedAt && $this->completedAt) {
            $this->timeSpent = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
        }

        // Check if passed
        $passingScore = $this->qcm?->getPassingScore();
        if ($passingScore !== null) {
            $this->passed = $this->score >= $passingScore;
        }

        return $this;
    }

    /**
     * Abandon the attempt.
     */
    public function abandon(): static
    {
        $this->status = self::STATUS_ABANDONED;

        // Calculate time spent until abandonment
        $now = new DateTimeImmutable();
        if ($this->startedAt) {
            $this->timeSpent = $now->getTimestamp() - $this->startedAt->getTimestamp();
        }

        return $this;
    }

    /**
     * Mark as expired.
     */
    public function expire(): static
    {
        $this->status = self::STATUS_EXPIRED;

        // Calculate time spent until expiration
        if ($this->startedAt && $this->expiresAt) {
            $this->timeSpent = $this->expiresAt->getTimestamp() - $this->startedAt->getTimestamp();
        }

        return $this;
    }

    /**
     * Calculate and set the score based on answers.
     */
    public function calculateScore(): static
    {
        if (!$this->qcm || !$this->answers) {
            $this->score = 0;

            return $this;
        }

        $questions = $this->qcm->getQuestions();
        $totalScore = 0;
        $questionScores = [];

        foreach ($questions as $index => $question) {
            $studentAnswers = $this->getAnswerForQuestion($index);
            $correctAnswers = $question['correct_answers'] ?? [];
            $questionPoints = $question['points'] ?? 1;

            // Calculate score for this question
            $questionScore = $this->calculateQuestionScore($studentAnswers, $correctAnswers, $questionPoints);
            $totalScore += $questionScore;

            $questionScores[$index] = [
                'correct' => $questionScore === $questionPoints,
                'points' => $questionScore,
                'max_points' => $questionPoints,
            ];
        }

        $this->score = $totalScore;
        $this->questionScores = $questionScores;

        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Calculate score for a single question.
     */
    private function calculateQuestionScore(array $studentAnswers, array $correctAnswers, int $questionPoints): int
    {
        if (empty($studentAnswers)) {
            return 0;
        }

        // Sort arrays for comparison
        sort($studentAnswers);
        sort($correctAnswers);

        // Exact match gives full points
        if ($studentAnswers === $correctAnswers) {
            return $questionPoints;
        }

        // Partial credit for multi-select questions
        if (count($correctAnswers) > 1) {
            $correctSelected = count(array_intersect($studentAnswers, $correctAnswers));
            $incorrectSelected = count(array_diff($studentAnswers, $correctAnswers));
            $totalCorrect = count($correctAnswers);

            // Partial credit formula: (correct selections - incorrect selections) / total correct
            $partialScore = max(0, ($correctSelected - $incorrectSelected) / $totalCorrect);

            return (int) round($partialScore * $questionPoints);
        }

        return 0;
    }
}
