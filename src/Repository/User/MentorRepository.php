<?php

namespace App\Repository\User;

use App\Entity\User\Mentor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * MentorRepository
 * 
 * Repository for Mentor entity with security integration.
 * Provides methods for mentor authentication and management.
 * 
 * @extends ServiceEntityRepository<Mentor>
 * @implements PasswordUpgraderInterface<Mentor>
 */
class MentorRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mentor::class);
    }

    /**
     * Used to upgrade (rehash) the mentor's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $mentor, string $newHashedPassword): void
    {
        if (!$mentor instanceof Mentor) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $mentor::class));
        }

        $mentor->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($mentor);
        $this->getEntityManager()->flush();
    }

    /**
     * Find mentor by email
     */
    public function findByEmail(string $email): ?Mentor
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find mentor by email verification token
     */
    public function findByEmailVerificationToken(string $token): ?Mentor
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.emailVerificationToken = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find mentor by password reset token
     */
    public function findByPasswordResetToken(string $token): ?Mentor
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.passwordResetToken = :token')
            ->andWhere('m.passwordResetTokenExpiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find mentor by company SIRET
     */
    public function findByCompanySiret(string $siret): ?Mentor
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.companySiret = :siret')
            ->setParameter('siret', $siret)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active mentors
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find mentors with verified emails
     */
    public function findVerified(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.emailVerified = :verified')
            ->setParameter('verified', true)
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find mentors by company name
     */
    public function findByCompanyName(string $companyName): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.companyName LIKE :companyName')
            ->setParameter('companyName', '%' . $companyName . '%')
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find mentors by expertise domain
     */
    public function findByExpertiseDomain(string $domain): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('JSON_CONTAINS(m.expertiseDomains, :domain) = 1')
            ->setParameter('domain', json_encode($domain))
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find mentors by experience years (minimum)
     */
    public function findByMinimumExperience(int $minYears): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.experienceYears >= :minYears')
            ->setParameter('minYears', $minYears)
            ->orderBy('m.experienceYears', 'DESC')
            ->addOrderBy('m.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find mentors by education level
     */
    public function findByEducationLevel(string $level): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.educationLevel = :level')
            ->setParameter('level', $level)
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find mentors registered in the last N days
     */
    public function findRecentlyRegistered(int $days = 7): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('m')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total mentors
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active mentors
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count verified mentors
     */
    public function countVerified(): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.emailVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search mentors by name, email, company, or position
     */
    public function searchByNameEmailCompanyOrPosition(string $query): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.firstName LIKE :query OR m.lastName LIKE :query OR m.email LIKE :query OR m.companyName LIKE :query OR m.position LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->countTotal(),
            'active' => $this->countActive(),
            'verified' => $this->countVerified(),
            'recent' => count($this->findRecentlyRegistered()),
        ];
    }

    /**
     * Find mentors with advanced filters
     */
    public function findWithFilters(array $filters, ?int $page = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('m');

        // Apply filters
        if (!empty($filters['search']) && $filters['search'] !== '') {
            $qb->andWhere('m.firstName LIKE :search OR m.lastName LIKE :search OR m.email LIKE :search OR m.companyName LIKE :search OR m.position LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $qb->andWhere('m.isActive = :active')
                   ->setParameter('active', true);
            } elseif ($filters['status'] === 'inactive') {
                $qb->andWhere('m.isActive = :active')
                   ->setParameter('active', false);
            }
        }

        if (!empty($filters['email_verified']) && $filters['email_verified'] !== '') {
            if ($filters['email_verified'] === 'verified') {
                $qb->andWhere('m.emailVerified = :verified')
                   ->setParameter('verified', true);
            } elseif ($filters['email_verified'] === 'unverified') {
                $qb->andWhere('m.emailVerified = :verified')
                   ->setParameter('verified', false);
            }
        }

        if (!empty($filters['company']) && $filters['company'] !== '') {
            $qb->andWhere('m.companyName LIKE :company')
               ->setParameter('company', '%' . $filters['company'] . '%');
        }

        if (!empty($filters['expertise_domain']) && $filters['expertise_domain'] !== '') {
            $qb->andWhere('JSON_CONTAINS(m.expertiseDomains, :domain) = 1')
               ->setParameter('domain', json_encode($filters['expertise_domain']));
        }

        if (!empty($filters['education_level']) && $filters['education_level'] !== '') {
            $qb->andWhere('m.educationLevel = :level')
               ->setParameter('level', $filters['education_level']);
        }

        if (!empty($filters['min_experience']) && is_numeric($filters['min_experience'])) {
            $qb->andWhere('m.experienceYears >= :minExp')
               ->setParameter('minExp', (int)$filters['min_experience']);
        }

        if (!empty($filters['registration_period']) && $filters['registration_period'] !== '') {
            $period = $filters['registration_period'];
            $now = new \DateTimeImmutable();
            
            switch ($period) {
                case 'today':
                    $qb->andWhere('DATE(m.createdAt) = :date')
                       ->setParameter('date', $now->format('Y-m-d'));
                    break;
                case 'week':
                    $weekAgo = $now->modify('-1 week');
                    $qb->andWhere('m.createdAt >= :weekAgo')
                       ->setParameter('weekAgo', $weekAgo);
                    break;
                case 'month':
                    $monthAgo = $now->modify('-1 month');
                    $qb->andWhere('m.createdAt >= :monthAgo')
                       ->setParameter('monthAgo', $monthAgo);
                    break;
                case 'year':
                    $yearAgo = $now->modify('-1 year');
                    $qb->andWhere('m.createdAt >= :yearAgo')
                       ->setParameter('yearAgo', $yearAgo);
                    break;
            }
        }

        // Default ordering
        $qb->orderBy('m.createdAt', 'DESC');

        // Apply pagination if provided
        if ($page !== null && $limit !== null) {
            $offset = ($page - 1) * $limit;
            $qb->setFirstResult($offset)
               ->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count mentors with filters
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('m')
                   ->select('COUNT(m.id)');

        // Apply same filters as findWithFilters but without pagination
        if (!empty($filters['search']) && $filters['search'] !== '') {
            $qb->andWhere('m.firstName LIKE :search OR m.lastName LIKE :search OR m.email LIKE :search OR m.companyName LIKE :search OR m.position LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $qb->andWhere('m.isActive = :active')
                   ->setParameter('active', true);
            } elseif ($filters['status'] === 'inactive') {
                $qb->andWhere('m.isActive = :active')
                   ->setParameter('active', false);
            }
        }

        if (!empty($filters['email_verified']) && $filters['email_verified'] !== '') {
            if ($filters['email_verified'] === 'verified') {
                $qb->andWhere('m.emailVerified = :verified')
                   ->setParameter('verified', true);
            } elseif ($filters['email_verified'] === 'unverified') {
                $qb->andWhere('m.emailVerified = :verified')
                   ->setParameter('verified', false);
            }
        }

        if (!empty($filters['company']) && $filters['company'] !== '') {
            $qb->andWhere('m.companyName LIKE :company')
               ->setParameter('company', '%' . $filters['company'] . '%');
        }

        if (!empty($filters['expertise_domain']) && $filters['expertise_domain'] !== '') {
            $qb->andWhere('JSON_CONTAINS(m.expertiseDomains, :domain) = 1')
               ->setParameter('domain', json_encode($filters['expertise_domain']));
        }

        if (!empty($filters['education_level']) && $filters['education_level'] !== '') {
            $qb->andWhere('m.educationLevel = :level')
               ->setParameter('level', $filters['education_level']);
        }

        if (!empty($filters['min_experience']) && is_numeric($filters['min_experience'])) {
            $qb->andWhere('m.experienceYears >= :minExp')
               ->setParameter('minExp', (int)$filters['min_experience']);
        }

        if (!empty($filters['registration_period']) && $filters['registration_period'] !== '') {
            $period = $filters['registration_period'];
            $now = new \DateTimeImmutable();
            
            switch ($period) {
                case 'today':
                    $qb->andWhere('DATE(m.createdAt) = :date')
                       ->setParameter('date', $now->format('Y-m-d'));
                    break;
                case 'week':
                    $weekAgo = $now->modify('-1 week');
                    $qb->andWhere('m.createdAt >= :weekAgo')
                       ->setParameter('weekAgo', $weekAgo);
                    break;
                case 'month':
                    $monthAgo = $now->modify('-1 month');
                    $qb->andWhere('m.createdAt >= :monthAgo')
                       ->setParameter('monthAgo', $monthAgo);
                    break;
                case 'year':
                    $yearAgo = $now->modify('-1 year');
                    $qb->andWhere('m.createdAt >= :yearAgo')
                       ->setParameter('yearAgo', $yearAgo);
                    break;
            }
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get distinct companies for filter dropdown
     */
    public function getDistinctCompanies(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('DISTINCT m.companyName')
            ->andWhere('m.companyName IS NOT NULL')
            ->andWhere('m.companyName != :empty')
            ->setParameter('empty', '')
            ->orderBy('m.companyName', 'ASC')
            ->getQuery()
            ->getArrayResult();
            
        return $result ?: [];
    }

    /**
     * Get distinct positions for filter dropdown
     */
    public function getDistinctPositions(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('DISTINCT m.position')
            ->andWhere('m.position IS NOT NULL')
            ->andWhere('m.position != :empty')
            ->setParameter('empty', '')
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getArrayResult();
            
        return $result ?: [];
    }

    /**
     * Count mentors with unverified emails
     */
    public function countUnverifiedEmails(): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.emailVerified = :verified')
            ->setParameter('verified', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count inactive mentors
     */
    public function countInactive(): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.isActive = :active')
            ->setParameter('active', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find mentors with expired password reset tokens
     */
    public function findWithExpiredPasswordResetTokens(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.passwordResetToken IS NOT NULL')
            ->andWhere('m.passwordResetTokenExpiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find mentors who haven't logged in for specified days
     */
    public function findInactiveForDays(int $days): array
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('m')
            ->andWhere('m.isActive = :active')
            ->andWhere('m.lastLoginAt < :cutoff OR m.lastLoginAt IS NULL')
            ->setParameter('active', true)
            ->setParameter('cutoff', $cutoffDate)
            ->orderBy('m.lastLoginAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find mentors by multiple expertise domains
     */
    public function findByExpertiseDomains(array $domains): array
    {
        $qb = $this->createQueryBuilder('m');
        
        foreach ($domains as $index => $domain) {
            $qb->andWhere('JSON_CONTAINS(m.expertiseDomains, :domain' . $index . ') = 1')
               ->setParameter('domain' . $index, json_encode($domain));
        }

        return $qb->orderBy('m.lastName', 'ASC')
                  ->addOrderBy('m.firstName', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find mentors available for new apprentices (to be implemented when Alternant entity exists)
     * This is a placeholder method for future use
     */
    public function findAvailableForNewApprentices(int $maxApprenticesPerMentor = 3): array
    {
        // TODO: Implement when Alternant entity is created
        // This should return mentors who have less than maxApprenticesPerMentor active apprentices
        return $this->findActive();
    }

    /**
     * Get experience statistics
     */
    public function getExperienceStatistics(): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('
                AVG(m.experienceYears) as averageExperience,
                MIN(m.experienceYears) as minExperience,
                MAX(m.experienceYears) as maxExperience
            ')
            ->andWhere('m.experienceYears IS NOT NULL');

        $result = $qb->getQuery()->getSingleResult();

        return [
            'average' => round($result['averageExperience'] ?? 0, 1),
            'minimum' => $result['minExperience'] ?? 0,
            'maximum' => $result['maxExperience'] ?? 0,
        ];
    }

    /**
     * Get education level distribution
     */
    public function getEducationLevelDistribution(): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.educationLevel, COUNT(m.id) as count')
            ->andWhere('m.educationLevel IS NOT NULL')
            ->groupBy('m.educationLevel')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get expertise domain distribution
     */
    public function getExpertiseDomainDistribution(): array
    {
        // This requires more complex JSON processing, for now return empty array
        // TODO: Implement when needed with proper JSON aggregation
        return [];
    }

    /**
     * Find paginated mentors with filters
     */
    public function findPaginatedMentors(array $filters, int $page, int $perPage): array
    {
        return $this->findWithFilters($filters, $page, $perPage);
    }

    /**
     * Count filtered mentors
     */
    public function countFilteredMentors(array $filters): int
    {
        return $this->countWithFilters($filters);
    }

    /**
     * Count mentors created this month
     */
    public function countCreatedThisMonth(): int
    {
        $firstDayOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.createdAt >= :firstDay')
            ->setParameter('firstDay', $firstDayOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count unique companies
     */
    public function countUniqueCompanies(): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(DISTINCT m.companyName)')
            ->andWhere('m.companyName IS NOT NULL')
            ->andWhere('m.companyName != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average students per mentor
     */
    public function getAverageStudentsPerMentor(): float
    {
        // Placeholder until student-mentor relationships are implemented
        return 2.3;
    }

    /**
     * Get mentors by company
     */
    public function getMentorsByCompany(): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.companyName, COUNT(m.id) as count')
            ->andWhere('m.companyName IS NOT NULL')
            ->groupBy('m.companyName')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get qualification distribution
     */
    public function getQualificationDistribution(): array
    {
        return $this->getEducationLevelDistribution();
    }

    /**
     * Find mentors for export
     */
    public function findForExport(array $filters): array
    {
        return $this->findWithFilters($filters);
    }
}
