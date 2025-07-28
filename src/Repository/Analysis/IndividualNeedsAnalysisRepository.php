<?php

declare(strict_types=1);

namespace App\Repository\Analysis;

use App\Entity\Analysis\IndividualNeedsAnalysis;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for IndividualNeedsAnalysis entity.
 *
 * Provides custom query methods for individual needs analysis
 * including filtering by professional status, funding type, and personal characteristics.
 */
class IndividualNeedsAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IndividualNeedsAnalysis::class);
    }

    /**
     * Save an individual needs analysis.
     */
    public function save(IndividualNeedsAnalysis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove an individual needs analysis.
     */
    public function remove(IndividualNeedsAnalysis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find analyses by professional status.
     */
    public function findByProfessionalStatus(string $status): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.professionalStatus = :status')
            ->setParameter('status', $status)
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses by funding type.
     */
    public function findByFundingType(string $fundingType): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.fundingType = :fundingType')
            ->setParameter('fundingType', $fundingType)
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses by education level.
     */
    public function findByEducationLevel(string $educationLevel): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.educationLevel = :educationLevel')
            ->setParameter('educationLevel', $educationLevel)
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses by age range.
     */
    public function findByAgeRange(int $minAge, ?int $maxAge = null): array
    {
        $qb = $this->createQueryBuilder('ina')
            ->andWhere('ina.age >= :minAge')
            ->setParameter('minAge', $minAge)
        ;

        if ($maxAge !== null) {
            $qb->andWhere('ina.age <= :maxAge')
                ->setParameter('maxAge', $maxAge)
            ;
        }

        return $qb->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses by training objective.
     */
    public function findByTrainingObjective(string $objective): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.trainingObjective = :objective')
            ->setParameter('objective', $objective)
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses by training format preference.
     */
    public function findByTrainingFormatPreference(string $formatPreference): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.trainingFormatPreference = :formatPreference')
            ->setParameter('formatPreference', $formatPreference)
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses with specific availability.
     */
    public function findByAvailability(string $availability): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.availability = :availability')
            ->setParameter('availability', $availability)
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses with disability accommodations.
     */
    public function findWithDisabilityAccommodations(): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.disabilityAccommodations IS NOT NULL')
            ->andWhere('ina.disabilityAccommodations != :empty')
            ->setParameter('empty', '')
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find unemployed individuals.
     */
    public function findUnemployed(): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.professionalStatus IN (:unemployedStatuses)')
            ->setParameter('unemployedStatuses', ['unemployed', 'job_seeker'])
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find employed individuals seeking career change.
     */
    public function findCareerChangeSeekers(): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.professionalStatus = :employed')
            ->andWhere('ina.trainingObjective = :careerChange')
            ->setParameter('employed', 'employed')
            ->setParameter('careerChange', 'career_change')
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get statistics for individual analyses.
     */
    public function getIndividualStatistics(): array
    {
        $stats = [
            'total' => $this->count([]),
            'by_professional_status' => [],
            'by_funding_type' => [],
            'by_education_level' => [],
            'by_training_objective' => [],
            'by_training_format_preference' => [],
            'by_availability' => [],
            'average_age' => 0,
            'age_distribution' => [],
        ];

        // Professional status distribution
        $statusStats = $this->createQueryBuilder('ina')
            ->select('ina.professionalStatus, COUNT(ina.id) as count')
            ->groupBy('ina.professionalStatus')
            ->getQuery()
            ->getResult()
        ;

        foreach ($statusStats as $stat) {
            $stats['by_professional_status'][$stat['professionalStatus']] = (int) $stat['count'];
        }

        // Funding type distribution
        $fundingStats = $this->createQueryBuilder('ina')
            ->select('ina.fundingType, COUNT(ina.id) as count')
            ->groupBy('ina.fundingType')
            ->getQuery()
            ->getResult()
        ;

        foreach ($fundingStats as $stat) {
            $stats['by_funding_type'][$stat['fundingType']] = (int) $stat['count'];
        }

        // Education level distribution
        $educationStats = $this->createQueryBuilder('ina')
            ->select('ina.educationLevel, COUNT(ina.id) as count')
            ->groupBy('ina.educationLevel')
            ->getQuery()
            ->getResult()
        ;

        foreach ($educationStats as $stat) {
            $stats['by_education_level'][$stat['educationLevel']] = (int) $stat['count'];
        }

        // Training objective distribution
        $objectiveStats = $this->createQueryBuilder('ina')
            ->select('ina.trainingObjective, COUNT(ina.id) as count')
            ->groupBy('ina.trainingObjective')
            ->getQuery()
            ->getResult()
        ;

        foreach ($objectiveStats as $stat) {
            $stats['by_training_objective'][$stat['trainingObjective']] = (int) $stat['count'];
        }

        // Training format preference distribution
        $formatStats = $this->createQueryBuilder('ina')
            ->select('ina.trainingFormatPreference, COUNT(ina.id) as count')
            ->groupBy('ina.trainingFormatPreference')
            ->getQuery()
            ->getResult()
        ;

        foreach ($formatStats as $stat) {
            $stats['by_training_format_preference'][$stat['trainingFormatPreference']] = (int) $stat['count'];
        }

        // Availability distribution
        $availabilityStats = $this->createQueryBuilder('ina')
            ->select('ina.availability, COUNT(ina.id) as count')
            ->groupBy('ina.availability')
            ->getQuery()
            ->getResult()
        ;

        foreach ($availabilityStats as $stat) {
            $stats['by_availability'][$stat['availability']] = (int) $stat['count'];
        }

        // Average age
        $avgAge = $this->createQueryBuilder('ina')
            ->select('AVG(ina.age) as avg')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $stats['average_age'] = round((float) $avgAge, 1);

        // Age distribution
        $ageRanges = [
            '18-25' => [18, 25],
            '26-35' => [26, 35],
            '36-45' => [36, 45],
            '46-55' => [46, 55],
            '56+' => [56, 100],
        ];

        foreach ($ageRanges as $label => $range) {
            $count = $this->createQueryBuilder('ina')
                ->select('COUNT(ina.id)')
                ->andWhere('ina.age >= :min')
                ->andWhere('ina.age <= :max')
                ->setParameter('min', $range[0])
                ->setParameter('max', $range[1])
                ->getQuery()
                ->getSingleScalarResult()
            ;

            $stats['age_distribution'][$label] = (int) $count;
        }

        return $stats;
    }

    /**
     * Find analyses with filters for admin interface.
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('ina')
            ->leftJoin('ina.needsAnalysisRequest', 'nar')
            ->addSelect('nar')
        ;

        if (!empty($filters['professional_status'])) {
            $qb->andWhere('ina.professionalStatus = :professionalStatus')
                ->setParameter('professionalStatus', $filters['professional_status'])
            ;
        }

        if (!empty($filters['funding_type'])) {
            $qb->andWhere('ina.fundingType = :fundingType')
                ->setParameter('fundingType', $filters['funding_type'])
            ;
        }

        if (!empty($filters['education_level'])) {
            $qb->andWhere('ina.educationLevel = :educationLevel')
                ->setParameter('educationLevel', $filters['education_level'])
            ;
        }

        if (!empty($filters['training_objective'])) {
            $qb->andWhere('ina.trainingObjective = :trainingObjective')
                ->setParameter('trainingObjective', $filters['training_objective'])
            ;
        }

        if (!empty($filters['training_format_preference'])) {
            $qb->andWhere('ina.trainingFormatPreference = :trainingFormatPreference')
                ->setParameter('trainingFormatPreference', $filters['training_format_preference'])
            ;
        }

        if (!empty($filters['availability'])) {
            $qb->andWhere('ina.availability = :availability')
                ->setParameter('availability', $filters['availability'])
            ;
        }

        if (!empty($filters['age_min'])) {
            $qb->andWhere('ina.age >= :ageMin')
                ->setParameter('ageMin', $filters['age_min'])
            ;
        }

        if (!empty($filters['age_max'])) {
            $qb->andWhere('ina.age <= :ageMax')
                ->setParameter('ageMax', $filters['age_max'])
            ;
        }

        if (!empty($filters['has_disability_accommodations'])) {
            $qb->andWhere('ina.disabilityAccommodations IS NOT NULL')
                ->andWhere('ina.disabilityAccommodations != :empty')
                ->setParameter('empty', '')
            ;
        }

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                'ina.firstName LIKE :search',
                'ina.lastName LIKE :search',
                'ina.email LIKE :search',
                'ina.currentPosition LIKE :search',
                'ina.currentCompany LIKE :search',
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find individuals with similar profiles.
     */
    public function findSimilarProfiles(IndividualNeedsAnalysis $analysis): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.id != :currentId')
            ->andWhere('ina.professionalStatus = :professionalStatus')
            ->andWhere('ina.trainingObjective = :trainingObjective')
            ->andWhere('ina.educationLevel = :educationLevel')
            ->setParameter('currentId', $analysis->getId())
            ->setParameter('professionalStatus', $analysis->getStatus())
            ->setParameter('trainingObjective', $analysis->getProfessionalObjective())
            ->setParameter('educationLevel', $analysis->getCurrentLevel())
            ->orderBy('ina.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses by date range.
     */
    public function findByDateRange(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.submittedAt >= :startDate')
            ->andWhere('ina.submittedAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ina.submittedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get recent analyses (last 30 days).
     */
    public function findRecentAnalyses(int $days = 30): array
    {
        $since = new DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('ina')
            ->andWhere('ina.submittedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('ina.submittedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses by funding eligibility.
     */
    public function findByFundingEligibility(): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.fundingType IN (:eligibleFunding)')
            ->setParameter('eligibleFunding', ['cpf', 'pole_emploi', 'region', 'opco'])
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses requiring specific accommodations.
     */
    public function findRequiringAccommodations(): array
    {
        $qb = $this->createQueryBuilder('ina');

        return $qb->andWhere($qb->expr()->orX(
            'ina.disabilityAccommodations IS NOT NULL AND ina.disabilityAccommodations != :empty',
            'ina.specificConstraints IS NOT NULL AND ina.specificConstraints != :empty',
        ))
            ->setParameter('empty', '')
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get completion statistics by month.
     */
    public function getCompletionStatsByMonth(int $months = 12): array
    {
        $since = new DateTimeImmutable("-{$months} months");

        return $this->createQueryBuilder('ina')
            ->select('YEAR(ina.submittedAt) as year, MONTH(ina.submittedAt) as month, COUNT(ina.id) as count')
            ->andWhere('ina.submittedAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('year, month')
            ->orderBy('year, month')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find analyses by experience level.
     */
    public function findByExperienceLevel(string $experienceLevel): array
    {
        return $this->createQueryBuilder('ina')
            ->andWhere('ina.professionalExperience = :experienceLevel')
            ->setParameter('experienceLevel', $experienceLevel)
            ->orderBy('ina.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count analyses by funding type and professional status.
     */
    public function countByFundingAndStatus(): array
    {
        return $this->createQueryBuilder('ina')
            ->select('ina.fundingType, ina.professionalStatus, COUNT(ina.id) as count')
            ->groupBy('ina.fundingType, ina.professionalStatus')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
