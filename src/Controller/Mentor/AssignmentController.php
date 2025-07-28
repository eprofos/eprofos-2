<?php

declare(strict_types=1);

namespace App\Controller\Mentor;

use App\Entity\Alternance\MissionAssignment;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Form\Alternance\MissionAssignmentType;
use App\Repository\Alternance\CompanyMissionRepository;
use App\Repository\Alternance\MissionAssignmentRepository;
use App\Service\Alternance\MissionAssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mentor Assignment Controller.
 *
 * Handles mission assignments management for mentors
 */
#[Route('/mentor/assignments', name: 'mentor_assignments_')]
#[IsGranted('ROLE_MENTOR')]
class AssignmentController extends AbstractController
{
    public function __construct(
        private MissionAssignmentService $assignmentService,
        private MissionAssignmentRepository $assignmentRepository,
        private CompanyMissionRepository $missionRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * List all assignments managed by the mentor.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Filter parameters
        $status = $request->query->get('status', 'all');
        $student = $request->query->get('student', 'all');
        $mission = $request->query->get('mission', 'all');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        // Build query for assignments of mentor's missions
        $queryBuilder = $this->assignmentRepository->createQueryBuilder('a')
            ->innerJoin('a.mission', 'm')
            ->where('m.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
            ->orderBy('a.startDate', 'DESC')
        ;

        if ($status !== 'all') {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $status)
            ;
        }

        if ($student !== 'all') {
            $queryBuilder->andWhere('a.student = :student')
                ->setParameter('student', (int) $student)
            ;
        }

        if ($mission !== 'all') {
            $queryBuilder->andWhere('m.id = :mission')
                ->setParameter('mission', (int) $mission)
            ;
        }

        // Get total count
        $totalCount = count($queryBuilder->getQuery()->getResult());
        $totalPages = ceil($totalCount / $limit);

        // Apply pagination
        $assignments = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        // Get statistics
        $stats = $this->getAssignmentStats($mentor);

        // Get students for filter (students who have assignments with this mentor)
        $students = $this->entityManager->getRepository(Student::class)
            ->createQueryBuilder('s')
            ->innerJoin('s.missionAssignments', 'ma')
            ->innerJoin('ma.mission', 'm')
            ->where('m.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
            ->orderBy('s.lastName', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        // Get missions for filter (missions supervised by this mentor)
        $availableMissions = $this->missionRepository
            ->createQueryBuilder('m')
            ->where('m.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
            ->orderBy('m.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $this->render('mentor/assignments/index.html.twig', [
            'assignments' => $assignments,
            'mentor' => $mentor,
            'stats' => $stats,
            'students' => $students,
            'available_missions' => $availableMissions,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'complexity_colors' => [
                'facile' => 'success',
                'moyen' => 'warning',
                'difficile' => 'danger',
            ],
            'filters' => [
                'status' => $status,
                'student' => $student,
                'mission' => $mission,
            ],
            'page_title' => 'Assignations de Missions',
        ]);
    }

    /**
     * Show assignment details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(MissionAssignment $assignment): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Security check: ensure mentor owns this assignment's mission
        if ($assignment->getMission()->getSupervisor() !== $mentor) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette assignation.');
        }

        return $this->render('mentor/assignments/show.html.twig', [
            'assignment' => $assignment,
            'mentor' => $mentor,
            'page_title' => 'Assignation - ' . $assignment->getMission()->getTitle(),
        ]);
    }

    /**
     * Create a new assignment.
     */
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        $assignment = new MissionAssignment();
        $form = $this->createForm(MissionAssignmentType::class, $assignment, [
            'mentor' => $mentor,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Use the service to create the assignment
                $createdAssignment = $this->assignmentService->createAssignment(
                    $assignment->getMission(),
                    $assignment->getStudent(),
                    [
                        'startDate' => $assignment->getStartDate(),
                        'endDate' => $assignment->getEndDate(),
                        'status' => $assignment->getStatus(),
                        'intermediateObjectives' => $assignment->getIntermediateObjectives(),
                        'completionRate' => $assignment->getCompletionRate() ?? 0.0,
                        'difficulties' => $assignment->getDifficulties(),
                        'achievements' => $assignment->getAchievements(),
                        'mentorFeedback' => $assignment->getMentorFeedback(),
                        'studentFeedback' => $assignment->getStudentFeedback(),
                        'mentorRating' => $assignment->getMentorRating(),
                        'studentSatisfaction' => $assignment->getStudentSatisfaction(),
                        'competenciesAcquired' => $assignment->getCompetenciesAcquired(),
                    ],
                );

                $this->addFlash('success', 'Assignation créée avec succès !');

                return $this->redirectToRoute('mentor_assignments_show', ['id' => $createdAssignment->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création de l\'assignation : ' . $e->getMessage());
            }
        }

        return $this->render('mentor/assignments/create.html.twig', [
            'form' => $form,
            'mentor' => $mentor,
            'page_title' => 'Créer une Assignation',
        ]);
    }

    /**
     * Edit an assignment.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, MissionAssignment $assignment): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Security check: ensure mentor owns this assignment's mission
        if ($assignment->getMission()->getSupervisor() !== $mentor) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette assignation.');
        }

        $form = $this->createForm(MissionAssignmentType::class, $assignment, [
            'mentor' => $mentor,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Use the service to update the assignment
                $this->assignmentService->updateAssignment($assignment, [
                    'startDate' => $assignment->getStartDate(),
                    'endDate' => $assignment->getEndDate(),
                    'status' => $assignment->getStatus(),
                    'intermediateObjectives' => $assignment->getIntermediateObjectives(),
                    'completionRate' => $assignment->getCompletionRate(),
                    'difficulties' => $assignment->getDifficulties(),
                    'achievements' => $assignment->getAchievements(),
                    'mentorFeedback' => $assignment->getMentorFeedback(),
                    'studentFeedback' => $assignment->getStudentFeedback(),
                    'mentorRating' => $assignment->getMentorRating(),
                    'studentSatisfaction' => $assignment->getStudentSatisfaction(),
                    'competenciesAcquired' => $assignment->getCompetenciesAcquired(),
                ]);

                $this->addFlash('success', 'Assignation mise à jour avec succès !');

                return $this->redirectToRoute('mentor_assignments_show', ['id' => $assignment->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }

        return $this->render('mentor/assignments/edit.html.twig', [
            'form' => $form,
            'assignment' => $assignment,
            'mentor' => $mentor,
            'page_title' => 'Modifier l\'Assignation',
        ]);
    }

    /**
     * Update assignment progress.
     */
    #[Route('/{id}/progress', name: 'progress', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateProgress(Request $request, MissionAssignment $assignment): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Security check: ensure mentor owns this assignment's mission
        if ($assignment->getMission()->getSupervisor() !== $mentor) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette assignation.');
        }

        $completionRate = (float) $request->request->get('completion_rate', 0);
        $status = $request->request->get('status', $assignment->getStatus());

        try {
            $this->assignmentService->updateProgress($assignment, $completionRate, [
                'status' => $status,
            ]);
            $this->addFlash('success', 'Progression mise à jour avec succès !');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('mentor_assignments_show', ['id' => $assignment->getId()]);
    }

    /**
     * Complete an assignment.
     */
    #[Route('/{id}/complete', name: 'complete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function complete(MissionAssignment $assignment): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Security check: ensure mentor owns this assignment's mission
        if ($assignment->getMission()->getSupervisor() !== $mentor) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette assignation.');
        }

