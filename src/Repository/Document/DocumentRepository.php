<?php

declare(strict_types=1);

namespace App\Repository\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentCategory;
use App\Entity\Document\DocumentType;
use App\Entity\User\Admin;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Find all published documents.
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->andWhere('d.publishedAt IS NOT NULL')
            ->andWhere('d.publishedAt <= :now')
            ->setParameter('status', Document::STATUS_PUBLISHED)
            ->setParameter('now', new DateTime())
            ->orderBy('d.publishedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find documents by type.
     */
    public function findByType(DocumentType $type): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.documentType = :type')
            ->setParameter('type', $type)
            ->orderBy('d.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find documents by category.
     */
    public function findByCategory(DocumentCategory $category): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.category = :category')
            ->setParameter('category', $category)
            ->orderBy('d.publishedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find published documents by category.
     */
    public function findPublishedByCategory(DocumentCategory $category): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.category = :category')
            ->andWhere('d.status = :status')
            ->andWhere('d.publishedAt IS NOT NULL')
            ->andWhere('d.publishedAt <= :now')
            ->setParameter('category', $category)
            ->setParameter('status', Document::STATUS_PUBLISHED)
            ->setParameter('now', new DateTime())
            ->orderBy('d.publishedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find document by slug.
     */
    public function findBySlug(string $slug): ?Document
    {
        return $this->createQueryBuilder('d')
            ->where('d.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find published document by slug.
     */
    public function findPublishedBySlug(string $slug): ?Document
    {
        return $this->createQueryBuilder('d')
            ->where('d.slug = :slug')
            ->andWhere('d.status = :status')
            ->andWhere('d.publishedAt IS NOT NULL')
            ->andWhere('d.publishedAt <= :now')
            ->setParameter('slug', $slug)
            ->setParameter('status', Document::STATUS_PUBLISHED)
            ->setParameter('now', new DateTime())
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find documents by author.
     */
    public function findByAuthor(Admin $author): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.createdBy = :author')
            ->setParameter('author', $author)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find documents requiring approval.
     */
    public function findRequiringApproval(): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.documentType', 'dt')
            ->where('d.status = :status')
            ->andWhere('dt.requiresApproval = :requiresApproval')
            ->setParameter('status', Document::STATUS_REVIEW)
            ->setParameter('requiresApproval', true)
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find documents with expiring validity.
     */
    public function findExpiringDocuments(int $days = 30): array
    {
        $cutoffDate = new DateTime();
        $cutoffDate->modify("+{$days} days");

        return $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->andWhere('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt <= :cutoffDate')
            ->andWhere('d.expiresAt > :now')
            ->setParameter('status', Document::STATUS_PUBLISHED)
            ->setParameter('cutoffDate', $cutoffDate)
            ->setParameter('now', new DateTime())
            ->orderBy('d.expiresAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Search documents.
     */
    public function search(string $query, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', Document::STATUS_PUBLISHED)
        ;

        // Text search
        if (!empty($query)) {
            $qb->andWhere('LOWER(d.title) LIKE LOWER(:query) OR LOWER(d.description) LIKE LOWER(:query) OR LOWER(d.content) LIKE LOWER(:query)')
                ->setParameter('query', '%' . $query . '%')
            ;
        }

        // Type filter
        if (!empty($filters['type'])) {
            $qb->andWhere('d.documentType = :type')
                ->setParameter('type', $filters['type'])
            ;
        }

        // Category filter
        if (!empty($filters['category'])) {
            $qb->andWhere('d.category = :category')
                ->setParameter('category', $filters['category'])
            ;
        }

        // Date range filter
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('d.publishedAt >= :dateFrom')
                ->setParameter('dateFrom', $filters['dateFrom'])
            ;
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('d.publishedAt <= :dateTo')
                ->setParameter('dateTo', $filters['dateTo'])
            ;
        }

        return $qb->orderBy('d.publishedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Create query builder for catalog with filters.
     */
    public function createCatalogQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.documentType', 'dt')
            ->leftJoin('d.category', 'dc')
            ->where('d.status = :status')
            ->andWhere('d.publishedAt IS NOT NULL')
            ->andWhere('d.publishedAt <= :now')
            ->setParameter('status', Document::STATUS_PUBLISHED)
            ->setParameter('now', new DateTime())
        ;

        if (!empty($filters['type'])) {
            $qb->andWhere('dt.id = :typeId')
                ->setParameter('typeId', $filters['type'])
            ;
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('dc.id = :categoryId')
                ->setParameter('categoryId', $filters['category'])
            ;
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('LOWER(d.title) LIKE LOWER(:search) OR LOWER(d.description) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $filters['search'] . '%')
            ;
        }

        return $qb->orderBy('d.publishedAt', 'DESC');
    }

    /**
     * Create query builder for admin with filters (all documents regardless of status).
     */
    public function createAdminQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.documentType', 'dt')
            ->leftJoin('d.category', 'dc')
            ->leftJoin('d.createdBy', 'cb')
        ;

        // Status filter
        if (!empty($filters['status'])) {
            $qb->andWhere('d.status = :status')
                ->setParameter('status', $filters['status'])
            ;
        }

        // Type filter
        if (!empty($filters['type'])) {
            $qb->andWhere('dt.id = :typeId')
                ->setParameter('typeId', $filters['type'])
            ;
        }

        // Category filter
        if (!empty($filters['category'])) {
            $qb->andWhere('dc.id = :categoryId')
                ->setParameter('categoryId', $filters['category'])
            ;
        }

        // Author filter
        if (!empty($filters['author'])) {
            $qb->andWhere('cb.id = :authorId')
                ->setParameter('authorId', $filters['author'])
            ;
        }

        // Search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('LOWER(d.title) LIKE LOWER(:search) OR LOWER(d.description) LIKE LOWER(:search) OR LOWER(d.content) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $filters['search'] . '%')
            ;
        }

        return $qb->orderBy('d.updatedAt', 'DESC');
    }

    /**
     * Get most recent documents.
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->andWhere('d.publishedAt IS NOT NULL')
            ->andWhere('d.publishedAt <= :now')
            ->setParameter('status', Document::STATUS_PUBLISHED)
            ->setParameter('now', new DateTime())
            ->orderBy('d.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get most popular documents by download count.
     */
    public function findMostPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->andWhere('d.publishedAt IS NOT NULL')
            ->andWhere('d.publishedAt <= :now')
            ->setParameter('status', Document::STATUS_PUBLISHED)
            ->setParameter('now', new DateTime())
            ->orderBy('d.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find documents with versions.
     */
    public function findWithVersions(): array
    {
        return $this->createQueryBuilder('d')
            ->where('SIZE(d.versions) > 0')
            ->orderBy('d.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get document statistics.
     */
    public function getStatistics(): array
    {
        $totalDocs = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $publishedDocs = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', Document::STATUS_PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $pendingDocs = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', Document::STATUS_REVIEW)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        // Skip download count for now until migration is run
        $totalDownloads = 0;

        return [
            'total' => $totalDocs,
            'published' => $publishedDocs,
            'pending' => $pendingDocs,
            'downloads' => $totalDownloads,
        ];
    }

    public function save(Document $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Document $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
