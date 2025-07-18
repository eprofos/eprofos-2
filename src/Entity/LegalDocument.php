<?php

namespace App\Entity;

use App\Repository\LegalDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * LegalDocument entity for managing all legal documents required by Qualiopi
 * 
 * Unified entity for internal regulations, student handbooks, training terms,
 * accessibility policies and other legal documents.
 */
#[ORM\Entity(repositoryClass: LegalDocumentRepository::class)]
#[ORM\Table(name: 'legal_documents')]
#[ORM\HasLifecycleCallbacks]
class LegalDocument
{
    public const TYPE_INTERNAL_REGULATION = 'internal_regulation';
    public const TYPE_STUDENT_HANDBOOK = 'student_handbook';
    public const TYPE_TRAINING_TERMS = 'training_terms';
    public const TYPE_ACCESSIBILITY_POLICY = 'accessibility_policy';
    public const TYPE_ACCESSIBILITY_PROCEDURES = 'accessibility_procedures';
    public const TYPE_ACCESSIBILITY_FAQ = 'accessibility_faq';

    public const TYPES = [
        self::TYPE_INTERNAL_REGULATION => 'Règlement intérieur',
        self::TYPE_STUDENT_HANDBOOK => 'Livret d\'accueil stagiaire',
        self::TYPE_TRAINING_TERMS => 'Conditions de formation',
        self::TYPE_ACCESSIBILITY_POLICY => 'Politique d\'accessibilité',
        self::TYPE_ACCESSIBILITY_PROCEDURES => 'Procédures d\'accessibilité',
        self::TYPE_ACCESSIBILITY_FAQ => 'FAQ Accessibilité',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_PUBLISHED => 'Publié',
        self::STATUS_ARCHIVED => 'Archivé',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le type de document est obligatoire.')]
    #[Assert\Choice(
        callback: 'getValidTypes',
        message: 'Type de document invalide.'
    )]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    private ?string $content = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'La version est obligatoire.')]
    private ?string $version = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        callback: 'getValidStatuses',
        message: 'Statut invalide.'
    )]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->version = '1.0';
    }

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

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;
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

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
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

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    /**
     * Get the type label for display
     */
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Check if document is published
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED 
            && $this->isActive 
            && $this->publishedAt !== null 
            && $this->publishedAt <= new \DateTime();
    }

    /**
     * Check if document is draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if document is archived
     */
    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Publish the document
     */
    public function publish(): static
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->publishedAt = new \DateTime();
        $this->isActive = true;
        return $this;
    }

    /**
     * Archive the document
     */
    public function archive(): static
    {
        $this->status = self::STATUS_ARCHIVED;
        return $this;
    }

    /**
     * Unpublish the document (set to draft)
     */
    public function unpublish(): static
    {
        $this->status = self::STATUS_DRAFT;
        $this->publishedAt = null;
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
        return $this->title ?: 'Document #' . $this->id;
    }

    /**
     * Get all valid document types
     */
    public static function getValidTypes(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Get all valid document statuses
     */
    public static function getValidStatuses(): array
    {
        return array_keys(self::STATUSES);
    }
}
