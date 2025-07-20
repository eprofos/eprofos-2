<?php

namespace App\Entity\Document;

use App\Repository\Document\DocumentCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DocumentCategory entity - Provides hierarchical organization capability
 * 
 * This entity enables hierarchical organization of documents in nested categories,
 * which is impossible with the current flat structure of LegalDocument.
 * Supports unlimited nesting levels for flexible document organization.
 */
#[ORM\Entity(repositoryClass: DocumentCategoryRepository::class)]
#[ORM\Table(name: 'document_categories')]
#[ORM\HasLifecycleCallbacks]
class DocumentCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 500, unique: true)]
    #[Assert\NotBlank(message: 'Le slug est obligatoire.')]
    #[Assert\Length(
        min: 2,
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

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    #[ORM\Column]
    private int $level = 0;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $color = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Document::class)]
    private Collection $documents;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Calculate and update the level based on parent hierarchy
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateLevel(): void
    {
        $this->level = $this->parent ? $this->parent->getLevel() + 1 : 0;
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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * Set children collection (used for tree building)
     * @param Collection<int, self>|array<self> $children
     */
    public function setChildren(Collection|array $children): static
    {
        if (is_array($children)) {
            $this->children = new ArrayCollection($children);
        } else {
            $this->children = $children;
        }

        return $this;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;
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
            $document->setCategory($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getCategory() === $this) {
                $document->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * Business logic methods
     */

    /**
     * Check if this category is a root category (no parent)
     */
    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * Check if this category has children
     */
    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    /**
     * Get all descendants recursively
     * 
     * @return array<self>
     */
    public function getAllDescendants(): array
    {
        $descendants = [];
        
        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getAllDescendants());
        }
        
        return $descendants;
    }

    /**
     * Get path from root to this category
     * 
     * @return array<self>
     */
    public function getPath(): array
    {
        $path = [];
        $current = $this;
        
        while ($current !== null) {
            array_unshift($path, $current);
            $current = $current->getParent();
        }
        
        return $path;
    }

    /**
     * Get breadcrumb names as string
     */
    public function getBreadcrumb(string $separator = ' > '): string
    {
        $path = $this->getPath();
        return implode($separator, array_map(fn(self $cat) => $cat->getName(), $path));
    }

    /**
     * Get full slug path
     */
    public function getFullSlug(): string
    {
        $path = $this->getPath();
        return implode('/', array_map(fn(self $cat) => $cat->getSlug(), $path));
    }

    /**
     * Count all documents in this category and its children
     */
    public function getTotalDocumentsCount(): int
    {
        $count = $this->documents->count();
        
        foreach ($this->children as $child) {
            $count += $child->getTotalDocumentsCount();
        }
        
        return $count;
    }

    public function __toString(): string
    {
        return $this->name ?: 'Catégorie #' . $this->id;
    }
}
