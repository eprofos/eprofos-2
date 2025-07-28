<?php

namespace App\Service\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentMetadata;
use App\Repository\Document\DocumentMetadataRepository;
use App\Repository\Document\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Document Metadata Service
 * 
 * Handles business logic for document metadata management:
 * - Metadata CRUD operations
 * - Metadata statistics and analytics
 * - Metadata validation and type enforcement
 * - CSV export functionality
 */
class DocumentMetadataService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentMetadataRepository $documentMetadataRepository,
        private DocumentRepository $documentRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get all metadata with usage statistics
     */
    public function getMetadataWithStats(array $filters = []): array
    {
        $qb = $this->documentMetadataRepository->createQueryBuilder('dm')
            ->leftJoin('dm.document', 'd')
            ->addSelect('d');

        // Apply filters
        if (!empty($filters['document'])) {
            $qb->andWhere('d.id = :document')
               ->setParameter('document', $filters['document']);
        }

        if (!empty($filters['key'])) {
            $qb->andWhere('dm.metaKey LIKE :key')
               ->setParameter('key', '%' . $filters['key'] . '%');
        }

        if (!empty($filters['value_type'])) {
            $qb->andWhere('dm.dataType = :value_type')
               ->setParameter('value_type', $filters['value_type']);
        }

        $qb->orderBy('dm.createdAt', 'DESC');

        $metadata = $qb->getQuery()->getResult();
        $result = [];

        foreach ($metadata as $meta) {
            $result[] = [
                'metadata' => $meta,
                'document_title' => $meta->getDocument()?->getTitle(),
                'document_type' => $meta->getDocument()?->getDocumentType()?->getName(),
                'typed_value' => $meta->getTypedValue(),
                'effective_display_name' => $meta->getEffectiveDisplayName()
            ];
        }

        return $result;
    }

    /**
     * Get aggregate statistics for metadata
     */
    public function getAggregateStatistics(): array
    {
        // Total metadata count
        $totalCount = $this->documentMetadataRepository->count([]);

        // Count by data type
        $typeStats = $this->documentMetadataRepository->createQueryBuilder('dm')
            ->select('dm.dataType as type, COUNT(dm.id) as count')
            ->groupBy('dm.dataType')
            ->getQuery()
            ->getResult();

        // Count searchable vs non-searchable
        $searchableCount = $this->documentMetadataRepository->count(['isSearchable' => true]);
        $nonSearchableCount = $totalCount - $searchableCount;

        // Count required vs optional
        $requiredCount = $this->documentMetadataRepository->count(['isRequired' => true]);
        $optionalCount = $totalCount - $requiredCount;

        // Most used metadata keys
        $topKeys = $this->documentMetadataRepository->createQueryBuilder('dm')
            ->select('dm.metaKey as key, COUNT(dm.id) as count')
            ->groupBy('dm.metaKey')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return [
            'total_count' => $totalCount,
            'type_distribution' => $typeStats,
            'searchable_count' => $searchableCount,
            'non_searchable_count' => $nonSearchableCount,
            'required_count' => $requiredCount,
            'optional_count' => $optionalCount,
            'top_keys' => $topKeys
        ];
    }

    /**
     * Create a new document metadata
     */
    public function createDocumentMetadata(DocumentMetadata $documentMetadata): array
    {
        try {
            // Set created timestamp
            $documentMetadata->setCreatedAt(new \DateTimeImmutable());
            $documentMetadata->setUpdatedAt(new \DateTimeImmutable());
            
            // Validate metadata
            $validation = $this->validateDocumentMetadata($documentMetadata);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }

            // Validate value according to data type
            $valueValidation = $this->validateMetadataValue($documentMetadata);
            if (!$valueValidation['valid']) {
                return ['success' => false, 'error' => $valueValidation['error']];
            }

            $this->entityManager->persist($documentMetadata);
            $this->entityManager->flush();

            $this->logger->info('Document metadata created', [
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'document_id' => $documentMetadata->getDocument()?->getId()
            ]);

            return ['success' => true, 'metadata' => $documentMetadata];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create document metadata', [
                'error' => $e->getMessage(),
                'key' => $documentMetadata->getMetaKey()
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la création de la métadonnée: ' . $e->getMessage()];
        }
    }

    /**
     * Update an existing document metadata
     */
    public function updateDocumentMetadata(DocumentMetadata $documentMetadata): array
    {
        try {
            // Set updated timestamp
            $documentMetadata->setUpdatedAt(new \DateTimeImmutable());
            
            // Validate metadata
            $validation = $this->validateDocumentMetadata($documentMetadata);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }

            // Validate value according to data type
            $valueValidation = $this->validateMetadataValue($documentMetadata);
            if (!$valueValidation['valid']) {
                return ['success' => false, 'error' => $valueValidation['error']];
            }

            $this->entityManager->flush();

            $this->logger->info('Document metadata updated', [
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey()
            ]);

            return ['success' => true, 'metadata' => $documentMetadata];

        } catch (\Exception $e) {
            $this->logger->error('Failed to update document metadata', [
                'error' => $e->getMessage(),
                'metadata_id' => $documentMetadata->getId()
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la modification de la métadonnée: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a document metadata
     */
    public function deleteDocumentMetadata(DocumentMetadata $documentMetadata): array
    {
        try {
            $metadataKey = $documentMetadata->getMetaKey();
            $metadataId = $documentMetadata->getId();

            $this->entityManager->remove($documentMetadata);
            $this->entityManager->flush();

            $this->logger->info('Document metadata deleted', [
                'metadata_id' => $metadataId,
                'key' => $metadataKey
            ]);

            return ['success' => true];

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete document metadata', [
                'error' => $e->getMessage(),
                'metadata_id' => $documentMetadata->getId()
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la suppression de la métadonnée: ' . $e->getMessage()];
        }
    }

    /**
     * Bulk delete metadata by IDs
     */
    public function bulkDeleteMetadata(array $ids): array
    {
        try {
            $qb = $this->documentMetadataRepository->createQueryBuilder('dm')
                ->delete()
                ->where('dm.id IN (:ids)')
                ->setParameter('ids', $ids);

            $deletedCount = $qb->getQuery()->execute();

            $this->logger->info('Bulk deleted document metadata', [
                'deleted_count' => $deletedCount,
                'ids' => $ids
            ]);

            return ['success' => true, 'deleted_count' => $deletedCount];

        } catch (\Exception $e) {
            $this->logger->error('Failed to bulk delete document metadata', [
                'error' => $e->getMessage(),
                'ids' => $ids
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la suppression en masse: ' . $e->getMessage()];
        }
    }

    /**
     * Export metadata to CSV
     */
    public function exportMetadataToCSV(array $filters = []): array
    {
        try {
            $metadata = $this->getMetadataWithStats($filters);

            $csvContent = "ID,Document,Clé,Valeur,Type,Requis,Recherchable,Modifiable,Créé le\n";

            foreach ($metadata as $item) {
                $meta = $item['metadata'];
                $csvContent .= sprintf(
                    "%d,\"%s\",\"%s\",\"%s\",\"%s\",%s,%s,%s,\"%s\"\n",
                    $meta->getId(),
                    $item['document_title'] ?? '',
                    $meta->getMetaKey(),
                    $meta->getMetaValue() ?? '',
                    $meta->getDataTypeLabel(),
                    $meta->isRequired() ? 'Oui' : 'Non',
                    $meta->isSearchable() ? 'Oui' : 'Non',
                    $meta->isEditable() ? 'Oui' : 'Non',
                    $meta->getCreatedAt()?->format('d/m/Y H:i:s') ?? ''
                );
            }

            $response = new Response($csvContent);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 
                $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    'metadata_export_' . date('Y-m-d_H-i-s') . '.csv'
                )
            );

            return ['success' => true, 'response' => $response];

        } catch (\Exception $e) {
            $this->logger->error('Failed to export metadata to CSV', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return ['success' => false, 'error' => 'Erreur lors de l\'export CSV: ' . $e->getMessage()];
        }
    }

    /**
     * Get statistics for a specific metadata key
     */
    public function getMetadataStatisticsByKey(string $key): array
    {
        $metadata = $this->documentMetadataRepository->findBy(['metaKey' => $key]);

        $stats = [
            'total_count' => count($metadata),
            'data_types' => [],
            'value_distribution' => [],
            'documents_count' => 0
        ];

        $uniqueDocuments = [];
        $valueCount = [];

        foreach ($metadata as $meta) {
            // Count data types
            $type = $meta->getDataType();
            if (!isset($stats['data_types'][$type])) {
                $stats['data_types'][$type] = 0;
            }
            $stats['data_types'][$type]++;

            // Count value distribution
            $value = $meta->getMetaValue() ?? '(vide)';
            if (!isset($valueCount[$value])) {
                $valueCount[$value] = 0;
            }
            $valueCount[$value]++;

            // Count unique documents
            if ($meta->getDocument()) {
                $uniqueDocuments[$meta->getDocument()->getId()] = true;
            }
        }

        $stats['documents_count'] = count($uniqueDocuments);
        
        // Sort value distribution by count
        arsort($valueCount);
        $stats['value_distribution'] = array_slice($valueCount, 0, 10, true);

        return $stats;
    }

    /**
     * Get available metadata keys with optional search
     */
    public function getAvailableMetadataKeys(string $search = ''): array
    {
        $qb = $this->documentMetadataRepository->createQueryBuilder('dm')
            ->select('DISTINCT dm.metaKey as key, COUNT(dm.id) as usage_count')
            ->groupBy('dm.metaKey')
            ->orderBy('usage_count', 'DESC');

        if (!empty($search)) {
            $qb->where('dm.metaKey LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get document by ID
     */
    public function getDocumentById(int $documentId): ?Document
    {
        return $this->documentRepository->find($documentId);
    }

    /**
     * Validate document metadata
     */
    private function validateDocumentMetadata(DocumentMetadata $documentMetadata): array
    {
        if (empty($documentMetadata->getMetaKey())) {
            return ['valid' => false, 'error' => 'La clé de métadonnée est requise.'];
        }

        if (!$documentMetadata->getDocument()) {
            return ['valid' => false, 'error' => 'Le document associé est requis.'];
        }

        // Check for duplicate keys within the same document
        $existingMetadata = $this->documentMetadataRepository->findOneBy([
            'document' => $documentMetadata->getDocument(),
            'metaKey' => $documentMetadata->getMetaKey()
        ]);

        if ($existingMetadata && $existingMetadata->getId() !== $documentMetadata->getId()) {
            return ['valid' => false, 'error' => 'Cette clé de métadonnée existe déjà pour ce document.'];
        }

        return ['valid' => true];
    }

    /**
     * Validate metadata value according to its data type
     */
    private function validateMetadataValue(DocumentMetadata $documentMetadata): array
    {
        $value = $documentMetadata->getMetaValue();
        $dataType = $documentMetadata->getDataType();

        // Required check
        if ($documentMetadata->isRequired() && empty($value)) {
            return ['valid' => false, 'error' => 'Cette métadonnée est obligatoire.'];
        }

        // Type validation
        if (!empty($value)) {
            switch ($dataType) {
                case DocumentMetadata::TYPE_INTEGER:
                    if (!is_numeric($value) || (int)$value != $value) {
                        return ['valid' => false, 'error' => 'La valeur doit être un nombre entier.'];
                    }
                    break;

                case DocumentMetadata::TYPE_FLOAT:
                    if (!is_numeric($value)) {
                        return ['valid' => false, 'error' => 'La valeur doit être un nombre décimal.'];
                    }
                    break;

                case DocumentMetadata::TYPE_BOOLEAN:
                    if (!in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no', 'oui', 'non'])) {
                        return ['valid' => false, 'error' => 'La valeur doit être un booléen (true/false, 1/0, oui/non).'];
                    }
                    break;

                case DocumentMetadata::TYPE_DATE:
                    try {
                        new \DateTime($value);
                    } catch (\Exception $e) {
                        return ['valid' => false, 'error' => 'La valeur doit être une date valide.'];
                    }
                    break;

                case DocumentMetadata::TYPE_DATETIME:
                    try {
                        new \DateTime($value);
                    } catch (\Exception $e) {
                        return ['valid' => false, 'error' => 'La valeur doit être une date et heure valide.'];
                    }
                    break;

                case DocumentMetadata::TYPE_JSON:
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return ['valid' => false, 'error' => 'La valeur doit être un JSON valide.'];
                    }
                    break;

                case DocumentMetadata::TYPE_URL:
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        return ['valid' => false, 'error' => 'La valeur doit être une URL valide.'];
                    }
                    break;
            }
        }

        return ['valid' => true];
    }
}
