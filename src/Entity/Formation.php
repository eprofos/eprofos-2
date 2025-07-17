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

    /**
     * Operational objectives that participants will achieve (required by Qualiopi 2.5)
     *
     * These are concrete, actionable objectives that define what participants
     * will be able to do after completing the training.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $operationalObjectives = null;

    /**
     * Evaluable objectives with measurable criteria (required by Qualiopi 2.5)
     *
     * These are objectives that can be measured and evaluated with specific
     * criteria and success indicators.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $evaluableObjectives = null;

    /**
     * Evaluation criteria for measuring objective achievement (required by Qualiopi 2.5)
     *
     * Specific criteria and methods used to evaluate whether the objectives
     * have been achieved by participants.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $evaluationCriteria = null;

    /**
     * Success indicators for tracking objective achievement (required by Qualiopi 2.5)
     *
     * Measurable indicators that demonstrate successful achievement of the
     * training objectives.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $successIndicators = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $prerequisites = null;

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

    /**
     * @var Collection<int, Module>
     */
    #[ORM\OneToMany(targetEntity: Module::class, mappedBy: 'formation', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $modules;

    /**
     * @var Collection<int, Session>
     */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'formation', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['startDate' => 'ASC'])]
    private Collection $sessions;

    public function __construct()
    {
        $this->contactRequests = new ArrayCollection();
        $this->modules = new ArrayCollection();
        $this->sessions = new ArrayCollection();
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

    public function getOperationalObjectives(): ?array
    {
        return $this->operationalObjectives;
    }

    public function setOperationalObjectives(?array $operationalObjectives): static
    {
        $this->operationalObjectives = $operationalObjectives;
        return $this;
    }

    public function getEvaluableObjectives(): ?array
    {
        return $this->evaluableObjectives;
    }

    public function setEvaluableObjectives(?array $evaluableObjectives): static
    {
        $this->evaluableObjectives = $evaluableObjectives;
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

    public function getSuccessIndicators(): ?array
    {
        return $this->successIndicators;
    }

    public function setSuccessIndicators(?array $successIndicators): static
    {
        $this->successIndicators = $successIndicators;
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

    /**
     * @return Collection<int, Module>
     */
    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function addModule(Module $module): static
    {
        if (!$this->modules->contains($module)) {
            $this->modules->add($module);
            $module->setFormation($this);
        }

        return $this;
    }

    public function removeModule(Module $module): static
    {
        if ($this->modules->removeElement($module)) {
            // set the owning side to null (unless already changed)
            if ($module->getFormation() === $this) {
                $module->setFormation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Session>
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(Session $session): static
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
            $session->setFormation($this);
        }

        return $this;
    }

    public function removeSession(Session $session): static
    {
        if ($this->sessions->removeElement($session)) {
            // set the owning side to null (unless already changed)
            if ($session->getFormation() === $this) {
                $session->setFormation(null);
            }
        }

        return $this;
    }

    /**
     * Get active modules for this formation
     * 
     * @return Collection<int, Module>
     */
    public function getActiveModules(): Collection
    {
        return $this->modules->filter(function (Module $module) {
            return $module->isActive();
        });
    }

    /**
     * Get upcoming sessions for this formation
     * 
     * @return Collection<int, Session>
     */
    public function getUpcomingSessions(): Collection
    {
        $now = new \DateTime();
        return $this->sessions->filter(function (Session $session) use ($now) {
            return $session->isActive() && $session->getStartDate() > $now;
        });
    }

    /**
     * Get open sessions for this formation (available for registration)
     * 
     * @return Collection<int, Session>
     */
    public function getOpenSessions(): Collection
    {
        $now = new \DateTime();
        return $this->sessions->filter(function (Session $session) use ($now) {
            return $session->isActive() 
                && $session->getStatus() === 'open'
                && $session->getStartDate() > $now
                && !$session->isFull()
                && $session->isRegistrationOpen();
        });
    }

    /**
     * Get the next upcoming session
     */
    public function getNextSession(): ?Session
    {
        $upcomingSessions = $this->getUpcomingSessions();
        
        if ($upcomingSessions->isEmpty()) {
            return null;
        }

        $sessionsArray = $upcomingSessions->toArray();
        usort($sessionsArray, function (Session $a, Session $b) {
            return $a->getStartDate() <=> $b->getStartDate();
        });

        return $sessionsArray[0];
    }

    /**
     * Check if formation has available sessions for registration
     */
    public function hasAvailableSessions(): bool
    {
        return !$this->getOpenSessions()->isEmpty();
    }

    /**
     * Get total duration of all modules in this formation
     */
    public function getTotalModulesDuration(): int
    {
        $totalDuration = 0;
        foreach ($this->modules as $module) {
            $totalDuration += $module->getDurationHours();
        }
        return $totalDuration;
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
     * Generate program content from modules and chapters
     */
    public function getGeneratedProgram(): string
    {
        $program = '';
        $activeModules = $this->getActiveModules();
        
        if ($activeModules->isEmpty()) {
            return 'Aucun module configuré pour cette formation.';
        }
        
        foreach ($activeModules as $index => $module) {
            $moduleNumber = $index + 1;
            $program .= "Module {$moduleNumber}: {$module->getTitle()}";
            
            if ($module->getDurationHours()) {
                $program .= " ({$module->getFormattedDuration()})";
            }
            
            $program .= "\n";
            
            if ($module->getDescription()) {
                $program .= "- {$module->getDescription()}\n";
            }
            
            // Add learning objectives if available
            if ($module->getLearningObjectives()) {
                $program .= "Objectifs :\n";
                foreach ($module->getLearningObjectives() as $objective) {
                    $program .= "• {$objective}\n";
                }
            }
            
            // Add chapters if available
            $activeChapters = $module->getActiveChapters();
            if (!$activeChapters->isEmpty()) {
                $program .= "Contenu :\n";
                foreach ($activeChapters as $chapter) {
                    $program .= "• {$chapter->getTitle()}";
                    if ($chapter->getDurationMinutes()) {
                        $program .= " ({$chapter->getFormattedDuration()})";
                    }
                    $program .= "\n";
                }
            }
            
            $program .= "\n";
        }
        
        return $program;
    }

    /**
     * Get program content - always returns generated program from modules and chapters
     */
    public function getProgramContent(): string
    {
        return $this->getGeneratedProgram();
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