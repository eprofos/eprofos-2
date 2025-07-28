<?php

namespace App\Repository\Training;

use App\Entity\Training\Formation;
use App\Entity\Training\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Formation entity
 * 
 * Provides advanced query methods for formation catalog with
 * search, filtering, sorting, and pagination capabilities.
 * 
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    /**
     * Find all active formations ordered by creation date
     * 
     * @return Formation[]
     */
    public function findActiveFormations(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.category', 'c')
            ->addSelect('c')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find featured formations for homepage
     * 
     * @return Formation[]
     */
    public function findFeaturedFormations(int $limit = 6): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.category', 'c')
            ->addSelect('c')
            ->where('f.isActive = :active')
            ->andWhere('f.isFeatured = :featured')
            ->setParameter('active', true)
            ->setParameter('featured', true)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find formation by slug with category
     */
    public function findBySlugWithCategory(string $slug): ?Formation
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.category', 'c')
            ->addSelect('c')
            ->where('f.slug = :slug')
            ->andWhere('f.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Create query builder for formation catalog with filters
     * 
     * @param array<string, mixed> $filters
     */
    public function createCatalogQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.category', 'c')
            ->addSelect('c')
            ->where('f.isActive = :active')
            ->setParameter('active', true);

        // Filter by category
        if (!empty($filters['category'])) {
            if ($filters['category'] instanceof Category) {
                $qb->andWhere('f.category = :category')
                   ->setParameter('category', $filters['category']);
            } elseif (is_string($filters['category'])) {
                $qb->andWhere('c.slug = :categorySlug')
                   ->setParameter('categorySlug', $filters['category']);
            }
        }

        // Filter by level
        if (!empty($filters['level'])) {
            $qb->andWhere('f.level = :level')
               ->setParameter('level', $filters['level']);
        }

        // Filter by format
        if (!empty($filters['format'])) {
            $qb->andWhere('f.format = :format')
               ->setParameter('format', $filters['format']);
        }

        // Filter by price range
        if (!empty($filters['minPrice'])) {
            $qb->andWhere('f.price >= :minPrice')
               ->setParameter('minPrice', $filters['minPrice']);
        }

        if (!empty($filters['maxPrice'])) {
            $qb->andWhere('f.price <= :maxPrice')
               ->setParameter('maxPrice', $filters['maxPrice']);
        }

        // Filter by duration range
        if (!empty($filters['minDuration'])) {
            $qb->andWhere('f.durationHours >= :minDuration')
               ->setParameter('minDuration', $filters['minDuration']);
        }

        if (!empty($filters['maxDuration'])) {
            $qb->andWhere('f.durationHours <= :maxDuration')
               ->setParameter('maxDuration', $filters['maxDuration']);
        }

        // Search in title and description
        if (!empty($filters['search'])) {
            $qb->andWhere('f.title LIKE :search OR f.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'createdAt';
        $sortOrder = $filters['sortOrder'] ?? 'DESC';

        switch ($sortBy) {
            case 'title':
                $qb->orderBy('f.title', $sortOrder);
                break;
            case 'price':
                $qb->orderBy('f.price', $sortOrder);
                break;
            case 'duration':
                $qb->orderBy('f.durationHours', $sortOrder);
                break;
            case 'category':
                $qb->orderBy('c.name', $sortOrder);
                break;
            default:
                $qb->orderBy('f.createdAt', $sortOrder);
        }

        return $qb;
    }

    /**
     * Find formations by category with pagination support
     * 
     * @return Formation[]
     */
    public function findByCategory(Category $category, ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.category = :category')
            ->andWhere('f.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('active', true)
            ->orderBy('f.createdAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all available levels from active formations
     * 
     * @return array<string>
     */
    public function getAvailableLevels(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('DISTINCT f.level')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.level', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'level');
    }

    /**
     * Get all available formats from active formations
     * 
     * @return array<string>
     */
    public function getAvailableFormats(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('DISTINCT f.format')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.format', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'format');
    }

    /**
     * Get price range from active formations
     * 
     * @return array{min: float, max: float}
     */
    public function getPriceRange(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('MIN(f.price) as minPrice', 'MAX(f.price) as maxPrice')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleResult();

        return [
            'min' => (float) ($result['minPrice'] ?? 0),
            'max' => (float) ($result['maxPrice'] ?? 0)
        ];
    }

    /**
     * Get duration range from active formations
     * 
     * @return array{min: int, max: int}
     */
    public function getDurationRange(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('MIN(f.durationHours) as minDuration', 'MAX(f.durationHours) as maxDuration')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleResult();

        return [
            'min' => (int) ($result['minDuration'] ?? 0),
            'max' => (int) ($result['maxDuration'] ?? 0)
        ];
    }

    /**
     * Count formations matching filters
     * 
     * @param array<string, mixed> $filters
     */
    public function countByFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.category', 'c')
            ->where('f.isActive = :active')
            ->setParameter('active', true);

        // Apply the same filters as createCatalogQueryBuilder but without ordering
        
        // Filter by category
        if (!empty($filters['category'])) {
            if ($filters['category'] instanceof Category) {
                $qb->andWhere('f.category = :category')
                   ->setParameter('category', $filters['category']);
            } elseif (is_string($filters['category'])) {
                $qb->andWhere('c.slug = :categorySlug')
                   ->setParameter('categorySlug', $filters['category']);
            }
        }

        // Filter by level
        if (!empty($filters['level'])) {
            $qb->andWhere('f.level = :level')
               ->setParameter('level', $filters['level']);
        }

        // Filter by format
        if (!empty($filters['format'])) {
            $qb->andWhere('f.format = :format')
               ->setParameter('format', $filters['format']);
        }

        // Filter by price range
        if (!empty($filters['minPrice'])) {
            $qb->andWhere('f.price >= :minPrice')
               ->setParameter('minPrice', $filters['minPrice']);
        }

        if (!empty($filters['maxPrice'])) {
            $qb->andWhere('f.price <= :maxPrice')
               ->setParameter('maxPrice', $filters['maxPrice']);
        }

        // Filter by duration range
        if (!empty($filters['minDuration'])) {
            $qb->andWhere('f.durationHours >= :minDuration')
               ->setParameter('minDuration', $filters['minDuration']);
        }

        if (!empty($filters['maxDuration'])) {
            $qb->andWhere('f.durationHours <= :maxDuration')
               ->setParameter('maxDuration', $filters['maxDuration']);
        }

        // Search in title and description
        if (!empty($filters['search'])) {
            $qb->andWhere('f.title LIKE :search OR f.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $qb->select('COUNT(f.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find similar formations based on category and level
     * 
     * @return Formation[]
     */
    public function findSimilarFormations(Formation $formation, int $limit = 4): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.category', 'c')
            ->addSelect('c')
            ->where('f.category = :category')
            ->andWhere('f.level = :level')
            ->andWhere('f.id != :currentId')
            ->andWhere('f.isActive = :active')
            ->setParameter('category', $formation->getCategory())
            ->setParameter('level', $formation->getLevel())
            ->setParameter('currentId', $formation->getId())
            ->setParameter('active', true)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Save a formation entity
     */
    public function save(Formation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a formation entity
     */
    public function remove(Formation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}