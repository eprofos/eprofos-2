<?php

namespace App\Repository;

use App\Entity\CRM\ContactRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for ContactRequest entity
 * 
 * Provides query methods for managing contact requests
 * with filtering and status management capabilities.
 * 
 * @extends ServiceEntityRepository<ContactRequest>
 */
class ContactRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactRequest::class);
    }

    /**
     * Find contact requests by status
     * 
     * @return ContactRequest[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('cr')
            ->leftJoin('cr.formation', 'f')
            ->addSelect('f')
            ->where('cr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('cr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending contact requests
     * 
     * @return ContactRequest[]
     */
    public function findPendingRequests(): array
    {
        return $this->findByStatus('pending');
    }

    /**
     * Find contact requests by type
     * 
     * @return ContactRequest[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('cr')
            ->leftJoin('cr.formation', 'f')
            ->addSelect('f')
            ->where('cr.type = :type')
            ->setParameter('type', $type)
            ->orderBy('cr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent contact requests
     * 
     * @return ContactRequest[]
     */
    public function findRecentRequests(int $limit = 10): array
    {
        return $this->createQueryBuilder('cr')
            ->leftJoin('cr.formation', 'f')
            ->addSelect('f')
            ->orderBy('cr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count requests by status
     * 
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('cr')
            ->select('cr.status', 'COUNT(cr.id) as count')
            ->groupBy('cr.status')
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
     * 
     * @return array<string, int>
     */
    public function countByType(): array
    {
        $result = $this->createQueryBuilder('cr')
            ->select('cr.type', 'COUNT(cr.id) as count')
            ->groupBy('cr.type')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['type']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Find requests for a specific formation
     * 
     * @return ContactRequest[]
     */
    public function findByFormation(int $formationId): array
    {
        return $this->createQueryBuilder('cr')
            ->where('cr.formation = :formationId')
            ->setParameter('formationId', $formationId)
            ->orderBy('cr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find requests created within date range
     * 
     * @return ContactRequest[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('cr')
            ->leftJoin('cr.formation', 'f')
            ->addSelect('f')
            ->where('cr.createdAt >= :startDate')
            ->andWhere('cr.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('cr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics for dashboard
     * 
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $totalRequests = $this->count([]);
        $pendingRequests = $this->count(['status' => 'pending']);
        $completedRequests = $this->count(['status' => 'completed']);

        // Requests from last 30 days
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        $recentRequests = $this->createQueryBuilder('cr')
            ->select('COUNT(cr.id)')
            ->where('cr.createdAt >= :thirtyDaysAgo')
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalRequests,
            'pending' => $pendingRequests,
            'completed' => $completedRequests,
            'recent' => (int) $recentRequests,
            'completion_rate' => $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100, 2) : 0
        ];
    }

    /**
     * Save a contact request entity
     */
    public function save(ContactRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a contact request entity
     */
    public function remove(ContactRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}