<?php

namespace App\Entity;

use App\Repository\ContactRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contact Request Entity
 * 
 * Represents a contact request submitted through various forms on the website.
 * Supports different types of requests: quote, advice, information, and quick registration.
 */
#[ORM\Entity(repositoryClass: ContactRequestRepository::class)]
#[ORM\Table(name: 'contact_requests')]
#[ORM\HasLifecycleCallbacks]
class ContactRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le type de demande est obligatoire.')]
    #[Assert\Choice(
        choices: ['quote', 'advice', 'information', 'quick_registration'],
        message: 'Type de demande invalide.'
    )]
    private ?string $type = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes.'
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.'
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse email valide.')]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^(?:\+33|0)[1-9](?:[0-9]{8})$/',
        message: 'Veuillez saisir un numéro de téléphone français valide.'
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $company = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(
        max: 200,
        maxMessage: 'Le sujet ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Le message doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $message = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['pending', 'in_progress', 'completed', 'cancelled'],
        message: 'Statut invalide.'
    )]
    private string $status = 'pending';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'contactRequests')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Service $service = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $additionalData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNotes = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Lifecycle callback executed before persisting the entity
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Lifecycle callback executed before updating the entity
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Get the full name of the contact
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Get the type label for display
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'quote' => 'Demande de devis',
            'advice' => 'Demande de conseil',
            'information' => 'Demande d\'information',
            'quick_registration' => 'Inscription rapide',
            default => 'Autre'
        };
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'En attente',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default => 'Inconnu'
        };
    }

    /**
     * Get the status badge class for display
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-warning',
            'in_progress' => 'bg-info',
            'completed' => 'bg-success',
            'cancelled' => 'bg-danger',
            default => 'bg-secondary'
        };
    }

    /**
     * Check if the request is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the request is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if the request is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the request is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Mark the request as processed
     */
    public function markAsProcessed(): void
    {
        $this->processedAt = new \DateTime();
        if ($this->status === 'pending') {
            $this->status = 'in_progress';
        }
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): static
    {
        $this->company = $company;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getProcessedAt(): ?\DateTimeInterface
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeInterface $processedAt): static
    {
        $this->processedAt = $processedAt;
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

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
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

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): static
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }
}