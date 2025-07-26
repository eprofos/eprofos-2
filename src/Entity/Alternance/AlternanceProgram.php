<?php

namespace App\Entity\Alternance;

use App\Entity\Training\Session;
use App\Repository\AlternanceProgramRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * AlternanceProgram entity representing an alternance pedagogical program
 * 
 * Contains all pedagogical information for managing alternance training
 * including modules, coordination points, and assessment periods.
 */
#[ORM\Entity(repositoryClass: AlternanceProgramRepository::class)]
#[ORM\Table(name: 'alternance_programs')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class AlternanceProgram
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La session associée est obligatoire.')]
    private ?Session $session = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre du programme est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 50,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La durée totale est obligatoire.')]
    #[Assert\Positive(message: 'La durée totale doit être positive.')]
    #[Gedmo\Versioned]
    private ?int $totalDuration = null; // en semaines

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La durée en centre est obligatoire.')]
    #[Assert\Positive(message: 'La durée en centre doit être positive.')]
    #[Gedmo\Versioned]
    private ?int $centerDuration = null; // en semaines

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La durée en entreprise est obligatoire.')]
    #[Assert\Positive(message: 'La durée en entreprise doit être positive.')]
    #[Gedmo\Versioned]
    private ?int $companyDuration = null; // en semaines

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les modules centre sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $centerModules = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les modules entreprise sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $companyModules = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les points de coordination sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $coordinationPoints = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les périodes d\'évaluation sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $assessmentPeriods = [];

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le rythme d\'alternance est obligatoire.')]
    #[Gedmo\Versioned]
    private ?string $rhythm = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'La progression pédagogique est obligatoire.')]
    #[Gedmo\Versioned]
    private array $learningProgression = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $notes = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $additionalData = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Common rhythm patterns for alternance programs
     */
    public const RHYTHM_PATTERNS = [
        '1-1' => '1 semaine centre / 1 semaine entreprise',
        '2-2' => '2 semaines centre / 2 semaines entreprise',
        '3-1' => '3 semaines centre / 1 semaine entreprise',
        '1-3' => '1 semaine centre / 3 semaines entreprise',
        '2-3' => '2 semaines centre / 3 semaines entreprise',
        '3-2' => '3 semaines centre / 2 semaines entreprise',
        '4-4' => '4 semaines centre / 4 semaines entreprise',
        'custom' => 'Rythme personnalisé'
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

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
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

    public function getTotalDuration(): ?int
    {
        return $this->totalDuration;
    }

    public function setTotalDuration(int $totalDuration): static
    {
        $this->totalDuration = $totalDuration;
        return $this;
    }

    public function getCenterDuration(): ?int
    {
        return $this->centerDuration;
    }

    public function setCenterDuration(int $centerDuration): static
    {
        $this->centerDuration = $centerDuration;
        return $this;
    }

    public function getCompanyDuration(): ?int
    {
        return $this->companyDuration;
    }

    public function setCompanyDuration(int $companyDuration): static
    {
        $this->companyDuration = $companyDuration;
        return $this;
    }

    public function getCenterModules(): array
    {
        return $this->centerModules;
    }

    public function setCenterModules(array $centerModules): static
    {
        $this->centerModules = $centerModules;
        return $this;
    }

    public function getCompanyModules(): array
    {
        return $this->companyModules;
    }

    public function setCompanyModules(array $companyModules): static
    {
        $this->companyModules = $companyModules;
        return $this;
    }

    public function getCoordinationPoints(): array
    {
        return $this->coordinationPoints;
    }

    public function setCoordinationPoints(array $coordinationPoints): static
    {
        $this->coordinationPoints = $coordinationPoints;
        return $this;
    }

    public function getAssessmentPeriods(): array
    {
        return $this->assessmentPeriods;
    }

    public function setAssessmentPeriods(array $assessmentPeriods): static
    {
        $this->assessmentPeriods = $assessmentPeriods;
        return $this;
    }

    public function getRhythm(): ?string
    {
        return $this->rhythm;
    }

    public function setRhythm(string $rhythm): static
    {
        $this->rhythm = $rhythm;
        return $this;
    }

    public function getLearningProgression(): array
    {
        return $this->learningProgression;
    }

    public function setLearningProgression(array $learningProgression): static
    {
        $this->learningProgression = $learningProgression;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getAdditionalData(): ?array
    {
        return $this->additionalData;
    }

    public function setAdditionalData(?array $additionalData): static
    {
        $this->additionalData = $additionalData;
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

    /**
     * Get center duration percentage
     */
    public function getCenterDurationPercentage(): float
    {
        if ($this->totalDuration === 0) {
            return 0;
        }

        return round(($this->centerDuration / $this->totalDuration) * 100, 1);
    }

    /**
     * Get company duration percentage
     */
    public function getCompanyDurationPercentage(): float
    {
        if ($this->totalDuration === 0) {
            return 0;
        }

        return round(($this->companyDuration / $this->totalDuration) * 100, 1);
    }

    /**
     * Check if durations are consistent
     */
    public function hasConsistentDurations(): bool
    {
        return ($this->centerDuration + $this->companyDuration) === $this->totalDuration;
    }

    /**
     * Get formatted total duration
     */
    public function getFormattedTotalDuration(): string
    {
        if (!$this->totalDuration) {
            return '';
        }

        if ($this->totalDuration === 1) {
            return '1 semaine';
        }

        if ($this->totalDuration < 52) {
            return $this->totalDuration . ' semaines';
        }

        $years = intval($this->totalDuration / 52);
        $remainingWeeks = $this->totalDuration % 52;

        if ($remainingWeeks === 0) {
            return $years === 1 ? '1 an' : $years . ' ans';
        }

        $yearText = $years === 1 ? '1 an' : $years . ' ans';
        $weekText = $remainingWeeks === 1 ? '1 semaine' : $remainingWeeks . ' semaines';

        return $yearText . ' et ' . $weekText;
    }

    /**
     * Get rhythm description
     */
    public function getRhythmDescription(): string
    {
        return self::RHYTHM_PATTERNS[$this->rhythm] ?? $this->rhythm;
    }

    /**
     * Get number of center modules
     */
    public function getCenterModulesCount(): int
    {
        return count($this->centerModules);
    }

    /**
     * Get number of company modules
     */
    public function getCompanyModulesCount(): int
    {
        return count($this->companyModules);
    }

    /**
     * Get number of coordination points
     */
    public function getCoordinationPointsCount(): int
    {
        return count($this->coordinationPoints);
    }

    /**
     * Get number of assessment periods
     */
    public function getAssessmentPeriodsCount(): int
    {
        return count($this->assessmentPeriods);
    }

    /**
     * Get learning progression steps count
     */
    public function getLearningProgressionStepsCount(): int
    {
        return count($this->learningProgression);
    }

    /**
     * Get formation title
     */
    public function getFormationTitle(): string
    {
        return $this->session?->getFormation()?->getTitle() ?? '';
    }

    /**
     * Get session name
     */
    public function getSessionName(): string
    {
        return $this->session?->getName() ?? '';
    }

    /**
     * Check if program has center modules
     */
    public function hasCenterModules(): bool
    {
        return !empty($this->centerModules);
    }

    /**
     * Check if program has company modules
     */
    public function hasCompanyModules(): bool
    {
        return !empty($this->companyModules);
    }

    /**
     * Check if program has coordination points
     */
    public function hasCoordinationPoints(): bool
    {
        return !empty($this->coordinationPoints);
    }

    /**
     * Check if program has assessment periods
     */
    public function hasAssessmentPeriods(): bool
    {
        return !empty($this->assessmentPeriods);
    }

    /**
     * Get center modules titles
     */
    public function getCenterModulesTitles(): array
    {
        return array_column($this->centerModules, 'title');
    }

    /**
     * Get company modules titles
     */
    public function getCompanyModulesTitles(): array
    {
        return array_column($this->companyModules, 'title');
    }

    /**
     * Get coordination points summaries
     */
    public function getCoordinationPointsSummaries(): array
    {
        return array_column($this->coordinationPoints, 'summary');
    }

    /**
     * Get assessment periods names
     */
    public function getAssessmentPeriodsNames(): array
    {
        return array_column($this->assessmentPeriods, 'name');
    }

    /**
     * Get learning progression milestones
     */
    public function getLearningProgressionMilestones(): array
    {
        return array_column($this->learningProgression, 'milestone');
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
