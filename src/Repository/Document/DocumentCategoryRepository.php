<?php

declare(strict_types=1);

namespace App\Repository\Document;

use App\Entity\Document\DocumentCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentCategory>
 */
class DocumentCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentCategory::class);
    }

    /**
     * Find all root categories (no parent) ordered by sort order.
     */
    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('dc')
            ->where('dc.parent IS NULL')
            ->andWhere('dc.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dc.sortOrder', 'ASC')
            ->addOrderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find all categories with children (hierarchical tree).
     */
    public function findCategoryTree(): array
    {
        $categories = $this->createQueryBuilder('dc')
            ->where('dc.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dc.level', 'ASC')
            ->addOrderBy('dc.sortOrder', 'ASC')
            ->addOrderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $this->buildTree($categories);
    }

    /**
     * Find category by slug.
     */
    public function findBySlug(string $slug): ?DocumentCategory
    {
        return $this->createQueryBuilder('dc')
            ->where('dc.slug = :slug')
            ->andWhere('dc.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find categories at specific level.
     */
    public function findByLevel(int $level): array
    {
        return $this->createQueryBuilder('dc')
            ->where('dc.level = :level')
            ->andWhere('dc.isActive = :active')
            ->setParameter('level', $level)
            ->setParameter('active', true)
            ->orderBy('dc.sortOrder', 'ASC')
            ->addOrderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find children of a specific category.
     */
    public function findChildren(DocumentCategory $parent): array
    {
        return $this->createQueryBuilder('dc')
            ->where('dc.parent = :parent')
            ->andWhere('dc.isActive = :active')
            ->setParameter('parent', $parent)
            ->setParameter('active', true)
            ->orderBy('dc.sortOrder', 'ASC')
            ->addOrderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find all categories with document counts.
     */
    public function findWithDocumentCounts(): array
    {
        return $this->createQueryBuilder('dc')
            ->select('dc, COUNT(d.id) as documentCount')
            ->leftJoin('dc.documents', 'd')
            ->where('dc.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('dc.id')
            ->orderBy('dc.level', 'ASC')
            ->addOrderBy('dc.sortOrder', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find categories that have documents.
     */
    public function findWithDocuments(): array
    {
        return $this->createQueryBuilder('dc')
            ->where('dc.isActive = :active')
            ->andWhere('SIZE(dc.documents) > 0')
            ->setParameter('active', true)
            ->orderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Search categories by name.
     */
    public function searchByName(string $search): array
    {
        return $this->createQueryBuilder('dc')
            ->where('dc.isActive = :active')
            ->andWhere('LOWER(dc.name) LIKE LOWER(:search)')
            ->setParameter('active', true)
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get category path by slug (breadcrumb).
     */
    public function getCategoryPath(string $slug): array
    {
        $category = $this->findBySlug($slug);

        if (!$category) {
            return [];
        }

        return $category->getPath();
    }

    /**
     * Get next sort order for a parent category.
     */
    public function getNextSortOrder(?DocumentCategory $parent = null): int
    {
        $qb = $this->createQueryBuilder('dc')
            ->select('MAX(dc.sortOrder)')
            ->where('dc.isActive = :active')
            ->setParameter('active', true)
        ;

        if ($parent) {
            $qb->andWhere('dc.parent = :parent')
                ->setParameter('parent', $parent)
            ;
        } else {
            $qb->andWhere('dc.parent IS NULL');
        }

        $maxOrder = $qb->getQuery()->getSingleScalarResult();

        return ($maxOrder ?? 0) + 1;
    }

    public function save(DocumentCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DocumentCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Build hierarchical tree from flat array.
     */
    private function buildTree(array $categories, ?DocumentCategory $parent = null): array
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category->getParent() === $parent) {
                $category->setChildren($this->buildTree($categories, $category));
                $tree[] = $category;
            }
        }

        return $tree;
    }
}
