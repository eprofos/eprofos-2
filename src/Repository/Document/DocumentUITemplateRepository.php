<?php

namespace App\Repository\Document;

use App\Entity\Document\DocumentUITemplate;
use App\Entity\Document\DocumentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentUITemplate>
 */
class DocumentUITemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentUITemplate::class);
    }

    /**
     * Find all active UI templates
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('dut')
            ->where('dut.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dut.sortOrder', 'ASC')
            ->addOrderBy('dut.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find UI templates by document type
     */
    public function findByDocumentType(?DocumentType $documentType): array
    {
        $qb = $this->createQueryBuilder('dut')
            ->where('dut.isActive = :active')
            ->setParameter('active', true);

        if ($documentType) {
            $qb->andWhere('(dut.documentType = :type OR dut.isGlobal = :global)')
              ->setParameter('type', $documentType)
              ->setParameter('global', true);
        } else {
            $qb->andWhere('dut.isGlobal = :global')
              ->setParameter('global', true);
        }

        return $qb->orderBy('dut.sortOrder', 'ASC')
                 ->addOrderBy('dut.name', 'ASC')
                 ->getQuery()
                 ->getResult();
    }

    /**
     * Find global UI templates
     */
    public function findGlobal(): array
    {
        return $this->createQueryBuilder('dut')
            ->where('dut.isGlobal = :global')
            ->andWhere('dut.isActive = :active')
            ->setParameter('global', true)
            ->setParameter('active', true)
            ->orderBy('dut.sortOrder', 'ASC')
            ->addOrderBy('dut.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find default template for document type
     */
    public function findDefaultForType(?DocumentType $documentType): ?DocumentUITemplate
    {
        $qb = $this->createQueryBuilder('dut')
            ->where('dut.isDefault = :default')
            ->andWhere('dut.isActive = :active')
            ->setParameter('default', true)
            ->setParameter('active', true);

        if ($documentType) {
            $qb->andWhere('(dut.documentType = :type OR dut.isGlobal = :global)')
              ->setParameter('type', $documentType)
              ->setParameter('global', true)
              ->orderBy('dut.isGlobal', 'ASC'); // Prefer type-specific over global
        } else {
            $qb->andWhere('dut.isGlobal = :global')
              ->setParameter('global', true);
        }

        return $qb->setMaxResults(1)
                 ->getQuery()
                 ->getOneOrNullResult();
    }

    /**
     * Find templates with usage statistics
     */
    public function findWithStats(): array
    {
        return $this->createQueryBuilder('dut')
            ->leftJoin('dut.components', 'c')
            ->addSelect('COUNT(c.id) as componentCount')
            ->groupBy('dut.id')
            ->orderBy('dut.sortOrder', 'ASC')
            ->addOrderBy('dut.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find templates by search criteria
     */
    public function findBySearchCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('dut');

        if (!empty($criteria['search'])) {
            $qb->andWhere('(dut.name LIKE :search OR dut.description LIKE :search)')
              ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (!empty($criteria['type'])) {
            $qb->andWhere('dut.documentType = :type')
              ->setParameter('type', $criteria['type']);
        }

        if (isset($criteria['active'])) {
            $qb->andWhere('dut.isActive = :active')
              ->setParameter('active', $criteria['active']);
        }

        if (isset($criteria['global'])) {
            $qb->andWhere('dut.isGlobal = :global')
              ->setParameter('global', $criteria['global']);
        }

        if (!empty($criteria['orientation'])) {
            $qb->andWhere('dut.orientation = :orientation')
              ->setParameter('orientation', $criteria['orientation']);
        }

        if (!empty($criteria['paperSize'])) {
            $qb->andWhere('dut.paperSize = :paperSize')
              ->setParameter('paperSize', $criteria['paperSize']);
        }

        return $qb->orderBy('dut.sortOrder', 'ASC')
                 ->addOrderBy('dut.name', 'ASC')
                 ->getQuery()
                 ->getResult();
    }

    /**
     * Find most used templates
     */
    public function findMostUsed(int $limit = 10): array
    {
        return $this->createQueryBuilder('dut')
            ->where('dut.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dut.usageCount', 'DESC')
            ->addOrderBy('dut.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next sort order
     */
    public function getNextSortOrder(): int
    {
        $result = $this->createQueryBuilder('dut')
            ->select('MAX(dut.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$result + 1;
    }

    /**
     * Count templates by type
     */
    public function countByType(?DocumentType $documentType = null): int
    {
        $qb = $this->createQueryBuilder('dut')
            ->select('COUNT(dut.id)');

        if ($documentType) {
            $qb->where('dut.documentType = :type')
              ->setParameter('type', $documentType);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find templates by slug
     */
    public function findBySlug(string $slug): ?DocumentUITemplate
    {
        return $this->createQueryBuilder('dut')
            ->where('dut.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find similar templates by name
     */
    public function findSimilarByName(string $name, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('dut')
            ->where('dut.name LIKE :name')
            ->setParameter('name', '%' . $name . '%');

        if ($excludeId) {
            $qb->andWhere('dut.id != :excludeId')
              ->setParameter('excludeId', $excludeId);
        }

        return $qb->orderBy('dut.name', 'ASC')
                 ->setMaxResults(10)
                 ->getQuery()
                 ->getResult();
    }

    /**
     * Find templates created by user
     */
    public function findByCreatedBy($user): array
    {
        return $this->createQueryBuilder('dut')
            ->where('dut.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('dut.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently updated templates
     */
    public function findRecentlyUpdated(int $limit = 10): array
    {
        return $this->createQueryBuilder('dut')
            ->where('dut.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dut.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
