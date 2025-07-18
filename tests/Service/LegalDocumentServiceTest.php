<?php

namespace App\Tests\Service;

use App\Entity\LegalDocument;
use App\Repository\LegalDocumentRepository;
use App\Service\LegalDocumentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LegalDocumentServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LegalDocumentRepository&MockObject $repository;
    private LoggerInterface&MockObject $logger;
    private LegalDocumentService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(LegalDocumentRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->service = new LegalDocumentService(
            $this->entityManager,
            $this->repository,
            $this->logger
        );
    }

    public function testPublishDocumentUnpublishesOthersOfSameType(): void
    {
        // Create a document to publish
        $newDocument = new LegalDocument();
        $newDocument->setType(LegalDocument::TYPE_INTERNAL_REGULATION);
        $newDocument->setTitle('New Internal Regulation');
        $newDocument->setContent('New content');
        $newDocument->setVersion('2.0');
        $newDocument->setIsActive(true);
        
        // Mock existing published documents of the same type
        $existingDoc1 = new LegalDocument();
        $existingDoc1->setType(LegalDocument::TYPE_INTERNAL_REGULATION);
        $existingDoc1->setTitle('Old Internal Regulation 1');
        $existingDoc1->publish();
        
        $existingDoc2 = new LegalDocument();
        $existingDoc2->setType(LegalDocument::TYPE_INTERNAL_REGULATION);
        $existingDoc2->setTitle('Old Internal Regulation 2');
        $existingDoc2->publish();
        
        // Mock repository methods
        $this->repository->expects($this->once())
            ->method('findOtherPublishedDocumentsOfType')
            ->with(LegalDocument::TYPE_INTERNAL_REGULATION, null)
            ->willReturn([$existingDoc1, $existingDoc2]);
        
        $this->repository->expects($this->once())
            ->method('unpublishOtherDocumentsOfType')
            ->with(LegalDocument::TYPE_INTERNAL_REGULATION, null)
            ->willReturn(2);
        
        // Mock entity manager
        $this->entityManager->expects($this->once())
            ->method('beginTransaction');
        
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($newDocument);
        
        $this->entityManager->expects($this->once())
            ->method('flush');
        
        $this->entityManager->expects($this->once())
            ->method('commit');
        
        // Execute the test
        $result = $this->service->publishDocument($newDocument);
        
        // Verify results
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['unpublished_count']);
        $this->assertCount(2, $result['affected_documents']);
        $this->assertTrue($newDocument->isPublished());
    }

    public function testPublishDocumentRollsBackOnError(): void
    {
        $document = new LegalDocument();
        $document->setType(LegalDocument::TYPE_INTERNAL_REGULATION);
        $document->setTitle('Test Document');
        $document->setContent('Test content');
        $document->setVersion('1.0');
        $document->setIsActive(true);
        
        // Mock repository methods
        $this->repository->expects($this->once())
            ->method('findOtherPublishedDocumentsOfType')
            ->willReturn([]);
        
        $this->repository->expects($this->once())
            ->method('unpublishOtherDocumentsOfType')
            ->willReturn(0);
        
        // Mock entity manager to throw exception
        $this->entityManager->expects($this->once())
            ->method('beginTransaction');
        
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($document);
        
        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception('Database error'));
        
        $this->entityManager->expects($this->once())
            ->method('rollback');
        
        // Execute the test
        $result = $this->service->publishDocument($document);
        
        // Verify results
        $this->assertFalse($result['success']);
        $this->assertEquals('Database error', $result['error']);
    }

    public function testCanPublishValidation(): void
    {
        // Test valid document
        $validDocument = new LegalDocument();
        $validDocument->setType(LegalDocument::TYPE_INTERNAL_REGULATION);
        $validDocument->setTitle('Valid Document');
        $validDocument->setContent('Valid content');
        $validDocument->setVersion('1.0');
        $validDocument->setIsActive(true);
        
        $result = $this->service->canPublish($validDocument);
        $this->assertTrue($result['can_publish']);
        $this->assertEmpty($result['issues']);
        
        // Test invalid document (missing title)
        $invalidDocument = new LegalDocument();
        $invalidDocument->setType(LegalDocument::TYPE_INTERNAL_REGULATION);
        $invalidDocument->setContent('Valid content');
        $invalidDocument->setVersion('1.0');
        $invalidDocument->setIsActive(true);
        
        $result = $this->service->canPublish($invalidDocument);
        $this->assertFalse($result['can_publish']);
        $this->assertContains('Le titre est requis pour la publication', $result['issues']);
    }

    public function testUnpublishDocument(): void
    {
        $document = new LegalDocument();
        $document->setType(LegalDocument::TYPE_INTERNAL_REGULATION);
        $document->setTitle('Test Document');
        $document->setContent('Test content');
        $document->setVersion('1.0');
        $document->setIsActive(true);
        $document->publish();
        
        // Mock entity manager
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($document);
        
        $this->entityManager->expects($this->once())
            ->method('flush');
        
        // Execute the test
        $result = $this->service->unpublishDocument($document);
        
        // Verify results
        $this->assertTrue($result['success']);
        $this->assertFalse($document->isPublished());
    }
}
