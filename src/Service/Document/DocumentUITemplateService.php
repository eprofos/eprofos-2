<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\Document\DocumentType;
use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentUITemplate;
use App\Repository\Document\DocumentTypeRepository;
use App\Repository\Document\DocumentUIComponentRepository;
use App\Repository\Document\DocumentUITemplateRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Document UI Template Service.
 *
 * Handles business logic for document UI template management:
 * - Template CRUD operations
 * - Component management
 * - Template rendering
 * - HTML/CSS generation
 * - PDF generation configuration
 */
class DocumentUITemplateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentUITemplateRepository $uiTemplateRepository,
        private DocumentUIComponentRepository $componentRepository,
        private DocumentTypeRepository $documentTypeRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Get all UI templates with statistics.
     */
    public function getTemplatesWithStats(): array
    {
        $this->logger->info('Starting getTemplatesWithStats operation');
        
        try {
            $this->logger->debug('Fetching templates with stats from repository');
            $templates = $this->uiTemplateRepository->findWithStats();
            $this->logger->debug('Retrieved templates from repository', [
                'template_count' => count($templates),
            ]);

            $result = [];

            foreach ($templates as $index => $template) {
                try {
                    $data = $template[0] ?? $template;
                    $componentCount = $template['componentCount'] ?? 0;
                    
                    $templateId = $data->getId();
                    $templateName = $data->getName();
                    
                    $this->logger->debug('Processing template statistics', [
                        'template_id' => $templateId,
                        'template_name' => $templateName,
                        'component_count' => $componentCount,
                        'usage_count' => $data->getUsageCount(),
                        'is_default' => $data->isDefault(),
                        'is_global' => $data->isGlobal(),
                        'document_type' => $data->getDocumentType()?->getName(),
                    ]);

                    $result[] = [
                        'template' => $data,
                        'stats' => [
                            'component_count' => $componentCount,
                            'usage_count' => $data->getUsageCount(),
                            'is_default' => $data->isDefault(),
                            'is_global' => $data->isGlobal(),
                            'type_name' => $data->getDocumentType()?->getName() ?? 'Global',
                        ],
                    ];
                } catch (Exception $e) {
                    $this->logger->error('Error processing template statistics', [
                        'template_index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue processing other templates
                }
            }

            $this->logger->info('Successfully completed getTemplatesWithStats operation', [
                'processed_templates' => count($result),
                'total_templates' => count($templates),
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Critical error in getTemplatesWithStats operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty array as fallback
            return [];
        }
    }

    /**
     * Create a new UI template.
     */
    public function createUITemplate(DocumentUITemplate $uiTemplate): array
    {
        $templateName = $uiTemplate->getName();
        $documentTypeName = $uiTemplate->getDocumentType()?->getName() ?? 'Global';
        
        $this->logger->info('Starting createUITemplate operation', [
            'template_name' => $templateName,
            'document_type' => $documentTypeName,
            'is_default' => $uiTemplate->isDefault(),
            'is_global' => $uiTemplate->isGlobal(),
            'orientation' => $uiTemplate->getOrientation(),
            'paper_size' => $uiTemplate->getPaperSize(),
        ]);

        try {
            $this->logger->debug('Beginning database transaction for template creation');
            $this->entityManager->beginTransaction();

            // Validate template
            $this->logger->debug('Validating template configuration');
            $validationErrors = $uiTemplate->validateConfiguration();
            if (!empty($validationErrors)) {
                $this->logger->warning('Template validation failed', [
                    'template_name' => $templateName,
                    'validation_errors' => $validationErrors,
                ]);

                $this->entityManager->rollback();
                return [
                    'success' => false,
                    'error' => 'Configuration invalide: ' . implode(', ', $validationErrors),
                ];
            }
            $this->logger->debug('Template configuration validation successful');

            // Handle default template logic
            if ($uiTemplate->isDefault()) {
                $this->logger->debug('Processing default template logic', [
                    'template_name' => $templateName,
                    'document_type' => $documentTypeName,
                ]);
                
                try {
                    $this->handleDefaultTemplateChange($uiTemplate);
                    $this->logger->debug('Default template logic processed successfully');
                } catch (Exception $e) {
                    $this->logger->error('Error in default template logic', [
                        'template_name' => $templateName,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }

            // Set sort order if not set
            if ($uiTemplate->getSortOrder() === 0) {
                $this->logger->debug('Setting sort order for template');
                try {
                    $nextSortOrder = $this->getNextSortOrder();
                    $uiTemplate->setSortOrder($nextSortOrder);
                    $this->logger->debug('Sort order set', [
                        'template_name' => $templateName,
                        'sort_order' => $nextSortOrder,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error setting sort order', [
                        'template_name' => $templateName,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $this->logger->debug('Persisting template to database');
            $this->entityManager->persist($uiTemplate);
            
            $this->logger->debug('Flushing entity manager changes');
            $this->entityManager->flush();
            
            $this->logger->debug('Committing database transaction');
            $this->entityManager->commit();

            $templateId = $uiTemplate->getId();
            $this->logger->info('UI template created successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'document_type' => $documentTypeName,
                'sort_order' => $uiTemplate->getSortOrder(),
                'is_default' => $uiTemplate->isDefault(),
                'is_global' => $uiTemplate->isGlobal(),
            ]);

            return [
                'success' => true,
                'template' => $uiTemplate,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error creating UI template', [
                'template_name' => $templateName,
                'document_type' => $documentTypeName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            try {
                $this->logger->debug('Rolling back database transaction due to error');
                $this->entityManager->rollback();
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction', [
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'error' => 'Erreur lors de la création du modèle UI: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update a UI template.
     */
    public function updateUITemplate(DocumentUITemplate $uiTemplate): array
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();
        $documentTypeName = $uiTemplate->getDocumentType()?->getName() ?? 'Global';
        
        $this->logger->info('Starting updateUITemplate operation', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'document_type' => $documentTypeName,
            'is_default' => $uiTemplate->isDefault(),
            'is_global' => $uiTemplate->isGlobal(),
        ]);

        try {
            $this->logger->debug('Beginning database transaction for template update');
            $this->entityManager->beginTransaction();

            // Validate template
            $this->logger->debug('Validating template configuration for update');
            $validationErrors = $uiTemplate->validateConfiguration();
            if (!empty($validationErrors)) {
                $this->logger->warning('Template validation failed during update', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'validation_errors' => $validationErrors,
                ]);

                $this->entityManager->rollback();
                return [
                    'success' => false,
                    'error' => 'Configuration invalide: ' . implode(', ', $validationErrors),
                ];
            }
            $this->logger->debug('Template configuration validation successful for update');

            // Handle default template logic
            if ($uiTemplate->isDefault()) {
                $this->logger->debug('Processing default template logic for update', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'document_type' => $documentTypeName,
                ]);
                
                try {
                    $this->handleDefaultTemplateChange($uiTemplate);
                    $this->logger->debug('Default template logic processed successfully for update');
                } catch (Exception $e) {
                    $this->logger->error('Error in default template logic during update', [
                        'template_id' => $templateId,
                        'template_name' => $templateName,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }

            $this->logger->debug('Setting updated timestamp');
            $updateTime = new DateTimeImmutable();
            $uiTemplate->setUpdatedAt($updateTime);
            
            $this->logger->debug('Flushing entity manager changes for update');
            $this->entityManager->flush();
            
            $this->logger->debug('Committing database transaction for update');
            $this->entityManager->commit();

            $this->logger->info('UI template updated successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'document_type' => $documentTypeName,
                'updated_at' => $updateTime->format('Y-m-d H:i:s'),
                'is_default' => $uiTemplate->isDefault(),
                'is_global' => $uiTemplate->isGlobal(),
            ]);

            return [
                'success' => true,
                'template' => $uiTemplate,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error updating UI template', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'document_type' => $documentTypeName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            try {
                $this->logger->debug('Rolling back database transaction due to update error');
                $this->entityManager->rollback();
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction during update', [
                    'template_id' => $templateId,
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'error' => 'Erreur lors de la mise à jour du modèle UI: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a UI template.
     */
    public function deleteUITemplate(DocumentUITemplate $uiTemplate): array
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();
        $usageCount = $uiTemplate->getUsageCount();
        $documentTypeName = $uiTemplate->getDocumentType()?->getName() ?? 'Global';
        
        $this->logger->info('Starting deleteUITemplate operation', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'document_type' => $documentTypeName,
            'usage_count' => $usageCount,
            'is_default' => $uiTemplate->isDefault(),
            'is_global' => $uiTemplate->isGlobal(),
        ]);

        try {
            $this->logger->debug('Beginning database transaction for template deletion');
            $this->entityManager->beginTransaction();

            // Check if template is being used
            $this->logger->debug('Checking if template is in use before deletion');
            if ($usageCount > 0) {
                $this->logger->warning('Template deletion blocked - template is in use', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'usage_count' => $usageCount,
                ]);

                $this->entityManager->rollback();
                return [
                    'success' => false,
                    'error' => 'Le modèle UI ne peut pas être supprimé car il est utilisé.',
                ];
            }
            $this->logger->debug('Template usage check passed - template is not in use');

            // Store template info before deletion
            $templateInfo = [
                'id' => $templateId,
                'name' => $templateName,
                'document_type' => $documentTypeName,
                'is_default' => $uiTemplate->isDefault(),
                'is_global' => $uiTemplate->isGlobal(),
                'sort_order' => $uiTemplate->getSortOrder(),
                'created_at' => $uiTemplate->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];

            $this->logger->debug('Removing template from entity manager');
            $this->entityManager->remove($uiTemplate);
            
            $this->logger->debug('Flushing entity manager changes for deletion');
            $this->entityManager->flush();
            
            $this->logger->debug('Committing database transaction for deletion');
            $this->entityManager->commit();

            $this->logger->info('UI template deleted successfully', $templateInfo);

            return [
                'success' => true,
                'message' => 'Modèle UI supprimé avec succès.',
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error deleting UI template', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'document_type' => $documentTypeName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            try {
                $this->logger->debug('Rolling back database transaction due to deletion error');
                $this->entityManager->rollback();
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction during deletion', [
                    'template_id' => $templateId,
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'error' => 'Erreur lors de la suppression du modèle UI: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Toggle UI template active status.
     */
    public function toggleActiveStatus(DocumentUITemplate $uiTemplate): array
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();
        $currentStatus = $uiTemplate->isActive();
        $newStatus = !$currentStatus;
        
        $this->logger->info('Starting toggleActiveStatus operation', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'current_status' => $currentStatus ? 'active' : 'inactive',
            'new_status' => $newStatus ? 'active' : 'inactive',
        ]);

        try {
            $this->logger->debug('Setting new active status', [
                'template_id' => $templateId,
                'old_status' => $currentStatus,
                'new_status' => $newStatus,
            ]);
            
            $uiTemplate->setIsActive($newStatus);
            
            $this->logger->debug('Setting updated timestamp for status change');
            $updateTime = new DateTimeImmutable();
            $uiTemplate->setUpdatedAt($updateTime);

            $this->logger->debug('Persisting status change to database');
            $this->entityManager->flush();

            $statusText = $newStatus ? 'activé' : 'désactivé';
            
            $this->logger->info('UI template status toggled successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'previous_status' => $currentStatus ? 'active' : 'inactive',
                'new_status' => $newStatus ? 'active' : 'inactive',
                'updated_at' => $updateTime->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => "Modèle UI {$statusText} avec succès.",
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error toggling UI template status', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'attempted_status' => $newStatus ? 'active' : 'inactive',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors du changement de statut du modèle UI.',
            ];
        }
    }

    /**
     * Duplicate a UI template.
     */
    public function duplicateUITemplate(DocumentUITemplate $uiTemplate): array
    {
        $originalId = $uiTemplate->getId();
        $originalName = $uiTemplate->getName();
        $originalSlug = $uiTemplate->getSlug();
        $documentTypeName = $uiTemplate->getDocumentType()?->getName() ?? 'Global';
        
        $this->logger->info('Starting duplicateUITemplate operation', [
            'original_template_id' => $originalId,
            'original_template_name' => $originalName,
            'original_slug' => $originalSlug,
            'document_type' => $documentTypeName,
        ]);

        try {
            $this->logger->debug('Beginning database transaction for template duplication');
            $this->entityManager->beginTransaction();

            // Generate unique name and slug
            $baseName = $originalName . ' (Copie)';
            $baseSlug = $originalSlug . '-copie';
            
            $this->logger->debug('Generating unique name and slug for duplicate', [
                'base_name' => $baseName,
                'base_slug' => $baseSlug,
            ]);

            try {
                $newName = $this->generateUniqueName($baseName);
                $this->logger->debug('Generated unique name', [
                    'base_name' => $baseName,
                    'unique_name' => $newName,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error generating unique name', [
                    'base_name' => $baseName,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            try {
                $newSlug = $this->generateUniqueSlug($baseSlug);
                $this->logger->debug('Generated unique slug', [
                    'base_slug' => $baseSlug,
                    'unique_slug' => $newSlug,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error generating unique slug', [
                    'base_slug' => $baseSlug,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Clone template
            $this->logger->debug('Cloning template with new name and slug');
            try {
                $newTemplate = $uiTemplate->cloneTemplate($newName, $newSlug);
                $this->logger->debug('Template cloned successfully', [
                    'new_name' => $newName,
                    'new_slug' => $newSlug,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error cloning template', [
                    'original_id' => $originalId,
                    'new_name' => $newName,
                    'new_slug' => $newSlug,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Set sort order
            try {
                $nextSortOrder = $this->getNextSortOrder();
                $newTemplate->setSortOrder($nextSortOrder);
                $this->logger->debug('Set sort order for duplicated template', [
                    'sort_order' => $nextSortOrder,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error setting sort order for duplicated template', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->logger->debug('Persisting duplicated template');
            $this->entityManager->persist($newTemplate);
            
            $this->logger->debug('Flushing entity manager changes for duplication');
            $this->entityManager->flush();
            
            $this->logger->debug('Committing database transaction for duplication');
            $this->entityManager->commit();

            $newTemplateId = $newTemplate->getId();
            $this->logger->info('UI template duplicated successfully', [
                'original_id' => $originalId,
                'original_name' => $originalName,
                'new_id' => $newTemplateId,
                'new_name' => $newName,
                'new_slug' => $newSlug,
                'sort_order' => $newTemplate->getSortOrder(),
                'document_type' => $documentTypeName,
            ]);

            return [
                'success' => true,
                'template' => $newTemplate,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error duplicating UI template', [
                'original_id' => $originalId,
                'original_name' => $originalName,
                'document_type' => $documentTypeName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            try {
                $this->logger->debug('Rolling back database transaction due to duplication error');
                $this->entityManager->rollback();
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback transaction during duplication', [
                    'original_id' => $originalId,
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'error' => 'Erreur lors de la duplication du modèle UI: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Add component to UI template.
     */
    public function addComponent(DocumentUITemplate $uiTemplate, DocumentUIComponent $component): array
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();
        $componentName = $component->getName();
        $componentType = $component->getType();
        $componentZone = $component->getZone();
        
        $this->logger->info('Starting addComponent operation', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'component_name' => $componentName,
            'component_type' => $componentType,
            'component_zone' => $componentZone,
        ]);

        try {
            $this->logger->debug('Setting component template association');
            $component->setUiTemplate($uiTemplate);

            // Get next sort order for component
            $this->logger->debug('Getting next sort order for component');
            try {
                $nextSortOrder = $this->componentRepository->getNextSortOrder($uiTemplate);
                $component->setSortOrder($nextSortOrder);
                $this->logger->debug('Component sort order set', [
                    'template_id' => $templateId,
                    'component_name' => $componentName,
                    'sort_order' => $nextSortOrder,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error getting next sort order for component', [
                    'template_id' => $templateId,
                    'component_name' => $componentName,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->logger->debug('Persisting component to database');
            $this->entityManager->persist($component);
            
            $this->logger->debug('Flushing entity manager changes for component addition');
            $this->entityManager->flush();

            $componentId = $component->getId();
            $this->logger->info('Component added to UI template successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'component_id' => $componentId,
                'component_name' => $componentName,
                'component_type' => $componentType,
                'component_zone' => $componentZone,
                'sort_order' => $component->getSortOrder(),
                'is_required' => $component->isRequired(),
            ]);

            return [
                'success' => true,
                'component' => $component,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error adding component to UI template', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'component_name' => $componentName,
                'component_type' => $componentType,
                'component_zone' => $componentZone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de l\'ajout du composant: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update component sort orders.
     */
    public function updateComponentSortOrders(DocumentUITemplate $uiTemplate, array $componentIds): array
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();
        $componentCount = count($componentIds);
        
        $this->logger->info('Starting updateComponentSortOrders operation', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'component_count' => $componentCount,
            'component_ids' => $componentIds,
        ]);

        try {
            $this->logger->debug('Validating component IDs array');
            if (empty($componentIds)) {
                $this->logger->warning('Empty component IDs array provided', [
                    'template_id' => $templateId,
                ]);
                return [
                    'success' => false,
                    'error' => 'Aucun composant fourni pour la mise à jour de l\'ordre.',
                ];
            }

            $this->logger->debug('Updating component sort orders via repository');
            $this->componentRepository->updateSortOrders($uiTemplate, $componentIds);
            
            $this->logger->info('Component sort orders updated successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'updated_components' => $componentCount,
                'component_ids' => $componentIds,
            ]);

            return [
                'success' => true,
                'message' => 'Ordre des composants mis à jour.',
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error updating component sort orders', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'component_count' => $componentCount,
                'component_ids' => $componentIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la mise à jour de l\'ordre des composants.',
            ];
        }
    }

    /**
     * Render template with data.
     */
    public function renderTemplate(DocumentUITemplate $uiTemplate, array $data = []): array
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();
        $dataKeys = array_keys($data);
        
        $this->logger->info('Starting renderTemplate operation', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'data_keys' => $dataKeys,
            'data_count' => count($data),
        ]);

        try {
            // Increment usage count
            $this->logger->debug('Incrementing template usage count');
            try {
                $previousUsageCount = $uiTemplate->getUsageCount();
                $uiTemplate->incrementUsage();
                $this->entityManager->flush();
                $newUsageCount = $uiTemplate->getUsageCount();
                $this->logger->debug('Template usage count incremented', [
                    'template_id' => $templateId,
                    'previous_count' => $previousUsageCount,
                    'new_count' => $newUsageCount,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error incrementing template usage count', [
                    'template_id' => $templateId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Render components first
            $this->logger->debug('Fetching active components for template');
            try {
                $components = $this->componentRepository->findActiveByTemplate($uiTemplate);
                $componentCount = count($components);
                $this->logger->debug('Active components retrieved', [
                    'template_id' => $templateId,
                    'component_count' => $componentCount,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error fetching active components', [
                    'template_id' => $templateId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $renderedComponents = [];
            $zoneContents = [
                'header' => '',
                'body' => '',
                'footer' => '',
            ];

            $this->logger->debug('Processing components for rendering');
            foreach ($components as $index => $component) {
                try {
                    $componentId = $component->getId();
                    $componentName = $component->getName();
                    $componentZone = $component->getZone();
                    
                    $this->logger->debug('Processing component', [
                        'template_id' => $templateId,
                        'component_id' => $componentId,
                        'component_name' => $componentName,
                        'component_zone' => $componentZone,
                        'component_index' => $index,
                    ]);

                    if ($component->shouldDisplay($data)) {
                        $this->logger->debug('Component should display - rendering HTML', [
                            'component_id' => $componentId,
                            'component_name' => $componentName,
                        ]);
                        
                        $componentHtml = $component->renderHtml($data);
                        $htmlLength = strlen($componentHtml);
                        
                        $renderedComponents[$componentZone][] = [
                            'component' => $component,
                            'html' => $componentHtml,
                        ];
                        $zoneContents[$componentZone] .= $componentHtml . "\n";
                        
                        $this->logger->debug('Component rendered successfully', [
                            'component_id' => $componentId,
                            'component_name' => $componentName,
                            'component_zone' => $componentZone,
                            'html_length' => $htmlLength,
                        ]);
                    } else {
                        $this->logger->debug('Component should not display - skipping', [
                            'component_id' => $componentId,
                            'component_name' => $componentName,
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Error processing component during rendering', [
                        'template_id' => $templateId,
                        'component_index' => $index,
                        'component_id' => $component->getId() ?? 'unknown',
                        'component_name' => $component->getName() ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue processing other components
                }
            }

            // Add zone contents to data for template rendering
            $this->logger->debug('Preparing template data with zone contents', [
                'template_id' => $templateId,
                'header_length' => strlen($zoneContents['header']),
                'body_length' => strlen($zoneContents['body']),
                'footer_length' => strlen($zoneContents['footer']),
            ]);

            $templateData = array_merge($data, [
                'header_content' => $zoneContents['header'],
                'content' => $zoneContents['body'],
                'footer_content' => $zoneContents['footer'],
            ]);

            // Generate HTML with component content
            $this->logger->debug('Rendering template HTML');
            try {
                $html = $uiTemplate->renderHtml($templateData);
                $htmlLength = strlen($html);
                $this->logger->debug('Template HTML rendered successfully', [
                    'template_id' => $templateId,
                    'html_length' => $htmlLength,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error rendering template HTML', [
                    'template_id' => $templateId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Generate CSS
            $this->logger->debug('Rendering template CSS');
            try {
                $css = $uiTemplate->renderCss();
                $cssLength = strlen($css);
                $this->logger->debug('Template CSS rendered successfully', [
                    'template_id' => $templateId,
                    'css_length' => $cssLength,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error rendering template CSS', [
                    'template_id' => $templateId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Get page configuration
            $this->logger->debug('Getting template page configuration');
            try {
                $pageConfig = $uiTemplate->getPageConfig();
                $this->logger->debug('Template page configuration retrieved', [
                    'template_id' => $templateId,
                    'page_config_keys' => is_array($pageConfig) ? array_keys($pageConfig) : 'not_array',
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error getting template page configuration', [
                    'template_id' => $templateId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->logger->info('Template rendered successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'components_processed' => count($components),
                'components_rendered' => array_sum(array_map('count', $renderedComponents)),
                'html_length' => strlen($html),
                'css_length' => strlen($css),
                'usage_count' => $uiTemplate->getUsageCount(),
            ]);

            return [
                'success' => true,
                'html' => $html,
                'css' => $css,
                'page_config' => $pageConfig,
                'components' => $renderedComponents,
                'template' => $uiTemplate,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error rendering UI template', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'data_keys' => $dataKeys,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors du rendu du modèle UI: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get templates for document type.
     */
    public function getTemplatesForType(?DocumentType $documentType): array
    {
        $documentTypeName = $documentType?->getName() ?? 'Global';
        $documentTypeId = $documentType?->getId() ?? null;
        
        $this->logger->debug('Starting getTemplatesForType operation', [
            'document_type_id' => $documentTypeId,
            'document_type_name' => $documentTypeName,
        ]);

        try {
            $templates = $this->uiTemplateRepository->findByDocumentType($documentType);
            $templateCount = count($templates);
            
            $this->logger->debug('Templates retrieved for document type', [
                'document_type_id' => $documentTypeId,
                'document_type_name' => $documentTypeName,
                'template_count' => $templateCount,
                'template_ids' => array_map(fn($t) => $t->getId(), $templates),
            ]);

            return $templates;
        } catch (Exception $e) {
            $this->logger->error('Error getting templates for document type', [
                'document_type_id' => $documentTypeId,
                'document_type_name' => $documentTypeName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty array as fallback
            return [];
        }
    }

    /**
     * Get default template for document type.
     */
    public function getDefaultTemplateForType(?DocumentType $documentType): ?DocumentUITemplate
    {
        $documentTypeName = $documentType?->getName() ?? 'Global';
        $documentTypeId = $documentType?->getId() ?? null;
        
        $this->logger->debug('Starting getDefaultTemplateForType operation', [
            'document_type_id' => $documentTypeId,
            'document_type_name' => $documentTypeName,
        ]);

        try {
            $defaultTemplate = $this->uiTemplateRepository->findDefaultForType($documentType);
            
            if ($defaultTemplate) {
                $this->logger->debug('Default template found for document type', [
                    'document_type_id' => $documentTypeId,
                    'document_type_name' => $documentTypeName,
                    'template_id' => $defaultTemplate->getId(),
                    'template_name' => $defaultTemplate->getName(),
                ]);
            } else {
                $this->logger->debug('No default template found for document type', [
                    'document_type_id' => $documentTypeId,
                    'document_type_name' => $documentTypeName,
                ]);
            }

            return $defaultTemplate;
        } catch (Exception $e) {
            $this->logger->error('Error getting default template for document type', [
                'document_type_id' => $documentTypeId,
                'document_type_name' => $documentTypeName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return null as fallback
            return null;
        }
    }

    /**
     * Get document type by ID.
     */
    public function getDocumentTypeById(int $id): ?DocumentType
    {
        $this->logger->debug('Starting getDocumentTypeById operation', [
            'document_type_id' => $id,
        ]);

        try {
            $documentType = $this->documentTypeRepository->find($id);
            
            if ($documentType) {
                $this->logger->debug('Document type found by ID', [
                    'document_type_id' => $id,
                    'document_type_name' => $documentType->getName(),
                ]);
            } else {
                $this->logger->warning('Document type not found by ID', [
                    'document_type_id' => $id,
                ]);
            }

            return $documentType;
        } catch (Exception $e) {
            $this->logger->error('Error getting document type by ID', [
                'document_type_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return null as fallback
            return null;
        }
    }

    /**
     * Get next sort order.
     */
    public function getNextSortOrder(): int
    {
        $this->logger->debug('Starting getNextSortOrder operation');

        try {
            $nextSortOrder = $this->uiTemplateRepository->getNextSortOrder();
            
            $this->logger->debug('Next sort order calculated', [
                'next_sort_order' => $nextSortOrder,
            ]);

            return $nextSortOrder;
        } catch (Exception $e) {
            $this->logger->error('Error getting next sort order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return default value as fallback
            return 1;
        }
    }

    /**
     * Export template configuration.
     */
    public function exportTemplate(DocumentUITemplate $uiTemplate): array
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();
        
        $this->logger->info('Starting exportTemplate operation', [
            'template_id' => $templateId,
            'template_name' => $templateName,
        ]);

        try {
            $this->logger->debug('Fetching components for template export');
            $components = $this->componentRepository->findByTemplate($uiTemplate);
            $componentCount = count($components);
            
            $this->logger->debug('Components retrieved for export', [
                'template_id' => $templateId,
                'component_count' => $componentCount,
                'component_ids' => array_map(fn($c) => $c->getId(), $components),
            ]);

            $this->logger->debug('Building template export configuration');
            $templateConfig = [
                'name' => $uiTemplate->getName(),
                'description' => $uiTemplate->getDescription(),
                'html_template' => $uiTemplate->getHtmlTemplate(),
                'css_styles' => $uiTemplate->getCssStyles(),
                'layout_configuration' => $uiTemplate->getLayoutConfiguration(),
                'page_settings' => $uiTemplate->getPageSettings(),
                'header_footer_config' => $uiTemplate->getHeaderFooterConfig(),
                'component_styles' => $uiTemplate->getComponentStyles(),
                'variables' => $uiTemplate->getVariables(),
                'orientation' => $uiTemplate->getOrientation(),
                'paper_size' => $uiTemplate->getPaperSize(),
                'margins' => $uiTemplate->getMargins(),
                'is_global' => $uiTemplate->isGlobal(),
            ];

            $this->logger->debug('Building components export configuration');
            $componentsConfig = array_map(static function ($component) {
                return [
                    'name' => $component->getName(),
                    'type' => $component->getType(),
                    'zone' => $component->getZone(),
                    'content' => $component->getContent(),
                    'html_content' => $component->getHtmlContent(),
                    'style_config' => $component->getStyleConfig(),
                    'position_config' => $component->getPositionConfig(),
                    'data_binding' => $component->getDataBinding(),
                    'conditional_display' => $component->getConditionalDisplay(),
                    'sort_order' => $component->getSortOrder(),
                    'css_class' => $component->getCssClass(),
                    'element_id' => $component->getElementId(),
                    'is_required' => $component->isRequired(),
                ];
            }, $components);

            $exportData = [
                'template' => $templateConfig,
                'components' => $componentsConfig,
            ];

            $this->logger->info('Template exported successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'exported_components' => $componentCount,
                'export_size' => strlen(json_encode($exportData)),
            ]);

            return $exportData;
        } catch (Exception $e) {
            $this->logger->error('Critical error exporting template', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            // Return empty configuration as fallback
            return [
                'template' => [],
                'components' => [],
            ];
        }
    }

    /**
     * Import template configuration.
     */
    public function importTemplate(array $config, ?DocumentType $documentType = null): array
    {
        $templateName = $config['template']['name'] ?? 'Unknown Template';
        $documentTypeName = $documentType?->getName() ?? 'Global';
        $componentCount = count($config['components'] ?? []);
        
        $this->logger->info('Starting importTemplate operation', [
            'template_name' => $templateName,
            'document_type' => $documentTypeName,
            'component_count' => $componentCount,
            'config_size' => strlen(json_encode($config)),
        ]);

        try {
            $this->logger->debug('Beginning database transaction for template import');
            $this->entityManager->beginTransaction();

            // Validate configuration structure
            $this->logger->debug('Validating import configuration structure');
            if (!isset($config['template']) || !is_array($config['template'])) {
                $this->logger->error('Invalid import configuration - missing or invalid template section');
                $this->entityManager->rollback();
                return [
                    'success' => false,
                    'error' => 'Configuration d\'importation invalide - section template manquante ou invalide.',
                ];
            }

            // Create template
            $this->logger->debug('Creating new template from import configuration');
            try {
                $template = new DocumentUITemplate();
                $template->setName($config['template']['name'])
                    ->setDescription($config['template']['description'] ?? null)
                    ->setDocumentType($documentType)
                    ->setHtmlTemplate($config['template']['html_template'] ?? null)
                    ->setCssStyles($config['template']['css_styles'] ?? null)
                    ->setLayoutConfiguration($config['template']['layout_configuration'] ?? null)
                    ->setPageSettings($config['template']['page_settings'] ?? null)
                    ->setHeaderFooterConfig($config['template']['header_footer_config'] ?? null)
                    ->setComponentStyles($config['template']['component_styles'] ?? null)
                    ->setVariables($config['template']['variables'] ?? null)
                    ->setOrientation($config['template']['orientation'] ?? 'portrait')
                    ->setPaperSize($config['template']['paper_size'] ?? 'A4')
                    ->setMargins($config['template']['margins'] ?? null)
                    ->setIsGlobal($config['template']['is_global'] ?? false)
                    ->setSortOrder($this->getNextSortOrder())
                ;

                $this->logger->debug('Template entity created successfully from import', [
                    'template_name' => $templateName,
                    'orientation' => $template->getOrientation(),
                    'paper_size' => $template->getPaperSize(),
                    'is_global' => $template->isGlobal(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error creating template entity from import', [
                    'template_name' => $templateName,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->logger->debug('Persisting imported template');
            $this->entityManager->persist($template);
            $this->entityManager->flush();

            $templateId = $template->getId();
            $this->logger->debug('Imported template persisted successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
            ]);

            // Create components
            $this->logger->debug('Processing components for import');
            $createdComponents = [];
            foreach ($config['components'] ?? [] as $index => $componentData) {
                try {
                    $componentName = $componentData['name'] ?? "Component {$index}";
                    $componentType = $componentData['type'] ?? 'text';
                    $componentZone = $componentData['zone'] ?? 'body';
                    
                    $this->logger->debug('Creating component from import data', [
                        'template_id' => $templateId,
                        'component_index' => $index,
                        'component_name' => $componentName,
                        'component_type' => $componentType,
                        'component_zone' => $componentZone,
                    ]);

                    $component = new DocumentUIComponent();
                    $component->setName($componentName)
                        ->setType($componentType)
                        ->setZone($componentZone)
                        ->setContent($componentData['content'] ?? null)
                        ->setHtmlContent($componentData['html_content'] ?? null)
                        ->setStyleConfig($componentData['style_config'] ?? null)
                        ->setPositionConfig($componentData['position_config'] ?? null)
                        ->setDataBinding($componentData['data_binding'] ?? null)
                        ->setConditionalDisplay($componentData['conditional_display'] ?? null)
                        ->setSortOrder($componentData['sort_order'] ?? 0)
                        ->setCssClass($componentData['css_class'] ?? null)
                        ->setElementId($componentData['element_id'] ?? null)
                        ->setIsRequired($componentData['is_required'] ?? false)
                        ->setUiTemplate($template)
                    ;

                    $this->entityManager->persist($component);
                    $createdComponents[] = $component;
                    
                    $this->logger->debug('Component created successfully from import', [
                        'template_id' => $templateId,
                        'component_name' => $componentName,
                        'component_type' => $componentType,
                        'component_zone' => $componentZone,
                        'sort_order' => $component->getSortOrder(),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error creating component from import data', [
                        'template_id' => $templateId,
                        'component_index' => $index,
                        'component_data' => $componentData,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $this->logger->debug('Flushing all imported entities');
            $this->entityManager->flush();
            
            $this->logger->debug('Committing import transaction');
            $this->entityManager->commit();

            $this->logger->info('Template imported successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'document_type' => $documentTypeName,
                'imported_components' => count($createdComponents),
                'sort_order' => $template->getSortOrder(),
            ]);

            return [
                'success' => true,
                'template' => $template,
            ];
        } catch (Exception $e) {
            $this->logger->error('Critical error importing UI template', [
                'template_name' => $templateName,
                'document_type' => $documentTypeName,
                'component_count' => $componentCount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            try {
                $this->logger->debug('Rolling back import transaction due to error');
                $this->entityManager->rollback();
            } catch (Exception $rollbackException) {
                $this->logger->critical('Failed to rollback import transaction', [
                    'template_name' => $templateName,
                    'original_error' => $e->getMessage(),
                    'rollback_error' => $rollbackException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'error' => 'Erreur lors de l\'importation du modèle UI: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle default template change.
     */
    private function handleDefaultTemplateChange(DocumentUITemplate $newDefaultTemplate): void
    {
        $templateId = $newDefaultTemplate->getId();
        $templateName = $newDefaultTemplate->getName();
        $documentType = $newDefaultTemplate->getDocumentType();
        $documentTypeName = $documentType?->getName() ?? 'Global';
        $isGlobal = $newDefaultTemplate->isGlobal();
        
        $this->logger->debug('Starting handleDefaultTemplateChange operation', [
            'new_default_template_id' => $templateId,
            'new_default_template_name' => $templateName,
            'document_type' => $documentTypeName,
            'is_global' => $isGlobal,
        ]);

        try {
            // Find existing default templates for the same document type
            $this->logger->debug('Finding existing default templates');
            $existingDefaults = $this->uiTemplateRepository->findBy([
                'documentType' => $documentType,
                'isDefault' => true,
                'isGlobal' => $isGlobal,
            ]);

            $existingDefaultCount = count($existingDefaults);
            $this->logger->debug('Found existing default templates', [
                'count' => $existingDefaultCount,
                'existing_template_ids' => array_map(fn($t) => $t->getId(), $existingDefaults),
            ]);

            // Remove default status from existing templates
            $updatedTemplates = [];
            foreach ($existingDefaults as $existing) {
                if ($existing->getId() !== $templateId) {
                    $existingId = $existing->getId();
                    $existingName = $existing->getName();
                    
                    $this->logger->debug('Removing default status from existing template', [
                        'existing_template_id' => $existingId,
                        'existing_template_name' => $existingName,
                        'new_default_template_id' => $templateId,
                    ]);
                    
                    $existing->setIsDefault(false);
                    $updatedTemplates[] = [
                        'id' => $existingId,
                        'name' => $existingName,
                    ];
                }
            }

            $this->logger->debug('Default template change processed successfully', [
                'new_default_template_id' => $templateId,
                'new_default_template_name' => $templateName,
                'document_type' => $documentTypeName,
                'updated_templates' => $updatedTemplates,
                'updated_count' => count($updatedTemplates),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in handleDefaultTemplateChange', [
                'new_default_template_id' => $templateId,
                'new_default_template_name' => $templateName,
                'document_type' => $documentTypeName,
                'is_global' => $isGlobal,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate unique name.
     */
    private function generateUniqueName(string $baseName): string
    {
        $this->logger->debug('Starting generateUniqueName operation', [
            'base_name' => $baseName,
        ]);

        try {
            $counter = 1;
            $name = $baseName;

            while (count($this->uiTemplateRepository->findSimilarByName($name)) > 0) {
                $name = $baseName . ' (' . $counter . ')';
                $counter++;
                
                $this->logger->debug('Name collision detected, trying new name', [
                    'attempted_name' => $name,
                    'counter' => $counter,
                ]);
                
                // Safety check to prevent infinite loop
                if ($counter > 1000) {
                    $this->logger->error('Too many name collision attempts', [
                        'base_name' => $baseName,
                        'counter' => $counter,
                    ]);
                    throw new Exception('Impossible de générer un nom unique après 1000 tentatives');
                }
            }

            $this->logger->debug('Unique name generated successfully', [
                'base_name' => $baseName,
                'unique_name' => $name,
                'attempts' => $counter,
            ]);

            return $name;
        } catch (Exception $e) {
            $this->logger->error('Error generating unique name', [
                'base_name' => $baseName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate unique slug.
     */
    private function generateUniqueSlug(string $baseSlug): string
    {
        $this->logger->debug('Starting generateUniqueSlug operation', [
            'base_slug' => $baseSlug,
        ]);

        try {
            $counter = 1;
            $slug = $baseSlug;

            while ($this->uiTemplateRepository->findBySlug($slug) !== null) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
                
                $this->logger->debug('Slug collision detected, trying new slug', [
                    'attempted_slug' => $slug,
                    'counter' => $counter,
                ]);
                
                // Safety check to prevent infinite loop
                if ($counter > 1000) {
                    $this->logger->error('Too many slug collision attempts', [
                        'base_slug' => $baseSlug,
                        'counter' => $counter,
                    ]);
                    throw new Exception('Impossible de générer un slug unique après 1000 tentatives');
                }
            }

            $this->logger->debug('Unique slug generated successfully', [
                'base_slug' => $baseSlug,
                'unique_slug' => $slug,
                'attempts' => $counter,
            ]);

            return $slug;
        } catch (Exception $e) {
            $this->logger->error('Error generating unique slug', [
                'base_slug' => $baseSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
