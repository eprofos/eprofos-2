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
        // Get total count
        $totalCount = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get issued count
        $issuedCount = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', Certificate::STATUS_ISSUED)
            ->getQuery()
            ->getSingleScalarResult();

        // Get revoked count
        $revokedCount = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', Certificate::STATUS_REVOKED)
            ->getQuery()
            ->getSingleScalarResult();

        // Get reissued count
        $reissuedCount = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', Certificate::STATUS_REISSUED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_certificates' => (int) $totalCount,
            'issued_count' => (int) $issuedCount,
            'revoked_count' => (int) $revokedCount,
            'reissued_count' => (int) $reissuedCount,
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

        // Use native SQL query since DQL doesn't support YEAR/MONTH functions
        $sql = "
            SELECT 
                EXTRACT(YEAR FROM issued_at) as year,
                EXTRACT(MONTH FROM issued_at) as month,
                COUNT(id) as count
            FROM student_certificates 
            WHERE issued_at >= :startDate 
            AND status != :revoked
            GROUP BY EXTRACT(YEAR FROM issued_at), EXTRACT(MONTH FROM issued_at)
            ORDER BY year ASC, month ASC
        ";

        $connection = $this->getEntityManager()->getConnection();
        $stmt = $connection->prepare($sql);
        $result = $stmt->executeQuery([
            'startDate' => $startDate->format('Y-m-d H:i:s'),
            'revoked' => Certificate::STATUS_REVOKED
        ])->fetchAllAssociative();

        $trends = [];
        foreach ($result as $row) {
            $monthKey = sprintf('%04d-%02d', (int)$row['year'], (int)$row['month']);
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
        // Use native SQL query since DQL doesn't support CAST function properly
        $sql = "
            SELECT 
                f.id as formation_id,
                f.title as formation_title,
                AVG(CAST(c.final_score AS DECIMAL(5,2))) as average_score,
                COUNT(c.id) as certificate_count
            FROM student_certificates c
            LEFT JOIN formation f ON c.formation_id = f.id
            WHERE c.status != :revoked 
            AND c.final_score IS NOT NULL
            GROUP BY f.id, f.title
            HAVING COUNT(c.id) > 0
            ORDER BY average_score DESC
        ";

        $connection = $this->getEntityManager()->getConnection();
        $stmt = $connection->prepare($sql);
        $result = $stmt->executeQuery([
            'revoked' => Certificate::STATUS_REVOKED
        ])->fetchAllAssociative();

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
