<?php

namespace App\Entity\Document;

use App\Entity\User\User;
use App\Repository\Document\DocumentVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DocumentVersion entity - Version History Tracking
 * 
 * This is critical for Qualiopi compliance as the current system has no change history.
 * Provides full audit trail of document changes with changelogs, which is required
 * for quality standards and regulatory compliance.
 */
#[ORM\Entity(repositoryClass: DocumentVersionRepository::class)]
#[ORM\Table(name: 'document_versions')]
#[ORM\HasLifecycleCallbacks]
class DocumentVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le document est obligatoire.')]
    private ?Document $document = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'La version est obligatoire.')]
    #[Assert\Length(
        min: 1,
        max: 50,
        minMessage: 'La version doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'La version ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $version = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $changeLog = null;

    #[ORM\Column]
    private bool $isCurrent = false;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $checksum = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $additionalData = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;
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

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getChangeLog(): ?string
    {
        return $this->changeLog;
    }

    public function setChangeLog(?string $changeLog): static
    {
        $this->changeLog = $changeLog;
        return $this;
    }

    public function isCurrent(): bool
    {
        return $this->isCurrent;
    }

    public function setIsCurrent(bool $isCurrent): static
    {
        $this->isCurrent = $isCurrent;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): static
    {
        $this->checksum = $checksum;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Business logic methods
     */

    /**
     * Get human-readable file size
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->fileSize) {
            return 'N/A';
        }

        $bytes = $this->fileSize;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get created by name for display
     */
    public function getCreatedByName(): string
    {
        return $this->createdBy?->getFullName() ?? 'Système';
    }

    /**
     * Calculate content length
     */
    public function getContentLength(): int
    {
        return $this->content ? strlen($this->content) : 0;
    }

    /**
     * Check if version has changes logged
     */
    public function hasChangeLog(): bool
    {
        return !empty($this->changeLog);
    }

    /**
     * Get short change log (first 100 characters)
     */
    public function getShortChangeLog(): string
    {
        if (!$this->changeLog) {
            return '';
        }

        return strlen($this->changeLog) > 100 
            ? substr($this->changeLog, 0, 97) . '...'
            : $this->changeLog;
    }

    /**
     * Generate content checksum
     */
    public function generateChecksum(): self
    {
        if ($this->content) {
            $this->checksum = md5($this->content);
        }
        return $this;
    }

    /**
     * Verify content integrity
     */
    public function verifyIntegrity(): bool
    {
        if (!$this->checksum || !$this->content) {
            return false;
        }

        return $this->checksum === md5($this->content);
    }

    public function __toString(): string
    {
        return sprintf('%s v%s', $this->title ?: 'Document', $this->version ?: '?');
    }
}
