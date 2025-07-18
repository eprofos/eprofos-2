<?php

namespace App\Repository;

use App\Entity\LegalDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for LegalDocument entity
 * 
 * Provides query methods for legal document management
 * with filtering and search capabilities.
 * 
 * @extends ServiceEntityRepository<LegalDocument>
 */
class LegalDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalDocument::class);
    }

    /**
     * Find published documents by type
     * 
     * @return LegalDocument[]
     */
    public function findPublishedByType(string $type): array
    {
        return $this->createQueryBuilder('ld')
            ->where('ld.type = :type')
            ->andWhere('ld.status = :status')
            ->andWhere('ld.isActive = :active')
            ->andWhere('ld.publishedAt IS NOT NULL')
            ->andWhere('ld.publishedAt <= :now')
            ->setParameter('type', $type)
            ->setParameter('status', LegalDocument::STATUS_PUBLISHED)
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('ld.version', 'DESC')
            ->orderBy('ld.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest published document by type
     */
    public function findLatestPublishedByType(string $type): ?LegalDocument
    {
        $documents = $this->findPublishedByType($type);
        return !empty($documents) ? $documents[0] : null;
    }

    /**
     * Find all published documents
     * 
     * @return LegalDocument[]
     */
    public function findAllPublished(): array
    {
        return $this->createQueryBuilder('ld')
            ->where('ld.status = :status')
            ->andWhere('ld.isActive = :active')
            ->andWhere('ld.publishedAt IS NOT NULL')
            ->andWhere('ld.publishedAt <= :now')
            ->setParameter('status', LegalDocument::STATUS_PUBLISHED)
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('ld.type', 'ASC')
            ->addOrderBy('ld.version', 'DESC')
            ->addOrderBy('ld.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Create admin query builder with filters
     */
    public function createAdminQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('ld');

        // Search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('ld.title LIKE :search OR ld.content LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Type filter
        if (!empty($filters['type'])) {
            $qb->andWhere('ld.type = :type')
               ->setParameter('type', $filters['type']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'published':
                    $qb->andWhere('ld.status = :status')
                       ->setParameter('status', LegalDocument::STATUS_PUBLISHED);
                    break;
                case 'draft':
                    $qb->andWhere('ld.status = :status')
                       ->setParameter('status', LegalDocument::STATUS_DRAFT);
                    break;
                case 'archived':
                    $qb->andWhere('ld.status = :status')
                       ->setParameter('status', LegalDocument::STATUS_ARCHIVED);
                    break;
                case 'inactive':
                    $qb->andWhere('ld.isActive = :active')
                       ->setParameter('active', false);
                    break;
            }
        }

        return $qb->orderBy('ld.updatedAt', 'DESC');
    }

    /**
     * Find documents by search term
     * 
     * @return LegalDocument[]
     */
    public function findBySearchTerm(string $search): array
    {
        return $this->createQueryBuilder('ld')
            ->where('ld.title LIKE :search OR ld.content LIKE :search')
            ->andWhere('ld.isActive = :active')
            ->setParameter('search', '%' . $search . '%')
            ->setParameter('active', true)
            ->orderBy('ld.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count documents by type
     */
    public function countByType(string $type): int
    {
        return $this->createQueryBuilder('ld')
            ->select('COUNT(ld.id)')
            ->where('ld.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get statistics for admin dashboard
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('ld');
        
        $totalDocuments = $qb->select('COUNT(ld.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('ld');
        $publishedDocuments = $qb->select('COUNT(ld.id)')
            ->where('ld.status = :status')
            ->setParameter('status', LegalDocument::STATUS_PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('ld');
        $draftDocuments = $qb->select('COUNT(ld.id)')
            ->where('ld.status = :status')
            ->setParameter('status', LegalDocument::STATUS_DRAFT)
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('ld');
        $archivedDocuments = $qb->select('COUNT(ld.id)')
            ->where('ld.status = :status')
            ->setParameter('status', LegalDocument::STATUS_ARCHIVED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalDocuments,
            'published' => $publishedDocuments,
            'drafts' => $draftDocuments,
            'archived' => $archivedDocuments,
            'types' => $this->countByTypes(),
        ];
    }

    /**
     * Get statistics for a specific document type
     */
    public function getTypeStatistics(string $type): array
    {
        $qb = $this->createQueryBuilder('ld');
        
        $totalDocuments = $qb->select('COUNT(ld.id)')
            ->where('ld.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('ld');
        $publishedDocuments = $qb->select('COUNT(ld.id)')
            ->where('ld.type = :type')
            ->andWhere('ld.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', LegalDocument::STATUS_PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('ld');
        $draftDocuments = $qb->select('COUNT(ld.id)')
            ->where('ld.type = :type')
            ->andWhere('ld.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', LegalDocument::STATUS_DRAFT)
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('ld');
        $archivedDocuments = $qb->select('COUNT(ld.id)')
            ->where('ld.type = :type')
            ->andWhere('ld.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', LegalDocument::STATUS_ARCHIVED)
            ->getQuery()
            ->getSingleScalarResult();

        // Get latest published version
        $latestPublished = $this->findLatestPublishedByType($type);

        return [
            'type' => $type,
            'total' => $totalDocuments,
            'published' => $publishedDocuments,
            'drafts' => $draftDocuments,
            'archived' => $archivedDocuments,
            'latest_published' => $latestPublished,
        ];
    }

    /**
     * Archive all documents of a specific type except the provided one
     * 
     * This ensures only one document of each type can be published at a time
     */
    public function archiveOtherDocumentsOfType(string $type, ?int $excludeId = null): int
    {
        $qb = $this->createQueryBuilder('ld')
            ->update()
            ->set('ld.status', ':archivedStatus')
            ->where('ld.type = :type')
            ->andWhere('ld.status = :publishedStatus')
            ->setParameter('type', $type)
            ->setParameter('publishedStatus', LegalDocument::STATUS_PUBLISHED)
            ->setParameter('archivedStatus', LegalDocument::STATUS_ARCHIVED);

        if ($excludeId !== null) {
            $qb->andWhere('ld.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @deprecated Use archiveOtherDocumentsOfType instead
     */
    public function unpublishOtherDocumentsOfType(string $type, ?int $excludeId = null): int
    {
        return $this->archiveOtherDocumentsOfType($type, $excludeId);
    }

    /**
     * Find all published documents of a specific type except the provided one
     * 
     * @return LegalDocument[]
     */
    public function findOtherPublishedDocumentsOfType(string $type, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('ld')
            ->where('ld.type = :type')
            ->andWhere('ld.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', LegalDocument::STATUS_PUBLISHED);

        if ($excludeId !== null) {
            $qb->andWhere('ld.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->orderBy('ld.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count documents by all types
     */
    private function countByTypes(): array
    {
        $results = $this->createQueryBuilder('ld')
            ->select('ld.type, COUNT(ld.id) as count')
            ->groupBy('ld.type')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['type']] = $result['count'];
        }

        return $counts;
    }

    public function save(LegalDocument $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LegalDocument $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
