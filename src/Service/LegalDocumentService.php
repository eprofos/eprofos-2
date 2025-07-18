<?php

namespace App\Service;

use App\Entity\LegalDocument;
use App\Repository\LegalDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing legal documents business logic
 * 
 * Handles document publication with business rules like
 * ensuring only one document of each type is published at a time.
 */
class LegalDocumentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LegalDocumentRepository $legalDocumentRepository,
        private LoggerInterface $logger,
        private LegalPdfGenerationService $pdfGenerationService
    ) {
    }

    /**
     * Publish a document and automatically archive other documents of the same type
     * 
     * @param LegalDocument $document The document to publish
     * @return array Information about the operation (affected documents count, etc.)
     */
    public function publishDocument(LegalDocument $document): array
    {
        $type = $document->getType();
        $documentId = $document->getId();
        
        $this->logger->info('Publishing legal document with type exclusivity', [
            'document_id' => $documentId,
            'type' => $type
        ]);

        // Get other published documents of the same type before making changes
        $otherPublishedDocuments = $this->legalDocumentRepository->findOtherPublishedDocumentsOfType($type, $documentId);
        $affectedCount = count($otherPublishedDocuments);

        // Log the documents that will be archived
        foreach ($otherPublishedDocuments as $otherDoc) {
            $this->logger->info('Archiving document due to type exclusivity', [
                'archived_document_id' => $otherDoc->getId(),
                'archived_document_title' => $otherDoc->getTitle(),
                'new_published_document_id' => $documentId
            ]);
        }

        // Start transaction
        $this->entityManager->beginTransaction();

        try {
            // Publish the current document
            $document->publish();

            // Generate PDF for the published document
            $pdfResult = $this->pdfGenerationService->generatePdf($document);
            if (!$pdfResult['success']) {
                throw new \Exception('Failed to generate PDF: ' . $pdfResult['error']);
            }

            // Archive other documents of the same type
            $archivedCount = $this->legalDocumentRepository->archiveOtherDocumentsOfType($type, $documentId);

            // Delete PDFs for archived documents
            $this->deleteArchivedDocumentsPdfs($otherPublishedDocuments);

            // Persist changes
            $this->entityManager->persist($document);
            $this->entityManager->flush();
            
            // Commit transaction
            $this->entityManager->commit();

            $this->logger->info('Successfully published document with type exclusivity', [
                'document_id' => $documentId,
                'type' => $type,
                'archived_count' => $archivedCount
            ]);

            return [
                'success' => true,
                'published_document' => $document,
                'archived_count' => $archivedCount,
                'affected_documents' => $otherPublishedDocuments
            ];

        } catch (\Exception $e) {
            // Rollback transaction on error
            $this->entityManager->rollback();
            
            $this->logger->error('Failed to publish document with type exclusivity', [
                'document_id' => $documentId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Unpublish a document (set to draft)
     * 
     * @param LegalDocument $document The document to unpublish
     * @return array Information about the operation
     */
    public function unpublishDocument(LegalDocument $document): array
    {
        $documentId = $document->getId();
        $type = $document->getType();
        
        $this->logger->info('Unpublishing legal document', [
            'document_id' => $documentId,
            'type' => $type
        ]);

        try {
            $document->unpublish();
            
            // Delete PDF when unpublishing
            $this->pdfGenerationService->deletePdf($document);
            
            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->logger->info('Successfully unpublished document', [
                'document_id' => $documentId,
                'type' => $type
            ]);

            return [
                'success' => true,
                'unpublished_document' => $document
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to unpublish document', [
                'document_id' => $documentId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Archive a document
     * 
     * @param LegalDocument $document The document to archive
     * @return array Information about the operation
     */
    public function archiveDocument(LegalDocument $document): array
    {
        $documentId = $document->getId();
        $type = $document->getType();
        
        $this->logger->info('Archiving legal document', [
            'document_id' => $documentId,
            'type' => $type
        ]);

        try {
            $document->archive();
            
            // Delete PDF when archiving
            $this->pdfGenerationService->deletePdf($document);
            
            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->logger->info('Successfully archived document', [
                'document_id' => $documentId,
                'type' => $type
            ]);

            return [
                'success' => true,
                'archived_document' => $document
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to archive document', [
                'document_id' => $documentId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update a document and regenerate PDF if published
     * 
     * @param LegalDocument $document The document to update
     * @return array Information about the operation
     */
    public function updateDocument(LegalDocument $document): array
    {
        $documentId = $document->getId();
        $type = $document->getType();
        
        $this->logger->info('Updating legal document', [
            'document_id' => $documentId,
            'type' => $type
        ]);

        try {
            // If document is published, regenerate PDF
            if ($document->isPublished()) {
                $pdfResult = $this->pdfGenerationService->generatePdf($document);
                if (!$pdfResult['success']) {
                    throw new \Exception('Failed to regenerate PDF: ' . $pdfResult['error']);
                }
            }
            
            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->logger->info('Successfully updated document', [
                'document_id' => $documentId,
                'type' => $type
            ]);

            return [
                'success' => true,
                'updated_document' => $document
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to update document', [
                'document_id' => $documentId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete PDFs for archived documents
     * 
     * @param array $documents Array of LegalDocument objects
     */
    private function deleteArchivedDocumentsPdfs(array $documents): void
    {
        foreach ($documents as $document) {
            try {
                $this->pdfGenerationService->deletePdf($document);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to delete PDF for archived document', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Get publication status information for a document type
     * 
     * @param string $type The document type
     * @return array Status information
     */
    public function getTypePublicationStatus(string $type): array
    {
        $publishedDocuments = $this->legalDocumentRepository->findPublishedByType($type);
        $latestPublished = $this->legalDocumentRepository->findLatestPublishedByType($type);
        
        return [
            'type' => $type,
            'published_count' => count($publishedDocuments),
            'latest_published' => $latestPublished,
            'has_published' => !empty($publishedDocuments)
        ];
    }

    /**
     * Check if a document can be published (business rules validation)
     * 
     * @param LegalDocument $document The document to check
     * @return array Validation result
     */
    public function canPublish(LegalDocument $document): array
    {
        $issues = [];

        // Check if document has required fields
        if (empty($document->getTitle())) {
            $issues[] = 'Le titre est requis pour la publication';
        }

        if (empty($document->getContent())) {
            $issues[] = 'Le contenu est requis pour la publication';
        }

        if (empty($document->getVersion())) {
            $issues[] = 'La version est requise pour la publication';
        }

        // Check if document is active
        if (!$document->isActive()) {
            $issues[] = 'Le document doit être actif pour être publié';
        }

        // Check if document is in a valid state for publication
        if ($document->getStatus() === LegalDocument::STATUS_ARCHIVED) {
            $issues[] = 'Les documents archivés ne peuvent pas être publiés directement';
        }

        return [
            'can_publish' => empty($issues),
            'issues' => $issues
        ];
    }
}
