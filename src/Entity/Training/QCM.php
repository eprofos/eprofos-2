<?php

declare(strict_types=1);

namespace App\Entity\Training;

use App\Repository\Training\QCMRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * QCM entity representing a multiple choice questionnaire within a course.
 *
 * Contains detailed QCM information with questions, answers, and evaluation
 * criteria to meet Qualiopi requirements for knowledge assessment.
 */
#[ORM\Entity(repositoryClass: QCMRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class QCM
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Versioned]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Versioned]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Gedmo\Versioned]
    private ?string $description = null;

    /**
     * Instructions for completing the QCM (required by Qualiopi).
     *
     * Clear instructions for participants on how to complete the questionnaire.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $instructions = null;

    /**
     * QCM questions with their answers and explanations.
     *
     * JSON structure containing questions, possible answers, correct answers,
     * and explanations for each question.
     */
    #[ORM\Column(type: Types::JSON)]
    #[Gedmo\Versioned]
    private ?array $questions = null;

    /**
     * Evaluation criteria for this QCM (required by Qualiopi).
     *
     * How the QCM results will be evaluated and graded.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $evaluationCriteria = null;

    /**
     * Success criteria for QCM completion (required by Qualiopi).
     *
     * Measurable indicators that demonstrate successful QCM completion.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $successCriteria = null;

    /**
     * Time limit for completing the QCM in minutes.
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $timeLimitMinutes = null;

    /**
     * Maximum score for this QCM.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $maxScore = null;

    /**
     * Minimum score required to pass this QCM.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $passingScore = null;

    /**
     * Number of attempts allowed for this QCM.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $maxAttempts = 1;

    /**
     * Whether to show correct answers after completion.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $showCorrectAnswers = true;

    /**
     * Whether to show explanations after completion.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $showExplanations = true;

    /**
     * Whether to randomize question order.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $randomizeQuestions = false;

    /**
     * Whether to randomize answer order.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $randomizeAnswers = false;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $orderIndex = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'qcms')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->questions = [];
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function getQuestions(): ?array
    {
        return $this->questions;
    }

    public function setQuestions(array $questions): static
    {
        $this->questions = $questions;

        return $this;
    }

    public function getEvaluationCriteria(): ?array
    {
        return $this->evaluationCriteria;
    }

    public function setEvaluationCriteria(?array $evaluationCriteria): static
    {
        $this->evaluationCriteria = $evaluationCriteria;

        return $this;
    }

    public function getSuccessCriteria(): ?array
    {
        return $this->successCriteria;
    }

    public function setSuccessCriteria(?array $successCriteria): static
    {
        $this->successCriteria = $successCriteria;

        return $this;
    }

    public function getTimeLimitMinutes(): ?int
    {
        return $this->timeLimitMinutes;
    }

    public function setTimeLimitMinutes(?int $timeLimitMinutes): static
    {
        $this->timeLimitMinutes = $timeLimitMinutes;

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

    public function getPassingScore(): ?int
    {
        return $this->passingScore;
    }

    public function setPassingScore(int $passingScore): static
    {
        $this->passingScore = $passingScore;

        return $this;
    }

    public function getMaxAttempts(): ?int
    {
        return $this->maxAttempts;
    }

    public function setMaxAttempts(int $maxAttempts): static
    {
        $this->maxAttempts = $maxAttempts;

        return $this;
    }

    public function isShowCorrectAnswers(): ?bool
    {
        return $this->showCorrectAnswers;
    }

    public function setShowCorrectAnswers(bool $showCorrectAnswers): static
    {
        $this->showCorrectAnswers = $showCorrectAnswers;

        return $this;
    }

    public function isShowExplanations(): ?bool
    {
        return $this->showExplanations;
    }

    public function setShowExplanations(bool $showExplanations): static
    {
        $this->showExplanations = $showExplanations;

        return $this;
    }

    public function isRandomizeQuestions(): ?bool
    {
        return $this->randomizeQuestions;
    }

    public function setRandomizeQuestions(bool $randomizeQuestions): static
    {
        $this->randomizeQuestions = $randomizeQuestions;

        return $this;
    }

    public function isRandomizeAnswers(): ?bool
    {
        return $this->randomizeAnswers;
    }

    public function setRandomizeAnswers(bool $randomizeAnswers): static
    {
        $this->randomizeAnswers = $randomizeAnswers;

        return $this;
    }

    public function getOrderIndex(): ?int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): static
    {
        $this->orderIndex = $orderIndex;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    /**
     * Get the number of questions in this QCM.
     */
    public function getQuestionCount(): int
    {
        return count($this->questions ?? []);
    }

    /**
     * Get passing percentage.
     */
    public function getPassingPercentage(): float
    {
        if ($this->maxScore === 0) {
            return 0;
        }

        return ($this->passingScore / $this->maxScore) * 100;
    }

    /**
     * Get formatted time limit.
     */
    public function getFormattedTimeLimit(): string
    {
        if ($this->timeLimitMinutes === null) {
            return 'Illimité';
        }

        if ($this->timeLimitMinutes < 60) {
            return $this->timeLimitMinutes . 'min';
        }

        $hours = (int) ($this->timeLimitMinutes / 60);
        $minutes = $this->timeLimitMinutes % 60;

        if ($minutes === 0) {
            return $hours . 'h';
        }

        return $hours . 'h' . $minutes . 'min';
    }

    /**
     * Add a question to the QCM.
     */
    public function addQuestion(array $question): static
    {
        $this->questions[] = $question;

        return $this;
    }

    /**
     * Remove a question from the QCM by index.
     */
    public function removeQuestion(int $index): static
    {
        if (isset($this->questions[$index])) {
            unset($this->questions[$index]);
            $this->questions = array_values($this->questions); // Reindex array
        }

        return $this;
    }

    /**
     * Update a question in the QCM.
     */
    public function updateQuestion(int $index, array $question): static
    {
        if (isset($this->questions[$index])) {
            $this->questions[$index] = $question;
        }

        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
