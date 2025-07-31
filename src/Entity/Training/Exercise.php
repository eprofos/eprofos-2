<?php

declare(strict_types=1);

namespace App\Entity\Training;

use App\Repository\Training\ExerciseRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Exercise entity representing a practical exercise within a course.
 *
 * Contains detailed exercise information with instructions, expected outcomes,
 * and evaluation criteria to meet Qualiopi requirements for practical assessment.
 */
#[ORM\Entity(repositoryClass: ExerciseRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class Exercise
{
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
     * Exercise instructions (required by Qualiopi).
     *
     * Clear, detailed instructions for completing the exercise.
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Gedmo\Versioned]
    private ?string $instructions = null;

    /**
     * Expected outcomes for this exercise (required by Qualiopi).
     *
     * What participants should achieve or produce by completing this exercise.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $expectedOutcomes = null;

    /**
     * Evaluation criteria for this exercise (required by Qualiopi).
     *
     * How the exercise completion will be evaluated and graded.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $evaluationCriteria = null;

    /**
     * Resources needed for this exercise (required by Qualiopi).
     *
     * Materials, tools, or resources required to complete the exercise.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $resources = null;

    /**
     * Prerequisites for this exercise (required by Qualiopi).
     *
     * Knowledge or skills required before attempting this exercise.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $prerequisites = null;

    /**
     * Success criteria for exercise completion (required by Qualiopi).
     *
     * Measurable indicators that demonstrate successful exercise completion.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $successCriteria = null;

    /**
     * Exercise type (individual, group, practical, theoretical, etc.).
     */
    #[ORM\Column(length: 50)]
    #[Gedmo\Versioned]
    private ?string $type = null;

    /**
     * Difficulty level of the exercise.
     */
    #[ORM\Column(length: 50)]
    #[Gedmo\Versioned]
    private ?string $difficulty = null;

    /**
     * Estimated time to complete the exercise in minutes.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $estimatedDurationMinutes = null;

    /**
     * Time limit for completing the exercise in minutes (optional).
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $timeLimitMinutes = null;

    /**
     * Exercise content/description that students will see.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $content = null;

    /**
     * Maximum number of attempts allowed for this exercise.
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $maxAttempts = null;

    /**
     * Whether this exercise is automatically graded.
     */
    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $isAutoGraded = false;

    /**
     * Resource files associated with this exercise (JSON array).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $resourceFiles = null;

    /**
     * Maximum points/score for this exercise.
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $maxPoints = null;

    /**
     * Minimum points required to pass this exercise.
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?int $passingPoints = null;

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

    #[ORM\ManyToOne(inversedBy: 'exercises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
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

    public function getTimeLimitMinutes(): ?int
    {
        return $this->timeLimitMinutes;
    }

    public function setTimeLimitMinutes(?int $timeLimitMinutes): static
    {
        $this->timeLimitMinutes = $timeLimitMinutes;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getMaxAttempts(): ?int
    {
        return $this->maxAttempts;
    }

    public function setMaxAttempts(?int $maxAttempts): static
    {
        $this->maxAttempts = $maxAttempts;

        return $this;
    }

    public function isAutoGraded(): ?bool
    {
        return $this->isAutoGraded;
    }

    public function setIsAutoGraded(bool $isAutoGraded): static
    {
        $this->isAutoGraded = $isAutoGraded;

        return $this;
    }

    public function getResourceFiles(): ?array
    {
        return $this->resourceFiles;
    }

    public function setResourceFiles(?array $resourceFiles): static
    {
        $this->resourceFiles = $resourceFiles;

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
     * Get formatted duration as human readable string.
     */
    public function getFormattedDuration(): string
    {
        if ($this->estimatedDurationMinutes === null) {
            return '';
        }

        if ($this->estimatedDurationMinutes < 60) {
            return $this->estimatedDurationMinutes . 'min';
        }

        $hours = (int) ($this->estimatedDurationMinutes / 60);
        $minutes = $this->estimatedDurationMinutes % 60;

        if ($minutes === 0) {
            return $hours . 'h';
        }

        return $hours . 'h' . $minutes . 'min';
    }

    /**
     * Get the type label.
     */
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get the difficulty label.
     */
    public function getDifficultyLabel(): string
    {
        return self::DIFFICULTIES[$this->difficulty] ?? $this->difficulty;
    }

    /**
     * Get passing percentage.
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
        $this->updatedAt = new DateTimeImmutable();
    }
}
