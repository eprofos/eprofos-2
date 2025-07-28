<?php

namespace App\Entity\Analysis;

use App\Entity\CRM\Prospect;
use App\Entity\Training\Formation;
use App\Entity\User\Admin;
use App\Repository\NeedsAnalysisRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Needs Analysis Request Entity
 * 
 * Represents a request for needs analysis sent to companies or individuals
 * to comply with Qualiopi 2.4 criteria. Contains secure token-based access
 * and tracks the complete lifecycle of the analysis request.
 */
#[ORM\Entity(repositoryClass: NeedsAnalysisRequestRepository::class)]
#[ORM\Table(name: 'needs_analysis_requests')]
#[ORM\HasLifecycleCallbacks]
class NeedsAnalysisRequest
{
    public const TYPE_COMPANY = 'company';
    public const TYPE_INDIVIDUAL = 'individual';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le type de demande est obligatoire.', groups: ['Default', 'admin_form'])]
    #[Assert\Choice(
        choices: [self::TYPE_COMPANY, self::TYPE_INDIVIDUAL],
        message: 'Type de demande invalide.',
        groups: ['Default', 'admin_form']
    )]
    private ?string $type = null;

    #[ORM\Column(length: 36, unique: true)]
    #[Assert\NotBlank(message: 'Le token est obligatoire.', groups: ['Default', 'token_validation'])]
    #[Assert\Uuid(message: 'Le token doit être un UUID valide.', groups: ['Default', 'token_validation'])]
    private ?string $token = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email du destinataire est obligatoire.', groups: ['Default', 'admin_form'])]
    #[Assert\Email(message: 'Veuillez saisir une adresse email valide.', groups: ['Default', 'admin_form'])]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.',
        groups: ['Default', 'admin_form']
    )]
    private ?string $recipientEmail = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du destinataire est obligatoire.', groups: ['Default', 'admin_form'])]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
        groups: ['Default', 'admin_form']
    )]
    private ?string $recipientName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères.',
        groups: ['Default', 'admin_form']
    )]
    private ?string $companyName = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.', groups: ['Default', 'admin_form'])]
    #[Assert\Choice(
        choices: [
            self::STATUS_PENDING,
            self::STATUS_SENT,
            self::STATUS_COMPLETED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED
        ],
        message: 'Statut invalide.',
        groups: ['Default', 'admin_form']
    )]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastReminderSentAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Admin $createdByAdmin = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(targetEntity: Prospect::class, inversedBy: 'needsAnalysisRequests')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Prospect $prospect = null;

    #[ORM\OneToOne(targetEntity: CompanyNeedsAnalysis::class, mappedBy: 'needsAnalysisRequest', cascade: ['persist', 'remove'])]
    private ?CompanyNeedsAnalysis $companyAnalysis = null;

    #[ORM\OneToOne(targetEntity: IndividualNeedsAnalysis::class, mappedBy: 'needsAnalysisRequest', cascade: ['persist', 'remove'])]
    private ?IndividualNeedsAnalysis $individualAnalysis = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->setDefaultExpiration();
    }

    /**
     * Set default expiration to 30 days from creation
     */
    private function setDefaultExpiration(): void
    {
        $this->expiresAt = $this->createdAt->modify('+30 days');
    }

    /**
     * Lifecycle callback executed before persisting the entity
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        if ($this->expiresAt === null) {
            $this->setDefaultExpiration();
        }
    }

    /**
     * Check if the request is expired
     */
    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * Check if the request is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the request is sent
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if the request is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the request is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Mark the request as sent
     */
    public function markAsSent(): void
    {
        $this->status = self::STATUS_SENT;
        $this->sentAt = new \DateTimeImmutable();
    }

    /**
     * Mark the request as completed
     */
    public function markAsCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * Mark the request as expired
     */
    public function markAsExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
    }

    /**
     * Mark the request as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->status = self::STATUS_CANCELLED;
    }

    /**
     * Get the type label for display
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_COMPANY => 'Entreprise',
            self::TYPE_INDIVIDUAL => 'Particulier',
            default => 'Inconnu'
        };
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_SENT => 'Envoyé',
            self::STATUS_COMPLETED => 'Complété',
            self::STATUS_EXPIRED => 'Expiré',
            self::STATUS_CANCELLED => 'Annulé',
            default => 'Inconnu'
        };
    }

    /**
     * Get the status badge class for display
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-warning',
            self::STATUS_SENT => 'bg-info',
            self::STATUS_COMPLETED => 'bg-success',
            self::STATUS_EXPIRED => 'bg-danger',
            self::STATUS_CANCELLED => 'bg-secondary',
            default => 'bg-light'
        };
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpiration(): int
    {
        $now = new \DateTimeImmutable();
        if ($this->expiresAt <= $now) {
            return 0;
        }
        
        return $now->diff($this->expiresAt)->days;
    }

    /**
     * Get the public URL for this analysis request
     */
    public function getPublicUrl(): string
    {
        $baseUrl = '/needs-analysis/form/' . $this->token;
        return $baseUrl;
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

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        $this->recipientEmail = $recipientEmail;
        return $this;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): static
    {
        $this->recipientName = $recipientName;
        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
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

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
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

    /**
     * Get the date when the last reminder was sent.
     */
    public function getLastReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->lastReminderSentAt;
    }

    /**
     * Set the date when the last reminder was sent.
     */
    public function setLastReminderSentAt(?\DateTimeImmutable $lastReminderSentAt): static
    {
        $this->lastReminderSentAt = $lastReminderSentAt;
        return $this;
    }

    public function getCreatedByAdmin(): ?Admin
    {
        return $this->createdByAdmin;
    }

    public function setCreatedByAdmin(?Admin $createdByAdmin): static
    {
        $this->createdByAdmin = $createdByAdmin;
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

    public function getProspect(): ?Prospect
    {
        return $this->prospect;
    }

    public function setProspect(?Prospect $prospect): static
    {
        $this->prospect = $prospect;
        return $this;
    }

    public function getCompanyAnalysis(): ?CompanyNeedsAnalysis
    {
        return $this->companyAnalysis;
    }

    public function setCompanyAnalysis(?CompanyNeedsAnalysis $companyAnalysis): static
    {
        $this->companyAnalysis = $companyAnalysis;
        
        // Set the owning side of the relation if necessary
        if ($companyAnalysis !== null && $companyAnalysis->getNeedsAnalysisRequest() !== $this) {
            $companyAnalysis->setNeedsAnalysisRequest($this);
        }
        
        return $this;
    }

    public function getIndividualAnalysis(): ?IndividualNeedsAnalysis
    {
        return $this->individualAnalysis;
    }

    public function setIndividualAnalysis(?IndividualNeedsAnalysis $individualAnalysis): static
    {
        $this->individualAnalysis = $individualAnalysis;
        
        // Set the owning side of the relation if necessary
        if ($individualAnalysis !== null && $individualAnalysis->getNeedsAnalysisRequest() !== $this) {
            $individualAnalysis->setNeedsAnalysisRequest($this);
        }
        
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->getTypeLabel(),
            $this->recipientName ?? 'Sans nom',
            $this->getStatusLabel()
        );
    }
}