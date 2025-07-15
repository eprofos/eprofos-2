<?php

namespace App\Entity;

use App\Repository\ExerciseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Exercise entity representing a practical exercise within a course
 * 
 * Contains detailed exercise information with instructions, expected outcomes,
 * and evaluation criteria to meet Qualiopi requirements for practical assessment.
 */
#[ORM\Entity(repositoryClass: ExerciseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Exercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    /**
     * Exercise instructions (required by Qualiopi)
     *
     * Clear, detailed instructions for completing the exercise.
     */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $instructions = null;

    /**
     * Expected outcomes for this exercise (required by Qualiopi)
     *
     * What participants should achieve or produce by completing this exercise.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $expectedOutcomes = null;

    /**
     * Evaluation criteria for this exercise (required by Qualiopi)
     *
     * How the exercise completion will be evaluated and graded.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $evaluationCriteria = null;

    /**
     * Resources needed for this exercise (required by Qualiopi)
     *
     * Materials, tools, or resources required to complete the exercise.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $resources = null;

    /**
     * Prerequisites for this exercise (required by Qualiopi)
     *
     * Knowledge or skills required before attempting this exercise.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $prerequisites = null;

    /**
     * Success criteria for exercise completion (required by Qualiopi)
     *
     * Measurable indicators that demonstrate successful exercise completion.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $successCriteria = null;

    /**
     * Exercise type (individual, group, practical, theoretical, etc.)
     */
    #[ORM\Column(length: 50)]
    private ?string $type = null;

    /**
     * Difficulty level of the exercise
     */
    #[ORM\Column(length: 50)]
    private ?string $difficulty = null;

    /**
     * Estimated time to complete the exercise in minutes
     */
    #[ORM\Column]
    private ?int $estimatedDurationMinutes = null;

    /**
     * Maximum points/score for this exercise
     */
    #[ORM\Column(nullable: true)]
    private ?int $maxPoints = null;

    /**
     * Minimum points required to pass this exercise
     */
    #[ORM\Column(nullable: true)]
    private ?int $passingPoints = null;

    #[ORM\Column]
    private ?int $orderIndex = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'exercises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    // Constants for exercise types
    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_GROUP = 'group';
    public const TYPE_PRACTICAL = 'practical';
    public const TYPE_THEORETICAL = 'theoretical';
    public const TYPE_CASE_STUDY = 'case_study';
    public const TYPE_SIMULATION = 'simulation';

    public const TYPES = [
        self::TYPE_INDIVIDUAL => 'Individuel',
        self::TYPE_GROUP => 'Groupe',
        self::TYPE_PRACTICAL => 'Pratique',
        self::TYPE_THEORETICAL => 'Théorique',
        self::TYPE_CASE_STUDY => 'Étude de cas',
        self::TYPE_SIMULATION => 'Simulation',
    ];

    // Constants for difficulty levels
    public const DIFFICULTY_BEGINNER = 'beginner';
    public const DIFFICULTY_INTERMEDIATE = 'intermediate';
    public const DIFFICULTY_ADVANCED = 'advanced';
    public const DIFFICULTY_EXPERT = 'expert';

    public const DIFFICULTIES = [
        self::DIFFICULTY_BEGINNER => 'Débutant',
        self::DIFFICULTY_INTERMEDIATE => 'Intermédiaire',
        self::DIFFICULTY_ADVANCED => 'Avancé',
        self::DIFFICULTY_EXPERT => 'Expert',
    ];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function setInstructions(string $instructions): static
    {
        $this->instructions = $instructions;
        return $this;
    }

    public function getExpectedOutcomes(): ?array
    {
        return $this->expectedOutcomes;
    }

    public function setExpectedOutcomes(?array $expectedOutcomes): static
    {
        $this->expectedOutcomes = $expectedOutcomes;
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

    public function getResources(): ?array
    {
        return $this->resources;
    }

    public function setResources(?array $resources): static
    {
        $this->resources = $resources;
        return $this;
    }

    public function getPrerequisites(): ?string
    {
        return $this->prerequisites;
    }

    public function setPrerequisites(?string $prerequisites): static
    {
        $this->prerequisites = $prerequisites;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDifficulty(): ?string
    {
        return $this->difficulty;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function getEstimatedDurationMinutes(): ?int
    {
        return $this->estimatedDurationMinutes;
    }

    public function setEstimatedDurationMinutes(int $estimatedDurationMinutes): static
    {
        $this->estimatedDurationMinutes = $estimatedDurationMinutes;
        return $this;
    }

    public function getMaxPoints(): ?int
    {
        return $this->maxPoints;
    }

    public function setMaxPoints(?int $maxPoints): static
    {
        $this->maxPoints = $maxPoints;
        return $this;
    }

    public function getPassingPoints(): ?int
    {
        return $this->passingPoints;
    }

    public function setPassingPoints(?int $passingPoints): static
    {
        $this->passingPoints = $passingPoints;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
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
     * Get formatted duration as human readable string
     */
    public function getFormattedDuration(): string
    {
        if ($this->estimatedDurationMinutes === null) {
            return '';
        }

        if ($this->estimatedDurationMinutes < 60) {
            return $this->estimatedDurationMinutes . 'min';
        }

        $hours = intval($this->estimatedDurationMinutes / 60);
        $minutes = $this->estimatedDurationMinutes % 60;

        if ($minutes === 0) {
            return $hours . 'h';
        }

        return $hours . 'h' . $minutes . 'min';
    }

    /**
     * Get the type label
     */
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get the difficulty label
     */
    public function getDifficultyLabel(): string
    {
        return self::DIFFICULTIES[$this->difficulty] ?? $this->difficulty;
    }

    /**
     * Get passing percentage
     */
    public function getPassingPercentage(): ?float
    {
        if ($this->maxPoints === null || $this->passingPoints === null || $this->maxPoints === 0) {
            return null;
        }

        return ($this->passingPoints / $this->maxPoints) * 100;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }
}
