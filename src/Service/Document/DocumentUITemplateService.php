<?php

namespace App\Service\Document;

use App\Entity\Document\DocumentUITemplate;
use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentType;
use App\Repository\Document\DocumentUITemplateRepository;
use App\Repository\Document\DocumentUIComponentRepository;
use App\Repository\Document\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Document UI Template Service
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
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get all UI templates with statistics
     */
    public function getTemplatesWithStats(): array
    {
        $templates = $this->uiTemplateRepository->findWithStats();
        $result = [];

        foreach ($templates as $template) {
            $data = $template[0] ?? $template;
            $componentCount = $template['componentCount'] ?? 0;
            
            $result[] = [
                'template' => $data,
                'stats' => [
                    'component_count' => $componentCount,
                    'usage_count' => $data->getUsageCount(),
                    'is_default' => $data->isDefault(),
                    'is_global' => $data->isGlobal(),
                    'type_name' => $data->getDocumentType()?->getName() ?? 'Global',
                ]
            ];
        }

        return $result;
    }

    /**
     * Create a new UI template
     */
    public function createUITemplate(DocumentUITemplate $uiTemplate): array
    {
        try {
            $this->entityManager->beginTransaction();

            // Validate template
            $validationErrors = $uiTemplate->validateConfiguration();
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'error' => 'Configuration invalide: ' . implode(', ', $validationErrors)
                ];
            }

            // Handle default template logic
            if ($uiTemplate->isDefault()) {
                $this->handleDefaultTemplateChange($uiTemplate);
            }

            // Set sort order if not set
            if ($uiTemplate->getSortOrder() === 0) {
                $uiTemplate->setSortOrder($this->getNextSortOrder());
            }

            $this->entityManager->persist($uiTemplate);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('UI template created', [
                'template_id' => $uiTemplate->getId(),
                'name' => $uiTemplate->getName()
            ]);

            return [
                'success' => true,
                'template' => $uiTemplate
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error creating UI template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la création du modèle UI: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update a UI template
     */
    public function updateUITemplate(DocumentUITemplate $uiTemplate): array
    {
        try {
            $this->entityManager->beginTransaction();

            // Validate template
            $validationErrors = $uiTemplate->validateConfiguration();
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'error' => 'Configuration invalide: ' . implode(', ', $validationErrors)
                ];
            }

            // Handle default template logic
            if ($uiTemplate->isDefault()) {
                $this->handleDefaultTemplateChange($uiTemplate);
            }

            $uiTemplate->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('UI template updated', [
                'template_id' => $uiTemplate->getId(),
                'name' => $uiTemplate->getName()
            ]);

            return [
                'success' => true,
                'template' => $uiTemplate
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error updating UI template', [
                'template_id' => $uiTemplate->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la mise à jour du modèle UI: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a UI template
     */
    public function deleteUITemplate(DocumentUITemplate $uiTemplate): array
    {
        try {
            $this->entityManager->beginTransaction();

            // Check if template is being used
            if ($uiTemplate->getUsageCount() > 0) {
                return [
                    'success' => false,
                    'error' => 'Le modèle UI ne peut pas être supprimé car il est utilisé.'
                ];
            }

            $templateId = $uiTemplate->getId();
            $templateName = $uiTemplate->getName();

            $this->entityManager->remove($uiTemplate);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('UI template deleted', [
                'template_id' => $templateId,
                'name' => $templateName
            ]);

            return [
                'success' => true,
                'message' => 'Modèle UI supprimé avec succès.'
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error deleting UI template', [
                'template_id' => $uiTemplate->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la suppression du modèle UI: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Toggle UI template active status
     */
    public function toggleActiveStatus(DocumentUITemplate $uiTemplate): array
    {
        try {
            $newStatus = !$uiTemplate->isActive();
            $uiTemplate->setIsActive($newStatus);
            $uiTemplate->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            $statusText = $newStatus ? 'activé' : 'désactivé';
            
            return [
                'success' => true,
                'message' => "Modèle UI {$statusText} avec succès."
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error toggling UI template status', [
                'template_id' => $uiTemplate->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors du changement de statut du modèle UI.'
            ];
        }
    }

    /**
     * Duplicate a UI template
     */
    public function duplicateUITemplate(DocumentUITemplate $uiTemplate): array
    {
        try {
            $this->entityManager->beginTransaction();

            // Generate unique name and slug
            $baseName = $uiTemplate->getName() . ' (Copie)';
            $baseSlug = $uiTemplate->getSlug() . '-copie';
            
            $newName = $this->generateUniqueName($baseName);
            $newSlug = $this->generateUniqueSlug($baseSlug);

            // Clone template
            $newTemplate = $uiTemplate->cloneTemplate($newName, $newSlug);
            $newTemplate->setSortOrder($this->getNextSortOrder());

            $this->entityManager->persist($newTemplate);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('UI template duplicated', [
                'original_id' => $uiTemplate->getId(),
                'new_id' => $newTemplate->getId(),
                'new_name' => $newName
            ]);

            return [
                'success' => true,
                'template' => $newTemplate
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error duplicating UI template', [
                'template_id' => $uiTemplate->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la duplication du modèle UI: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Add component to UI template
     */
    public function addComponent(DocumentUITemplate $uiTemplate, DocumentUIComponent $component): array
    {
        try {
            $component->setUiTemplate($uiTemplate);
            $component->setSortOrder($this->componentRepository->getNextSortOrder($uiTemplate));

            $this->entityManager->persist($component);
            $this->entityManager->flush();

            $this->logger->info('Component added to UI template', [
                'template_id' => $uiTemplate->getId(),
                'component_id' => $component->getId(),
                'component_name' => $component->getName()
            ]);

            return [
                'success' => true,
                'component' => $component
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error adding component to UI template', [
                'template_id' => $uiTemplate->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de l\'ajout du composant: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update component sort orders
     */
    public function updateComponentSortOrders(DocumentUITemplate $uiTemplate, array $componentIds): array
    {
        try {
            $this->componentRepository->updateSortOrders($uiTemplate, $componentIds);
            
            return [
                'success' => true,
                'message' => 'Ordre des composants mis à jour.'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error updating component sort orders', [
                'template_id' => $uiTemplate->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la mise à jour de l\'ordre des composants.'
            ];
        }
    }

    /**
     * Render template with data
     */
    public function renderTemplate(DocumentUITemplate $uiTemplate, array $data = []): array
    {
        try {
            // Increment usage count
            $uiTemplate->incrementUsage();
            $this->entityManager->flush();

            // Render components first
            $components = $this->componentRepository->findActiveByTemplate($uiTemplate);
            $renderedComponents = [];
            $zoneContents = [
                'header' => '',
                'body' => '',
                'footer' => ''
            ];
            
            foreach ($components as $component) {
                if ($component->shouldDisplay($data)) {
                    $componentHtml = $component->renderHtml($data);
                    $renderedComponents[$component->getZone()][] = [
                        'component' => $component,
                        'html' => $componentHtml
                    ];
                    $zoneContents[$component->getZone()] .= $componentHtml . "\n";
                }
            }
            
            // Add zone contents to data for template rendering
            $templateData = array_merge($data, [
                'header_content' => $zoneContents['header'],
                'content' => $zoneContents['body'],
                'footer_content' => $zoneContents['footer']
            ]);

            // Generate HTML with component content
            $html = $uiTemplate->renderHtml($templateData);
            
            // Generate CSS
            $css = $uiTemplate->renderCss();
            
            // Get page configuration
            $pageConfig = $uiTemplate->getPageConfig();

            return [
                'success' => true,
                'html' => $html,
                'css' => $css,
                'page_config' => $pageConfig,
                'components' => $renderedComponents,
                'template' => $uiTemplate
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error rendering UI template', [
                'template_id' => $uiTemplate->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors du rendu du modèle UI: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get templates for document type
     */
    public function getTemplatesForType(?DocumentType $documentType): array
    {
        return $this->uiTemplateRepository->findByDocumentType($documentType);
    }

    /**
     * Get default template for document type
     */
    public function getDefaultTemplateForType(?DocumentType $documentType): ?DocumentUITemplate
    {
        return $this->uiTemplateRepository->findDefaultForType($documentType);
    }

    /**
     * Get document type by ID
     */
    public function getDocumentTypeById(int $id): ?DocumentType
    {
        return $this->documentTypeRepository->find($id);
    }

    /**
     * Get next sort order
     */
    public function getNextSortOrder(): int
    {
        return $this->uiTemplateRepository->getNextSortOrder();
    }

    /**
     * Handle default template change
     */
    private function handleDefaultTemplateChange(DocumentUITemplate $newDefaultTemplate): void
    {
        // Find existing default templates for the same document type
        $existingDefaults = $this->uiTemplateRepository->findBy([
            'documentType' => $newDefaultTemplate->getDocumentType(),
            'isDefault' => true,
            'isGlobal' => $newDefaultTemplate->isGlobal()
        ]);

        // Remove default status from existing templates
        foreach ($existingDefaults as $existing) {
            if ($existing->getId() !== $newDefaultTemplate->getId()) {
                $existing->setIsDefault(false);
            }
        }
    }

    /**
     * Generate unique name
     */
    private function generateUniqueName(string $baseName): string
    {
        $counter = 1;
        $name = $baseName;
        
        while (count($this->uiTemplateRepository->findSimilarByName($name)) > 0) {
            $name = $baseName . ' (' . $counter . ')';
            $counter++;
        }
        
        return $name;
    }

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug(string $baseSlug): string
    {
        $counter = 1;
        $slug = $baseSlug;
        
        while ($this->uiTemplateRepository->findBySlug($slug) !== null) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Export template configuration
     */
    public function exportTemplate(DocumentUITemplate $uiTemplate): array
    {
        $components = $this->componentRepository->findByTemplate($uiTemplate);
        
        return [
            'template' => [
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
            ],
            'components' => array_map(function($component) {
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
            }, $components)
        ];
    }

    /**
     * Import template configuration
     */
    public function importTemplate(array $config, ?DocumentType $documentType = null): array
    {
        try {
            $this->entityManager->beginTransaction();

            // Create template
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
                     ->setSortOrder($this->getNextSortOrder());

            $this->entityManager->persist($template);
            $this->entityManager->flush();

            // Create components
            foreach ($config['components'] ?? [] as $componentData) {
                $component = new DocumentUIComponent();
                $component->setName($componentData['name'])
                          ->setType($componentData['type'])
                          ->setZone($componentData['zone'])
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
                          ->setUiTemplate($template);

                $this->entityManager->persist($component);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return [
                'success' => true,
                'template' => $template
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error importing UI template', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de l\'importation du modèle UI: ' . $e->getMessage()
            ];
        }
    }
}
