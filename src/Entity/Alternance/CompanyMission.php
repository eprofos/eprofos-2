<?php

declare(strict_types=1);

namespace App\Entity\Alternance;

use App\Entity\User\Mentor;
use App\Repository\Alternance\CompanyMissionRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CompanyMission entity representing a mission/project for apprentices in companies.
 *
 * Defines structured missions with learning objectives, required skills,
 * complexity levels, and progression logic for Qualiopi compliance.
 */
#[ORM\Entity(repositoryClass: CompanyMissionRepository::class)]
#[ORM\Table(name: 'company_missions')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class CompanyMission
{
    /**
     * Available complexity levels for missions.
     */
    public const COMPLEXITY_LEVELS = [
        'debutant' => 'Débutant',
        'intermediaire' => 'Intermédiaire',
        'avance' => 'Avancé',
    ];

    /**
     * Available terms for missions.
     */
    public const TERMS = [
        'court' => 'Court terme (1-4 semaines)',
        'moyen' => 'Moyen terme (1-3 mois)',
        'long' => 'Long terme (3+ mois)',
    ];

    /**
     * Common mission departments/services.
     */
    public const DEPARTMENTS = [
        'informatique' => 'Informatique & Systèmes',
        'commercial' => 'Commercial & Vente',
        'marketing' => 'Marketing & Communication',
        'rh' => 'Ressources Humaines',
        'finance' => 'Finance & Comptabilité',
        'production' => 'Production & Qualité',
        'logistique' => 'Logistique & Supply Chain',
        'juridique' => 'Juridique & Compliance',
        'direction' => 'Direction & Management',
        'rd' => 'Recherche & Développement',
        'formation' => 'Formation & Développement',
        'autre' => 'Autre département',
    ];

    /**
     * Typical duration options for missions.
     */
    public const DURATION_OPTIONS = [
        '1-2 semaines',
        '3-4 semaines',
        '1-2 mois',
        '3-4 mois',
        '6 mois',
        '1 an',
        'Récurrente',
        'À définir',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre de la mission est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Gedmo\Versioned]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description de la mission est obligatoire.')]
    #[Assert\Length(
        min: 20,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.',
    )]
    #[Gedmo\Versioned]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contexte de la mission est obligatoire.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Le contexte doit contenir au moins {{ limit }} caractères.',
    )]
    #[Gedmo\Versioned]
    private ?string $context = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les objectifs de la mission sont obligatoires.')]
    #[Assert\Count(
        min: 1,
        minMessage: 'Au moins un objectif doit être défini.',
    )]
    #[Gedmo\Versioned]
    private array $objectives = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les compétences requises sont obligatoires.')]
    #[Assert\Count(
        min: 1,
        minMessage: 'Au moins une compétence requise doit être définie.',
    )]
    #[Gedmo\Versioned]
    private array $requiredSkills = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les compétences à acquérir sont obligatoires.')]
    #[Assert\Count(
        min: 1,
        minMessage: 'Au moins une compétence à acquérir doit être définie.',
    )]
    #[Gedmo\Versioned]
    private array $skillsToAcquire = [];

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La durée estimée est obligatoire.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'La durée ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Gedmo\Versioned]
    private ?string $duration = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le niveau de complexité est obligatoire.')]
    #[Assert\Choice(
        choices: ['debutant', 'intermediaire', 'avance'],
        message: 'Niveau de complexité invalide.',
    )]
    #[Gedmo\Versioned]
    private ?string $complexity = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le terme de la mission est obligatoire.')]
    #[Assert\Choice(
        choices: ['court', 'moyen', 'long'],
        message: 'Terme de mission invalide.',
    )]
    #[Gedmo\Versioned]
    private ?string $term = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull(message: 'L\'ordre de progression est obligatoire.')]
    #[Assert\Positive(message: 'L\'ordre de progression doit être un nombre positif.')]
    #[Gedmo\Versioned]
    private ?int $orderIndex = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le service/département est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 150,
        minMessage: 'Le département doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le département ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Gedmo\Versioned]
    private ?string $department = null;

    #[ORM\ManyToOne(targetEntity: Mentor::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le responsable de mission est obligatoire.')]
    private ?Mentor $supervisor = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les prérequis pédagogiques sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $prerequisites = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les critères d\'évaluation sont obligatoires.')]
    #[Assert\Count(
        min: 1,
        minMessage: 'Au moins un critère d\'évaluation doit être défini.',
    )]
    #[Gedmo\Versioned]
    private array $evaluationCriteria = [];

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'create')]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'update')]
    private ?DateTimeImmutable $updatedAt = null;

    /**
     * Collection of mission assignments for this mission.
     *
     * @var Collection<int, MissionAssignment>
     */
    #[ORM\OneToMany(mappedBy: 'mission', targetEntity: MissionAssignment::class, cascade: ['persist', 'remove'])]
    private Collection $assignments;

    public function __construct()
    {
        $this->assignments = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->title ?: 'Mission #' . $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(string $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getObjectives(): array
    {
        return $this->objectives;
    }

    public function setObjectives(array $objectives): static
    {
        $this->objectives = $objectives;

        return $this;
    }

    public function getRequiredSkills(): array
    {
        return $this->requiredSkills;
    }

    public function setRequiredSkills(array $requiredSkills): static
    {
        $this->requiredSkills = $requiredSkills;

        return $this;
    }

    public function getSkillsToAcquire(): array
    {
        return $this->skillsToAcquire;
    }

    public function setSkillsToAcquire(array $skillsToAcquire): static
    {
        $this->skillsToAcquire = $skillsToAcquire;

        return $this;
    }

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration(string $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getComplexity(): ?string
    {
        return $this->complexity;
    }

    public function setComplexity(string $complexity): static
    {
        $this->complexity = $complexity;

        return $this;
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function setTerm(string $term): static
    {
        $this->term = $term;

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

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(string $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function getSupervisor(): ?Mentor
    {
        return $this->supervisor;
    }

    public function setSupervisor(?Mentor $supervisor): static
    {
        $this->supervisor = $supervisor;

        return $this;
    }

    public function getPrerequisites(): array
    {
        return $this->prerequisites;
    }

    public function setPrerequisites(array $prerequisites): static
    {
        $this->prerequisites = $prerequisites;

        return $this;
    }

    public function getEvaluationCriteria(): array
    {
        return $this->evaluationCriteria;
    }

    public function setEvaluationCriteria(array $evaluationCriteria): static
    {
        $this->evaluationCriteria = $evaluationCriteria;

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

    /**
     * @return Collection<int, MissionAssignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(MissionAssignment $assignment): static
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
            $assignment->setMission($this);
        }

        return $this;
    }

    public function removeAssignment(MissionAssignment $assignment): static
    {
        if ($this->assignments->removeElement($assignment)) {
            // set the owning side to null (unless already changed)
            if ($assignment->getMission() === $this) {
                $assignment->setMission(null);
            }
        }

        return $this;
    }

    /**
     * Get the complexity level as human-readable label.
     */
    public function getComplexityLabel(): string
    {
        return self::COMPLEXITY_LEVELS[$this->complexity] ?? $this->complexity;
    }

    /**
     * Get the term as human-readable label.
     */
    public function getTermLabel(): string
    {
        return self::TERMS[$this->term] ?? $this->term;
    }

    /**
     * Get the department as human-readable label.
     */
    public function getDepartmentLabel(): string
    {
        return self::DEPARTMENTS[$this->department] ?? $this->department;
    }

    /**
     * Get the estimated duration in weeks (for calculation purposes).
     */
    public function getEstimatedWeeks(): int
    {
        return match ($this->term) {
            'court' => 2,
            'moyen' => 8,
            'long' => 16,
            default => 4
        };
    }

    /**
     * Check if this mission is suitable for a given complexity level.
     */
    public function isSuitableForComplexity(string $complexity): bool
    {
        $complexityOrder = ['debutant' => 1, 'intermediaire' => 2, 'avance' => 3];
        $missionLevel = $complexityOrder[$this->complexity] ?? 1;
        $targetLevel = $complexityOrder[$complexity] ?? 1;

        return $missionLevel <= $targetLevel;
    }

    /**
     * Get the number of active assignments for this mission.
     */
    public function getActiveAssignmentsCount(): int
    {
        return $this->assignments->filter(static fn (MissionAssignment $assignment) => in_array($assignment->getStatus(), ['planifiee', 'en_cours'], true))->count();
    }

    /**
     * Get the number of completed assignments for this mission.
     */
    public function getCompletedAssignmentsCount(): int
    {
        return $this->assignments->filter(static fn (MissionAssignment $assignment) => $assignment->getStatus() === 'terminee')->count();
    }

    /**
     * Check if mission has prerequisite skills.
     */
    public function hasPrerequisites(): bool
    {
        return !empty($this->prerequisites);
    }

    /**
     * Get a summary of the mission objectives (first 2 objectives).
     */
    public function getObjectivesSummary(): string
    {
        if (empty($this->objectives)) {
            return 'Aucun objectif défini';
        }

        $summary = array_slice($this->objectives, 0, 2);
        $text = implode(' • ', $summary);

        if (count($this->objectives) > 2) {
            $text .= ' • +' . (count($this->objectives) - 2) . ' autre(s)';
        }

        return $text;
    }

    /**
     * Get a summary of skills to acquire (first 3 skills).
     */
    public function getSkillsSummary(): string
    {
        if (empty($this->skillsToAcquire)) {
            return 'Aucune compétence définie';
        }

        $summary = array_slice($this->skillsToAcquire, 0, 3);
        $text = implode(', ', $summary);

        if (count($this->skillsToAcquire) > 3) {
            $text .= ', +' . (count($this->skillsToAcquire) - 3) . ' autre(s)';
        }

        return $text;
    }

    /**
     * Calculate mission progress score based on assignments.
     */
    public function getProgressScore(): float
    {
        if ($this->assignments->isEmpty()) {
            return 0.0;
        }

        $totalProgress = 0;
        $count = 0;

        foreach ($this->assignments as $assignment) {
            if ($assignment->getStatus() !== 'suspendue') {
                $totalProgress += $assignment->getCompletionRate();
                $count++;
            }
        }

        return $count > 0 ? $totalProgress / $count : 0.0;
    }

    /**
     * Get the CSS class for the complexity badge.
     */
    public function getComplexityBadgeClass(): string
    {
        return match ($this->complexity) {
            'debutant' => 'badge-success',
            'intermediaire' => 'badge-warning',
            'avance' => 'badge-danger',
            default => 'badge-secondary'
        };
    }

    /**
     * Get the CSS class for the term badge.
     */
    public function getTermBadgeClass(): string
    {
        return match ($this->term) {
            'court' => 'badge-info',
            'moyen' => 'badge-primary',
            'long' => 'badge-dark',
            default => 'badge-secondary'
        };
    }

    /**
     * Lifecycle callback to update the updatedAt timestamp.
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
