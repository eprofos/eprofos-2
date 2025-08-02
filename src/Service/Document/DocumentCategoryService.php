<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\Document\DocumentCategory;
use App\Repository\Document\DocumentCategoryRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Document Category Service.
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
        private LoggerInterface $logger,
    ) {}

    /**
     * Create a new document category with automatic slug generation.
     */
    public function createDocumentCategory(DocumentCategory $category): array
    {
        $this->logger->info('Starting document category creation', [
            'category_name' => $category->getName(),
            'parent_id' => $category->getParent()?->getId(),
            'provided_slug' => $category->getSlug(),
            'sort_order' => $category->getSortOrder(),
            'is_active' => $category->isActive(),
            'description' => $category->getDescription(),
        ]);

        try {
            // Generate unique slug if not provided
            if (!$category->getSlug()) {
                $this->logger->debug('Generating unique slug for category', [
                    'category_name' => $category->getName(),
                    'parent_slug' => $category->getParent()?->getSlug(),
                ]);

                $slug = $this->generateUniqueSlug($category->getName(), $category->getParent());
                $category->setSlug($slug);

                $this->logger->info('Generated unique slug for category', [
                    'generated_slug' => $slug,
                    'category_name' => $category->getName(),
                ]);
            }

            // Validate slug uniqueness
            $this->logger->debug('Validating slug uniqueness', [
                'slug' => $category->getSlug(),
                'category_id' => $category->getId(),
            ]);

            $existing = $this->documentCategoryRepository->findBySlug($category->getSlug());
            if ($existing && $existing->getId() !== $category->getId()) {
                $this->logger->warning('Slug already exists for another category', [
                    'attempted_slug' => $category->getSlug(),
                    'existing_category_id' => $existing->getId(),
                    'existing_category_name' => $existing->getName(),
                    'new_category_name' => $category->getName(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Une catégorie avec ce slug existe déjà.',
                ];
            }

            // Set sort order if not provided
            if ($category->getSortOrder() === 0) {
                $this->logger->debug('Determining sort order for category', [
                    'parent_id' => $category->getParent()?->getId(),
                ]);

                $sortOrder = $this->documentCategoryRepository->getNextSortOrder($category->getParent());
                $category->setSortOrder($sortOrder);

                $this->logger->info('Set sort order for category', [
                    'sort_order' => $sortOrder,
                    'category_name' => $category->getName(),
                    'parent_id' => $category->getParent()?->getId(),
                ]);
            }

            // Calculate level based on parent
            $oldLevel = $category->getLevel();
            $this->updateCategoryLevel($category);

            $this->logger->debug('Updated category level', [
                'category_name' => $category->getName(),
                'old_level' => $oldLevel,
                'new_level' => $category->getLevel(),
                'parent_level' => $category->getParent()?->getLevel(),
            ]);

            $this->logger->debug('Persisting category to database', [
                'category_name' => $category->getName(),
                'slug' => $category->getSlug(),
                'level' => $category->getLevel(),
                'sort_order' => $category->getSortOrder(),
            ]);

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->logger->info('Document category created successfully', [
                'category_id' => $category->getId(),
                'slug' => $category->getSlug(),
                'name' => $category->getName(),
                'level' => $category->getLevel(),
                'sort_order' => $category->getSortOrder(),
                'parent_id' => $category->getParent()?->getId(),
                'is_active' => $category->isActive(),
                'created_at' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'category' => $category,
            ];
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->error('Unique constraint violation during category creation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'category_name' => $category->getName(),
                'slug' => $category->getSlug(),
                'constraint_details' => $e->getPrevious()?->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Une catégorie avec ces informations existe déjà.',
            ];
        } catch (ORMException $e) {
            $this->logger->error('ORM error during category creation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'category_name' => $category->getName(),
                'slug' => $category->getSlug(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur de base de données lors de la création de la catégorie.',
            ];
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for category creation', [
                'error_message' => $e->getMessage(),
                'category_name' => $category->getName(),
                'slug' => $category->getSlug(),
                'parent_id' => $category->getParent()?->getId(),
            ]);

            return [
                'success' => false,
                'error' => 'Données invalides fournies pour la création de la catégorie.',
            ];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error creating document category', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'category_name' => $category->getName(),
                'slug' => $category->getSlug(),
                'parent_id' => $category->getParent()?->getId(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur inattendue lors de la création de la catégorie: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update an existing document category.
     */
    public function updateDocumentCategory(DocumentCategory $category): array
    {
        $this->logger->info('Starting document category update', [
            'category_id' => $category->getId(),
            'category_name' => $category->getName(),
            'slug' => $category->getSlug(),
            'parent_id' => $category->getParent()?->getId(),
            'level' => $category->getLevel(),
            'sort_order' => $category->getSortOrder(),
            'is_active' => $category->isActive(),
        ]);

        try {
            // Validate slug uniqueness if changed
            $this->logger->debug('Validating slug uniqueness for update', [
                'category_id' => $category->getId(),
                'slug' => $category->getSlug(),
            ]);

            $existing = $this->documentCategoryRepository->findBySlug($category->getSlug());
            if ($existing && $existing->getId() !== $category->getId()) {
                $this->logger->warning('Slug conflict detected during update', [
                    'category_id' => $category->getId(),
                    'attempted_slug' => $category->getSlug(),
                    'conflicting_category_id' => $existing->getId(),
                    'conflicting_category_name' => $existing->getName(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Une catégorie avec ce slug existe déjà.',
                ];
            }

            // Store original level for comparison
            $originalLevel = $category->getLevel();
            $originalParentId = $category->getParent()?->getId();

            // Update level if parent changed
            $this->updateCategoryLevel($category);

            $this->logger->debug('Updated category level', [
                'category_id' => $category->getId(),
                'original_level' => $originalLevel,
                'new_level' => $category->getLevel(),
                'original_parent_id' => $originalParentId,
                'new_parent_id' => $category->getParent()?->getId(),
            ]);

            // Update children levels if needed
            $childrenCount = $category->getChildren()->count();
            if ($childrenCount > 0) {
                $this->logger->debug('Updating children levels', [
                    'parent_category_id' => $category->getId(),
                    'children_count' => $childrenCount,
                    'new_parent_level' => $category->getLevel(),
                ]);

                $this->updateChildrenLevels($category);

                $this->logger->info('Updated children levels', [
                    'parent_category_id' => $category->getId(),
                    'children_updated' => $childrenCount,
                ]);
            }

            $this->logger->debug('Flushing category updates to database', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Document category updated successfully', [
                'category_id' => $category->getId(),
                'slug' => $category->getSlug(),
                'name' => $category->getName(),
                'level' => $category->getLevel(),
                'parent_id' => $category->getParent()?->getId(),
                'children_count' => $category->getChildren()->count(),
                'documents_count' => $category->getDocuments()->count(),
                'updated_at' => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'category' => $category,
            ];
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->error('Unique constraint violation during category update', [
                'error_message' => $e->getMessage(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'slug' => $category->getSlug(),
                'constraint_details' => $e->getPrevious()?->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Conflit de données lors de la mise à jour de la catégorie.',
            ];
        } catch (ORMException $e) {
            $this->logger->error('ORM error during category update', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur de base de données lors de la mise à jour.',
            ];
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument during category update', [
                'error_message' => $e->getMessage(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'slug' => $category->getSlug(),
            ]);

            return [
                'success' => false,
                'error' => 'Données invalides pour la mise à jour de la catégorie.',
            ];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error updating document category', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'slug' => $category->getSlug(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur inattendue lors de la mise à jour de la catégorie: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a document category (only if no documents or children exist).
     */
    public function deleteDocumentCategory(DocumentCategory $category): array
    {
        $this->logger->info('Starting document category deletion', [
            'category_id' => $category->getId(),
            'category_name' => $category->getName(),
            'slug' => $category->getSlug(),
            'level' => $category->getLevel(),
            'parent_id' => $category->getParent()?->getId(),
            'documents_count' => $category->getDocuments()->count(),
            'children_count' => $category->getChildren()->count(),
        ]);

        try {
            // Check if category has documents
            $documentsCount = $category->getDocuments()->count();
            if ($documentsCount > 0) {
                $this->logger->warning('Cannot delete category with documents', [
                    'category_id' => $category->getId(),
                    'category_name' => $category->getName(),
                    'documents_count' => $documentsCount,
                    'document_ids' => $category->getDocuments()->map(static fn ($doc) => $doc->getId())->toArray(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Impossible de supprimer cette catégorie car elle contient des documents.',
                ];
            }

            // Check if category has children
            $childrenCount = $category->getChildren()->count();
            if ($childrenCount > 0) {
                $childrenData = [];
                foreach ($category->getChildren() as $child) {
                    $childrenData[] = [
                        'id' => $child->getId(),
                        'name' => $child->getName(),
                        'slug' => $child->getSlug(),
                    ];
                }

                $this->logger->warning('Cannot delete category with children', [
                    'category_id' => $category->getId(),
                    'category_name' => $category->getName(),
                    'children_count' => $childrenCount,
                    'children_details' => $childrenData,
                ]);

                return [
                    'success' => false,
                    'error' => 'Impossible de supprimer cette catégorie car elle contient des sous-catégories.',
                ];
            }

            // Store category data for logging after deletion
            $categoryData = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'level' => $category->getLevel(),
                'sort_order' => $category->getSortOrder(),
                'parent_id' => $category->getParent()?->getId(),
                'parent_name' => $category->getParent()?->getName(),
                'created_at' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];

            $this->logger->debug('Removing category from database', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
            ]);

            $this->entityManager->remove($category);
            $this->entityManager->flush();

            $this->logger->info('Document category deleted successfully', $categoryData);

            return [
                'success' => true,
            ];
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error('Foreign key constraint violation during category deletion', [
                'error_message' => $e->getMessage(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'constraint_details' => $e->getPrevious()?->getMessage(),
                'related_table' => $this->extractTableFromConstraintError($e->getMessage()),
            ]);

            return [
                'success' => false,
                'error' => 'Impossible de supprimer cette catégorie car elle est référencée par d\'autres éléments.',
            ];
        } catch (ORMException $e) {
            $this->logger->error('ORM error during category deletion', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur de base de données lors de la suppression.',
            ];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error deleting document category', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur inattendue lors de la suppression de la catégorie: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get category tree with document counts.
     */
    public function getCategoryTreeWithStats(): array
    {
        $this->logger->info('Getting category tree with statistics');

        try {
            $categories = $this->documentCategoryRepository->findCategoryTree();

            $this->logger->debug('Retrieved categories for tree', [
                'categories_count' => count($categories),
                'category_ids' => array_map(static fn ($cat) => $cat->getId(), $categories),
            ]);

            $treeWithStats = $this->buildTreeWithStats($categories);

            $this->logger->info('Built category tree with statistics', [
                'root_categories_count' => count($treeWithStats),
                'total_categories_processed' => $this->countCategoriesInTree($treeWithStats),
            ]);

            return $treeWithStats;
        } catch (ORMException $e) {
            $this->logger->error('ORM error getting category tree', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error getting category tree with stats', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Move category to new parent.
     */
    public function moveCategory(DocumentCategory $category, ?DocumentCategory $newParent): array
    {
        $this->logger->info('Starting category move operation', [
            'category_id' => $category->getId(),
            'category_name' => $category->getName(),
            'current_parent_id' => $category->getParent()?->getId(),
            'current_parent_name' => $category->getParent()?->getName(),
            'new_parent_id' => $newParent?->getId(),
            'new_parent_name' => $newParent?->getName(),
            'current_level' => $category->getLevel(),
        ]);

        try {
            // Prevent circular reference
            if ($newParent && $this->isDescendantOf($newParent, $category)) {
                $this->logger->warning('Circular reference detected during category move', [
                    'category_id' => $category->getId(),
                    'category_name' => $category->getName(),
                    'attempted_new_parent_id' => $newParent->getId(),
                    'attempted_new_parent_name' => $newParent->getName(),
                    'ancestor_path' => $this->getAncestorPath($newParent),
                ]);

                return [
                    'success' => false,
                    'error' => 'Impossible de déplacer une catégorie vers une de ses sous-catégories.',
                ];
            }

            $oldSlug = $category->getSlug();
            $oldLevel = $category->getLevel();
            $oldParentId = $category->getParent()?->getId();

            $category->setParent($newParent);
            $this->updateCategoryLevel($category);

            $this->logger->debug('Updated category hierarchy', [
                'category_id' => $category->getId(),
                'old_level' => $oldLevel,
                'new_level' => $category->getLevel(),
                'old_parent_id' => $oldParentId,
                'new_parent_id' => $newParent?->getId(),
            ]);

            // Update children levels
            $childrenCount = $category->getChildren()->count();
            if ($childrenCount > 0) {
                $this->logger->debug('Updating children levels after move', [
                    'parent_category_id' => $category->getId(),
                    'children_count' => $childrenCount,
                ]);

                $this->updateChildrenLevels($category);
            }

            // Update slug to reflect new hierarchy
            $newSlug = $this->generateUniqueSlug($category->getName(), $newParent);
            $category->setSlug($newSlug);

            $this->logger->debug('Generated new slug after move', [
                'category_id' => $category->getId(),
                'old_slug' => $oldSlug,
                'new_slug' => $newSlug,
                'new_parent_slug' => $newParent?->getSlug(),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Document category moved successfully', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'old_parent_id' => $oldParentId,
                'new_parent_id' => $newParent?->getId(),
                'old_level' => $oldLevel,
                'new_level' => $category->getLevel(),
                'old_slug' => $oldSlug,
                'new_slug' => $newSlug,
                'children_updated' => $childrenCount,
            ]);

            return [
                'success' => true,
                'category' => $category,
            ];
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->error('Slug conflict during category move', [
                'error_message' => $e->getMessage(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'new_parent_id' => $newParent?->getId(),
                'attempted_slug' => $category->getSlug(),
            ]);

            return [
                'success' => false,
                'error' => 'Conflit de slug lors du déplacement de la catégorie.',
            ];
        } catch (ORMException $e) {
            $this->logger->error('ORM error during category move', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'category_id' => $category->getId(),
                'new_parent_id' => $newParent?->getId(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur de base de données lors du déplacement.',
            ];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error moving document category', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'category_id' => $category->getId(),
                'new_parent_id' => $newParent?->getId(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur inattendue lors du déplacement de la catégorie: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Toggle category active status.
     */
    public function toggleActiveStatus(DocumentCategory $category): array
    {
        $oldStatus = $category->isActive();

        $this->logger->info('Starting category status toggle', [
            'category_id' => $category->getId(),
            'category_name' => $category->getName(),
            'current_status' => $oldStatus,
            'children_count' => $category->getChildren()->count(),
            'documents_count' => $category->getDocuments()->count(),
        ]);

        try {
            $category->setIsActive(!$category->isActive());
            $newStatus = $category->isActive();

            $this->logger->debug('Toggled category status', [
                'category_id' => $category->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            $this->entityManager->flush();

            $status = $category->isActive() ? 'activée' : 'désactivée';

            $this->logger->info('Document category status toggled successfully', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'status_text' => $status,
                'updated_at' => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => "La catégorie a été {$status} avec succès.",
            ];
        } catch (ORMException $e) {
            $this->logger->error('ORM error during status toggle', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'attempted_status' => !$oldStatus,
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur de base de données lors du changement de statut.',
            ];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error toggling document category status', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'old_status' => $oldStatus,
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur inattendue lors du changement de statut: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate unique slug for category.
     */
    private function generateUniqueSlug(string $name, ?DocumentCategory $parent = null): string
    {
        $this->logger->debug('Generating unique slug', [
            'name' => $name,
            'parent_id' => $parent?->getId(),
            'parent_slug' => $parent?->getSlug(),
        ]);

        try {
            $baseSlug = $this->slugger->slug($name)->lower();

            // Add parent slug prefix if has parent
            if ($parent) {
                $baseSlug = $parent->getSlug() . '/' . $baseSlug;

                $this->logger->debug('Added parent slug prefix', [
                    'parent_slug' => $parent->getSlug(),
                    'base_slug' => $baseSlug,
                ]);
            }

            $slug = $baseSlug;
            $counter = 1;

            while ($this->documentCategoryRepository->findBySlug($slug)) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;

                $this->logger->debug('Slug already exists, trying variation', [
                    'attempted_slug' => $slug,
                    'counter' => $counter,
                ]);

                if ($counter > 100) {
                    $this->logger->warning('Too many slug variations attempted', [
                        'name' => $name,
                        'base_slug' => $baseSlug,
                        'counter' => $counter,
                    ]);

                    throw new RuntimeException('Unable to generate unique slug after 100 attempts');
                }
            }

            $this->logger->info('Generated unique slug', [
                'name' => $name,
                'final_slug' => $slug,
                'attempts' => $counter,
                'parent_id' => $parent?->getId(),
            ]);

            return $slug;
        } catch (Exception $e) {
            $this->logger->error('Error generating unique slug', [
                'error_message' => $e->getMessage(),
                'name' => $name,
                'parent_id' => $parent?->getId(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update category level based on parent.
     */
    private function updateCategoryLevel(DocumentCategory $category): void
    {
        $oldLevel = $category->getLevel();

        if ($category->getParent()) {
            $category->setLevel($category->getParent()->getLevel() + 1);
        } else {
            $category->setLevel(0);
        }

        $this->logger->debug('Updated category level', [
            'category_id' => $category->getId(),
            'category_name' => $category->getName(),
            'old_level' => $oldLevel,
            'new_level' => $category->getLevel(),
            'parent_id' => $category->getParent()?->getId(),
            'parent_level' => $category->getParent()?->getLevel(),
        ]);
    }

    /**
     * Update all children levels recursively.
     */
    private function updateChildrenLevels(DocumentCategory $category): void
    {
        $this->logger->debug('Starting recursive children level update', [
            'parent_category_id' => $category->getId(),
            'parent_level' => $category->getLevel(),
            'children_count' => $category->getChildren()->count(),
        ]);

        foreach ($category->getChildren() as $child) {
            $oldLevel = $child->getLevel();
            $child->setLevel($category->getLevel() + 1);

            $this->logger->debug('Updated child category level', [
                'child_id' => $child->getId(),
                'child_name' => $child->getName(),
                'old_level' => $oldLevel,
                'new_level' => $child->getLevel(),
                'parent_id' => $category->getId(),
            ]);

            $this->updateChildrenLevels($child);
        }
    }

    /**
     * Check if category is descendant of another category.
     */
    private function isDescendantOf(DocumentCategory $category, DocumentCategory $ancestor): bool
    {
        $this->logger->debug('Checking if category is descendant', [
            'category_id' => $category->getId(),
            'ancestor_id' => $ancestor->getId(),
        ]);

        $parent = $category->getParent();
        $depth = 0;

        while ($parent) {
            $depth++;

            if ($parent->getId() === $ancestor->getId()) {
                $this->logger->debug('Descendant relationship confirmed', [
                    'category_id' => $category->getId(),
                    'ancestor_id' => $ancestor->getId(),
                    'depth' => $depth,
                ]);

                return true;
            }

            $parent = $parent->getParent();

            // Prevent infinite loops
            if ($depth > 50) {
                $this->logger->warning('Deep hierarchy detected during descendant check', [
                    'category_id' => $category->getId(),
                    'ancestor_id' => $ancestor->getId(),
                    'depth' => $depth,
                ]);
                break;
            }
        }

        $this->logger->debug('No descendant relationship found', [
            'category_id' => $category->getId(),
            'ancestor_id' => $ancestor->getId(),
            'depth_checked' => $depth,
        ]);

        return false;
    }

    /**
     * Build tree with statistics.
     */
    private function buildTreeWithStats(array $categories): array
    {
        $this->logger->debug('Building tree with statistics', [
            'categories_count' => count($categories),
        ]);

        $stats = [];

        foreach ($categories as $category) {
            $documentsCount = $category->getDocuments()->count();
            $childrenCount = $category->getChildren()->count();
            $totalDocuments = $this->getTotalDocumentCount($category);

            $categoryStats = [
                'category' => $category,
                'document_count' => $documentsCount,
                'children_count' => $childrenCount,
                'total_documents' => $totalDocuments,
                'children' => $this->buildTreeWithStats($category->getChildren()->toArray()),
            ];

            $this->logger->debug('Built category statistics', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'direct_documents' => $documentsCount,
                'children_count' => $childrenCount,
                'total_documents' => $totalDocuments,
                'level' => $category->getLevel(),
            ]);

            $stats[] = $categoryStats;
        }

        return $stats;
    }

    /**
     * Get total document count including children.
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
     * Extract table name from constraint error message.
     */
    private function extractTableFromConstraintError(string $errorMessage): ?string
    {
        // Try to extract table name from common constraint error patterns
        if (preg_match('/table "([^"]+)"/', $errorMessage, $matches)) {
            return $matches[1];
        }

        if (preg_match('/constraint "([^"]+)"/', $errorMessage, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get ancestor path for a category.
     */
    private function getAncestorPath(DocumentCategory $category): array
    {
        $path = [];
        $current = $category;
        $depth = 0;

        while ($current && $depth < 50) {
            $path[] = [
                'id' => $current->getId(),
                'name' => $current->getName(),
                'slug' => $current->getSlug(),
                'level' => $current->getLevel(),
            ];

            $current = $current->getParent();
            $depth++;
        }

        return array_reverse($path);
    }

    /**
     * Count total categories in tree structure.
     */
    private function countCategoriesInTree(array $tree): int
    {
        $count = count($tree);

        foreach ($tree as $item) {
            if (isset($item['children']) && is_array($item['children'])) {
                $count += $this->countCategoriesInTree($item['children']);
            }
        }

        return $count;
    }
}
