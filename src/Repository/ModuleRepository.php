<?php

namespace App\Repository;

use App\Entity\Training\Module;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Module>
 */
class ModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Module::class);
    }

    /**
     * Find modules by formation with order
     */
    public function findByFormationOrdered(int $formationId): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.formation = :formationId')
            ->andWhere('m.isActive = true')
            ->setParameter('formationId', $formationId)
            ->orderBy('m.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active modules for a formation
     */
    public function findActiveByFormation(int $formationId): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.formation = :formationId')
            ->andWhere('m.isActive = true')
            ->setParameter('formationId', $formationId)
            ->orderBy('m.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find module by slug with formation
     */
    public function findBySlugWithFormation(string $slug): ?Module
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.formation', 'f')
            ->addSelect('f')
            ->where('m.slug = :slug')
            ->andWhere('m.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get modules with chapter count
     */
    public function findWithChapterCount(): array
    {
        return $this->createQueryBuilder('m')
            ->select('m, COUNT(c.id) as chapterCount')
            ->leftJoin('m.chapters', 'c')
            ->where('m.isActive = true')
            ->groupBy('m.id')
            ->orderBy('m.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next order index for a formation
     */
    public function getNextOrderIndex(int $formationId): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('MAX(m.orderIndex) as maxOrder')
            ->where('m.formation = :formationId')
            ->setParameter('formationId', $formationId)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Update order indexes for modules
     */
    public function updateOrderIndexes(array $moduleIds): void
    {
        $em = $this->getEntityManager();
        
        foreach ($moduleIds as $index => $moduleId) {
            $em->createQuery(
                'UPDATE App\Entity\Module m SET m.orderIndex = :orderIndex WHERE m.id = :id'
            )
            ->setParameter('orderIndex', $index + 1)
            ->setParameter('id', $moduleId)
            ->execute();
        }
    }
}
