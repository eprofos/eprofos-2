<?php

namespace App\Entity\Training;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Course entity representing a course within a chapter
 * 
 * Contains detailed pedagogical content with specific learning objectives,
 * resources, and evaluation methods to meet Qualiopi requirements.
 */
#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class Course
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
     * Specific learning objectives for this course (required by Qualiopi)
     *
     * Concrete, measurable objectives that participants will achieve
     * by completing this specific course.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $learningObjectives = null;

    /**
     * Detailed content outline for this course (required by Qualiopi)
     *
     * Structured content plan with key topics and subtopics covered.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $contentOutline = null;

    /**
     * Prerequisites specific to this course (required by Qualiopi)
     *
     * Knowledge or skills required before starting this course.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $prerequisites = null;

    /**
     * Expected learning outcomes for this course (required by Qualiopi)
     *
     * What participants should know or be able to do after completing this course.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $learningOutcomes = null;

    /**
     * Teaching methods used in this course (required by Qualiopi)
     *
     * Pedagogical approaches and methodologies employed.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $teachingMethods = null;

    /**
     * Resources and materials for this course (required by Qualiopi)
     *
     * Educational resources, documents, tools, and materials used.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $resources = null;

    /**
     * Assessment methods for this course (required by Qualiopi)
     *
     * How learning is evaluated within this course.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $assessmentMethods = null;

    /**
     * Success criteria for course completion (required by Qualiopi)
     *
     * Measurable indicators that demonstrate successful course completion.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $successCriteria = null;

    /**
     * Course content (text, video, documents, etc.)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $content = null;

    /**
     * Course type (lesson, video, document, interactive, etc.)
     */
    #[ORM\Column(length: 50)]
    #[Gedmo\Versioned]
    private ?string $type = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $durationMinutes = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?int $orderIndex = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Chapter $chapter = null;

    /**
     * @var Collection<int, Exercise>
     */
    #[ORM\OneToMany(targetEntity: Exercise::class, mappedBy: 'course', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $exercises;

    /**
     * @var Collection<int, QCM>
     */
    #[ORM\OneToMany(targetEntity: QCM::class, mappedBy: 'course', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $qcms;

    // Constants for course types
    public const TYPE_LESSON = 'lesson';
    public const TYPE_VIDEO = 'video';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_INTERACTIVE = 'interactive';
    public const TYPE_PRACTICAL = 'practical';

    public const TYPES = [
        self::TYPE_LESSON => 'Cours magistral',
        self::TYPE_VIDEO => 'Vidéo',
        self::TYPE_DOCUMENT => 'Document',
        self::TYPE_INTERACTIVE => 'Interactif',
        self::TYPE_PRACTICAL => 'Pratique',
    ];

    public function __construct()
    {
        $this->exercises = new ArrayCollection();
        $this->qcms = new ArrayCollection();
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

    public function getLearningObjectives(): ?array
    {
        return $this->learningObjectives;
    }

    public function setLearningObjectives(?array $learningObjectives): static
    {
        $this->learningObjectives = $learningObjectives;
        return $this;
    }

    public function getContentOutline(): ?string
    {
        return $this->contentOutline;
    }

    public function setContentOutline(?string $contentOutline): static
    {
        $this->contentOutline = $contentOutline;
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

    public function getLearningOutcomes(): ?array
    {
        return $this->learningOutcomes;
    }

    public function setLearningOutcomes(?array $learningOutcomes): static
    {
        $this->learningOutcomes = $learningOutcomes;
        return $this;
    }

    public function getTeachingMethods(): ?string
    {
        return $this->teachingMethods;
    }

    public function setTeachingMethods(?string $teachingMethods): static
    {
        $this->teachingMethods = $teachingMethods;
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

    public function getAssessmentMethods(): ?string
    {
        return $this->assessmentMethods;
    }

    public function setAssessmentMethods(?string $assessmentMethods): static
    {
        $this->assessmentMethods = $assessmentMethods;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
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

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;
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

    public function getChapter(): ?Chapter
    {
        return $this->chapter;
    }

    public function setChapter(?Chapter $chapter): static
    {
        $this->chapter = $chapter;
        return $this;
    }

    /**
     * @return Collection<int, Exercise>
     */
    public function getExercises(): Collection
    {
        return $this->exercises;
    }

    public function addExercise(Exercise $exercise): static
    {
        if (!$this->exercises->contains($exercise)) {
            $this->exercises->add($exercise);
            $exercise->setCourse($this);
        }

        return $this;
    }

    public function removeExercise(Exercise $exercise): static
    {
        if ($this->exercises->removeElement($exercise)) {
            // set the owning side to null (unless already changed)
            if ($exercise->getCourse() === $this) {
                $exercise->setCourse(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, QCM>
     */
    public function getQcms(): Collection
    {
        return $this->qcms;
    }

    public function addQcm(QCM $qcm): static
    {
        if (!$this->qcms->contains($qcm)) {
            $this->qcms->add($qcm);
            $qcm->setCourse($this);
        }

        return $this;
    }

    public function removeQcm(QCM $qcm): static
    {
        if ($this->qcms->removeElement($qcm)) {
            // set the owning side to null (unless already changed)
            if ($qcm->getCourse() === $this) {
                $qcm->setCourse(null);
            }
        }

        return $this;
    }

    /**
     * Get active exercises for this course
     * 
     * @return Collection<int, Exercise>
     */
    public function getActiveExercises(): Collection
    {
        return $this->exercises->filter(function (Exercise $exercise) {
            return $exercise->isActive();
        });
    }

    /**
     * Get active QCMs for this course
     * 
     * @return Collection<int, QCM>
     */
    public function getActiveQcms(): Collection
    {
        return $this->qcms->filter(function (QCM $qcm) {
            return $qcm->isActive();
        });
    }

    /**
     * Get formatted duration as human readable string
     */
    public function getFormattedDuration(): string
    {
        if ($this->durationMinutes === null) {
            return '';
        }

        if ($this->durationMinutes < 60) {
            return $this->durationMinutes . 'min';
        }

        $hours = intval($this->durationMinutes / 60);
        $minutes = $this->durationMinutes % 60;

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
