<?php

namespace App\Repository\Document;

use App\Entity\Document\DocumentTemplate;
use App\Entity\Document\DocumentType;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentTemplate>
 */
class DocumentTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentTemplate::class);
    }

    /**
     * Find all active templates
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates by type
     */
    public function findByType(DocumentType $type): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.documentType = :type')
            ->andWhere('dt.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find template by slug
     */
    public function findBySlug(string $slug): ?DocumentTemplate
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.slug = :slug')
            ->andWhere('dt.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find templates created by user
     */
    public function findByCreatedBy(User $user): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('dt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most used templates
     */
    public function findMostUsed(int $limit = 10): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dt.usageCount', 'DESC')
            ->addOrderBy('dt.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search templates by name or description
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->andWhere('LOWER(dt.name) LIKE LOWER(:query) OR LOWER(dt.description) LIKE LOWER(:query)')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates with placeholders
     */
    public function findWithPlaceholders(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->andWhere('dt.placeholders IS NOT NULL')
            ->andWhere('JSON_LENGTH(dt.placeholders) > 0')
            ->setParameter('active', true)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates by placeholder key
     */
    public function findByPlaceholder(string $placeholderKey): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->andWhere('JSON_SEARCH(dt.placeholders, "one", :key) IS NOT NULL')
            ->setParameter('active', true)
            ->setParameter('key', $placeholderKey)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get template statistics
     */
    public function getTemplateStatistics(): array
    {
        $totalTemplates = $this->createQueryBuilder('dt')
            ->select('COUNT(dt.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $activeTemplates = $this->createQueryBuilder('dt')
            ->select('COUNT(dt.id)')
            ->where('dt.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $totalUsage = $this->createQueryBuilder('dt')
            ->select('SUM(dt.usageCount)')
            ->where('dt.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $withPlaceholders = $this->createQueryBuilder('dt')
            ->select('COUNT(dt.id)')
            ->where('dt.isActive = :active')
            ->andWhere('dt.placeholders IS NOT NULL')
            ->andWhere('JSON_LENGTH(dt.placeholders) > 0')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalTemplates,
            'active' => $activeTemplates,
            'totalUsage' => $totalUsage ?? 0,
            'withPlaceholders' => $withPlaceholders,
        ];
    }

    /**
     * Find templates by document type statistics
     */
    public function getTypeStatistics(): array
    {
        $result = $this->createQueryBuilder('dt')
            ->select('doc_type.name as typeName, COUNT(dt.id) as count')
            ->join('dt.documentType', 'doc_type')
            ->where('dt.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('doc_type.id')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['typeName']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find unused templates
     */
    public function findUnused(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->andWhere('dt.usageCount = 0')
            ->setParameter('active', true)
            ->orderBy('dt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently created templates
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dt.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates updated since date
     */
    public function findUpdatedSince(\DateTime $since): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->andWhere('dt.updatedAt >= :since')
            ->setParameter('active', true)
            ->setParameter('since', $since)
            ->orderBy('dt.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get available placeholders across all templates
     */
    public function getAllPlaceholders(): array
    {
        $templates = $this->findWithPlaceholders();
        $allPlaceholders = [];

        foreach ($templates as $template) {
            $placeholders = $template->getPlaceholders();
            if ($placeholders) {
                foreach ($placeholders as $key => $config) {
                    if (!isset($allPlaceholders[$key])) {
                        $allPlaceholders[$key] = $config;
                    }
                }
            }
        }

        return $allPlaceholders;
    }

    public function save(DocumentTemplate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DocumentTemplate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
