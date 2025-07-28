<?php

declare(strict_types=1);

namespace App\Entity\Document;

use App\Entity\User\Admin;
use App\Repository\Document\DocumentUITemplateRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DocumentUITemplate entity - UI Layout Templates for Documents.
 *
 * Manages configurable UI templates for document rendering in PDF/Word format.
 * Provides flexible layout system with CSS, styling options, and component management.
 */
#[ORM\Entity(repositoryClass: DocumentUITemplateRepository::class)]
#[ORM\Table(name: 'document_ui_templates')]
#[ORM\HasLifecycleCallbacks]
class DocumentUITemplate
{
    // Template type constants
    public const TYPE_STANDARD = 'standard';

    public const TYPE_LETTERHEAD = 'letterhead';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_REPORT = 'report';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CONTRACT = 'contract';

    public const TYPES = [
        self::TYPE_STANDARD => 'Standard',
        self::TYPE_LETTERHEAD => 'En-tête',
        self::TYPE_CERTIFICATE => 'Certificat',
        self::TYPE_REPORT => 'Rapport',
        self::TYPE_INVOICE => 'Facture',
        self::TYPE_CONTRACT => 'Contrat',
    ];

    // Component zones constants
    public const ZONE_HEADER = 'header';

    public const ZONE_BODY = 'body';

    public const ZONE_FOOTER = 'footer';

    public const ZONE_SIDEBAR = 'sidebar';

    public const ZONES = [
        self::ZONE_HEADER => 'En-tête',
        self::ZONE_BODY => 'Corps',
        self::ZONE_FOOTER => 'Pied de page',
        self::ZONE_SIDEBAR => 'Barre latérale',
    ];

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

