<?php

namespace App\Service\Document;

use App\Entity\Document\DocumentCategory;
use App\Repository\Document\DocumentCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Document Category Service
 * 
 * Provides business logic for managing document categories.
 * Handles hierarchical operations, slug generation, and validation.
 */
class DocumentCategoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentCategoryRepository $documentCategoryRepository,
        private SluggerInterface $slugger,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a new document category with automatic slug generation
     */
    public function createDocumentCategory(DocumentCategory $category): array
    {
        try {
            // Generate unique slug if not provided
            if (!$category->getSlug()) {
                $slug = $this->generateUniqueSlug($category->getName(), $category->getParent());
                $category->setSlug($slug);
            }

            // Validate slug uniqueness
            $existing = $this->documentCategoryRepository->findBySlug($category->getSlug());
            if ($existing && $existing->getId() !== $category->getId()) {
                return [
                    'success' => false,
                    'error' => 'Une catégorie avec ce slug existe déjà.'
                ];
            }

            // Set sort order if not provided
            if ($category->getSortOrder() === 0) {
                $sortOrder = $this->documentCategoryRepository->getNextSortOrder($category->getParent());
                $category->setSortOrder($sortOrder);
            }

            // Calculate level based on parent
            $this->updateCategoryLevel($category);

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->logger->info('Document category created', [
                'category_id' => $category->getId(),
                'slug' => $category->getSlug(),
                'name' => $category->getName(),
                'level' => $category->getLevel()
            ]);

            return [
                'success' => true,
                'category' => $category
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error creating document category', [
                'error' => $e->getMessage(),
                'slug' => $category->getSlug()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la création de la catégorie: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing document category
     */
    public function updateDocumentCategory(DocumentCategory $category): array
    {
        try {
            // Validate slug uniqueness if changed
            $existing = $this->documentCategoryRepository->findBySlug($category->getSlug());
            if ($existing && $existing->getId() !== $category->getId()) {
                return [
                    'success' => false,
                    'error' => 'Une catégorie avec ce slug existe déjà.'
                ];
            }

            // Update level if parent changed
            $this->updateCategoryLevel($category);

            // Update children levels if needed
            $this->updateChildrenLevels($category);

            $this->entityManager->flush();

            $this->logger->info('Document category updated', [
                'category_id' => $category->getId(),
                'slug' => $category->getSlug(),
                'name' => $category->getName()
            ]);

            return [
                'success' => true,
                'category' => $category
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error updating document category', [
                'error' => $e->getMessage(),
                'category_id' => $category->getId()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la mise à jour de la catégorie: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a document category (only if no documents or children exist)
     */
    public function deleteDocumentCategory(DocumentCategory $category): array
    {
        try {
            // Check if category has documents
            if ($category->getDocuments()->count() > 0) {
                return [
                    'success' => false,
                    'error' => 'Impossible de supprimer cette catégorie car elle contient des documents.'
                ];
            }

            // Check if category has children
            if ($category->getChildren()->count() > 0) {
                return [
                    'success' => false,
                    'error' => 'Impossible de supprimer cette catégorie car elle contient des sous-catégories.'
                ];
            }

            $categoryId = $category->getId();
            $categoryName = $category->getName();

            $this->entityManager->remove($category);
            $this->entityManager->flush();

            $this->logger->info('Document category deleted', [
                'category_id' => $categoryId,
                'name' => $categoryName
            ]);

            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error deleting document category', [
                'error' => $e->getMessage(),
                'category_id' => $category->getId()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la suppression de la catégorie: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get category tree with document counts
     */
    public function getCategoryTreeWithStats(): array
    {
        $categories = $this->documentCategoryRepository->findCategoryTree();
        
        return $this->buildTreeWithStats($categories);
    }

    /**
     * Move category to new parent
     */
    public function moveCategory(DocumentCategory $category, ?DocumentCategory $newParent): array
    {
        try {
            // Prevent circular reference
            if ($newParent && $this->isDescendantOf($newParent, $category)) {
                return [
                    'success' => false,
                    'error' => 'Impossible de déplacer une catégorie vers une de ses sous-catégories.'
                ];
            }

            $category->setParent($newParent);
            $this->updateCategoryLevel($category);
            $this->updateChildrenLevels($category);

            // Update slug to reflect new hierarchy
            $newSlug = $this->generateUniqueSlug($category->getName(), $newParent);
            $category->setSlug($newSlug);

            $this->entityManager->flush();

            $this->logger->info('Document category moved', [
                'category_id' => $category->getId(),
                'new_parent_id' => $newParent?->getId(),
                'new_level' => $category->getLevel()
            ]);

            return [
                'success' => true,
                'category' => $category
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error moving document category', [
                'error' => $e->getMessage(),
                'category_id' => $category->getId()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors du déplacement de la catégorie: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate unique slug for category
     */
    private function generateUniqueSlug(string $name, ?DocumentCategory $parent = null): string
    {
        $baseSlug = $this->slugger->slug($name)->lower();
        
        // Add parent slug prefix if has parent
        if ($parent) {
            $baseSlug = $parent->getSlug() . '/' . $baseSlug;
        }
        
        $slug = $baseSlug;
        $counter = 1;

        while ($this->documentCategoryRepository->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Update category level based on parent
     */
    private function updateCategoryLevel(DocumentCategory $category): void
    {
        if ($category->getParent()) {
            $category->setLevel($category->getParent()->getLevel() + 1);
        } else {
            $category->setLevel(0);
        }
    }

    /**
     * Update all children levels recursively
     */
    private function updateChildrenLevels(DocumentCategory $category): void
    {
        foreach ($category->getChildren() as $child) {
            $child->setLevel($category->getLevel() + 1);
            $this->updateChildrenLevels($child);
        }
    }

    /**
     * Check if category is descendant of another category
     */
    private function isDescendantOf(DocumentCategory $category, DocumentCategory $ancestor): bool
    {
        $parent = $category->getParent();
        
        while ($parent) {
            if ($parent->getId() === $ancestor->getId()) {
                return true;
            }
            $parent = $parent->getParent();
        }
        
        return false;
    }

    /**
     * Build tree with statistics
     */
    private function buildTreeWithStats(array $categories): array
    {
        $stats = [];
        
        foreach ($categories as $category) {
            $stats[] = [
                'category' => $category,
                'document_count' => $category->getDocuments()->count(),
                'children_count' => $category->getChildren()->count(),
                'total_documents' => $this->getTotalDocumentCount($category),
                'children' => $this->buildTreeWithStats($category->getChildren()->toArray())
            ];
        }
        
        return $stats;
    }

    /**
     * Get total document count including children
     */
    private function getTotalDocumentCount(DocumentCategory $category): int
    {
        $count = $category->getDocuments()->count();
        
        foreach ($category->getChildren() as $child) {
            $count += $this->getTotalDocumentCount($child);
        }
        
        return $count;
    }

    /**
     * Toggle category active status
     */
    public function toggleActiveStatus(DocumentCategory $category): array
    {
        try {
            $category->setIsActive(!$category->isActive());
            $this->entityManager->flush();

            $status = $category->isActive() ? 'activée' : 'désactivée';

            $this->logger->info('Document category status toggled', [
                'category_id' => $category->getId(),
                'new_status' => $category->isActive()
            ]);

            return [
                'success' => true,
                'message' => "La catégorie a été {$status} avec succès."
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error toggling document category status', [
                'error' => $e->getMessage(),
                'category_id' => $category->getId()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors du changement de statut: ' . $e->getMessage()
            ];
        }
    }
}
