<?php

declare(strict_types=1);

namespace App\Entity\Document;

use App\Repository\Document\DocumentMetadataRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DocumentMetadata entity - Structured Metadata Framework.
 *
 * Replaces the unstructured JSON metadata blob in LegalDocument with
 * a structured, searchable, and type-safe metadata system.
 * Each metadata field can have validation, data types, and search capabilities.
 */
#[ORM\Entity(repositoryClass: DocumentMetadataRepository::class)]
#[ORM\Table(name: 'document_metadata')]
#[ORM\HasLifecycleCallbacks]
class DocumentMetadata
{
    // Data type constants
    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_FLOAT = 'float';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_DATE = 'date';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_JSON = 'json';

    public const TYPE_FILE = 'file';

    public const TYPE_URL = 'url';

    public const TYPE_LABELS = [
        self::TYPE_STRING => 'Texte court',
        self::TYPE_TEXT => 'Texte long',
        self::TYPE_INTEGER => 'Nombre entier',
        self::TYPE_FLOAT => 'Nombre décimal',
        self::TYPE_BOOLEAN => 'Booléen (Oui/Non)',
        self::TYPE_DATE => 'Date',
        self::TYPE_DATETIME => 'Date et heure',
        self::TYPE_JSON => 'JSON',
        self::TYPE_FILE => 'Fichier',
        self::TYPE_URL => 'URL',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'metadata')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le document est obligatoire.')]
    private ?Document $document = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La clé de métadonnée est obligatoire.')]
    #[Assert\Length(
        min: 1,
        max: 100,
        minMessage: 'La clé doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'La clé ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Regex(
        pattern: '/^[a-z0-9_]+$/',
        message: 'La clé ne peut contenir que des lettres minuscules, chiffres et underscores.',
    )]
    private ?string $metaKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaValue = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type de donnée est obligatoire.')]
    #[Assert\Choice(
        choices: [
            self::TYPE_STRING,
            self::TYPE_TEXT,
            self::TYPE_INTEGER,
            self::TYPE_FLOAT,
            self::TYPE_BOOLEAN,
            self::TYPE_DATE,
            self::TYPE_DATETIME,
            self::TYPE_JSON,
            self::TYPE_FILE,
            self::TYPE_URL,
        ],
        message: 'Type de donnée invalide.',
    )]
    private string $dataType = self::TYPE_STRING;

    #[ORM\Column]
    private bool $isRequired = false;

    #[ORM\Column]
    private bool $isSearchable = true;

    #[ORM\Column]
    private bool $isEditable = true;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $validationRules = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('%s: %s', $this->getEffectiveDisplayName(), $this->metaValue ?: 'N/A');
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

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getMetaKey(): ?string
    {
        return $this->metaKey;
    }

    public function setMetaKey(string $metaKey): static
    {
        $this->metaKey = $metaKey;

        return $this;
    }

    public function getMetaValue(): ?string
    {
        return $this->metaValue;
    }

    public function setMetaValue(?string $metaValue): static
    {
        $this->metaValue = $metaValue;

        return $this;
    }

    public function getDataType(): string
    {
        return $this->dataType;
    }

    public function setDataType(string $dataType): static
    {
        $this->dataType = $dataType;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->isSearchable;
    }

    public function setIsSearchable(bool $isSearchable): static
    {
        $this->isSearchable = $isSearchable;

        return $this;
    }

    public function isEditable(): bool
    {
        return $this->isEditable;
    }

    public function setIsEditable(bool $isEditable): static
    {
        $this->isEditable = $isEditable;

        return $this;
    }

    public function getValidationRules(): ?array
    {
        return $this->validationRules;
    }

    public function setValidationRules(?array $validationRules): static
    {
        $this->validationRules = $validationRules;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

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
     * Business logic methods.
     */

    /**
     * Get type-casted value based on data type.
     */
    public function getTypedValue(): mixed
    {
        if ($this->metaValue === null) {
            return null;
        }

        return match ($this->dataType) {
            self::TYPE_INTEGER => (int) $this->metaValue,
            self::TYPE_FLOAT => (float) $this->metaValue,
            self::TYPE_BOOLEAN => filter_var($this->metaValue, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_DATE => DateTimeImmutable::createFromFormat('Y-m-d', $this->metaValue) ?: null,
            self::TYPE_DATETIME => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->metaValue) ?: null,
            self::TYPE_JSON => json_decode($this->metaValue, true),
            default => $this->metaValue,
        };
    }

    /**
     * Set value with automatic type conversion.
     */
    public function setTypedValue(mixed $value): static
    {
        if ($value === null) {
            $this->metaValue = null;

            return $this;
        }

        $this->metaValue = match ($this->dataType) {
            self::TYPE_INTEGER, self::TYPE_FLOAT => (string) $value,
            self::TYPE_BOOLEAN => $value ? '1' : '0',
            self::TYPE_DATE => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value,
            self::TYPE_DATETIME => $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value,
            self::TYPE_JSON => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };

        return $this;
    }

    /**
     * Get data type label.
     */
    public function getDataTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->dataType] ?? $this->dataType;
    }

    /**
     * Get effective display name.
     */
    public function getEffectiveDisplayName(): string
    {
        return $this->displayName ?: $this->metaKey;
    }

    /**
     * Validate value against validation rules.
     */
    public function validateValue(): array
    {
        $errors = [];

        if ($this->isRequired && ($this->metaValue === null || $this->metaValue === '')) {
            $errors[] = sprintf('La métadonnée "%s" est obligatoire.', $this->getEffectiveDisplayName());
        }

        if ($this->validationRules && $this->metaValue !== null) {
            foreach ($this->validationRules as $rule => $constraint) {
                $errors = array_merge($errors, $this->validateRule($rule, $constraint));
            }
        }

        return $errors;
    }

    /**
     * Validate individual rule.
     */
    private function validateRule(string $rule, mixed $constraint): array
    {
        $errors = [];
        $value = $this->getTypedValue();

        switch ($rule) {
            case 'min_length':
                if (is_string($value) && strlen($value) < $constraint) {
                    $errors[] = sprintf('"%s" doit contenir au moins %d caractères.', $this->getEffectiveDisplayName(), $constraint);
                }
                break;

            case 'max_length':
                if (is_string($value) && strlen($value) > $constraint) {
                    $errors[] = sprintf('"%s" ne peut pas dépasser %d caractères.', $this->getEffectiveDisplayName(), $constraint);
                }
                break;

            case 'min_value':
                if (is_numeric($value) && $value < $constraint) {
                    $errors[] = sprintf('"%s" doit être supérieur ou égal à %s.', $this->getEffectiveDisplayName(), $constraint);
                }
                break;

            case 'max_value':
                if (is_numeric($value) && $value > $constraint) {
                    $errors[] = sprintf('"%s" doit être inférieur ou égal à %s.', $this->getEffectiveDisplayName(), $constraint);
                }
                break;

            case 'pattern':
                if (is_string($value) && !preg_match($constraint, $value)) {
                    $errors[] = sprintf('"%s" ne respecte pas le format requis.', $this->getEffectiveDisplayName());
                }
                break;

            case 'allowed_values':
                if (!in_array($value, $constraint, true)) {
                    $errors[] = sprintf('"%s" doit être une des valeurs autorisées.', $this->getEffectiveDisplayName());
                }
                break;
        }

        return $errors;
    }
}
