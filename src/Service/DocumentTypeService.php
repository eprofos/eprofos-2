<?php

namespace App\Service;

use App\Entity\Document\DocumentType;
use App\Repository\Document\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Document Type Service
 * 
 * Provides business logic for managing document types.
 * Handles type creation, validation, and business rules.
 */
class DocumentTypeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentTypeRepository $documentTypeRepository,
        private SluggerInterface $slugger,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a new document type with automatic slug generation
     */
    public function createDocumentType(DocumentType $documentType): array
    {
        try {
            // Generate unique code if not provided
            if (!$documentType->getCode()) {
                $code = $this->generateUniqueCode($documentType->getName());
                $documentType->setCode($code);
            }

            // Validate uniqueness
            $existing = $this->documentTypeRepository->findByCode($documentType->getCode());
            if ($existing && $existing->getId() !== $documentType->getId()) {
                return [
                    'success' => false,
                    'error' => 'Un type de document avec ce code existe déjà.'
                ];
            }

            // Set default configuration if not provided
            if (!$documentType->getConfiguration()) {
                $documentType->setConfiguration([
                    'auto_version' => true,
                    'track_downloads' => true,
                    'enable_comments' => false,
                    'require_review' => false
                ]);
            }

            // Set default allowed statuses if not provided
            if (!$documentType->getAllowedStatuses()) {
                $documentType->setAllowedStatuses([
                    'draft',
                    'under_review',
                    'published',
                    'archived'
                ]);
            }

            $this->entityManager->persist($documentType);
            $this->entityManager->flush();

            $this->logger->info('Document type created', [
                'type_id' => $documentType->getId(),
                'code' => $documentType->getCode(),
                'name' => $documentType->getName()
            ]);

            return [
                'success' => true,
                'document_type' => $documentType
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error creating document type', [
                'error' => $e->getMessage(),
                'code' => $documentType->getCode()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la création du type de document: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing document type
     */
    public function updateDocumentType(DocumentType $documentType): array
    {
        try {
            // Validate uniqueness if code changed
            $existing = $this->documentTypeRepository->findByCode($documentType->getCode());
            if ($existing && $existing->getId() !== $documentType->getId()) {
                return [
                    'success' => false,
                    'error' => 'Un type de document avec ce code existe déjà.'
                ];
            }

            $this->entityManager->flush();

            $this->logger->info('Document type updated', [
                'type_id' => $documentType->getId(),
                'code' => $documentType->getCode(),
                'name' => $documentType->getName()
            ]);

            return [
                'success' => true,
                'document_type' => $documentType
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error updating document type', [
                'error' => $e->getMessage(),
                'type_id' => $documentType->getId()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la mise à jour du type de document: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a document type (only if no documents exist)
     */
    public function deleteDocumentType(DocumentType $documentType): array
    {
        try {
            // Check if type has documents
            if ($documentType->getDocuments()->count() > 0) {
                return [
                    'success' => false,
                    'error' => 'Impossible de supprimer ce type car il contient des documents.'
                ];
            }

            $typeId = $documentType->getId();
            $typeName = $documentType->getName();

            $this->entityManager->remove($documentType);
            $this->entityManager->flush();

            $this->logger->info('Document type deleted', [
                'type_id' => $typeId,
                'name' => $typeName
            ]);

            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error deleting document type', [
                'error' => $e->getMessage(),
                'type_id' => $documentType->getId()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la suppression du type de document: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get document types with statistics
     */
    public function getDocumentTypesWithStats(): array
    {
        $types = $this->documentTypeRepository->findAllActive();
        $stats = [];

        foreach ($types as $type) {
            $stats[] = [
                'type' => $type,
                'document_count' => $type->getDocuments()->count(),
                'template_count' => $type->getTemplates()->count(),
                'published_count' => $type->getDocuments()->filter(fn($doc) => $doc->getStatus() === 'published')->count()
            ];
        }

        return $stats;
    }

    /**
     * Generate unique code from name
     */
    private function generateUniqueCode(string $name): string
    {
        $baseCode = $this->slugger->slug($name)->lower();
        $code = $baseCode;
        $counter = 1;

        while ($this->documentTypeRepository->findByCode($code)) {
            $code = $baseCode . '_' . $counter;
            $counter++;
        }

        return $code;
    }

    /**
     * Validate document type business rules
     */
    public function validateDocumentType(DocumentType $documentType): array
    {
        $issues = [];

        // Required fields validation
        if (!$documentType->getName()) {
            $issues[] = 'Le nom est obligatoire.';
        }

        if (!$documentType->getCode()) {
            $issues[] = 'Le code est obligatoire.';
        }

        // Code format validation
        if ($documentType->getCode() && !preg_match('/^[a-z0-9_]+$/', $documentType->getCode())) {
            $issues[] = 'Le code ne peut contenir que des lettres minuscules, chiffres et underscores.';
        }

        // Business logic validation
        if (!$documentType->isAllowMultiplePublished() && $documentType->getDocuments()->count() > 1) {
            $publishedCount = $documentType->getDocuments()->filter(fn($doc) => $doc->getStatus() === 'published')->count();
            if ($publishedCount > 1) {
                $issues[] = 'Ce type ne permet qu\'un seul document publié à la fois.';
            }
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Toggle document type active status
     */
    public function toggleActiveStatus(DocumentType $documentType): array
    {
        try {
            $documentType->setIsActive(!$documentType->isActive());
            $this->entityManager->flush();

            $status = $documentType->isActive() ? 'activé' : 'désactivé';

            $this->logger->info('Document type status toggled', [
                'type_id' => $documentType->getId(),
                'new_status' => $documentType->isActive()
            ]);

            return [
                'success' => true,
                'message' => "Le type de document a été {$status} avec succès."
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error toggling document type status', [
                'error' => $e->getMessage(),
                'type_id' => $documentType->getId()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors du changement de statut: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get next sort order for document types
     */
    public function getNextSortOrder(): int
    {
        $maxOrder = $this->entityManager->createQueryBuilder()
            ->select('MAX(dt.sortOrder)')
            ->from(DocumentType::class, 'dt')
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxOrder ?? 0) + 1;
    }
}
