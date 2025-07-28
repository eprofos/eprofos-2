<?php

declare(strict_types=1);

namespace App\Repository\Alternance;

use App\Entity\Alternance\AlternanceProgram;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for AlternanceProgram entity.
 *
 * Provides query methods for alternance programs with filtering,
 * searching, and statistics functionality.
 *
 * @extends ServiceEntityRepository<AlternanceProgram>
 */
class AlternanceProgramRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlternanceProgram::class);
    }

    /**
     * Find program by session.
     */
    public function findBySession(int $sessionId): ?AlternanceProgram
    {
        return $this->createQueryBuilder('ap')
            ->where('ap.session = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find programs by duration range.
     *
     * @return AlternanceProgram[]
     */
    public function findByDurationRange(?int $minDuration = null, ?int $maxDuration = null): array
    {
        $qb = $this->createQueryBuilder('ap');

        if ($minDuration) {
            $qb->andWhere('ap.totalDuration >= :minDuration')
                ->setParameter('minDuration', $minDuration)
            ;
        }

        if ($maxDuration) {
            $qb->andWhere('ap.totalDuration <= :maxDuration')
                ->setParameter('maxDuration', $maxDuration)
            ;
        }

        return $qb->orderBy('ap.totalDuration', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find programs by rhythm.
     *
     * @return AlternanceProgram[]
     */
    public function findByRhythm(string $rhythm): array
    {
        return $this->createQueryBuilder('ap')
            ->where('ap.rhythm = :rhythm')
            ->setParameter('rhythm', $rhythm)
            ->orderBy('ap.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find programs by formation.
     *
     * @return AlternanceProgram[]
     */
    public function findByFormation(int $formationId): array
    {
        return $this->createQueryBuilder('ap')
            ->leftJoin('ap.session', 's')
            ->where('s.formation = :formationId')
            ->setParameter('formationId', $formationId)
            ->orderBy('ap.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Search programs with filters.
     *
     * @return AlternanceProgram[]
     */
    public function searchWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('ap')
            ->leftJoin('ap.session', 's')
            ->leftJoin('s.formation', 'f')
        ;

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                'ap.title LIKE :search',
                'ap.description LIKE :search',
                'f.title LIKE :search',
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['rhythm'])) {
            $qb->andWhere('ap.rhythm = :rhythm')
                ->setParameter('rhythm', $filters['rhythm'])
            ;
        }

        if (!empty($filters['minDuration'])) {
            $qb->andWhere('ap.totalDuration >= :minDuration')
                ->setParameter('minDuration', $filters['minDuration'])
            ;
        }

        if (!empty($filters['maxDuration'])) {
            $qb->andWhere('ap.totalDuration <= :maxDuration')
                ->setParameter('maxDuration', $filters['maxDuration'])
            ;
        }

        if (!empty($filters['formationId'])) {
            $qb->andWhere('s.formation = :formationId')
                ->setParameter('formationId', $filters['formationId'])
            ;
        }

        $sortField = $filters['sort'] ?? 'title';
        $sortDirection = $filters['direction'] ?? 'ASC';

        $qb->orderBy('ap.' . $sortField, $sortDirection);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get duration statistics.
     */
    public function getDurationStatistics(): array
    {
        $result = $this->createQueryBuilder('ap')
            ->select('MIN(ap.totalDuration) as minDuration')
            ->addSelect('MAX(ap.totalDuration) as maxDuration')
            ->addSelect('AVG(ap.totalDuration) as avgDuration')
            ->addSelect('COUNT(ap.id) as totalPrograms')
            ->getQuery()
            ->getSingleResult()
        ;

        return [
            'minDuration' => (int) $result['minDuration'],
            'maxDuration' => (int) $result['maxDuration'],
            'avgDuration' => round($result['avgDuration'], 1),
            'totalPrograms' => (int) $result['totalPrograms'],
        ];
    }

    /**
     * Get rhythm statistics.
     */
    public function getRhythmStatistics(): array
    {
        $result = $this->createQueryBuilder('ap')
            ->select('ap.rhythm', 'COUNT(ap.id) as count')
            ->groupBy('ap.rhythm')
            ->getQuery()
            ->getResult()
        ;

        $statistics = [];
        foreach ($result as $row) {
            $statistics[$row['rhythm']] = (int) $row['count'];
        }

        return $statistics;
    }

    /**
     * Get center vs company duration statistics.
     */
    public function getCenterCompanyStatistics(): array
    {
        return $this->createQueryBuilder('ap')
            ->select('AVG(ap.centerDuration) as avgCenterDuration')
            ->addSelect('AVG(ap.companyDuration) as avgCompanyDuration')
            ->addSelect('AVG(ap.centerDuration / ap.totalDuration * 100) as avgCenterPercentage')
            ->addSelect('AVG(ap.companyDuration / ap.totalDuration * 100) as avgCompanyPercentage')
            ->getQuery()
            ->getSingleResult()
        ;
    }

    /**
     * Find programs with relations for display.
     *
     * @return AlternanceProgram[]
     */
    public function findWithRelations(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->createQueryBuilder('ap')
            ->leftJoin('ap.session', 's')
            ->leftJoin('s.formation', 'f')
            ->addSelect('s', 'f')
        ;

        foreach ($criteria as $field => $value) {
            $qb->andWhere("ap.{$field} = :{$field}")
                ->setParameter($field, $value)
            ;
        }

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy("ap.{$field}", $direction);
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
     * Find programs by center modules count.
     *
     * @return AlternanceProgram[]
     */
    public function findByCenterModulesCount(int $count, string $operator = '>='): array
    {
        $qb = $this->createQueryBuilder('ap');

        switch ($operator) {
            case '=':
                $qb->andWhere('JSON_LENGTH(ap.centerModules) = :count');
                break;

            case '>=':
                $qb->andWhere('JSON_LENGTH(ap.centerModules) >= :count');
                break;

            case '<=':
                $qb->andWhere('JSON_LENGTH(ap.centerModules) <= :count');
                break;

            case '>':
                $qb->andWhere('JSON_LENGTH(ap.centerModules) > :count');
                break;

            case '<':
                $qb->andWhere('JSON_LENGTH(ap.centerModules) < :count');
                break;
        }

        $qb->setParameter('count', $count);

        return $qb->orderBy('ap.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find programs by company modules count.
     *
     * @return AlternanceProgram[]
     */
    public function findByCompanyModulesCount(int $count, string $operator = '>='): array
    {
        $qb = $this->createQueryBuilder('ap');

        switch ($operator) {
            case '=':
                $qb->andWhere('JSON_LENGTH(ap.companyModules) = :count');
                break;

            case '>=':
                $qb->andWhere('JSON_LENGTH(ap.companyModules) >= :count');
                break;

            case '<=':
                $qb->andWhere('JSON_LENGTH(ap.companyModules) <= :count');
                break;

            case '>':
                $qb->andWhere('JSON_LENGTH(ap.companyModules) > :count');
                break;

            case '<':
                $qb->andWhere('JSON_LENGTH(ap.companyModules) < :count');
                break;
        }

        $qb->setParameter('count', $count);

        return $qb->orderBy('ap.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get monthly creation statistics.
     *
     * @param int $months Number of months to look back
     */
    public function getMonthlyCreationStatistics(int $months = 12): array
    {
        $startDate = new DateTime("-{$months} months");

        return $this->createQueryBuilder('ap')
            ->select('DATE_FORMAT(ap.createdAt, \'%Y-%m\') as month', 'COUNT(ap.id) as count')
            ->where('ap.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Count programs.
     */
    public function countPrograms(): int
    {
        return $this->createQueryBuilder('ap')
            ->select('COUNT(ap.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Find recent programs.
     *
     * @return AlternanceProgram[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('ap')
            ->orderBy('ap.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
