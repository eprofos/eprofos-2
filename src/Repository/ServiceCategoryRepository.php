<?php

namespace App\Repository;

use App\Entity\Service\ServiceCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for ServiceCategory entity
 * 
 * Provides query methods for retrieving service categories
 * with their associated services.
 * 
 * @extends ServiceEntityRepository<ServiceCategory>
 */
class ServiceCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceCategory::class);
    }

    /**
     * Find all categories ordered by name
     * 
     * @return ServiceCategory[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('sc')
            ->orderBy('sc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find categories with their active services
     * 
     * @return ServiceCategory[]
     */
    public function findWithActiveServices(): array
    {
        return $this->createQueryBuilder('sc')
            ->leftJoin('sc.services', 's', 'WITH', 's.isActive = true')
            ->addSelect('s')
            ->orderBy('sc.name', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find category by slug with active services
     */
    public function findBySlugWithActiveServices(string $slug): ?ServiceCategory
    {
        return $this->createQueryBuilder('sc')
            ->leftJoin('sc.services', 's', 'WITH', 's.isActive = true')
            ->addSelect('s')
            ->where('sc.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('s.title', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find categories that have at least one active service
     * 
     * @return ServiceCategory[]
     */
    public function findCategoriesWithActiveServices(): array
    {
        return $this->createQueryBuilder('sc')
            ->innerJoin('sc.services', 's')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('sc.id')
            ->orderBy('sc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find categories with service count
     * 
     * @return array<array{category: ServiceCategory, serviceCount: int}>
     */
    public function findWithServiceCount(): array
    {
        return $this->createQueryBuilder('sc')
            ->select('sc', 'COUNT(s.id) as serviceCount')
            ->leftJoin('sc.services', 's', 'WITH', 's.isActive = true')
            ->groupBy('sc.id')
            ->orderBy('sc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Save a service category entity
     */
    public function save(ServiceCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a service category entity
     */
    public function remove(ServiceCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}