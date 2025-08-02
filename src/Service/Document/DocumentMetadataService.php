<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentMetadata;
use App\Repository\Document\DocumentMetadataRepository;
use App\Repository\Document\DocumentRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Document Metadata Service.
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
        private LoggerInterface $logger,
    ) {}

    /**
     * Get all metadata with usage statistics.
     */
    public function getMetadataWithStats(array $filters = []): array
    {
        try {
            $this->logger->info('Starting metadata retrieval with stats', [
                'filters' => $filters,
                'method' => __METHOD__,
            ]);

            $qb = $this->documentMetadataRepository->createQueryBuilder('dm')
                ->leftJoin('dm.document', 'd')
                ->addSelect('d')
            ;

            // Apply filters
            if (!empty($filters['document'])) {
                $this->logger->debug('Applying document filter', [
                    'document_id' => $filters['document'],
                ]);
                $qb->andWhere('d.id = :document')
                    ->setParameter('document', $filters['document'])
                ;
            }

            if (!empty($filters['key'])) {
                $this->logger->debug('Applying key filter', [
                    'key_pattern' => $filters['key'],
                ]);
                $qb->andWhere('dm.metaKey LIKE :key')
                    ->setParameter('key', '%' . $filters['key'] . '%')
                ;
            }

            if (!empty($filters['value_type'])) {
                $this->logger->debug('Applying value type filter', [
                    'value_type' => $filters['value_type'],
                ]);
                $qb->andWhere('dm.dataType = :value_type')
                    ->setParameter('value_type', $filters['value_type'])
                ;
            }

            $qb->orderBy('dm.createdAt', 'DESC');

            $this->logger->debug('Executing metadata query with filters', [
                'dql' => $qb->getDQL(),
                'parameters' => $qb->getParameters()->toArray(),
            ]);

            $metadata = $qb->getQuery()->getResult();
            $result = [];

            $this->logger->info('Metadata query executed successfully', [
                'metadata_count' => count($metadata),
            ]);

            foreach ($metadata as $meta) {
                try {
                    $result[] = [
                        'metadata' => $meta,
                        'document_title' => $meta->getDocument()?->getTitle(),
                        'document_type' => $meta->getDocument()?->getDocumentType()?->getName(),
                        'typed_value' => $meta->getTypedValue(),
                        'effective_display_name' => $meta->getEffectiveDisplayName(),
                    ];
                } catch (Exception $e) {
                    $this->logger->warning('Error processing metadata item', [
                        'metadata_id' => $meta->getId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue processing other items
                }
            }

            $this->logger->info('Metadata with stats processing completed', [
                'result_count' => count($result),
                'filters_applied' => array_keys(array_filter($filters)),
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve metadata with stats', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw new Exception('Erreur lors de la récupération des métadonnées: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get aggregate statistics for metadata.
     */
    public function getAggregateStatistics(): array
    {
        try {
            $this->logger->info('Starting aggregate statistics calculation', [
                'method' => __METHOD__,
            ]);

            // Total metadata count
            $this->logger->debug('Calculating total metadata count');
            $totalCount = $this->documentMetadataRepository->count([]);
            $this->logger->debug('Total metadata count calculated', [
                'total_count' => $totalCount,
            ]);

            // Count by data type
            $this->logger->debug('Calculating metadata count by data type');
            $typeStats = $this->documentMetadataRepository->createQueryBuilder('dm')
                ->select('dm.dataType as type, COUNT(dm.id) as count')
                ->groupBy('dm.dataType')
                ->getQuery()
                ->getResult()
            ;
            $this->logger->debug('Data type statistics calculated', [
                'type_stats' => $typeStats,
            ]);

            // Count searchable vs non-searchable
            $this->logger->debug('Calculating searchable vs non-searchable count');
            $searchableCount = $this->documentMetadataRepository->count(['isSearchable' => true]);
            $nonSearchableCount = $totalCount - $searchableCount;
            $this->logger->debug('Searchable statistics calculated', [
                'searchable_count' => $searchableCount,
                'non_searchable_count' => $nonSearchableCount,
            ]);

            // Count required vs optional
            $this->logger->debug('Calculating required vs optional count');
            $requiredCount = $this->documentMetadataRepository->count(['isRequired' => true]);
            $optionalCount = $totalCount - $requiredCount;
            $this->logger->debug('Required statistics calculated', [
                'required_count' => $requiredCount,
                'optional_count' => $optionalCount,
            ]);

            // Most used metadata keys
            $this->logger->debug('Calculating most used metadata keys');
            $topKeys = $this->documentMetadataRepository->createQueryBuilder('dm')
                ->select('dm.metaKey as key, COUNT(dm.id) as count')
                ->groupBy('dm.metaKey')
                ->orderBy('count', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult()
            ;
            $this->logger->debug('Top metadata keys calculated', [
                'top_keys_count' => count($topKeys),
            ]);

            $result = [
                'total_count' => $totalCount,
                'type_distribution' => $typeStats,
                'searchable_count' => $searchableCount,
                'non_searchable_count' => $nonSearchableCount,
                'required_count' => $requiredCount,
                'optional_count' => $optionalCount,
                'top_keys' => $topKeys,
            ];

            $this->logger->info('Aggregate statistics calculation completed successfully', [
                'statistics_keys' => array_keys($result),
                'total_metadata_count' => $totalCount,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate aggregate statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw new Exception('Erreur lors du calcul des statistiques agrégées: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a new document metadata.
     */
    public function createDocumentMetadata(DocumentMetadata $documentMetadata): array
    {
        try {
            $this->logger->info('Starting document metadata creation', [
                'key' => $documentMetadata->getMetaKey(),
                'document_id' => $documentMetadata->getDocument()?->getId(),
                'data_type' => $documentMetadata->getDataType(),
                'is_required' => $documentMetadata->isRequired(),
                'is_searchable' => $documentMetadata->isSearchable(),
                'is_editable' => $documentMetadata->isEditable(),
                'method' => __METHOD__,
            ]);

            // Set created timestamp
            $documentMetadata->setCreatedAt(new DateTimeImmutable());
            $documentMetadata->setUpdatedAt(new DateTimeImmutable());

            $this->logger->debug('Timestamps set for new metadata', [
                'created_at' => $documentMetadata->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $documentMetadata->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]);

            // Validate metadata
            $this->logger->debug('Validating document metadata');
            $validation = $this->validateDocumentMetadata($documentMetadata);
            if (!$validation['valid']) {
                $this->logger->warning('Document metadata validation failed', [
                    'validation_error' => $validation['error'],
                    'key' => $documentMetadata->getMetaKey(),
                    'document_id' => $documentMetadata->getDocument()?->getId(),
                ]);

                return ['success' => false, 'error' => $validation['error']];
            }

            $this->logger->debug('Document metadata validation passed');

            // Validate value according to data type
            $this->logger->debug('Validating metadata value according to data type');
            $valueValidation = $this->validateMetadataValue($documentMetadata);
            if (!$valueValidation['valid']) {
                $this->logger->warning('Metadata value validation failed', [
                    'validation_error' => $valueValidation['error'],
                    'value' => $documentMetadata->getMetaValue(),
                    'data_type' => $documentMetadata->getDataType(),
                    'key' => $documentMetadata->getMetaKey(),
                ]);

                return ['success' => false, 'error' => $valueValidation['error']];
            }

            $this->logger->debug('Metadata value validation passed');

            $this->logger->debug('Persisting metadata to database');
            $this->entityManager->persist($documentMetadata);
            $this->entityManager->flush();

            $this->logger->info('Document metadata created successfully', [
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'document_id' => $documentMetadata->getDocument()?->getId(),
                'value' => $documentMetadata->getMetaValue(),
                'data_type' => $documentMetadata->getDataType(),
            ]);

            return ['success' => true, 'metadata' => $documentMetadata];
        } catch (Exception $e) {
            $this->logger->error('Failed to create document metadata', [
                'error' => $e->getMessage(),
                'key' => $documentMetadata->getMetaKey(),
                'document_id' => $documentMetadata->getDocument()?->getId(),
                'data_type' => $documentMetadata->getDataType(),
                'value' => $documentMetadata->getMetaValue(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la création de la métadonnée: ' . $e->getMessage()];
        }
    }

    /**
     * Update an existing document metadata.
     */
    public function updateDocumentMetadata(DocumentMetadata $documentMetadata): array
    {
        try {
            $this->logger->info('Starting document metadata update', [
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'document_id' => $documentMetadata->getDocument()?->getId(),
                'data_type' => $documentMetadata->getDataType(),
                'new_value' => $documentMetadata->getMetaValue(),
                'method' => __METHOD__,
            ]);

            // Set updated timestamp
            $oldUpdatedAt = $documentMetadata->getUpdatedAt();
            $documentMetadata->setUpdatedAt(new DateTimeImmutable());

            $this->logger->debug('Updated timestamp changed', [
                'old_updated_at' => $oldUpdatedAt?->format('Y-m-d H:i:s'),
                'new_updated_at' => $documentMetadata->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]);

            // Validate metadata
            $this->logger->debug('Validating updated document metadata');
            $validation = $this->validateDocumentMetadata($documentMetadata);
            if (!$validation['valid']) {
                $this->logger->warning('Document metadata update validation failed', [
                    'validation_error' => $validation['error'],
                    'metadata_id' => $documentMetadata->getId(),
                    'key' => $documentMetadata->getMetaKey(),
                ]);

                return ['success' => false, 'error' => $validation['error']];
            }

            $this->logger->debug('Document metadata update validation passed');

            // Validate value according to data type
            $this->logger->debug('Validating updated metadata value according to data type');
            $valueValidation = $this->validateMetadataValue($documentMetadata);
            if (!$valueValidation['valid']) {
                $this->logger->warning('Metadata value update validation failed', [
                    'validation_error' => $valueValidation['error'],
                    'metadata_id' => $documentMetadata->getId(),
                    'value' => $documentMetadata->getMetaValue(),
                    'data_type' => $documentMetadata->getDataType(),
                ]);

                return ['success' => false, 'error' => $valueValidation['error']];
            }

            $this->logger->debug('Metadata value update validation passed');

            $this->logger->debug('Flushing metadata changes to database');
            $this->entityManager->flush();

            $this->logger->info('Document metadata updated successfully', [
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'updated_value' => $documentMetadata->getMetaValue(),
                'data_type' => $documentMetadata->getDataType(),
            ]);

            return ['success' => true, 'metadata' => $documentMetadata];
        } catch (Exception $e) {
            $this->logger->error('Failed to update document metadata', [
                'error' => $e->getMessage(),
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'document_id' => $documentMetadata->getDocument()?->getId(),
                'attempted_value' => $documentMetadata->getMetaValue(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la modification de la métadonnée: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a document metadata.
     */
    public function deleteDocumentMetadata(DocumentMetadata $documentMetadata): array
    {
        try {
            $metadataKey = $documentMetadata->getMetaKey();
            $metadataId = $documentMetadata->getId();
            $documentId = $documentMetadata->getDocument()?->getId();
            $documentTitle = $documentMetadata->getDocument()?->getTitle();

            $this->logger->info('Starting document metadata deletion', [
                'metadata_id' => $metadataId,
                'key' => $metadataKey,
                'document_id' => $documentId,
                'document_title' => $documentTitle,
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Removing metadata from entity manager');
            $this->entityManager->remove($documentMetadata);

            $this->logger->debug('Flushing metadata deletion to database');
            $this->entityManager->flush();

            $this->logger->info('Document metadata deleted successfully', [
                'metadata_id' => $metadataId,
                'key' => $metadataKey,
                'document_id' => $documentId,
                'document_title' => $documentTitle,
            ]);

            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error('Failed to delete document metadata', [
                'error' => $e->getMessage(),
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'document_id' => $documentMetadata->getDocument()?->getId(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la suppression de la métadonnée: ' . $e->getMessage()];
        }
    }

    /**
     * Bulk delete metadata by IDs.
     */
    public function bulkDeleteMetadata(array $ids): array
    {
        try {
            $this->logger->info('Starting bulk metadata deletion', [
                'ids' => $ids,
                'ids_count' => count($ids),
                'method' => __METHOD__,
            ]);

            if (empty($ids)) {
                $this->logger->warning('Bulk delete called with empty IDs array');

                return ['success' => false, 'error' => 'Aucun ID fourni pour la suppression en masse.'];
            }

            // Log existing metadata before deletion for audit purposes
            $existingMetadata = $this->documentMetadataRepository->createQueryBuilder('dm')
                ->select('dm.id, dm.metaKey, d.title as document_title')
                ->leftJoin('dm.document', 'd')
                ->where('dm.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult()
            ;

            $this->logger->debug('Found metadata for bulk deletion', [
                'found_metadata' => $existingMetadata,
                'found_count' => count($existingMetadata),
            ]);

            $qb = $this->documentMetadataRepository->createQueryBuilder('dm')
                ->delete()
                ->where('dm.id IN (:ids)')
                ->setParameter('ids', $ids)
            ;

            $this->logger->debug('Executing bulk delete query', [
                'dql' => $qb->getDQL(),
                'parameters' => $qb->getParameters()->toArray(),
            ]);

            $deletedCount = $qb->getQuery()->execute();

            $this->logger->info('Bulk metadata deletion completed successfully', [
                'deleted_count' => $deletedCount,
                'requested_ids' => $ids,
                'requested_count' => count($ids),
            ]);

            if ($deletedCount < count($ids)) {
                $this->logger->warning('Some metadata could not be deleted', [
                    'requested_count' => count($ids),
                    'deleted_count' => $deletedCount,
                    'missing_count' => count($ids) - $deletedCount,
                ]);
            }

            return ['success' => true, 'deleted_count' => $deletedCount];
        } catch (Exception $e) {
            $this->logger->error('Failed to bulk delete document metadata', [
                'error' => $e->getMessage(),
                'ids' => $ids,
                'ids_count' => count($ids),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return ['success' => false, 'error' => 'Erreur lors de la suppression en masse: ' . $e->getMessage()];
        }
    }

    /**
     * Export metadata to CSV.
     */
    public function exportMetadataToCSV(array $filters = []): array
    {
        try {
            $this->logger->info('Starting metadata CSV export', [
                'filters' => $filters,
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Retrieving metadata for CSV export');
            $metadata = $this->getMetadataWithStats($filters);
            $metadataCount = count($metadata);

            $this->logger->debug('Metadata retrieved for CSV export', [
                'metadata_count' => $metadataCount,
            ]);

            $csvContent = "ID,Document,Clé,Valeur,Type,Requis,Recherchable,Modifiable,Créé le\n";
            $processedRows = 0;

            foreach ($metadata as $item) {
                try {
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
                        $meta->getCreatedAt()?->format('d/m/Y H:i:s') ?? '',
                    );
                    $processedRows++;
                } catch (Exception $rowException) {
                    $this->logger->warning('Error processing metadata row for CSV export', [
                        'metadata_id' => $item['metadata']?->getId(),
                        'error' => $rowException->getMessage(),
                    ]);
                    // Continue processing other rows
                }
            }

            $this->logger->debug('CSV content generated', [
                'processed_rows' => $processedRows,
                'content_length' => strlen($csvContent),
            ]);

            $response = new Response($csvContent);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $fileName = 'metadata_export_' . date('Y-m-d_H-i-s') . '.csv';
            $response->headers->set(
                'Content-Disposition',
                $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $fileName,
                ),
            );

            $this->logger->info('Metadata CSV export completed successfully', [
                'exported_rows' => $processedRows,
                'file_name' => $fileName,
                'content_size_bytes' => strlen($csvContent),
                'filters_applied' => array_keys(array_filter($filters)),
            ]);

            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            $this->logger->error('Failed to export metadata to CSV', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return ['success' => false, 'error' => 'Erreur lors de l\'export CSV: ' . $e->getMessage()];
        }
    }

    /**
     * Get statistics for a specific metadata key.
     */
    public function getMetadataStatisticsByKey(string $key): array
    {
        try {
            $this->logger->info('Starting metadata statistics calculation by key', [
                'key' => $key,
                'method' => __METHOD__,
            ]);

            $this->logger->debug('Retrieving metadata for specific key');
            $metadata = $this->documentMetadataRepository->findBy(['metaKey' => $key]);
            $metadataCount = count($metadata);

            $this->logger->debug('Metadata retrieved for key statistics', [
                'metadata_count' => $metadataCount,
                'key' => $key,
            ]);

            $stats = [
                'total_count' => $metadataCount,
                'data_types' => [],
                'value_distribution' => [],
                'documents_count' => 0,
            ];

            $uniqueDocuments = [];
            $valueCount = [];

            foreach ($metadata as $meta) {
                try {
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
                } catch (Exception $metaException) {
                    $this->logger->warning('Error processing metadata item for statistics', [
                        'metadata_id' => $meta->getId(),
                        'key' => $key,
                        'error' => $metaException->getMessage(),
                    ]);
                    // Continue processing other items
                }
            }

            $stats['documents_count'] = count($uniqueDocuments);

            // Sort value distribution by count
            arsort($valueCount);
            $stats['value_distribution'] = array_slice($valueCount, 0, 10, true);

            $this->logger->info('Metadata statistics by key calculated successfully', [
                'key' => $key,
                'total_metadata_count' => $stats['total_count'],
                'unique_documents_count' => $stats['documents_count'],
                'data_types_count' => count($stats['data_types']),
                'top_values_count' => count($stats['value_distribution']),
            ]);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate metadata statistics by key', [
                'error' => $e->getMessage(),
                'key' => $key,
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw new Exception('Erreur lors du calcul des statistiques pour la clé: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get available metadata keys with optional search.
     *
     * Returns all unique metadata keys currently in use, along with
     * their usage statistics. Used for autocomplete functionality
     * in admin forms to help users choose consistent key names.
     *
     * @param string $search Optional search term to filter keys
     *
     * @return array Array of associative arrays with 'key' and 'usage_count'
     */
    public function getAvailableMetadataKeys(string $search = ''): array
    {
        try {
            $this->logger->info('Starting available metadata keys retrieval', [
                'search' => $search,
                'method' => __METHOD__,
            ]);

            $qb = $this->documentMetadataRepository->createQueryBuilder('dm')
                ->select('DISTINCT dm.metaKey as key, COUNT(dm.id) as usage_count')
                ->groupBy('dm.metaKey')
                ->orderBy('usage_count', 'DESC')
            ;

            if (!empty($search)) {
                $this->logger->debug('Applying search filter to metadata keys', [
                    'search_term' => $search,
                ]);
                $qb->where('dm.metaKey LIKE :search')
                    ->setParameter('search', '%' . $search . '%')
                ;
            }

            $this->logger->debug('Executing available metadata keys query', [
                'dql' => $qb->getDQL(),
                'parameters' => $qb->getParameters()->toArray(),
            ]);

            $result = $qb->getQuery()->getResult();

            $this->logger->info('Available metadata keys retrieved successfully', [
                'keys_count' => count($result),
                'search_applied' => !empty($search),
                'search_term' => $search,
            ]);

            $this->logger->debug('Available metadata keys details', [
                'keys' => array_column($result, 'key'),
                'total_usage' => array_sum(array_column($result, 'usage_count')),
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve available metadata keys', [
                'error' => $e->getMessage(),
                'search' => $search,
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw new Exception('Erreur lors de la récupération des clés de métadonnées disponibles: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get document by ID.
     */
    public function getDocumentById(int $documentId): ?Document
    {
        try {
            $this->logger->info('Retrieving document by ID', [
                'document_id' => $documentId,
                'method' => __METHOD__,
            ]);

            $document = $this->documentRepository->find($documentId);

            if ($document) {
                $this->logger->debug('Document found successfully', [
                    'document_id' => $documentId,
                    'document_title' => $document->getTitle(),
                    'document_type' => $document->getDocumentType()?->getName(),
                ]);
            } else {
                $this->logger->warning('Document not found', [
                    'document_id' => $documentId,
                ]);
            }

            return $document;
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve document by ID', [
                'error' => $e->getMessage(),
                'document_id' => $documentId,
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            throw new Exception('Erreur lors de la récupération du document: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate document metadata.
     */
    private function validateDocumentMetadata(DocumentMetadata $documentMetadata): array
    {
        try {
            $this->logger->debug('Starting document metadata validation', [
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'document_id' => $documentMetadata->getDocument()?->getId(),
                'method' => __METHOD__,
            ]);

            if (empty($documentMetadata->getMetaKey())) {
                $this->logger->warning('Metadata validation failed: empty key', [
                    'metadata_id' => $documentMetadata->getId(),
                ]);

                return ['valid' => false, 'error' => 'La clé de métadonnée est requise.'];
            }

            if (!$documentMetadata->getDocument()) {
                $this->logger->warning('Metadata validation failed: no document', [
                    'metadata_id' => $documentMetadata->getId(),
                    'key' => $documentMetadata->getMetaKey(),
                ]);

                return ['valid' => false, 'error' => 'Le document associé est requis.'];
            }

            // Check for duplicate keys within the same document
            $this->logger->debug('Checking for duplicate metadata keys in document');
            $existingMetadata = $this->documentMetadataRepository->findOneBy([
                'document' => $documentMetadata->getDocument(),
                'metaKey' => $documentMetadata->getMetaKey(),
            ]);

            if ($existingMetadata && $existingMetadata->getId() !== $documentMetadata->getId()) {
                $this->logger->warning('Metadata validation failed: duplicate key in document', [
                    'metadata_id' => $documentMetadata->getId(),
                    'key' => $documentMetadata->getMetaKey(),
                    'document_id' => $documentMetadata->getDocument()->getId(),
                    'existing_metadata_id' => $existingMetadata->getId(),
                ]);

                return ['valid' => false, 'error' => 'Cette clé de métadonnée existe déjà pour ce document.'];
            }

            $this->logger->debug('Document metadata validation passed', [
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'document_id' => $documentMetadata->getDocument()->getId(),
            ]);

            return ['valid' => true];
        } catch (Exception $e) {
            $this->logger->error('Error during document metadata validation', [
                'error' => $e->getMessage(),
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return ['valid' => false, 'error' => 'Erreur lors de la validation: ' . $e->getMessage()];
        }
    }

    /**
     * Validate metadata value according to its data type.
     */
    private function validateMetadataValue(DocumentMetadata $documentMetadata): array
    {
        try {
            $value = $documentMetadata->getMetaValue();
            $dataType = $documentMetadata->getDataType();

            $this->logger->debug('Starting metadata value validation', [
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'value' => $value,
                'data_type' => $dataType,
                'is_required' => $documentMetadata->isRequired(),
                'method' => __METHOD__,
            ]);

            // Required check
            if ($documentMetadata->isRequired() && empty($value)) {
                $this->logger->warning('Metadata value validation failed: required field is empty', [
                    'metadata_id' => $documentMetadata->getId(),
                    'key' => $documentMetadata->getMetaKey(),
                    'data_type' => $dataType,
                ]);

                return ['valid' => false, 'error' => 'Cette métadonnée est obligatoire.'];
            }

            // Type validation
            if (!empty($value)) {
                $this->logger->debug('Validating value according to data type', [
                    'data_type' => $dataType,
                    'value_length' => strlen($value),
                ]);

                switch ($dataType) {
                    case DocumentMetadata::TYPE_INTEGER:
                        if (!is_numeric($value) || (int) $value !== $value) {
                            $this->logger->warning('Integer validation failed', [
                                'value' => $value,
                                'metadata_id' => $documentMetadata->getId(),
                            ]);

                            return ['valid' => false, 'error' => 'La valeur doit être un nombre entier.'];
                        }
                        break;

                    case DocumentMetadata::TYPE_FLOAT:
                        if (!is_numeric($value)) {
                            $this->logger->warning('Float validation failed', [
                                'value' => $value,
                                'metadata_id' => $documentMetadata->getId(),
                            ]);

                            return ['valid' => false, 'error' => 'La valeur doit être un nombre décimal.'];
                        }
                        break;

                    case DocumentMetadata::TYPE_BOOLEAN:
                        $allowedValues = ['true', 'false', '1', '0', 'yes', 'no', 'oui', 'non'];
                        if (!in_array(strtolower($value), $allowedValues, true)) {
                            $this->logger->warning('Boolean validation failed', [
                                'value' => $value,
                                'allowed_values' => $allowedValues,
                                'metadata_id' => $documentMetadata->getId(),
                            ]);

                            return ['valid' => false, 'error' => 'La valeur doit être un booléen (true/false, 1/0, oui/non).'];
                        }
                        break;

                    case DocumentMetadata::TYPE_DATE:
                        try {
                            $dateObj = new DateTime($value);
                            $this->logger->debug('Date validation successful', [
                                'value' => $value,
                                'parsed_date' => $dateObj->format('Y-m-d'),
                            ]);
                        } catch (Exception $dateException) {
                            $this->logger->warning('Date validation failed', [
                                'value' => $value,
                                'error' => $dateException->getMessage(),
                                'metadata_id' => $documentMetadata->getId(),
                            ]);

                            return ['valid' => false, 'error' => 'La valeur doit être une date valide.'];
                        }
                        break;

                    case DocumentMetadata::TYPE_DATETIME:
                        try {
                            $dateTimeObj = new DateTime($value);
                            $this->logger->debug('DateTime validation successful', [
                                'value' => $value,
                                'parsed_datetime' => $dateTimeObj->format('Y-m-d H:i:s'),
                            ]);
                        } catch (Exception $dateTimeException) {
                            $this->logger->warning('DateTime validation failed', [
                                'value' => $value,
                                'error' => $dateTimeException->getMessage(),
                                'metadata_id' => $documentMetadata->getId(),
                            ]);

                            return ['valid' => false, 'error' => 'La valeur doit être une date et heure valide.'];
                        }
                        break;

                    case DocumentMetadata::TYPE_JSON:
                        $decodedJson = json_decode($value);
                        $jsonError = json_last_error();
                        if ($jsonError !== JSON_ERROR_NONE) {
                            $this->logger->warning('JSON validation failed', [
                                'value' => $value,
                                'json_error' => json_last_error_msg(),
                                'json_error_code' => $jsonError,
                                'metadata_id' => $documentMetadata->getId(),
                            ]);

                            return ['valid' => false, 'error' => 'La valeur doit être un JSON valide.'];
                        }
                        $this->logger->debug('JSON validation successful', [
                            'value_length' => strlen($value),
                            'decoded_type' => gettype($decodedJson),
                        ]);
                        break;

                    case DocumentMetadata::TYPE_URL:
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $this->logger->warning('URL validation failed', [
                                'value' => $value,
                                'metadata_id' => $documentMetadata->getId(),
                            ]);

                            return ['valid' => false, 'error' => 'La valeur doit être une URL valide.'];
                        }
                        $this->logger->debug('URL validation successful', [
                            'url' => $value,
                        ]);
                        break;
                }
            }

            $this->logger->debug('Metadata value validation completed successfully', [
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'data_type' => $dataType,
                'value_is_empty' => empty($value),
            ]);

            return ['valid' => true];
        } catch (Exception $e) {
            $this->logger->error('Error during metadata value validation', [
                'error' => $e->getMessage(),
                'metadata_id' => $documentMetadata->getId(),
                'key' => $documentMetadata->getMetaKey(),
                'value' => $documentMetadata->getMetaValue(),
                'data_type' => $documentMetadata->getDataType(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            return ['valid' => false, 'error' => 'Erreur lors de la validation de la valeur: ' . $e->getMessage()];
        }
    }
}
