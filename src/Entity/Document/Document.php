<?php

namespace App\Entity\Document;

use App\Entity\User\Admin;
use App\Repository\Document\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Document entity - Main entity that replaces LegalDocument
 * 
 * This flexible, configurable, and extensible entity can handle any document type,
 * unlike the rigid LegalDocument that was limited to hard-coded legal document types.
 * 
 * Key improvements over LegalDocument:
 * - References DocumentType instead of hard-coded constants
 * - Hierarchical organization via DocumentCategory
 * - Proper collections with cascade operations
 * - Lifecycle callbacks for automatic timestamp updates
 * - Clean API for common business operations
 * - Extensible status system configurable per document type
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
#[ORM\HasLifecycleCallbacks]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(length: 500, unique: true)]
    #[Assert\NotBlank(message: 'Le slug est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 500,
        minMessage: 'Le slug doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le slug ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-z0-9\-\/]+$/',
        message: 'Le slug ne peut contenir que des lettres minuscules, chiffres, tirets et slashes.'
    )]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: DocumentType::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le type de document est obligatoire.')]
    private ?DocumentType $documentType = null;

    #[ORM\ManyToOne(targetEntity: DocumentCategory::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true)]
    private ?DocumentCategory $category = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        callback: 'getValidStatuses',
        message: 'Statut invalide.'
    )]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isPublic = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $downloadCount = 0;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $createdBy = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $updatedBy = null;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: DocumentVersion::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $versions;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: DocumentMetadata::class, cascade: ['persist', 'remove'])]
    private Collection $metadata;

    // Status constants - configurable per document type
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEW = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_REVIEW => 'En révision',
        self::STATUS_APPROVED => 'Approuvé',
        self::STATUS_PUBLISHED => 'Publié',
        self::STATUS_ARCHIVED => 'Archivé',
        self::STATUS_EXPIRED => 'Expiré',
    ];

    public function __construct()
    {
        $this->versions = new ArrayCollection();
        $this->metadata = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->version = '1.0';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
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

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getDocumentType(): ?DocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(?DocumentType $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getCategory(): ?DocumentCategory
    {
        return $this->category;
    }

    public function setCategory(?DocumentCategory $category): static
    {
        $this->category = $category;
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

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): static
    {
        $this->tags = $tags;
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

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
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

    public function getUpdatedBy(): ?Admin
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?Admin $updatedBy): static
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    /**
     * @return Collection<int, DocumentVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(DocumentVersion $version): static
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setDocument($this);
        }

        return $this;
    }

    public function removeVersion(DocumentVersion $version): static
    {
        if ($this->versions->removeElement($version)) {
            // set the owning side to null (unless already changed)
            if ($version->getDocument() === $this) {
                $version->setDocument(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DocumentMetadata>
     */
    public function getMetadata(): Collection
    {
        return $this->metadata;
    }

    public function addMetadata(DocumentMetadata $metadata): static
    {
        if (!$this->metadata->contains($metadata)) {
            $this->metadata->add($metadata);
            $metadata->setDocument($this);
        }

        return $this;
    }

    public function removeMetadata(DocumentMetadata $metadata): static
    {
        if ($this->metadata->removeElement($metadata)) {
            // set the owning side to null (unless already changed)
            if ($metadata->getDocument() === $this) {
                $metadata->setDocument(null);
            }
        }

        return $this;
    }

    /**
     * Business logic methods - Clean API for common operations
     */

    /**
     * Publish the document
     */
    public function publish(): self
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->publishedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Archive the document
     */
    public function archive(): self
    {
        $this->status = self::STATUS_ARCHIVED;
        return $this;
    }

    /**
     * Unpublish the document (set to draft)
     */
    public function unpublish(): self
    {
        $this->status = self::STATUS_DRAFT;
        $this->publishedAt = null;
        return $this;
    }

    /**
     * Check if document is published
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && 
               $this->publishedAt !== null &&
               $this->publishedAt <= new \DateTimeImmutable();
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
     * Check if document is expired
     */
    public function isExpired(): bool
    {
        return $this->expiresAt !== null && 
               $this->expiresAt <= new \DateTimeImmutable();
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get the type label for display
     */
    public function getTypeLabel(): string
    {
        return $this->documentType?->getName() ?? 'Type inconnu';
    }

    /**
     * Get the category label for display
     */
    public function getCategoryLabel(): string
    {
        return $this->category?->getName() ?? 'Sans catégorie';
    }

    /**
     * Get metadata value by key
     */
    public function getMetadataValue(string $key): ?string
    {
        foreach ($this->metadata as $meta) {
            if ($meta->getMetaKey() === $key) {
                return $meta->getMetaValue();
            }
        }
        return null;
    }

    /**
     * Set metadata value by key
     */
    public function setMetadataValue(string $key, ?string $value, string $dataType = 'string'): self
    {
        // Find existing metadata
        foreach ($this->metadata as $meta) {
            if ($meta->getMetaKey() === $key) {
                $meta->setMetaValue($value);
                $meta->setDataType($dataType);
                return $this;
            }
        }

        // Create new metadata if not found
        $meta = new DocumentMetadata();
        $meta->setMetaKey($key)
             ->setMetaValue($value)
             ->setDataType($dataType)
             ->setDocument($this);
        
        $this->addMetadata($meta);
        return $this;
    }

    /**
     * Get current version
     */
    public function getCurrentVersion(): ?DocumentVersion
    {
        foreach ($this->versions as $version) {
            if ($version->isCurrent()) {
                return $version;
            }
        }
        return null;
    }

    /**
     * Create a new version
     */
    public function createVersion(string $version, ?string $changeLog = null, ?Admin $createdBy = null): DocumentVersion
    {
        // Mark all existing versions as not current
        foreach ($this->versions as $existingVersion) {
            $existingVersion->setIsCurrent(false);
        }

        $newVersion = new DocumentVersion();
        $newVersion->setDocument($this)
                  ->setVersion($version)
                  ->setTitle($this->title)
                  ->setContent($this->content)
                  ->setChangeLog($changeLog)
                  ->setIsCurrent(true)
                  ->setCreatedBy($createdBy);

        $this->addVersion($newVersion);
        $this->setVersion($version);

        return $newVersion;
    }

    /**
     * Check if document has specific tag
     */
    public function hasTag(string $tag): bool
    {
        return $this->tags && in_array($tag, $this->tags, true);
    }

    /**
     * Add tag
     */
    public function addTag(string $tag): self
    {
        if (!$this->hasTag($tag)) {
            $tags = $this->tags ?? [];
            $tags[] = $tag;
            $this->tags = $tags;
        }
        return $this;
    }

    /**
     * Remove tag
     */
    public function removeTag(string $tag): self
    {
        if ($this->tags) {
            $this->tags = array_values(array_filter($this->tags, fn($t) => $t !== $tag));
        }
        return $this;
    }

    public function getDownloadCount(): int
    {
        return $this->downloadCount;
    }

    public function setDownloadCount(int $downloadCount): static
    {
        $this->downloadCount = $downloadCount;
        return $this;
    }

    public function incrementDownloadCount(): static
    {
        $this->downloadCount++;
        return $this;
    }

    /**
     * Get all valid statuses (for validation)
     */
    public static function getValidStatuses(): array
    {
        return array_keys(self::STATUSES);
    }

    public function __toString(): string
    {
        return $this->title ?: 'Document #' . $this->id;
    }
}
