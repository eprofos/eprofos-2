<?php

namespace App\Repository;

use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * Repository for User entity
 * 
 * Provides custom query methods for User entity and implements
 * PasswordUpgraderInterface for automatic password rehashing.
 * 
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find active users only
     * 
     * @return User[]
     */
    public function findActiveUsers(): array
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
     * Find user by email (case insensitive)
     */
    public function findByEmailIgnoreCase(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count total active users
     */
    public function countActiveUsers(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find users who logged in recently
     * 
     * @return User[]
     */
    public function findRecentlyLoggedInUsers(int $days = 30): array
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
     * Update last login timestamp for user
     */
    public function updateLastLogin(User $user): void
    {
        $user->updateLastLogin();
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}