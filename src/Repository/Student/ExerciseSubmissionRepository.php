<?php

declare(strict_types=1);

namespace App\Repository\Student;

use App\Entity\Student\ExerciseSubmission;
use App\Entity\Training\Exercise;
use App\Entity\User\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExerciseSubmission>
 */
class ExerciseSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciseSubmission::class);
    }

    /**
     * Find submissions by student.
     *
     * @return ExerciseSubmission[]
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('es')
            ->andWhere('es.student = :student')
            ->setParameter('student', $student)
            ->orderBy('es.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find submission by student and exercise.
     */
    public function findByStudentAndExercise(Student $student, Exercise $exercise): ?ExerciseSubmission
    {
        return $this->createQueryBuilder('es')
            ->andWhere('es.student = :student')
            ->andWhere('es.exercise = :exercise')
            ->setParameter('student', $student)
            ->setParameter('exercise', $exercise)
            ->orderBy('es.attemptNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all submissions for a specific exercise and student.
     *
     * @return ExerciseSubmission[]
     */
    public function findAllByStudentAndExercise(Student $student, Exercise $exercise): array
    {
        return $this->createQueryBuilder('es')
            ->andWhere('es.student = :student')
            ->andWhere('es.exercise = :exercise')
            ->setParameter('student', $student)
            ->setParameter('exercise', $exercise)
            ->orderBy('es.attemptNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next attempt number for a student and exercise.
     */
    public function getNextAttemptNumber(Student $student, Exercise $exercise): int
    {
        $lastSubmission = $this->createQueryBuilder('es')
            ->select('es.attemptNumber')
            ->andWhere('es.student = :student')
            ->andWhere('es.exercise = :exercise')
            ->setParameter('student', $student)
            ->setParameter('exercise', $exercise)
            ->orderBy('es.attemptNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $lastSubmission ? $lastSubmission['attemptNumber'] + 1 : 1;
    }

    /**
     * Find submissions by status.
     *
     * @return ExerciseSubmission[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('es')
            ->andWhere('es.status = :status')
            ->setParameter('status', $status)
            ->orderBy('es.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find submissions that need grading.
     *
     * @return ExerciseSubmission[]
     */
    public function findSubmissionsToGrade(): array
    {
        return $this->findByStatus(ExerciseSubmission::STATUS_SUBMITTED);
    }

    /**
     * Get student's best score for an exercise.
     */
    public function getBestScore(Student $student, Exercise $exercise): ?int
    {
        $result = $this->createQueryBuilder('es')
            ->select('MAX(es.score) as bestScore')
            ->andWhere('es.student = :student')
            ->andWhere('es.exercise = :exercise')
            ->andWhere('es.status = :status')
            ->setParameter('student', $student)
            ->setParameter('exercise', $exercise)
            ->setParameter('status', ExerciseSubmission::STATUS_GRADED)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['bestScore'] ?? null;
    }

    /**
     * Check if student has passed an exercise.
     */
    public function hasStudentPassed(Student $student, Exercise $exercise): bool
    {
        $passedSubmission = $this->createQueryBuilder('es')
            ->andWhere('es.student = :student')
            ->andWhere('es.exercise = :exercise')
            ->andWhere('es.passed = :passed')
            ->setParameter('student', $student)
            ->setParameter('exercise', $exercise)
            ->setParameter('passed', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $passedSubmission !== null;
    }

    /**
     * Get submission statistics for an exercise.
     */
    public function getExerciseStatistics(Exercise $exercise): array
    {
        $qb = $this->createQueryBuilder('es')
            ->select([
                'COUNT(DISTINCT es.student) as totalStudents',
                'COUNT(es) as totalSubmissions',
                'AVG(es.score) as averageScore',
                'MAX(es.score) as maxScore',
                'MIN(es.score) as minScore',
                'SUM(CASE WHEN es.passed = true THEN 1 ELSE 0 END) as passedCount'
            ])
            ->andWhere('es.exercise = :exercise')
            ->andWhere('es.status = :status')
            ->setParameter('exercise', $exercise)
            ->setParameter('status', ExerciseSubmission::STATUS_GRADED);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function save(ExerciseSubmission $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExerciseSubmission $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
