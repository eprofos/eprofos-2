<?php

declare(strict_types=1);

namespace App\Entity\Training;

use App\Repository\Training\ChapterRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Chapter entity representing a chapter within a module.
 *
 * Contains detailed pedagogical content with specific learning objectives,
 * resources, and evaluation methods to meet Qualiopi requirements.
 */
#[ORM\Entity(repositoryClass: ChapterRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class Chapter
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
     * Specific learning objectives for this chapter (required by Qualiopi).
     *
     * Concrete, measurable objectives that participants will achieve
     * by completing this specific chapter.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $learningObjectives = null;

    /**
     * Detailed content outline for this chapter (required by Qualiopi).
     *
     * Structured content plan with key topics and subtopics covered.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $contentOutline = null;

    /**
     * Prerequisites specific to this chapter (required by Qualiopi).
     *
     * Knowledge or skills required before starting this chapter.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $prerequisites = null;

    /**
     * Expected learning outcomes for this chapter (required by Qualiopi).
     *
     * What participants should know or be able to do after completing this chapter.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $learningOutcomes = null;

    /**
     * Teaching methods used in this chapter (required by Qualiopi).
     *
     * Pedagogical approaches and methodologies employed.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $teachingMethods = null;

    /**
     * Resources and materials for this chapter (required by Qualiopi).
     *
     * Educational resources, documents, tools, and materials used.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $resources = null;

    /**
     * Assessment methods for this chapter (required by Qualiopi).
     *
     * How learning is evaluated within this chapter.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $assessmentMethods = null;

    /**
     * Success criteria for chapter completion (required by Qualiopi).
     *
     * Measurable indicators that demonstrate successful chapter completion.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $successCriteria = null;

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
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'chapters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Module $module = null;

    /**
     * @var Collection<int, Course>
     */
    #[ORM\OneToMany(targetEntity: Course::class, mappedBy: 'chapter', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
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

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function setModule(?Module $module): static
    {
        $this->module = $module;

        return $this;
    }

    /**
     * @return Collection<int, Course>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(Course $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setChapter($this);
        }

        return $this;
    }

    public function removeCourse(Course $course): static
    {
        if ($this->courses->removeElement($course)) {
            // set the owning side to null (unless already changed)
            if ($course->getChapter() === $this) {
                $course->setChapter(null);
            }
        }

        return $this;
    }

    /**
     * Get active courses for this chapter.
     *
     * @return Collection<int, Course>
     */
    public function getActiveCourses(): Collection
    {
        return $this->courses->filter(static fn (Course $course) => $course->isActive());
    }

    /**
     * Get total duration of all courses in this chapter.
     */
    public function getTotalCoursesDuration(): int
    {
        $totalDuration = 0;
        foreach ($this->courses as $course) {
            $totalDuration += $course->getDurationMinutes();
        }

        return $totalDuration;
    }

    /**
     * Get formatted duration as human readable string.
     */
    public function getFormattedDuration(): string
    {
        if ($this->durationMinutes === null) {
            return '';
        }

        if ($this->durationMinutes < 60) {
            return $this->durationMinutes . 'min';
        }

        $hours = (int) ($this->durationMinutes / 60);
        $minutes = $this->durationMinutes % 60;

        if ($minutes === 0) {
            return $hours . 'h';
        }

        return $hours . 'h' . $minutes . 'min';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
