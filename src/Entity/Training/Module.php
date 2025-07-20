<?php

namespace App\Entity\Training;

use App\Repository\ModuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Module entity representing a learning module within a formation
 * 
 * Contains structured pedagogical content with objectives, evaluation methods,
 * and duration information to meet Qualiopi requirements for training structure.
 */
#[ORM\Entity(repositoryClass: ModuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Module
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
     * Specific learning objectives for this module (required by Qualiopi)
     *
     * Concrete, measurable objectives that participants will achieve
     * by completing this specific module.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $learningObjectives = null;

    /**
     * Prerequisites specific to this module (required by Qualiopi)
     *
     * Knowledge, skills, or experience required before starting this module.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $prerequisites = null;

    /**
     * Duration in hours for this module (required by Qualiopi)
     */
    #[ORM\Column]
    private ?int $durationHours = null;

    /**
     * Order/position of this module within the formation
     */
    #[ORM\Column]
    private ?int $orderIndex = null;

    /**
     * Evaluation methods specific to this module (required by Qualiopi)
     *
     * How learning outcomes are assessed within this module.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $evaluationMethods = null;

    /**
     * Teaching methods used in this module (required by Qualiopi)
     *
     * Pedagogical approaches and methodologies employed.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $teachingMethods = null;

    /**
     * Resources and materials for this module (required by Qualiopi)
     *
     * Educational resources, documents, tools, and materials used.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $resources = null;

    /**
     * Success criteria for module completion (required by Qualiopi)
     *
     * Measurable indicators that demonstrate successful module completion.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $successCriteria = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Parent formation this module belongs to
     */
    #[ORM\ManyToOne(inversedBy: 'modules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formation $formation = null;

    /**
     * @var Collection<int, Chapter>
     */
    #[ORM\OneToMany(targetEntity: Chapter::class, mappedBy: 'module', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $chapters;

    public function __construct()
    {
        $this->chapters = new ArrayCollection();
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

    public function getPrerequisites(): ?string
    {
        return $this->prerequisites;
    }

    public function setPrerequisites(?string $prerequisites): static
    {
        $this->prerequisites = $prerequisites;
        return $this;
    }

    public function getDurationHours(): ?int
    {
        return $this->durationHours;
    }

    public function setDurationHours(int $durationHours): static
    {
        $this->durationHours = $durationHours;
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

    public function getEvaluationMethods(): ?string
    {
        return $this->evaluationMethods;
    }

    public function setEvaluationMethods(?string $evaluationMethods): static
    {
        $this->evaluationMethods = $evaluationMethods;
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

    public function getSuccessCriteria(): ?array
    {
        return $this->successCriteria;
    }

    public function setSuccessCriteria(?array $successCriteria): static
    {
        $this->successCriteria = $successCriteria;
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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;
        return $this;
    }

    /**
     * @return Collection<int, Chapter>
     */
    public function getChapters(): Collection
    {
        return $this->chapters;
    }

    public function addChapter(Chapter $chapter): static
    {
        if (!$this->chapters->contains($chapter)) {
            $this->chapters->add($chapter);
            $chapter->setModule($this);
        }

        return $this;
    }

    public function removeChapter(Chapter $chapter): static
    {
        if ($this->chapters->removeElement($chapter)) {
            // set the owning side to null (unless already changed)
            if ($chapter->getModule() === $this) {
                $chapter->setModule(null);
            }
        }

        return $this;
    }

    /**
     * Get active chapters for this module
     * 
     * @return Collection<int, Chapter>
     */
    public function getActiveChapters(): Collection
    {
        return $this->chapters->filter(function (Chapter $chapter) {
            return $chapter->isActive();
        });
    }

    /**
     * Get total duration of all chapters in this module
     */
    public function getTotalChaptersDuration(): int
    {
        $totalDuration = 0;
        foreach ($this->chapters as $chapter) {
            $totalDuration += $chapter->getDurationMinutes();
        }
        return $totalDuration;
    }

    /**
     * Get formatted duration as human readable string
     */
    public function getFormattedDuration(): string
    {
        if ($this->durationHours === null) {
            return '';
        }

        if ($this->durationHours < 8) {
            return $this->durationHours . 'h';
        }

        $days = intval($this->durationHours / 8);
        $remainingHours = $this->durationHours % 8;

        if ($remainingHours === 0) {
            return $days . ' jour' . ($days > 1 ? 's' : '');
        }

        return $days . ' jour' . ($days > 1 ? 's' : '') . ' ' . $remainingHours . 'h';
    }

    /**
     * Lifecycle callback to update the updatedAt timestamp
     */
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