        try {
            $this->assignmentService->completeAssignment($assignment);
            $this->addFlash('success', 'Mission marquée comme terminée !');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la finalisation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('mentor_assignments_show', ['id' => $assignment->getId()]);
    }

    /**
     * Get assignment statistics for the mentor.
     */
    private function getAssignmentStats(Mentor $mentor): array
    {
        $qb = $this->assignmentRepository->createQueryBuilder('a')
            ->innerJoin('a.mission', 'm')
            ->where('m.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
        ;

        $total = count($qb->getQuery()->getResult());

        $planned = count($qb->andWhere('a.status = :status')
            ->setParameter('status', 'planifiee')
            ->getQuery()->getResult());

        $qb = $this->assignmentRepository->createQueryBuilder('a')
            ->innerJoin('a.mission', 'm')
            ->where('m.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
        ;

        $inProgress = count($qb->andWhere('a.status = :status')
            ->setParameter('status', 'en_cours')
            ->getQuery()->getResult());

        $qb = $this->assignmentRepository->createQueryBuilder('a')
            ->innerJoin('a.mission', 'm')
            ->where('m.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
        ;

        $completed = count($qb->andWhere('a.status = :status')
            ->setParameter('status', 'terminee')
            ->getQuery()->getResult());

        $qb = $this->assignmentRepository->createQueryBuilder('a')
            ->innerJoin('a.mission', 'm')
            ->where('m.supervisor = :mentor')
            ->setParameter('mentor', $mentor)
        ;

        $suspended = count($qb->andWhere('a.status = :status')
            ->setParameter('status', 'suspendue')
            ->getQuery()->getResult());

        return [
            'total' => $total,
            'planned' => $planned,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'suspended' => $suspended,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }
}
