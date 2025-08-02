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
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
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
#[Route('/mentor/assignments')]
#[IsGranted('ROLE_MENTOR')]
class AssignmentController extends AbstractController
{
    public function __construct(
        private MissionAssignmentService $assignmentService,
        private MissionAssignmentRepository $assignmentRepository,
        private CompanyMissionRepository $missionRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * List all assignments managed by the mentor.
     */
    #[Route('', name: 'mentor_assignments_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor assignments index accessed', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'request_uri' => $request->getRequestUri(),
                'user_agent' => $request->headers->get('User-Agent'),
                'ip_address' => $request->getClientIp(),
            ]);

            // Filter parameters
            $status = $request->query->get('status', 'all');
            $student = $request->query->get('student', 'all');
            $mission = $request->query->get('mission', 'all');
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = 10;

            $this->logger->debug('Assignments index filters applied', [
                'mentor_id' => $mentor->getId(),
                'filters' => [
                    'status' => $status,
                    'student' => $student,
                    'mission' => $mission,
                    'page' => $page,
                    'limit' => $limit,
                ],
            ]);

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
                $this->logger->debug('Status filter applied', [
                    'mentor_id' => $mentor->getId(),
                    'status_filter' => $status,
                ]);
            }

            if ($student !== 'all') {
                $queryBuilder->andWhere('a.student = :student')
                    ->setParameter('student', (int) $student)
                ;
                $this->logger->debug('Student filter applied', [
                    'mentor_id' => $mentor->getId(),
                    'student_filter' => $student,
                ]);
            }

            if ($mission !== 'all') {
                $queryBuilder->andWhere('m.id = :mission')
                    ->setParameter('mission', (int) $mission)
                ;
                $this->logger->debug('Mission filter applied', [
                    'mentor_id' => $mentor->getId(),
                    'mission_filter' => $mission,
                ]);
            }

            // Get total count
            $totalCount = count($queryBuilder->getQuery()->getResult());
            $totalPages = ceil($totalCount / $limit);

            $this->logger->debug('Query results calculated', [
                'mentor_id' => $mentor->getId(),
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
                'current_page' => $page,
            ]);

            // Apply pagination
            $assignments = $queryBuilder
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
            ;

            $this->logger->debug('Assignments retrieved with pagination', [
                'mentor_id' => $mentor->getId(),
                'assignments_count' => count($assignments),
                'offset' => ($page - 1) * $limit,
                'limit' => $limit,
            ]);

            // Get statistics
            $stats = $this->getAssignmentStats($mentor);

            $this->logger->debug('Assignment statistics calculated', [
                'mentor_id' => $mentor->getId(),
                'stats' => $stats,
            ]);

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

            $this->logger->debug('Students for filter retrieved', [
                'mentor_id' => $mentor->getId(),
                'students_count' => count($students),
            ]);

            // Get missions for filter (missions supervised by this mentor)
            $availableMissions = $this->missionRepository
                ->createQueryBuilder('m')
                ->where('m.supervisor = :mentor')
                ->setParameter('mentor', $mentor)
                ->orderBy('m.title', 'ASC')
                ->getQuery()
                ->getResult()
            ;

            $this->logger->debug('Available missions for filter retrieved', [
                'mentor_id' => $mentor->getId(),
                'missions_count' => count($availableMissions),
            ]);

            $this->logger->info('Mentor assignments index successfully rendered', [
                'mentor_id' => $mentor->getId(),
                'assignments_displayed' => count($assignments),
                'page' => $page,
                'total_pages' => $totalPages,
            ]);

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
        } catch (Exception $e) {
            $this->logger->error('Error in mentor assignments index', [
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_uri' => $request->getRequestUri(),
                'request_method' => $request->getMethod(),
                'request_parameters' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des assignations. Veuillez réessayer.');

            // Return a basic view with empty data in case of error
            return $this->render('mentor/assignments/index.html.twig', [
                'assignments' => [],
                'mentor' => $this->getUser(),
                'stats' => [
                    'total' => 0,
                    'planned' => 0,
                    'in_progress' => 0,
                    'completed' => 0,
                    'suspended' => 0,
                    'completion_rate' => 0,
                ],
                'students' => [],
                'available_missions' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_count' => 0,
                'complexity_colors' => [
                    'facile' => 'success',
                    'moyen' => 'warning',
                    'difficile' => 'danger',
                ],
                'filters' => [
                    'status' => 'all',
                    'student' => 'all',
                    'mission' => 'all',
                ],
                'page_title' => 'Assignations de Missions',
            ]);
        }
    }

    /**
     * Show assignment details.
     */
    #[Route('/{id}', name: 'mentor_assignments_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(MissionAssignment $assignment): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor assignment show accessed', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'assignment_id' => $assignment->getId(),
                'assignment_status' => $assignment->getStatus(),
                'mission_id' => $assignment->getMission()->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'student_id' => $assignment->getStudent()?->getId(),
                'student_email' => $assignment->getStudent()?->getEmail(),
            ]);

            // Security check: ensure mentor owns this assignment's mission
            if ($assignment->getMission()->getSupervisor() !== $mentor) {
                $this->logger->warning('Unauthorized access attempt to assignment', [
                    'mentor_id' => $mentor->getId(),
                    'assignment_id' => $assignment->getId(),
                    'assignment_supervisor_id' => $assignment->getMission()->getSupervisor()?->getId(),
                    'attempt_type' => 'unauthorized_assignment_access',
                ]);

                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette assignation.');
            }

            $this->logger->debug('Assignment details retrieved successfully', [
                'mentor_id' => $mentor->getId(),
                'assignment_id' => $assignment->getId(),
                'completion_rate' => $assignment->getCompletionRate(),
                'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                'mentor_rating' => $assignment->getMentorRating(),
                'student_satisfaction' => $assignment->getStudentSatisfaction(),
            ]);

            $this->logger->info('Mentor assignment show successfully rendered', [
                'mentor_id' => $mentor->getId(),
                'assignment_id' => $assignment->getId(),
            ]);

            return $this->render('mentor/assignments/show.html.twig', [
                'assignment' => $assignment,
                'mentor' => $mentor,
                'page_title' => 'Assignation - ' . $assignment->getMission()->getTitle(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in mentor assignment show', [
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
                'assignment_id' => $assignment->getId() ?? null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de l\'assignation. Veuillez réessayer.');

            return $this->redirectToRoute('mentor_assignments_index');
        }
    }

    /**
     * Create a new assignment.
     */
    #[Route('/create', name: 'mentor_assignments_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor assignment create accessed', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'request_method' => $request->getMethod(),
                'user_agent' => $request->headers->get('User-Agent'),
                'ip_address' => $request->getClientIp(),
            ]);

            $assignment = new MissionAssignment();
            $form = $this->createForm(MissionAssignmentType::class, $assignment, [
                'mentor' => $mentor,
            ]);

            $this->logger->debug('Assignment creation form created', [
                'mentor_id' => $mentor->getId(),
                'form_name' => $form->getName(),
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('Assignment creation form submitted', [
                    'mentor_id' => $mentor->getId(),
                    'form_valid' => $form->isValid(),
                    'assignment_data' => [
                        'mission_id' => $assignment->getMission()?->getId(),
                        'student_id' => $assignment->getStudent()?->getId(),
                        'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                        'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                        'status' => $assignment->getStatus(),
                    ],
                ]);
            }

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->logger->info('Creating new assignment', [
                        'mentor_id' => $mentor->getId(),
                        'mission_id' => $assignment->getMission()->getId(),
                        'mission_title' => $assignment->getMission()->getTitle(),
                        'student_id' => $assignment->getStudent()->getId(),
                        'student_email' => $assignment->getStudent()->getEmail(),
                        'assignment_details' => [
                            'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                            'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                            'status' => $assignment->getStatus(),
                            'completion_rate' => $assignment->getCompletionRate() ?? 0.0,
                        ],
                    ]);

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

                    $this->logger->info('Assignment created successfully', [
                        'mentor_id' => $mentor->getId(),
                        'assignment_id' => $createdAssignment->getId(),
                        'mission_id' => $createdAssignment->getMission()->getId(),
                        'student_id' => $createdAssignment->getStudent()->getId(),
                        'creation_timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ]);

                    $this->addFlash('success', 'Assignation créée avec succès !');

                    return $this->redirectToRoute('mentor_assignments_show', ['id' => $createdAssignment->getId()]);
                } catch (Exception $e) {
                    $this->logger->error('Error creating assignment via service', [
                        'mentor_id' => $mentor->getId(),
                        'mission_id' => $assignment->getMission()?->getId(),
                        'student_id' => $assignment->getStudent()?->getId(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString(),
                        'assignment_data' => [
                            'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                            'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                            'status' => $assignment->getStatus(),
                        ],
                    ]);

                    $this->addFlash('error', 'Erreur lors de la création de l\'assignation : ' . $e->getMessage());
                }
            }

            $this->logger->debug('Rendering assignment creation form', [
                'mentor_id' => $mentor->getId(),
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
            ]);

            return $this->render('mentor/assignments/create.html.twig', [
                'form' => $form,
                'mentor' => $mentor,
                'page_title' => 'Créer une Assignation',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in mentor assignment create', [
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_method' => $request->getMethod(),
                'request_uri' => $request->getRequestUri(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création de l\'assignation. Veuillez réessayer.');

            return $this->redirectToRoute('mentor_assignments_index');
        }
    }

    /**
     * Edit an assignment.
     */
    #[Route('/{id}/edit', name: 'mentor_assignments_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, MissionAssignment $assignment): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor assignment edit accessed', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'assignment_id' => $assignment->getId(),
                'assignment_status' => $assignment->getStatus(),
                'mission_id' => $assignment->getMission()->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'student_id' => $assignment->getStudent()?->getId(),
                'request_method' => $request->getMethod(),
            ]);

            // Security check: ensure mentor owns this assignment's mission
            if ($assignment->getMission()->getSupervisor() !== $mentor) {
                $this->logger->warning('Unauthorized edit attempt on assignment', [
                    'mentor_id' => $mentor->getId(),
                    'assignment_id' => $assignment->getId(),
                    'assignment_supervisor_id' => $assignment->getMission()->getSupervisor()?->getId(),
                    'attempt_type' => 'unauthorized_assignment_edit',
                ]);

                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette assignation.');
            }

            // Store original values for comparison
            $originalData = [
                'status' => $assignment->getStatus(),
                'completion_rate' => $assignment->getCompletionRate(),
                'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                'mentor_rating' => $assignment->getMentorRating(),
                'student_satisfaction' => $assignment->getStudentSatisfaction(),
            ];

            $form = $this->createForm(MissionAssignmentType::class, $assignment, [
                'mentor' => $mentor,
            ]);

            $this->logger->debug('Assignment edit form created', [
                'mentor_id' => $mentor->getId(),
                'assignment_id' => $assignment->getId(),
                'form_name' => $form->getName(),
                'original_data' => $originalData,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('Assignment edit form submitted', [
                    'mentor_id' => $mentor->getId(),
                    'assignment_id' => $assignment->getId(),
                    'form_valid' => $form->isValid(),
                    'updated_data' => [
                        'status' => $assignment->getStatus(),
                        'completion_rate' => $assignment->getCompletionRate(),
                        'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                        'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                        'mentor_rating' => $assignment->getMentorRating(),
                        'student_satisfaction' => $assignment->getStudentSatisfaction(),
                    ],
                ]);
            }

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->logger->info('Updating assignment', [
                        'mentor_id' => $mentor->getId(),
                        'assignment_id' => $assignment->getId(),
                        'mission_id' => $assignment->getMission()->getId(),
                        'student_id' => $assignment->getStudent()->getId(),
                        'changes' => [
                            'original' => $originalData,
                            'updated' => [
                                'status' => $assignment->getStatus(),
                                'completion_rate' => $assignment->getCompletionRate(),
                                'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                                'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                                'mentor_rating' => $assignment->getMentorRating(),
                                'student_satisfaction' => $assignment->getStudentSatisfaction(),
                            ],
                        ],
                    ]);

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

                    $this->logger->info('Assignment updated successfully', [
                        'mentor_id' => $mentor->getId(),
                        'assignment_id' => $assignment->getId(),
                        'mission_id' => $assignment->getMission()->getId(),
                        'student_id' => $assignment->getStudent()->getId(),
                        'update_timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ]);

                    $this->addFlash('success', 'Assignation mise à jour avec succès !');

                    return $this->redirectToRoute('mentor_assignments_show', ['id' => $assignment->getId()]);
                } catch (Exception $e) {
                    $this->logger->error('Error updating assignment via service', [
                        'mentor_id' => $mentor->getId(),
                        'assignment_id' => $assignment->getId(),
                        'mission_id' => $assignment->getMission()->getId(),
                        'student_id' => $assignment->getStudent()->getId(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString(),
                        'update_data' => [
                            'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                            'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                            'status' => $assignment->getStatus(),
                            'completion_rate' => $assignment->getCompletionRate(),
                        ],
                    ]);

                    $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
                }
            }

            $this->logger->debug('Rendering assignment edit form', [
                'mentor_id' => $mentor->getId(),
                'assignment_id' => $assignment->getId(),
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
            ]);

            return $this->render('mentor/assignments/edit.html.twig', [
                'form' => $form,
                'assignment' => $assignment,
                'mentor' => $mentor,
                'page_title' => 'Modifier l\'Assignation',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in mentor assignment edit', [
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
                'assignment_id' => $assignment->getId() ?? null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_method' => $request->getMethod(),
                'request_uri' => $request->getRequestUri(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification de l\'assignation. Veuillez réessayer.');

            return $this->redirectToRoute('mentor_assignments_index');
        }
    }

    /**
     * Update assignment progress.
     */
    #[Route('/{id}/progress', name: 'mentor_assignments_progress', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateProgress(Request $request, MissionAssignment $assignment): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor assignment progress update initiated', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'assignment_id' => $assignment->getId(),
                'mission_id' => $assignment->getMission()->getId(),
                'student_id' => $assignment->getStudent()?->getId(),
                'current_completion_rate' => $assignment->getCompletionRate(),
                'current_status' => $assignment->getStatus(),
            ]);

            // Security check: ensure mentor owns this assignment's mission
            if ($assignment->getMission()->getSupervisor() !== $mentor) {
                $this->logger->warning('Unauthorized progress update attempt on assignment', [
                    'mentor_id' => $mentor->getId(),
                    'assignment_id' => $assignment->getId(),
                    'assignment_supervisor_id' => $assignment->getMission()->getSupervisor()?->getId(),
                    'attempt_type' => 'unauthorized_progress_update',
                ]);

                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette assignation.');
            }

            $completionRate = (float) $request->request->get('completion_rate', 0);
            $status = $request->request->get('status', $assignment->getStatus());

            $this->logger->debug('Progress update parameters received', [
                'mentor_id' => $mentor->getId(),
                'assignment_id' => $assignment->getId(),
                'requested_completion_rate' => $completionRate,
                'requested_status' => $status,
                'previous_completion_rate' => $assignment->getCompletionRate(),
                'previous_status' => $assignment->getStatus(),
            ]);

            try {
                $this->assignmentService->updateProgress($assignment, $completionRate, [
                    'status' => $status,
                ]);

                $this->logger->info('Assignment progress updated successfully', [
                    'mentor_id' => $mentor->getId(),
                    'assignment_id' => $assignment->getId(),
                    'mission_id' => $assignment->getMission()->getId(),
                    'student_id' => $assignment->getStudent()->getId(),
                    'progress_change' => [
                        'completion_rate' => [
                            'from' => $assignment->getCompletionRate(),
                            'to' => $completionRate,
                        ],
                        'status' => [
                            'from' => $assignment->getStatus(),
                            'to' => $status,
                        ],
                    ],
                    'update_timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);

                $this->addFlash('success', 'Progression mise à jour avec succès !');
            } catch (Exception $e) {
                $this->logger->error('Error updating assignment progress via service', [
                    'mentor_id' => $mentor->getId(),
                    'assignment_id' => $assignment->getId(),
                    'mission_id' => $assignment->getMission()->getId(),
                    'student_id' => $assignment->getStudent()->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'stack_trace' => $e->getTraceAsString(),
                    'update_parameters' => [
                        'completion_rate' => $completionRate,
                        'status' => $status,
                    ],
                ]);

                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }

            return $this->redirectToRoute('mentor_assignments_show', ['id' => $assignment->getId()]);
        } catch (Exception $e) {
            $this->logger->error('Error in mentor assignment progress update', [
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
                'assignment_id' => $assignment->getId() ?? null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_method' => $request->getMethod(),
                'request_parameters' => $request->request->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour de la progression. Veuillez réessayer.');

            return $this->redirectToRoute('mentor_assignments_index');
        }
    }

    /**
     * Complete an assignment.
     */
    #[Route('/{id}/complete', name: 'mentor_assignments_complete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function complete(MissionAssignment $assignment): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor assignment completion initiated', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'assignment_id' => $assignment->getId(),
                'mission_id' => $assignment->getMission()->getId(),
                'mission_title' => $assignment->getMission()->getTitle(),
                'student_id' => $assignment->getStudent()?->getId(),
                'student_email' => $assignment->getStudent()?->getEmail(),
                'current_status' => $assignment->getStatus(),
                'current_completion_rate' => $assignment->getCompletionRate(),
                'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
            ]);

            // Security check: ensure mentor owns this assignment's mission
            if ($assignment->getMission()->getSupervisor() !== $mentor) {
                $this->logger->warning('Unauthorized completion attempt on assignment', [
                    'mentor_id' => $mentor->getId(),
                    'assignment_id' => $assignment->getId(),
                    'assignment_supervisor_id' => $assignment->getMission()->getSupervisor()?->getId(),
                    'attempt_type' => 'unauthorized_assignment_completion',
                ]);

                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette assignation.');
            }

            try {
                $this->assignmentService->completeAssignment($assignment);

                $this->logger->info('Assignment completed successfully', [
                    'mentor_id' => $mentor->getId(),
                    'assignment_id' => $assignment->getId(),
                    'mission_id' => $assignment->getMission()->getId(),
                    'student_id' => $assignment->getStudent()->getId(),
                    'completion_details' => [
                        'previous_status' => $assignment->getStatus(),
                        'final_completion_rate' => $assignment->getCompletionRate(),
                        'completion_timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                        'mission_title' => $assignment->getMission()->getTitle(),
                        'mentor_rating' => $assignment->getMentorRating(),
                        'student_satisfaction' => $assignment->getStudentSatisfaction(),
                    ],
                    'duration_analysis' => [
                        'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                        'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                        'actual_completion_date' => (new DateTimeImmutable())->format('Y-m-d'),
                    ],
                ]);

                $this->addFlash('success', 'Mission marquée comme terminée !');
            } catch (Exception $e) {
                $this->logger->error('Error completing assignment via service', [
                    'mentor_id' => $mentor->getId(),
                    'assignment_id' => $assignment->getId(),
                    'mission_id' => $assignment->getMission()->getId(),
                    'student_id' => $assignment->getStudent()->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'stack_trace' => $e->getTraceAsString(),
                    'assignment_state' => [
                        'status' => $assignment->getStatus(),
                        'completion_rate' => $assignment->getCompletionRate(),
                        'start_date' => $assignment->getStartDate()?->format('Y-m-d'),
                        'end_date' => $assignment->getEndDate()?->format('Y-m-d'),
                    ],
                ]);

                $this->addFlash('error', 'Erreur lors de la finalisation : ' . $e->getMessage());
            }

            return $this->redirectToRoute('mentor_assignments_show', ['id' => $assignment->getId()]);
        } catch (Exception $e) {
            $this->logger->error('Error in mentor assignment completion', [
                'mentor_id' => ($user = $this->getUser()) instanceof Mentor ? $user->getId() : null,
                'assignment_id' => $assignment->getId() ?? null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la finalisation de l\'assignation. Veuillez réessayer.');

            return $this->redirectToRoute('mentor_assignments_index');
        }
    }

    /**
     * Get assignment statistics for the mentor.
     */
    private function getAssignmentStats(Mentor $mentor): array
    {
        try {
            $this->logger->debug('Calculating assignment statistics', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
            ]);

            $qb = $this->assignmentRepository->createQueryBuilder('a')
                ->innerJoin('a.mission', 'm')
                ->where('m.supervisor = :mentor')
                ->setParameter('mentor', $mentor)
            ;

            $total = count($qb->getQuery()->getResult());

            $this->logger->debug('Total assignments calculated', [
                'mentor_id' => $mentor->getId(),
                'total_assignments' => $total,
            ]);

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

            $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

            $stats = [
                'total' => $total,
                'planned' => $planned,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'suspended' => $suspended,
                'completion_rate' => $completionRate,
            ];

            $this->logger->debug('Assignment statistics calculated successfully', [
                'mentor_id' => $mentor->getId(),
                'statistics' => $stats,
                'calculation_timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error('Error calculating assignment statistics', [
                'mentor_id' => $mentor->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return default stats in case of error
            return [
                'total' => 0,
                'planned' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'suspended' => 0,
                'completion_rate' => 0,
            ];
        }
    }
}
