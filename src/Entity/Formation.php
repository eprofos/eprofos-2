<?php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Formation entity representing a training course
 * 
 * Contains all information about a formation including description,
 * objectives, prerequisites, program, pricing, and categorization.
 */
#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Formation
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

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $objectives = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $prerequisites = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $program = null;

    #[ORM\Column]
    private ?int $durationHours = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(length: 50)]
    private ?string $level = null;

    #[ORM\Column(length: 50)]
    private ?string $format = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?bool $isFeatured = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Target audience for the formation (required by Qualiopi)
     *
     * Description of the target audience concerned by the training
     * (e.g., employees, job seekers, students, professionals, etc.)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $targetAudience = null;

    /**
     * Access modalities and deadlines for the formation (required by Qualiopi)
     *
     * Information about how and when participants can access the training,
     * including registration deadlines, prerequisites validation, etc.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $accessModalities = null;

    /**
     * Accessibility for people with disabilities (required by Qualiopi)
     *
     * Description of accommodations and accessibility measures available
     * for participants with disabilities or special needs.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $handicapAccessibility = null;

    /**
     * Teaching methods used in the formation (required by Qualiopi)
     *
     * Description of pedagogical approaches, methodologies, and techniques
     * employed during the training (e.g., lectures, workshops, case studies, etc.)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $teachingMethods = null;

    /**
     * Evaluation methods for learning outcomes (required by Qualiopi)
     *
     * Description of how participant knowledge and skills are assessed
     * throughout and at the end of the training program.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $evaluationMethods = null;

    /**
     * Contact information for pedagogical and administrative support (required by Qualiopi)
     *
     * Contact details of the pedagogical coordinator or administrative
     * reference person for the training program.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contactInfo = null;

    /**
     * Training location(s) information (required by Qualiopi)
     *
     * Description of where the training takes place, including physical
     * addresses, online platforms, or hybrid arrangements.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $trainingLocation = null;

    /**
     * Available funding modalities for the formation (required by Qualiopi)
     *
     * Information about possible funding options such as CPF, OPCO,
     * company funding, personal payment, etc.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fundingModalities = null;

    #[ORM\ManyToOne(inversedBy: 'formations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    /**
     * @var Collection<int, ContactRequest>
     */
    #[ORM\OneToMany(targetEntity: ContactRequest::class, mappedBy: 'formation')]
    private Collection $contactRequests;

    public function __construct()
    {
        $this->contactRequests = new ArrayCollection();
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

    public function getObjectives(): ?string
    {
        return $this->objectives;
    }

    public function setObjectives(?string $objectives): static
    {
        $this->objectives = $objectives;
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

    public function getProgram(): ?string
    {
        return $this->program;
    }

    public function setProgram(?string $program): static
    {
        $this->program = $program;
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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;
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

    public function isFeatured(): ?bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    /**
     * Get the image filename for the formation
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * Set the image filename for the formation
     */
    public function setImage(?string $image): static
    {
        $this->image = $image;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Collection<int, ContactRequest>
     */
    public function getContactRequests(): Collection
    {
        return $this->contactRequests;
    }

    public function addContactRequest(ContactRequest $contactRequest): static
    {
        if (!$this->contactRequests->contains($contactRequest)) {
            $this->contactRequests->add($contactRequest);
            $contactRequest->setFormation($this);
        }

        return $this;
    }

    public function removeContactRequest(ContactRequest $contactRequest): static
    {
        if ($this->contactRequests->removeElement($contactRequest)) {
            // set the owning side to null (unless already changed)
            if ($contactRequest->getFormation() === $this) {
                $contactRequest->setFormation(null);
            }
        }

        return $this;
    }

    public function getTargetAudience(): ?string
    {
        return $this->targetAudience;
    }

    public function setTargetAudience(?string $targetAudience): static
    {
        $this->targetAudience = $targetAudience;
        return $this;
    }

    public function getAccessModalities(): ?string
    {
        return $this->accessModalities;
    }

    public function setAccessModalities(?string $accessModalities): static
    {
        $this->accessModalities = $accessModalities;
        return $this;
    }

    public function getHandicapAccessibility(): ?string
    {
        return $this->handicapAccessibility;
    }

    public function setHandicapAccessibility(?string $handicapAccessibility): static
    {
        $this->handicapAccessibility = $handicapAccessibility;
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

    public function getEvaluationMethods(): ?string
    {
        return $this->evaluationMethods;
    }

    public function setEvaluationMethods(?string $evaluationMethods): static
    {
        $this->evaluationMethods = $evaluationMethods;
        return $this;
    }

    public function getContactInfo(): ?string
    {
        return $this->contactInfo;
    }

    public function setContactInfo(?string $contactInfo): static
    {
        $this->contactInfo = $contactInfo;
        return $this;
    }

    public function getTrainingLocation(): ?string
    {
        return $this->trainingLocation;
    }

    public function setTrainingLocation(?string $trainingLocation): static
    {
        $this->trainingLocation = $trainingLocation;
        return $this;
    }

    public function getFundingModalities(): ?string
    {
        return $this->fundingModalities;
    }

    public function setFundingModalities(?string $fundingModalities): static
    {
        $this->fundingModalities = $fundingModalities;
        return $this;
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
     * Get formatted price with currency
     */
    public function getFormattedPrice(): string
    {
        return number_format((float) $this->price, 0, ',', ' ') . ' €';
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