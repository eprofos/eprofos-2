<?php

namespace App\Repository\CRM;

use App\Entity\CRM\ProspectNote;
use App\Entity\CRM\Prospect;
use App\Entity\User\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for ProspectNote entity
 * 
 * Provides query methods for managing prospect notes and interactions
 * with filtering, statistics, and activity tracking capabilities.
 * 
 * @extends ServiceEntityRepository<ProspectNote>
 */
class ProspectNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProspectNote::class);
    }

    /**
     * Find notes by prospect
     * 
     * @return ProspectNote[]
     */
    public function findByProspect(Prospect $prospect): array
    {
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('u')
            ->where('pn.prospect = :prospect')
            ->setParameter('prospect', $prospect)
            ->orderBy('pn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notes by type
     * 
     * @return ProspectNote[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('p', 'u')
            ->where('pn.type = :type')
            ->setParameter('type', $type)
            ->orderBy('pn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notes by status
     * 
     * @return ProspectNote[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('p', 'u')
            ->where('pn.status = :status')
            ->setParameter('status', $status)
            ->orderBy('pn.scheduledAt', 'ASC')
            ->addOrderBy('pn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending notes (tasks/reminders)
     * 
     * @return ProspectNote[]
     */
    public function findPendingNotes(): array
    {
        return $this->findByStatus('pending');
    }

    /**
     * Find overdue notes
     * 
     * @return ProspectNote[]
     */
    public function findOverdueNotes(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('p', 'u')
            ->where('pn.status = :status')
            ->andWhere('pn.scheduledAt IS NOT NULL')
            ->andWhere('pn.scheduledAt < :now')
            ->setParameter('status', 'pending')
            ->setParameter('now', $now)
            ->orderBy('pn.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notes created by a user
     * 
     * @return ProspectNote[]
     */
    public function findByCreatedBy(Admin $admin): array
    {
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->addSelect('p')
            ->where('pn.createdBy = :user')
            ->setParameter('user', $admin)
            ->orderBy('pn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find important notes
     * 
     * @return ProspectNote[]
     */
    public function findImportantNotes(): array
    {
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('p', 'u')
            ->where('pn.isImportant = :important')
            ->setParameter('important', true)
            ->orderBy('pn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notes within date range
     * 
     * @return ProspectNote[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('p', 'u')
            ->where('pn.createdAt >= :startDate')
            ->andWhere('pn.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('pn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find notes scheduled for today
     * 
     * @return ProspectNote[]
     */
    public function findScheduledForToday(): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('p', 'u')
            ->where('pn.scheduledAt >= :today')
            ->andWhere('pn.scheduledAt < :tomorrow')
            ->andWhere('pn.status = :status')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('status', 'pending')
            ->orderBy('pn.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent activity (notes created in last X days)
     * 
     * @return ProspectNote[]
     */
    public function findRecentActivity(int $days = 7, int $limit = 20): array
    {
        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('p', 'u')
            ->where('pn.createdAt >= :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('pn.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search notes by content or title
     * 
     * @return ProspectNote[]
     */
    public function searchNotes(string $query): array
    {
        $searchTerms = '%' . strtolower($query) . '%';
        
        return $this->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('p', 'u')
            ->where('LOWER(pn.title) LIKE :search')
            ->orWhere('LOWER(pn.content) LIKE :search')
            ->setParameter('search', $searchTerms)
            ->orderBy('pn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get notes statistics for a prospect
     * 
     * @return array<string, mixed>
     */
    public function getProspectNoteStatistics(Prospect $prospect): array
    {
        $totalNotes = $this->count(['prospect' => $prospect]);
        $pendingNotes = $this->count(['prospect' => $prospect, 'status' => 'pending']);
        $completedNotes = $this->count(['prospect' => $prospect, 'status' => 'completed']);

        // Count by type
        $typeResult = $this->createQueryBuilder('pn')
            ->select('pn.type', 'COUNT(pn.id) as count')
            ->where('pn.prospect = :prospect')
            ->setParameter('prospect', $prospect)
            ->groupBy('pn.type')
            ->getQuery()
            ->getResult();

        $typeStats = [];
        foreach ($typeResult as $row) {
            $typeStats[$row['type']] = (int) $row['count'];
        }

        // Get last note date
        $lastNote = $this->createQueryBuilder('pn')
            ->where('pn.prospect = :prospect')
            ->setParameter('prospect', $prospect)
            ->orderBy('pn.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total' => $totalNotes,
            'pending' => $pendingNotes,
            'completed' => $completedNotes,
            'by_type' => $typeStats,
            'last_note_date' => $lastNote?->getCreatedAt()
        ];
    }

    /**
     * Count notes by type
     * 
     * @return array<string, int>
     */
    public function countByType(): array
    {
        $result = $this->createQueryBuilder('pn')
            ->select('pn.type', 'COUNT(pn.id) as count')
            ->groupBy('pn.type')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['type']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Count notes by status
     * 
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('pn')
            ->select('pn.status', 'COUNT(pn.id) as count')
            ->groupBy('pn.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get activity statistics for dashboard
     * 
     * @return array<string, mixed>
     */
    public function getActivityStatistics(): array
    {
        $totalNotes = $this->count([]);
        $pendingTasks = $this->count(['status' => 'pending']);
        $overdueCount = count($this->findOverdueNotes());
        $todayCount = count($this->findScheduledForToday());

        // Notes from last 7 days
        $weekAgo = new \DateTimeImmutable('-7 days');
        $recentNotes = $this->createQueryBuilder('pn')
            ->select('COUNT(pn.id)')
            ->where('pn.createdAt >= :weekAgo')
            ->setParameter('weekAgo', $weekAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalNotes,
            'pending_tasks' => $pendingTasks,
            'overdue' => $overdueCount,
            'today' => $todayCount,
            'recent' => (int) $recentNotes
        ];
    }

    /**
     * Get daily activity for the last 30 days
     * 
     * @return array<string, int>
     */
    public function getDailyActivity(int $days = 30): array
    {
        $startDate = new \DateTimeImmutable("-{$days} days");
        
        $result = $this->createQueryBuilder('pn')
            ->select('DATE(pn.createdAt) as date', 'COUNT(pn.id) as count')
            ->where('pn.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        $activity = [];
        foreach ($result as $row) {
            $activity[$row['date']] = (int) $row['count'];
        }

        return $activity;
    }

    /**
     * Save a prospect note entity
     */
    public function save(ProspectNote $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a prospect note entity
     */
    public function remove(ProspectNote $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
