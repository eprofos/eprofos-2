<?php

namespace App\Repository\Document;

use App\Entity\Document\DocumentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentType>
 */
class DocumentTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentType::class);
    }

    /**
     * Find all active document types ordered by sort order
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dt.sortOrder', 'ASC')
            ->addOrderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find document type by code
     */
    public function findByCode(string $code): ?DocumentType
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.code = :code')
            ->andWhere('dt.isActive = :active')
            ->setParameter('code', $code)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find types that allow multiple published documents
     */
    public function findAllowingMultiplePublished(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->andWhere('dt.allowMultiplePublished = :allow')
            ->setParameter('active', true)
            ->setParameter('allow', true)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find types that require approval
     */
    public function findRequiringApproval(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->andWhere('dt.requiresApproval = :require')
            ->setParameter('active', true)
            ->setParameter('require', true)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find types that generate PDF
     */
    public function findGeneratingPdf(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.isActive = :active')
            ->andWhere('dt.generatesPdf = :generate')
            ->setParameter('active', true)
            ->setParameter('generate', true)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get document counts by type
     */
    public function getDocumentCounts(): array
    {
        return $this->createQueryBuilder('dt')
            ->select('dt.id, dt.name, dt.code, COUNT(d.id) as documentCount')
            ->leftJoin('dt.documents', 'd')
            ->where('dt.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('dt.id')
            ->orderBy('dt.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(DocumentType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DocumentType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
