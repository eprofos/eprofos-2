<?php

namespace App\Repository;

use App\Entity\QuestionResponse;
use App\Entity\Question;
use App\Entity\QuestionnaireResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestionResponse>
 */
class QuestionResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionResponse::class);
    }

    /**
     * Find responses by questionnaire response
     */
    public function findByQuestionnaireResponse(QuestionnaireResponse $questionnaireResponse): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.question', 'q')
            ->addSelect('q')
            ->where('r.questionnaireResponse = :questionnaireResponse')
            ->setParameter('questionnaireResponse', $questionnaireResponse)
            ->orderBy('q.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find response by question and questionnaire response
     */
    public function findByQuestionAndResponse(Question $question, QuestionnaireResponse $questionnaireResponse): ?QuestionResponse
    {
        return $this->createQueryBuilder('r')
            ->where('r.question = :question')
            ->andWhere('r.questionnaireResponse = :questionnaireResponse')
            ->setParameter('question', $question)
            ->setParameter('questionnaireResponse', $questionnaireResponse)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find responses by question
     */
    public function findByQuestion(Question $question): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.questionnaireResponse', 'qr')
            ->addSelect('qr')
            ->where('r.question = :question')
            ->setParameter('question', $question)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get response statistics for a question
     */
    public function getQuestionStatistics(Question $question): array
    {
        $stats = [
            'total_responses' => 0,
            'answered' => 0,
            'unanswered' => 0,
            'response_rate' => 0,
            'average_score' => null,
            'success_rate' => null,
            'choice_distribution' => []
        ];

        // Get total responses for this question
        $totalResponses = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.question = :question')
            ->setParameter('question', $question)
            ->getQuery()
            ->getSingleScalarResult();

        $stats['total_responses'] = (int) $totalResponses;

        if ($totalResponses > 0) {
            // Get answered vs unanswered
            $answeredCount = $this->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.question = :question')
                ->andWhere('r.textResponse IS NOT NULL OR r.choiceResponse IS NOT NULL OR r.fileResponse IS NOT NULL OR r.numberResponse IS NOT NULL OR r.dateResponse IS NOT NULL')
                ->setParameter('question', $question)
                ->getQuery()
                ->getSingleScalarResult();

            $stats['answered'] = (int) $answeredCount;
            $stats['unanswered'] = $stats['total_responses'] - $stats['answered'];
            $stats['response_rate'] = ($stats['answered'] / $stats['total_responses']) * 100;

            // Get average score if applicable
            if ($question->getPoints() > 0) {
                $avgScore = $this->getAverageScore($question);
                if ($avgScore !== null) {
                    $stats['average_score'] = ($avgScore / $question->getPoints()) * 100;
                }

                // Calculate success rate for questions with correct answers
                if ($question->hasCorrectAnswers()) {
                    $correctCount = $this->createQueryBuilder('r')
                        ->select('COUNT(r.id)')
                        ->where('r.question = :question')
                        ->andWhere('r.scoreEarned = :maxScore')
                        ->setParameter('question', $question)
                        ->setParameter('maxScore', $question->getPoints())
                        ->getQuery()
                        ->getSingleScalarResult();

                    if ($stats['answered'] > 0) {
                        $stats['success_rate'] = ((int) $correctCount / $stats['answered']) * 100;
                    }
                }
            }

            // For choice questions, get distribution
            if ($question->hasChoices()) {
                $stats['choice_distribution'] = $this->getChoiceDistribution($question);
            }
        }

        return $stats;
    }

    /**
     * Get choice distribution for a choice question
     */
    private function getChoiceDistribution(Question $question): array
    {
        $responses = $this->createQueryBuilder('r')
            ->select('r.choiceResponse')
            ->where('r.question = :question')
            ->andWhere('r.choiceResponse IS NOT NULL')
            ->setParameter('question', $question)
            ->getQuery()
            ->getArrayResult();

        $distribution = [];
        
        // Initialize distribution for all options
        foreach ($question->getOptions() as $option) {
            $distribution[$option->getId()] = [
                'option_text' => $option->getOptionText(),
                'count' => 0,
                'is_correct' => $option->isCorrect()
            ];
        }

        // Count selections
        foreach ($responses as $response) {
            $choices = $response['choiceResponse'];
            if (is_array($choices)) {
                foreach ($choices as $choiceId) {
                    if (isset($distribution[$choiceId])) {
                        $distribution[$choiceId]['count']++;
                    }
                }
            }
        }

        return $distribution;
    }

    /**
     * Get text responses for a text question
     */
    public function getTextResponses(Question $question, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.textResponse, qr.firstName, qr.lastName, r.createdAt')
            ->leftJoin('r.questionnaireResponse', 'qr')
            ->where('r.question = :question')
            ->andWhere('r.textResponse IS NOT NULL')
            ->andWhere('r.textResponse != :empty')
            ->setParameter('question', $question)
            ->setParameter('empty', '')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Get file responses for a file upload question
     */
    public function getFileResponses(Question $question): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.fileResponse, qr.firstName, qr.lastName, r.createdAt')
            ->leftJoin('r.questionnaireResponse', 'qr')
            ->where('r.question = :question')
            ->andWhere('r.fileResponse IS NOT NULL')
            ->andWhere('r.fileResponse != :empty')
            ->setParameter('question', $question)
            ->setParameter('empty', '')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Get average score for a question
     */
    public function getAverageScore(Question $question): ?float
    {
        return $this->createQueryBuilder('r')
            ->select('AVG(r.scoreEarned)')
            ->where('r.question = :question')
            ->andWhere('r.scoreEarned IS NOT NULL')
            ->setParameter('question', $question)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
