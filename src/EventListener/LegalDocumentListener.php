<?php

namespace App\EventListener;

use App\Entity\LegalDocument;
use App\Service\LegalPdfGenerationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;

/**
 * Doctrine Event Listener for LegalDocument entity
 * 
 * Automatically handles PDF generation/deletion based on document lifecycle
 */
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class LegalDocumentListener
{
    public function __construct(
        private LegalPdfGenerationService $pdfGenerationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Handle document updates - regenerate PDF if published
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof LegalDocument) {
            return;
        }

        $this->logger->info('LegalDocument updated, checking PDF requirements', [
            'document_id' => $entity->getId(),
            'status' => $entity->getStatus(),
            'is_published' => $entity->isPublished()
        ]);

        // Get entity change set to check what changed
        $entityManager = $args->getObjectManager();
        
        // For updates, we need to detect changes differently
        // We'll check current status and assume changes if needed
        $isPublished = $entity->isPublished();
        
        // If document is published, ensure it has a PDF
        if ($isPublished && !$this->pdfGenerationService->hasPdf($entity)) {
            $this->logger->info('Published document missing PDF, generating', [
                'document_id' => $entity->getId()
            ]);
            
            try {
                $result = $this->pdfGenerationService->generatePdf($entity);
                if ($result['success']) {
                    $this->logger->info('PDF generated successfully for published document', [
                        'document_id' => $entity->getId(),
                        'filename' => $result['filename']
                    ]);
                    
                    // Update the entity with new metadata
                    $entityManager->persist($entity);
                    $entityManager->flush();
                } else {
                    $this->logger->error('Failed to generate PDF for published document', [
                        'document_id' => $entity->getId(),
                        'error' => $result['error']
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('Exception while generating PDF for published document', [
                    'document_id' => $entity->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // If document is not published but has PDF, delete it
        if (!$isPublished && $this->pdfGenerationService->hasPdf($entity)) {
            $this->logger->info('Unpublished document has PDF, deleting', [
                'document_id' => $entity->getId()
            ]);
            
            try {
                $result = $this->pdfGenerationService->deletePdf($entity);
                if ($result['success']) {
                    $this->logger->info('PDF deleted successfully for unpublished document', [
                        'document_id' => $entity->getId()
                    ]);
                    
                    // Update the entity with new metadata
                    $entityManager->persist($entity);
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                $this->logger->error('Exception while deleting PDF for unpublished document', [
                    'document_id' => $entity->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle document removal - delete associated PDF
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof LegalDocument) {
            return;
        }

        $this->logger->info('LegalDocument removed, deleting PDF', [
            'document_id' => $entity->getId()
        ]);

        try {
            $result = $this->pdfGenerationService->deletePdf($entity);
            if ($result['success']) {
                $this->logger->info('PDF deleted successfully for removed document', [
                    'document_id' => $entity->getId()
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception while deleting PDF for removed document', [
                'document_id' => $entity->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
