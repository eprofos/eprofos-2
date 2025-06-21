<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\ServiceCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Service entity
 * 
 * Provides query methods for retrieving EPROFOS services
 * with category filtering and organization.
 * 
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Find all active services ordered by title
     * 
     * @return Service[]
     */
    public function findActiveServices(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.serviceCategory', 'sc')
            ->addSelect('sc')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find services by category
     * 
     * @return Service[]
     */
    public function findByCategory(ServiceCategory $category): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.serviceCategory = :category')
            ->andWhere('s.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('active', true)
            ->orderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find service by slug with category
     */
    public function findBySlugWithCategory(string $slug): ?Service
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.serviceCategory', 'sc')
            ->addSelect('sc')
            ->where('s.slug = :slug')
            ->andWhere('s.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find services grouped by category
     * 
     * @return array<string, Service[]>
     */
    public function findServicesGroupedByCategory(): array
    {
        $services = $this->createQueryBuilder('s')
            ->leftJoin('s.serviceCategory', 'sc')
            ->addSelect('sc')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('sc.name', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($services as $service) {
            $categoryName = $service->getServiceCategory()?->getName() ?? 'Sans catégorie';
            if (!isset($grouped[$categoryName])) {
                $grouped[$categoryName] = [];
            }
            $grouped[$categoryName][] = $service;
        }

        return $grouped;
    }

    /**
     * Find featured services for homepage
     * 
     * @return Service[]
     */
    public function findFeaturedServices(int $limit = 4): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.serviceCategory', 'sc')
            ->addSelect('sc')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search services by title and description
     * 
     * @return Service[]
     */
    public function searchServices(string $query): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.serviceCategory', 'sc')
            ->addSelect('sc')
            ->where('s.isActive = :active')
            ->andWhere('s.title LIKE :query OR s.description LIKE :query')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count services by category
     * 
     * @return array<string, int>
     */
    public function countByCategory(): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('sc.name as categoryName', 'COUNT(s.id) as count')
            ->leftJoin('s.serviceCategory', 'sc')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('sc.id')
            ->orderBy('sc.name', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['categoryName'] ?? 'Sans catégorie'] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Save a service entity
     */
    public function save(Service $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a service entity
     */
    public function remove(Service $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}