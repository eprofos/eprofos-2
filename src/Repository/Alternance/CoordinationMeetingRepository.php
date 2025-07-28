<?php

namespace App\Repository\Alternance;

use App\Entity\Alternance\CoordinationMeeting;
use App\Entity\User\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CoordinationMeeting>
 */
class CoordinationMeetingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoordinationMeeting::class);
    }

    /**
     * Find upcoming meetings by student
     */
    public function findUpcomingByStudent(Student $student): array
    {
        return $this->createQueryBuilder('cm')
            ->andWhere('cm.student = :student')
            ->andWhere('cm.status = :status')
            ->andWhere('cm.date > :now')
            ->setParameter('student', $student)
            ->setParameter('status', CoordinationMeeting::STATUS_PLANNED)
            ->setParameter('now', new \DateTime())
            ->orderBy('cm.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find meetings by period and type
     */
    public function findByPeriodAndType(\DateTimeInterface $startDate, \DateTimeInterface $endDate, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->andWhere('cm.date >= :startDate')
            ->andWhere('cm.date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if ($type) {
            $qb->andWhere('cm.type = :type')
               ->setParameter('type', $type);
        }

        return $qb->orderBy('cm.date', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find missed meetings (planned meetings in the past without completion)
     */
    public function findMissedMeetings(): array
    {
        return $this->createQueryBuilder('cm')
            ->andWhere('cm.status = :status')
            ->andWhere('cm.date < :now')
            ->setParameter('status', CoordinationMeeting::STATUS_PLANNED)
            ->setParameter('now', new \DateTime())
            ->orderBy('cm.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find meetings requiring follow-up
     */
    public function findRequiringFollowUp(): array
    {
        return $this->createQueryBuilder('cm')
            ->andWhere('cm.status = :status')
            ->andWhere('cm.nextMeetingDate IS NOT NULL OR SIZE(cm.actionPlan) > 0')
            ->setParameter('status', CoordinationMeeting::STATUS_COMPLETED)
            ->orderBy('cm.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get coordination statistics for a period
     */
    public function getCoordinationStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->select('
                COUNT(cm.id) as total_meetings,
                COUNT(CASE WHEN cm.status = :completed THEN 1 END) as completed_meetings,
                COUNT(CASE WHEN cm.status = :cancelled THEN 1 END) as cancelled_meetings,
                COUNT(CASE WHEN cm.status = :planned AND cm.date < :now THEN 1 END) as missed_meetings,
                AVG(cm.satisfactionRating) as avg_satisfaction,
                AVG(cm.duration) as avg_duration
            ')
            ->andWhere('cm.date >= :startDate')
            ->andWhere('cm.date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('completed', CoordinationMeeting::STATUS_COMPLETED)
            ->setParameter('cancelled', CoordinationMeeting::STATUS_CANCELLED)
            ->setParameter('planned', CoordinationMeeting::STATUS_PLANNED)
            ->setParameter('now', new \DateTime());

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total_meetings' => (int) $result['total_meetings'],
            'completed_meetings' => (int) $result['completed_meetings'],
            'cancelled_meetings' => (int) $result['cancelled_meetings'],
            'missed_meetings' => (int) $result['missed_meetings'],
            'completion_rate' => $result['total_meetings'] > 0 ? 
                round(($result['completed_meetings'] / $result['total_meetings']) * 100, 2) : 0,
            'avg_satisfaction' => $result['avg_satisfaction'] ? round($result['avg_satisfaction'], 2) : null,
            'avg_duration' => $result['avg_duration'] ? round($result['avg_duration']) : null
        ];
    }

    /**
     * Find meetings by mentor
     */
    public function findByMentor($mentor, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->andWhere('cm.mentor = :mentor')
            ->setParameter('mentor', $mentor);

        if ($status) {
            $qb->andWhere('cm.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->orderBy('cm.date', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find meetings by pedagogical supervisor
     */
    public function findByPedagogicalSupervisor($teacher, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('cm')
            ->andWhere('cm.pedagogicalSupervisor = :teacher')
            ->setParameter('teacher', $teacher);

        if ($status) {
            $qb->andWhere('cm.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->orderBy('cm.date', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find recent meetings for dashboard
     */
    public function findRecentMeetings(int $limit = 10): array
    {
        return $this->createQueryBuilder('cm')
            ->orderBy('cm.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count meetings by status
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('cm')
            ->select('cm.status, COUNT(cm.id) as count')
            ->groupBy('cm.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Find meetings needing scheduling (students without recent meetings)
     */
    public function findStudentsNeedingMeetings(int $daysSinceLastMeeting = 30): array
    {
        $cutoffDate = new \DateTime('-' . $daysSinceLastMeeting . ' days');

        return $this->createQueryBuilder('cm')
            ->select('DISTINCT s.id as student_id, s.firstName, s.lastName, MAX(cm.date) as last_meeting_date')
            ->join('cm.student', 's')
            ->andWhere('cm.status = :completed')
            ->andWhere('cm.date < :cutoffDate')
            ->setParameter('completed', CoordinationMeeting::STATUS_COMPLETED)
            ->setParameter('cutoffDate', $cutoffDate)
            ->groupBy('s.id, s.firstName, s.lastName')
            ->orderBy('last_meeting_date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
