<?php

declare(strict_types=1);

namespace App\Repository\Training;

use App\Entity\Training\Session;
use App\Entity\Training\SessionRegistration;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for SessionRegistration entity.
 *
 * Provides query methods for registration management with
 * filtering and statistical capabilities.
 *
 * @extends ServiceEntityRepository<SessionRegistration>
 */
class SessionRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionRegistration::class);
    }

    /**
     * Find registrations for a specific session.
     *
     * @return SessionRegistration[]
     */
    public function findBySession(Session $session): array
    {
        return $this->createQueryBuilder('sr')
            ->where('sr.session = :session')
            ->setParameter('session', $session)
            ->orderBy('sr.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find confirmed registrations for a session.
     *
     * @return SessionRegistration[]
     */
    public function findConfirmedBySession(Session $session): array
    {
        return $this->createQueryBuilder('sr')
            ->where('sr.session = :session')
            ->andWhere('sr.status = :status')
            ->setParameter('session', $session)
            ->setParameter('status', 'confirmed')
            ->orderBy('sr.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find pending registrations for a session.
     *
     * @return SessionRegistration[]
     */
    public function findPendingBySession(Session $session): array
    {
        return $this->createQueryBuilder('sr')
            ->where('sr.session = :session')
            ->andWhere('sr.status = :status')
            ->setParameter('session', $session)
            ->setParameter('status', 'pending')
            ->orderBy('sr.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Check if email is already registered for a session.
     */
    public function isEmailRegisteredForSession(string $email, Session $session): bool
    {
        $count = $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.session = :session')
            ->andWhere('sr.email = :email')
            ->andWhere('sr.status != :cancelled')
            ->setParameter('session', $session)
            ->setParameter('email', $email)
            ->setParameter('cancelled', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }

    /**
     * Get registration statistics for a session.
     */
    public function getSessionRegistrationStats(Session $session): array
    {
        $result = $this->createQueryBuilder('sr')
            ->select('
                COUNT(sr.id) as total,
                SUM(CASE WHEN sr.status = :pending THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN sr.status = :confirmed THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN sr.status = :cancelled THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN sr.status = :attended THEN 1 ELSE 0 END) as attended,
                SUM(CASE WHEN sr.status = :no_show THEN 1 ELSE 0 END) as no_show
            ')
            ->where('sr.session = :session')
            ->setParameter('session', $session)
            ->setParameter('pending', 'pending')
            ->setParameter('confirmed', 'confirmed')
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('attended', 'attended')
            ->setParameter('no_show', 'no_show')
            ->getQuery()
            ->getSingleResult()
        ;

        return [
            'total' => (int) $result['total'],
            'pending' => (int) $result['pending'],
            'confirmed' => (int) $result['confirmed'],
            'cancelled' => (int) $result['cancelled'],
            'attended' => (int) $result['attended'],
            'no_show' => (int) $result['no_show'],
        ];
    }

    /**
     * Create query builder for admin registrations list with filters.
     */
    public function createAdminQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('sr')
            ->leftJoin('sr.session', 's')
            ->leftJoin('s.formation', 'f')
            ->addSelect('s', 'f')
        ;

        // Search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('sr.firstName LIKE :search OR sr.lastName LIKE :search OR sr.email LIKE :search OR sr.company LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%')
            ;
        }

        // Session filter
        if (!empty($filters['session'])) {
            $qb->andWhere('sr.session = :session')
                ->setParameter('session', $filters['session'])
            ;
        }

        // Formation filter
        if (!empty($filters['formation'])) {
            $qb->andWhere('s.formation = :formation')
                ->setParameter('formation', $filters['formation'])
            ;
        }

        // Status filter
        if (!empty($filters['status'])) {
            $qb->andWhere('sr.status = :status')
                ->setParameter('status', $filters['status'])
            ;
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $qb->andWhere('sr.createdAt >= :date_from')
                ->setParameter('date_from', new DateTime($filters['date_from']))
            ;
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('sr.createdAt <= :date_to')
                ->setParameter('date_to', new DateTime($filters['date_to']))
            ;
        }

        // Sort
        $sortField = $filters['sort'] ?? 'createdAt';
        $sortDirection = $filters['direction'] ?? 'DESC';

        if (in_array($sortField, ['firstName', 'lastName', 'email', 'status', 'createdAt'], true)) {
            $qb->orderBy('sr.' . $sortField, $sortDirection);
        } elseif ($sortField === 'session') {
            $qb->orderBy('s.name', $sortDirection);
        } elseif ($sortField === 'formation') {
            $qb->orderBy('f.title', $sortDirection);
        } else {
            $qb->orderBy('sr.createdAt', 'DESC');
        }

        return $qb;
    }

    /**
     * Count registrations with admin filters (without ORDER BY for COUNT queries).
     */
    public function countWithAdminFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->leftJoin('sr.session', 's')
            ->leftJoin('s.formation', 'f')
        ;

        // Apply the same filters as createAdminQueryBuilder but without ORDER BY

        // Search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('sr.firstName LIKE :search OR sr.lastName LIKE :search OR sr.email LIKE :search OR sr.company LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%')
            ;
        }

        // Session filter
        if (!empty($filters['session'])) {
            $qb->andWhere('sr.session = :session')
                ->setParameter('session', $filters['session'])
            ;
        }

        // Formation filter
        if (!empty($filters['formation'])) {
            $qb->andWhere('s.formation = :formation')
                ->setParameter('formation', $filters['formation'])
            ;
        }

        // Status filter
        if (!empty($filters['status'])) {
            $qb->andWhere('sr.status = :status')
                ->setParameter('status', $filters['status'])
            ;
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $qb->andWhere('sr.createdAt >= :date_from')
                ->setParameter('date_from', new DateTime($filters['date_from']))
            ;
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('sr.createdAt <= :date_to')
                ->setParameter('date_to', new DateTime($filters['date_to']))
            ;
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get recent registrations.
     *
     * @return SessionRegistration[]
     */
    public function findRecentRegistrations(int $limit = 10): array
    {
        return $this->createQueryBuilder('sr')
            ->leftJoin('sr.session', 's')
            ->leftJoin('s.formation', 'f')
            ->addSelect('s', 'f')
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get registrations that need confirmation.
     *
     * @return SessionRegistration[]
     */
    public function findPendingRegistrations(): array
    {
        return $this->createQueryBuilder('sr')
            ->leftJoin('sr.session', 's')
            ->leftJoin('s.formation', 'f')
            ->addSelect('s', 'f')
            ->where('sr.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('sr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
