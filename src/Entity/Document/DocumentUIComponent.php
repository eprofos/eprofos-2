<?php

namespace App\Entity\Document;

use App\Entity\User;
use App\Repository\Document\DocumentUIComponentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DocumentUIComponent entity - Individual UI Components for Templates
 * 
 * Represents individual components within a UI template (header, footer, body sections, etc.)
 * Each component has its own styling, content, and positioning configuration.
 */
#[ORM\Entity(repositoryClass: DocumentUIComponentRepository::class)]
#[ORM\Table(name: 'document_ui_components')]
#[ORM\HasLifecycleCallbacks]
class DocumentUIComponent
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
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    #[Assert\Choice(
        callback: 'getValidTypes',
        message: 'Type de composant invalide.'
    )]
    private string $type = self::TYPE_TEXT;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'La zone est obligatoire.')]
    #[Assert\Choice(
        callback: 'getValidZones',
        message: 'Zone invalide.'
    )]
    private string $zone = DocumentUITemplate::ZONE_BODY;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $htmlContent = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $styleConfig = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $positionConfig = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $dataBinding = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $conditionalDisplay = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isRequired = false;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cssClass = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $elementId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: DocumentUITemplate::class, inversedBy: 'components')]
    #[ORM\JoinColumn(nullable: false)]
    private ?DocumentUITemplate $uiTemplate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $updatedBy = null;

    // Component type constants
    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_LOGO = 'logo';
    public const TYPE_TABLE = 'table';
    public const TYPE_LIST = 'list';
    public const TYPE_SIGNATURE = 'signature';
    public const TYPE_DATE = 'date';
    public const TYPE_PAGE_NUMBER = 'page_number';
    public const TYPE_BARCODE = 'barcode';
    public const TYPE_QR_CODE = 'qr_code';
    public const TYPE_DIVIDER = 'divider';
    public const TYPE_SPACER = 'spacer';
    public const TYPE_CUSTOM_HTML = 'custom_html';

    public const TYPES = [
        self::TYPE_TEXT => 'Texte',
        self::TYPE_IMAGE => 'Image',
        self::TYPE_LOGO => 'Logo',
        self::TYPE_TABLE => 'Tableau',
        self::TYPE_LIST => 'Liste',
        self::TYPE_SIGNATURE => 'Signature',
        self::TYPE_DATE => 'Date',
        self::TYPE_PAGE_NUMBER => 'Numéro de page',
        self::TYPE_BARCODE => 'Code-barres',
        self::TYPE_QR_CODE => 'QR Code',
        self::TYPE_DIVIDER => 'Séparateur',
        self::TYPE_SPACER => 'Espacement',
        self::TYPE_CUSTOM_HTML => 'HTML personnalisé',
    ];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->styleConfig = [];
        $this->positionConfig = [
            'width' => '100%',
            'height' => 'auto',
            'margin' => '0',
            'padding' => '0'
        ];
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getZone(): string
    {
        return $this->zone;
    }

    public function setZone(string $zone): static
    {
        $this->zone = $zone;
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

    public function getHtmlContent(): ?string
    {
        return $this->htmlContent;
    }

    public function setHtmlContent(?string $htmlContent): static
    {
        $this->htmlContent = $htmlContent;
        return $this;
    }

    public function getStyleConfig(): ?array
    {
        return $this->styleConfig;
    }

    public function setStyleConfig(?array $styleConfig): static
    {
        $this->styleConfig = $styleConfig;
        return $this;
    }

    public function getPositionConfig(): ?array
    {
        return $this->positionConfig;
    }

    public function setPositionConfig(?array $positionConfig): static
    {
        $this->positionConfig = $positionConfig;
        return $this;
    }

    public function getDataBinding(): ?array
    {
        return $this->dataBinding;
    }

    public function setDataBinding(?array $dataBinding): static
    {
        $this->dataBinding = $dataBinding;
        return $this;
    }

    public function getConditionalDisplay(): ?array
    {
        return $this->conditionalDisplay;
    }

    public function setConditionalDisplay(?array $conditionalDisplay): static
    {
        $this->conditionalDisplay = $conditionalDisplay;
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

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;
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

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function setCssClass(?string $cssClass): static
    {
        $this->cssClass = $cssClass;
        return $this;
    }

    public function getElementId(): ?string
    {
        return $this->elementId;
    }

    public function setElementId(?string $elementId): static
    {
        $this->elementId = $elementId;
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

    public function getUiTemplate(): ?DocumentUITemplate
    {
        return $this->uiTemplate;
    }

    public function setUiTemplate(?DocumentUITemplate $uiTemplate): static
    {
        $this->uiTemplate = $uiTemplate;
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

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    /**
     * Business logic methods
     */

    /**
     * Render component HTML
     */
    public function renderHtml(array $data = []): string
    {
        // Use custom HTML if provided
        if ($this->htmlContent) {
            return $this->processVariables($this->htmlContent, $data);
        }

        // Generate HTML based on component type
        return $this->generateHtmlByType($data);
    }

    /**
     * Generate HTML based on component type
     */
    private function generateHtmlByType(array $data = []): string
    {
        $content = $this->processVariables($this->content ?? '', $data);
        $attrs = $this->generateHtmlAttributes();

        return match ($this->type) {
            self::TYPE_TEXT => "<div{$attrs}>{$content}</div>",
            self::TYPE_IMAGE => $this->generateImageHtml($data),
            self::TYPE_LOGO => $this->generateLogoHtml($data),
            self::TYPE_TABLE => $this->generateTableHtml($data),
            self::TYPE_LIST => $this->generateListHtml($data),
            self::TYPE_SIGNATURE => $this->generateSignatureHtml($data),
            self::TYPE_DATE => "<span{$attrs}>" . date('d/m/Y') . "</span>",
            self::TYPE_PAGE_NUMBER => "<span{$attrs} class='page-number'>{{page_number}}</span>",
            self::TYPE_BARCODE => $this->generateBarcodeHtml($data),
            self::TYPE_QR_CODE => $this->generateQrCodeHtml($data),
            self::TYPE_DIVIDER => "<hr{$attrs}>",
            self::TYPE_SPACER => "<div{$attrs} style='height: {$this->getStyleValue('height', '20px')};'></div>",
            self::TYPE_CUSTOM_HTML => $content,
            default => "<div{$attrs}>{$content}</div>",
        };
    }

    /**
     * Generate HTML attributes
     */
    private function generateHtmlAttributes(): string
    {
        $attrs = [];
        
        if ($this->elementId) {
            $attrs[] = 'id="' . htmlspecialchars($this->elementId) . '"';
        }
        
        if ($this->cssClass) {
            $attrs[] = 'class="' . htmlspecialchars($this->cssClass) . '"';
        }
        
        if ($this->styleConfig) {
            $styles = [];
            foreach ($this->styleConfig as $property => $value) {
                $styles[] = htmlspecialchars($property) . ': ' . htmlspecialchars($value);
            }
            if (!empty($styles)) {
                $attrs[] = 'style="' . implode('; ', $styles) . '"';
            }
        }

        return !empty($attrs) ? ' ' . implode(' ', $attrs) : '';
    }

    /**
     * Generate image HTML
     */
    private function generateImageHtml(array $data = []): string
    {
        $src = $data['image_src'] ?? $this->getDataBindingValue('src', $data) ?? '';
        $alt = $data['image_alt'] ?? $this->getDataBindingValue('alt', $data) ?? '';
        $attrs = $this->generateHtmlAttributes();
        
        return "<img{$attrs} src=\"{$src}\" alt=\"{$alt}\">";
    }

    /**
     * Generate logo HTML
     */
    private function generateLogoHtml(array $data = []): string
    {
        $src = $data['logo_src'] ?? '/images/logo.png';
        $attrs = $this->generateHtmlAttributes();
        
        return "<img{$attrs} src=\"{$src}\" alt=\"Logo\" class=\"logo\">";
    }

    /**
     * Generate table HTML
     */
    private function generateTableHtml(array $data = []): string
    {
        $tableData = $data['table_data'] ?? [];
        $attrs = $this->generateHtmlAttributes();
        
        if (empty($tableData)) {
            return "<div{$attrs}>Aucune donnée de tableau disponible</div>";
        }

        $html = "<table{$attrs}>";
        
        // Headers
        if (isset($tableData[0]) && is_array($tableData[0])) {
            $html .= "<thead><tr>";
            foreach (array_keys($tableData[0]) as $header) {
                $html .= "<th>" . htmlspecialchars($header) . "</th>";
            }
            $html .= "</tr></thead>";
        }
        
        // Rows
        $html .= "<tbody>";
        foreach ($tableData as $row) {
            $html .= "<tr>";
            foreach ($row as $cell) {
                $html .= "<td>" . htmlspecialchars($cell) . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";

        return $html;
    }

    /**
     * Generate list HTML
     */
    private function generateListHtml(array $data = []): string
    {
        $listData = $data['list_data'] ?? explode("\n", $this->content ?? '');
        $listType = $this->getStyleValue('list-type', 'ul');
        $attrs = $this->generateHtmlAttributes();
        
        $html = "<{$listType}{$attrs}>";
        foreach ($listData as $item) {
            $html .= "<li>" . htmlspecialchars(trim($item)) . "</li>";
        }
        $html .= "</{$listType}>";

        return $html;
    }

    /**
     * Generate signature HTML
     */
    private function generateSignatureHtml(array $data = []): string
    {
        $signatureName = $data['signature_name'] ?? $this->getDataBindingValue('name', $data) ?? '';
        $signatureTitle = $data['signature_title'] ?? $this->getDataBindingValue('title', $data) ?? '';
        $signatureImage = $data['signature_image'] ?? $this->getDataBindingValue('image', $data) ?? '';
        $attrs = $this->generateHtmlAttributes();
        
        $html = "<div{$attrs} class=\"signature-block\">";
        if ($signatureImage) {
            $html .= "<img src=\"{$signatureImage}\" alt=\"Signature\" class=\"signature-image\">";
        }
        $html .= "<div class=\"signature-name\">{$signatureName}</div>";
        if ($signatureTitle) {
            $html .= "<div class=\"signature-title\">{$signatureTitle}</div>";
        }
        $html .= "</div>";

        return $html;
    }

    /**
     * Generate barcode HTML
     */
    private function generateBarcodeHtml(array $data = []): string
    {
        $value = $data['barcode_value'] ?? $this->getDataBindingValue('value', $data) ?? '';
        $attrs = $this->generateHtmlAttributes();
        
        return "<div{$attrs} class=\"barcode\" data-value=\"{$value}\">{$value}</div>";
    }

    /**
     * Generate QR code HTML
     */
    private function generateQrCodeHtml(array $data = []): string
    {
        $value = $data['qr_value'] ?? $this->getDataBindingValue('value', $data) ?? '';
        $attrs = $this->generateHtmlAttributes();
        
        return "<div{$attrs} class=\"qr-code\" data-value=\"{$value}\">{$value}</div>";
    }

    /**
     * Process variables in content
     */
    private function processVariables(string $content, array $data): string
    {
        // Replace data binding variables
        if ($this->dataBinding) {
            foreach ($this->dataBinding as $variable => $path) {
                $value = $this->getNestedValue($data, $path) ?? '';
                $content = str_replace('{{' . $variable . '}}', $value, $content);
            }
        }

        // Replace direct data variables
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
        }

        return $content;
    }

    /**
     * Get nested value from data array
     */
    private function getNestedValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }

    /**
     * Get data binding value
     */
    private function getDataBindingValue(string $key, array $data)
    {
        if (!$this->dataBinding || !isset($this->dataBinding[$key])) {
            return null;
        }
        
        return $this->getNestedValue($data, $this->dataBinding[$key]);
    }

    /**
     * Get style configuration value
     */
    private function getStyleValue(string $property, string $default = ''): string
    {
        return $this->styleConfig[$property] ?? $default;
    }

    /**
     * Set style configuration value
     */
    public function setStyleValue(string $property, string $value): self
    {
        $config = $this->styleConfig ?? [];
        $config[$property] = $value;
        $this->styleConfig = $config;
        return $this;
    }

    /**
     * Set data binding
     */
    public function setDataBindingValue(string $variable, string $path): self
    {
        $binding = $this->dataBinding ?? [];
        $binding[$variable] = $path;
        $this->dataBinding = $binding;
        return $this;
    }

    /**
     * Check if component has conditional display rules
     */
    public function isConditional(): bool
    {
        return !empty($this->conditionalDisplay);
    }

    /**
     * Check if component should be displayed based on conditions
     */
    public function shouldDisplay(array $data = []): bool
    {
        if (!$this->conditionalDisplay) {
            return $this->isActive;
        }

        foreach ($this->conditionalDisplay as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? '';
            
            $fieldValue = $this->getNestedValue($data, $field);
            
            $result = match ($operator) {
                '=' => $fieldValue == $value,
                '!=' => $fieldValue != $value,
                '>' => $fieldValue > $value,
                '<' => $fieldValue < $value,
                '>=' => $fieldValue >= $value,
                '<=' => $fieldValue <= $value,
                'contains' => str_contains((string)$fieldValue, (string)$value),
                'not_contains' => !str_contains((string)$fieldValue, (string)$value),
                'empty' => empty($fieldValue),
                'not_empty' => !empty($fieldValue),
                default => true,
            };
            
            if (!$result) {
                return false;
            }
        }

        return $this->isActive;
    }

    /**
     * Get type label
     */
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get zone label
     */
    public function getZoneLabel(): string
    {
        return DocumentUITemplate::ZONES[$this->zone] ?? $this->zone;
    }

    /**
     * Clone component
     */
    public function cloneComponent(): self
    {
        $clone = new self();
        $clone->setName($this->name . ' (Copie)')
              ->setType($this->type)
              ->setZone($this->zone)
              ->setContent($this->content)
              ->setHtmlContent($this->htmlContent)
              ->setStyleConfig($this->styleConfig)
              ->setPositionConfig($this->positionConfig)
              ->setDataBinding($this->dataBinding)
              ->setConditionalDisplay($this->conditionalDisplay)
              ->setIsActive($this->isActive)
              ->setIsRequired($this->isRequired)
              ->setSortOrder($this->sortOrder + 1)
              ->setCssClass($this->cssClass)
              ->setElementId($this->elementId ? $this->elementId . '_copy' : null);

        return $clone;
    }

    /**
     * Get valid types for validation
     */
    public static function getValidTypes(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Get valid zones for validation
     */
    public static function getValidZones(): array
    {
        return array_keys(DocumentUITemplate::ZONES);
    }

    public function __toString(): string
    {
        return $this->name ?: 'Component #' . $this->id;
    }
}
