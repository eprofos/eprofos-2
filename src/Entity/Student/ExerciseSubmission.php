<?php

declare(strict_types=1);

namespace App\Entity\Student;

use App\Entity\Training\Exercise;
use App\Entity\User\Student;
use App\Repository\Student\ExerciseSubmissionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * ExerciseSubmission entity representing a student's submission for an exercise.
 *
 * Tracks exercise attempts, submissions, and grading with progress integration
 * for Qualiopi compliance and learning analytics.
 */
#[ORM\Entity(repositoryClass: ExerciseSubmissionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class ExerciseSubmission
{
    // Constants for submission statuses
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADED = 'graded';
    public const STATUS_REVIEWED = 'reviewed';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_SUBMITTED => 'Soumis',
        self::STATUS_GRADED => 'Noté',
        self::STATUS_REVIEWED => 'Révisé',
    ];

    // Constants for submission types
    public const TYPE_TEXT = 'text';
    public const TYPE_FILE = 'file';
    public const TYPE_PRACTICAL = 'practical';

    public const TYPES = [
        self::TYPE_TEXT => 'Texte',
        self::TYPE_FILE => 'Fichier',
        self::TYPE_PRACTICAL => 'Pratique',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Exercise $exercise = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    /**
     * Submission data containing student's work.
     * Structure varies based on exercise type:
     * - Text exercises: ['content' => string, 'word_count' => int]
     * - File exercises: ['files' => [array of file info], 'description' => string]
     * - Practical exercises: ['checklist' => array, 'evidence' => array, 'self_assessment' => array]
     */
    #[ORM\Column(type: Types::JSON)]
    #[Gedmo\Versioned]
    private ?array $submissionData = null;

    /**
     * Feedback from teacher/instructor on the submission.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $feedback = null;

    /**
     * Score achieved for this submission.
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $score = null;

    /**
     * Auto-calculated score based on objective criteria.
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $autoScore = null;

    /**
     * Manual score adjustment by instructor.
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $manualScore = null;

    /**
     * Current status of the submission.
     */
    #[ORM\Column(length: 50)]
    #[Gedmo\Versioned]
    private ?string $status = self::STATUS_DRAFT;

    /**
     * Type of submission based on exercise requirements.
     */
    #[ORM\Column(length: 50)]
    #[Gedmo\Versioned]
    private ?string $type = self::TYPE_TEXT;

    /**
     * Attempt number for this exercise (1, 2, 3...).
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $attemptNumber = 1;

    /**
     * Time spent on this submission in minutes.
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $timeSpentMinutes = null;

    /**
     * Whether this submission passed the exercise requirements.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $passed = false;

    /**
     * When the submission was started.
     */
    #[ORM\Column]
    private ?DateTimeImmutable $startedAt = null;

    /**
     * When the submission was submitted.
     */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $submittedAt = null;

    /**
     * When the submission was graded.
     */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $gradedAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->submissionData = [];
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->startedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('%s - %s (#%d)', 
            $this->exercise?->getTitle() ?? 'Exercise',
            $this->student?->getFullName() ?? 'Student',
            $this->attemptNumber
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExercise(): ?Exercise
    {
        return $this->exercise;
    }

    public function setExercise(?Exercise $exercise): static
    {
        $this->exercise = $exercise;

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

    public function getSubmissionData(): ?array
    {
        return $this->submissionData;
    }

    public function setSubmissionData(array $submissionData): static
    {
        $this->submissionData = $submissionData;

        return $this;
    }

    public function getFeedback(): ?string
    {
        return $this->feedback;
    }

    public function setFeedback(?string $feedback): static
    {
        $this->feedback = $feedback;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getAutoScore(): ?int
    {
        return $this->autoScore;
    }

    public function setAutoScore(?int $autoScore): static
    {
        $this->autoScore = $autoScore;

        return $this;
    }

    public function getManualScore(): ?int
    {
        return $this->manualScore;
    }

    public function setManualScore(?int $manualScore): static
    {
        $this->manualScore = $manualScore;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getTimeSpentMinutes(): ?int
    {
        return $this->timeSpentMinutes;
    }

    public function setTimeSpentMinutes(?int $timeSpentMinutes): static
    {
        $this->timeSpentMinutes = $timeSpentMinutes;

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

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getSubmittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getGradedAt(): ?DateTimeImmutable
    {
        return $this->gradedAt;
    }

    public function setGradedAt(?DateTimeImmutable $gradedAt): static
    {
        $this->gradedAt = $gradedAt;

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
     * Get type label.
     */
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Calculate score percentage.
     */
    public function getScorePercentage(): ?float
    {
        if ($this->score === null || $this->exercise?->getMaxPoints() === null || $this->exercise->getMaxPoints() === 0) {
            return null;
        }

        return ($this->score / $this->exercise->getMaxPoints()) * 100;
    }

    /**
     * Get formatted time spent.
     */
    public function getFormattedTimeSpent(): string
    {
        if ($this->timeSpentMinutes === null) {
            return '';
        }

        if ($this->timeSpentMinutes < 60) {
            return $this->timeSpentMinutes . 'min';
        }

        $hours = (int) ($this->timeSpentMinutes / 60);
        $minutes = $this->timeSpentMinutes % 60;

        if ($minutes === 0) {
            return $hours . 'h';
        }

        return $hours . 'h' . $minutes . 'min';
    }

    /**
     * Check if submission can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if submission can be graded.
     */
    public function canBeGraded(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Submit the exercise.
     */
    public function submit(): static
    {
        $this->status = self::STATUS_SUBMITTED;
        $this->submittedAt = new DateTimeImmutable();

        return $this;
    }

    /**
     * Grade the exercise.
     */
    public function grade(int $score, ?string $feedback = null): static
    {
        $this->score = $score;
        $this->feedback = $feedback;
        $this->status = self::STATUS_GRADED;
        $this->gradedAt = new DateTimeImmutable();
        
        // Check if passed
        $passingScore = $this->exercise?->getPassingPoints();
        if ($passingScore !== null) {
            $this->passed = $score >= $passingScore;
        }

        return $this;
    }

    /**
     * Update submission data.
     */
    public function updateSubmissionData(array $data): static
    {
        $this->submissionData = array_merge($this->submissionData ?? [], $data);

        return $this;
    }

    /**
     * Get submission content based on type.
     */
    public function getContent(): ?string
    {
        if ($this->type === self::TYPE_TEXT) {
            return $this->submissionData['content'] ?? null;
        }

        return null;
    }

    /**
     * Get uploaded files for file submissions.
     */
    public function getFiles(): array
    {
        if ($this->type === self::TYPE_FILE) {
            return $this->submissionData['files'] ?? [];
        }

        return [];
    }

    /**
     * Get checklist progress for practical exercises.
     */
    public function getChecklistProgress(): array
    {
        if ($this->type === self::TYPE_PRACTICAL) {
            return $this->submissionData['checklist'] ?? [];
        }

        return [];
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
