<?php

declare(strict_types=1);

namespace App\Repository\Assessment;

use App\Entity\Assessment\Questionnaire;
use App\Entity\Assessment\QuestionnaireResponse;
use App\Entity\Training\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestionnaireResponse>
 */
class QuestionnaireResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionnaireResponse::class);
    }

    /**
     * Find response by token.
     */
    public function findByToken(string $token): ?QuestionnaireResponse
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.questionnaire', 'q')
            ->leftJoin('r.questionResponses', 'qr')
            ->leftJoin('qr.question', 'quest')
            ->addSelect('q', 'qr', 'quest')
            ->where('r.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find responses by questionnaire.
     */
    public function findByQuestionnaire(Questionnaire $questionnaire): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.questionnaire = :questionnaire')
            ->setParameter('questionnaire', $questionnaire)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find completed responses by questionnaire.
     */
    public function findCompletedByQuestionnaire(Questionnaire $questionnaire): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.questionnaire = :questionnaire')
            ->andWhere('r.status = :status')
            ->setParameter('questionnaire', $questionnaire)
            ->setParameter('status', QuestionnaireResponse::STATUS_COMPLETED)
            ->orderBy('r.completedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find responses by formation.
     */
    public function findByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find responses pending evaluation.
     */
    public function findPendingEvaluation(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.status = :completed')
            ->andWhere('r.evaluationStatus = :pending')
            ->setParameter('completed', QuestionnaireResponse::STATUS_COMPLETED)
            ->setParameter('pending', QuestionnaireResponse::EVALUATION_STATUS_PENDING)
            ->orderBy('r.completedAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find responses by email.
     */
    public function findByEmail(string $email): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.email = :email')
            ->setParameter('email', $email)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find responses with statistics.
     */
    public function findWithStatistics(Questionnaire $questionnaire): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.questionResponses', 'qr')
            ->addSelect('COUNT(qr.id) as answerCount')
            ->where('r.questionnaire = :questionnaire')
            ->setParameter('questionnaire', $questionnaire)
            ->groupBy('r.id')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get completion statistics for a questionnaire.
     */
    public function getCompletionStatistics(Questionnaire $questionnaire): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.status, COUNT(r.id) as count')
            ->where('r.questionnaire = :questionnaire')
            ->setParameter('questionnaire', $questionnaire)
            ->groupBy('r.status')
        ;

        $result = $qb->getQuery()->getResult();

        $stats = [
            'total' => 0,
            'started' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'abandoned' => 0,
        ];

        foreach ($result as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get evaluation statistics for a questionnaire.
     */
    public function getEvaluationStatistics(Questionnaire $questionnaire): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.evaluationStatus, COUNT(r.id) as count')
            ->where('r.questionnaire = :questionnaire')
            ->andWhere('r.status = :completed')
            ->setParameter('questionnaire', $questionnaire)
            ->setParameter('completed', QuestionnaireResponse::STATUS_COMPLETED)
            ->groupBy('r.evaluationStatus')
        ;

        $result = $qb->getQuery()->getResult();

        $stats = [
            'total' => 0,
            'pending' => 0,
            'in_review' => 0,
            'completed' => 0,
        ];

        foreach ($result as $row) {
            $stats[$row['evaluationStatus']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get average completion time for a questionnaire.
     */
    public function getAverageCompletionTime(Questionnaire $questionnaire): ?float
    {
        $qb = $this->createQueryBuilder('r')
            ->select('AVG(r.durationMinutes)')
            ->where('r.questionnaire = :questionnaire')
            ->andWhere('r.status = :completed')
            ->andWhere('r.durationMinutes IS NOT NULL')
            ->setParameter('questionnaire', $questionnaire)
            ->setParameter('completed', QuestionnaireResponse::STATUS_COMPLETED)
        ;

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get score distribution for a questionnaire.
     */
    public function getScoreDistribution(Questionnaire $questionnaire): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.scorePercentage')
            ->where('r.questionnaire = :questionnaire')
            ->andWhere('r.status = :completed')
            ->andWhere('r.scorePercentage IS NOT NULL')
            ->setParameter('questionnaire', $questionnaire)
            ->setParameter('completed', QuestionnaireResponse::STATUS_COMPLETED)
            ->orderBy('r.scorePercentage', 'ASC')
        ;

        $scores = $qb->getQuery()->getSingleColumnResult();
        $totalScores = count($scores);

        $distribution = [
            '0-20%' => ['count' => 0, 'percentage' => 0],
            '21-40%' => ['count' => 0, 'percentage' => 0],
            '41-60%' => ['count' => 0, 'percentage' => 0],
            '61-80%' => ['count' => 0, 'percentage' => 0],
            '81-100%' => ['count' => 0, 'percentage' => 0],
        ];

        foreach ($scores as $score) {
            $percentage = (float) $score;
            if ($percentage <= 20) {
                $distribution['0-20%']['count']++;
            } elseif ($percentage <= 40) {
                $distribution['21-40%']['count']++;
            } elseif ($percentage <= 60) {
                $distribution['41-60%']['count']++;
            } elseif ($percentage <= 80) {
                $distribution['61-80%']['count']++;
            } else {
                $distribution['81-100%']['count']++;
            }
        }

        // Calculate percentages
        if ($totalScores > 0) {
            foreach ($distribution as $range => &$data) {
                $data['percentage'] = ($data['count'] / $totalScores) * 100;
            }
        }

        return $distribution;
    }
}
