<?php

namespace App\Entity\Training;

use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Session entity representing a training session for a formation
 * 
 * Contains all information about a specific session including dates,
 * location, pricing, capacity, and registration status.
 */
#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: 'sessions')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la session est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
    #[Assert\GreaterThan(
        'today',
        message: 'La date de début doit être ultérieure à aujourd\'hui.'
    )]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'startDate',
        message: 'La date de fin doit être postérieure à la date de début.'
    )]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\LessThan(
        propertyPath: 'startDate',
        message: 'La date limite d\'inscription doit être antérieure à la date de début.'
    )]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $registrationDeadline = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Gedmo\Versioned]
    private ?string $location = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $address = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La capacité maximale est obligatoire.')]
    #[Assert\Positive(message: 'La capacité doit être un nombre positif.')]
    #[Gedmo\Versioned]
    private ?int $maxCapacity = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'La capacité minimum ne peut pas être négative.')]
    #[Gedmo\Versioned]
    private int $minCapacity = 0;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private int $currentRegistrations = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    #[Gedmo\Versioned]
    private ?string $price = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['planned', 'open', 'confirmed', 'cancelled', 'completed'],
        message: 'Statut invalide.'
    )]
    #[Gedmo\Versioned]
    private string $status = 'planned';

    #[ORM\Column]
    #[Gedmo\Versioned]
    private bool $isActive = true;

    #[ORM\Column(length: 100, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $instructor = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $notes = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $additionalInfo = null;

    // Alternance-related fields
    #[ORM\Column(nullable: true)]
    #[Gedmo\Versioned]
    private ?bool $isAlternanceSession = false;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ['apprentissage', 'professionnalisation'],
        message: 'Type d\'alternance invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $alternanceType = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'La durée minimale doit être positive.')]
    #[Gedmo\Versioned]
    private ?int $minimumAlternanceDuration = null; // en semaines

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le pourcentage doit être entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $centerPercentage = null; // % temps centre

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le pourcentage doit être entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $companyPercentage = null; // % temps entreprise

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $alternancePrerequisites = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $alternanceRhythm = null; // Rythme alternance

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formation $formation = null;

    /**
     * @var Collection<int, SessionRegistration>
     */
    #[ORM\OneToMany(targetEntity: SessionRegistration::class, mappedBy: 'session', cascade: ['persist', 'remove'])]
    private Collection $registrations;

    public function __construct()
    {
        $this->registrations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getRegistrationDeadline(): ?\DateTimeInterface
    {
        return $this->registrationDeadline;
    }

    public function setRegistrationDeadline(?\DateTimeInterface $registrationDeadline): static
    {
        $this->registrationDeadline = $registrationDeadline;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getMaxCapacity(): ?int
    {
        return $this->maxCapacity;
    }

    public function setMaxCapacity(int $maxCapacity): static
    {
        $this->maxCapacity = $maxCapacity;
        return $this;
    }

    public function getMinCapacity(): int
    {
        return $this->minCapacity;
    }

    public function setMinCapacity(int $minCapacity): static
    {
        $this->minCapacity = $minCapacity;
        return $this;
    }

    public function getCurrentRegistrations(): int
    {
        return $this->currentRegistrations;
    }

    public function setCurrentRegistrations(int $currentRegistrations): static
    {
        $this->currentRegistrations = $currentRegistrations;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getInstructor(): ?string
    {
        return $this->instructor;
    }

    public function setInstructor(?string $instructor): static
    {
        $this->instructor = $instructor;
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

    public function getAdditionalInfo(): ?array
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(?array $additionalInfo): static
    {
        $this->additionalInfo = $additionalInfo;
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
     * @return Collection<int, SessionRegistration>
     */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    public function addRegistration(SessionRegistration $registration): static
    {
        if (!$this->registrations->contains($registration)) {
            $this->registrations->add($registration);
            $registration->setSession($this);
        }

        return $this;
    }

    public function removeRegistration(SessionRegistration $registration): static
    {
        if ($this->registrations->removeElement($registration)) {
            // set the owning side to null (unless already changed)
            if ($registration->getSession() === $this) {
                $registration->setSession(null);
            }
        }

        return $this;
    }

    // Alternance-related getters and setters
    public function isAlternanceSession(): bool
    {
        return $this->isAlternanceSession ?? false;
    }

    public function setIsAlternanceSession(?bool $isAlternanceSession): static
    {
        $this->isAlternanceSession = $isAlternanceSession;
        return $this;
    }

    public function getAlternanceType(): ?string
    {
        return $this->alternanceType;
    }

    public function setAlternanceType(?string $alternanceType): static
    {
        $this->alternanceType = $alternanceType;
        return $this;
    }

    public function getMinimumAlternanceDuration(): ?int
    {
        return $this->minimumAlternanceDuration;
    }

    public function setMinimumAlternanceDuration(?int $minimumAlternanceDuration): static
    {
        $this->minimumAlternanceDuration = $minimumAlternanceDuration;
        return $this;
    }

    public function getCenterPercentage(): ?int
    {
        return $this->centerPercentage;
    }

    public function setCenterPercentage(?int $centerPercentage): static
    {
        $this->centerPercentage = $centerPercentage;
        return $this;
    }

    public function getCompanyPercentage(): ?int
    {
        return $this->companyPercentage;
    }

    public function setCompanyPercentage(?int $companyPercentage): static
    {
        $this->companyPercentage = $companyPercentage;
        return $this;
    }

    public function getAlternancePrerequisites(): ?array
    {
        return $this->alternancePrerequisites;
    }

    public function setAlternancePrerequisites(?array $alternancePrerequisites): static
    {
        $this->alternancePrerequisites = $alternancePrerequisites;
        return $this;
    }

    public function getAlternanceRhythm(): ?string
    {
        return $this->alternanceRhythm;
    }

    public function setAlternanceRhythm(?string $alternanceRhythm): static
    {
        $this->alternanceRhythm = $alternanceRhythm;
        return $this;
    }

    /**
     * Get the available places
     */
    public function getAvailablePlaces(): int
    {
        return $this->maxCapacity - $this->currentRegistrations;
    }

    /**
     * Check if session is full
     */
    public function isFull(): bool
    {
        return $this->currentRegistrations >= $this->maxCapacity;
    }

    /**
     * Check if registration is open
     */
    public function isRegistrationOpen(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->status !== 'open') {
            return false;
        }

        if ($this->isFull()) {
            return false;
        }

        // Check registration deadline
        if ($this->registrationDeadline && $this->registrationDeadline < new \DateTime()) {
            return false;
        }

        return true;
    }

    /**
     * Check if session can be confirmed (minimum capacity reached)
     */
    public function canBeConfirmed(): bool
    {
        return $this->currentRegistrations >= $this->minCapacity;
    }

    /**
     * Get session duration in days
     */
    public function getDurationInDays(): int
    {
        if (!$this->startDate || !$this->endDate) {
            return 0;
        }

        $interval = $this->startDate->diff($this->endDate);
        return $interval->days + 1; // +1 to include both start and end days
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

        if ($start === $end) {
            return $start;
        }

        return "{$start} - {$end}";
    }

    /**
     * Get formatted price with currency
     */
    public function getFormattedPrice(): string
    {
        if ($this->price === null) {
            // Use formation price if no specific session price
            return $this->formation ? $this->formation->getFormattedPrice() : 'Prix sur demande';
        }

        return number_format((float) $this->price, 0, ',', ' ') . ' €';
    }

    /**
     * Get the effective price (session price or formation price)
     */
    public function getEffectivePrice(): ?string
    {
        return $this->price ?? $this->formation?->getPrice();
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'planned' => 'Planifiée',
            'open' => 'Inscriptions ouvertes',
            'confirmed' => 'Confirmée',
            'cancelled' => 'Annulée',
            'completed' => 'Terminée',
            default => 'Inconnu'
        };
    }

    /**
     * Get the status badge class for display
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'planned' => 'bg-secondary',
            'open' => 'bg-success',
            'confirmed' => 'bg-primary',
            'cancelled' => 'bg-danger',
            'completed' => 'bg-info',
            default => 'bg-light'
        };
    }

    /**
     * Update the current registrations count
     */
    public function updateRegistrationsCount(): static
    {
        $this->currentRegistrations = $this->registrations->count();
        return $this;
    }

    /**
     * Get alternance type label for display
     */
    public function getAlternanceTypeLabel(): string
    {
        return match ($this->alternanceType) {
            'apprentissage' => 'Contrat d\'apprentissage',
            'professionnalisation' => 'Contrat de professionnalisation',
            default => ''
        };
    }

    /**
     * Get alternance rhythm description
     */
    public function getAlternanceRhythmDescription(): string
    {
        if (!$this->alternanceRhythm) {
            return '';
        }

        // Common rhythm patterns
        $patterns = [
            '1-1' => '1 semaine centre / 1 semaine entreprise',
            '2-2' => '2 semaines centre / 2 semaines entreprise',
            '3-1' => '3 semaines centre / 1 semaine entreprise',
            '1-3' => '1 semaine centre / 3 semaines entreprise',
            '2-3' => '2 semaines centre / 3 semaines entreprise',
            '3-2' => '3 semaines centre / 2 semaines entreprise',
        ];

        return $patterns[$this->alternanceRhythm] ?? $this->alternanceRhythm;
    }

    /**
     * Check if alternance percentages are valid (should sum to 100)
     */
    public function hasValidAlternancePercentages(): bool
    {
        if (!$this->isAlternanceSession()) {
            return true;
        }

        if ($this->centerPercentage === null || $this->companyPercentage === null) {
            return false;
        }

        return ($this->centerPercentage + $this->companyPercentage) === 100;
    }

    /**
     * Get formatted alternance duration
     */
    public function getFormattedAlternanceDuration(): string
    {
        if (!$this->minimumAlternanceDuration) {
            return '';
        }

        $weeks = $this->minimumAlternanceDuration;
        if ($weeks === 1) {
            return '1 semaine minimum';
        }

        if ($weeks < 52) {
            return $weeks . ' semaines minimum';
        }

        $years = intval($weeks / 52);
        $remainingWeeks = $weeks % 52;

        if ($remainingWeeks === 0) {
            return $years === 1 ? '1 an minimum' : $years . ' ans minimum';
        }

        $yearText = $years === 1 ? '1 an' : $years . ' ans';
        $weekText = $remainingWeeks === 1 ? '1 semaine' : $remainingWeeks . ' semaines';

        return $yearText . ' et ' . $weekText . ' minimum';
    }

    /**
     * Get formatted alternance prerequisites as formatted list
     */
    public function getFormattedAlternancePrerequisites(): array
    {
        return $this->alternancePrerequisites ?? [];
    }

    /**
     * Get alternance program (will be populated by service or repository)
     * This method will be implemented when the relationship is established via service layer
     */
    public function getAlternanceProgram(): ?object
    {
        // This will be implemented in a service to avoid circular dependencies
        return null;
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
        return $this->name ?? '';
    }
}
