<?php

namespace App\Entity;

use App\Entity\User\Admin;
use App\Repository\ProspectNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProspectNote Entity
 * 
 * Represents a note or interaction log for a prospect in the EPROFOS CRM system.
 * Tracks communications, meetings, and other interactions with prospects.
 */
#[ORM\Entity(repositoryClass: ProspectNoteRepository::class)]
#[ORM\Table(name: 'prospect_notes')]
#[ORM\HasLifecycleCallbacks]
class ProspectNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 200,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 5000,
        minMessage: 'Le contenu doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le contenu ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $content = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    #[Assert\Choice(
        choices: ['call', 'email', 'meeting', 'demo', 'proposal', 'follow_up', 'general', 'task', 'reminder'],
        message: 'Type de note invalide.'
    )]
    private string $type = 'general';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['pending', 'completed', 'cancelled'],
        message: 'Statut invalide.'
    )]
    private string $status = 'completed';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\ManyToOne(targetEntity: Prospect::class, inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Prospect $prospect = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Admin $createdBy = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isImportant = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isPrivate = false;

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
        
        if ($this->status === 'completed' && !$this->completedAt) {
            $this->completedAt = new \DateTime();
        }
    }

    /**
     * Lifecycle callback executed before updating the entity
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
        
        if ($this->status === 'completed' && !$this->completedAt) {
            $this->completedAt = new \DateTime();
        } elseif ($this->status !== 'completed') {
            $this->completedAt = null;
        }
    }

    /**
     * Get the type label for display
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'call' => 'Appel téléphonique',
            'email' => 'Email',
            'meeting' => 'Réunion',
            'demo' => 'Démonstration',
            'proposal' => 'Proposition',
            'follow_up' => 'Suivi',
            'general' => 'Note générale',
            'task' => 'Tâche',
            'reminder' => 'Rappel',
            default => 'Autre'
        };
    }

    /**
     * Get the type icon for display
     */
    public function getTypeIcon(): string
    {
        return match ($this->type) {
            'call' => 'phone',
            'email' => 'mail',
            'meeting' => 'users',
            'demo' => 'monitor',
            'proposal' => 'file-text',
            'follow_up' => 'refresh-cw',
            'general' => 'file-text',
            'task' => 'check-square',
            'reminder' => 'bell',
            default => 'file'
        };
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'En attente',
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
            'completed' => 'bg-success',
            'cancelled' => 'bg-danger',
            default => 'bg-secondary'
        };
    }

    /**
     * Get the type badge class for display
     */
    public function getTypeBadgeClass(): string
    {
        return match ($this->type) {
            'call' => 'var(--tblr-blue)',
            'email' => 'var(--tblr-purple)',
            'meeting' => 'var(--tblr-green)',
            'demo' => 'var(--tblr-orange)',
            'proposal' => 'var(--tblr-red)',
            'follow_up' => 'var(--tblr-cyan)',
            'general' => 'var(--tblr-gray)',
            'task' => 'var(--tblr-yellow)',
            'reminder' => 'var(--tblr-pink)',
            default => 'var(--tblr-secondary)'
        };
    }

    /**
     * Check if the note is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the note is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the note is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the note is overdue (for pending tasks/reminders)
     */
    public function isOverdue(): bool
    {
        if (!$this->isPending() || !$this->scheduledAt) {
            return false;
        }

        return $this->scheduledAt < new \DateTime();
    }

    /**
     * Mark the note as completed
     */
    public function markAsCompleted(): static
    {
        $this->status = 'completed';
        $this->completedAt = new \DateTime();
        return $this;
    }

    /**
     * Mark the note as cancelled
     */
    public function markAsCancelled(): static
    {
        $this->status = 'cancelled';
        $this->completedAt = null;
        return $this;
    }

    /**
     * Get a short excerpt of the content
     */
    public function getExcerpt(int $length = 100): string
    {
        if (strlen($this->content) <= $length) {
            return $this->content;
        }

        return substr($this->content, 0, $length) . '...';
    }

    /**
     * Get formatted creation date
     */
    public function getFormattedCreatedAt(): string
    {
        if (!$this->createdAt) {
            return '';
        }

        return $this->createdAt->format('d/m/Y à H:i');
    }

    /**
     * Get formatted scheduled date
     */
    public function getFormattedScheduledAt(): ?string
    {
        if (!$this->scheduledAt) {
            return null;
        }

        return $this->scheduledAt->format('d/m/Y à H:i');
    }

    // Getters and Setters

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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getScheduledAt(): ?\DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeInterface $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getProspect(): ?Prospect
    {
        return $this->prospect;
    }

    public function setProspect(?Prospect $prospect): static
    {
        $this->prospect = $prospect;
        return $this;
    }

    public function getCreatedBy(): ?Admin
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Admin $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function isImportant(): bool
    {
        return $this->isImportant;
    }

    public function setIsImportant(bool $isImportant): static
    {
        $this->isImportant = $isImportant;
        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): static
    {
        $this->isPrivate = $isPrivate;
        return $this;
    }
}
