<?php

declare(strict_types=1);

namespace App\Repository\Student;

use App\Entity\Student\Certificate;
use App\Entity\Training\Formation;
use App\Entity\User\Student;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * CertificateRepository for managing certificate data and queries.
 *
 * Provides methods for finding certificates, managing certificate verification,
 * and supporting certificate analytics and reporting.
 *
 * @extends ServiceEntityRepository<Certificate>
 */
class CertificateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certificate::class);
    }

    /**
     * Find certificates for a student.
     *
     * @return Certificate[]
     */
    public function findCertificatesByStudent(Student $student): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.formation', 'f')
            ->where('c.student = :student')
            ->setParameter('student', $student)
            ->orderBy('c.issuedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find certificates for a formation.
     *
     * @return Certificate[]
     */
    public function findCertificatesByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.student', 's')
            ->where('c.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('c.issuedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find certificate by verification code.
     */
    public function findByVerificationCode(string $verificationCode): ?Certificate
    {
        return $this->findOneBy(['verificationCode' => $verificationCode]);
    }

    /**
     * Find certificate by certificate number.
     */
    public function findByCertificateNumber(string $certificateNumber): ?Certificate
    {
        return $this->findOneBy(['certificateNumber' => $certificateNumber]);
    }

    /**
     * Check if student already has a certificate for formation.
     */
    public function hasStudentCertificateForFormation(Student $student, Formation $formation): bool
    {
        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.student = :student')
            ->andWhere('c.formation = :formation')
            ->andWhere('c.status != :revokedStatus')
            ->setParameter('student', $student)
            ->setParameter('formation', $formation)
            ->setParameter('revokedStatus', Certificate::STATUS_REVOKED)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }

    /**
     * Find existing certificate for student and formation.
     */
    public function findStudentCertificateForFormation(Student $student, Formation $formation): ?Certificate
    {
        return $this->createQueryBuilder('c')
            ->where('c.student = :student')
            ->andWhere('c.formation = :formation')
            ->setParameter('student', $student)
            ->setParameter('formation', $formation)
            ->orderBy('c.issuedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Create query builder for certificate management.
     */
    public function createCertificateQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.student', 's')
            ->leftJoin('c.formation', 'f')
            ->leftJoin('c.enrollment', 'e')
            ->addSelect('s', 'f', 'e')
        ;
    }

    /**
     * Find certificates by status.
     *
     * @return Certificate[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.student', 's')
            ->leftJoin('c.formation', 'f')
            ->where('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.issuedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find recently issued certificates.
     *
     * @return Certificate[]
     */
    public function findRecentCertificates(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.student', 's')
            ->leftJoin('c.formation', 'f')
            ->where('c.status = :status')
            ->setParameter('status', Certificate::STATUS_ISSUED)
            ->orderBy('c.issuedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find certificates issued in date range.
     *
     * @return Certificate[]
     */
    public function findCertificatesInDateRange(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.student', 's')
            ->leftJoin('c.formation', 'f')
            ->where('c.issuedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('c.issuedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get certificate statistics.
     */
    public function getCertificateStats(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as total_certificates',
                'COUNT(CASE WHEN c.status = :issued THEN 1 END) as issued_count',
                'COUNT(CASE WHEN c.status = :revoked THEN 1 END) as revoked_count',
                'COUNT(CASE WHEN c.status = :reissued THEN 1 END) as reissued_count',
            ])
            ->setParameter('issued', Certificate::STATUS_ISSUED)
            ->setParameter('revoked', Certificate::STATUS_REVOKED)
            ->setParameter('reissued', Certificate::STATUS_REISSUED)
            ->getQuery()
            ->getSingleResult()
        ;

        return [
            'total_certificates' => (int) $result['total_certificates'],
            'issued_count' => (int) $result['issued_count'],
            'revoked_count' => (int) $result['revoked_count'],
            'reissued_count' => (int) $result['reissued_count'],
        ];
    }

    /**
     * Get certificates by grade distribution.
     */
    public function getGradeDistribution(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select([
                'c.grade',
                'COUNT(c.id) as count'
            ])
            ->where('c.status != :revoked')
            ->setParameter('revoked', Certificate::STATUS_REVOKED)
            ->groupBy('c.grade')
            ->getQuery()
            ->getResult()
        ;

        $distribution = [];
        foreach ($result as $row) {
            $distribution[$row['grade']] = (int) $row['count'];
        }

        return $distribution;
    }

    /**
     * Get monthly certificate issuance trends.
     */
    public function getMonthlyCertificateTrends(int $months = 12): array
    {
        $startDate = (new DateTimeImmutable())->modify("-{$months} months");

        $result = $this->createQueryBuilder('c')
            ->select([
                'YEAR(c.issuedAt) as year',
                'MONTH(c.issuedAt) as month',
                'COUNT(c.id) as count'
            ])
            ->where('c.issuedAt >= :startDate')
            ->andWhere('c.status != :revoked')
            ->setParameter('startDate', $startDate)
            ->setParameter('revoked', Certificate::STATUS_REVOKED)
            ->groupBy('year', 'month')
            ->orderBy('year', 'ASC')
            ->addOrderBy('month', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        $trends = [];
        foreach ($result as $row) {
            $monthKey = sprintf('%04d-%02d', $row['year'], $row['month']);
            $trends[$monthKey] = (int) $row['count'];
        }

        return $trends;
    }

    /**
     * Find certificates that need to be verified or have been recently verified.
     *
     * @return Certificate[]
     */
    public function findCertificatesForVerificationReport(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.student', 's')
            ->leftJoin('c.formation', 'f')
            ->where('c.status = :status')
            ->setParameter('status', Certificate::STATUS_ISSUED)
            ->orderBy('c.issuedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Search certificates by student name, formation title, or certificate number.
     *
     * @return Certificate[]
     */
    public function searchCertificates(string $searchTerm): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.student', 's')
            ->leftJoin('c.formation', 'f')
            ->where('s.firstName LIKE :search OR s.lastName LIKE :search')
            ->orWhere('f.title LIKE :search')
            ->orWhere('c.certificateNumber LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('c.issuedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Count certificates by formation.
     */
    public function countCertificatesByFormation(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select([
                'f.id as formation_id',
                'f.title as formation_title',
                'COUNT(c.id) as certificate_count'
            ])
            ->leftJoin('c.formation', 'f')
            ->where('c.status != :revoked')
            ->setParameter('revoked', Certificate::STATUS_REVOKED)
            ->groupBy('f.id', 'f.title')
            ->orderBy('certificate_count', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        $counts = [];
        foreach ($result as $row) {
            $counts[] = [
                'formation_id' => (int) $row['formation_id'],
                'formation_title' => $row['formation_title'],
                'certificate_count' => (int) $row['certificate_count'],
            ];
        }

        return $counts;
    }

    /**
     * Find certificates that are due for renewal or expiration alerts.
     *
     * @return Certificate[]
     */
    public function findCertificatesForRenewalAlert(DateTimeImmutable $alertDate): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.student', 's')
            ->leftJoin('c.formation', 'f')
            ->where('c.status = :status')
            ->andWhere('c.issuedAt <= :alertDate')
            ->setParameter('status', Certificate::STATUS_ISSUED)
            ->setParameter('alertDate', $alertDate)
            ->orderBy('c.issuedAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get average completion scores by formation.
     */
    public function getAverageScoresByFormation(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select([
                'f.id as formation_id',
                'f.title as formation_title',
                'AVG(CAST(c.finalScore AS DECIMAL)) as average_score',
                'COUNT(c.id) as certificate_count'
            ])
            ->leftJoin('c.formation', 'f')
            ->where('c.status != :revoked')
            ->andWhere('c.finalScore IS NOT NULL')
            ->setParameter('revoked', Certificate::STATUS_REVOKED)
            ->groupBy('f.id', 'f.title')
            ->having('COUNT(c.id) > 0')
            ->orderBy('average_score', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        $scores = [];
        foreach ($result as $row) {
            $scores[] = [
                'formation_id' => (int) $row['formation_id'],
                'formation_title' => $row['formation_title'],
                'average_score' => round((float) $row['average_score'], 2),
                'certificate_count' => (int) $row['certificate_count'],
            ];
        }

        return $scores;
    }
}
