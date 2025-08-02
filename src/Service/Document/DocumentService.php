<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentType;
use App\Entity\Document\DocumentVersion;
use App\Repository\Document\DocumentRepository;
use App\Repository\Document\DocumentTypeRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Document Service.
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
        private LoggerInterface $logger,
    ) {}

    /**
     * Create a new document with automatic versioning.
     */
    public function createDocument(Document $document): array
    {
        $this->logger->info('Starting document creation process', [
            'title' => $document->getTitle(),
            'document_type' => $document->getDocumentType()?->getCode(),
            'status' => $document->getStatus(),
            'is_public' => $document->isPublic(),
            'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
        ]);

        try {
            // Set the current user as creator
            if ($admin = $this->security->getUser()) {
                $this->logger->debug('Setting document creator and updater', [
                    'user_identifier' => $admin->getUserIdentifier(),
                    'user_type' => get_class($admin),
                ]);
                $document->setCreatedBy($admin);
                $document->setUpdatedBy($admin);
            } else {
                $this->logger->warning('No authenticated user found for document creation', [
                    'title' => $document->getTitle(),
                ]);
            }

            // Generate unique slug if not provided
            if (!$document->getSlug()) {
                $this->logger->debug('Generating unique slug for document', [
                    'original_title' => $document->getTitle(),
                ]);

                try {
                    $slug = $this->generateUniqueSlug($document->getTitle());
                    $document->setSlug($slug);

                    $this->logger->debug('Generated unique slug', [
                        'title' => $document->getTitle(),
                        'generated_slug' => $slug,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to generate unique slug', [
                        'title' => $document->getTitle(),
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            } else {
                $this->logger->debug('Document already has slug', [
                    'existing_slug' => $document->getSlug(),
                ]);
            }

            // Set default status based on document type
            if (!$document->getStatus()) {
                $this->logger->debug('Setting default status for document type', [
                    'document_type' => $document->getDocumentType()?->getCode(),
                ]);

                try {
                    $defaultStatus = $this->getDefaultStatusForType($document->getDocumentType());
                    $document->setStatus($defaultStatus);

                    $this->logger->debug('Set default status', [
                        'document_type' => $document->getDocumentType()?->getCode(),
                        'default_status' => $defaultStatus,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to set default status', [
                        'document_type' => $document->getDocumentType()?->getCode(),
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            } else {
                $this->logger->debug('Document already has status', [
                    'existing_status' => $document->getStatus(),
                ]);
            }

            // Validate business rules
            $this->logger->debug('Starting document validation');

            try {
                $validationResult = $this->validateDocument($document);
                if (!$validationResult['valid']) {
                    $this->logger->warning('Document validation failed', [
                        'title' => $document->getTitle(),
                        'validation_errors' => $validationResult['errors'],
                    ]);

                    return [
                        'success' => false,
                        'errors' => $validationResult['errors'],
                    ];
                }

                $this->logger->debug('Document validation passed');
            } catch (Exception $e) {
                $this->logger->error('Error during document validation', [
                    'title' => $document->getTitle(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Check if document type allows multiple published documents
            $this->logger->debug('Checking publishing rules');

            try {
                $publishingResult = $this->checkPublishingRules($document);
                if (!$publishingResult['valid']) {
                    $this->logger->warning('Publishing rules validation failed', [
                        'title' => $document->getTitle(),
                        'document_type' => $document->getDocumentType()?->getCode(),
                        'publishing_errors' => $publishingResult['errors'],
                    ]);

                    return [
                        'success' => false,
                        'errors' => $publishingResult['errors'],
                    ];
                }

                $this->logger->debug('Publishing rules validation passed');
            } catch (Exception $e) {
                $this->logger->error('Error during publishing rules validation', [
                    'title' => $document->getTitle(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Persist document
            $this->logger->debug('Persisting document to database');

            try {
                $this->entityManager->persist($document);
                $this->logger->debug('Document persisted successfully');
            } catch (Exception $e) {
                $this->logger->error('Failed to persist document', [
                    'title' => $document->getTitle(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Create initial version
            $this->logger->debug('Creating initial version for document');

            try {
                $this->createInitialVersion($document);
                $this->logger->debug('Initial version created successfully');
            } catch (Exception $e) {
                $this->logger->error('Failed to create initial version', [
                    'title' => $document->getTitle(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Flush changes to database
            $this->logger->debug('Flushing changes to database');

            try {
                $this->entityManager->flush();
                $this->logger->debug('Database flush completed successfully');
            } catch (Exception $e) {
                $this->logger->error('Failed to flush changes to database', [
                    'title' => $document->getTitle(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->logger->info('Document created successfully', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'slug' => $document->getSlug(),
                'type' => $document->getDocumentType()?->getCode(),
                'status' => $document->getStatus(),
                'version' => $document->getVersion(),
                'is_public' => $document->isPublic(),
                'is_active' => $document->isActive(),
                'created_by' => $document->getCreatedBy()?->getEmail(),
                'created_at' => $document->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'document' => $document,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error during document creation', [
                'title' => $document->getTitle() ?? 'unknown',
                'document_type' => $document->getDocumentType()?->getCode() ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
            ]);

            // Rollback any pending changes
            try {
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                    $this->logger->debug('Transaction rolled back due to error');
                }
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction after error', [
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la création du document.'],
            ];
        }
    }

    /**
     * Update an existing document with versioning.
     */
    public function updateDocument(Document $document, array $versionData = []): array
    {
        $this->logger->info('Starting document update process', [
            'document_id' => $document->getId(),
            'title' => $document->getTitle(),
            'current_status' => $document->getStatus(),
            'current_version' => $document->getVersion(),
            'version_data' => $versionData,
            'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
        ]);

        try {
            // Set the current user as updater
            if ($admin = $this->security->getUser()) {
                $this->logger->debug('Setting document updater', [
                    'user_identifier' => $admin->getUserIdentifier(),
                    'document_id' => $document->getId(),
                ]);
                $document->setUpdatedBy($admin);
            } else {
                $this->logger->warning('No authenticated user found for document update', [
                    'document_id' => $document->getId(),
                    'title' => $document->getTitle(),
                ]);
            }

            // Validate business rules
            $this->logger->debug('Starting document validation for update', [
                'document_id' => $document->getId(),
            ]);

            try {
                $validationResult = $this->validateDocument($document);
                if (!$validationResult['valid']) {
                    $this->logger->warning('Document validation failed during update', [
                        'document_id' => $document->getId(),
                        'title' => $document->getTitle(),
                        'validation_errors' => $validationResult['errors'],
                    ]);

                    return [
                        'success' => false,
                        'errors' => $validationResult['errors'],
                    ];
                }

                $this->logger->debug('Document validation passed for update');
            } catch (Exception $e) {
                $this->logger->error('Error during document validation for update', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Check publishing rules
            $this->logger->debug('Checking publishing rules for update', [
                'document_id' => $document->getId(),
                'status' => $document->getStatus(),
            ]);

            try {
                $publishingResult = $this->checkPublishingRules($document);
                if (!$publishingResult['valid']) {
                    $this->logger->warning('Publishing rules validation failed during update', [
                        'document_id' => $document->getId(),
                        'title' => $document->getTitle(),
                        'publishing_errors' => $publishingResult['errors'],
                    ]);

                    return [
                        'success' => false,
                        'errors' => $publishingResult['errors'],
                    ];
                }

                $this->logger->debug('Publishing rules validation passed for update');
            } catch (Exception $e) {
                $this->logger->error('Error during publishing rules validation for update', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Handle versioning based on user input
            $this->logger->debug('Starting versioning process', [
                'document_id' => $document->getId(),
                'version_data' => $versionData,
            ]);

            $newVersion = null;

            try {
                $newVersion = $this->handleVersioning($document, $versionData);

                if ($newVersion) {
                    $this->logger->info('New version created during update', [
                        'document_id' => $document->getId(),
                        'new_version' => $newVersion->getVersion(),
                        'change_log' => $newVersion->getChangeLog(),
                    ]);
                } else {
                    $this->logger->debug('No new version created during update', [
                        'document_id' => $document->getId(),
                        'reason' => 'No significant changes or version creation skipped',
                    ]);
                }
            } catch (Exception $e) {
                $this->logger->error('Error during versioning process', [
                    'document_id' => $document->getId(),
                    'version_data' => $versionData,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Flush changes to database
            $this->logger->debug('Flushing update changes to database', [
                'document_id' => $document->getId(),
            ]);

            try {
                $this->entityManager->flush();
                $this->logger->debug('Database flush completed successfully for update');
            } catch (Exception $e) {
                $this->logger->error('Failed to flush update changes to database', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->logger->info('Document updated successfully', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'status' => $document->getStatus(),
                'version' => $document->getVersion(),
                'version_created' => $newVersion ? $newVersion->getVersion() : null,
                'updated_by' => $document->getUpdatedBy()?->getUserIdentifier(),
                'updated_at' => $document->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]);

            $result = [
                'success' => true,
                'document' => $document,
            ];

            if ($newVersion) {
                $result['new_version'] = $newVersion;
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Critical error during document update', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle() ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
                'version_data' => $versionData,
            ]);

            // Rollback any pending changes
            try {
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                    $this->logger->debug('Transaction rolled back due to update error');
                }
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction after update error', [
                    'document_id' => $document->getId(),
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la modification du document.'],
            ];
        }
    }

    /**
     * Delete a document (with safety checks).
     */
    public function deleteDocument(Document $document): array
    {
        $documentId = $document->getId();
        $documentTitle = $document->getTitle();
        $documentStatus = $document->getStatus();

        $this->logger->info('Starting document deletion process', [
            'document_id' => $documentId,
            'title' => $documentTitle,
            'status' => $documentStatus,
            'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
        ]);

        try {
            // Check if document can be deleted
            $this->logger->debug('Checking document deletion permissions', [
                'document_id' => $documentId,
                'status' => $documentStatus,
            ]);

            if ($document->getStatus() === Document::STATUS_PUBLISHED) {
                $this->logger->warning('Attempted to delete published document', [
                    'document_id' => $documentId,
                    'title' => $documentTitle,
                    'status' => $documentStatus,
                    'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
                ]);

                return [
                    'success' => false,
                    'errors' => ['Impossible de supprimer un document publié. Archivez-le d\'abord.'],
                ];
            }

            $this->logger->debug('Document deletion permission check passed');

            // Log document details before deletion for audit trail
            $this->logger->info('Document details before deletion', [
                'document_id' => $documentId,
                'title' => $documentTitle,
                'slug' => $document->getSlug(),
                'status' => $documentStatus,
                'version' => $document->getVersion(),
                'document_type' => $document->getDocumentType()?->getCode(),
                'category' => $document->getCategory()?->getName(),
                'is_public' => $document->isPublic(),
                'is_active' => $document->isActive(),
                'created_by' => $document->getCreatedBy()?->getUserIdentifier(),
                'created_at' => $document->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_by' => $document->getUpdatedBy()?->getUserIdentifier(),
                'updated_at' => $document->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]);

            // Check for related entities before deletion
            try {
                $relatedVersionsCount = count($document->getVersions());
                if ($relatedVersionsCount > 0) {
                    $this->logger->info('Document has related versions that will be deleted', [
                        'document_id' => $documentId,
                        'versions_count' => $relatedVersionsCount,
                    ]);
                }
            } catch (Exception $e) {
                $this->logger->warning('Could not check related versions before deletion', [
                    'document_id' => $documentId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Perform deletion
            $this->logger->debug('Removing document from entity manager', [
                'document_id' => $documentId,
            ]);

            try {
                $this->entityManager->remove($document);
                $this->logger->debug('Document removed from entity manager');
            } catch (Exception $e) {
                $this->logger->error('Failed to remove document from entity manager', [
                    'document_id' => $documentId,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Flush changes to database
            $this->logger->debug('Flushing deletion changes to database', [
                'document_id' => $documentId,
            ]);

            try {
                $this->entityManager->flush();
                $this->logger->debug('Database flush completed successfully for deletion');
            } catch (Exception $e) {
                $this->logger->error('Failed to flush deletion changes to database', [
                    'document_id' => $documentId,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->logger->info('Document deleted successfully', [
                'document_id' => $documentId,
                'title' => $documentTitle,
                'status' => $documentStatus,
                'deleted_by' => ($admin = $this->security->getUser()) ? $admin->getUserIdentifier() : 'anonymous',
                'deleted_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error during document deletion', [
                'document_id' => $documentId,
                'title' => $documentTitle,
                'status' => $documentStatus,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
            ]);

            // Rollback any pending changes
            try {
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                    $this->logger->debug('Transaction rolled back due to deletion error');
                }
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction after deletion error', [
                    'document_id' => $documentId,
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la suppression du document.'],
            ];
        }
    }

    /**
     * Publish a document.
     */
    public function publishDocument(Document $document): array
    {
        $this->logger->info('Starting document publication process', [
            'document_id' => $document->getId(),
            'title' => $document->getTitle(),
            'current_status' => $document->getStatus(),
            'document_type' => $document->getDocumentType()?->getCode(),
            'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
        ]);

        try {
            // Check if document type allows publishing
            $this->logger->debug('Checking if document can be published', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'status' => $document->getStatus(),
            ]);

            try {
                $canPublish = $this->canPublishDocument($document);
                if (!$canPublish) {
                    $this->logger->warning('Document cannot be published - requirements not met', [
                        'document_id' => $document->getId(),
                        'title' => $document->getTitle(),
                        'status' => $document->getStatus(),
                        'document_type' => $document->getDocumentType()?->getCode(),
                        'has_title' => !empty($document->getTitle()),
                        'has_document_type' => $document->getDocumentType() !== null,
                        'requires_approval' => $document->getDocumentType()?->isRequiresApproval() ?? false,
                    ]);

                    return [
                        'success' => false,
                        'errors' => ['Ce document ne peut pas être publié dans son état actuel.'],
                    ];
                }

                $this->logger->debug('Document publishing requirements check passed');
            } catch (Exception $e) {
                $this->logger->error('Error checking document publishing requirements', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Check publishing rules (e.g., only one published document per type)
            $this->logger->debug('Checking publishing rules for document type', [
                'document_id' => $document->getId(),
                'document_type' => $document->getDocumentType()?->getCode(),
            ]);

            try {
                $publishingResult = $this->checkPublishingRules($document, true);
                if (!$publishingResult['valid']) {
                    $this->logger->warning('Publishing rules validation failed', [
                        'document_id' => $document->getId(),
                        'title' => $document->getTitle(),
                        'document_type' => $document->getDocumentType()?->getCode(),
                        'publishing_errors' => $publishingResult['errors'],
                    ]);

                    return [
                        'success' => false,
                        'errors' => $publishingResult['errors'],
                    ];
                }

                $this->logger->debug('Publishing rules validation passed');
            } catch (Exception $e) {
                $this->logger->error('Error during publishing rules validation', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Store pre-publication state for logging
            $previousStatus = $document->getStatus();
            $previousIsActive = $document->isActive();

            // Publish the document
            $this->logger->debug('Publishing document', [
                'document_id' => $document->getId(),
                'previous_status' => $previousStatus,
            ]);

            try {
                $document->publish();
                $this->logger->debug('Document status updated to published', [
                    'document_id' => $document->getId(),
                    'new_status' => $document->getStatus(),
                    'is_active' => $document->isActive(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to update document status to published', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Set updater
            if ($admin = $this->security->getUser()) {
                $this->logger->debug('Setting document updater for publication', [
                    'user_identifier' => $admin->getUserIdentifier(),
                    'document_id' => $document->getId(),
                ]);
                $document->setUpdatedBy($admin);
            } else {
                $this->logger->warning('No authenticated user found for document publication', [
                    'document_id' => $document->getId(),
                ]);
            }

            // Flush changes to database
            $this->logger->debug('Flushing publication changes to database', [
                'document_id' => $document->getId(),
            ]);

            try {
                $this->entityManager->flush();
                $this->logger->debug('Database flush completed successfully for publication');
            } catch (Exception $e) {
                $this->logger->error('Failed to flush publication changes to database', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->logger->info('Document published successfully', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'previous_status' => $previousStatus,
                'new_status' => $document->getStatus(),
                'document_type' => $document->getDocumentType()?->getCode(),
                'published_by' => ($admin = $this->security->getUser()) ? $admin->getUserIdentifier() : 'anonymous',
                'published_at' => (new DateTime())->format('Y-m-d H:i:s'),
                'version' => $document->getVersion(),
                'is_public' => $document->isPublic(),
            ]);

            return [
                'success' => true,
                'document' => $document,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error during document publication', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle() ?? 'unknown',
                'current_status' => $document->getStatus(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
            ]);

            // Rollback any pending changes
            try {
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                    $this->logger->debug('Transaction rolled back due to publication error');
                }
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction after publication error', [
                    'document_id' => $document->getId(),
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la publication.'],
            ];
        }
    }

    /**
     * Archive a document.
     */
    public function archiveDocument(Document $document): array
    {
        $this->logger->info('Starting document archiving process', [
            'document_id' => $document->getId(),
            'title' => $document->getTitle(),
            'current_status' => $document->getStatus(),
            'is_active' => $document->isActive(),
            'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
        ]);

        try {
            // Store pre-archiving state for logging
            $previousStatus = $document->getStatus();
            $previousIsActive = $document->isActive();

            // Archive the document
            $this->logger->debug('Setting document status to archived', [
                'document_id' => $document->getId(),
                'previous_status' => $previousStatus,
                'previous_is_active' => $previousIsActive,
            ]);

            try {
                $document->setStatus(Document::STATUS_ARCHIVED);
                $document->setIsActive(false);

                $this->logger->debug('Document status updated to archived', [
                    'document_id' => $document->getId(),
                    'new_status' => $document->getStatus(),
                    'is_active' => $document->isActive(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to update document status to archived', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Set updater
            if ($admin = $this->security->getUser()) {
                $this->logger->debug('Setting document updater for archiving', [
                    'user_identifier' => $admin->getUserIdentifier(),
                    'document_id' => $document->getId(),
                ]);
                $document->setUpdatedBy($admin);
            } else {
                $this->logger->warning('No authenticated user found for document archiving', [
                    'document_id' => $document->getId(),
                ]);
            }

            // Flush changes to database
            $this->logger->debug('Flushing archiving changes to database', [
                'document_id' => $document->getId(),
            ]);

            try {
                $this->entityManager->flush();
                $this->logger->debug('Database flush completed successfully for archiving');
            } catch (Exception $e) {
                $this->logger->error('Failed to flush archiving changes to database', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->logger->info('Document archived successfully', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'previous_status' => $previousStatus,
                'new_status' => $document->getStatus(),
                'previous_is_active' => $previousIsActive,
                'new_is_active' => $document->isActive(),
                'archived_by' => ($admin = $this->security->getUser()) ? $admin->getUserIdentifier() : 'anonymous',
                'archived_at' => (new DateTime())->format('Y-m-d H:i:s'),
                'version' => $document->getVersion(),
                'document_type' => $document->getDocumentType()?->getCode(),
            ]);

            return [
                'success' => true,
                'document' => $document,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error during document archiving', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle() ?? 'unknown',
                'current_status' => $document->getStatus(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
            ]);

            // Rollback any pending changes
            try {
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                    $this->logger->debug('Transaction rolled back due to archiving error');
                }
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction after archiving error', [
                    'document_id' => $document->getId(),
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de l\'archivage.'],
            ];
        }
    }

    /**
     * Duplicate a document.
     */
    public function duplicateDocument(Document $document): array
    {
        $this->logger->info('Starting document duplication process', [
            'original_document_id' => $document->getId(),
            'title' => $document->getTitle(),
            'status' => $document->getStatus(),
            'document_type' => $document->getDocumentType()?->getCode(),
            'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
        ]);

        try {
            // Create duplicate document
            $this->logger->debug('Creating duplicate document instance', [
                'original_document_id' => $document->getId(),
                'original_title' => $document->getTitle(),
            ]);

            try {
                $duplicate = new Document();
                $duplicateTitle = $document->getTitle() . ' (Copie)';

                $duplicate->setTitle($duplicateTitle);
                $duplicate->setDescription($document->getDescription());
                $duplicate->setContent($document->getContent());
                $duplicate->setDocumentType($document->getDocumentType());
                $duplicate->setCategory($document->getCategory());
                $duplicate->setStatus(Document::STATUS_DRAFT);
                $duplicate->setIsActive(true);
                $duplicate->setIsPublic($document->isPublic());
                $duplicate->setTags($document->getTags());

                $this->logger->debug('Duplicate document properties set', [
                    'duplicate_title' => $duplicateTitle,
                    'status' => Document::STATUS_DRAFT,
                    'is_public' => $duplicate->isPublic(),
                    'document_type' => $duplicate->getDocumentType()?->getCode(),
                    'category' => $duplicate->getCategory()?->getName(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to create duplicate document instance', [
                    'original_document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Generate unique slug
            $this->logger->debug('Generating unique slug for duplicate', [
                'duplicate_title' => $duplicate->getTitle(),
            ]);

            try {
                $slug = $this->generateUniqueSlug($duplicate->getTitle());
                $duplicate->setSlug($slug);

                $this->logger->debug('Generated unique slug for duplicate', [
                    'duplicate_title' => $duplicate->getTitle(),
                    'generated_slug' => $slug,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to generate unique slug for duplicate', [
                    'duplicate_title' => $duplicate->getTitle(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Set creator/updater
            if ($admin = $this->security->getUser()) {
                $this->logger->debug('Setting duplicate document creator and updater', [
                    'user_identifier' => $admin->getUserIdentifier(),
                ]);
                $duplicate->setCreatedBy($admin);
                $duplicate->setUpdatedBy($admin);
            } else {
                $this->logger->warning('No authenticated user found for document duplication', [
                    'original_document_id' => $document->getId(),
                ]);
            }

            // Persist duplicate document
            $this->logger->debug('Persisting duplicate document to database');

            try {
                $this->entityManager->persist($duplicate);
                $this->logger->debug('Duplicate document persisted successfully');
            } catch (Exception $e) {
                $this->logger->error('Failed to persist duplicate document', [
                    'original_document_id' => $document->getId(),
                    'duplicate_title' => $duplicate->getTitle(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Create initial version for duplicate
            $this->logger->debug('Creating initial version for duplicate document');

            try {
                $this->createInitialVersion($duplicate);
                $this->logger->debug('Initial version created successfully for duplicate');
            } catch (Exception $e) {
                $this->logger->error('Failed to create initial version for duplicate', [
                    'original_document_id' => $document->getId(),
                    'duplicate_title' => $duplicate->getTitle(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            // Flush changes to database
            $this->logger->debug('Flushing duplication changes to database');

            try {
                $this->entityManager->flush();
                $this->logger->debug('Database flush completed successfully for duplication');
            } catch (Exception $e) {
                $this->logger->error('Failed to flush duplication changes to database', [
                    'original_document_id' => $document->getId(),
                    'duplicate_title' => $duplicate->getTitle(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->logger->info('Document duplicated successfully', [
                'original_id' => $document->getId(),
                'original_title' => $document->getTitle(),
                'duplicate_id' => $duplicate->getId(),
                'duplicate_title' => $duplicate->getTitle(),
                'duplicate_slug' => $duplicate->getSlug(),
                'duplicate_status' => $duplicate->getStatus(),
                'duplicate_version' => $duplicate->getVersion(),
                'duplicated_by' => ($admin = $this->security->getUser()) ? $admin->getUserIdentifier() : 'anonymous',
                'duplicated_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'document' => $duplicate,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error during document duplication', [
                'original_document_id' => $document->getId(),
                'original_title' => $document->getTitle() ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($user = $this->security->getUser()) ? $user->getUserIdentifier() : 'anonymous',
            ]);

            // Rollback any pending changes
            try {
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                    $this->logger->debug('Transaction rolled back due to duplication error');
                }
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction after duplication error', [
                    'original_document_id' => $document->getId(),
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'errors' => ['Une erreur est survenue lors de la duplication.'],
            ];
        }
    }

    /**
     * Get document type by ID.
     */
    public function getDocumentTypeById(int $id): ?DocumentType
    {
        return $this->documentTypeRepository->find($id);
    }

    /**
     * Generate a unique slug for a document.
     */
    private function generateUniqueSlug(string $title): string
    {
        $this->logger->debug('Starting unique slug generation', [
            'original_title' => $title,
        ]);

        try {
            $baseSlug = $this->slugger->slug($title)->lower()->toString();
            $this->logger->debug('Generated base slug', [
                'original_title' => $title,
                'base_slug' => $baseSlug,
            ]);

            $slug = $baseSlug;
            $counter = 1;

            // Check for existing slugs and increment counter if needed
            while ($this->documentRepository->findBySlug($slug)) {
                $this->logger->debug('Slug already exists, incrementing counter', [
                    'attempted_slug' => $slug,
                    'counter' => $counter,
                ]);

                $slug = $baseSlug . '-' . $counter;
                $counter++;

                // Prevent infinite loops
                if ($counter > 1000) {
                    $this->logger->warning('Slug generation reached maximum attempts', [
                        'base_slug' => $baseSlug,
                        'final_counter' => $counter,
                    ]);
                    break;
                }
            }

            $this->logger->debug('Unique slug generated successfully', [
                'original_title' => $title,
                'base_slug' => $baseSlug,
                'final_slug' => $slug,
                'attempts' => $counter,
            ]);

            return $slug;
        } catch (Exception $e) {
            $this->logger->error('Error generating unique slug', [
                'title' => $title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get default status for a document type.
     */
    private function getDefaultStatusForType(?DocumentType $documentType): string
    {
        $this->logger->debug('Determining default status for document type', [
            'document_type' => $documentType?->getCode(),
            'document_type_id' => $documentType?->getId(),
        ]);

        try {
            if (!$documentType) {
                $this->logger->debug('No document type provided, using default draft status');

                return Document::STATUS_DRAFT;
            }

            $allowedStatuses = $documentType->getAllowedStatuses() ?? [];
            $this->logger->debug('Retrieved allowed statuses for document type', [
                'document_type' => $documentType->getCode(),
                'allowed_statuses' => $allowedStatuses,
            ]);

            if (in_array(Document::STATUS_DRAFT, $allowedStatuses, true)) {
                $this->logger->debug('Draft status is allowed, using as default', [
                    'document_type' => $documentType->getCode(),
                ]);

                return Document::STATUS_DRAFT;
            }

            $defaultStatus = $allowedStatuses[0] ?? Document::STATUS_DRAFT;
            $this->logger->debug('Draft status not allowed, using first allowed status', [
                'document_type' => $documentType->getCode(),
                'default_status' => $defaultStatus,
                'allowed_statuses' => $allowedStatuses,
            ]);

            return $defaultStatus;
        } catch (Exception $e) {
            $this->logger->error('Error determining default status for document type', [
                'document_type' => $documentType?->getCode(),
                'error' => $e->getMessage(),
            ]);

            // Fallback to draft status
            return Document::STATUS_DRAFT;
        }
    }

    /**
     * Validate document business rules.
     */
    private function validateDocument(Document $document): array
    {
        $this->logger->debug('Starting document validation', [
            'document_id' => $document->getId(),
            'title' => $document->getTitle(),
            'status' => $document->getStatus(),
            'document_type' => $document->getDocumentType()?->getCode(),
        ]);

        $errors = [];

        try {
            // Check required fields
            if (!$document->getTitle()) {
                $errors[] = 'Le titre est obligatoire.';
                $this->logger->debug('Validation error: missing title', [
                    'document_id' => $document->getId(),
                ]);
            }

            if (!$document->getDocumentType()) {
                $errors[] = 'Le type de document est obligatoire.';
                $this->logger->debug('Validation error: missing document type', [
                    'document_id' => $document->getId(),
                ]);
            }

            // Validate status against document type
            if ($document->getDocumentType()) {
                $allowedStatuses = $document->getDocumentType()->getAllowedStatuses() ?? [];
                $this->logger->debug('Checking status against allowed statuses', [
                    'document_id' => $document->getId(),
                    'current_status' => $document->getStatus(),
                    'allowed_statuses' => $allowedStatuses,
                    'document_type' => $document->getDocumentType()->getCode(),
                ]);

                if (!empty($allowedStatuses) && !in_array($document->getStatus(), $allowedStatuses, true)) {
                    $error = 'Le statut "' . $document->getStatus() . '" n\'est pas autorisé pour ce type de document.';
                    $errors[] = $error;
                    $this->logger->debug('Validation error: invalid status for document type', [
                        'document_id' => $document->getId(),
                        'current_status' => $document->getStatus(),
                        'allowed_statuses' => $allowedStatuses,
                        'document_type' => $document->getDocumentType()->getCode(),
                    ]);
                }
            }

            $isValid = empty($errors);
            $this->logger->debug('Document validation completed', [
                'document_id' => $document->getId(),
                'is_valid' => $isValid,
                'error_count' => count($errors),
                'errors' => $errors,
            ]);

            return [
                'valid' => $isValid,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            $this->logger->error('Error during document validation', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'valid' => false,
                'errors' => ['Une erreur est survenue lors de la validation du document.'],
            ];
        }
    }

    /**
     * Check publishing rules for document type.
     */
    private function checkPublishingRules(Document $document, bool $isPublishing = false): array
    {
        $this->logger->debug('Starting publishing rules validation', [
            'document_id' => $document->getId(),
            'title' => $document->getTitle(),
            'status' => $document->getStatus(),
            'is_publishing' => $isPublishing,
            'document_type' => $document->getDocumentType()?->getCode(),
        ]);

        $errors = [];
        $documentType = $document->getDocumentType();

        try {
            if (!$documentType) {
                $this->logger->debug('No document type found, skipping publishing rules validation');

                return ['valid' => true, 'errors' => []];
            }

            $this->logger->debug('Checking multiple published documents rule', [
                'document_type' => $documentType->getCode(),
                'allow_multiple_published' => $documentType->isAllowMultiplePublished(),
                'document_status' => $document->getStatus(),
                'is_publishing' => $isPublishing,
            ]);

            // Check if multiple published documents are allowed
            if (!$documentType->isAllowMultiplePublished()
                && ($document->getStatus() === Document::STATUS_PUBLISHED || $isPublishing)) {
                try {
                    $existingPublished = $this->documentRepository->findBy([
                        'documentType' => $documentType,
                        'status' => Document::STATUS_PUBLISHED,
                    ]);

                    $this->logger->debug('Found existing published documents', [
                        'document_type' => $documentType->getCode(),
                        'existing_published_count' => count($existingPublished),
                        'existing_published_ids' => array_map(static fn ($doc) => $doc->getId(), $existingPublished),
                    ]);

                    // Filter out the current document if it's being updated
                    if ($document->getId()) {
                        $filteredPublished = array_filter($existingPublished, static fn ($doc) => $doc->getId() !== $document->getId());
                        $this->logger->debug('Filtered out current document from existing published', [
                            'current_document_id' => $document->getId(),
                            'filtered_published_count' => count($filteredPublished),
                            'filtered_published_ids' => array_map(static fn ($doc) => $doc->getId(), $filteredPublished),
                        ]);
                        $existingPublished = $filteredPublished;
                    }

                    if (!empty($existingPublished)) {
                        $error = 'Un seul document de ce type peut être publié à la fois. Archivez d\'abord le document existant.';
                        $errors[] = $error;
                        $this->logger->warning('Publishing rule violation: multiple published documents not allowed', [
                            'document_id' => $document->getId(),
                            'document_type' => $documentType->getCode(),
                            'existing_published_count' => count($existingPublished),
                            'existing_published_titles' => array_map(static fn ($doc) => $doc->getTitle(), $existingPublished),
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Error checking existing published documents', [
                        'document_id' => $document->getId(),
                        'document_type' => $documentType->getCode(),
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }

            $isValid = empty($errors);
            $this->logger->debug('Publishing rules validation completed', [
                'document_id' => $document->getId(),
                'document_type' => $documentType->getCode(),
                'is_valid' => $isValid,
                'error_count' => count($errors),
                'errors' => $errors,
            ]);

            return [
                'valid' => $isValid,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            $this->logger->error('Error during publishing rules validation', [
                'document_id' => $document->getId(),
                'document_type' => $documentType?->getCode(),
                'is_publishing' => $isPublishing,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'valid' => false,
                'errors' => ['Une erreur est survenue lors de la validation des règles de publication.'],
            ];
        }
    }

    /**
     * Check if document can be published.
     */
    private function canPublishDocument(Document $document): bool
    {
        $this->logger->debug('Checking if document can be published', [
            'document_id' => $document->getId(),
            'title' => $document->getTitle(),
            'status' => $document->getStatus(),
            'has_title' => !empty($document->getTitle()),
            'has_document_type' => $document->getDocumentType() !== null,
        ]);

        try {
            if (!$document->getTitle() || !$document->getDocumentType()) {
                $this->logger->debug('Document cannot be published: missing required fields', [
                    'document_id' => $document->getId(),
                    'has_title' => !empty($document->getTitle()),
                    'has_document_type' => $document->getDocumentType() !== null,
                ]);

                return false;
            }

            $documentType = $document->getDocumentType();

            // Check if document type requires approval
            $requiresApproval = $documentType->isRequiresApproval();
            $this->logger->debug('Checking approval requirements', [
                'document_id' => $document->getId(),
                'document_type' => $documentType->getCode(),
                'requires_approval' => $requiresApproval,
                'current_status' => $document->getStatus(),
            ]);

            if ($requiresApproval && $document->getStatus() !== Document::STATUS_APPROVED) {
                $this->logger->debug('Document cannot be published: requires approval but not approved', [
                    'document_id' => $document->getId(),
                    'document_type' => $documentType->getCode(),
                    'current_status' => $document->getStatus(),
                    'required_status' => Document::STATUS_APPROVED,
                ]);

                return false;
            }

            $this->logger->debug('Document can be published', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'document_type' => $documentType->getCode(),
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Error checking if document can be published', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'error' => $e->getMessage(),
            ]);

            // Return false on error for safety
            return false;
        }
    }

    /**
     * Create initial version for a new document.
     */
    private function createInitialVersion(Document $document): void
    {
        $this->logger->debug('Creating initial version for document', [
            'document_id' => $document->getId(),
            'title' => $document->getTitle(),
        ]);

        try {
            $version = new DocumentVersion();
            $version->setDocument($document);
            $version->setVersion('1.0');
            $version->setTitle($document->getTitle());
            $version->setContent($document->getContent());
            $version->setChangeLog('Version initiale');
            $version->setIsCurrent(true);

            if ($admin = $this->security->getUser()) {
                $this->logger->debug('Setting version creator', [
                    'user_identifier' => $admin->getUserIdentifier(),
                    'document_id' => $document->getId(),
                    'version' => '1.0',
                ]);
                $version->setCreatedBy($admin);
            } else {
                $this->logger->warning('No authenticated user found for initial version creation', [
                    'document_id' => $document->getId(),
                ]);
            }

            $this->entityManager->persist($version);

            $this->logger->debug('Initial version created and persisted', [
                'document_id' => $document->getId(),
                'version' => '1.0',
                'is_current' => true,
                'change_log' => 'Version initiale',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error creating initial version for document', [
                'document_id' => $document->getId(),
                'title' => $document->getTitle(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle versioning based on user input.
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
            ->findLatestByDocument($document)
        ;

        if (!$lastVersion) {
            return $this->createInitialVersionForUpdate($document, $versionMessage);
        }

        // Check if significant changes occurred
        $hasSignificantChanges =
            $lastVersion->getTitle() !== $document->getTitle()
            || $lastVersion->getContent() !== $document->getContent();

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
     * Calculate new version number based on type.
     */
    private function calculateNewVersionNumber(string $currentVersion, string $versionType): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $currentVersion, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];

            if ($versionType === 'major') {
                return ($major + 1) . '.0';
            }

            return $major . '.' . ($minor + 1);
        }

        return '1.0';
    }

    /**
     * Get default change log message based on version type.
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
     * Create initial version for an existing document being updated.
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
     * Create new version if content has changed significantly.
     *
     * @deprecated Use handleVersioning() instead for better control
     */
    private function createVersionIfNeeded(Document $document): void
    {
        // Get the last version
        $lastVersion = $this->entityManager
            ->getRepository(DocumentVersion::class)
            ->findLatestByDocument($document)
        ;

        if (!$lastVersion) {
            $this->createInitialVersion($document);

            return;
        }

        // Check if significant changes occurred
        $hasSignificantChanges =
            $lastVersion->getTitle() !== $document->getTitle()
            || $lastVersion->getContent() !== $document->getContent();

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
     * Get next version number.
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
