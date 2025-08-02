<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Entity\Alternance\CoordinationMeeting;
use App\Entity\Alternance\MissionAssignment;
use App\Entity\Alternance\SkillsAssessment;
use App\Entity\User\Student;
use App\Service\Alternance\MissionAssignmentService;
use App\Service\Alternance\SkillsAssessmentService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student Alternance Controller.
 *
 * Handles alternance-specific features for students including
 * mission assignments, skills assessments, and coordination meetings.
 */
#[Route('/student/alternance')]
#[IsGranted('ROLE_STUDENT')]
class AlternanceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissionAssignmentService $assignmentService,
        private SkillsAssessmentService $assessmentService,
        private LoggerInterface $logger,
    ) {}

    /**
     * Alternance dashboard for students.
     */
    #[Route('/', name: 'student_alternance_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student accessing alternance dashboard', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'action' => 'dashboard_access',
            ]);

            // Get current assignments
            $this->logger->debug('Fetching student assignments', [
                'student_id' => $student->getId(),
            ]);

            $assignments = $this->entityManager->getRepository(MissionAssignment::class)
                ->findBy(['student' => $student], ['createdAt' => 'DESC'])
            ;

            $this->logger->debug('Retrieved assignments for student', [
                'student_id' => $student->getId(),
                'assignments_count' => count($assignments),
            ]);

            // Get recent assessments
            $this->logger->debug('Fetching recent assessments for student', [
                'student_id' => $student->getId(),
            ]);

            $assessments = $this->entityManager->getRepository(SkillsAssessment::class)
                ->findBy(['student' => $student], ['createdAt' => 'DESC'], 5)
            ;

            $this->logger->debug('Retrieved assessments for student', [
                'student_id' => $student->getId(),
                'assessments_count' => count($assessments),
            ]);

            // Get upcoming meetings
            $this->logger->debug('Fetching upcoming coordination meetings', [
                'student_id' => $student->getId(),
            ]);

            $upcomingMeetings = $this->entityManager->getRepository(CoordinationMeeting::class)
                ->createQueryBuilder('cm')
                ->where('cm.student = :student')
                ->andWhere('cm.date > :now')
                ->setParameter('student', $student)
                ->setParameter('now', new DateTime())
                ->orderBy('cm.date', 'ASC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult()
            ;

            $this->logger->debug('Retrieved upcoming meetings for student', [
                'student_id' => $student->getId(),
                'meetings_count' => count($upcomingMeetings),
            ]);

            // Calculate statistics
            $this->logger->debug('Calculating student statistics', [
                'student_id' => $student->getId(),
            ]);

            $stats = $this->calculateStudentStats($student, $assignments);

            $this->logger->info('Successfully loaded alternance dashboard', [
                'student_id' => $student->getId(),
                'stats' => $stats,
                'data_loaded' => [
                    'assignments' => count($assignments),
                    'assessments' => count($assessments),
                    'upcoming_meetings' => count($upcomingMeetings),
                ],
            ]);

            return $this->render('student/alternance/dashboard.html.twig', [
                'student' => $student,
                'assignments' => $assignments,
                'assessments' => $assessments,
                'upcoming_meetings' => $upcomingMeetings,
                'stats' => $stats,
                'page_title' => 'Mon Alternance',
            ]);
        } catch (Exception $e) {
            $currentUser = $this->getUser();
            $studentId = $currentUser instanceof Student ? $currentUser->getId() : null;

            $this->logger->error('Error loading alternance dashboard', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du tableau de bord.');

            return $this->render('student/alternance/dashboard.html.twig', [
                'student' => $currentUser,
                'assignments' => [],
                'assessments' => [],
                'upcoming_meetings' => [],
                'stats' => [
                    'total_assignments' => 0,
                    'completed_assignments' => 0,
                    'active_assignments' => 0,
                    'overdue_assignments' => 0,
                    'average_progress' => 0,
                    'completion_rate' => 0,
                ],
                'page_title' => 'Mon Alternance',
            ]);
        }
    }

    /**
     * View student's mission assignments.
     */
    #[Route('/missions', name: 'student_alternance_missions', methods: ['GET'])]
    public function missions(Request $request): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $status = $request->query->get('status', 'all');
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 10;

            $this->logger->info('Student accessing missions page', [
                'student_id' => $student->getId(),
                'status_filter' => $status,
                'page' => $page,
                'limit' => $limit,
                'is_ajax' => $request->isXmlHttpRequest(),
            ]);

            $this->logger->debug('Building mission assignments query', [
                'student_id' => $student->getId(),
                'status_filter' => $status,
            ]);

            $qb = $this->entityManager->getRepository(MissionAssignment::class)
                ->createQueryBuilder('ma')
                ->where('ma.student = :student')
                ->setParameter('student', $student)
                ->orderBy('ma.createdAt', 'DESC')
            ;

            // Apply status filter
            switch ($status) {
                case 'active':
                    $this->logger->debug('Applying active status filter');
                    $qb->andWhere('ma.status IN (:activeStatuses)')
                        ->setParameter('activeStatuses', ['planifiee', 'en_cours'])
                    ;
                    break;

                case 'completed':
                    $this->logger->debug('Applying completed status filter');
                    $qb->andWhere('ma.status = :completedStatus')
                        ->setParameter('completedStatus', 'terminee')
                    ;
                    break;

                case 'overdue':
                    $this->logger->debug('Applying overdue status filter');
                    $qb->andWhere('ma.endDate < :now')
                        ->andWhere('ma.status != :completedStatus')
                        ->setParameter('now', new DateTime())
                        ->setParameter('completedStatus', 'terminee')
                    ;
                    break;

                default:
                    $this->logger->debug('No status filter applied - showing all missions');
                    break;
            }

            // Create a separate count query without joins
            $this->logger->debug('Creating count query for pagination');
            $countQb = $this->entityManager->getRepository(MissionAssignment::class)
                ->createQueryBuilder('ma')
                ->select('COUNT(ma.id)')
                ->where('ma.student = :student')
                ->setParameter('student', $student)
            ;

            // Apply the same filters to count query
            switch ($status) {
                case 'active':
                    $countQb->andWhere('ma.status IN (:activeStatuses)')
                        ->setParameter('activeStatuses', ['planifiee', 'en_cours'])
                    ;
                    break;

                case 'completed':
                    $countQb->andWhere('ma.status = :completedStatus')
                        ->setParameter('completedStatus', 'terminee')
                    ;
                    break;

                case 'overdue':
                    $countQb->andWhere('ma.endDate < :now')
                        ->andWhere('ma.status != :completedStatus')
                        ->setParameter('now', new DateTime())
                        ->setParameter('completedStatus', 'terminee')
                    ;
                    break;
            }

            $totalCount = $countQb->getQuery()->getSingleScalarResult();
            $totalPages = ceil($totalCount / $limit);

            $this->logger->debug('Mission count calculation completed', [
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
                'current_page' => $page,
            ]);

            // Add join for the main query to get mission data
            $assignments = $qb
                ->join('ma.mission', 'm')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
            ;

            $this->logger->info('Successfully retrieved student missions', [
                'student_id' => $student->getId(),
                'assignments_count' => count($assignments),
                'total_count' => $totalCount,
                'status_filter' => $status,
                'page' => $page,
            ]);

            $filters = ['status' => $status];

            if ($request->isXmlHttpRequest()) {
                $this->logger->debug('Returning AJAX response for missions list');

                return $this->render('student/alternance/_missions_list.html.twig', [
                    'assignments' => $assignments,
                    'filters' => $filters,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_count' => $totalCount,
                ]);
            }

            return $this->render('student/alternance/missions.html.twig', [
                'assignments' => $assignments,
                'filters' => $filters,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_count' => $totalCount,
                'page_title' => 'Mes Missions',
            ]);
        } catch (Exception $e) {
            $currentUser = $this->getUser();
            $studentId = $currentUser instanceof Student ? $currentUser->getId() : null;

            $this->logger->error('Error loading student missions', [
                'student_id' => $studentId,
                'status_filter' => $request->query->get('status', 'all'),
                'page' => $request->query->getInt('page', 1),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des missions.');

            $emptyData = [
                'assignments' => [],
                'filters' => ['status' => $request->query->get('status', 'all')],
                'current_page' => 1,
                'total_pages' => 0,
                'total_count' => 0,
            ];

            if ($request->isXmlHttpRequest()) {
                return $this->render('student/alternance/_missions_list.html.twig', $emptyData);
            }

            return $this->render('student/alternance/missions.html.twig', array_merge($emptyData, [
                'page_title' => 'Mes Missions',
            ]));
        }
    }

    /**
     * View specific mission assignment details.
     */
    #[Route('/missions/{id}', name: 'student_alternance_mission_show', methods: ['GET'])]
    public function showMission(MissionAssignment $assignment): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student accessing mission details', [
                'student_id' => $student->getId(),
                'mission_assignment_id' => $assignment->getId(),
                'mission_id' => $assignment->getMission()->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
            ]);

            if ($assignment->getStudent() !== $student) {
                $this->logger->warning('Unauthorized access attempt to mission assignment', [
                    'student_id' => $student->getId(),
                    'mission_assignment_id' => $assignment->getId(),
                    'assignment_owner_id' => $assignment->getStudent()->getId(),
                ]);

                throw $this->createAccessDeniedException('Vous ne pouvez voir que vos propres missions.');
            }

            $this->logger->debug('Mission assignment details loaded successfully', [
                'student_id' => $student->getId(),
                'mission_assignment_id' => $assignment->getId(),
                'mission_status' => $assignment->getStatus(),
                'completion_rate' => $assignment->getCompletionRate(),
            ]);

            return $this->render('student/alternance/mission_show.html.twig', [
                'assignment' => $assignment,
                'mission' => $assignment->getMission(),
                'page_title' => $assignment->getMission()->getTitle(),
            ]);
        } catch (Exception $e) {
            $currentUser = $this->getUser();
            $studentId = $currentUser instanceof Student ? $currentUser->getId() : null;

            $this->logger->error('Error loading mission assignment details', [
                'student_id' => $studentId,
                'mission_assignment_id' => $assignment->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails de la mission.');

            return $this->redirectToRoute('student_alternance_missions');
        }
    }

    /**
     * Update mission progress (student self-assessment).
     */
    #[Route('/missions/{id}/progress', name: 'student_alternance_mission_update_progress', methods: ['POST'])]
    public function updateMissionProgress(MissionAssignment $assignment, Request $request): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student attempting to update mission progress', [
                'student_id' => $student->getId(),
                'mission_assignment_id' => $assignment->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'current_completion_rate' => $assignment->getCompletionRate(),
                'current_status' => $assignment->getStatus(),
            ]);

            if ($assignment->getStudent() !== $student) {
                $this->logger->warning('Unauthorized attempt to update mission progress', [
                    'student_id' => $student->getId(),
                    'mission_assignment_id' => $assignment->getId(),
                    'assignment_owner_id' => $assignment->getStudent()->getId(),
                ]);

                throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres missions.');
            }

            if ($assignment->isCompleted()) {
                $this->logger->warning('Attempt to update progress on completed mission', [
                    'student_id' => $student->getId(),
                    'mission_assignment_id' => $assignment->getId(),
                    'mission_status' => $assignment->getStatus(),
                ]);

                $this->addFlash('error', 'Cette mission est déjà terminée.');

                return $this->redirectToRoute('student_alternance_mission_show', ['id' => $assignment->getId()]);
            }

            $completionRate = $request->request->getInt('completion_rate');
            $selfAssessment = $request->request->get('self_assessment', '');

            $this->logger->debug('Processing mission progress update', [
                'student_id' => $student->getId(),
                'mission_assignment_id' => $assignment->getId(),
                'new_completion_rate' => $completionRate,
                'self_assessment_length' => strlen($selfAssessment),
                'previous_completion_rate' => $assignment->getCompletionRate(),
            ]);

            // Validate completion rate
            if ($completionRate < 0 || $completionRate > 100) {
                $this->logger->warning('Invalid completion rate provided', [
                    'student_id' => $student->getId(),
                    'mission_assignment_id' => $assignment->getId(),
                    'invalid_completion_rate' => $completionRate,
                ]);

                $this->addFlash('error', 'Le taux de completion doit être entre 0 et 100%.');

                return $this->redirectToRoute('student_alternance_mission_show', ['id' => $assignment->getId()]);
            }

            $this->assignmentService->updateProgress(
                $assignment,
                (float) $completionRate,
                ['selfAssessment' => $selfAssessment],
            );

            $this->logger->info('Mission progress updated successfully', [
                'student_id' => $student->getId(),
                'mission_assignment_id' => $assignment->getId(),
                'old_completion_rate' => $assignment->getCompletionRate(),
                'new_completion_rate' => $completionRate,
                'self_assessment_provided' => !empty($selfAssessment),
            ]);

            $this->addFlash('success', 'Progression mise à jour avec succès.');
        } catch (Exception $e) {
            $currentUser = $this->getUser();
            $studentId = $currentUser instanceof Student ? $currentUser->getId() : null;

            $this->logger->error('Error updating mission progress', [
                'student_id' => $studentId,
                'mission_assignment_id' => $assignment->getId(),
                'completion_rate' => $request->request->getInt('completion_rate'),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('student_alternance_mission_show', ['id' => $assignment->getId()]);
    }

    /**
     * View skills assessments.
     */
    #[Route('/assessments', name: 'student_alternance_assessments', methods: ['GET'])]
    public function assessments(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student accessing skills assessments', [
                'student_id' => $student->getId(),
                'action' => 'assessments_list',
            ]);

            $this->logger->debug('Fetching skills assessments for student', [
                'student_id' => $student->getId(),
            ]);

            $assessments = $this->entityManager->getRepository(SkillsAssessment::class)
                ->findBy(['student' => $student], ['createdAt' => 'DESC'])
            ;

            $this->logger->info('Successfully retrieved skills assessments', [
                'student_id' => $student->getId(),
                'assessments_count' => count($assessments),
            ]);

            return $this->render('student/alternance/assessments.html.twig', [
                'assessments' => $assessments,
                'page_title' => 'Mes Évaluations',
            ]);
        } catch (Exception $e) {
            $currentUser = $this->getUser();
            $studentId = $currentUser instanceof Student ? $currentUser->getId() : null;

            $this->logger->error('Error loading skills assessments', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des évaluations.');

            return $this->render('student/alternance/assessments.html.twig', [
                'assessments' => [],
                'page_title' => 'Mes Évaluations',
            ]);
        }
    }

    /**
     * View specific skills assessment.
     */
    #[Route('/assessments/{id}', name: 'student_alternance_assessment_show', methods: ['GET'])]
    public function showAssessment(SkillsAssessment $assessment): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student accessing skills assessment details', [
                'student_id' => $student->getId(),
                'assessment_id' => $assessment->getId(),
                'assessment_type' => $assessment->getAssessmentType(),
                'assessment_context' => $assessment->getContext(),
                'related_mission_id' => $assessment->getRelatedMission()?->getId(),
            ]);

            if ($assessment->getStudent() !== $student) {
                $this->logger->warning('Unauthorized access attempt to skills assessment', [
                    'student_id' => $student->getId(),
                    'assessment_id' => $assessment->getId(),
                    'assessment_owner_id' => $assessment->getStudent()->getId(),
                ]);

                throw $this->createAccessDeniedException('Vous ne pouvez voir que vos propres évaluations.');
            }

            $this->logger->debug('Skills assessment details loaded successfully', [
                'student_id' => $student->getId(),
                'assessment_id' => $assessment->getId(),
                'assessment_type' => $assessment->getAssessmentType(),
                'assessment_context' => $assessment->getContext(),
                'center_scores_count' => count($assessment->getCenterScores()),
                'company_scores_count' => count($assessment->getCompanyScores()),
                'overall_rating' => $assessment->getOverallRating(),
            ]);

            return $this->render('student/alternance/assessment_show.html.twig', [
                'assessment' => $assessment,
                'page_title' => 'Évaluation - ' . ($assessment->getRelatedMission() ? $assessment->getRelatedMission()->getMission()->getTitle() : 'Évaluation'),
            ]);
        } catch (Exception $e) {
            $currentUser = $this->getUser();
            $studentId = $currentUser instanceof Student ? $currentUser->getId() : null;

            $this->logger->error('Error loading skills assessment details', [
                'student_id' => $studentId,
                'assessment_id' => $assessment->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails de l\'évaluation.');

            return $this->redirectToRoute('student_alternance_assessments');
        }
    }

    /**
     * View coordination meetings.
     */
    #[Route('/meetings', name: 'student_alternance_meetings', methods: ['GET'])]
    public function meetings(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student accessing coordination meetings', [
                'student_id' => $student->getId(),
                'action' => 'meetings_list',
            ]);

            $this->logger->debug('Fetching coordination meetings for student', [
                'student_id' => $student->getId(),
            ]);

            $meetings = $this->entityManager->getRepository(CoordinationMeeting::class)
                ->findBy(['student' => $student], ['date' => 'DESC'])
            ;

            $this->logger->info('Successfully retrieved coordination meetings', [
                'student_id' => $student->getId(),
                'meetings_count' => count($meetings),
                'meetings_breakdown' => [
                    'total' => count($meetings),
                    'upcoming' => count(array_filter($meetings, static fn ($m) => $m->getDate() > new DateTime())),
                    'past' => count(array_filter($meetings, static fn ($m) => $m->getDate() <= new DateTime())),
                ],
            ]);

            return $this->render('student/alternance/meetings.html.twig', [
                'meetings' => $meetings,
                'page_title' => 'Réunions de Coordination',
            ]);
        } catch (Exception $e) {
            $currentUser = $this->getUser();
            $studentId = $currentUser instanceof Student ? $currentUser->getId() : null;

            $this->logger->error('Error loading coordination meetings', [
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des réunions.');

            return $this->render('student/alternance/meetings.html.twig', [
                'meetings' => [],
                'page_title' => 'Réunions de Coordination',
            ]);
        }
    }

    /**
     * View specific coordination meeting.
     */
    #[Route('/meetings/{id}', name: 'student_alternance_meeting_show', methods: ['GET'])]
    public function showMeeting(CoordinationMeeting $meeting): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student accessing coordination meeting details', [
                'student_id' => $student->getId(),
                'meeting_id' => $meeting->getId(),
                'meeting_date' => $meeting->getDate()->format('Y-m-d H:i'),
                'meeting_type' => $meeting->getType(),
                'meeting_status' => $meeting->getStatus(),
            ]);

            if ($meeting->getStudent() !== $student) {
                $this->logger->warning('Unauthorized access attempt to coordination meeting', [
                    'student_id' => $student->getId(),
                    'meeting_id' => $meeting->getId(),
                    'meeting_owner_id' => $meeting->getStudent()->getId(),
                ]);

                throw $this->createAccessDeniedException('Vous ne pouvez voir que vos propres réunions.');
            }

            $this->logger->debug('Coordination meeting details loaded successfully', [
                'student_id' => $student->getId(),
                'meeting_id' => $meeting->getId(),
                'agenda_items_count' => count($meeting->getAgenda()),
                'has_notes' => !empty($meeting->getNotes()),
                'duration_minutes' => $meeting->getDuration(),
            ]);

            return $this->render('student/alternance/meeting_show.html.twig', [
                'meeting' => $meeting,
                'page_title' => 'Réunion - ' . $meeting->getDate()->format('d/m/Y'),
            ]);
        } catch (Exception $e) {
            $currentUser = $this->getUser();
            $studentId = $currentUser instanceof Student ? $currentUser->getId() : null;

            $this->logger->error('Error loading coordination meeting details', [
                'student_id' => $studentId,
                'meeting_id' => $meeting->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails de la réunion.');

            return $this->redirectToRoute('student_alternance_meetings');
        }
    }

    /**
     * Calculate student statistics.
     */
    private function calculateStudentStats(Student $student, array $assignments): array
    {
        try {
            $this->logger->debug('Calculating student statistics', [
                'student_id' => $student->getId(),
                'assignments_count' => count($assignments),
            ]);

            $totalAssignments = count($assignments);
            $completedAssignments = array_filter($assignments, static fn ($a) => $a->isCompleted());
            $activeAssignments = array_filter($assignments, static fn ($a) => !$a->isCompleted());
            $overdueAssignments = array_filter($assignments, static fn ($a) => $a->isOverdue());

            $totalProgress = 0;
            foreach ($assignments as $assignment) {
                $totalProgress += $assignment->getCompletionRate();
            }

            $averageProgress = $totalAssignments > 0 ? $totalProgress / $totalAssignments : 0;
            $completionRate = $totalAssignments > 0 ? round((count($completedAssignments) / $totalAssignments) * 100, 1) : 0;

            $stats = [
                'total_assignments' => $totalAssignments,
                'completed_assignments' => count($completedAssignments),
                'active_assignments' => count($activeAssignments),
                'overdue_assignments' => count($overdueAssignments),
                'average_progress' => round($averageProgress, 1),
                'completion_rate' => $completionRate,
            ];

            $this->logger->debug('Student statistics calculated successfully', [
                'student_id' => $student->getId(),
                'stats' => $stats,
            ]);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error('Error calculating student statistics', [
                'student_id' => $student->getId(),
                'assignments_count' => count($assignments),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return default stats in case of error
            return [
                'total_assignments' => 0,
                'completed_assignments' => 0,
                'active_assignments' => 0,
                'overdue_assignments' => 0,
                'average_progress' => 0,
                'completion_rate' => 0,
            ];
        }
    }
}
