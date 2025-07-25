<?php

namespace App\Repository;

use App\Entity\User\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * TeacherRepository
 * 
 * Repository for Teacher entity with security integration.
 * Provides methods for teacher authentication and user management.
 * 
 * @extends ServiceEntityRepository<Teacher>
 * @implements PasswordUpgraderInterface<Teacher>
 */
class TeacherRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Teacher::class);
    }

    /**
     * Used to upgrade (rehash) the teacher's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $teacher, string $newHashedPassword): void
    {
        if (!$teacher instanceof Teacher) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $teacher::class));
        }

        $teacher->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($teacher);
        $this->getEntityManager()->flush();
    }

    /**
     * Find teacher by email
     */
    public function findByEmail(string $email): ?Teacher
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find teacher by email verification token
     */
    public function findByEmailVerificationToken(string $token): ?Teacher
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.emailVerificationToken = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find teacher by password reset token
     */
    public function findByPasswordResetToken(string $token): ?Teacher
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.passwordResetToken = :token')
            ->andWhere('t.passwordResetTokenExpiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active teachers
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.lastName', 'ASC')
            ->addOrderBy('t.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find teachers with verified emails
     */
    public function findVerified(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.emailVerified = :verified')
            ->setParameter('verified', true)
            ->orderBy('t.lastName', 'ASC')
            ->addOrderBy('t.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find teachers by specialty
     */
    public function findBySpecialty(string $specialty): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.specialty = :specialty')
            ->setParameter('specialty', $specialty)
            ->orderBy('t.lastName', 'ASC')
            ->addOrderBy('t.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find teachers with minimum years of experience
     */
    public function findByMinimumExperience(int $years): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.yearsOfExperience >= :years')
            ->setParameter('years', $years)
            ->orderBy('t.yearsOfExperience', 'DESC')
            ->addOrderBy('t.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search teachers by name, email, or specialty
     */
    public function searchByNameOrSpecialty(string $query): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.firstName LIKE :query OR t.lastName LIKE :query OR t.email LIKE :query OR t.specialty LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.lastName', 'ASC')
            ->addOrderBy('t.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total teachers
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active teachers
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count verified teachers
     */
    public function countVerified(): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.emailVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
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
        ];
    }

    /**
     * Get distinct specialties for filter dropdown
     */
    public function getDistinctSpecialties(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('DISTINCT t.specialty')
            ->andWhere('t.specialty IS NOT NULL')
            ->andWhere('t.specialty != :empty')
            ->setParameter('empty', '')
            ->orderBy('t.specialty', 'ASC')
            ->getQuery()
            ->getArrayResult();
            
        return $result ?: [];
    }

    /**
     * Find teachers with advanced filters
     */
    public function findWithFilters(array $filters, ?int $page = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('t');

        // Apply filters
        if (!empty($filters['search']) && $filters['search'] !== '') {
            $qb->andWhere('t.firstName LIKE :search OR t.lastName LIKE :search OR t.email LIKE :search OR t.specialty LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $qb->andWhere('t.isActive = :active')
                   ->setParameter('active', true);
            } elseif ($filters['status'] === 'inactive') {
                $qb->andWhere('t.isActive = :active')
                   ->setParameter('active', false);
            }
        }

        if (!empty($filters['email_verified']) && $filters['email_verified'] !== '') {
            if ($filters['email_verified'] === 'verified') {
                $qb->andWhere('t.emailVerified = :verified')
                   ->setParameter('verified', true);
            } elseif ($filters['email_verified'] === 'unverified') {
                $qb->andWhere('t.emailVerified = :verified')
                   ->setParameter('verified', false);
            }
        }

        if (!empty($filters['specialty']) && $filters['specialty'] !== '') {
            $qb->andWhere('t.specialty = :specialty')
               ->setParameter('specialty', $filters['specialty']);
        }

        if (!empty($filters['min_experience']) && $filters['min_experience'] !== '') {
            $qb->andWhere('t.yearsOfExperience >= :minExp')
               ->setParameter('minExp', (int) $filters['min_experience']);
        }

        // Default ordering
        $qb->orderBy('t.createdAt', 'DESC');

        // Apply pagination if provided
        if ($page !== null && $limit !== null) {
            $offset = ($page - 1) * $limit;
            $qb->setFirstResult($offset)
               ->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count teachers with filters
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('t')
                   ->select('COUNT(t.id)');

        // Apply same filters as findWithFilters but without pagination
        if (!empty($filters['search']) && $filters['search'] !== '') {
            $qb->andWhere('t.firstName LIKE :search OR t.lastName LIKE :search OR t.email LIKE :search OR t.specialty LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'active') {
                $qb->andWhere('t.isActive = :active')
                   ->setParameter('active', true);
            } elseif ($filters['status'] === 'inactive') {
                $qb->andWhere('t.isActive = :active')
                   ->setParameter('active', false);
            }
        }

        if (!empty($filters['email_verified']) && $filters['email_verified'] !== '') {
            if ($filters['email_verified'] === 'verified') {
                $qb->andWhere('t.emailVerified = :verified')
                   ->setParameter('verified', true);
            } elseif ($filters['email_verified'] === 'unverified') {
                $qb->andWhere('t.emailVerified = :verified')
                   ->setParameter('verified', false);
            }
        }

        if (!empty($filters['specialty']) && $filters['specialty'] !== '') {
            $qb->andWhere('t.specialty = :specialty')
               ->setParameter('specialty', $filters['specialty']);
        }

        if (!empty($filters['min_experience']) && $filters['min_experience'] !== '') {
            $qb->andWhere('t.yearsOfExperience >= :minExp')
               ->setParameter('minExp', (int) $filters['min_experience']);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    //    /**
    //     * @return Teacher[] Returns an array of Teacher objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Teacher
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
