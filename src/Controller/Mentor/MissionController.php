<?php

declare(strict_types=1);

namespace App\Controller\Mentor;

use App\Entity\Alternance\CompanyMission;
use App\Entity\User\Mentor;
use App\Form\Alternance\CompanyMissionType;
use App\Repository\Alternance\CompanyMissionRepository;
use App\Service\Alternance\CompanyMissionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mentor Mission Controller.
 *
 * Handles CRUD operations for company missions created by mentors
 */
#[Route('/mentor/missions')]
#[IsGranted('ROLE_MENTOR')]
class MissionController extends AbstractController
{
    public function __construct(
        private CompanyMissionService $missionService,
        private CompanyMissionRepository $missionRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * List all missions created by the mentor.
     */
    #[Route('', name: 'mentor_missions_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();
            
            $this->logger->info('Mentor accessing missions index page', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'request_uri' => $request->getRequestUri(),
                'user_agent' => $request->headers->get('User-Agent'),
                'ip_address' => $request->getClientIp(),
            ]);

            // Filter and pagination parameters
            $status = $request->query->get('status', 'all');
            $complexity = $request->query->get('complexity', 'all');
            $term = $request->query->get('term', 'all');
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = 10;

            $this->logger->debug('Mission index filters applied', [
                'mentor_id' => $mentor->getId(),
                'filters' => [
                    'status' => $status,
                    'complexity' => $complexity,
                    'term' => $term,
                    'page' => $page,
                    'limit' => $limit,
                ],
            ]);

            // Build query based on filters
            $queryBuilder = $this->missionRepository->createQueryBuilder('m')
                ->where('m.supervisor = :mentor')
                ->setParameter('mentor', $mentor)
                ->orderBy('m.createdAt', 'DESC')
            ;

            if ($status !== 'all') {
                $isActive = $status === 'active';
                $queryBuilder->andWhere('m.isActive = :isActive')
                    ->setParameter('isActive', $isActive)
                ;
                
                $this->logger->debug('Status filter applied', [
                    'mentor_id' => $mentor->getId(),
                    'status_filter' => $status,
                    'is_active' => $isActive,
                ]);
            }

            if ($complexity !== 'all') {
                $queryBuilder->andWhere('m.complexity = :complexity')
                    ->setParameter('complexity', $complexity)
                ;
                
                $this->logger->debug('Complexity filter applied', [
                    'mentor_id' => $mentor->getId(),
                    'complexity_filter' => $complexity,
                ]);
            }

            if ($term !== 'all') {
                $queryBuilder->andWhere('m.term = :term')
                    ->setParameter('term', $term)
                ;
                
                $this->logger->debug('Term filter applied', [
                    'mentor_id' => $mentor->getId(),
                    'term_filter' => $term,
                ]);
            }

            try {
                // Get total count
                $totalCount = count($queryBuilder->getQuery()->getResult());
                $totalPages = ceil($totalCount / $limit);

                $this->logger->debug('Mission query results calculated', [
                    'mentor_id' => $mentor->getId(),
                    'total_count' => $totalCount,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                ]);

                // Apply pagination
                $missions = $queryBuilder
                    ->setFirstResult(($page - 1) * $limit)
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult()
                ;

                $this->logger->debug('Missions retrieved successfully', [
                    'mentor_id' => $mentor->getId(),
                    'missions_count' => count($missions),
                    'mission_ids' => array_map(fn($m) => $m->getId(), $missions),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Database error while retrieving missions', [
                    'mentor_id' => $mentor->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                    'query_parameters' => [
                        'status' => $status,
                        'complexity' => $complexity,
                        'term' => $term,
                        'page' => $page,
                    ],
                ]);
                
                $this->addFlash('error', 'Erreur lors de la récupération des missions. Veuillez réessayer.');
                
                return $this->render('mentor/missions/index.html.twig', [
                    'missions' => [],
                    'mentor' => $mentor,
                    'stats' => [],
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_count' => 0,
                    'filters' => [
                        'status' => $status,
                        'complexity' => $complexity,
                        'term' => $term,
                    ],
                    'complexity_options' => CompanyMission::COMPLEXITY_LEVELS,
                    'term_options' => CompanyMission::TERMS,
                    'page_title' => 'Mes Missions',
                ]);
            }