    #[ORM\ManyToOne(targetEntity: DocumentType::class, inversedBy: 'uiTemplates')]
    #[ORM\JoinColumn(nullable: true)]
    private ?DocumentType $documentType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $htmlTemplate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cssStyles = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $layoutConfiguration = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $pageSettings = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $headerFooterConfig = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $componentStyles = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $variables = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: ['portrait', 'landscape'],
        message: 'Orientation invalide.',
    )]
    private string $orientation = 'portrait';

    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: ['A4', 'A3', 'A5', 'Letter', 'Legal'],
        message: 'Format de page invalide.',
    )]
    private string $paperSize = 'A4';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $margins = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1, nullable: true)]
    private ?float $marginTop = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1, nullable: true)]
    private ?float $marginRight = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1, nullable: true)]
    private ?float $marginBottom = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1, nullable: true)]
    private ?float $marginLeft = null;

    #[ORM\Column]
    private bool $showPageNumbers = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customCss = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $color = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column]
    private bool $isGlobal = false;

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

    #[ORM\OneToMany(mappedBy: 'uiTemplate', targetEntity: DocumentUIComponent::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $components;

    public function __construct()
    {
        $this->components = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->margins = [
            'top' => '20mm',
            'bottom' => '20mm',
            'left' => '20mm',
            'right' => '20mm',
        ];
        $this->marginTop = 20.0;
        $this->marginRight = 20.0;
        $this->marginBottom = 20.0;
        $this->marginLeft = 20.0;
        $this->showPageNumbers = false;
        $this->pageSettings = [
            'numbering' => true,
            'numbering_position' => 'bottom-center',
            'show_date' => true,
            'show_watermark' => false,
        ];
    }

    public function __toString(): string
    {
        return $this->name ?: 'UI Template #' . $this->id;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
        $this->syncMargins(); // Keep margins in sync
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->syncMargins(); // Keep margins in sync on creation
    }

    #[ORM\PostLoad]
    public function loadMargins(): void
    {
        $this->loadMarginsFromArray(); // Load individual margins after loading from DB
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

    public function getHtmlTemplate(): ?string
    {
        return $this->htmlTemplate;
    }

    public function setHtmlTemplate(?string $htmlTemplate): static
    {
        $this->htmlTemplate = $htmlTemplate;

        return $this;
    }

    public function getCssStyles(): ?string
    {
        return $this->cssStyles;
    }

    public function setCssStyles(?string $cssStyles): static
    {
        $this->cssStyles = $cssStyles;

        return $this;
    }

    public function getLayoutConfiguration(): ?array
    {
        return $this->layoutConfiguration;
    }

    public function setLayoutConfiguration(?array $layoutConfiguration): static
    {
        $this->layoutConfiguration = $layoutConfiguration;

        return $this;
    }

    public function getPageSettings(): ?array
    {
        return $this->pageSettings;
    }

    public function setPageSettings(?array $pageSettings): static
    {
        $this->pageSettings = $pageSettings;

        return $this;
    }

    public function getHeaderFooterConfig(): ?array
    {
        return $this->headerFooterConfig;
    }

    public function setHeaderFooterConfig(?array $headerFooterConfig): static
    {
        $this->headerFooterConfig = $headerFooterConfig;

        return $this;
    }

    public function getComponentStyles(): ?array
    {
        return $this->componentStyles;
    }

    public function setComponentStyles(?array $componentStyles): static
    {
        $this->componentStyles = $componentStyles;

        return $this;
    }

    public function getVariables(): ?array
    {
        return $this->variables;
    }

    public function setVariables(?array $variables): static
    {
        $this->variables = $variables;

        return $this;
    }

    public function getOrientation(): string
    {
        return $this->orientation;
    }

    public function setOrientation(string $orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function getPaperSize(): string
    {
        return $this->paperSize;
    }

    public function setPaperSize(string $paperSize): static
    {
        $this->paperSize = $paperSize;

        return $this;
    }

    public function getMargins(): ?array
    {
        return $this->margins;
    }

    public function setMargins(?array $margins): static
    {
        $this->margins = $margins;

        return $this;
    }

    public function getMarginTop(): ?float
    {
        return $this->marginTop;
    }

    public function setMarginTop(?float $marginTop): static
    {
        $this->marginTop = $marginTop;

        return $this;
    }

    public function getMarginRight(): ?float
    {
        return $this->marginRight;
    }

    public function setMarginRight(?float $marginRight): static
    {
        $this->marginRight = $marginRight;

        return $this;
    }

    public function getMarginBottom(): ?float
    {
        return $this->marginBottom;
    }

    public function setMarginBottom(?float $marginBottom): static
    {
        $this->marginBottom = $marginBottom;

        return $this;
    }

    public function getMarginLeft(): ?float
    {
        return $this->marginLeft;
    }

    public function setMarginLeft(?float $marginLeft): static
    {
        $this->marginLeft = $marginLeft;

        return $this;
    }

    public function getShowPageNumbers(): bool
    {
        return $this->showPageNumbers;
    }

    public function setShowPageNumbers(bool $showPageNumbers): static
    {
        $this->showPageNumbers = $showPageNumbers;

        return $this;
    }

    public function getCustomCss(): ?string
    {
        return $this->customCss;
    }

    public function setCustomCss(?string $customCss): static
    {
        $this->customCss = $customCss;

        return $this;
    }

    /**
     * Synchronize individual margin properties with the margins array.
     */
    public function syncMargins(): void
    {
        // Update the margins array from individual properties
        $this->margins = [
            'top' => $this->marginTop ? $this->marginTop . 'mm' : '20mm',
            'right' => $this->marginRight ? $this->marginRight . 'mm' : '20mm',
            'bottom' => $this->marginBottom ? $this->marginBottom . 'mm' : '20mm',
            'left' => $this->marginLeft ? $this->marginLeft . 'mm' : '20mm',
        ];
    }

    /**
     * Load individual margin properties from the margins array.
     */
    public function loadMarginsFromArray(): void
    {
        if ($this->margins) {
            $this->marginTop = isset($this->margins['top']) ? (float) str_replace('mm', '', $this->margins['top']) : 20.0;
            $this->marginRight = isset($this->margins['right']) ? (float) str_replace('mm', '', $this->margins['right']) : 20.0;
            $this->marginBottom = isset($this->margins['bottom']) ? (float) str_replace('mm', '', $this->margins['bottom']) : 20.0;
            $this->marginLeft = isset($this->margins['left']) ? (float) str_replace('mm', '', $this->margins['left']) : 20.0;
        }
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

    public function isGlobal(): bool
    {
        return $this->isGlobal;
    }

    public function setIsGlobal(bool $isGlobal): static
    {
        $this->isGlobal = $isGlobal;

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
     * @return Collection<int, DocumentUIComponent>
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    public function addComponent(DocumentUIComponent $component): static
    {
        if (!$this->components->contains($component)) {
            $this->components->add($component);
            $component->setUiTemplate($this);
        }

        return $this;
    }

    public function removeComponent(DocumentUIComponent $component): static
    {
        if ($this->components->removeElement($component)) {
            if ($component->getUiTemplate() === $this) {
                $component->setUiTemplate(null);
            }
        }

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
     * Get components by zone.
     */
    public function getComponentsByZone(string $zone): Collection
    {
        return $this->components->filter(static fn (DocumentUIComponent $component) => $component->getZone() === $zone);
    }

    /**
     * Render complete HTML template with data.
     */
    public function renderHtml(array $data = []): string
    {
        $html = $this->htmlTemplate ?: $this->generateDefaultTemplate();

        // Replace variables from template configuration
        if ($this->variables) {
            foreach ($this->variables as $key => $config) {
                $value = $data[$key] ?? $config['default'] ?? '';
                $html = str_replace('{{' . $key . '}}', $value, $html);
            }
        }

        // Replace standard variables
        $standardVars = [
            'date' => date('d/m/Y'),
            'datetime' => date('d/m/Y H:i'),
            'year' => date('Y'),
            'page_number' => '{{page_number}}', // Handled by PDF generator
            'total_pages' => '{{total_pages}}', // Handled by PDF generator
        ];

        foreach ($standardVars as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }

        // Replace any remaining variables from data array
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $html = str_replace('{{' . $key . '}}', (string) $value, $html);
            }
        }

        // Replace CSS placeholder
        $css = $this->renderCss();
        $html = str_replace('{{css}}', $css, $html);

        // Handle empty content fallback
        $allContent = ($data['header_content'] ?? '') . ($data['content'] ?? '') . ($data['footer_content'] ?? '');
        if (empty(trim($allContent))) {
            $html = str_replace('{{#if_empty_content}}', '', $html);
            $html = str_replace('{{/if_empty_content}}', '', $html);
        } else {
            // Remove the empty content section
            $html = preg_replace('/\{\{#if_empty_content\}\}.*?\{\{\/if_empty_content\}\}/s', '', $html);
        }

        return $html;
    }

    /**
     * Generate complete CSS with all styles.
     */
    public function renderCss(): string
    {
        $css = $this->cssStyles ?: '';

        // Add page settings
        $css .= $this->generatePageCss();

        // Add component styles
        if ($this->componentStyles) {
            foreach ($this->componentStyles as $selector => $styles) {
                $css .= "\n" . $selector . " {\n";
                foreach ($styles as $property => $value) {
                    $css .= "  {$property}: {$value};\n";
                }
                $css .= "}\n";
            }
        }

        return $css;
    }

    /**
     * Get page configuration for PDF generation.
     */
    public function getPageConfig(): array
    {
        return [
            'format' => $this->paperSize,
            'orientation' => $this->orientation,
            'margin' => $this->margins,
            'displayHeaderFooter' => !empty($this->headerFooterConfig),
            'headerTemplate' => $this->headerFooterConfig['header'] ?? '',
            'footerTemplate' => $this->headerFooterConfig['footer'] ?? '',
            'printBackground' => true,
        ];
    }

    /**
     * Set variable configuration.
     */
    public function setVariable(string $name, array $config): self
    {
        $variables = $this->variables ?? [];
        $variables[$name] = $config;
        $this->variables = $variables;

        return $this;
    }

    /**
     * Remove variable.
     */
    public function removeVariable(string $name): self
    {
        if ($this->variables && isset($this->variables[$name])) {
            unset($this->variables[$name]);
        }

        return $this;
    }

    /**
     * Get variable names.
     */
    public function getVariableNames(): array
    {
        return array_keys($this->variables ?? []);
    }

    /**
     * Set component style.
     */
    public function setComponentStyle(string $selector, array $styles): self
    {
        $componentStyles = $this->componentStyles ?? [];
        $componentStyles[$selector] = $styles;
        $this->componentStyles = $componentStyles;

        return $this;
    }

    /**
     * Get type label for display.
     */
    public function getTypeLabel(): string
    {
        return $this->documentType?->getName() ?? 'Global';
    }

    /**
     * Check if template can be used for specific document type.
     */
    public function canBeUsedFor(?DocumentType $documentType): bool
    {
        // Global templates can be used for any type
        if ($this->isGlobal) {
            return true;
        }

        // Type-specific templates
        return $this->documentType === $documentType;
    }

    /**
     * Validate template configuration.
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        // Check HTML template for undefined variables
        if ($this->htmlTemplate) {
            preg_match_all('/\{\{([^}]+)\}\}/', $this->htmlTemplate, $matches);
            $usedVars = $matches[1];
            $definedVars = $this->getVariableNames();

            $standardVars = ['date', 'datetime', 'year', 'page_number', 'total_pages', 'title', 'content', 'css'];
            $allValidVars = array_merge($definedVars, $standardVars);

            foreach ($usedVars as $var) {
                if (!in_array($var, $allValidVars, true)) {
                    $errors[] = sprintf('Variable non définie: %s', $var);
                }
            }
        }

        // Check CSS validity (basic check)
        if ($this->cssStyles) {
            if (substr_count($this->cssStyles, '{') !== substr_count($this->cssStyles, '}')) {
                $errors[] = 'CSS invalide: accolades non équilibrées';
            }
        }

        return $errors;
    }

    /**
     * Clone template with new name.
     */
    public function cloneTemplate(string $newName, string $newSlug): self
    {
        $clone = new self();
        $clone->setName($newName)
            ->setSlug($newSlug)
            ->setDescription($this->description)
            ->setDocumentType($this->documentType)
            ->setHtmlTemplate($this->htmlTemplate)
            ->setCssStyles($this->cssStyles)
            ->setLayoutConfiguration($this->layoutConfiguration)
            ->setPageSettings($this->pageSettings)
            ->setHeaderFooterConfig($this->headerFooterConfig)
            ->setComponentStyles($this->componentStyles)
            ->setVariables($this->variables)
            ->setOrientation($this->orientation)
            ->setPaperSize($this->paperSize)
            ->setMargins($this->margins)
            ->setIcon($this->icon)
            ->setColor($this->color)
            ->setIsActive(false) // Start as inactive
            ->setIsDefault(false)
            ->setIsGlobal($this->isGlobal)
            ->setSortOrder($this->sortOrder + 1)
        ;

        // Clone components
        foreach ($this->components as $component) {
            $clonedComponent = $component->cloneComponent();
            $clone->addComponent($clonedComponent);
        }

        return $clone;
    }

    /**
     * Generate default HTML template structure.
     */
    private function generateDefaultTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{title}}</title>
    <style>{{css}}</style>
</head>
<body>
    <header class="template-header">
        {{header_content}}
    </header>
    <main class="template-body">
        {{content}}
        {{#if_empty_content}}
        <div style="padding: 20px; text-align: center; color: #666; border: 2px dashed #ccc; margin: 20px;">
            <h3>Modèle vide</h3>
            <p>Ce modèle ne contient aucun composant actif.</p>
            <p>Ajoutez des composants pour voir le contenu ici.</p>
        </div>
        {{/if_empty_content}}
    </main>
    <footer class="template-footer">
        {{footer_content}}
    </footer>
</body>
</html>';
    }

    /**
     * Generate CSS for page settings.
     */
    private function generatePageCss(): string
    {
        $css = "\n@page {\n";
        $css .= "  size: {$this->paperSize} {$this->orientation};\n";

        if ($this->margins) {
            foreach ($this->margins as $side => $value) {
                $css .= "  margin-{$side}: {$value};\n";
            }
        }

        $css .= "}\n";

        // Body styles
        $css .= "\nbody {\n";
        $css .= "  font-family: 'Arial', sans-serif;\n";
        $css .= "  line-height: 1.4;\n";
        $css .= "  color: #333;\n";
        $css .= "}\n";

        return $css;
    }
}
