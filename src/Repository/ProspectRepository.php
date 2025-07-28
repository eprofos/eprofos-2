<?php

namespace App\Repository;

use App\Entity\CRM\Prospect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Prospect entity
 * 
 * Provides query methods for managing prospects with filtering,
 * statistics, and follow-up management capabilities.
 * 
 * @extends ServiceEntityRepository<Prospect>
 */
class ProspectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prospect::class);
    }

    /**
     * Find prospects by status
     * 
     * @return Prospect[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.assignedTo', 'u')
            ->leftJoin('p.interestedFormations', 'f')
            ->leftJoin('p.interestedServices', 's')
            ->addSelect('u', 'f', 's')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find prospects by priority
     * 
     * @return Prospect[]
     */
    public function findByPriority(string $priority): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.assignedTo', 'u')
            ->addSelect('u')
            ->where('p.priority = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('p.nextFollowUpDate', 'ASC')
            ->addOrderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find prospects assigned to an admin
     * 
     * @return Prospect[]
     */
    public function findByAssignedAdmin(int $adminId): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.interestedFormations', 'f')
            ->leftJoin('p.interestedServices', 's')
            ->addSelect('f', 's')
            ->where('p.assignedTo = :adminId')
            ->setParameter('adminId', $adminId)
            ->orderBy('p.priority', 'DESC')
            ->addOrderBy('p.nextFollowUpDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find prospects that need follow-up
     * 
     * @return Prospect[]
     */
    public function findNeedingFollowUp(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('p')
            ->leftJoin('p.assignedTo', 'u')
            ->addSelect('u')
            ->where('p.nextFollowUpDate IS NOT NULL')
            ->andWhere('p.nextFollowUpDate <= :now')
            ->andWhere('p.status NOT IN (:closedStatuses)')
            ->setParameter('now', $now)
            ->setParameter('closedStatuses', ['customer', 'lost'])
            ->orderBy('p.nextFollowUpDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find overdue prospects (no follow-up for more than X days)
     * 
     * @return Prospect[]
     */
    public function findOverdueProspects(int $days = 7): array
    {
        $cutoffDate = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('p')
            ->leftJoin('p.assignedTo', 'u')
            ->addSelect('u')
            ->where('p.lastContactDate IS NULL OR p.lastContactDate < :cutoffDate')
            ->andWhere('p.status NOT IN (:closedStatuses)')
            ->setParameter('cutoffDate', $cutoffDate)
            ->setParameter('closedStatuses', ['customer', 'lost'])
            ->orderBy('p.lastContactDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find prospects by source
     * 
     * @return Prospect[]
     */
    public function findBySource(string $source): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.assignedTo', 'u')
            ->addSelect('u')
            ->where('p.source = :source')
            ->setParameter('source', $source)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find prospects created within date range
     * 
     * @return Prospect[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.assignedTo', 'u')
            ->addSelect('u')
            ->where('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find prospects interested in a specific formation
     * 
     * @return Prospect[]
     */
    public function findByFormationInterest(int $formationId): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.interestedFormations', 'f')
            ->leftJoin('p.assignedTo', 'u')
            ->addSelect('f', 'u')
            ->where('f.id = :formationId')
            ->setParameter('formationId', $formationId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find prospects interested in a specific service
     * 
     * @return Prospect[]
     */
    public function findByServiceInterest(int $serviceId): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.interestedServices', 's')
            ->leftJoin('p.assignedTo', 'u')
            ->addSelect('s', 'u')
            ->where('s.id = :serviceId')
            ->setParameter('serviceId', $serviceId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search prospects by name, email, or company
     * 
     * @return Prospect[]
     */
    public function searchProspects(string $query): array
    {
        $searchTerms = '%' . strtolower($query) . '%';
        
        return $this->createQueryBuilder('p')
            ->leftJoin('p.assignedTo', 'u')
            ->addSelect('u')
            ->where('LOWER(p.firstName) LIKE :search')
            ->orWhere('LOWER(p.lastName) LIKE :search')
            ->orWhere('LOWER(p.email) LIKE :search')
            ->orWhere('LOWER(p.company) LIKE :search')
            ->setParameter('search', $searchTerms)
            ->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count prospects by status
     * 
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.status', 'COUNT(p.id) as count')
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Count prospects by priority
     * 
     * @return array<string, int>
     */
    public function countByPriority(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.priority', 'COUNT(p.id) as count')
            ->groupBy('p.priority')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['priority']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Count prospects by source
     * 
     * @return array<string, int>
     */
    public function countBySource(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.source', 'COUNT(p.id) as count')
            ->where('p.source IS NOT NULL')
            ->groupBy('p.source')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['source']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Count prospects by assigned admin
     * 
     * @return array<string, int>
     */
    public function countByAssignedAdmin(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('CONCAT(u.firstName, \' \' , u.lastName) as adminName', 'COUNT(p.id) as count')
            ->leftJoin('p.assignedTo', 'u')
            ->where('p.assignedTo IS NOT NULL')
            ->groupBy('p.assignedTo')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['adminName']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get prospects conversion statistics
     * 
     * @return array<string, mixed>
     */
    public function getConversionStatistics(): array
    {
        $totalProspects = $this->count([]);
        $customers = $this->count(['status' => 'customer']);
        $lost = $this->count(['status' => 'lost']);
        $active = $totalProspects - $customers - $lost;

        // Get prospects from last 30 days
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        $recentProspects = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt >= :thirtyDaysAgo')
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        // Get average days to conversion
        $avgDaysToConversion = $this->createQueryBuilder('p')
            ->select('AVG(DATEDIFF(p.updatedAt, p.createdAt))')
            ->where('p.status = :status')
            ->setParameter('status', 'customer')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalProspects,
            'customers' => $customers,
            'lost' => $lost,
            'active' => $active,
            'recent' => (int) $recentProspects,
            'conversion_rate' => $totalProspects > 0 ? round(($customers / $totalProspects) * 100, 2) : 0,
            'avg_days_to_conversion' => $avgDaysToConversion ? round((float) $avgDaysToConversion, 1) : null
        ];
    }

    /**
     * Get dashboard statistics
     * 
     * @return array<string, mixed>
     */
    public function getDashboardStatistics(): array
    {
        $totalProspects = $this->count([]);
        $activeProspects = $this->count(['status' => ['lead', 'prospect', 'qualified', 'negotiation']]);
        $needingFollowUp = count($this->findNeedingFollowUp());
        $overdueProspects = count($this->findOverdueProspects());

        // Hot prospects (high priority or qualified/negotiation status)
        $hotProspects = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.priority IN (:priorities) OR p.status IN (:hotStatuses)')
            ->setParameter('priorities', ['high', 'urgent'])
            ->setParameter('hotStatuses', ['qualified', 'negotiation'])
            ->getQuery()
            ->getSingleScalarResult();

        // Converted prospects (customer status)
        $convertedProspects = $this->count(['status' => 'customer']);

        // Recent activity (prospects created or updated in last 7 days)
        $weekAgo = new \DateTimeImmutable('-7 days');
        $recentActivity = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt >= :weekAgo OR p.updatedAt >= :weekAgo')
            ->setParameter('weekAgo', $weekAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalProspects,
            'active' => $activeProspects,
            'hot' => (int) $hotProspects,
            'converted' => $convertedProspects,
            'needing_follow_up' => $needingFollowUp,
            'overdue' => $overdueProspects,
            'recent_activity' => (int) $recentActivity,
            'conversion_rate' => $totalProspects > 0 ? round(($convertedProspects / $totalProspects) * 100, 1) : 0
        ];
    }

    /**
     * Get monthly statistics for charts
     * 
     * @return array<string, mixed>
     */
    public function getMonthlyStatistics(int $months = 12): array
    {
        $startDate = new \DateTimeImmutable("-{$months} months");
        
        $result = $this->createQueryBuilder('p')
            ->select('DATE_FORMAT(p.createdAt, \'%Y-%m\') as month', 'COUNT(p.id) as count')
            ->where('p.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();

        $statistics = [];
        foreach ($result as $row) {
            $statistics[$row['month']] = (int) $row['count'];
        }

        return $statistics;
    }

    /**
     * Save a prospect entity
     */
    public function save(Prospect $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a prospect entity
     */
    public function remove(Prospect $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
