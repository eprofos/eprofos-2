<?php

declare(strict_types=1);

namespace App\Repository\Alternance;

use App\Entity\Alternance\CompanyMission;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for CompanyMission entity.
 *
 * Provides query methods for company missions with filtering,
 * searching, progression logic, and statistics functionality.
 *
 * @extends ServiceEntityRepository<CompanyMission>
 */
class CompanyMissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyMission::class);
    }

    /**
     * Find missions by term and complexity.
     *
     * @return CompanyMission[]
     */
    public function findByTermAndComplexity(string $term, string $complexity): array
    {
        return $this->createQueryBuilder('cm')
            ->where('cm.term = :term')
            ->andWhere('cm.complexity = :complexity')
            ->andWhere('cm.isActive = true')
            ->setParameter('term', $term)
            ->setParameter('complexity', $complexity)
            ->orderBy('cm.orderIndex', 'ASC')
            ->addOrderBy('cm.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find next recommended missions for a student based on their progress.
     *
     * @return CompanyMission[]
     */
    public function findNextRecommendedMissions(Student $student, int $limit = 10): array
    {
        // This is a complex query that would analyze the student's completed missions
        // and recommend next missions based on progression logic

        $qb = $this->createQueryBuilder('cm')
            ->leftJoin('cm.assignments', 'ma')
            ->leftJoin('ma.student', 's')
            ->where('cm.isActive = true')
            ->andWhere('(ma.student IS NULL OR ma.student != :student OR ma.status != :completed)')
            ->setParameter('student', $student)
            ->setParameter('completed', 'terminee')
            ->orderBy('cm.term', 'ASC')
            ->addOrderBy('cm.complexity', 'ASC')
            ->addOrderBy('cm.orderIndex', 'ASC')
            ->setMaxResults($limit)
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * Find missions by mentor and status.
     *
     * @return CompanyMission[]
     */
    public function findByMentorAndStatus(Mentor $mentor, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->leftJoin('cm.assignments', 'ma')
            ->where('cm.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
        ;

        if ($status) {
            $qb->andWhere('ma.status = :status')
                ->setParameter('status', $status)
            ;
        }

        return $qb->orderBy('cm.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find active missions by mentor.
     *
     * @return CompanyMission[]
     */
    public function findActiveMissionsByMentor(Mentor $mentor): array
    {
        return $this->createQueryBuilder('cm')
            ->leftJoin('cm.assignments', 'ma')
            ->where('cm.supervisor = :mentor')
            ->andWhere('cm.isActive = true')
            ->andWhere('ma.status IN (:activeStatuses)')
            ->setParameter('mentor', $mentor)
            ->setParameter('activeStatuses', ['planifiee', 'en_cours'])
            ->orderBy('cm.orderIndex', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find missions by complexity level.
     *
     * @return CompanyMission[]
     */
    public function findByComplexity(string $complexity, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->where('cm.complexity = :complexity')
            ->setParameter('complexity', $complexity)
            ->orderBy('cm.orderIndex', 'ASC')
        ;

        if ($activeOnly) {
            $qb->andWhere('cm.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find missions by department.
     *
     * @return CompanyMission[]
     */
    public function findByDepartment(string $department, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->where('cm.department = :department')
            ->setParameter('department', $department)
            ->orderBy('cm.orderIndex', 'ASC')
        ;

        if ($activeOnly) {
            $qb->andWhere('cm.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search missions by keywords in title, description, or objectives.
     *
     * @return CompanyMission[]
     */
    public function searchMissions(string $keywords, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->where('cm.isActive = true')
            ->andWhere(
                'cm.title LIKE :keywords OR cm.description LIKE :keywords OR JSON_CONTAINS(cm.objectives, :objectiveKeywords) = 1',
            )
            ->setParameter('keywords', '%' . $keywords . '%')
            ->setParameter('objectiveKeywords', json_encode([$keywords]))
        ;

        // Apply filters
        if (isset($filters['complexity']) && $filters['complexity']) {
            $qb->andWhere('cm.complexity = :complexity')
                ->setParameter('complexity', $filters['complexity'])
            ;
        }

        if (isset($filters['term']) && $filters['term']) {
            $qb->andWhere('cm.term = :term')
                ->setParameter('term', $filters['term'])
            ;
        }

        if (isset($filters['department']) && $filters['department']) {
            $qb->andWhere('cm.department = :department')
                ->setParameter('department', $filters['department'])
            ;
        }

        if (isset($filters['supervisor']) && $filters['supervisor']) {
            $qb->andWhere('cm.supervisor = :supervisor')
                ->setParameter('supervisor', $filters['supervisor'])
            ;
        }

        return $qb->orderBy('cm.orderIndex', 'ASC')
            ->addOrderBy('cm.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get missions statistics by mentor.
     */
    public function getMissionStatsByMentor(Mentor $mentor): array
    {
        $result = $this->createQueryBuilder('cm')
            ->select('
                COUNT(cm.id) as total_missions,
                SUM(CASE WHEN cm.isActive = true THEN 1 ELSE 0 END) as active_missions,
                SUM(CASE WHEN cm.complexity = \'debutant\' THEN 1 ELSE 0 END) as beginner_missions,
                SUM(CASE WHEN cm.complexity = \'intermediaire\' THEN 1 ELSE 0 END) as intermediate_missions,
                SUM(CASE WHEN cm.complexity = \'avance\' THEN 1 ELSE 0 END) as advanced_missions
            ')
            ->where('cm.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
            ->getQuery()
            ->getSingleResult()
        ;

        return [
            'total' => (int) $result['total_missions'],
            'active' => (int) $result['active_missions'],
            'by_complexity' => [
                'debutant' => (int) $result['beginner_missions'],
                'intermediaire' => (int) $result['intermediate_missions'],
                'avance' => (int) $result['advanced_missions'],
            ],
        ];
    }

    /**
     * Get missions statistics by term.
     */
    public function getMissionStatsByTerm(): array
    {
        $result = $this->createQueryBuilder('cm')
            ->select('
                cm.term,
                COUNT(cm.id) as mission_count
            ')
            ->where('cm.isActive = true')
            ->groupBy('cm.term')
            ->getQuery()
            ->getResult()
        ;

        $stats = ['court' => 0, 'moyen' => 0, 'long' => 0];
        foreach ($result as $row) {
            $stats[$row['term']] = (int) $row['mission_count'];
        }

        return $stats;
    }

    /**
     * Find missions with the most assignments.
     *
     * @return CompanyMission[]
     */
    public function findMostAssignedMissions(int $limit = 10): array
    {
        return $this->createQueryBuilder('cm')
            ->leftJoin('cm.assignments', 'ma')
            ->where('cm.isActive = true')
            ->groupBy('cm.id')
            ->orderBy('COUNT(ma.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find missions without any assignments.
     *
     * @return CompanyMission[]
     */
    public function findUnassignedMissions(): array
    {
        return $this->createQueryBuilder('cm')
            ->leftJoin('cm.assignments', 'ma')
            ->where('cm.isActive = true')
            ->andWhere('ma.id IS NULL')
            ->orderBy('cm.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Create a query builder for missions with common joins.
     */
    public function createMissionQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('cm')
            ->leftJoin('cm.supervisor', 's')
            ->leftJoin('cm.assignments', 'ma')
            ->addSelect('s', 'ma')
        ;
    }

    /**
     * Find missions by order index range for progression.
     *
     * @return CompanyMission[]
     */
    public function findByOrderRange(int $minOrder, int $maxOrder, ?string $term = null, ?string $complexity = null): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->where('cm.orderIndex BETWEEN :minOrder AND :maxOrder')
            ->andWhere('cm.isActive = true')
            ->setParameter('minOrder', $minOrder)
            ->setParameter('maxOrder', $maxOrder)
        ;

        if ($term) {
            $qb->andWhere('cm.term = :term')
                ->setParameter('term', $term)
            ;
        }

        if ($complexity) {
            $qb->andWhere('cm.complexity = :complexity')
                ->setParameter('complexity', $complexity)
            ;
        }

        return $qb->orderBy('cm.orderIndex', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get the next order index for a given term and complexity.
     */
    public function getNextOrderIndex(string $term, string $complexity): int
    {
        $result = $this->createQueryBuilder('cm')
            ->select('MAX(cm.orderIndex) as max_order')
            ->where('cm.term = :term')
            ->andWhere('cm.complexity = :complexity')
            ->setParameter('term', $term)
            ->setParameter('complexity', $complexity)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return ($result ?? 0) + 1;
    }

    /**
     * Count missions by status of their assignments.
     */
    public function countMissionsByAssignmentStatus(?Mentor $mentor = null): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->leftJoin('cm.assignments', 'ma')
            ->select('
                SUM(CASE WHEN ma.status = \'planifiee\' THEN 1 ELSE 0 END) as planned,
                SUM(CASE WHEN ma.status = \'en_cours\' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN ma.status = \'terminee\' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN ma.status = \'suspendue\' THEN 1 ELSE 0 END) as suspended
            ')
            ->where('cm.isActive = true')
        ;

        if ($mentor) {
            $qb->andWhere('cm.supervisor = :mentor')
                ->setParameter('mentor', $mentor)
            ;
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'planifiee' => (int) $result['planned'],
            'en_cours' => (int) $result['in_progress'],
            'terminee' => (int) $result['completed'],
            'suspendue' => (int) $result['suspended'],
        ];
    }

    /**
     * Find missions suitable for a student based on their current level.
     *
     * @return CompanyMission[]
     */
    public function findSuitableMissionsForStudent(Student $student, string $targetComplexity = 'debutant', int $limit = 10): array
    {
        // Get complexity levels in order
        $complexityOrder = ['debutant', 'intermediaire', 'avance'];
        $maxComplexityIndex = array_search($targetComplexity, $complexityOrder, true);

        if ($maxComplexityIndex === false) {
            $maxComplexityIndex = 0;
        }

        $allowedComplexities = array_slice($complexityOrder, 0, $maxComplexityIndex + 1);

        return $this->createQueryBuilder('cm')
            ->leftJoin('cm.assignments', 'ma', 'WITH', 'ma.student = :student')
            ->where('cm.isActive = true')
            ->andWhere('cm.complexity IN (:complexities)')
            ->andWhere('ma.id IS NULL OR ma.status != :completed')
            ->setParameter('student', $student)
            ->setParameter('complexities', $allowedComplexities)
            ->setParameter('completed', 'terminee')
            ->orderBy('cm.term', 'ASC')
            ->addOrderBy('cm.complexity', 'ASC')
            ->addOrderBy('cm.orderIndex', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    // Additional methods for Doctrine repository pattern
    public function save(CompanyMission $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CompanyMission $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find paginated missions with filters.
     */
    public function findPaginatedMissions(array $filters, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('cm');

        // Apply filters
        if (!empty($filters['search'])) {
            $qb->andWhere('cm.title LIKE :search OR cm.description LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%')
            ;
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $qb->andWhere('cm.isActive = true');
            } elseif ($filters['status'] === 'inactive') {
                $qb->andWhere('cm.isActive = false');
            }
        }

        if (!empty($filters['complexity'])) {
            $qb->andWhere('cm.complexity = :complexity')
                ->setParameter('complexity', $filters['complexity'])
            ;
        }

        $offset = ($page - 1) * $perPage;
        $qb->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->orderBy('cm.createdAt', 'DESC')
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * Count filtered missions.
     */
    public function countFilteredMissions(array $filters): int
    {
        $qb = $this->createQueryBuilder('cm')
            ->select('COUNT(cm.id)')
        ;

        // Apply same filters as findPaginatedMissions
        if (!empty($filters['search'])) {
            $qb->andWhere('cm.title LIKE :search OR cm.description LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%')
            ;
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $qb->andWhere('cm.isActive = true');
            } elseif ($filters['status'] === 'inactive') {
                $qb->andWhere('cm.isActive = false');
            }
        }

        if (!empty($filters['complexity'])) {
            $qb->andWhere('cm.complexity = :complexity')
                ->setParameter('complexity', $filters['complexity'])
            ;
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count active missions.
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('cm')
            ->select('COUNT(cm.id)')
            ->where('cm.isActive = true')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Get difficulty distribution.
     */
    public function getComplexityDistribution(): array
    {
        return $this->createQueryBuilder('cm')
            ->select('cm.complexity, COUNT(cm.id) as count')
            ->groupBy('cm.complexity')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find recent missions.
     */
    public function findRecentMissions(int $limit): array
    {
        return $this->createQueryBuilder('cm')
            ->orderBy('cm.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find missions for export.
     */
    public function findForExport(array $filters): array
    {
        $qb = $this->createQueryBuilder('cm');

        // Apply filters (same as paginated but without pagination)
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $qb->andWhere('cm.isActive = true');
            } elseif ($filters['status'] === 'inactive') {
                $qb->andWhere('cm.isActive = false');
            }
        }

        if (!empty($filters['complexity'])) {
            $qb->andWhere('cm.complexity = :complexity')
                ->setParameter('complexity', $filters['complexity']);
        }

        return $qb->orderBy('cm.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
