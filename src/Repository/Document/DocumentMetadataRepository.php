<?php

declare(strict_types=1);

namespace App\Repository\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentMetadata;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentMetadata>
 */
class DocumentMetadataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentMetadata::class);
    }

    /**
     * Find all metadata for a document.
     */
    public function findByDocument(Document $document): array
    {
        return $this->createQueryBuilder('dm')
            ->where('dm.document = :document')
            ->setParameter('document', $document)
            ->orderBy('dm.metaKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find specific metadata by document and key.
     */
    public function findByDocumentAndKey(Document $document, string $key): ?DocumentMetadata
    {
        return $this->createQueryBuilder('dm')
            ->where('dm.document = :document')
            ->andWhere('dm.metaKey = :key')
            ->setParameter('document', $document)
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find metadata by key across all documents.
     */
    public function findByKey(string $key): array
    {
        return $this->createQueryBuilder('dm')
            ->where('dm.metaKey = :key')
            ->setParameter('key', $key)
            ->orderBy('dm.document', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find metadata by data type.
     */
    public function findByDataType(string $dataType): array
    {
        return $this->createQueryBuilder('dm')
            ->where('dm.dataType = :dataType')
            ->setParameter('dataType', $dataType)
            ->orderBy('dm.metaKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find metadata with specific string value.
     */
    public function findByStringValue(string $value): array
    {
        return $this->createQueryBuilder('dm')
            ->where('dm.dataType = :dataType')
            ->andWhere('dm.stringValue = :value')
            ->setParameter('dataType', DocumentMetadata::TYPE_STRING)
            ->setParameter('value', $value)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find metadata with integer value in range.
     */
    public function findByIntegerRange(int $min, int $max): array
    {
        return $this->createQueryBuilder('dm')
            ->where('dm.dataType = :dataType')
            ->andWhere('dm.integerValue BETWEEN :min AND :max')
            ->setParameter('dataType', DocumentMetadata::TYPE_INTEGER)
            ->setParameter('min', $min)
            ->setParameter('max', $max)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find metadata with date value in range.
     */
    public function findByDateRange(DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('dm')
            ->where('dm.dataType = :dataType')
            ->andWhere('dm.dateValue BETWEEN :start AND :end')
            ->setParameter('dataType', DocumentMetadata::TYPE_DATE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find metadata with boolean value.
     */
    public function findByBooleanValue(bool $value): array
    {
        return $this->createQueryBuilder('dm')
            ->where('dm.dataType = :dataType')
            ->andWhere('dm.booleanValue = :value')
            ->setParameter('dataType', DocumentMetadata::TYPE_BOOLEAN)
            ->setParameter('value', $value)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Search metadata by key pattern.
     */
    public function searchByKey(string $pattern): array
    {
        return $this->createQueryBuilder('dm')
            ->where('LOWER(dm.metaKey) LIKE LOWER(:pattern)')
            ->setParameter('pattern', '%' . $pattern . '%')
            ->orderBy('dm.metaKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get all unique metadata keys.
     */
    public function getUniqueKeys(): array
    {
        $result = $this->createQueryBuilder('dm')
            ->select('DISTINCT dm.metaKey')
            ->orderBy('dm.metaKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_column($result, 'metaKey');
    }

    /**
     * Get metadata statistics by data type.
     */
    public function getDataTypeStatistics(): array
    {
        $result = $this->createQueryBuilder('dm')
            ->select('dm.dataType, COUNT(dm.id) as count')
            ->groupBy('dm.dataType')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['dataType']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find documents by metadata criteria.
     *
     * @param mixed $value
     */
    public function findDocumentsByMetadata(string $key, $value, string $dataType = DocumentMetadata::TYPE_STRING): array
    {
        $qb = $this->createQueryBuilder('dm')
            ->select('DISTINCT d')
            ->join('dm.document', 'd')
            ->where('dm.metaKey = :key')
            ->andWhere('dm.dataType = :dataType')
            ->setParameter('key', $key)
            ->setParameter('dataType', $dataType)
        ;

        switch ($dataType) {
            case DocumentMetadata::TYPE_STRING:
                $qb->andWhere('dm.stringValue = :value');
                break;

            case DocumentMetadata::TYPE_INTEGER:
                $qb->andWhere('dm.integerValue = :value');
                break;

            case DocumentMetadata::TYPE_FLOAT:
                $qb->andWhere('dm.floatValue = :value');
                break;

            case DocumentMetadata::TYPE_BOOLEAN:
                $qb->andWhere('dm.booleanValue = :value');
                break;

            case DocumentMetadata::TYPE_DATE:
                $qb->andWhere('dm.dateValue = :value');
                break;
        }

        $qb->setParameter('value', $value);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get metadata summary for a document.
     */
    public function getDocumentMetadataSummary(Document $document): array
    {
        $metadata = $this->findByDocument($document);

        $summary = [
            'total' => count($metadata),
            'byType' => [],
            'keys' => [],
        ];

        foreach ($metadata as $meta) {
            $type = $meta->getDataType();
            if (!isset($summary['byType'][$type])) {
                $summary['byType'][$type] = 0;
            }
            $summary['byType'][$type]++;
            $summary['keys'][] = $meta->getMetaKey();
        }

        return $summary;
    }

    /**
     * Find metadata that needs validation.
     */
    public function findNeedingValidation(): array
    {
        return $this->createQueryBuilder('dm')
            ->where('dm.isValid = :isValid')
            ->setParameter('isValid', false)
            ->orderBy('dm.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Remove all metadata for a document.
     */
    public function removeByDocument(Document $document): int
    {
        return $this->createQueryBuilder('dm')
            ->delete()
            ->where('dm.document = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->execute()
        ;
    }

    public function save(DocumentMetadata $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DocumentMetadata $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
