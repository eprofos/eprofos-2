<?php

declare(strict_types=1);

namespace App\Repository\Document;

use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentUITemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentUIComponent>
 */
class DocumentUIComponentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentUIComponent::class);
    }

    /**
     * Find components by UI template.
     */
    public function findByTemplate(DocumentUITemplate $template): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.uiTemplate = :template')
            ->setParameter('template', $template)
            ->orderBy('duc.sortOrder', 'ASC')
            ->addOrderBy('duc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find all components with their templates.
     */
    public function findAllWithTemplates(): array
    {
        return $this->createQueryBuilder('duc')
            ->leftJoin('duc.uiTemplate', 'ut')
            ->addSelect('ut')
            ->where('duc.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('ut.name', 'ASC')
            ->addOrderBy('duc.zone', 'ASC')
            ->addOrderBy('duc.sortOrder', 'ASC')
            ->addOrderBy('duc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find active components by template.
     */
    public function findActiveByTemplate(DocumentUITemplate $template): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.uiTemplate = :template')
            ->andWhere('duc.isActive = :active')
            ->setParameter('template', $template)
            ->setParameter('active', true)
            ->orderBy('duc.sortOrder', 'ASC')
            ->addOrderBy('duc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find components by zone.
     */
    public function findByZone(DocumentUITemplate $template, string $zone): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.uiTemplate = :template')
            ->andWhere('duc.zone = :zone')
            ->andWhere('duc.isActive = :active')
            ->setParameter('template', $template)
            ->setParameter('zone', $zone)
            ->setParameter('active', true)
            ->orderBy('duc.sortOrder', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find components by type.
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.type = :type')
            ->andWhere('duc.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('duc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find required components by template.
     */
    public function findRequiredByTemplate(DocumentUITemplate $template): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.uiTemplate = :template')
            ->andWhere('duc.isRequired = :required')
            ->andWhere('duc.isActive = :active')
            ->setParameter('template', $template)
            ->setParameter('required', true)
            ->setParameter('active', true)
            ->orderBy('duc.sortOrder', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get next sort order for template.
     */
    public function getNextSortOrder(DocumentUITemplate $template): int
    {
        $result = $this->createQueryBuilder('duc')
            ->select('MAX(duc.sortOrder)')
            ->where('duc.uiTemplate = :template')
            ->setParameter('template', $template)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result + 1;
    }

    /**
     * Find components with data binding.
     */
    public function findWithDataBinding(DocumentUITemplate $template): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.uiTemplate = :template')
            ->andWhere('duc.dataBinding IS NOT NULL')
            ->andWhere('duc.isActive = :active')
            ->setParameter('template', $template)
            ->setParameter('active', true)
            ->orderBy('duc.sortOrder', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find components with conditional display.
     */
    public function findWithConditionalDisplay(DocumentUITemplate $template): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.uiTemplate = :template')
            ->andWhere('duc.conditionalDisplay IS NOT NULL')
            ->andWhere('duc.isActive = :active')
            ->setParameter('template', $template)
            ->setParameter('active', true)
            ->orderBy('duc.sortOrder', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Search components by criteria.
     */
    public function findBySearchCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('duc');

        if (!empty($criteria['template'])) {
            $qb->andWhere('duc.uiTemplate = :template')
                ->setParameter('template', $criteria['template'])
            ;
        }

        if (!empty($criteria['search'])) {
            $qb->andWhere('(duc.name LIKE :search OR duc.content LIKE :search)')
                ->setParameter('search', '%' . $criteria['search'] . '%')
            ;
        }

        if (!empty($criteria['type'])) {
            $qb->andWhere('duc.type = :type')
                ->setParameter('type', $criteria['type'])
            ;
        }

        if (!empty($criteria['zone'])) {
            $qb->andWhere('duc.zone = :zone')
                ->setParameter('zone', $criteria['zone'])
            ;
        }

        if (isset($criteria['active'])) {
            $qb->andWhere('duc.isActive = :active')
                ->setParameter('active', $criteria['active'])
            ;
        }

        if (isset($criteria['required'])) {
            $qb->andWhere('duc.isRequired = :required')
                ->setParameter('required', $criteria['required'])
            ;
        }

        return $qb->orderBy('duc.sortOrder', 'ASC')
            ->addOrderBy('duc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Count components by template.
     */
    public function countByTemplate(DocumentUITemplate $template): int
    {
        return (int) $this->createQueryBuilder('duc')
            ->select('COUNT(duc.id)')
            ->where('duc.uiTemplate = :template')
            ->setParameter('template', $template)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Count active components by template.
     */
    public function countActiveByTemplate(DocumentUITemplate $template): int
    {
        return (int) $this->createQueryBuilder('duc')
            ->select('COUNT(duc.id)')
            ->where('duc.uiTemplate = :template')
            ->andWhere('duc.isActive = :active')
            ->setParameter('template', $template)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Count components by zone.
     */
    public function countByZone(DocumentUITemplate $template, string $zone): int
    {
        return (int) $this->createQueryBuilder('duc')
            ->select('COUNT(duc.id)')
            ->where('duc.uiTemplate = :template')
            ->andWhere('duc.zone = :zone')
            ->andWhere('duc.isActive = :active')
            ->setParameter('template', $template)
            ->setParameter('zone', $zone)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Find components by CSS class.
     */
    public function findByCssClass(string $cssClass): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.cssClass LIKE :cssClass')
            ->andWhere('duc.isActive = :active')
            ->setParameter('cssClass', '%' . $cssClass . '%')
            ->setParameter('active', true)
            ->orderBy('duc.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find components by element ID.
     */
    public function findByElementId(string $elementId): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.elementId = :elementId')
            ->setParameter('elementId', $elementId)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find components created by user.
     *
     * @param mixed $admin
     */
    public function findByCreatedBy($admin): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.createdBy = :user')
            ->setParameter('user', $admin)
            ->orderBy('duc.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find recently updated components.
     */
    public function findRecentlyUpdated(int $limit = 10): array
    {
        return $this->createQueryBuilder('duc')
            ->where('duc.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('duc.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Update sort orders for template components.
     */
    public function updateSortOrders(DocumentUITemplate $template, array $componentIds): void
    {
        $sortOrder = 1;
        foreach ($componentIds as $componentId) {
            $this->createQueryBuilder('duc')
                ->update()
                ->set('duc.sortOrder', ':sortOrder')
                ->where('duc.id = :id')
                ->andWhere('duc.uiTemplate = :template')
                ->setParameter('sortOrder', $sortOrder)
                ->setParameter('id', $componentId)
                ->setParameter('template', $template)
                ->getQuery()
                ->execute()
            ;

            $sortOrder++;
        }
    }

    /**
     * Clone components from one template to another.
     */
    public function cloneFromTemplate(DocumentUITemplate $sourceTemplate, DocumentUITemplate $targetTemplate): array
    {
        $sourceComponents = $this->findByTemplate($sourceTemplate);
        $clonedComponents = [];

        foreach ($sourceComponents as $component) {
            $cloned = $component->cloneComponent();
            $cloned->setUiTemplate($targetTemplate);

            $this->getEntityManager()->persist($cloned);
            $clonedComponents[] = $cloned;
        }

        $this->getEntityManager()->flush();

        return $clonedComponents;
    }
}
