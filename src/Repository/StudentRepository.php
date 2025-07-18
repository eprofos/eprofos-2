<?php

namespace App\Repository;

use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * StudentRepository
 * 
 * Repository for Student entity with security integration.
 * Provides methods for student authentication and user management.
 * 
 * @extends ServiceEntityRepository<Student>
 * @implements PasswordUpgraderInterface<Student>
 */
class StudentRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Student::class);
    }

    /**
     * Used to upgrade (rehash) the student's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $student, string $newHashedPassword): void
    {
        if (!$student instanceof Student) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $student::class));
        }

        $student->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($student);
        $this->getEntityManager()->flush();
    }

    /**
     * Find student by email
     */
    public function findByEmail(string $email): ?Student
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find student by email verification token
     */
    public function findByEmailVerificationToken(string $token): ?Student
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.emailVerificationToken = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find student by password reset token
     */
    public function findByPasswordResetToken(string $token): ?Student
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.passwordResetToken = :token')
            ->andWhere('s.passwordResetTokenExpiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active students
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find students with verified emails
     */
    public function findVerified(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.emailVerified = :verified')
            ->setParameter('verified', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find students registered in the last N days
     */
    public function findRecentlyRegistered(int $days = 7): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('s')
            ->andWhere('s.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total students
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active students
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count verified students
     */
    public function countVerified(): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.emailVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search students by name or email
     */
    public function searchByNameOrEmail(string $query): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.firstName LIKE :query OR s.lastName LIKE :query OR s.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find students by city
     */
    public function findByCity(string $city): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.city = :city')
            ->setParameter('city', $city)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find students by profession
     */
    public function findByProfession(string $profession): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.profession = :profession')
            ->setParameter('profession', $profession)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
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
     * Find students with advanced filters
     */
    public function findWithFilters(array $filters, ?int $page = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('s');

        // Apply filters
        if (!empty($filters['search']) && $filters['search'] !== '') {
            $qb->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search OR s.email LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $qb->andWhere('s.isActive = :active')
                   ->setParameter('active', true);
            } elseif ($filters['status'] === 'inactive') {
                $qb->andWhere('s.isActive = :active')
                   ->setParameter('active', false);
            }
        }

        if (!empty($filters['email_verified']) && $filters['email_verified'] !== '') {
            if ($filters['email_verified'] === 'verified') {
                $qb->andWhere('s.emailVerified = :verified')
                   ->setParameter('verified', true);
            } elseif ($filters['email_verified'] === 'unverified') {
                $qb->andWhere('s.emailVerified = :verified')
                   ->setParameter('verified', false);
            }
        }

        if (!empty($filters['city']) && $filters['city'] !== '') {
            $qb->andWhere('s.city = :city')
               ->setParameter('city', $filters['city']);
        }

        if (!empty($filters['profession']) && $filters['profession'] !== '') {
            $qb->andWhere('s.profession = :profession')
               ->setParameter('profession', $filters['profession']);
        }

        if (!empty($filters['registration_period']) && $filters['registration_period'] !== '') {
            $period = $filters['registration_period'];
            $now = new \DateTimeImmutable();
            
            switch ($period) {
                case 'today':
                    $qb->andWhere('DATE(s.createdAt) = :date')
                       ->setParameter('date', $now->format('Y-m-d'));
                    break;
                case 'week':
                    $weekAgo = $now->modify('-1 week');
                    $qb->andWhere('s.createdAt >= :weekAgo')
                       ->setParameter('weekAgo', $weekAgo);
                    break;
                case 'month':
                    $monthAgo = $now->modify('-1 month');
                    $qb->andWhere('s.createdAt >= :monthAgo')
                       ->setParameter('monthAgo', $monthAgo);
                    break;
                case 'year':
                    $yearAgo = $now->modify('-1 year');
                    $qb->andWhere('s.createdAt >= :yearAgo')
                       ->setParameter('yearAgo', $yearAgo);
                    break;
            }
        }

        // Default ordering
        $qb->orderBy('s.createdAt', 'DESC');

        // Apply pagination if provided
        if ($page !== null && $limit !== null) {
            $offset = ($page - 1) * $limit;
            $qb->setFirstResult($offset)
               ->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count students with filters
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('s')
                   ->select('COUNT(s.id)');

        // Apply same filters as findWithFilters but without pagination
        if (!empty($filters['search']) && $filters['search'] !== '') {
            $qb->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search OR s.email LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $qb->andWhere('s.isActive = :active')
                   ->setParameter('active', true);
            } elseif ($filters['status'] === 'inactive') {
                $qb->andWhere('s.isActive = :active')
                   ->setParameter('active', false);
            }
        }

        if (!empty($filters['email_verified']) && $filters['email_verified'] !== '') {
            if ($filters['email_verified'] === 'verified') {
                $qb->andWhere('s.emailVerified = :verified')
                   ->setParameter('verified', true);
            } elseif ($filters['email_verified'] === 'unverified') {
                $qb->andWhere('s.emailVerified = :verified')
                   ->setParameter('verified', false);
            }
        }

        if (!empty($filters['city']) && $filters['city'] !== '') {
            $qb->andWhere('s.city = :city')
               ->setParameter('city', $filters['city']);
        }

        if (!empty($filters['profession']) && $filters['profession'] !== '') {
            $qb->andWhere('s.profession = :profession')
               ->setParameter('profession', $filters['profession']);
        }

        if (!empty($filters['registration_period']) && $filters['registration_period'] !== '') {
            $period = $filters['registration_period'];
            $now = new \DateTimeImmutable();
            
            switch ($period) {
                case 'today':
                    $qb->andWhere('DATE(s.createdAt) = :date')
                       ->setParameter('date', $now->format('Y-m-d'));
                    break;
                case 'week':
                    $weekAgo = $now->modify('-1 week');
                    $qb->andWhere('s.createdAt >= :weekAgo')
                       ->setParameter('weekAgo', $weekAgo);
                    break;
                case 'month':
                    $monthAgo = $now->modify('-1 month');
                    $qb->andWhere('s.createdAt >= :monthAgo')
                       ->setParameter('monthAgo', $monthAgo);
                    break;
                case 'year':
                    $yearAgo = $now->modify('-1 year');
                    $qb->andWhere('s.createdAt >= :yearAgo')
                       ->setParameter('yearAgo', $yearAgo);
                    break;
            }
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get distinct cities for filter dropdown
     */
    public function getDistinctCities(): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('DISTINCT s.city')
            ->andWhere('s.city IS NOT NULL')
            ->andWhere('s.city != :empty')
            ->setParameter('empty', '')
            ->orderBy('s.city', 'ASC')
            ->getQuery()
            ->getArrayResult();
            
        return $result ?: [];
    }

    /**
     * Get distinct professions for filter dropdown
     */
    public function getDistinctProfessions(): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('DISTINCT s.profession')
            ->andWhere('s.profession IS NOT NULL')
            ->andWhere('s.profession != :empty')
            ->setParameter('empty', '')
            ->orderBy('s.profession', 'ASC')
            ->getQuery()
            ->getArrayResult();
            
        return $result ?: [];
    }

    /**
     * Count students with unverified emails
     */
    public function countUnverifiedEmails(): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.emailVerified = :verified')
            ->setParameter('verified', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count inactive students
     */
    public function countInactive(): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find students with expired password reset tokens
     */
    public function findWithExpiredPasswordResetTokens(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.passwordResetToken IS NOT NULL')
            ->andWhere('s.passwordResetTokenExpiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find students who haven't logged in for specified days
     */
    public function findInactiveForDays(int $days): array
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.lastLoginAt < :cutoff OR s.lastLoginAt IS NULL')
            ->setParameter('active', true)
            ->setParameter('cutoff', $cutoffDate)
            ->orderBy('s.lastLoginAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Student[] Returns an array of Student objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Student
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
