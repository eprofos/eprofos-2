<?php

namespace App\Repository\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentVersion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentVersion>
 */
class DocumentVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentVersion::class);
    }

    /**
     * Find all versions for a document ordered by version number
     */
    public function findByDocument(Document $document): array
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.document = :document')
            ->setParameter('document', $document)
            ->orderBy('dv.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest version for a document
     */
    public function findLatestByDocument(Document $document): ?DocumentVersion
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.document = :document')
            ->setParameter('document', $document)
            ->orderBy('dv.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find specific version by document and version number
     */
    public function findByDocumentAndVersion(Document $document, string $versionNumber): ?DocumentVersion
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.document = :document')
            ->andWhere('dv.version = :versionNumber')
            ->setParameter('document', $document)
            ->setParameter('versionNumber', $versionNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find versions created by a specific user
     */
    public function findByCreatedBy(User $user): array
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('dv.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find versions within date range
     */
    public function findByDateRange(\DateTime $from, \DateTime $to): array
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('dv.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find versions with changelog containing specific text
     */
    public function searchByChangelog(string $search): array
    {
        return $this->createQueryBuilder('dv')
            ->where('LOWER(dv.changelog) LIKE LOWER(:search)')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('dv.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get version statistics for a document
     */
    public function getDocumentVersionStats(Document $document): array
    {
        $totalVersions = $this->createQueryBuilder('dv')
            ->select('COUNT(dv.id)')
            ->where('dv.document = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->getSingleScalarResult();

        $firstVersion = $this->createQueryBuilder('dv')
            ->where('dv.document = :document')
            ->setParameter('document', $document)
            ->orderBy('dv.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $latestVersion = $this->createQueryBuilder('dv')
            ->where('dv.document = :document')
            ->setParameter('document', $document)
            ->orderBy('dv.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'totalVersions' => $totalVersions,
            'firstVersion' => $firstVersion,
            'latestVersion' => $latestVersion,
            'firstCreated' => $firstVersion ? $firstVersion->getCreatedAt() : null,
            'lastModified' => $latestVersion ? $latestVersion->getCreatedAt() : null,
        ];
    }

    /**
     * Find versions that need integrity verification
     */
    public function findNeedingIntegrityCheck(): array
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.fileSize IS NOT NULL')
            ->andWhere('dv.checksum IS NULL OR dv.checksum = :empty')
            ->setParameter('empty', '')
            ->orderBy('dv.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent versions across all documents
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('dv')
            ->join('dv.document', 'd')
            ->orderBy('dv.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get version history for audit purposes
     */
    public function getAuditHistory(Document $document, ?\DateTime $since = null): array
    {
        $qb = $this->createQueryBuilder('dv')
            ->where('dv.document = :document')
            ->setParameter('document', $document);

        if ($since) {
            $qb->andWhere('dv.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->orderBy('dv.createdAt', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find versions with specific file checksum (duplicate detection)
     */
    public function findByChecksum(string $checksum): array
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.checksum = :checksum')
            ->setParameter('checksum', $checksum)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next version number for a document
     */
    public function getNextVersionNumber(Document $document): string
    {
        $latestVersion = $this->findLatestByDocument($document);
        
        if (!$latestVersion) {
            return '1.0';
        }

        $versionParts = explode('.', $latestVersion->getVersion());
        $major = (int) ($versionParts[0] ?? 0);
        $minor = (int) ($versionParts[1] ?? 0);

        // Increment minor version by default
        $minor++;

        return $major . '.' . $minor;
    }

    /**
     * Get major version number for breaking changes
     */
    public function getNextMajorVersionNumber(Document $document): string
    {
        $latestVersion = $this->findLatestByDocument($document);
        
        if (!$latestVersion) {
            return '1.0';
        }

        $versionParts = explode('.', $latestVersion->getVersion());
        $major = (int) ($versionParts[0] ?? 0);

        // Increment major version, reset minor to 0
        $major++;

        return $major . '.0';
    }

    public function save(DocumentVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DocumentVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
