<?php

namespace App\Repository;

use App\Entity\CompanyNeedsAnalysis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for CompanyNeedsAnalysis entity
 * 
 * Provides custom query methods for company needs analysis
 * including filtering by company characteristics and training needs.
 */
class CompanyNeedsAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyNeedsAnalysis::class);
    }

    /**
     * Save a company needs analysis
     */
    public function save(CompanyNeedsAnalysis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a company needs analysis
     */
    public function remove(CompanyNeedsAnalysis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find analyses by employee count range
     */
    public function findByEmployeeCountRange(int $minCount, int $maxCount = null): array
    {
        $qb = $this->createQueryBuilder('cna')
            ->andWhere('cna.employeeCount >= :minCount')
            ->setParameter('minCount', $minCount);

        if ($maxCount !== null) {
            $qb->andWhere('cna.employeeCount <= :maxCount')
               ->setParameter('maxCount', $maxCount);
        }

        return $qb->orderBy('cna.id', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find analyses by NAF code
     */
    public function findByNafCode(string $nafCode): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.nafCode = :nafCode')
            ->setParameter('nafCode', $nafCode)
            ->orderBy('cna.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find analyses by SIRET number
     */
    public function findBySiret(string $siret): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.siret = :siret')
            ->setParameter('siret', $siret)
            ->orderBy('cna.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find analyses by training title
     */
    public function findByTrainingTitle(string $trainingTitle): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.trainingTitle LIKE :trainingTitle')
            ->setParameter('trainingTitle', '%' . $trainingTitle . '%')
            ->orderBy('cna.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find analyses by training location preference
     */
    public function findByTrainingLocationPreference(string $locationPreference): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.trainingLocationPreference = :locationPreference')
            ->setParameter('locationPreference', $locationPreference)
            ->orderBy('cna.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find analyses by activity sector
     */
    public function findByActivitySector(string $activitySector): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.activitySector LIKE :activitySector')
            ->setParameter('activitySector', '%' . $activitySector . '%')
            ->orderBy('cna.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find analyses by training duration range
     */
    public function findByTrainingDurationRange(int $minHours, int $maxHours = null): array
    {
        $qb = $this->createQueryBuilder('cna')
            ->andWhere('cna.trainingDurationHours >= :minHours')
            ->setParameter('minHours', $minHours);

        if ($maxHours !== null) {
            $qb->andWhere('cna.trainingDurationHours <= :maxHours')
               ->setParameter('maxHours', $maxHours);
        }

        return $qb->orderBy('cna.id', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find analyses by OPCO
     */
    public function findByOpco(string $opco): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.opco LIKE :opco')
            ->setParameter('opco', '%' . $opco . '%')
            ->orderBy('cna.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics for company analyses
     */
    public function getCompanyStatistics(): array
    {
        $stats = [
            'total' => $this->count([]),
            'by_location_preference' => [],
            'by_activity_sector' => [],
            'average_employees' => 0,
            'average_training_duration' => 0,
            'average_trainees' => 0,
        ];

        // Location preference distribution
        $locationStats = $this->createQueryBuilder('cna')
            ->select('cna.trainingLocationPreference, COUNT(cna.id) as count')
            ->groupBy('cna.trainingLocationPreference')
            ->getQuery()
            ->getResult();

        foreach ($locationStats as $stat) {
            $stats['by_location_preference'][$stat['trainingLocationPreference']] = (int) $stat['count'];
        }

        // Activity sector distribution (top 10)
        $sectorStats = $this->createQueryBuilder('cna')
            ->select('cna.activitySector, COUNT(cna.id) as count')
            ->groupBy('cna.activitySector')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        foreach ($sectorStats as $stat) {
            $stats['by_activity_sector'][$stat['activitySector']] = (int) $stat['count'];
        }

        // Average employee count
        $avgEmployees = $this->createQueryBuilder('cna')
            ->select('AVG(cna.employeeCount) as avg')
            ->getQuery()
            ->getSingleScalarResult();

        $stats['average_employees'] = round((float) $avgEmployees, 1);

        // Average training duration
        $avgDuration = $this->createQueryBuilder('cna')
            ->select('AVG(cna.trainingDurationHours) as avg')
            ->getQuery()
            ->getSingleScalarResult();

        $stats['average_training_duration'] = round((float) $avgDuration, 1);

        // Average number of trainees (calculated from JSON field)
        $allAnalyses = $this->findAll();
        $totalTrainees = 0;
        $totalAnalyses = count($allAnalyses);

        foreach ($allAnalyses as $analysis) {
            $totalTrainees += $analysis->getTraineesCount();
        }

        if ($totalAnalyses > 0) {
            $stats['average_trainees'] = round($totalTrainees / $totalAnalyses, 1);
        }

        return $stats;
    }

    /**
     * Find analyses with filters for admin interface
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('cna')
            ->leftJoin('cna.needsAnalysisRequest', 'nar')
            ->addSelect('nar');

        if (!empty($filters['employee_count_min'])) {
            $qb->andWhere('cna.employeeCount >= :employeeCountMin')
               ->setParameter('employeeCountMin', $filters['employee_count_min']);
        }

        if (!empty($filters['employee_count_max'])) {
            $qb->andWhere('cna.employeeCount <= :employeeCountMax')
               ->setParameter('employeeCountMax', $filters['employee_count_max']);
        }

        if (!empty($filters['training_location_preference'])) {
            $qb->andWhere('cna.trainingLocationPreference = :trainingLocationPreference')
               ->setParameter('trainingLocationPreference', $filters['training_location_preference']);
        }

        if (!empty($filters['activity_sector'])) {
            $qb->andWhere('cna.activitySector LIKE :activitySector')
               ->setParameter('activitySector', '%' . $filters['activity_sector'] . '%');
        }

        if (!empty($filters['naf_code'])) {
            $qb->andWhere('cna.nafCode LIKE :nafCode')
               ->setParameter('nafCode', '%' . $filters['naf_code'] . '%');
        }

        if (!empty($filters['siret'])) {
            $qb->andWhere('cna.siret LIKE :siret')
               ->setParameter('siret', '%' . $filters['siret'] . '%');
        }

        if (!empty($filters['training_duration_min'])) {
            $qb->andWhere('cna.trainingDurationHours >= :trainingDurationMin')
               ->setParameter('trainingDurationMin', $filters['training_duration_min']);
        }

        if (!empty($filters['training_duration_max'])) {
            $qb->andWhere('cna.trainingDurationHours <= :trainingDurationMax')
               ->setParameter('trainingDurationMax', $filters['training_duration_max']);
        }

        if (!empty($filters['opco'])) {
            $qb->andWhere('cna.opco LIKE :opco')
               ->setParameter('opco', '%' . $filters['opco'] . '%');
        }

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                'cna.companyName LIKE :search',
                'cna.responsiblePerson LIKE :search',
                'cna.contactEmail LIKE :search',
                'cna.siret LIKE :search',
                'cna.nafCode LIKE :search',
                'cna.trainingTitle LIKE :search'
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('cna.id', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find companies with similar training needs
     */
    public function findSimilarTrainingNeeds(CompanyNeedsAnalysis $analysis): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.id != :currentId')
            ->andWhere('cna.trainingTitle = :trainingTitle')
            ->andWhere('cna.activitySector = :activitySector')
            ->setParameter('currentId', $analysis->getId())
            ->setParameter('trainingTitle', $analysis->getTrainingTitle())
            ->setParameter('activitySector', $analysis->getActivitySector())
            ->orderBy('cna.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find analyses by sector (based on NAF code)
     */
    public function findBySector(string $sectorPrefix): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.nafCode LIKE :sectorPrefix')
            ->setParameter('sectorPrefix', $sectorPrefix . '%')
            ->orderBy('cna.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get most common training titles
     */
    public function getMostCommonTrainingTitles(int $limit = 10): array
    {
        return $this->createQueryBuilder('cna')
            ->select('cna.trainingTitle, COUNT(cna.id) as count')
            ->groupBy('cna.trainingTitle')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get employee count distribution
     */
    public function getEmployeeCountDistribution(): array
    {
        $ranges = [
            '1-10' => [1, 10],
            '11-50' => [11, 50],
            '51-250' => [51, 250],
            '251-500' => [251, 500],
            '500+' => [501, 999999]
        ];

        $distribution = [];
        foreach ($ranges as $label => $range) {
            $count = $this->createQueryBuilder('cna')
                ->select('COUNT(cna.id)')
                ->andWhere('cna.employeeCount >= :min')
                ->andWhere('cna.employeeCount <= :max')
                ->setParameter('min', $range[0])
                ->setParameter('max', $range[1])
                ->getQuery()
                ->getSingleScalarResult();

            $distribution[$label] = (int) $count;
        }

        return $distribution;
    }

    /**
     * Find analyses with specific training characteristics
     */
    public function findByTrainingCharacteristics(array $characteristics): array
    {
        $qb = $this->createQueryBuilder('cna');

        if (isset($characteristics['title'])) {
            $qb->andWhere('cna.trainingTitle LIKE :title')
               ->setParameter('title', '%' . $characteristics['title'] . '%');
        }

        if (isset($characteristics['location_preference'])) {
            $qb->andWhere('cna.trainingLocationPreference = :locationPreference')
               ->setParameter('locationPreference', $characteristics['location_preference']);
        }

        if (isset($characteristics['min_duration'])) {
            $qb->andWhere('cna.trainingDurationHours >= :minDuration')
               ->setParameter('minDuration', $characteristics['min_duration']);
        }

        if (isset($characteristics['max_duration'])) {
            $qb->andWhere('cna.trainingDurationHours <= :maxDuration')
               ->setParameter('maxDuration', $characteristics['max_duration']);
        }

        return $qb->orderBy('cna.id', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find analyses by date range
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.submittedAt >= :startDate')
            ->andWhere('cna.submittedAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('cna.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent analyses (last 30 days)
     */
    public function findRecentAnalyses(int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");
        
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.submittedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('cna.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find analyses with disability accommodations
     */
    public function findWithDisabilityAccommodations(): array
    {
        return $this->createQueryBuilder('cna')
            ->andWhere('cna.disabilityAccommodations IS NOT NULL')
            ->andWhere('cna.disabilityAccommodations != :empty')
            ->setParameter('empty', '')
            ->orderBy('cna.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}