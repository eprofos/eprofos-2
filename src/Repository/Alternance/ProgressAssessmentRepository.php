<?php

namespace App\Repository\Alternance;

use App\Entity\Alternance\ProgressAssessment;
use App\Entity\User\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * ProgressAssessmentRepository
 * 
 * Repository for ProgressAssessment entity providing specialized queries
 * for progress tracking and risk assessment.
 * 
 * @extends ServiceEntityRepository<ProgressAssessment>
 */
class ProgressAssessmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProgressAssessment::class);
    }

    /**
     * Find latest assessment by student
     */
    public function findLatestByStudent(Student $student): ?ProgressAssessment
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.student = :student')
            ->setParameter('student', $student)
            ->orderBy('pa.period', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find students at risk (with risk level >= threshold)
     */
    public function findStudentsAtRisk(int $riskThreshold = 3): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.riskLevel >= :threshold')
            ->setParameter('threshold', $riskThreshold)
            ->orderBy('pa.riskLevel', 'DESC')
            ->addOrderBy('pa.period', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Generate progression report for a period
     */
    public function generateProgressionReport(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('pa');
        
        $result = $qb
            ->select([
                'COUNT(pa.id) as total_assessments',
                'COUNT(DISTINCT pa.student) as assessed_students',
                'AVG(pa.overallProgression) as average_progression',
                'AVG(pa.centerProgression) as average_center_progression',
                'AVG(pa.companyProgression) as average_company_progression',
                'AVG(pa.riskLevel) as average_risk_level'
            ])
            ->andWhere('pa.period BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleResult();

        // Get risk level distribution
        $riskDistribution = $this->createQueryBuilder('pa')
            ->select('pa.riskLevel, COUNT(pa.id) as count')
            ->andWhere('pa.period BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('pa.riskLevel')
            ->orderBy('pa.riskLevel')
            ->getQuery()
            ->getResult();

        $result['risk_distribution'] = array_column($riskDistribution, 'count', 'riskLevel');

        // Get progression status distribution
        $progressionStats = $this->createQueryBuilder('pa')
            ->select([
                'COUNT(CASE WHEN pa.overallProgression >= 90 THEN 1 END) as excellent',
                'COUNT(CASE WHEN pa.overallProgression >= 75 AND pa.overallProgression < 90 THEN 1 END) as satisfactory',
                'COUNT(CASE WHEN pa.overallProgression >= 50 AND pa.overallProgression < 75 THEN 1 END) as average',
                'COUNT(CASE WHEN pa.overallProgression >= 25 AND pa.overallProgression < 50 THEN 1 END) as needs_improvement',
                'COUNT(CASE WHEN pa.overallProgression < 25 THEN 1 END) as critical'
            ])
            ->andWhere('pa.period BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleResult();

        $result['progression_status_distribution'] = $progressionStats;

        return $result;
    }

    /**
     * Find assessments by student and date range
     */
    public function findByStudentAndDateRange(Student $student, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.student = :student')
            ->andWhere('pa.period BETWEEN :start AND :end')
            ->setParameter('student', $student)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('pa.period', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get student progression trend
     */
    public function getStudentProgressionTrend(Student $student, int $monthsBack = 6): array
    {
        $startDate = new \DateTime("-{$monthsBack} months");
        $endDate = new \DateTime();

        $assessments = $this->findByStudentAndDateRange($student, $startDate, $endDate);

        $trend = [
            'periods' => [],
            'center_progression' => [],
            'company_progression' => [],
            'overall_progression' => [],
            'risk_levels' => []
        ];

        foreach ($assessments as $assessment) {
            $period = $assessment->getPeriod()->format('Y-m');
            $trend['periods'][] = $period;
            $trend['center_progression'][] = (float) $assessment->getCenterProgression();
            $trend['company_progression'][] = (float) $assessment->getCompanyProgression();
            $trend['overall_progression'][] = (float) $assessment->getOverallProgression();
            $trend['risk_levels'][] = $assessment->getRiskLevel();
        }

        return $trend;
    }

    /**
     * Find students requiring assessment update
     */
    public function findStudentsRequiringUpdate(\DateTimeInterface $cutoffDate): array
    {
        $subQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(pa2.student)')
            ->from(ProgressAssessment::class, 'pa2')
            ->andWhere('pa2.period > :cutoff');

        return $this->getEntityManager()->createQueryBuilder()
            ->select('s')
            ->from(Student::class, 's')
            ->andWhere('s.id NOT IN (' . $subQuery->getDQL() . ')')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get top performing students
     */
    public function getTopPerformingStudents(int $limit = 10): array
    {
        return $this->createQueryBuilder('pa')
            ->orderBy('pa.overallProgression', 'DESC')
            ->addOrderBy('pa.period', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get students with declining progression
     */
    public function getStudentsWithDecliningProgression(): array
    {
        // This requires a more complex query to compare progression over time
        // For now, we'll use a simplified approach
        $recentDate = new \DateTime('-1 month');
        $olderDate = new \DateTime('-3 months');

        $recentAssessments = $this->createQueryBuilder('pa')
            ->andWhere('pa.period >= :recent')
            ->setParameter('recent', $recentDate)
            ->getQuery()
            ->getResult();

        $olderAssessments = $this->createQueryBuilder('pa')
            ->andWhere('pa.period BETWEEN :older AND :recent')
            ->setParameter('older', $olderDate)
            ->setParameter('recent', $recentDate)
            ->getQuery()
            ->getResult();

        // Group by student and compare
        $decliningStudents = [];
        $recentByStudent = [];
        $olderByStudent = [];

        foreach ($recentAssessments as $assessment) {
            $studentId = $assessment->getStudent()->getId();
            if (!isset($recentByStudent[$studentId]) || 
                $assessment->getPeriod() > $recentByStudent[$studentId]->getPeriod()) {
                $recentByStudent[$studentId] = $assessment;
            }
        }

        foreach ($olderAssessments as $assessment) {
            $studentId = $assessment->getStudent()->getId();
            if (!isset($olderByStudent[$studentId]) || 
                $assessment->getPeriod() > $olderByStudent[$studentId]->getPeriod()) {
                $olderByStudent[$studentId] = $assessment;
            }
        }

        foreach ($recentByStudent as $studentId => $recentAssessment) {
            if (isset($olderByStudent[$studentId])) {
                $olderAssessment = $olderByStudent[$studentId];
                $recentProgression = (float) $recentAssessment->getOverallProgression();
                $olderProgression = (float) $olderAssessment->getOverallProgression();
                
                if ($recentProgression < $olderProgression - 5) { // 5% decline threshold
                    $decliningStudents[] = $recentAssessment;
                }
            }
        }

        return $decliningStudents;
    }

    /**
     * Get average progression by period
     */
    public function getAverageProgressionByPeriod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $result = $this->createQueryBuilder('pa')
            ->select([
                'YEAR(pa.period) as year',
                'MONTH(pa.period) as month',
                'AVG(pa.overallProgression) as avg_progression',
                'AVG(pa.centerProgression) as avg_center_progression',
                'AVG(pa.companyProgression) as avg_company_progression',
                'COUNT(pa.id) as assessment_count'
            ])
            ->andWhere('pa.period BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('year, month')
            ->orderBy('year, month')
            ->getQuery()
            ->getResult();

        // Format the result
        $formattedResult = [];
        foreach ($result as $row) {
            $period = sprintf('%04d-%02d', $row['year'], $row['month']);
            $formattedResult[$period] = [
                'avg_progression' => round((float) $row['avg_progression'], 2),
                'avg_center_progression' => round((float) $row['avg_center_progression'], 2),
                'avg_company_progression' => round((float) $row['avg_company_progression'], 2),
                'assessment_count' => (int) $row['assessment_count']
            ];
        }

        return $formattedResult;
    }

    /**
     * Get detailed risk analysis
     */
    public function getDetailedRiskAnalysis(): array
    {
        $highRiskStudents = $this->findStudentsAtRisk(4);
        $moderateRiskStudents = $this->findStudentsAtRisk(3);
        
        $riskFactors = [];
        
        foreach ($highRiskStudents as $assessment) {
            $factors = $assessment->getRiskFactorsAnalysis();
            foreach ($factors as $factor) {
                $factorName = $factor['factor'];
                if (!isset($riskFactors[$factorName])) {
                    $riskFactors[$factorName] = [
                        'count' => 0,
                        'severity' => $factor['severity']
                    ];
                }
                $riskFactors[$factorName]['count']++;
            }
        }

        return [
            'high_risk_count' => count($highRiskStudents),
            'moderate_risk_count' => count($moderateRiskStudents) - count($highRiskStudents),
            'total_at_risk' => count($moderateRiskStudents),
            'risk_factors' => $riskFactors
        ];
    }

    /**
     * Find assessments by risk level
     */
    public function findByRiskLevel(int $riskLevel): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.riskLevel = :riskLevel')
            ->setParameter('riskLevel', $riskLevel)
            ->orderBy('pa.period', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get skills matrix evolution for a student
     */
    public function getSkillsMatrixEvolution(Student $student): array
    {
        $assessments = $this->createQueryBuilder('pa')
            ->andWhere('pa.student = :student')
            ->setParameter('student', $student)
            ->orderBy('pa.period', 'ASC')
            ->getQuery()
            ->getResult();

        $evolution = [];
        
        foreach ($assessments as $assessment) {
            $period = $assessment->getPeriod()->format('Y-m');
            $skillsMatrix = $assessment->getSkillsMatrix();
            
            foreach ($skillsMatrix as $skillCode => $skillData) {
                if (!isset($evolution[$skillCode])) {
                    $evolution[$skillCode] = [
                        'name' => $skillData['name'],
                        'periods' => [],
                        'levels' => [],
                        'trends' => []
                    ];
                }
                
                $evolution[$skillCode]['periods'][] = $period;
                $evolution[$skillCode]['levels'][] = $skillData['level'];
                $evolution[$skillCode]['trends'][] = $skillData['progression_trend'] ?? 'stable';
            }
        }

        return $evolution;
    }
}
