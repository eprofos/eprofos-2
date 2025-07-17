<?php

namespace App\Repository;

use App\Entity\Session;
use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Session entity
 * 
 * Provides query methods for session management with
 * filtering, sorting, and advanced search capabilities.
 * 
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Find upcoming sessions for a formation
     * 
     * @return Session[]
     */
    public function findUpcomingSessionsForFormation(Formation $formation, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.formation = :formation')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.startDate > :now')
            ->setParameter('formation', $formation)
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startDate', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find open sessions for a formation (available for registration)
     * 
     * @return Session[]
     */
    public function findOpenSessionsForFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.formation = :formation')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.status = :status')
            ->andWhere('s.startDate > :now')
            ->andWhere('s.currentRegistrations < s.maxCapacity')
            ->setParameter('formation', $formation)
            ->setParameter('active', true)
            ->setParameter('status', 'open')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all upcoming sessions
     * 
     * @return Session[]
     */
    public function findUpcomingSessions(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.formation', 'f')
            ->addSelect('f')
            ->where('s.isActive = :active')
            ->andWhere('s.startDate > :now')
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Create a query builder for admin sessions list with filters
     */
    public function createAdminQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.formation', 'f')
            ->addSelect('f');

        // Search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('s.name LIKE :search OR f.title LIKE :search OR s.location LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Formation filter
        if (!empty($filters['formation'])) {
            $qb->andWhere('s.formation = :formation')
                ->setParameter('formation', $filters['formation']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // Date range filter
        if (!empty($filters['start_date'])) {
            $qb->andWhere('s.startDate >= :start_date')
                ->setParameter('start_date', new \DateTime($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $qb->andWhere('s.endDate <= :end_date')
                ->setParameter('end_date', new \DateTime($filters['end_date']));
        }

        // Active filter
        if (isset($filters['active']) && $filters['active'] !== '') {
            $activeValue = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activeValue !== null) {
                $qb->andWhere('s.isActive = :active')
                    ->setParameter('active', $activeValue);
            }
        }

        // Sort
        $sortField = $filters['sort'] ?? 'startDate';
        $sortDirection = $filters['direction'] ?? 'ASC';
        
        if (in_array($sortField, ['name', 'startDate', 'endDate', 'status', 'currentRegistrations', 'maxCapacity'])) {
            $qb->orderBy('s.' . $sortField, $sortDirection);
        } elseif ($sortField === 'formation') {
            $qb->orderBy('f.title', $sortDirection);
        } else {
            $qb->orderBy('s.startDate', 'ASC');
        }

        return $qb;
    }

    /**
     * Count sessions with filters (without ORDER BY for aggregation)
     */
    public function countAdminSessions(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->leftJoin('s.formation', 'f');

        // Formation filter
        if (!empty($filters['formation_id'])) {
            $qb->andWhere('s.formation = :formation_id')
                ->setParameter('formation_id', $filters['formation_id']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('s.name', ':search'),
                $qb->expr()->like('f.title', ':search'),
                $qb->expr()->like('s.location', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Status filter
        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // Date range filter
        if (!empty($filters['start_date'])) {
            $qb->andWhere('s.startDate >= :start_date')
                ->setParameter('start_date', new \DateTime($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $qb->andWhere('s.endDate <= :end_date')
                ->setParameter('end_date', new \DateTime($filters['end_date']));
        }

        // Active filter
        if (isset($filters['active']) && $filters['active'] !== '') {
            $activeValue = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activeValue !== null) {
                $qb->andWhere('s.isActive = :active')
                    ->setParameter('active', $activeValue);
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find sessions that need confirmation (minimum capacity reached)
     * 
     * @return Session[]
     */
    public function findSessionsNeedingConfirmation(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.currentRegistrations >= s.minCapacity')
            ->andWhere('s.startDate > :now')
            ->setParameter('status', 'open')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find sessions with low registration rates
     * 
     * @return Session[]
     */
    public function findSessionsWithLowRegistration(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.currentRegistrations < s.minCapacity')
            ->andWhere('s.startDate > :threshold')
            ->setParameter('status', 'open')
            ->setParameter('threshold', (new \DateTime())->modify('+2 weeks'))
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get sessions statistics
     */
    public function getSessionsStats(): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('
                COUNT(s.id) as total,
                SUM(CASE WHEN s.status = :open THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN s.status = :confirmed THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN s.status = :cancelled THEN 1 ELSE 0 END) as cancelled,
                SUM(s.currentRegistrations) as totalRegistrations
            ')
            ->where('s.startDate > :now')
            ->setParameter('open', 'open')
            ->setParameter('confirmed', 'confirmed')
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'open' => (int) $result['open'],
            'confirmed' => (int) $result['confirmed'],
            'cancelled' => (int) $result['cancelled'],
            'totalRegistrations' => (int) $result['totalRegistrations'],
        ];
    }

    /**
     * Find sessions by month for calendar display
     */
    public function findSessionsByMonth(int $year, int $month): array
    {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = (clone $startDate)->modify('last day of this month');

        return $this->createQueryBuilder('s')
            ->leftJoin('s.formation', 'f')
            ->addSelect('f')
            ->where('s.startDate BETWEEN :start AND :end')
            ->andWhere('s.isActive = :active')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('active', true)
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
