<?php

namespace App\Service;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentType;
use App\Entity\Document\DocumentVersion;
use App\Entity\User\Admin;
use App\Repository\Document\DocumentRepository;
use App\Repository\Document\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Document Service
 * 
 * Provides business logic for managing documents.
 * Handles document operations, versioning, and workflow management.
 */
class DocumentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentRepository $documentRepository,
        private DocumentTypeRepository $documentTypeRepository,
        private SluggerInterface $slugger,
        private Security $security,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a new document with automatic versioning
     */
    public function createDocument(Document $document): array
    {
        try {
            // Set the current user as creator
            if ($admin = $this->security->getUser()) {
                $document->setCreatedBy($admin);
                $document->setUpdatedBy($admin);
            }

            // Generate unique slug if not provided
            if (!$document->getSlug()) {
                $slug = $this->generateUniqueSlug($document->getTitle());
                $document->setSlug($slug);
            }

            // Set default status based on document type
            if (!$document->getStatus()) {
                $defaultStatus = $this->getDefaultStatusForType($document->getDocumentType());
                $document->setStatus($defaultStatus);
            }

            // Validate business rules
            $validationResult = $this->validateDocument($document);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $validationResult['errors']
                ];
            }

            // Check if document type allows multiple published documents
            $publishingResult = $this->checkPublishingRules($document);
            if (!$publishingResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $publishingResult['errors']
                ];
            }

            $this->entityManager->persist($document);

            // Create initial version
            $this->createInitialVersion($document);

            $this->entityManager->flush();

            $this->logger->info('Document created successfully', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'type' => $document->getDocumentType()?->getCode(),
                'created_by' => $document->getCreatedBy()?->getEmail()
            ]);

            return [
                'success' => true,
                'document' => $document
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error creating document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la création du document.']
            ];
        }
    }

    /**
     * Update an existing document with versioning
     */
    public function updateDocument(Document $document, array $versionData = []): array
    {
        try {
            // Set the current user as updater
            if ($admin = $this->security->getUser()) {
                $document->setUpdatedBy($admin);
            }

            // Validate business rules
            $validationResult = $this->validateDocument($document);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $validationResult['errors']
                ];
            }

            // Check publishing rules
            $publishingResult = $this->checkPublishingRules($document);
            if (!$publishingResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $publishingResult['errors']
                ];
            }

            // Handle versioning based on user input
            $newVersion = $this->handleVersioning($document, $versionData);

            $this->entityManager->flush();

            $this->logger->info('Document updated successfully', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'version_created' => $newVersion ? $newVersion->getVersion() : null,
                'updated_by' => $document->getUpdatedBy()?->getEmail()
            ]);

            $result = [
                'success' => true,
                'document' => $document
            ];

            if ($newVersion) {
                $result['new_version'] = $newVersion;
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Error updating document', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la modification du document.']
            ];
        }
    }

    /**
     * Delete a document (with safety checks)
     */
    public function deleteDocument(Document $document): array
    {
        try {
            // Check if document can be deleted
            if ($document->getStatus() === Document::STATUS_PUBLISHED) {
                return [
                    'success' => false,
                    'errors' => ['Impossible de supprimer un document publié. Archivez-le d\'abord.']
                ];
            }

            $documentId = $document->getId();
            $documentTitle = $document->getTitle();

            $this->entityManager->remove($document);
            $this->entityManager->flush();

            $this->logger->info('Document deleted successfully', [
                'document_id' => $documentId,
                'title' => $documentTitle,
                'deleted_by' => ($admin = $this->security->getUser()) instanceof Admin ? $admin->getEmail() : null
            ]);

            return [
                'success' => true
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error deleting document', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la suppression du document.']
            ];
        }
    }

    /**
     * Publish a document
     */
    public function publishDocument(Document $document): array
    {
        try {
            // Check if document type allows publishing
            if (!$this->canPublishDocument($document)) {
                return [
                    'success' => false,
                    'errors' => ['Ce document ne peut pas être publié dans son état actuel.']
                ];
            }

            // Check publishing rules (e.g., only one published document per type)
            $publishingResult = $this->checkPublishingRules($document, true);
            if (!$publishingResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $publishingResult['errors']
                ];
            }

            $document->publish();
            
            if ($admin = $this->security->getUser()) {
                $document->setUpdatedBy($admin);
            }

            $this->entityManager->flush();

            $this->logger->info('Document published successfully', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'published_by' => ($admin = $this->security->getUser()) instanceof Admin ? $admin->getEmail() : null
            ]);

            return [
                'success' => true,
                'document' => $document
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error publishing document', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la publication.']
            ];
        }
    }

    /**
     * Archive a document
     */
    public function archiveDocument(Document $document): array
    {
        try {
            $document->setStatus(Document::STATUS_ARCHIVED);
            $document->setIsActive(false);
            
            if ($admin = $this->security->getUser()) {
                $document->setUpdatedBy($admin);
            }

            $this->entityManager->flush();

            $this->logger->info('Document archived successfully', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'archived_by' => ($admin = $this->security->getUser()) instanceof Admin ? $admin->getEmail() : null
            ]);

            return [
                'success' => true,
                'document' => $document
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error archiving document', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de l\'archivage.']
            ];
        }
    }

    /**
     * Duplicate a document
     */
    public function duplicateDocument(Document $document): array
    {
        try {
            $duplicate = new Document();
            $duplicate->setTitle($document->getTitle() . ' (Copie)');
            $duplicate->setDescription($document->getDescription());
            $duplicate->setContent($document->getContent());
            $duplicate->setDocumentType($document->getDocumentType());
            $duplicate->setCategory($document->getCategory());
            $duplicate->setStatus(Document::STATUS_DRAFT);
            $duplicate->setIsActive(true);
            $duplicate->setIsPublic($document->isPublic());
            $duplicate->setTags($document->getTags());

            // Generate unique slug
            $slug = $this->generateUniqueSlug($duplicate->getTitle());
            $duplicate->setSlug($slug);

            if ($admin = $this->security->getUser()) {
                $duplicate->setCreatedBy($admin);
                $duplicate->setUpdatedBy($admin);
            }

            $this->entityManager->persist($duplicate);

            // Create initial version for duplicate
            $this->createInitialVersion($duplicate);

            $this->entityManager->flush();

            $this->logger->info('Document duplicated successfully', [
                'original_id' => $document->getId(),
                'duplicate_id' => $duplicate->getId(),
                'title' => $duplicate->getTitle(),
                'duplicated_by' => ($admin = $this->security->getUser()) instanceof Admin ? $admin->getEmail() : null
            ]);

            return [
                'success' => true,
                'document' => $duplicate
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error duplicating document', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la duplication.']
            ];
        }
    }

    /**
     * Get document type by ID
     */
    public function getDocumentTypeById(int $id): ?DocumentType
    {
        return $this->documentTypeRepository->find($id);
    }

    /**
     * Generate a unique slug for a document
     */
    private function generateUniqueSlug(string $title): string
    {
        $baseSlug = $this->slugger->slug($title)->lower();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->documentRepository->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get default status for a document type
     */
    private function getDefaultStatusForType(?DocumentType $documentType): string
    {
        if (!$documentType) {
            return Document::STATUS_DRAFT;
        }

        $allowedStatuses = $documentType->getAllowedStatuses() ?? [];
        
        if (in_array(Document::STATUS_DRAFT, $allowedStatuses)) {
            return Document::STATUS_DRAFT;
        }

        return $allowedStatuses[0] ?? Document::STATUS_DRAFT;
    }

    /**
     * Validate document business rules
     */
    private function validateDocument(Document $document): array
    {
        $errors = [];

        // Check required fields
        if (!$document->getTitle()) {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (!$document->getDocumentType()) {
            $errors[] = 'Le type de document est obligatoire.';
        }

        // Validate status against document type
        if ($document->getDocumentType()) {
            $allowedStatuses = $document->getDocumentType()->getAllowedStatuses() ?? [];
            if (!empty($allowedStatuses) && !in_array($document->getStatus(), $allowedStatuses)) {
                $errors[] = 'Le statut "' . $document->getStatus() . '" n\'est pas autorisé pour ce type de document.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check publishing rules for document type
     */
    private function checkPublishingRules(Document $document, bool $isPublishing = false): array
    {
        $errors = [];
        $documentType = $document->getDocumentType();

        if (!$documentType) {
            return ['valid' => true, 'errors' => []];
        }

        // Check if multiple published documents are allowed
        if (!$documentType->isAllowMultiplePublished() && 
            ($document->getStatus() === Document::STATUS_PUBLISHED || $isPublishing)) {
            
            $existingPublished = $this->documentRepository->findBy([
                'documentType' => $documentType,
                'status' => Document::STATUS_PUBLISHED
            ]);

            // Filter out the current document if it's being updated
            if ($document->getId()) {
                $existingPublished = array_filter($existingPublished, function($doc) use ($document) {
                    return $doc->getId() !== $document->getId();
                });
            }

            if (!empty($existingPublished)) {
                $errors[] = 'Un seul document de ce type peut être publié à la fois. Archivez d\'abord le document existant.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check if document can be published
     */
    private function canPublishDocument(Document $document): bool
    {
        if (!$document->getTitle() || !$document->getDocumentType()) {
            return false;
        }

        $documentType = $document->getDocumentType();
        
        // Check if document type requires approval
        if ($documentType->isRequiresApproval() && $document->getStatus() !== Document::STATUS_APPROVED) {
            return false;
        }

        return true;
    }

    /**
     * Create initial version for a new document
     */
    private function createInitialVersion(Document $document): void
    {
        $version = new DocumentVersion();
        $version->setDocument($document);
        $version->setVersion('1.0');
        $version->setTitle($document->getTitle());
        $version->setContent($document->getContent());
        $version->setChangeLog('Version initiale');
        $version->setIsCurrent(true);

        if ($admin = $this->security->getUser()) {
            $version->setCreatedBy($admin);
        }

        $this->entityManager->persist($version);
    }

    /**
     * Handle versioning based on user input
     */
    private function handleVersioning(Document $document, array $versionData): ?DocumentVersion
    {
        // Get version settings
        $versionType = $versionData['type'] ?? 'minor';
        $versionMessage = $versionData['message'] ?? null;

        // Skip versioning if explicitly requested
        if ($versionType === 'none') {
            return null;
        }

        // Get the last version
        $lastVersion = $this->entityManager
            ->getRepository(DocumentVersion::class)
            ->findLatestByDocument($document);

        if (!$lastVersion) {
            return $this->createInitialVersionForUpdate($document, $versionMessage);
        }

        // Check if significant changes occurred
        $hasSignificantChanges = 
            $lastVersion->getTitle() !== $document->getTitle() ||
            $lastVersion->getContent() !== $document->getContent();

        // Create version based on user choice and content changes
        if ($hasSignificantChanges || $versionMessage) {
            // Mark previous version as not current
            $lastVersion->setIsCurrent(false);

            // Create new version with proper numbering
            $newVersionNumber = $this->calculateNewVersionNumber($lastVersion->getVersion(), $versionType);
            
            $newVersion = new DocumentVersion();
            $newVersion->setDocument($document);
            $newVersion->setVersion($newVersionNumber);
            $newVersion->setTitle($document->getTitle());
            $newVersion->setContent($document->getContent());
            $newVersion->setChangeLog($versionMessage ?: $this->getDefaultChangeLog($versionType));
            $newVersion->setIsCurrent(true);

            if ($admin = $this->security->getUser()) {
                $newVersion->setCreatedBy($admin);
            }

            // Generate checksum for content integrity
            $newVersion->generateChecksum();

            $this->entityManager->persist($newVersion);
            
            // Update document version
            $document->setVersion($newVersionNumber);

            return $newVersion;
        }

        return null;
    }

    /**
     * Calculate new version number based on type
     */
    private function calculateNewVersionNumber(string $currentVersion, string $versionType): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $currentVersion, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            
            if ($versionType === 'major') {
                return ($major + 1) . '.0';
            } else {
                return $major . '.' . ($minor + 1);
            }
        }

        return '1.0';
    }

    /**
     * Get default change log message based on version type
     */
    private function getDefaultChangeLog(string $versionType): string
    {
        return match ($versionType) {
            'major' => 'Modification majeure du document',
            'minor' => 'Modification mineure du document',
            default => 'Mise à jour du document'
        };
    }

    /**
     * Create initial version for an existing document being updated
     */
    private function createInitialVersionForUpdate(Document $document, ?string $changeLog = null): DocumentVersion
    {
        $version = new DocumentVersion();
        $version->setDocument($document);
        $version->setVersion('1.0');
        $version->setTitle($document->getTitle());
        $version->setContent($document->getContent());
        $version->setChangeLog($changeLog ?: 'Version initiale lors de la mise à jour');
        $version->setIsCurrent(true);

        if ($admin = $this->security->getUser()) {
            $version->setCreatedBy($admin);
        }

        $version->generateChecksum();
        $this->entityManager->persist($version);
        
        $document->setVersion('1.0');

        return $version;
    }

    /**
     * Create new version if content has changed significantly
     * 
     * @deprecated Use handleVersioning() instead for better control
     */
    private function createVersionIfNeeded(Document $document): void
    {
        // Get the last version
        $lastVersion = $this->entityManager
            ->getRepository(DocumentVersion::class)
            ->findLatestByDocument($document);

        if (!$lastVersion) {
            $this->createInitialVersion($document);
            return;
        }

        // Check if significant changes occurred
        $hasSignificantChanges = 
            $lastVersion->getTitle() !== $document->getTitle() ||
            $lastVersion->getContent() !== $document->getContent();

        if ($hasSignificantChanges) {
            // Mark previous version as not current
            $lastVersion->setIsCurrent(false);

            // Create new version
            $newVersion = new DocumentVersion();
            $newVersion->setDocument($document);
            $newVersion->setVersion($this->getNextVersionNumber($lastVersion->getVersion()));
            $newVersion->setTitle($document->getTitle());
            $newVersion->setContent($document->getContent());
            $newVersion->setChangeLog('Mise à jour automatique');
            $newVersion->setIsCurrent(true);

            if ($admin = $this->security->getUser()) {
                $newVersion->setCreatedBy($admin);
            }

            $this->entityManager->persist($newVersion);
        }
    }

    /**
     * Get next version number
     */
    private function getNextVersionNumber(string $currentVersion): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $currentVersion, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            
            return $major . '.' . ($minor + 1);
        }

        return '1.0';
    }
}
