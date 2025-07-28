<?php

namespace App\Repository\Assessment;

use App\Entity\Assessment\QuestionOption;
use App\Entity\Assessment\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestionOption>
 */
class QuestionOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionOption::class);
    }

    /**
     * Find active options for a question
     */
    public function findActiveByQuestion(Question $question): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.question = :question')
            ->andWhere('o.isActive = :active')
            ->setParameter('question', $question)
            ->setParameter('active', true)
            ->orderBy('o.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find correct options for a question
     */
    public function findCorrectByQuestion(Question $question): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.question = :question')
            ->andWhere('o.isCorrect = :correct')
            ->andWhere('o.isActive = :active')
            ->setParameter('question', $question)
            ->setParameter('correct', true)
            ->setParameter('active', true)
            ->orderBy('o.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next order index for a question
     */
    public function getNextOrderIndex(Question $question): int
    {
        $result = $this->createQueryBuilder('o')
            ->select('MAX(o.orderIndex)')
            ->where('o.question = :question')
            ->setParameter('question', $question)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Reorder options for a question
     */
    public function reorderOptions(Question $question, array $optionIds): void
    {
        $em = $this->getEntityManager();
        
        foreach ($optionIds as $index => $optionId) {
            $option = $this->find($optionId);
            if ($option && $option->getQuestion() === $question) {
                $option->setOrderIndex($index + 1);
                $em->persist($option);
            }
        }
        
        $em->flush();
    }
}
