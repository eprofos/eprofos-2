<?php

namespace App\Entity\Alternance;

use App\Entity\Training\Session;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Repository\AlternanceContractRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * AlternanceContract entity representing an apprenticeship or professionalization contract
 * 
 * Contains all Qualiopi-compliant information about alternance contracts including
 * company details, supervision, objectives, and administrative data.
 */
#[ORM\Entity(repositoryClass: AlternanceContractRepository::class)]
#[ORM\Table(name: 'alternance_contracts')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class AlternanceContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'alternant est obligatoire.')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La session de formation est obligatoire.')]
    private ?Session $session = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de l\'entreprise est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom de l\'entreprise doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $companyName = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'L\'adresse de l\'entreprise est obligatoire.')]
    #[Gedmo\Versioned]
    private ?string $companyAddress = null;

    #[ORM\Column(length: 14)]
    #[Assert\NotBlank(message: 'Le SIRET est obligatoire.')]
    #[Assert\Length(exactly: 14, exactMessage: 'Le SIRET doit contenir exactement 14 chiffres.')]
    #[Assert\Regex(pattern: '/^\d{14}$/', message: 'Le SIRET doit contenir uniquement des chiffres.')]
    #[Gedmo\Versioned]
    private ?string $companySiret = null;

    #[ORM\ManyToOne(targetEntity: Mentor::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le tuteur entreprise est obligatoire.')]
    private ?Mentor $mentor = null;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le référent pédagogique est obligatoire.')]
    private ?Teacher $pedagogicalSupervisor = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type de contrat est obligatoire.')]
    #[Assert\Choice(
        choices: ['apprentissage', 'professionnalisation'],
        message: 'Type de contrat invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $contractType = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'startDate',
        message: 'La date de fin doit être postérieure à la date de début.'
    )]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 36,
        notInRangeMessage: 'La durée du contrat doit être comprise entre {{ min }} et {{ max }} mois.'
    )]
    #[Gedmo\Versioned]
    private ?int $duration = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'intitulé du poste est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'L\'intitulé du poste doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'intitulé du poste ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $jobTitle = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description du poste est obligatoire.')]
    #[Assert\Length(
        min: 50,
        minMessage: 'La description du poste doit contenir au moins {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $jobDescription = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les objectifs pédagogiques sont obligatoires.')]
    #[Assert\Count(min: 1, minMessage: 'Au moins un objectif pédagogique doit être défini.')]
    #[Gedmo\Versioned]
    private array $learningObjectives = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les objectifs entreprise sont obligatoires.')]
    #[Assert\Count(min: 1, minMessage: 'Au moins un objectif entreprise doit être défini.')]
    #[Gedmo\Versioned]
    private array $companyObjectives = [];

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le nombre d\'heures par semaine en centre est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre d\'heures doit être positif.')]
    #[Assert\LessThanOrEqual(value: 35, message: 'Le nombre d\'heures ne peut pas dépasser 35h par semaine.')]
    #[Gedmo\Versioned]
    private ?int $weeklyCenterHours = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le nombre d\'heures par semaine en entreprise est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre d\'heures doit être positif.')]
    #[Assert\LessThanOrEqual(value: 35, message: 'Le nombre d\'heures ne peut pas dépasser 35h par semaine.')]
    #[Gedmo\Versioned]
    private ?int $weeklyCompanyHours = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La rémunération est obligatoire.')]
    #[Gedmo\Versioned]
    private ?string $remuneration = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['draft', 'pending_validation', 'validated', 'active', 'suspended', 'completed', 'terminated'],
        message: 'Statut invalide.'
    )]
    #[Gedmo\Versioned]
    private string $status = 'draft';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    #[Gedmo\Versioned]
    private ?string $contractNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $companyContactPerson = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $companyContactEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $companyContactPhone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $objectives = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $tasks = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $evaluationCriteria = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'La rémunération doit être positive ou nulle.')]
    #[Gedmo\Versioned]
    private ?int $compensation = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $additionalData = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * Available contract statuses
     */
    public const STATUSES = [
        'draft' => 'Brouillon',
        'pending_validation' => 'En attente de validation',
        'validated' => 'Validé',
        'active' => 'Actif',
        'suspended' => 'Suspendu',
        'completed' => 'Terminé',
        'terminated' => 'Résilié'
    ];

    /**
     * Available contract types
     */
    public const CONTRACT_TYPES = [
        'apprentissage' => 'Contrat d\'apprentissage',
        'professionnalisation' => 'Contrat de professionnalisation'
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

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        return $this;
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

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(string $companyAddress): static
    {
        $this->companyAddress = $companyAddress;
        return $this;
    }

    public function getCompanySiret(): ?string
    {
        return $this->companySiret;
    }

    public function setCompanySiret(string $companySiret): static
    {
        $this->companySiret = $companySiret;
        return $this;
    }

    public function getMentor(): ?Mentor
    {
        return $this->mentor;
    }

    public function setMentor(?Mentor $mentor): static
    {
        $this->mentor = $mentor;
        return $this;
    }

    public function getPedagogicalSupervisor(): ?Teacher
    {
        return $this->pedagogicalSupervisor;
    }

    public function setPedagogicalSupervisor(?Teacher $pedagogicalSupervisor): static
    {
        $this->pedagogicalSupervisor = $pedagogicalSupervisor;
        return $this;
    }

    // Alias for form compatibility
    public function getTeacher(): ?Teacher
    {
        return $this->pedagogicalSupervisor;
    }

    public function setTeacher(?Teacher $teacher): static
    {
        $this->pedagogicalSupervisor = $teacher;
        return $this;
    }

    public function getContractType(): ?string
    {
        return $this->contractType;
    }

    public function setContractType(string $contractType): static
    {
        $this->contractType = $contractType;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(string $jobTitle): static
    {
        $this->jobTitle = $jobTitle;
        return $this;
    }

    public function getJobDescription(): ?string
    {
        return $this->jobDescription;
    }

    public function setJobDescription(string $jobDescription): static
    {
        $this->jobDescription = $jobDescription;
        return $this;
    }

    public function getLearningObjectives(): array
    {
        return $this->learningObjectives;
    }

    public function setLearningObjectives(array $learningObjectives): static
    {
        $this->learningObjectives = $learningObjectives;
        return $this;
    }

    public function getCompanyObjectives(): array
    {
        return $this->companyObjectives;
    }

    public function setCompanyObjectives(array $companyObjectives): static
    {
        $this->companyObjectives = $companyObjectives;
        return $this;
    }

    public function getWeeklyCenterHours(): ?int
    {
        return $this->weeklyCenterHours;
    }

    public function setWeeklyCenterHours(int $weeklyCenterHours): static
    {
        $this->weeklyCenterHours = $weeklyCenterHours;
        return $this;
    }

    public function getWeeklyCompanyHours(): ?int
    {
        return $this->weeklyCompanyHours;
    }

    public function setWeeklyCompanyHours(int $weeklyCompanyHours): static
    {
        $this->weeklyCompanyHours = $weeklyCompanyHours;
        return $this;
    }

    // Alias for form compatibility
    public function getWeeklyHours(): ?int
    {
        return $this->weeklyCompanyHours;
    }

    public function setWeeklyHours(?int $weeklyHours): static
    {
        $this->weeklyCompanyHours = $weeklyHours;
        return $this;
    }

    public function getRemuneration(): ?string
    {
        return $this->remuneration;
    }

    public function setRemuneration(string $remuneration): static
    {
        $this->remuneration = $remuneration;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getContractNumber(): ?string
    {
        return $this->contractNumber;
    }

    public function setContractNumber(?string $contractNumber): static
    {
        $this->contractNumber = $contractNumber;
        return $this;
    }

    public function getCompanyContactPerson(): ?string
    {
        return $this->companyContactPerson;
    }

    public function setCompanyContactPerson(?string $companyContactPerson): static
    {
        $this->companyContactPerson = $companyContactPerson;
        return $this;
    }

    public function getCompanyContactEmail(): ?string
    {
        return $this->companyContactEmail;
    }

    public function setCompanyContactEmail(?string $companyContactEmail): static
    {
        $this->companyContactEmail = $companyContactEmail;
        return $this;
    }

    public function getCompanyContactPhone(): ?string
    {
        return $this->companyContactPhone;
    }

    public function setCompanyContactPhone(?string $companyContactPhone): static
    {
        $this->companyContactPhone = $companyContactPhone;
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

    public function getTasks(): ?string
    {
        return $this->tasks;
    }

    public function setTasks(?string $tasks): static
    {
        $this->tasks = $tasks;
        return $this;
    }

    public function getEvaluationCriteria(): ?string
    {
        return $this->evaluationCriteria;
    }

    public function setEvaluationCriteria(?string $evaluationCriteria): static
    {
        $this->evaluationCriteria = $evaluationCriteria;
        return $this;
    }

    public function getCompensation(): ?int
    {
        return $this->compensation;
    }

    public function setCompensation(?int $compensation): static
    {
        $this->compensation = $compensation;
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

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    /**
     * Get contract duration in days
     */
    public function getDurationInDays(): int
    {
        if (!$this->startDate || !$this->endDate) {
            return 0;
        }

        $interval = $this->startDate->diff($this->endDate);
        return $interval->days + 1;
    }

    /**
     * Get contract duration in weeks
     */
    public function getDurationInWeeks(): int
    {
        return intval($this->getDurationInDays() / 7);
    }

    /**
     * Get contract duration in months
     */
    public function getDurationInMonths(): int
    {
        if (!$this->startDate || !$this->endDate) {
            return 0;
        }

        $start = new \DateTime($this->startDate->format('Y-m-d'));
        $end = new \DateTime($this->endDate->format('Y-m-d'));
        $interval = $start->diff($end);

        return ($interval->y * 12) + $interval->m;
    }

    /**
     * Get formatted contract duration
     */
    public function getFormattedDuration(): string
    {
        $months = $this->getDurationInMonths();
        
        if ($months < 1) {
            $weeks = $this->getDurationInWeeks();
            return $weeks === 1 ? '1 semaine' : $weeks . ' semaines';
        }

        if ($months < 12) {
            return $months === 1 ? '1 mois' : $months . ' mois';
        }

        $years = intval($months / 12);
        $remainingMonths = $months % 12;

        if ($remainingMonths === 0) {
            return $years === 1 ? '1 an' : $years . ' ans';
        }

        $yearText = $years === 1 ? '1 an' : $years . ' ans';
        $monthText = $remainingMonths === 1 ? '1 mois' : $remainingMonths . ' mois';

        return $yearText . ' et ' . $monthText;
    }

    /**
     * Get formatted date range
     */
    public function getFormattedDateRange(): string
    {
        if (!$this->startDate || !$this->endDate) {
            return '';
        }

        $start = $this->startDate->format('d/m/Y');
        $end = $this->endDate->format('d/m/Y');

        return "{$start} - {$end}";
    }

    /**
     * Get status label for display
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get status badge class for display
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'draft' => 'bg-secondary',
            'pending_validation' => 'bg-warning',
            'validated' => 'bg-info',
            'active' => 'bg-success',
            'suspended' => 'bg-warning text-dark',
            'completed' => 'bg-primary',
            'terminated' => 'bg-danger',
            default => 'bg-light text-dark'
        };
    }

    /**
     * Get contract type label for display
     */
    public function getContractTypeLabel(): string
    {
        return self::CONTRACT_TYPES[$this->contractType] ?? $this->contractType;
    }

    /**
     * Get total weekly hours
     */
    public function getTotalWeeklyHours(): int
    {
        return ($this->weeklyCenterHours ?? 0) + ($this->weeklyCompanyHours ?? 0);
    }

    /**
     * Get center hours percentage
     */
    public function getCenterHoursPercentage(): float
    {
        $total = $this->getTotalWeeklyHours();
        if ($total === 0) {
            return 0;
        }

        return round(($this->weeklyCenterHours / $total) * 100, 1);
    }

    /**
     * Get company hours percentage
     */
    public function getCompanyHoursPercentage(): float
    {
        $total = $this->getTotalWeeklyHours();
        if ($total === 0) {
            return 0;
        }

        return round(($this->weeklyCompanyHours / $total) * 100, 1);
    }

    /**
     * Check if contract is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if contract is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if contract is currently in progress
     */
    public function isInProgress(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $now = new \DateTime();
        return $this->startDate <= $now && $this->endDate >= $now;
    }

    /**
     * Get remaining days
     */
    public function getRemainingDays(): int
    {
        if (!$this->endDate || !$this->isActive()) {
            return 0;
        }

        $now = new \DateTime();
        if ($now > $this->endDate) {
            return 0;
        }

        $interval = $now->diff($this->endDate);
        return $interval->days;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if (!$this->startDate || !$this->endDate || !$this->isActive()) {
            return 0;
        }

        $now = new \DateTime();
        if ($now < $this->startDate) {
            return 0;
        }

        if ($now > $this->endDate) {
            return 100;
        }

        $totalDays = $this->getDurationInDays();
        $elapsedDays = $this->startDate->diff($now)->days;

        return round(($elapsedDays / $totalDays) * 100, 1);
    }

    /**
     * Get full student name
     */
    public function getStudentFullName(): string
    {
        return $this->student ? $this->student->getFullName() : '';
    }

    /**
     * Get mentor full name
     */
    public function getMentorFullName(): string
    {
        return $this->mentor ? $this->mentor->getFullName() : '';
    }

    /**
     * Get pedagogical supervisor full name
     */
    public function getPedagogicalSupervisorFullName(): string
    {
        return $this->pedagogicalSupervisor ? $this->pedagogicalSupervisor->getFullName() : '';
    }

    /**
     * Get formation title
     */
    public function getFormationTitle(): string
    {
        return $this->session?->getFormation()?->getTitle() ?? '';
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
        return $this->getStudentFullName() . ' - ' . $this->getCompanyName() . ' (' . $this->getContractTypeLabel() . ')';
    }
}
