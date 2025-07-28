<?php

namespace App\Repository\Training;

use App\Entity\Training\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Category entity
 * 
 * Provides custom query methods for retrieving categories
 * with specific criteria and optimizations.
 * 
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Find all active categories ordered by name
     * 
     * @return Category[]
     */
    public function findActiveCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active categories with their active formations count
     * 
     * @return array<array{category: Category, formationCount: int}>
     */
    public function findActiveCategoriesWithFormationCount(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(f.id) as formationCount')
            ->leftJoin('c.formations', 'f', 'WITH', 'f.isActive = true')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find category by slug with active formations
     */
    public function findBySlugWithActiveFormations(string $slug): ?Category
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.formations', 'f')
            ->addSelect('f')
            ->where('c.slug = :slug')
            ->andWhere('c.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find categories that have at least one active formation
     * 
     * @return Category[]
     */
    public function findCategoriesWithActiveFormations(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.formations', 'f')
            ->where('c.isActive = :active')
            ->andWhere('f.isActive = :formationActive')
            ->setParameter('active', true)
            ->setParameter('formationActive', true)
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Save a category entity
     */
    public function save(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a category entity
     */
    public function remove(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}