<?php

namespace App\Repository\Assessment;

use App\Entity\Assessment\Questionnaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Questionnaire>
 */
class QuestionnaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Questionnaire::class);
    }

    /**
     * Find active questionnaires
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.status = :status')
            ->setParameter('status', Questionnaire::STATUS_ACTIVE)
            ->orderBy('q.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questionnaires by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.type = :type')
            ->setParameter('type', $type)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questionnaires for a specific formation
     */
    public function findByFormation(int $formationId): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.formation = :formation')
            ->andWhere('q.status = :status')
            ->setParameter('formation', $formationId)
            ->setParameter('status', Questionnaire::STATUS_ACTIVE)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questionnaire by slug with questions
     */
    public function findBySlugWithQuestions(string $slug): ?Questionnaire
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.questions', 'quest')
            ->leftJoin('quest.options', 'opt')
            ->addSelect('quest', 'opt')
            ->where('q.slug = :slug')
            ->andWhere('q.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', Questionnaire::STATUS_ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find questionnaires with statistics
     */
    public function findWithStatistics(): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.responses', 'r')
            ->leftJoin('q.questions', 'quest')
            ->addSelect('COUNT(DISTINCT r.id) as responseCount')
            ->addSelect('COUNT(DISTINCT quest.id) as questionCount')
            ->addSelect('COUNT(CASE WHEN r.status = :completed THEN 1 END) as completedCount')
            ->groupBy('q.id')
            ->setParameter('completed', 'completed')
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search questionnaires by title or description
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.title LIKE :query OR q.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('q.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
