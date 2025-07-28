<?php

namespace App\Repository;

use App\Entity\Assessment\Question;
use App\Entity\Assessment\Questionnaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /**
     * Find active questions for a questionnaire
     */
    public function findActiveByQuestionnaire(Questionnaire $questionnaire): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.questionnaire = :questionnaire')
            ->andWhere('q.isActive = :active')
            ->setParameter('questionnaire', $questionnaire)
            ->setParameter('active', true)
            ->orderBy('q.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions with options for a questionnaire
     */
    public function findByQuestionnaireWithOptions(Questionnaire $questionnaire): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.options', 'o')
            ->addSelect('o')
            ->where('q.questionnaire = :questionnaire')
            ->andWhere('q.isActive = :active')
            ->setParameter('questionnaire', $questionnaire)
            ->setParameter('active', true)
            ->orderBy('q.orderIndex', 'ASC')
            ->addOrderBy('o.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.type = :type')
            ->andWhere('q.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('q.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next order index for a questionnaire
     */
    public function getNextOrderIndex(Questionnaire $questionnaire): int
    {
        $result = $this->createQueryBuilder('q')
            ->select('MAX(q.orderIndex)')
            ->where('q.questionnaire = :questionnaire')
            ->setParameter('questionnaire', $questionnaire)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Find questions with statistics
     */
    public function findWithStatistics(Questionnaire $questionnaire): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.responses', 'r')
            ->addSelect('COUNT(r.id) as responseCount')
            ->where('q.questionnaire = :questionnaire')
            ->setParameter('questionnaire', $questionnaire)
            ->groupBy('q.id')
            ->orderBy('q.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Reorder questions in a questionnaire
     */
    public function reorderQuestions(Questionnaire $questionnaire, array $questionIds): void
    {
        $em = $this->getEntityManager();
        
        foreach ($questionIds as $index => $questionId) {
            $question = $this->find($questionId);
            if ($question && $question->getQuestionnaire() === $questionnaire) {
                $question->setOrderIndex($index + 1);
                $em->persist($question);
            }
        }
        
        $em->flush();
    }
}
