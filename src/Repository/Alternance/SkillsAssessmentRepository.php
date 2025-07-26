<?php

namespace App\Repository\Alternance;

use App\Entity\Alternance\SkillsAssessment;
use App\Entity\User\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * SkillsAssessmentRepository
 * 
 * Repository for SkillsAssessment entity providing specialized queries
 * for skills evaluation and progression tracking.
 * 
 * @extends ServiceEntityRepository<SkillsAssessment>
 */
class SkillsAssessmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SkillsAssessment::class);
    }

    /**
     * Find assessments by student and date period
     */
    public function findByStudentAndPeriod(Student $student, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.student = :student')
            ->andWhere('sa.assessmentDate BETWEEN :start AND :end')
            ->setParameter('student', $student)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('sa.assessmentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find cross-evaluations (both center and company assessments) for a student
     */
    public function findCrossEvaluations(Student $student): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.student = :student')
            ->andWhere('sa.context = :context')
            ->setParameter('student', $student)
            ->setParameter('context', 'mixte')
            ->orderBy('sa.assessmentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get skills progression data for a student
     */
    public function getSkillsProgressionData(Student $student): array
    {
        $assessments = $this->createQueryBuilder('sa')
            ->andWhere('sa.student = :student')
            ->setParameter('student', $student)
            ->orderBy('sa.assessmentDate', 'ASC')
            ->getQuery()
            ->getResult();

        $progressionData = [];

        foreach ($assessments as $assessment) {
            $date = $assessment->getAssessmentDate()->format('Y-m-d');
            
            foreach ($assessment->getSkillsEvaluated() as $skillCode => $skillInfo) {
                if (!isset($progressionData[$skillCode])) {
                    $progressionData[$skillCode] = [
                        'name' => $skillInfo['name'] ?? $skillCode,
                        'assessments' => []
                    ];
                }

                $centerScore = $assessment->getCenterScores()[$skillCode]['value'] ?? null;
                $companyScore = $assessment->getCompanyScores()[$skillCode]['value'] ?? null;

                $progressionData[$skillCode]['assessments'][] = [
                    'date' => $date,
                    'center_score' => $centerScore,
                    'company_score' => $companyScore,
                    'overall_rating' => $assessment->getOverallRating(),
                    'assessment_type' => $assessment->getAssessmentType()
                ];
            }
        }

        return $progressionData;
    }

    /**
     * Find assessments by context (centre, entreprise, mixte)
     */
    public function findByContext(string $context): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.context = :context')
            ->setParameter('context', $context)
            ->orderBy('sa.assessmentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find assessments by assessment type
     */
    public function findByAssessmentType(string $assessmentType): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.assessmentType = :type')
            ->setParameter('type', $assessmentType)
            ->orderBy('sa.assessmentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest assessment for a student
     */
    public function findLatestByStudent(Student $student): ?SkillsAssessment
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.student = :student')
            ->setParameter('student', $student)
            ->orderBy('sa.assessmentDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find assessments requiring cross-evaluation completion
     */
    public function findPendingCrossEvaluations(): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.context = :context')
            ->andWhere('(SIZE(sa.centerScores) = 0 OR SIZE(sa.companyScores) = 0)')
            ->setParameter('context', 'mixte')
            ->orderBy('sa.assessmentDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get assessment statistics for a period
     */
    public function getAssessmentStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('sa');
        
        $result = $qb
            ->select([
                'COUNT(sa.id) as total_assessments',
                'AVG(CASE WHEN sa.overallRating = \'excellent\' THEN 1 ELSE 0 END) * 100 as excellent_rate',
                'AVG(CASE WHEN sa.overallRating = \'satisfaisant\' THEN 1 ELSE 0 END) * 100 as satisfactory_rate',
                'COUNT(DISTINCT sa.student) as assessed_students'
            ])
            ->andWhere('sa.assessmentDate BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleResult();

        // Get context distribution
        $contextStats = $this->createQueryBuilder('sa')
            ->select('sa.context, COUNT(sa.id) as count')
            ->andWhere('sa.assessmentDate BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('sa.context')
            ->getQuery()
            ->getResult();

        $result['context_distribution'] = array_column($contextStats, 'count', 'context');

        return $result;
    }

    /**
     * Find assessments with significant competency gaps
     */
    public function findAssessmentsWithCompetencyGaps(float $gapThreshold = 2.0): array
    {
        // This query would need to be implemented with custom SQL
        // For now, we'll filter in PHP
        $assessments = $this->createQueryBuilder('sa')
            ->andWhere('sa.context = :context')
            ->setParameter('context', 'mixte')
            ->getQuery()
            ->getResult();

        $assessmentsWithGaps = [];
        
        foreach ($assessments as $assessment) {
            $gaps = $assessment->getCompetencyGaps();
            if (!empty($gaps)) {
                $assessmentsWithGaps[] = $assessment;
            }
        }

        return $assessmentsWithGaps;
    }

    /**
     * Get skills mastery overview for a student
     */
    public function getSkillsMasteryOverview(Student $student): array
    {
        $assessments = $this->findByStudentAndPeriod(
            $student,
            new \DateTime('-1 year'),
            new \DateTime()
        );

        $skillsMastery = [];

        foreach ($assessments as $assessment) {
            foreach ($assessment->getSkillsEvaluated() as $skillCode => $skillInfo) {
                if (!isset($skillsMastery[$skillCode])) {
                    $skillsMastery[$skillCode] = [
                        'name' => $skillInfo['name'] ?? $skillCode,
                        'evaluations' => [],
                        'latest_center_score' => null,
                        'latest_company_score' => null,
                        'progression_trend' => 'stable'
                    ];
                }

                $centerScore = $assessment->getCenterScores()[$skillCode]['value'] ?? null;
                $companyScore = $assessment->getCompanyScores()[$skillCode]['value'] ?? null;

                $skillsMastery[$skillCode]['evaluations'][] = [
                    'date' => $assessment->getAssessmentDate(),
                    'center_score' => $centerScore,
                    'company_score' => $companyScore
                ];

                // Update latest scores
                if ($centerScore !== null) {
                    $skillsMastery[$skillCode]['latest_center_score'] = $centerScore;
                }
                if ($companyScore !== null) {
                    $skillsMastery[$skillCode]['latest_company_score'] = $companyScore;
                }
            }
        }

        // Calculate progression trends
        foreach ($skillsMastery as $skillCode => &$skillData) {
            if (count($skillData['evaluations']) >= 2) {
                $firstEval = reset($skillData['evaluations']);
                $lastEval = end($skillData['evaluations']);
                
                $firstScore = $firstEval['center_score'] ?? $firstEval['company_score'] ?? 0;
                $lastScore = $lastEval['center_score'] ?? $lastEval['company_score'] ?? 0;
                
                if ($lastScore > $firstScore + 1) {
                    $skillData['progression_trend'] = 'improving';
                } elseif ($lastScore < $firstScore - 1) {
                    $skillData['progression_trend'] = 'declining';
                }
            }
        }

        return $skillsMastery;
    }

    /**
     * Find students requiring skills assessment
     */
    public function findStudentsRequiringAssessment(\DateTimeInterface $cutoffDate): array
    {
        $subQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(sa2.student)')
            ->from(SkillsAssessment::class, 'sa2')
            ->andWhere('sa2.assessmentDate > :cutoff');

        return $this->getEntityManager()->createQueryBuilder()
            ->select('s')
            ->from(Student::class, 's')
            ->andWhere('s.id NOT IN (' . $subQuery->getDQL() . ')')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();
    }
}
