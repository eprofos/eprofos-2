<?php

namespace App\Service;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentTemplate;
use App\Entity\Document\DocumentType;
use App\Repository\Document\DocumentTemplateRepository;
use App\Repository\Document\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Document Template Service
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
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get all templates with usage statistics
     */
    public function getTemplatesWithStats(): array
    {
        $templates = $this->documentTemplateRepository->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']);
        $result = [];

        foreach ($templates as $template) {
            $result[] = [
                'template' => $template,
                'usage_count' => $template->getUsageCount(),
                'document_type' => $template->getDocumentType()?->getName(),
                'placeholders_count' => count($template->getPlaceholders() ?? []),
                'is_default' => $template->isDefault()
            ];
        }

        return $result;
    }

    /**
     * Create a new document template
     */
    public function createDocumentTemplate(DocumentTemplate $documentTemplate): array
    {
        try {
            // Set created timestamp
            $documentTemplate->setCreatedAt(new \DateTimeImmutable());
            
            // Validate template
            $validation = $this->validateDocumentTemplate($documentTemplate);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }

            // Handle default template logic
            if ($documentTemplate->isDefault() && $documentTemplate->getDocumentType()) {
                $this->unsetOtherDefaultTemplates($documentTemplate->getDocumentType());
            }

            $this->entityManager->persist($documentTemplate);
            $this->entityManager->flush();

            $this->logger->info('Document template created', [
                'template_id' => $documentTemplate->getId(),
                'name' => $documentTemplate->getName(),
                'type' => $documentTemplate->getDocumentType()?->getName()
            ]);

            return ['success' => true, 'template' => $documentTemplate];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create document template', [
                'error' => $e->getMessage(),
                'name' => $documentTemplate->getName()
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la création du modèle: ' . $e->getMessage()];
        }
    }

    /**
     * Update an existing document template
     */
    public function updateDocumentTemplate(DocumentTemplate $documentTemplate): array
    {
        try {
            // Set updated timestamp
            $documentTemplate->setUpdatedAt(new \DateTimeImmutable());
            
            // Validate template
            $validation = $this->validateDocumentTemplate($documentTemplate);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }

            // Handle default template logic
            if ($documentTemplate->isDefault() && $documentTemplate->getDocumentType()) {
                $this->unsetOtherDefaultTemplates($documentTemplate->getDocumentType(), $documentTemplate);
            }

            $this->entityManager->flush();

            $this->logger->info('Document template updated', [
                'template_id' => $documentTemplate->getId(),
                'name' => $documentTemplate->getName()
            ]);

            return ['success' => true, 'template' => $documentTemplate];

        } catch (\Exception $e) {
            $this->logger->error('Failed to update document template', [
                'error' => $e->getMessage(),
                'template_id' => $documentTemplate->getId()
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la modification du modèle: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a document template
     */
    public function deleteDocumentTemplate(DocumentTemplate $documentTemplate): array
    {
        try {
            // Check if template is in use
            if ($documentTemplate->getUsageCount() > 0) {
                return ['success' => false, 'error' => 'Ce modèle ne peut pas être supprimé car il est utilisé par des documents.'];
            }

            $templateName = $documentTemplate->getName();
            $templateId = $documentTemplate->getId();

            $this->entityManager->remove($documentTemplate);
            $this->entityManager->flush();

            $this->logger->info('Document template deleted', [
                'template_id' => $templateId,
                'name' => $templateName
            ]);

            return ['success' => true];

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete document template', [
                'error' => $e->getMessage(),
                'template_id' => $documentTemplate->getId()
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la suppression du modèle: ' . $e->getMessage()];
        }
    }

    /**
     * Toggle document template active status
     */
    public function toggleActiveStatus(DocumentTemplate $documentTemplate): array
    {
        try {
            $wasActive = $documentTemplate->isActive();
            $documentTemplate->setIsActive(!$wasActive);
            $documentTemplate->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            $status = $documentTemplate->isActive() ? 'activé' : 'désactivé';
            $message = sprintf('Le modèle "%s" a été %s avec succès.', $documentTemplate->getName(), $status);

            $this->logger->info('Document template status toggled', [
                'template_id' => $documentTemplate->getId(),
                'name' => $documentTemplate->getName(),
                'new_status' => $documentTemplate->isActive()
            ]);

            return ['success' => true, 'message' => $message];

        } catch (\Exception $e) {
            $this->logger->error('Failed to toggle document template status', [
                'error' => $e->getMessage(),
                'template_id' => $documentTemplate->getId()
            ]);

            return ['success' => false, 'error' => 'Erreur lors du changement de statut: ' . $e->getMessage()];
        }
    }

    /**
     * Duplicate a document template
     */
    public function duplicateDocumentTemplate(DocumentTemplate $original): array
    {
        try {
            $duplicate = new DocumentTemplate();
            
            // Copy all properties except ID and timestamps
            $duplicate->setName($original->getName() . ' (Copie)');
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
            $duplicate->setSortOrder($this->getNextSortOrder());
            $duplicate->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($duplicate);
            $this->entityManager->flush();

            $this->logger->info('Document template duplicated', [
                'original_id' => $original->getId(),
                'duplicate_id' => $duplicate->getId(),
                'name' => $duplicate->getName()
            ]);

            return ['success' => true, 'template' => $duplicate];

        } catch (\Exception $e) {
            $this->logger->error('Failed to duplicate document template', [
                'error' => $e->getMessage(),
                'original_id' => $original->getId()
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la duplication du modèle: ' . $e->getMessage()];
        }
    }

    /**
     * Create a document from a template
     */
    public function createDocumentFromTemplate(DocumentTemplate $template, array $placeholderValues = []): array
    {
        try {
            $document = new Document();
            
            // Set basic properties
            $document->setTitle($template->getName());
            $document->setDescription($template->getDescription());
            $document->setDocumentType($template->getDocumentType());
            
            // Process template content with placeholders
            $content = $this->processTemplatePlaceholders($template->getTemplateContent(), $placeholderValues);
            $document->setContent($content);
            
            // Apply metadata defaults
            if ($template->getDefaultMetadata()) {
                foreach ($template->getDefaultMetadata() as $key => $value) {
                    // Apply metadata defaults to document
                    // This would require DocumentMetadata entity creation
                }
            }
            
            // Set status and timestamps
            $document->setStatus('draft');
            $document->setCreatedAt(new \DateTimeImmutable());
            
            // Increment template usage count
            $template->setUsageCount($template->getUsageCount() + 1);
            $template->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->logger->info('Document created from template', [
                'template_id' => $template->getId(),
                'document_id' => $document->getId(),
                'template_name' => $template->getName()
            ]);

            return ['success' => true, 'document' => $document];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create document from template', [
                'error' => $e->getMessage(),
                'template_id' => $template->getId()
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la création du document: ' . $e->getMessage()];
        }
    }

    /**
     * Get next sort order for templates
     */
    public function getNextSortOrder(): int
    {
        $maxSortOrder = $this->documentTemplateRepository->createQueryBuilder('dt')
            ->select('MAX(dt.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxSortOrder ?? 0) + 1;
    }

    /**
     * Get document type by ID
     */
    public function getDocumentTypeById(int $typeId): ?DocumentType
    {
        return $this->documentTypeRepository->find($typeId);
    }

    /**
     * Validate document template
     */
    private function validateDocumentTemplate(DocumentTemplate $documentTemplate): array
    {
        if (empty($documentTemplate->getName())) {
            return ['valid' => false, 'error' => 'Le nom du modèle est requis.'];
        }

        if (empty($documentTemplate->getTemplateContent())) {
            return ['valid' => false, 'error' => 'Le contenu du modèle est requis.'];
        }

        // Check for duplicate names within the same document type
        $existingTemplate = $this->documentTemplateRepository->findOneBy([
            'name' => $documentTemplate->getName(),
            'documentType' => $documentTemplate->getDocumentType()
        ]);

        if ($existingTemplate && $existingTemplate->getId() !== $documentTemplate->getId()) {
            return ['valid' => false, 'error' => 'Un modèle avec ce nom existe déjà pour ce type de document.'];
        }

        return ['valid' => true];
    }

    /**
     * Unset other default templates for the same document type
     */
    private function unsetOtherDefaultTemplates(DocumentType $documentType, ?DocumentTemplate $excludeTemplate = null): void
    {
        $qb = $this->documentTemplateRepository->createQueryBuilder('dt')
            ->where('dt.documentType = :documentType')
            ->andWhere('dt.isDefault = true')
            ->setParameter('documentType', $documentType);

        if ($excludeTemplate) {
            $qb->andWhere('dt.id != :excludeId')
               ->setParameter('excludeId', $excludeTemplate->getId());
        }

        $defaultTemplates = $qb->getQuery()->getResult();

        foreach ($defaultTemplates as $template) {
            $template->setIsDefault(false);
        }
    }

    /**
     * Process template placeholders with provided values
     */
    private function processTemplatePlaceholders(string $content, array $placeholderValues): string
    {
        // Simple placeholder replacement: {{placeholder_name}}
        foreach ($placeholderValues as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }
}
