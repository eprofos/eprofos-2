<?php

namespace App\Repository;

use App\Entity\NeedsAnalysisRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for NeedsAnalysisRequest entity
 * 
 * Provides custom query methods for needs analysis requests
 * including filtering, statistics, and expiration management.
 */
class NeedsAnalysisRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NeedsAnalysisRequest::class);
    }

    /**
     * Save a needs analysis request
     */
    public function save(NeedsAnalysisRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a needs analysis request
     */
    public function remove(NeedsAnalysisRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find a request by its token
     */
    public function findByToken(string $token): ?NeedsAnalysisRequest
    {
        return $this->createQueryBuilder('nar')
            ->andWhere('nar.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find requests by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('nar')
            ->andWhere('nar.status = :status')
            ->setParameter('status', $status)
            ->orderBy('nar.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find requests by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('nar')
            ->andWhere('nar.type = :type')
            ->setParameter('type', $type)
            ->orderBy('nar.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expired requests that are still marked as sent
     */
    public function findExpiredRequests(): array
    {
        return $this->createQueryBuilder('nar')
            ->andWhere('nar.status = :status')
            ->andWhere('nar.expiresAt < :now')
            ->setParameter('status', NeedsAnalysisRequest::STATUS_SENT)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find requests expiring soon (within specified days)
     */
    public function findRequestsExpiringSoon(int $days = 7): array
    {
        $expirationDate = new \DateTimeImmutable("+{$days} days");
        
        return $this->createQueryBuilder('nar')
            ->andWhere('nar.status = :status')
            ->andWhere('nar.expiresAt <= :expirationDate')
            ->andWhere('nar.expiresAt > :now')
            ->setParameter('status', NeedsAnalysisRequest::STATUS_SENT)
            ->setParameter('expirationDate', $expirationDate)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('nar.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('nar');
        
        $result = $qb
            ->select('nar.status, nar.type, COUNT(nar.id) as count')
            ->groupBy('nar.status, nar.type')
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'by_status' => [
                NeedsAnalysisRequest::STATUS_PENDING => 0,
                NeedsAnalysisRequest::STATUS_SENT => 0,
                NeedsAnalysisRequest::STATUS_COMPLETED => 0,
                NeedsAnalysisRequest::STATUS_EXPIRED => 0,
                NeedsAnalysisRequest::STATUS_CANCELLED => 0,
            ],
            'by_type' => [
                NeedsAnalysisRequest::TYPE_COMPANY => 0,
                NeedsAnalysisRequest::TYPE_INDIVIDUAL => 0,
            ],
            'completion_rate' => 0,
        ];

        foreach ($result as $row) {
            $count = (int) $row['count'];
            $stats['total'] += $count;
            $stats['by_status'][$row['status']] += $count;
            $stats['by_type'][$row['type']] += $count;
        }

        // Calculate completion rate
        if ($stats['total'] > 0) {
            $completed = $stats['by_status'][NeedsAnalysisRequest::STATUS_COMPLETED];
            $stats['completion_rate'] = round(($completed / $stats['total']) * 100, 1);
        }

        return $stats;
    }

    /**
     * Count requests by status
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('nar')
            ->select('nar.status, COUNT(nar.id) as count')
            ->groupBy('nar.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Count requests by type
     */
    public function countByType(): array
    {
        $result = $this->createQueryBuilder('nar')
            ->select('nar.type, COUNT(nar.id) as count')
            ->groupBy('nar.type')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['type']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Find requests with filters for admin interface
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('nar')
            ->leftJoin('nar.createdByUser', 'u')
            ->leftJoin('nar.formation', 'f')
            ->addSelect('u', 'f');

        if (!empty($filters['status'])) {
            $qb->andWhere('nar.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('nar.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['formation'])) {
            $qb->andWhere('nar.formation = :formation')
               ->setParameter('formation', $filters['formation']);
        }

        if (!empty($filters['created_by'])) {
            $qb->andWhere('nar.createdByUser = :createdBy')
               ->setParameter('createdBy', $filters['created_by']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                'nar.recipientName LIKE :search',
                'nar.recipientEmail LIKE :search',
                'nar.companyName LIKE :search'
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('nar.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('nar.createdAt <= :dateTo')
               ->setParameter('dateTo', $filters['date_to']);
        }

        return $qb->orderBy('nar.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find requests created by a specific user
     */
    public function findByCreatedByUser($user): array
    {
        return $this->createQueryBuilder('nar')
            ->andWhere('nar.createdByUser = :user')
            ->setParameter('user', $user)
            ->orderBy('nar.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find requests for a specific formation
     */
    public function findByFormation($formation): array
    {
        return $this->createQueryBuilder('nar')
            ->andWhere('nar.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('nar.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent requests (last 30 days)
     */
    public function findRecentRequests(int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");
        
        return $this->createQueryBuilder('nar')
            ->andWhere('nar.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('nar.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark expired requests as expired
     */
    public function markExpiredRequests(): int
    {
        return $this->createQueryBuilder('nar')
            ->update()
            ->set('nar.status', ':expiredStatus')
            ->where('nar.status = :sentStatus')
            ->andWhere('nar.expiresAt < :now')
            ->setParameter('expiredStatus', NeedsAnalysisRequest::STATUS_EXPIRED)
            ->setParameter('sentStatus', NeedsAnalysisRequest::STATUS_SENT)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Get completion statistics by month
     */
    public function getCompletionStatsByMonth(int $months = 12): array
    {
        $since = new \DateTimeImmutable("-{$months} months");
        
        return $this->createQueryBuilder('nar')
            ->select('YEAR(nar.completedAt) as year, MONTH(nar.completedAt) as month, COUNT(nar.id) as count')
            ->andWhere('nar.status = :status')
            ->andWhere('nar.completedAt >= :since')
            ->setParameter('status', NeedsAnalysisRequest::STATUS_COMPLETED)
            ->setParameter('since', $since)
            ->groupBy('year, month')
            ->orderBy('year, month')
            ->getQuery()
            ->getResult();
    }
}