            try {
                // Get statistics for the mentor
                $stats = $this->missionService->getMentorMissionStats($mentor);
                
                $this->logger->debug('Mission statistics retrieved', [
                    'mentor_id' => $mentor->getId(),
                    'stats' => $stats,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error retrieving mentor mission statistics', [
                    'mentor_id' => $mentor->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                ]);
                
                $stats = []; // Fallback to empty stats
                $this->addFlash('warning', 'Les statistiques ne peuvent pas être affichées pour le moment.');
            }

            $this->logger->info('Mission index page rendered successfully', [
                'mentor_id' => $mentor->getId(),
                'missions_displayed' => count($missions),
                'total_missions' => $totalCount,
                'page' => $page,
                'filters_applied' => array_filter([
                    'status' => $status !== 'all' ? $status : null,
                    'complexity' => $complexity !== 'all' ? $complexity : null,
                    'term' => $term !== 'all' ? $term : null,
                ]),
            ]);

            return $this->render('mentor/missions/index.html.twig', [
                'missions' => $missions,
                'mentor' => $mentor,
                'stats' => $stats,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_count' => $totalCount,
                'filters' => [
                    'status' => $status,
                    'complexity' => $complexity,
                    'term' => $term,
                ],
                'complexity_options' => CompanyMission::COMPLEXITY_LEVELS,
                'term_options' => CompanyMission::TERMS,
                'page_title' => 'Mes Missions',
            ]);
        } catch (Exception $e) {
            $user = $this->getUser();
            $this->logger->critical('Critical error in mission index controller', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'request_uri' => $request->getRequestUri(),
                'user_id' => $user instanceof Mentor ? $user->getId() : null,
                'session_id' => $request->getSession()->getId(),
            ]);
            
            $this->addFlash('error', 'Une erreur critique est survenue. Veuillez contacter l\'administrateur.');
            
            // Return minimal safe response
            return $this->render('mentor/missions/index.html.twig', [
                'missions' => [],
                'mentor' => $this->getUser(),
                'stats' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_count' => 0,
                'filters' => [
                    'status' => 'all',
                    'complexity' => 'all',
                    'term' => 'all',
                ],
                'complexity_options' => CompanyMission::COMPLEXITY_LEVELS,
                'term_options' => CompanyMission::TERMS,
                'page_title' => 'Mes Missions',
            ]);
        }
    }

    /**
     * Show mission details.
     */
    #[Route('/{id}', name: 'mentor_missions_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(CompanyMission $mission): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor accessing mission details', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mission_id' => $mission->getId(),
                'mission_title' => $mission->getTitle(),
            ]);

            // Security check: ensure mentor owns this mission
            if ($mission->getSupervisor() !== $mentor) {
                $this->logger->warning('Unauthorized access attempt to mission', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                    'mission_id' => $mission->getId(),
                    'mission_supervisor_id' => $mission->getSupervisor()?->getId(),
                    'attempted_access' => 'show',
                ]);
                
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette mission.');
            }

            try {
                // Get mission statistics
                $stats = [
                    'total_assignments' => $mission->getAssignments()->count(),
                    'active_assignments' => $mission->getActiveAssignmentsCount(),
                    'completed_assignments' => $mission->getCompletedAssignmentsCount(),
                    'complexity_level' => $mission->getComplexityLabel(),
                    'term_type' => $mission->getTermLabel(),
                ];

                $this->logger->debug('Mission statistics calculated', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'stats' => $stats,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error calculating mission statistics', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                ]);
                
                // Provide fallback stats
                $stats = [
                    'total_assignments' => 0,
                    'active_assignments' => 0,
                    'completed_assignments' => 0,
                    'complexity_level' => 'Non disponible',
                    'term_type' => 'Non disponible',
                ];
                
                $this->addFlash('warning', 'Certaines statistiques ne peuvent pas être affichées.');
            }

            $this->logger->info('Mission details displayed successfully', [
                'mentor_id' => $mentor->getId(),
                'mission_id' => $mission->getId(),
                'mission_title' => $mission->getTitle(),
                'mission_complexity' => $mission->getComplexity(),
                'mission_active' => $mission->isActive(),
            ]);

            return $this->render('mentor/missions/show.html.twig', [
                'mission' => $mission,
                'mentor' => $mentor,
                'stats' => $stats,
                'page_title' => $mission->getTitle(),
            ]);
        } catch (Exception $e) {
            $user = $this->getUser();
            $this->logger->critical('Critical error in mission show controller', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $user instanceof Mentor ? $user->getId() : null,
            ]);
            
            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage de la mission.');
            
            return $this->redirectToRoute('mentor_missions_index');
        }
    }

    /**
     * Create a new mission.
     */
    #[Route('/create', name: 'mentor_missions_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor accessing mission creation', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'request_method' => $request->getMethod(),
                'request_uri' => $request->getRequestUri(),
            ]);

            $mission = new CompanyMission();
            $form = $this->createForm(CompanyMissionType::class, $mission);
            
            try {
                $form->handleRequest($request);
                
                $this->logger->debug('Mission creation form processed', [
                    'mentor_id' => $mentor->getId(),
                    'form_submitted' => $form->isSubmitted(),
                    'form_valid' => $form->isValid(),
                    'request_method' => $request->getMethod(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error processing mission creation form', [
                    'mentor_id' => $mentor->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                    'form_data' => $request->request->all(),
                ]);
                
                $this->addFlash('error', 'Erreur lors du traitement du formulaire. Veuillez vérifier vos données.');
                
                return $this->render('mentor/missions/create.html.twig', [
                    'form' => $form,
                    'mentor' => $mentor,
                    'page_title' => 'Créer une Mission',
                ]);
            }

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    // Set the mentor as supervisor
                    $mission->setSupervisor($mentor);

                    $missionData = [
                        'title' => $mission->getTitle(),
                        'description' => $mission->getDescription(),
                        'context' => $mission->getContext(),
                        'objectives' => $mission->getObjectives(),
                        'requiredSkills' => $mission->getRequiredSkills(),
                        'skillsToAcquire' => $mission->getSkillsToAcquire(),
                        'duration' => $mission->getDuration(),
                        'complexity' => $mission->getComplexity(),
                        'term' => $mission->getTerm(),
                        'department' => $mission->getDepartment(),
                        'orderIndex' => $mission->getOrderIndex(),
                        'prerequisites' => $mission->getPrerequisites(),
                        'evaluationCriteria' => $mission->getEvaluationCriteria(),
                    ];

                    $this->logger->info('Attempting to create new mission', [
                        'mentor_id' => $mentor->getId(),
                        'mission_data' => [
                            'title' => $missionData['title'],
                            'complexity' => $missionData['complexity'],
                            'term' => $missionData['term'],
                            'duration' => $missionData['duration'],
                            'department' => $missionData['department'],
                        ],
                    ]);

                    // Use the service to create the mission (handles business logic)
                    $createdMission = $this->missionService->createMission($missionData, $mentor);

                    $this->logger->info('Mission created successfully', [
                        'mentor_id' => $mentor->getId(),
                        'created_mission_id' => $createdMission->getId(),
                        'mission_title' => $createdMission->getTitle(),
                        'mission_complexity' => $createdMission->getComplexity(),
                        'creation_timestamp' => $createdMission->getCreatedAt()->format('Y-m-d H:i:s'),
                    ]);

                    $this->addFlash('success', 'Mission créée avec succès !');

                    return $this->redirectToRoute('mentor_missions_show', ['id' => $createdMission->getId()]);
                } catch (Exception $e) {
                    $this->logger->error('Error creating mission via service', [
                        'mentor_id' => $mentor->getId(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'stack_trace' => $e->getTraceAsString(),
                        'mission_data' => [
                            'title' => $mission->getTitle(),
                            'complexity' => $mission->getComplexity(),
                            'term' => $mission->getTerm(),
                        ],
                    ]);
                    
                    $this->addFlash('error', 'Erreur lors de la création de la mission : ' . $e->getMessage());
                }
            }

            return $this->render('mentor/missions/create.html.twig', [
                'form' => $form,
                'mentor' => $mentor,
                'page_title' => 'Créer une Mission',
            ]);
        } catch (Exception $e) {
            $user = $this->getUser();
            $this->logger->critical('Critical error in mission create controller', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'request_uri' => $request->getRequestUri(),
                'request_method' => $request->getMethod(),
                'user_id' => $user instanceof Mentor ? $user->getId() : null,
                'session_id' => $request->getSession()->getId(),
            ]);
            
            $this->addFlash('error', 'Une erreur critique est survenue lors de la création de la mission.');
            
            return $this->redirectToRoute('mentor_missions_index');
        }
    }

    /**
     * Edit an existing mission.
     */
    #[Route('/{id}/edit', name: 'mentor_missions_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, CompanyMission $mission): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor accessing mission edit', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mission_id' => $mission->getId(),
                'mission_title' => $mission->getTitle(),
                'request_method' => $request->getMethod(),
            ]);

            // Security check: ensure mentor owns this mission
            if ($mission->getSupervisor() !== $mentor) {
                $this->logger->warning('Unauthorized access attempt to edit mission', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                    'mission_id' => $mission->getId(),
                    'mission_supervisor_id' => $mission->getSupervisor()?->getId(),
                    'attempted_access' => 'edit',
                ]);
                
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette mission.');
            }

            try {
                // Check if mission can be edited (no active assignments)
                $activeAssignmentsCount = $mission->getActiveAssignmentsCount();
                
                $this->logger->debug('Checking if mission can be edited', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'active_assignments_count' => $activeAssignmentsCount,
                ]);
                
                if ($activeAssignmentsCount > 0) {
                    $this->logger->warning('Attempt to edit mission with active assignments', [
                        'mentor_id' => $mentor->getId(),
                        'mission_id' => $mission->getId(),
                        'active_assignments_count' => $activeAssignmentsCount,
                    ]);
                    
                    $this->addFlash('warning', 'Cette mission ne peut pas être modifiée car elle a des assignations actives.');

                    return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
                }
            } catch (Exception $e) {
                $this->logger->error('Error checking mission edit eligibility', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                ]);
                
                $this->addFlash('error', 'Impossible de vérifier si la mission peut être modifiée.');
                return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
            }

            $form = $this->createForm(CompanyMissionType::class, $mission);
            
            try {
                $form->handleRequest($request);
                
                $this->logger->debug('Mission edit form processed', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'form_submitted' => $form->isSubmitted(),
                    'form_valid' => $form->isValid(),
                    'request_method' => $request->getMethod(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error processing mission edit form', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                    'form_data' => $request->request->all(),
                ]);
                
                $this->addFlash('error', 'Erreur lors du traitement du formulaire. Veuillez vérifier vos données.');
                
                return $this->render('mentor/missions/edit.html.twig', [
                    'form' => $form,
                    'mission' => $mission,
                    'mentor' => $mentor,
                    'page_title' => 'Modifier ' . $mission->getTitle(),
                ]);
            }

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $updateData = [
                        'title' => $mission->getTitle(),
                        'description' => $mission->getDescription(),
                        'context' => $mission->getContext(),
                        'objectives' => $mission->getObjectives(),
                        'requiredSkills' => $mission->getRequiredSkills(),
                        'skillsToAcquire' => $mission->getSkillsToAcquire(),
                        'duration' => $mission->getDuration(),
                        'complexity' => $mission->getComplexity(),
                        'term' => $mission->getTerm(),
                        'department' => $mission->getDepartment(),
                        'orderIndex' => $mission->getOrderIndex(),
                        'prerequisites' => $mission->getPrerequisites(),
                        'evaluationCriteria' => $mission->getEvaluationCriteria(),
                    ];

                    $this->logger->info('Attempting to update mission', [
                        'mentor_id' => $mentor->getId(),
                        'mission_id' => $mission->getId(),
                        'update_data' => [
                            'title_changed' => $updateData['title'] !== $mission->getTitle(),
                            'complexity_changed' => $updateData['complexity'] !== $mission->getComplexity(),
                            'term_changed' => $updateData['term'] !== $mission->getTerm(),
                        ],
                    ]);

                    // Use the service to update the mission
                    $this->missionService->updateMission($mission, $updateData);

                    $this->logger->info('Mission updated successfully', [
                        'mentor_id' => $mentor->getId(),
                        'mission_id' => $mission->getId(),
                        'mission_title' => $mission->getTitle(),
                        'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ]);

                    $this->addFlash('success', 'Mission mise à jour avec succès !');

                    return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
                } catch (Exception $e) {
                    $this->logger->error('Error updating mission via service', [
                        'mentor_id' => $mentor->getId(),
                        'mission_id' => $mission->getId(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'stack_trace' => $e->getTraceAsString(),
                        'mission_title' => $mission->getTitle(),
                    ]);
                    
                    $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
                }
            }

            return $this->render('mentor/missions/edit.html.twig', [
                'form' => $form,
                'mission' => $mission,
                'mentor' => $mentor,
                'page_title' => 'Modifier ' . $mission->getTitle(),
            ]);
        } catch (Exception $e) {
            $user = $this->getUser();
            $this->logger->critical('Critical error in mission edit controller', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'request_uri' => $request->getRequestUri(),
                'request_method' => $request->getMethod(),
                'user_id' => $user instanceof Mentor ? $user->getId() : null,
            ]);
            
            $this->addFlash('error', 'Une erreur critique est survenue lors de la modification de la mission.');
            
            return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
        }
    }

    /**
     * Toggle mission active status.
     */
    #[Route('/{id}/toggle-status', name: 'mentor_missions_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(CompanyMission $mission): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor attempting to toggle mission status', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mission_id' => $mission->getId(),
                'mission_title' => $mission->getTitle(),
                'current_status' => $mission->isActive(),
            ]);

            // Security check: ensure mentor owns this mission
            if ($mission->getSupervisor() !== $mentor) {
                $this->logger->warning('Unauthorized access attempt to toggle mission status', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                    'mission_id' => $mission->getId(),
                    'mission_supervisor_id' => $mission->getSupervisor()?->getId(),
                    'attempted_access' => 'toggle_status',
                ]);
                
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette mission.');
            }

            try {
                $oldStatus = $mission->isActive();
                $newStatus = !$oldStatus;
                
                $this->logger->debug('Changing mission status', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
                
                $mission->setIsActive($newStatus);
                $this->entityManager->flush();

                $this->logger->info('Mission status changed successfully', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'mission_title' => $mission->getTitle(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'changed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);

                $statusText = $newStatus ? 'activée' : 'désactivée';
                $this->addFlash('success', "Mission {$statusText} avec succès !");
            } catch (Exception $e) {
                $this->logger->error('Database error while toggling mission status', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                    'current_status' => $mission->isActive(),
                ]);
                
                $this->addFlash('error', 'Erreur lors du changement de statut : ' . $e->getMessage());
            }

            return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
        } catch (Exception $e) {
            $user = $this->getUser();
            $this->logger->critical('Critical error in mission toggle status controller', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $user instanceof Mentor ? $user->getId() : null,
            ]);
            
            $this->addFlash('error', 'Une erreur critique est survenue lors du changement de statut.');
            
            return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
        }
    }

    /**
     * Delete a mission (soft delete by deactivating).
     */
    #[Route('/{id}/delete', name: 'mentor_missions_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(CompanyMission $mission): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor attempting to delete mission', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mission_id' => $mission->getId(),
                'mission_title' => $mission->getTitle(),
                'mission_active' => $mission->isActive(),
            ]);

            // Security check: ensure mentor owns this mission
            if ($mission->getSupervisor() !== $mentor) {
                $this->logger->warning('Unauthorized access attempt to delete mission', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                    'mission_id' => $mission->getId(),
                    'mission_supervisor_id' => $mission->getSupervisor()?->getId(),
                    'attempted_access' => 'delete',
                ]);
                
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette mission.');
            }

            try {
                // Check if mission can be deleted (no assignments)
                $assignmentsCount = $mission->getAssignments()->count();
                
                $this->logger->debug('Checking if mission can be deleted', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'assignments_count' => $assignmentsCount,
                ]);
                
                if ($assignmentsCount > 0) {
                    $this->logger->warning('Attempt to delete mission with existing assignments', [
                        'mentor_id' => $mentor->getId(),
                        'mission_id' => $mission->getId(),
                        'assignments_count' => $assignmentsCount,
                    ]);
                    
                    $this->addFlash('error', 'Cette mission ne peut pas être supprimée car elle a des assignations.');

                    return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
                }
            } catch (Exception $e) {
                $this->logger->error('Error checking mission delete eligibility', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                ]);
                
                $this->addFlash('error', 'Impossible de vérifier si la mission peut être supprimée.');
                return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
            }

            try {
                $missionTitle = $mission->getTitle();
                $missionId = $mission->getId();
                
                $this->logger->info('Deleting mission from database', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $missionId,
                    'mission_title' => $missionTitle,
                ]);
                
                $this->entityManager->remove($mission);
                $this->entityManager->flush();

                $this->logger->info('Mission deleted successfully', [
                    'mentor_id' => $mentor->getId(),
                    'deleted_mission_id' => $missionId,
                    'deleted_mission_title' => $missionTitle,
                    'deleted_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);

                $this->addFlash('success', "Mission \"{$missionTitle}\" supprimée avec succès !");
            } catch (Exception $e) {
                $this->logger->error('Database error while deleting mission', [
                    'mentor_id' => $mentor->getId(),
                    'mission_id' => $mission->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                ]);
                
                $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());

                return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
            }

            return $this->redirectToRoute('mentor_missions_index');
        } catch (Exception $e) {
            $user = $this->getUser();
            $this->logger->critical('Critical error in mission delete controller', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $user instanceof Mentor ? $user->getId() : null,
            ]);
            
            $this->addFlash('error', 'Une erreur critique est survenue lors de la suppression.');
            
            return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
        }
    }

    /**
     * Get recommended next missions for progression.
     */
    #[Route('/recommendations', name: 'mentor_missions_recommendations', methods: ['GET'])]
    public function recommendations(): Response
    {
        try {
            /** @var Mentor $mentor */
            $mentor = $this->getUser();

            $this->logger->info('Mentor accessing mission recommendations', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
            ]);

            try {
                // Get mentor's missions that need attention
                $recommendations = $this->missionService->getMissionsRequiringAttention($mentor);
                
                $this->logger->debug('Mission recommendations retrieved', [
                    'mentor_id' => $mentor->getId(),
                    'recommendations_count' => count($recommendations),
                    'recommendation_types' => array_keys($recommendations),
                ]);
                
                $this->logger->info('Mission recommendations displayed successfully', [
                    'mentor_id' => $mentor->getId(),
                    'total_recommendations' => array_sum(array_map('count', $recommendations)),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error retrieving mission recommendations', [
                    'mentor_id' => $mentor->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                ]);
                
                $recommendations = [];
                $this->addFlash('warning', 'Les recommandations ne peuvent pas être affichées pour le moment.');
            }

            return $this->render('mentor/missions/recommendations.html.twig', [
                'recommendations' => $recommendations,
                'mentor' => $mentor,
                'page_title' => 'Recommandations de Missions',
            ]);
        } catch (Exception $e) {
            $user = $this->getUser();
            $this->logger->critical('Critical error in mission recommendations controller', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $user instanceof Mentor ? $user->getId() : null,
            ]);
            
            $this->addFlash('error', 'Une erreur est survenue lors du chargement des recommandations.');
            
            return $this->redirectToRoute('mentor_missions_index');
        }
    }
}
