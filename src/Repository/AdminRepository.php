<?php

namespace App\Repository;

use App\Entity\User\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * Repository for Admin entity
 * 
 * Provides custom query methods for Admin entity and implements
 * PasswordUpgraderInterface for automatic password rehashing.
 * 
 * @extends ServiceEntityRepository<Admin>
 */
class AdminRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Admin::class);
    }

    /**
     * Used to upgrade (rehash) the admin user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $admin, string $newHashedPassword): void
    {
        if (!$admin instanceof Admin) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $admin::class));
        }

        $admin->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($admin);
        $this->getEntityManager()->flush();
    }

    /**
     * Find active admin users only
     * 
     * @return Admin[]
     */
    public function findActiveAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find admin user by email (case insensitive)
     */
    public function findByEmailIgnoreCase(string $email): ?Admin
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count total active admin users
     */
    public function countActiveAdmins(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find admin users who logged in recently
     * 
     * @return Admin[]
     */
    public function findRecentlyLoggedInAdmins(int $days = 30): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));
        
        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt >= :since')
            ->andWhere('u.isActive = :active')
            ->setParameter('since', $since)
            ->setParameter('active', true)
            ->orderBy('u.lastLoginAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Update last login timestamp for admin user
     */
    public function updateLastLogin(Admin $admin): void
    {
        $admin->updateLastLogin();
        $this->getEntityManager()->persist($admin);
        $this->getEntityManager()->flush();
    }
}