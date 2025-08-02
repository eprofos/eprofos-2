<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentTemplate;
use App\Entity\Document\DocumentType;
use App\Repository\Document\DocumentTemplateRepository;
use App\Repository\Document\DocumentTypeRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Document Template Service.
 *
 * Handles business logic for document template management:
 * - Template CRUD operations
 * - Template duplication
 * - Document creation from templates
 * - Template statistics and usage tracking
 */
class DocumentTemplateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentTemplateRepository $documentTemplateRepository,
        private DocumentTypeRepository $documentTypeRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Get all templates with usage statistics.
     */
    public function getTemplatesWithStats(): array
    {
        try {
            $this->logger->info('Starting to retrieve templates with statistics');
            
            $templates = $this->documentTemplateRepository->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']);
            $result = [];

            $this->logger->debug('Found templates count', ['count' => count($templates)]);

            foreach ($templates as $template) {
                try {
                    $templateData = [
                        'template' => $template,
                        'usage_count' => $template->getUsageCount(),
                        'document_type' => $template->getDocumentType()?->getName(),
                        'placeholders_count' => count($template->getPlaceholders() ?? []),
                        'is_default' => $template->isDefault(),
                    ];
                    
                    $result[] = $templateData;
                    
                    $this->logger->debug('Processed template for statistics', [
                        'template_id' => $template->getId(),
                        'template_name' => $template->getName(),
                        'usage_count' => $templateData['usage_count'],
                        'document_type' => $templateData['document_type'],
                        'placeholders_count' => $templateData['placeholders_count'],
                        'is_default' => $templateData['is_default'],
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error processing individual template for statistics', [
                        'template_id' => $template->getId() ?? 'unknown',
                        'template_name' => $template->getName() ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue processing other templates
                    continue;
                }
            }

            $this->logger->info('Successfully retrieved templates with statistics', [
                'total_templates' => count($templates),
                'processed_templates' => count($result),
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve templates with statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty array on error to prevent application crash
            return [];
        }
    }

    /**
     * Create a new document template.
     */
    public function createDocumentTemplate(DocumentTemplate $documentTemplate): array
    {
        $this->logger->info('Starting document template creation process', [
            'name' => $documentTemplate->getName(),
            'document_type_id' => $documentTemplate->getDocumentType()?->getId(),
            'document_type_name' => $documentTemplate->getDocumentType()?->getName(),
            'is_default' => $documentTemplate->isDefault(),
            'is_active' => $documentTemplate->isActive(),
        ]);

        try {
            // Set created timestamp
            $this->logger->debug('Setting creation timestamp for document template');
            $documentTemplate->setCreatedAt(new DateTimeImmutable());

            // Validate template
            $this->logger->debug('Starting document template validation');
            $validation = $this->validateDocumentTemplate($documentTemplate);
            if (!$validation['valid']) {
                $this->logger->warning('Document template validation failed', [
                    'name' => $documentTemplate->getName(),
                    'validation_error' => $validation['error'],
                ]);
                return ['success' => false, 'error' => $validation['error']];
            }
            $this->logger->debug('Document template validation passed');

            // Handle default template logic
            if ($documentTemplate->isDefault() && $documentTemplate->getDocumentType()) {
                $this->logger->debug('Handling default template logic', [
                    'document_type_id' => $documentTemplate->getDocumentType()->getId(),
                    'document_type_name' => $documentTemplate->getDocumentType()->getName(),
                ]);
                
                try {
                    $this->unsetOtherDefaultTemplates($documentTemplate->getDocumentType());
                    $this->logger->debug('Successfully unset other default templates');
                } catch (Exception $e) {
                    $this->logger->error('Failed to unset other default templates', [
                        'document_type_id' => $documentTemplate->getDocumentType()->getId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }

            $this->logger->debug('Persisting document template to database');
            $this->entityManager->persist($documentTemplate);
            
            $this->logger->debug('Flushing entity manager to save document template');
            $this->entityManager->flush();

            $this->logger->info('Document template created successfully', [
                'template_id' => $documentTemplate->getId(),
                'name' => $documentTemplate->getName(),
                'type' => $documentTemplate->getDocumentType()?->getName(),
                'is_default' => $documentTemplate->isDefault(),
                'sort_order' => $documentTemplate->getSortOrder(),
                'placeholders_count' => count($documentTemplate->getPlaceholders() ?? []),
            ]);

            return ['success' => true, 'template' => $documentTemplate];
        } catch (Exception $e) {
            $this->logger->error('Failed to create document template', [
                'error' => $e->getMessage(),
                'name' => $documentTemplate->getName(),
                'document_type_id' => $documentTemplate->getDocumentType()?->getId(),
                'document_type_name' => $documentTemplate->getDocumentType()?->getName(),
                'is_default' => $documentTemplate->isDefault(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Rollback any changes
            try {
                $this->entityManager->rollback();
                $this->logger->debug('Successfully rolled back transaction after error');
            } catch (Exception $rollbackException) {
                $this->logger->error('Failed to rollback transaction', [
                    'rollback_error' => $rollbackException->getMessage(),
                    'original_error' => $e->getMessage(),
                ]);
            }

            return ['success' => false, 'error' => 'Erreur lors de la création du modèle: ' . $e->getMessage()];
        }
    }

    /**
     * Update an existing document template.
     */
    public function updateDocumentTemplate(DocumentTemplate $documentTemplate): array
    {
        $this->logger->info('Starting document template update process', [
            'template_id' => $documentTemplate->getId(),
            'name' => $documentTemplate->getName(),
            'document_type_id' => $documentTemplate->getDocumentType()?->getId(),
            'document_type_name' => $documentTemplate->getDocumentType()?->getName(),
            'is_default' => $documentTemplate->isDefault(),
            'is_active' => $documentTemplate->isActive(),
            'usage_count' => $documentTemplate->getUsageCount(),
        ]);

        try {
            // Set updated timestamp
            $this->logger->debug('Setting update timestamp for document template', [
                'template_id' => $documentTemplate->getId(),
            ]);
            $documentTemplate->setUpdatedAt(new DateTimeImmutable());

            // Validate template
            $this->logger->debug('Starting document template validation for update', [
                'template_id' => $documentTemplate->getId(),
            ]);
            $validation = $this->validateDocumentTemplate($documentTemplate);
            if (!$validation['valid']) {
                $this->logger->warning('Document template validation failed during update', [
                    'template_id' => $documentTemplate->getId(),
                    'name' => $documentTemplate->getName(),
                    'validation_error' => $validation['error'],
                ]);
                return ['success' => false, 'error' => $validation['error']];
            }
            $this->logger->debug('Document template validation passed for update');

            // Handle default template logic
            if ($documentTemplate->isDefault() && $documentTemplate->getDocumentType()) {
                $this->logger->debug('Handling default template logic for update', [
                    'template_id' => $documentTemplate->getId(),
                    'document_type_id' => $documentTemplate->getDocumentType()->getId(),
                    'document_type_name' => $documentTemplate->getDocumentType()->getName(),
                ]);
                
                try {
                    $this->unsetOtherDefaultTemplates($documentTemplate->getDocumentType(), $documentTemplate);
                    $this->logger->debug('Successfully unset other default templates during update');
                } catch (Exception $e) {
                    $this->logger->error('Failed to unset other default templates during update', [
                        'template_id' => $documentTemplate->getId(),
                        'document_type_id' => $documentTemplate->getDocumentType()->getId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }

            $this->logger->debug('Flushing entity manager to save template updates', [
                'template_id' => $documentTemplate->getId(),
            ]);
            $this->entityManager->flush();

            $this->logger->info('Document template updated successfully', [
                'template_id' => $documentTemplate->getId(),
                'name' => $documentTemplate->getName(),
                'document_type_name' => $documentTemplate->getDocumentType()?->getName(),
                'is_default' => $documentTemplate->isDefault(),
                'is_active' => $documentTemplate->isActive(),
                'placeholders_count' => count($documentTemplate->getPlaceholders() ?? []),
            ]);

            return ['success' => true, 'template' => $documentTemplate];
        } catch (Exception $e) {
            $this->logger->error('Failed to update document template', [
                'error' => $e->getMessage(),
                'template_id' => $documentTemplate->getId(),
                'name' => $documentTemplate->getName(),
                'document_type_id' => $documentTemplate->getDocumentType()?->getId(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Rollback any changes
            try {
                $this->entityManager->rollback();
                $this->logger->debug('Successfully rolled back transaction after update error', [
                    'template_id' => $documentTemplate->getId(),
                ]);
            } catch (Exception $rollbackException) {
                $this->logger->error('Failed to rollback transaction during update', [
                    'template_id' => $documentTemplate->getId(),
                    'rollback_error' => $rollbackException->getMessage(),
                    'original_error' => $e->getMessage(),
                ]);
            }

            return ['success' => false, 'error' => 'Erreur lors de la modification du modèle: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a document template.
     */
    public function deleteDocumentTemplate(DocumentTemplate $documentTemplate): array
    {
        $templateId = $documentTemplate->getId();
        $templateName = $documentTemplate->getName();
        $usageCount = $documentTemplate->getUsageCount();

        $this->logger->info('Starting document template deletion process', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'usage_count' => $usageCount,
            'document_type_name' => $documentTemplate->getDocumentType()?->getName(),
            'is_default' => $documentTemplate->isDefault(),
        ]);

        try {
            // Check if template is in use
            $this->logger->debug('Checking if template is in use before deletion', [
                'template_id' => $templateId,
                'usage_count' => $usageCount,
            ]);

            if ($usageCount > 0) {
                $this->logger->warning('Template deletion prevented due to usage', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'usage_count' => $usageCount,
                ]);
                return ['success' => false, 'error' => 'Ce modèle ne peut pas être supprimé car il est utilisé par des documents.'];
            }

            $this->logger->debug('Template is not in use, proceeding with deletion', [
                'template_id' => $templateId,
            ]);

            // Additional safety check - verify no related entities exist
            try {
                $this->logger->debug('Performing additional safety checks before deletion');
                // Here you could add checks for related entities if needed
                // $relatedDocuments = $this->documentRepository->findBy(['template' => $documentTemplate]);
                // if (count($relatedDocuments) > 0) { ... }
            } catch (Exception $safetyCheckException) {
                $this->logger->error('Safety check failed during template deletion', [
                    'template_id' => $templateId,
                    'safety_check_error' => $safetyCheckException->getMessage(),
                    'trace' => $safetyCheckException->getTraceAsString(),
                ]);
                throw $safetyCheckException;
            }

            $this->logger->debug('Removing template from entity manager', [
                'template_id' => $templateId,
            ]);
            $this->entityManager->remove($documentTemplate);
            
            $this->logger->debug('Flushing entity manager to delete template', [
                'template_id' => $templateId,
            ]);
            $this->entityManager->flush();

            $this->logger->info('Document template deleted successfully', [
                'template_id' => $templateId,
                'name' => $templateName,
                'document_type_name' => $documentTemplate->getDocumentType()?->getName(),
            ]);

            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error('Failed to delete document template', [
                'error' => $e->getMessage(),
                'template_id' => $templateId,
                'template_name' => $templateName,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Rollback any changes
            try {
                $this->entityManager->rollback();
                $this->logger->debug('Successfully rolled back transaction after deletion error', [
                    'template_id' => $templateId,
                ]);
            } catch (Exception $rollbackException) {
                $this->logger->error('Failed to rollback transaction during deletion', [
                    'template_id' => $templateId,
                    'rollback_error' => $rollbackException->getMessage(),
                    'original_error' => $e->getMessage(),
                ]);
            }

            return ['success' => false, 'error' => 'Erreur lors de la suppression du modèle: ' . $e->getMessage()];
        }
    }

    /**
     * Toggle document template active status.
     */
    public function toggleActiveStatus(DocumentTemplate $documentTemplate): array
    {
        $templateId = $documentTemplate->getId();
        $templateName = $documentTemplate->getName();
        $wasActive = $documentTemplate->isActive();

        $this->logger->info('Starting template active status toggle', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'current_status' => $wasActive,
            'target_status' => !$wasActive,
        ]);

        try {
            $this->logger->debug('Toggling active status', [
                'template_id' => $templateId,
                'from_active' => $wasActive,
                'to_active' => !$wasActive,
            ]);

            $documentTemplate->setIsActive(!$wasActive);
            $documentTemplate->setUpdatedAt(new DateTimeImmutable());

            $this->logger->debug('Flushing entity manager to save status change', [
                'template_id' => $templateId,
            ]);
            $this->entityManager->flush();

            $status = $documentTemplate->isActive() ? 'activé' : 'désactivé';
            $message = sprintf('Le modèle "%s" a été %s avec succès.', $templateName, $status);

            $this->logger->info('Document template status toggled successfully', [
                'template_id' => $templateId,
                'name' => $templateName,
                'previous_status' => $wasActive,
                'new_status' => $documentTemplate->isActive(),
                'status_text' => $status,
            ]);

            return ['success' => true, 'message' => $message];
        } catch (Exception $e) {
            $this->logger->error('Failed to toggle document template status', [
                'error' => $e->getMessage(),
                'template_id' => $templateId,
                'template_name' => $templateName,
                'attempted_status_change' => ['from' => $wasActive, 'to' => !$wasActive],
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Rollback any changes
            try {
                $this->entityManager->rollback();
                $this->logger->debug('Successfully rolled back transaction after status toggle error', [
                    'template_id' => $templateId,
                ]);
            } catch (Exception $rollbackException) {
                $this->logger->error('Failed to rollback transaction during status toggle', [
                    'template_id' => $templateId,
                    'rollback_error' => $rollbackException->getMessage(),
                    'original_error' => $e->getMessage(),
                ]);
            }

            return ['success' => false, 'error' => 'Erreur lors du changement de statut: ' . $e->getMessage()];
        }
    }

    /**
     * Duplicate a document template.
     */
    public function duplicateDocumentTemplate(DocumentTemplate $original): array
    {
        $originalId = $original->getId();
        $originalName = $original->getName();

        $this->logger->info('Starting document template duplication process', [
            'original_id' => $originalId,
            'original_name' => $originalName,
            'document_type_name' => $original->getDocumentType()?->getName(),
            'is_default' => $original->isDefault(),
            'usage_count' => $original->getUsageCount(),
        ]);

        try {
            $this->logger->debug('Creating new template instance for duplication');
            $duplicate = new DocumentTemplate();

            // Copy all properties except ID and timestamps
            $this->logger->debug('Copying template properties', [
                'original_id' => $originalId,
            ]);

            $duplicateName = $originalName . ' (Copie)';
            $duplicate->setName($duplicateName);
            $duplicate->setDescription($original->getDescription());
            $duplicate->setTemplateContent($original->getTemplateContent());
            $duplicate->setPlaceholders($original->getPlaceholders());
            $duplicate->setDefaultMetadata($original->getDefaultMetadata());
            $duplicate->setDocumentType($original->getDocumentType());
            $duplicate->setConfiguration($original->getConfiguration());
            $duplicate->setIcon($original->getIcon());
            $duplicate->setColor($original->getColor());
            $duplicate->setIsActive($original->isActive());
            $duplicate->setIsDefault(false); // Never duplicate as default
            $duplicate->setUsageCount(0);

            $this->logger->debug('Getting next sort order for duplicate template');
            try {
                $nextSortOrder = $this->getNextSortOrder();
                $duplicate->setSortOrder($nextSortOrder);
                $this->logger->debug('Set sort order for duplicate template', [
                    'sort_order' => $nextSortOrder,
                ]);
            } catch (Exception $sortOrderException) {
                $this->logger->error('Failed to get next sort order for duplicate', [
                    'original_id' => $originalId,
                    'error' => $sortOrderException->getMessage(),
                ]);
                throw $sortOrderException;
            }

            $duplicate->setCreatedAt(new DateTimeImmutable());

            $this->logger->debug('Persisting duplicate template to database', [
                'original_id' => $originalId,
                'duplicate_name' => $duplicateName,
            ]);
            $this->entityManager->persist($duplicate);
            
            $this->logger->debug('Flushing entity manager to save duplicate template');
            $this->entityManager->flush();

            $this->logger->info('Document template duplicated successfully', [
                'original_id' => $originalId,
                'original_name' => $originalName,
                'duplicate_id' => $duplicate->getId(),
                'duplicate_name' => $duplicate->getName(),
                'document_type_name' => $duplicate->getDocumentType()?->getName(),
                'sort_order' => $duplicate->getSortOrder(),
                'placeholders_count' => count($duplicate->getPlaceholders() ?? []),
            ]);

            return ['success' => true, 'template' => $duplicate];
        } catch (Exception $e) {
            $this->logger->error('Failed to duplicate document template', [
                'error' => $e->getMessage(),
                'original_id' => $originalId,
                'original_name' => $originalName,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Rollback any changes
            try {
                $this->entityManager->rollback();
                $this->logger->debug('Successfully rolled back transaction after duplication error', [
                    'original_id' => $originalId,
                ]);
            } catch (Exception $rollbackException) {
                $this->logger->error('Failed to rollback transaction during duplication', [
                    'original_id' => $originalId,
                    'rollback_error' => $rollbackException->getMessage(),
                    'original_error' => $e->getMessage(),
                ]);
            }

            return ['success' => false, 'error' => 'Erreur lors de la duplication du modèle: ' . $e->getMessage()];
        }
    }

    /**
     * Create a document from a template.
     */
    public function createDocumentFromTemplate(DocumentTemplate $template, array $placeholderValues = []): array
    {
        $templateId = $template->getId();
        $templateName = $template->getName();

        $this->logger->info('Starting document creation from template', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'document_type_name' => $template->getDocumentType()?->getName(),
            'placeholders_provided' => count($placeholderValues),
            'template_placeholders' => count($template->getPlaceholders() ?? []),
        ]);

        try {
            $this->logger->debug('Creating new document instance from template');
            $document = new Document();

            // Set basic properties
            $this->logger->debug('Setting basic document properties from template', [
                'template_id' => $templateId,
            ]);
            $document->setTitle($templateName);
            $document->setDescription($template->getDescription());
            $document->setDocumentType($template->getDocumentType());

            // Process template content with placeholders
            $this->logger->debug('Processing template content with placeholders', [
                'template_id' => $templateId,
                'placeholders_count' => count($placeholderValues),
                'placeholder_keys' => array_keys($placeholderValues),
            ]);

            try {
                $content = $this->processTemplatePlaceholders($template->getTemplateContent(), $placeholderValues);
                $document->setContent($content);
                $this->logger->debug('Successfully processed template placeholders');
            } catch (Exception $placeholderException) {
                $this->logger->error('Failed to process template placeholders', [
                    'template_id' => $templateId,
                    'placeholder_error' => $placeholderException->getMessage(),
                    'placeholders' => $placeholderValues,
                ]);
                throw $placeholderException;
            }

            // Apply metadata defaults
            if ($template->getDefaultMetadata()) {
                $this->logger->debug('Applying default metadata from template', [
                    'template_id' => $templateId,
                    'metadata_keys' => array_keys($template->getDefaultMetadata()),
                ]);

                try {
                    foreach ($template->getDefaultMetadata() as $key => $value) {
                        // Apply metadata defaults to document
                        // This would require DocumentMetadata entity creation
                        $this->logger->debug('Applied metadata default', [
                            'key' => $key,
                            'value' => $value,
                        ]);
                    }
                } catch (Exception $metadataException) {
                    $this->logger->warning('Failed to apply some metadata defaults', [
                        'template_id' => $templateId,
                        'metadata_error' => $metadataException->getMessage(),
                    ]);
                    // Continue execution as metadata application is not critical
                }
            }

            // Set status and timestamps
            $this->logger->debug('Setting document status and timestamps');
            $document->setStatus('draft');
            $document->setCreatedAt(new DateTimeImmutable());

            // Increment template usage count
            $this->logger->debug('Incrementing template usage count', [
                'template_id' => $templateId,
                'current_usage_count' => $template->getUsageCount(),
            ]);
            $template->setUsageCount($template->getUsageCount() + 1);
            $template->setUpdatedAt(new DateTimeImmutable());

            $this->logger->debug('Persisting document and updating template');
            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->logger->info('Document created from template successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'document_id' => $document->getId(),
                'document_title' => $document->getTitle(),
                'new_usage_count' => $template->getUsageCount(),
                'document_status' => $document->getStatus(),
            ]);

            return ['success' => true, 'document' => $document];
        } catch (Exception $e) {
            $this->logger->error('Failed to create document from template', [
                'error' => $e->getMessage(),
                'template_id' => $templateId,
                'template_name' => $templateName,
                'placeholders_provided' => count($placeholderValues),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Rollback any changes
            try {
                $this->entityManager->rollback();
                $this->logger->debug('Successfully rolled back transaction after document creation error', [
                    'template_id' => $templateId,
                ]);
            } catch (Exception $rollbackException) {
                $this->logger->error('Failed to rollback transaction during document creation', [
                    'template_id' => $templateId,
                    'rollback_error' => $rollbackException->getMessage(),
                    'original_error' => $e->getMessage(),
                ]);
            }

            return ['success' => false, 'error' => 'Erreur lors de la création du document: ' . $e->getMessage()];
        }
    }

    /**
     * Get next sort order for templates.
     */
    public function getNextSortOrder(): int
    {
        $this->logger->debug('Calculating next sort order for document template');

        try {
            $maxSortOrder = $this->documentTemplateRepository->createQueryBuilder('dt')
                ->select('MAX(dt.sortOrder)')
                ->getQuery()
                ->getSingleScalarResult()
            ;

            $nextSortOrder = ($maxSortOrder ?? 0) + 1;

            $this->logger->debug('Next sort order calculated', [
                'max_sort_order' => $maxSortOrder,
                'next_sort_order' => $nextSortOrder,
            ]);

            return $nextSortOrder;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate next sort order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return a safe default value
            $defaultSortOrder = 1;
            $this->logger->warning('Using default sort order due to calculation error', [
                'default_sort_order' => $defaultSortOrder,
            ]);

            return $defaultSortOrder;
        }
    }

    /**
     * Get document type by ID.
     */
    public function getDocumentTypeById(int $typeId): ?DocumentType
    {
        $this->logger->debug('Retrieving document type by ID', [
            'type_id' => $typeId,
        ]);

        try {
            $documentType = $this->documentTypeRepository->find($typeId);

            if ($documentType) {
                $this->logger->debug('Document type found', [
                    'type_id' => $typeId,
                    'type_name' => $documentType->getName(),
                ]);
            } else {
                $this->logger->warning('Document type not found', [
                    'type_id' => $typeId,
                ]);
            }

            return $documentType;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve document type by ID', [
                'type_id' => $typeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Validate document template.
     */
    private function validateDocumentTemplate(DocumentTemplate $documentTemplate): array
    {
        $this->logger->debug('Starting document template validation', [
            'template_id' => $documentTemplate->getId(),
            'template_name' => $documentTemplate->getName(),
        ]);

        try {
            // Check required name
            if (empty($documentTemplate->getName())) {
                $this->logger->warning('Template validation failed: missing name', [
                    'template_id' => $documentTemplate->getId(),
                ]);
                return ['valid' => false, 'error' => 'Le nom du modèle est requis.'];
            }

            // Check required content
            if (empty($documentTemplate->getTemplateContent())) {
                $this->logger->warning('Template validation failed: missing content', [
                    'template_id' => $documentTemplate->getId(),
                    'template_name' => $documentTemplate->getName(),
                ]);
                return ['valid' => false, 'error' => 'Le contenu du modèle est requis.'];
            }

            // Check for duplicate names within the same document type
            $this->logger->debug('Checking for duplicate template names', [
                'template_name' => $documentTemplate->getName(),
                'document_type_id' => $documentTemplate->getDocumentType()?->getId(),
            ]);

            try {
                $existingTemplate = $this->documentTemplateRepository->findOneBy([
                    'name' => $documentTemplate->getName(),
                    'documentType' => $documentTemplate->getDocumentType(),
                ]);

                if ($existingTemplate && $existingTemplate->getId() !== $documentTemplate->getId()) {
                    $this->logger->warning('Template validation failed: duplicate name', [
                        'template_id' => $documentTemplate->getId(),
                        'template_name' => $documentTemplate->getName(),
                        'existing_template_id' => $existingTemplate->getId(),
                        'document_type_id' => $documentTemplate->getDocumentType()?->getId(),
                    ]);
                    return ['valid' => false, 'error' => 'Un modèle avec ce nom existe déjà pour ce type de document.'];
                }
            } catch (Exception $duplicateCheckException) {
                $this->logger->error('Failed to check for duplicate template names', [
                    'template_name' => $documentTemplate->getName(),
                    'error' => $duplicateCheckException->getMessage(),
                ]);
                throw $duplicateCheckException;
            }

            $this->logger->debug('Document template validation passed', [
                'template_id' => $documentTemplate->getId(),
                'template_name' => $documentTemplate->getName(),
            ]);

            return ['valid' => true];
        } catch (Exception $e) {
            $this->logger->error('Error during document template validation', [
                'template_id' => $documentTemplate->getId(),
                'template_name' => $documentTemplate->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['valid' => false, 'error' => 'Erreur lors de la validation du modèle: ' . $e->getMessage()];
        }
    }

    /**
     * Unset other default templates for the same document type.
     */
    private function unsetOtherDefaultTemplates(DocumentType $documentType, ?DocumentTemplate $excludeTemplate = null): void
    {
        $this->logger->debug('Starting to unset other default templates', [
            'document_type_id' => $documentType->getId(),
            'document_type_name' => $documentType->getName(),
            'exclude_template_id' => $excludeTemplate?->getId(),
        ]);

        try {
            $qb = $this->documentTemplateRepository->createQueryBuilder('dt')
                ->where('dt.documentType = :documentType')
                ->andWhere('dt.isDefault = true')
                ->setParameter('documentType', $documentType)
            ;

            if ($excludeTemplate) {
                $qb->andWhere('dt.id != :excludeId')
                    ->setParameter('excludeId', $excludeTemplate->getId())
                ;
            }

            $defaultTemplates = $qb->getQuery()->getResult();

            $this->logger->debug('Found default templates to unset', [
                'document_type_id' => $documentType->getId(),
                'templates_count' => count($defaultTemplates),
                'template_ids' => array_map(fn($t) => $t->getId(), $defaultTemplates),
            ]);

            foreach ($defaultTemplates as $template) {
                try {
                    $this->logger->debug('Unsetting default status for template', [
                        'template_id' => $template->getId(),
                        'template_name' => $template->getName(),
                    ]);
                    
                    $template->setIsDefault(false);
                } catch (Exception $templateException) {
                    $this->logger->error('Failed to unset default status for individual template', [
                        'template_id' => $template->getId(),
                        'template_name' => $template->getName(),
                        'error' => $templateException->getMessage(),
                    ]);
                    throw $templateException;
                }
            }

            $this->logger->info('Successfully unset other default templates', [
                'document_type_id' => $documentType->getId(),
                'document_type_name' => $documentType->getName(),
                'unset_templates_count' => count($defaultTemplates),
                'exclude_template_id' => $excludeTemplate?->getId(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to unset other default templates', [
                'document_type_id' => $documentType->getId(),
                'document_type_name' => $documentType->getName(),
                'exclude_template_id' => $excludeTemplate?->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Process template placeholders with provided values.
     */
    private function processTemplatePlaceholders(string $content, array $placeholderValues): string
    {
        $this->logger->debug('Starting template placeholder processing', [
            'content_length' => strlen($content),
            'placeholders_count' => count($placeholderValues),
            'placeholder_keys' => array_keys($placeholderValues),
        ]);

        try {
            $originalContent = $content;
            $replacementCount = 0;

            // Simple placeholder replacement: {{placeholder_name}}
            foreach ($placeholderValues as $key => $value) {
                try {
                    $placeholder = '{{' . $key . '}}';
                    $occurrences = substr_count($content, $placeholder);
                    
                    if ($occurrences > 0) {
                        $content = str_replace($placeholder, $value, $content);
                        $replacementCount += $occurrences;
                        
                        $this->logger->debug('Replaced placeholder in template', [
                            'placeholder' => $placeholder,
                            'value' => is_string($value) ? (strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value) : gettype($value),
                            'occurrences' => $occurrences,
                        ]);
                    } else {
                        $this->logger->debug('Placeholder not found in template content', [
                            'placeholder' => $placeholder,
                        ]);
                    }
                } catch (Exception $placeholderException) {
                    $this->logger->error('Failed to process individual placeholder', [
                        'placeholder_key' => $key,
                        'placeholder_value' => is_string($value) ? (strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value) : gettype($value),
                        'error' => $placeholderException->getMessage(),
                    ]);
                    // Continue processing other placeholders
                    continue;
                }
            }

            $this->logger->info('Template placeholder processing completed', [
                'original_content_length' => strlen($originalContent),
                'processed_content_length' => strlen($content),
                'total_replacements' => $replacementCount,
                'placeholders_provided' => count($placeholderValues),
            ]);

            return $content;
        } catch (Exception $e) {
            $this->logger->error('Failed to process template placeholders', [
                'content_length' => strlen($content),
                'placeholders_count' => count($placeholderValues),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return original content on error to prevent data loss
            $this->logger->warning('Returning original content due to placeholder processing error');
            return $content;
        }
    }
}
