<?php

namespace App\Controller\Student;

use App\Entity\User\Student;
use App\Entity\Alternance\MissionAssignment;
use App\Entity\Alternance\SkillsAssessment;
use App\Entity\Alternance\CoordinationMeeting;
use App\Service\Alternance\MissionAssignmentService;
use App\Service\Alternance\SkillsAssessmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student Alternance Controller
 * 
 * Handles alternance-specific features for students including
 * mission assignments, skills assessments, and coordination meetings.
 */
#[Route('/student/alternance', name: 'student_alternance_')]
#[IsGranted('ROLE_STUDENT')]
class AlternanceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissionAssignmentService $assignmentService,
        private SkillsAssessmentService $assessmentService
    ) {}

    /**
     * Alternance dashboard for students
     */
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get current assignments
        $assignments = $this->entityManager->getRepository(MissionAssignment::class)
            ->findBy(['student' => $student], ['createdAt' => 'DESC']);

        // Get recent assessments
        $assessments = $this->entityManager->getRepository(SkillsAssessment::class)
            ->findBy(['student' => $student], ['createdAt' => 'DESC'], 5);

        // Get upcoming meetings
        $upcomingMeetings = $this->entityManager->getRepository(CoordinationMeeting::class)
            ->createQueryBuilder('cm')
            ->where('cm.student = :student')
            ->andWhere('cm.date > :now')
            ->setParameter('student', $student)
            ->setParameter('now', new \DateTime())
            ->orderBy('cm.date', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Calculate statistics
        $stats = $this->calculateStudentStats($student, $assignments);

        return $this->render('student/alternance/dashboard.html.twig', [
            'student' => $student,
            'assignments' => $assignments,
            'assessments' => $assessments,
            'upcoming_meetings' => $upcomingMeetings,
            'stats' => $stats,
            'page_title' => 'Mon Alternance'
        ]);
    }

    /**
     * View student's mission assignments
     */
    #[Route('/missions', name: 'missions', methods: ['GET'])]
    public function missions(Request $request): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        $status = $request->query->get('status', 'all');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $qb = $this->entityManager->getRepository(MissionAssignment::class)
            ->createQueryBuilder('ma')
            ->where('ma.student = :student')
            ->setParameter('student', $student)
            ->orderBy('ma.createdAt', 'DESC');

        // Apply status filter
        switch ($status) {
            case 'active':
                $qb->andWhere('ma.status IN (:activeStatuses)')
                   ->setParameter('activeStatuses', ['planifiee', 'en_cours']);
                break;
            case 'completed':
                $qb->andWhere('ma.status = :completedStatus')
                   ->setParameter('completedStatus', 'terminee');
                break;
            case 'overdue':
                $qb->andWhere('ma.endDate < :now')
                   ->andWhere('ma.status != :completedStatus')
                   ->setParameter('now', new \DateTime())
                   ->setParameter('completedStatus', 'terminee');
                break;
        }

        // Create a separate count query without joins
        $countQb = $this->entityManager->getRepository(MissionAssignment::class)
            ->createQueryBuilder('ma')
            ->select('COUNT(ma.id)')
            ->where('ma.student = :student')
            ->setParameter('student', $student);

        // Apply the same filters to count query
        switch ($status) {
            case 'active':
                $countQb->andWhere('ma.status IN (:activeStatuses)')
                        ->setParameter('activeStatuses', ['planifiee', 'en_cours']);
                break;
            case 'completed':
                $countQb->andWhere('ma.status = :completedStatus')
                        ->setParameter('completedStatus', 'terminee');
                break;
            case 'overdue':
                $countQb->andWhere('ma.endDate < :now')
                        ->andWhere('ma.status != :completedStatus')
                        ->setParameter('now', new \DateTime())
                        ->setParameter('completedStatus', 'terminee');
                break;
        }

        $totalCount = $countQb->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalCount / $limit);

        // Add join for the main query to get mission data
        $assignments = $qb
            ->join('ma.mission', 'm')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $filters = ['status' => $status];

        if ($request->isXmlHttpRequest()) {
            return $this->render('student/alternance/_missions_list.html.twig', [
                'assignments' => $assignments,
                'filters' => $filters,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_count' => $totalCount
            ]);
        }

        return $this->render('student/alternance/missions.html.twig', [
            'assignments' => $assignments,
            'filters' => $filters,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'page_title' => 'Mes Missions'
        ]);
    }

    /**
     * View specific mission assignment details
     */
    #[Route('/missions/{id}', name: 'mission_show', methods: ['GET'])]
    public function showMission(MissionAssignment $assignment): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        if ($assignment->getStudent() !== $student) {
            throw $this->createAccessDeniedException('Vous ne pouvez voir que vos propres missions.');
        }

        return $this->render('student/alternance/mission_show.html.twig', [
            'assignment' => $assignment,
            'mission' => $assignment->getMission(),
            'page_title' => $assignment->getMission()->getTitle()
        ]);
    }

    /**
     * Update mission progress (student self-assessment)
     */
    #[Route('/missions/{id}/progress', name: 'mission_update_progress', methods: ['POST'])]
    public function updateMissionProgress(MissionAssignment $assignment, Request $request): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        if ($assignment->getStudent() !== $student) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres missions.');
        }

        if ($assignment->isCompleted()) {
            $this->addFlash('error', 'Cette mission est déjà terminée.');
            return $this->redirectToRoute('student_alternance_mission_show', ['id' => $assignment->getId()]);
        }

        $completionRate = $request->request->getInt('completion_rate');
        $selfAssessment = $request->request->get('self_assessment', '');

        try {
            $this->assignmentService->updateProgress(
                $assignment,
                (float) $completionRate,
                ['selfAssessment' => $selfAssessment]
            );

            $this->addFlash('success', 'Progression mise à jour avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('student_alternance_mission_show', ['id' => $assignment->getId()]);
    }

    /**
     * View skills assessments
     */
    #[Route('/assessments', name: 'assessments', methods: ['GET'])]
    public function assessments(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        $assessments = $this->entityManager->getRepository(SkillsAssessment::class)
            ->findBy(['student' => $student], ['createdAt' => 'DESC']);

        return $this->render('student/alternance/assessments.html.twig', [
            'assessments' => $assessments,
            'page_title' => 'Mes Évaluations'
        ]);
    }

    /**
     * View specific skills assessment
     */
    #[Route('/assessments/{id}', name: 'assessment_show', methods: ['GET'])]
    public function showAssessment(SkillsAssessment $assessment): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        if ($assessment->getStudent() !== $student) {
            throw $this->createAccessDeniedException('Vous ne pouvez voir que vos propres évaluations.');
        }

        return $this->render('student/alternance/assessment_show.html.twig', [
            'assessment' => $assessment,
            'page_title' => 'Évaluation - ' . ($assessment->getRelatedMission() ? $assessment->getRelatedMission()->getMission()->getTitle() : 'Évaluation')
        ]);
    }

    /**
     * View coordination meetings
     */
    #[Route('/meetings', name: 'meetings', methods: ['GET'])]
    public function meetings(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        $meetings = $this->entityManager->getRepository(CoordinationMeeting::class)
            ->findBy(['student' => $student], ['date' => 'DESC']);

        return $this->render('student/alternance/meetings.html.twig', [
            'meetings' => $meetings,
            'page_title' => 'Réunions de Coordination'
        ]);
    }

    /**
     * View specific coordination meeting
     */
    #[Route('/meetings/{id}', name: 'meeting_show', methods: ['GET'])]
    public function showMeeting(CoordinationMeeting $meeting): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        if ($meeting->getStudent() !== $student) {
            throw $this->createAccessDeniedException('Vous ne pouvez voir que vos propres réunions.');
        }

        return $this->render('student/alternance/meeting_show.html.twig', [
            'meeting' => $meeting,
            'page_title' => 'Réunion - ' . $meeting->getDate()->format('d/m/Y')
        ]);
    }

    /**
     * AJAX: Get dashboard statistics
     */
    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        /** @var Student $student */
        $student = $this->getUser();

        $assignments = $this->entityManager->getRepository(MissionAssignment::class)
            ->findBy(['student' => $student]);

        $stats = $this->calculateStudentStats($student, $assignments);

        return $this->json(['stats' => $stats]);
    }

    /**
     * AJAX: Get recent activities
     */
    #[Route('/api/activities', name: 'api_activities', methods: ['GET'])]
    public function getRecentActivities(): JsonResponse
    {
        /** @var Student $student */
        $student = $this->getUser();

        $activities = [];

        // Recent assignment updates
        $recentAssignments = $this->entityManager->getRepository(MissionAssignment::class)
            ->findBy(['student' => $student], ['updatedAt' => 'DESC'], 5);

        foreach ($recentAssignments as $assignment) {
            $activities[] = [
                'type' => 'assignment_update',
                'title' => 'Mission mise à jour',
                'description' => $assignment->getMission()->getTitle(),
                'date' => $assignment->getUpdatedAt(),
                'url' => $this->generateUrl('student_alternance_mission_show', ['id' => $assignment->getId()])
            ];
        }

        // Recent assessments
        $recentAssessments = $this->entityManager->getRepository(SkillsAssessment::class)
            ->findBy(['student' => $student], ['createdAt' => 'DESC'], 3);

        foreach ($recentAssessments as $assessment) {
            $missionTitle = $assessment->getRelatedMission() 
                ? $assessment->getRelatedMission()->getMission()->getTitle() 
                : 'Évaluation générale';
            
            $activities[] = [
                'type' => 'assessment_created',
                'title' => 'Nouvelle évaluation',
                'description' => $missionTitle,
                'date' => $assessment->getCreatedAt(),
                'url' => $this->generateUrl('student_alternance_assessment_show', ['id' => $assessment->getId()])
            ];
        }

        // Sort by date
        usort($activities, fn($a, $b) => $b['date'] <=> $a['date']);

        return $this->json(['activities' => array_slice($activities, 0, 10)]);
    }

    /**
     * Calculate student statistics
     */
    private function calculateStudentStats(Student $student, array $assignments): array
    {
        $totalAssignments = count($assignments);
        $completedAssignments = array_filter($assignments, fn($a) => $a->isCompleted());
        $activeAssignments = array_filter($assignments, fn($a) => !$a->isCompleted());
        $overdueAssignments = array_filter($assignments, fn($a) => $a->isOverdue());

        $totalProgress = 0;
        foreach ($assignments as $assignment) {
            $totalProgress += $assignment->getCompletionRate();
        }

        $averageProgress = $totalAssignments > 0 ? $totalProgress / $totalAssignments : 0;

        return [
            'total_assignments' => $totalAssignments,
            'completed_assignments' => count($completedAssignments),
            'active_assignments' => count($activeAssignments),
            'overdue_assignments' => count($overdueAssignments),
            'average_progress' => round($averageProgress, 1),
            'completion_rate' => $totalAssignments > 0 ? round((count($completedAssignments) / $totalAssignments) * 100, 1) : 0
        ];
    }
}
