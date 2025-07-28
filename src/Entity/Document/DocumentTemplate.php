<?php

declare(strict_types=1);

namespace App\Entity\Document;

use App\Entity\User\Admin;
use App\Repository\Document\DocumentTemplateRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DocumentTemplate entity - Reusable Document Templates.
 *
 * Provides a template system for creating consistent documents of specific types.
 * Templates can include predefined content, metadata structure, and configuration
 * to ensure consistency and speed up document creation.
 */
#[ORM\Entity(repositoryClass: DocumentTemplateRepository::class)]
#[ORM\Table(name: 'document_templates')]
#[ORM\HasLifecycleCallbacks]
class DocumentTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $name = null;

    #[ORM\Column(length: 500, unique: true)]
    #[Assert\NotBlank(message: 'Le slug est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 500,
        minMessage: 'Le slug doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le slug ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Regex(
        pattern: '/^[a-z0-9\-\/]+$/',
        message: 'Le slug ne peut contenir que des lettres minuscules, chiffres, tirets et slashes.',
    )]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: DocumentType::class, inversedBy: 'templates')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le type de document est obligatoire.')]
    private ?DocumentType $documentType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $templateContent = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $defaultMetadata = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $placeholders = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $configuration = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $color = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private int $usageCount = 0;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $createdBy = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $updatedBy = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name ?: 'Template #' . $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getDocumentType(): ?DocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(?DocumentType $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }

    public function getTemplateContent(): ?string
    {
        return $this->templateContent;
    }

    public function setTemplateContent(?string $templateContent): static
    {
        $this->templateContent = $templateContent;

        return $this;
    }

    public function getDefaultMetadata(): ?array
    {
        return $this->defaultMetadata;
    }

    public function setDefaultMetadata(?array $defaultMetadata): static
    {
        $this->defaultMetadata = $defaultMetadata;

        return $this;
    }

    public function getPlaceholders(): ?array
    {
        return $this->placeholders;
    }

    public function setPlaceholders(?array $placeholders): static
    {
        $this->placeholders = $placeholders;

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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

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

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): static
    {
        $this->usageCount = $usageCount;

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
     * Business logic methods.
     */

    /**
     * Increment usage count.
     */
    public function incrementUsage(): self
    {
        $this->usageCount++;

        return $this;
    }

    /**
     * Render template content with placeholders.
     */
    public function renderContent(array $variables = []): string
    {
        if (!$this->templateContent) {
            return '';
        }

        $content = $this->templateContent;

        // Replace placeholders
        if ($this->placeholders) {
            foreach ($this->placeholders as $placeholder => $config) {
                $value = $variables[$placeholder] ?? $config['default'] ?? '';
                $content = str_replace('{{' . $placeholder . '}}', $value, $content);
            }
        }

        // Replace standard variables
        $standardVariables = [
            'date' => date('d/m/Y'),
            'datetime' => date('d/m/Y H:i'),
            'year' => date('Y'),
            'month' => date('m'),
            'day' => date('d'),
        ];

        foreach ($standardVariables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Get placeholder definitions.
     */
    public function getPlaceholderDefinitions(): array
    {
        return $this->placeholders ?? [];
    }

    /**
     * Create document from this template.
     */
    public function createDocument(array $variables = [], ?Admin $createdBy = null): Document
    {
        $document = new Document();
        $document->setDocumentType($this->documentType)
            ->setContent($this->renderContent($variables))
            ->setCreatedBy($createdBy)
        ;

        // Apply default metadata
        if ($this->defaultMetadata) {
            foreach ($this->defaultMetadata as $key => $value) {
                $document->setMetadataValue($key, $value);
            }
        }

        // Apply configuration
        if ($this->configuration) {
            if (isset($this->configuration['default_status'])) {
                $document->setStatus($this->configuration['default_status']);
            }
            if (isset($this->configuration['default_public'])) {
                $document->setIsPublic($this->configuration['default_public']);
            }
            if (isset($this->configuration['default_tags'])) {
                $document->setTags($this->configuration['default_tags']);
            }
        }

        $this->incrementUsage();

        return $document;
    }

    /**
     * Get type label for display.
     */
    public function getTypeLabel(): string
    {
        return $this->documentType?->getName() ?? 'Type inconnu';
    }

    /**
     * Get created by name for display.
     */
    public function getCreatedByName(): string
    {
        return $this->createdBy?->getFullName() ?? 'Système';
    }

    /**
     * Get updated by name for display.
     */
    public function getUpdatedByName(): string
    {
        return $this->updatedBy?->getFullName() ?? 'Système';
    }

    /**
     * Check if template has specific placeholder.
     */
    public function hasPlaceholder(string $placeholder): bool
    {
        return isset($this->placeholders[$placeholder]);
    }

    /**
     * Add or update placeholder.
     */
    public function setPlaceholder(string $name, array $config): self
    {
        $placeholders = $this->placeholders ?? [];
        $placeholders[$name] = $config;
        $this->placeholders = $placeholders;

        return $this;
    }

    /**
     * Remove placeholder.
     */
    public function removePlaceholder(string $name): self
    {
        if ($this->placeholders && isset($this->placeholders[$name])) {
            unset($this->placeholders[$name]);
        }

        return $this;
    }

    /**
     * Get all placeholder names.
     */
    public function getPlaceholderNames(): array
    {
        return array_keys($this->placeholders ?? []);
    }

    /**
     * Validate template content.
     */
    public function validateContent(): array
    {
        $errors = [];

        if (!$this->templateContent) {
            return $errors;
        }

        // Check for undefined placeholders
        preg_match_all('/\{\{([^}]+)\}\}/', $this->templateContent, $matches);
        $usedPlaceholders = $matches[1];
        $definedPlaceholders = $this->getPlaceholderNames();

        $standardPlaceholders = ['date', 'datetime', 'year', 'month', 'day'];
        $allValidPlaceholders = array_merge($definedPlaceholders, $standardPlaceholders);

        foreach ($usedPlaceholders as $placeholder) {
            if (!in_array($placeholder, $allValidPlaceholders, true)) {
                $errors[] = sprintf('Placeholder non défini: %s', $placeholder);
            }
        }

        return $errors;
    }
}
