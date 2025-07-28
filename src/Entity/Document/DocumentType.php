<?php

declare(strict_types=1);

namespace App\Entity\Document;

use App\Repository\Document\DocumentTypeRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DocumentType entity - Makes document types configurable instead of hard-coded constants.
 *
 * This entity replaces the hard-coded TYPE_* constants in LegalDocument,
 * allowing business users to create new document types via admin interface
 * without requiring developer intervention.
 */
#[ORM\Entity(repositoryClass: DocumentTypeRepository::class)]
#[ORM\Table(name: 'document_types')]
#[ORM\HasLifecycleCallbacks]
class DocumentType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le code est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le code doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Regex(
        pattern: '/^[a-z0-9_]+$/',
        message: 'Le code ne peut contenir que des lettres minuscules, chiffres et underscores.',
    )]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $color = null;

    #[ORM\Column]
    private bool $requiresApproval = false;

    #[ORM\Column]
    private bool $allowMultiplePublished = true;

    #[ORM\Column]
    private bool $hasExpiration = false;

    #[ORM\Column]
    private bool $generatesPdf = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allowedStatuses = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $requiredMetadata = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $configuration = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'documentType', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\OneToMany(mappedBy: 'documentType', targetEntity: DocumentTemplate::class)]
    private Collection $templates;

    #[ORM\OneToMany(mappedBy: 'documentType', targetEntity: DocumentUITemplate::class)]
    private Collection $uiTemplates;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->templates = new ArrayCollection();
        $this->uiTemplates = new ArrayCollection();
        $this->allowedStatuses = ['draft', 'published', 'archived'];
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name ?: 'Type #' . $this->id;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
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

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function isRequiresApproval(): bool
    {
        return $this->requiresApproval;
    }

    public function setRequiresApproval(bool $requiresApproval): static
    {
        $this->requiresApproval = $requiresApproval;

        return $this;
    }

    public function isAllowMultiplePublished(): bool
    {
        return $this->allowMultiplePublished;
    }

    public function setAllowMultiplePublished(bool $allowMultiplePublished): static
    {
        $this->allowMultiplePublished = $allowMultiplePublished;

        return $this;
    }

    public function isHasExpiration(): bool
    {
        return $this->hasExpiration;
    }

    public function setHasExpiration(bool $hasExpiration): static
    {
        $this->hasExpiration = $hasExpiration;

        return $this;
    }

    public function isGeneratesPdf(): bool
    {
        return $this->generatesPdf;
    }

    public function setGeneratesPdf(bool $generatesPdf): static
    {
        $this->generatesPdf = $generatesPdf;

        return $this;
    }

    public function getAllowedStatuses(): ?array
    {
        return $this->allowedStatuses;
    }

    public function setAllowedStatuses(?array $allowedStatuses): static
    {
        $this->allowedStatuses = $allowedStatuses;

        return $this;
    }

    public function getRequiredMetadata(): ?array
    {
        return $this->requiredMetadata;
    }

    public function setRequiredMetadata(?array $requiredMetadata): static
    {
        $this->requiredMetadata = $requiredMetadata;

        return $this;
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(?array $configuration): static
    {
        $this->configuration = $configuration;

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setDocumentType($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getDocumentType() === $this) {
                $document->setDocumentType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DocumentTemplate>
     */
    public function getTemplates(): Collection
    {
        return $this->templates;
    }

    public function addTemplate(DocumentTemplate $template): static
    {
        if (!$this->templates->contains($template)) {
            $this->templates->add($template);
            $template->setDocumentType($this);
        }

        return $this;
    }

    public function removeTemplate(DocumentTemplate $template): static
    {
        if ($this->templates->removeElement($template)) {
            // set the owning side to null (unless already changed)
            if ($template->getDocumentType() === $this) {
                $template->setDocumentType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DocumentUITemplate>
     */
    public function getUiTemplates(): Collection
    {
        return $this->uiTemplates;
    }

    public function addUiTemplate(DocumentUITemplate $uiTemplate): static
    {
        if (!$this->uiTemplates->contains($uiTemplate)) {
            $this->uiTemplates->add($uiTemplate);
            $uiTemplate->setDocumentType($this);
        }

        return $this;
    }

    public function removeUiTemplate(DocumentUITemplate $uiTemplate): static
    {
        if ($this->uiTemplates->removeElement($uiTemplate)) {
            // set the owning side to null (unless already changed)
            if ($uiTemplate->getDocumentType() === $this) {
                $uiTemplate->setDocumentType(null);
            }
        }

        return $this;
    }
}
