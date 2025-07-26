<?php

namespace App\Repository;

use App\Entity\Alternance\AlternanceContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for AlternanceContract entity
 * 
 * Provides query methods for alternance contracts with filtering,
 * searching, and statistics functionality.
 *
 * @extends ServiceEntityRepository<AlternanceContract>
 */
class AlternanceContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlternanceContract::class);
    }

    /**
     * Find contracts by status
     *
     * @param string $status
     * @param int|null $limit
     * @return AlternanceContract[]
     */
    public function findByStatus(string $status, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('ac')
            ->where('ac.status = :status')
            ->setParameter('status', $status)
            ->orderBy('ac.startDate', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find active contracts
     *
     * @param int|null $limit
     * @return AlternanceContract[]
     */
    public function findActiveContracts(?int $limit = null): array
    {
        return $this->findByStatus('active', $limit);
    }

    /**
     * Find contracts by mentor
     *
     * @param int $mentorId
     * @return AlternanceContract[]
     */
    public function findByMentor(int $mentorId): array
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.mentor = :mentorId')
            ->setParameter('mentorId', $mentorId)
            ->orderBy('ac.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find contracts by student
     *
     * @param int $studentId
     * @return AlternanceContract[]
     */
    public function findByStudent(int $studentId): array
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.student = :studentId')
            ->setParameter('studentId', $studentId)
            ->orderBy('ac.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find contracts by session
     *
     * @param int $sessionId
     * @return AlternanceContract[]
     */
    public function findBySession(int $sessionId): array
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.session = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('ac.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find contracts by company
     *
     * @param string $companyName
     * @return AlternanceContract[]
     */
    public function findByCompany(string $companyName): array
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.companyName LIKE :companyName')
            ->setParameter('companyName', '%' . $companyName . '%')
            ->orderBy('ac.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find contracts by date range
     *
     * @param \DateTimeInterface|null $startDate
     * @param \DateTimeInterface|null $endDate
     * @return AlternanceContract[]
     */
    public function findByDateRange(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('ac');

        if ($startDate) {
            $qb->andWhere('ac.startDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('ac.endDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $qb->orderBy('ac.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find contracts ending soon
     *
     * @param int $days Number of days to look ahead
     * @return AlternanceContract[]
     */
    public function findEndingSoon(int $days = 30): array
    {
        $endDate = new \DateTime("+{$days} days");

        return $this->createQueryBuilder('ac')
            ->where('ac.status = :status')
            ->andWhere('ac.endDate <= :endDate')
            ->andWhere('ac.endDate >= CURRENT_DATE()')
            ->setParameter('status', 'active')
            ->setParameter('endDate', $endDate)
            ->orderBy('ac.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get contracts statistics by status
     *
     * @return array
     */
    public function getStatusStatistics(): array
    {
        $result = $this->createQueryBuilder('ac')
            ->select('ac.status', 'COUNT(ac.id) as count')
            ->groupBy('ac.status')
            ->getQuery()
            ->getResult();

        $statistics = [];
        foreach ($result as $row) {
            $statistics[$row['status']] = (int) $row['count'];
        }

        return $statistics;
    }

    /**
     * Get contracts statistics by contract type
     *
     * @return array
     */
    public function getContractTypeStatistics(): array
    {
        $result = $this->createQueryBuilder('ac')
            ->select('ac.contractType', 'COUNT(ac.id) as count')
            ->groupBy('ac.contractType')
            ->getQuery()
            ->getResult();

        $statistics = [];
        foreach ($result as $row) {
            $statistics[$row['contractType']] = (int) $row['count'];
        }

        return $statistics;
    }

    /**
     * Get monthly contracts creation statistics
     *
     * @param int $months Number of months to look back
     * @return array
     */
    public function getMonthlyCreationStatistics(int $months = 12): array
    {
        $startDate = new \DateTime("-{$months} months");

        return $this->createQueryBuilder('ac')
            ->select('DATE_FORMAT(ac.createdAt, \'%Y-%m\') as month', 'COUNT(ac.id) as count')
            ->where('ac.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search contracts with filters
     *
     * @param array $filters
     * @return AlternanceContract[]
     */
    public function searchWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('ac')
            ->leftJoin('ac.student', 's')
            ->leftJoin('ac.mentor', 'm')
            ->leftJoin('ac.session', 'sess')
            ->leftJoin('sess.formation', 'f');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                'ac.companyName LIKE :search',
                's.firstName LIKE :search',
                's.lastName LIKE :search',
                'm.firstName LIKE :search',
                'm.lastName LIKE :search',
                'f.title LIKE :search'
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('ac.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['contractType'])) {
            $qb->andWhere('ac.contractType = :contractType')
                ->setParameter('contractType', $filters['contractType']);
        }

        if (!empty($filters['mentorId'])) {
            $qb->andWhere('ac.mentor = :mentorId')
                ->setParameter('mentorId', $filters['mentorId']);
        }

        if (!empty($filters['startDateFrom'])) {
            $qb->andWhere('ac.startDate >= :startDateFrom')
                ->setParameter('startDateFrom', $filters['startDateFrom']);
        }

        if (!empty($filters['startDateTo'])) {
            $qb->andWhere('ac.startDate <= :startDateTo')
                ->setParameter('startDateTo', $filters['startDateTo']);
        }

        $sortField = $filters['sort'] ?? 'startDate';
        $sortDirection = $filters['direction'] ?? 'DESC';

        $qb->orderBy('ac.' . $sortField, $sortDirection);

        return $qb->getQuery()->getResult();
    }

    /**
     * Count contracts by status
     *
     * @param string $status
     * @return int
     */
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('ac')
            ->select('COUNT(ac.id)')
            ->where('ac.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find contracts with relations for display
     *
     * @param array $criteria
     * @param array $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return AlternanceContract[]
     */
    public function findWithRelations(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('ac')
            ->leftJoin('ac.student', 's')
            ->leftJoin('ac.mentor', 'm')
            ->leftJoin('ac.pedagogicalSupervisor', 'ps')
            ->leftJoin('ac.session', 'sess')
            ->leftJoin('sess.formation', 'f')
            ->addSelect('s', 'm', 'ps', 'sess', 'f');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("ac.{$field} = :{$field}")
                ->setParameter($field, $value);
        }

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy("ac.{$field}", $direction);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find paginated contracts with filters
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return AlternanceContract[]
     */
    public function findPaginatedContracts(array $filters, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        
        $qb = $this->createQueryBuilder('ac')
            ->leftJoin('ac.student', 's')
            ->leftJoin('ac.mentor', 'm')
            ->leftJoin('ac.pedagogicalSupervisor', 'ps')
            ->leftJoin('ac.session', 'sess')
            ->leftJoin('sess.formation', 'f')
            ->addSelect('s', 'm', 'ps', 'sess', 'f');

        // Apply filters
        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                'ac.companyName LIKE :search',
                'ac.companySiret LIKE :search',
                's.firstName LIKE :search',
                's.lastName LIKE :search',
                's.email LIKE :search',
                'm.firstName LIKE :search',
                'm.lastName LIKE :search',
                'f.title LIKE :search'
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('ac.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['contractType'])) {
            $qb->andWhere('ac.contractType = :contractType')
                ->setParameter('contractType', $filters['contractType']);
        }

        if (!empty($filters['mentorId'])) {
            $qb->andWhere('ac.mentor = :mentorId')
                ->setParameter('mentorId', $filters['mentorId']);
        }

        if (!empty($filters['startDateFrom'])) {
            $qb->andWhere('ac.startDate >= :startDateFrom')
                ->setParameter('startDateFrom', $filters['startDateFrom']);
        }

        if (!empty($filters['startDateTo'])) {
            $qb->andWhere('ac.startDate <= :startDateTo')
                ->setParameter('startDateTo', $filters['startDateTo']);
        }

        return $qb->orderBy('ac.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count filtered contracts
     *
     * @param array $filters
     * @return int
     */
    public function countFilteredContracts(array $filters): int
    {
        $qb = $this->createQueryBuilder('ac')
            ->select('COUNT(ac.id)')
            ->leftJoin('ac.student', 's')
            ->leftJoin('ac.mentor', 'm')
            ->leftJoin('ac.session', 'sess')
            ->leftJoin('sess.formation', 'f');

        // Apply same filters as findPaginatedContracts
        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                'ac.companyName LIKE :search',
                'ac.companySiret LIKE :search',
                's.firstName LIKE :search',
                's.lastName LIKE :search',
                's.email LIKE :search',
                'm.firstName LIKE :search',
                'm.lastName LIKE :search',
                'f.title LIKE :search'
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('ac.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['contractType'])) {
            $qb->andWhere('ac.contractType = :contractType')
                ->setParameter('contractType', $filters['contractType']);
        }

        if (!empty($filters['mentorId'])) {
            $qb->andWhere('ac.mentor = :mentorId')
                ->setParameter('mentorId', $filters['mentorId']);
        }

        if (!empty($filters['startDateFrom'])) {
            $qb->andWhere('ac.startDate >= :startDateFrom')
                ->setParameter('startDateFrom', $filters['startDateFrom']);
        }

        if (!empty($filters['startDateTo'])) {
            $qb->andWhere('ac.startDate <= :startDateTo')
                ->setParameter('startDateTo', $filters['startDateTo']);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get contract statistics for dashboard
     *
     * @return array
     */
    public function getContractStatistics(): array
    {
        // Get total count
        $total = $this->createQueryBuilder('ac')
            ->select('COUNT(ac.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get status statistics
        $statusStats = $this->createQueryBuilder('ac')
            ->select('ac.status', 'COUNT(ac.id) as count')
            ->groupBy('ac.status')
            ->getQuery()
            ->getResult();

        $statistics = [
            'total' => (int) $total,
            'active' => 0,
            'pending' => 0,
            'completed' => 0,
            'draft' => 0,
            'validated' => 0,
            'suspended' => 0,
            'terminated' => 0,
        ];

        foreach ($statusStats as $stat) {
            $status = $stat['status'];
            $count = (int) $stat['count'];
            
            if ($status === 'pending_validation') {
                $statistics['pending'] = $count;
            } else {
                $statistics[$status] = $count;
            }
        }

        return $statistics;
    }
}
