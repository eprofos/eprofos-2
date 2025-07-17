<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
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
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
    #[Assert\GreaterThan(
        'today',
        message: 'La date de début doit être ultérieure à aujourd\'hui.'
    )]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'startDate',
        message: 'La date de fin doit être postérieure à la date de début.'
    )]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\LessThan(
        propertyPath: 'startDate',
        message: 'La date limite d\'inscription doit être antérieure à la date de début.'
    )]
    private ?\DateTimeInterface $registrationDeadline = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    private ?string $location = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $address = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La capacité maximale est obligatoire.')]
    #[Assert\Positive(message: 'La capacité doit être un nombre positif.')]
    private ?int $maxCapacity = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'La capacité minimum ne peut pas être négative.')]
    private int $minCapacity = 0;

    #[ORM\Column]
    private int $currentRegistrations = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    private ?string $price = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['planned', 'open', 'confirmed', 'cancelled', 'completed'],
        message: 'Statut invalide.'
    )]
    private string $status = 'planned';

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $instructor = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $additionalInfo = null;

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

    /**
     * Get the number of available places
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
