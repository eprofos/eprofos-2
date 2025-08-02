<?php

declare(strict_types=1);

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\CompanyMission;
use App\Entity\Alternance\MissionAssignment;
use App\Form\Alternance\CompanyMissionType;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Repository\Alternance\CompanyMissionRepository;
use App\Repository\Alternance\MissionAssignmentRepository;
use App\Service\Alternance\CompanyMissionService;
use App\Service\Alternance\MissionAssignmentService;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/alternance/missions')]
#[IsGranted('ROLE_ADMIN')]
class MissionController extends AbstractController
{
    public function __construct(
        private CompanyMissionRepository $missionRepository,
        private MissionAssignmentRepository $assignmentRepository,
        private AlternanceContractRepository $contractRepository,
        private CompanyMissionService $missionService,
        private MissionAssignmentService $assignmentService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_alternance_mission_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('MissionController: Starting mission index listing', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_parameters' => $request->query->all(),
        ]);

        try {
            $page = $request->query->getInt('page', 1);
            $search = $request->query->get('search', '');
            $status = $request->query->get('status', '');
            $complexity = $request->query->get('complexity', '');
            $perPage = 20;

            $filters = [
                'search' => $search,
                'status' => $status,
                'complexity' => $complexity,
            ];

            $this->logger->debug('MissionController: Applied filters for mission listing', [
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            $missions = $this->missionRepository->findPaginatedMissions($filters, $page, $perPage);
            $totalMissions = $this->missionRepository->countFilteredMissions($filters);
            $totalPages = ceil($totalMissions / $perPage);

            $this->logger->debug('MissionController: Retrieved missions from repository', [
                'missions_count' => count($missions),
                'total_missions' => $totalMissions,
                'total_pages' => $totalPages,
            ]);

            // Get mission statistics
            $statistics = $this->getMissionStatistics();

            $this->logger->info('MissionController: Successfully loaded mission index', [
                'missions_count' => count($missions),
                'total_missions' => $totalMissions,
                'statistics' => $statistics,
            ]);

            return $this->render('admin/alternance/mission/index.html.twig', [
                'missions' => $missions,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'filters' => $filters,
                'statistics' => $statistics,
            ]);
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during mission index listing', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors du chargement des missions.');

            return $this->render('admin/alternance/mission/index.html.twig', [
                'missions' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'filters' => [],
                'statistics' => [],
            ]);
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during mission index listing', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du chargement des missions.');

            return $this->render('admin/alternance/mission/index.html.twig', [
                'missions' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'filters' => [],
                'statistics' => [],
            ]);
        }
    }

    #[Route('/new', name: 'admin_alternance_mission_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('MissionController: Starting new mission creation', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod(),
        ]);

        try {
            $mission = new CompanyMission();
            $form = $this->createForm(CompanyMissionType::class, $mission);
            $form->handleRequest($request);

            $this->logger->debug('MissionController: Form created and request handled', [
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
            ]);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('MissionController: Form submitted and valid, persisting new mission', [
                    'mission_title' => $mission->getTitle(),
                    'mission_complexity' => $mission->getComplexity(),
                    'mission_duration' => $mission->getDuration(),
                ]);

                $this->entityManager->persist($mission);
                $this->entityManager->flush();

                $this->logger->info('MissionController: New mission created successfully', [
                    'mission_id' => $mission->getId(),
                    'mission_title' => $mission->getTitle(),
                    'created_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Mission créée avec succès.');

                return $this->redirectToRoute('admin_alternance_mission_show', [
                    'id' => $mission->getId(),
                ]);
            }

            if ($form->isSubmitted() && !$form->isValid()) {
                $this->logger->warning('MissionController: Form submitted but invalid', [
                    'form_errors' => (string) $form->getErrors(true),
                ]);
            }

            return $this->render('admin/alternance/mission/new.html.twig', [
                'mission' => $mission,
                'form' => $form,
            ]);
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during mission creation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la création de la mission : ' . $e->getMessage());

            $mission = new CompanyMission();
            $form = $this->createForm(CompanyMissionType::class, $mission);

            return $this->render('admin/alternance/mission/new.html.twig', [
                'mission' => $mission,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during mission creation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de la création de la mission : ' . $e->getMessage());

            $mission = new CompanyMission();
            $form = $this->createForm(CompanyMissionType::class, $mission);

            return $this->render('admin/alternance/mission/new.html.twig', [
                'mission' => $mission,
                'form' => $form,
            ]);
        }
    }

    #[Route('/{id}', name: 'admin_alternance_mission_show', methods: ['GET'])]
    public function show(CompanyMission $mission): Response
    {
        $this->logger->info('MissionController: Displaying mission details', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            // Get mission assignments
            $assignments = $this->assignmentRepository->findBy(['mission' => $mission]);

            $this->logger->debug('MissionController: Retrieved mission assignments', [
                'mission_id' => $mission->getId(),
                'assignments_count' => count($assignments),
            ]);

            // Get mission progress data
            $progressData = $this->missionService->getMissionProgressData($mission);

            $this->logger->debug('MissionController: Retrieved mission progress data', [
                'mission_id' => $mission->getId(),
                'progress_data_keys' => array_keys($progressData),
            ]);

            $this->logger->info('MissionController: Successfully loaded mission details', [
                'mission_id' => $mission->getId(),
                'assignments_count' => count($assignments),
            ]);

            return $this->render('admin/alternance/mission/show.html.twig', [
                'mission' => $mission,
                'assignments' => $assignments,
                'progress_data' => $progressData,
            ]);
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during mission show', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors du chargement des détails de la mission.');

            return $this->render('admin/alternance/mission/show.html.twig', [
                'mission' => $mission,
                'assignments' => [],
                'progress_data' => [],
            ]);
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during mission show', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du chargement des détails de la mission.');

            return $this->render('admin/alternance/mission/show.html.twig', [
                'mission' => $mission,
                'assignments' => [],
                'progress_data' => [],
            ]);
        }
    }

    #[Route('/{id}/edit', name: 'admin_alternance_mission_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CompanyMission $mission): Response
    {
        $this->logger->info('MissionController: Starting mission edit', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod(),
        ]);

        try {
            $originalData = [
                'title' => $mission->getTitle(),
                'description' => $mission->getDescription(),
                'complexity' => $mission->getComplexity(),
                'duration' => $mission->getDuration(),
                'is_active' => $mission->isActive(),
            ];

            $form = $this->createForm(CompanyMissionType::class, $mission);
            $form->handleRequest($request);

            $this->logger->debug('MissionController: Form created and request handled for edit', [
                'mission_id' => $mission->getId(),
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
            ]);

            if ($form->isSubmitted() && $form->isValid()) {
                $updatedData = [
                    'title' => $mission->getTitle(),
                    'description' => $mission->getDescription(),
                    'complexity' => $mission->getComplexity(),
                    'duration' => $mission->getDuration(),
                    'is_active' => $mission->isActive(),
                ];

                $this->logger->info('MissionController: Form submitted and valid, updating mission', [
                    'mission_id' => $mission->getId(),
                    'original_data' => $originalData,
                    'updated_data' => $updatedData,
                    'changes' => array_diff_assoc($updatedData, $originalData),
                ]);

                $this->entityManager->flush();

                $this->logger->info('MissionController: Mission updated successfully', [
                    'mission_id' => $mission->getId(),
                    'updated_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Mission modifiée avec succès.');

                return $this->redirectToRoute('admin_alternance_mission_show', [
                    'id' => $mission->getId(),
                ]);
            }

            if ($form->isSubmitted() && !$form->isValid()) {
                $this->logger->warning('MissionController: Form submitted but invalid during edit', [
                    'mission_id' => $mission->getId(),
                    'form_errors' => (string) $form->getErrors(true),
                ]);
            }

            return $this->render('admin/alternance/mission/edit.html.twig', [
                'mission' => $mission,
                'form' => $form,
            ]);
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during mission edit', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la modification de la mission : ' . $e->getMessage());

            $form = $this->createForm(CompanyMissionType::class, $mission);

            return $this->render('admin/alternance/mission/edit.html.twig', [
                'mission' => $mission,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during mission edit', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de la modification de la mission : ' . $e->getMessage());

            $form = $this->createForm(CompanyMissionType::class, $mission);

            return $this->render('admin/alternance/mission/edit.html.twig', [
                'mission' => $mission,
                'form' => $form,
            ]);
        }
    }

    #[Route('/{id}/assignments', name: 'admin_alternance_mission_assignments', methods: ['GET'])]
    public function assignments(CompanyMission $mission): Response
    {
        $this->logger->info('MissionController: Displaying mission assignments', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $assignments = $this->assignmentRepository->findBy(['mission' => $mission], ['createdAt' => 'DESC']);

            $this->logger->debug('MissionController: Retrieved mission assignments', [
                'mission_id' => $mission->getId(),
                'assignments_count' => count($assignments),
                'assignment_statuses' => array_count_values(array_map(static fn ($a) => $a->getStatus(), $assignments)),
            ]);

            $this->logger->info('MissionController: Successfully loaded mission assignments', [
                'mission_id' => $mission->getId(),
                'assignments_count' => count($assignments),
            ]);

            return $this->render('admin/alternance/mission/assignments.html.twig', [
                'mission' => $mission,
                'assignments' => $assignments,
            ]);
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during assignments retrieval', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors du chargement des assignations.');

            return $this->render('admin/alternance/mission/assignments.html.twig', [
                'mission' => $mission,
                'assignments' => [],
            ]);
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during assignments retrieval', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du chargement des assignations.');

            return $this->render('admin/alternance/mission/assignments.html.twig', [
                'mission' => $mission,
                'assignments' => [],
            ]);
        }
    }

    #[Route('/{id}/assign', name: 'admin_alternance_mission_assign', methods: ['POST'])]
    public function assign(Request $request, CompanyMission $mission): Response
    {
        $contractId = $request->request->get('contract_id');

        $this->logger->info('MissionController: Starting mission assignment', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
            'contract_id' => $contractId,
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if (!$contractId) {
                $this->logger->warning('MissionController: Missing contract ID for mission assignment', [
                    'mission_id' => $mission->getId(),
                    'request_data' => $request->request->all(),
                ]);

                $this->addFlash('error', 'ID du contrat manquant.');

                return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
            }

            $contract = $this->contractRepository->find($contractId);

            if (!$contract) {
                $this->logger->warning('MissionController: Contract not found for mission assignment', [
                    'mission_id' => $mission->getId(),
                    'contract_id' => $contractId,
                ]);

                $this->addFlash('error', 'Contrat non trouvé.');

                return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
            }

            $this->logger->debug('MissionController: Contract found, proceeding with assignment', [
                'mission_id' => $mission->getId(),
                'contract_id' => $contract->getId(),
                'student_name' => $contract->getStudent()?->getFullName(),
                'mentor_name' => $contract->getMentor()?->getFullName(),
            ]);

            $assignment = $this->assignmentService->assignMissionToContract($mission, $contract);

            $this->logger->info('MissionController: Mission assigned successfully', [
                'mission_id' => $mission->getId(),
                'contract_id' => $contract->getId(),
                'assignment_id' => $assignment->getId(),
                'assigned_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Mission assignée avec succès.');
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during mission assignment', [
                'mission_id' => $mission->getId(),
                'contract_id' => $contractId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de l\'assignation : ' . $e->getMessage());
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during mission assignment', [
                'mission_id' => $mission->getId(),
                'contract_id' => $contractId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'assignation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_alternance_mission_delete', methods: ['POST'])]
    public function delete(Request $request, CompanyMission $mission): Response
    {
        $this->logger->info('MissionController: Starting mission deletion', [
            'mission_id' => $mission->getId(),
            'mission_title' => $mission->getTitle(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if ($this->isCsrfTokenValid('delete' . $mission->getId(), $request->request->get('_token'))) {
                $this->logger->debug('MissionController: CSRF token valid, checking for assignments', [
                    'mission_id' => $mission->getId(),
                ]);

                // Check if mission has assignments
                $assignments = $this->assignmentRepository->findBy(['mission' => $mission]);

                if (!empty($assignments)) {
                    $this->logger->warning('MissionController: Cannot delete mission with existing assignments', [
                        'mission_id' => $mission->getId(),
                        'assignments_count' => count($assignments),
                        'assignment_ids' => array_map(static fn ($a) => $a->getId(), $assignments),
                    ]);

                    $this->addFlash('error', 'Impossible de supprimer une mission avec des assignations.');

                    return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
                }

                $missionData = [
                    'id' => $mission->getId(),
                    'title' => $mission->getTitle(),
                    'description' => $mission->getDescription(),
                    'complexity' => $mission->getComplexity(),
                ];

                $this->logger->info('MissionController: Proceeding with mission deletion', [
                    'mission_data' => $missionData,
                ]);

                $this->entityManager->remove($mission);
                $this->entityManager->flush();

                $this->logger->info('MissionController: Mission deleted successfully', [
                    'deleted_mission' => $missionData,
                    'deleted_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Mission supprimée avec succès.');
            } else {
                $this->logger->warning('MissionController: Invalid CSRF token for mission deletion', [
                    'mission_id' => $mission->getId(),
                    'provided_token' => $request->request->get('_token'),
                    'user_email' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');

                return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
            }
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during mission deletion', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la suppression : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during mission deletion', [
                'mission_id' => $mission->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
        }

        return $this->redirectToRoute('admin_alternance_mission_index');
    }

    #[Route('/assignments/{id}/status', name: 'admin_alternance_mission_assignment_status', methods: ['POST'])]
    public function changeAssignmentStatus(Request $request, MissionAssignment $assignment): Response
    {
        $newStatus = $request->request->get('status');
        $oldStatus = $assignment->getStatus();

        $this->logger->info('MissionController: Starting assignment status change', [
            'assignment_id' => $assignment->getId(),
            'mission_id' => $assignment->getMission()->getId(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if (!in_array($newStatus, ['assigned', 'in_progress', 'completed', 'cancelled'], true)) {
                $this->logger->warning('MissionController: Invalid status provided for assignment', [
                    'assignment_id' => $assignment->getId(),
                    'provided_status' => $newStatus,
                    'valid_statuses' => ['assigned', 'in_progress', 'completed', 'cancelled'],
                ]);

                $this->addFlash('error', 'Statut invalide.');

                return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $assignment->getMission()->getId()]);
            }

            $this->logger->debug('MissionController: Valid status provided, updating assignment', [
                'assignment_id' => $assignment->getId(),
                'status_change' => ['from' => $oldStatus, 'to' => $newStatus],
            ]);

            $assignment->setStatus($newStatus);
            // Note: setCompletedAt method doesn't exist on the entity
            // In a real implementation, you might need to add this field
            $this->entityManager->flush();

            $this->logger->info('MissionController: Assignment status updated successfully', [
                'assignment_id' => $assignment->getId(),
                'mission_id' => $assignment->getMission()->getId(),
                'status_change' => ['from' => $oldStatus, 'to' => $newStatus],
                'updated_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Statut de l\'assignation modifié avec succès.');
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during assignment status change', [
                'assignment_id' => $assignment->getId(),
                'mission_id' => $assignment->getMission()->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors du changement de statut : ' . $e->getMessage());
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during assignment status change', [
                'assignment_id' => $assignment->getId(),
                'mission_id' => $assignment->getMission()->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors du changement de statut : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $assignment->getMission()->getId()]);
    }

    #[Route('/bulk/actions', name: 'admin_alternance_mission_bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): Response
    {
        $missionIds = $request->request->all('mission_ids');
        $action = $request->request->get('action');

        $this->logger->info('MissionController: Starting bulk actions', [
            'mission_ids' => $missionIds,
            'action' => $action,
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        if (empty($missionIds) || !$action) {
            $this->logger->warning('MissionController: Missing data for bulk actions', [
                'mission_ids_count' => count($missionIds),
                'action' => $action,
            ]);

            $this->addFlash('error', 'Veuillez sélectionner des missions et une action.');

            return $this->redirectToRoute('admin_alternance_mission_index');
        }

        try {
            $missions = $this->missionRepository->findBy(['id' => $missionIds]);
            $processed = 0;

            $this->logger->debug('MissionController: Found missions for bulk action', [
                'requested_ids' => $missionIds,
                'found_missions' => count($missions),
                'action' => $action,
            ]);

            foreach ($missions as $mission) {
                $this->logger->debug('MissionController: Processing bulk action for mission', [
                    'mission_id' => $mission->getId(),
                    'mission_title' => $mission->getTitle(),
                    'action' => $action,
                ]);

                switch ($action) {
                    case 'activate':
                        $mission->setIsActive(true);
                        $processed++;
                        $this->logger->debug('MissionController: Activated mission', [
                            'mission_id' => $mission->getId(),
                        ]);
                        break;

                    case 'deactivate':
                        $mission->setIsActive(false);
                        $processed++;
                        $this->logger->debug('MissionController: Deactivated mission', [
                            'mission_id' => $mission->getId(),
                        ]);
                        break;

                    default:
                        $this->logger->warning('MissionController: Unknown bulk action', [
                            'action' => $action,
                            'mission_id' => $mission->getId(),
                        ]);
                }
            }

            $this->entityManager->flush();

            $this->logger->info('MissionController: Bulk actions completed successfully', [
                'action' => $action,
                'processed_count' => $processed,
                'total_missions' => count($missions),
                'user_email' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', sprintf('%d mission(s) traitée(s) avec succès.', $processed));
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during bulk actions', [
                'mission_ids' => $missionIds,
                'action' => $action,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors du traitement : ' . $e->getMessage());
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during bulk actions', [
                'mission_ids' => $missionIds,
                'action' => $action,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors du traitement : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_mission_index');
    }

    #[Route('/export', name: 'admin_alternance_mission_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $filters = [
            'status' => $request->query->get('status', ''),
            'complexity' => $request->query->get('complexity', ''),
        ];

        $this->logger->info('MissionController: Starting mission export', [
            'format' => $format,
            'filters' => $filters,
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('MissionController: Retrieving missions for export', [
                'filters' => $filters,
            ]);

            $missions = $this->missionRepository->findForExport($filters);

            $this->logger->debug('MissionController: Retrieved missions for export', [
                'missions_count' => count($missions),
                'format' => $format,
            ]);

            $data = $this->exportMissions($missions, $format);

            $this->logger->info('MissionController: Export data generated successfully', [
                'format' => $format,
                'missions_count' => count($missions),
                'data_size' => strlen($data),
                'user_email' => $this->getUser()?->getUserIdentifier(),
            ]);

            $response = new Response($data);
            $response->headers->set('Content-Type', $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="missions_export.' . $format . '"');

            return $response;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('MissionController: Invalid export format', [
                'format' => $format,
                'error_message' => $e->getMessage(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Format d\'export non supporté : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_mission_index');
        } catch (DBALException $e) {
            $this->logger->error('MissionController: Database error during export', [
                'format' => $format,
                'filters' => $filters,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de l\'export : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_mission_index');
        } catch (Exception $e) {
            $this->logger->error('MissionController: Unexpected error during export', [
                'format' => $format,
                'filters' => $filters,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_mission_index');
        }
    }

    private function getMissionStatistics(): array
    {
        $this->logger->debug('MissionController: Calculating mission statistics');

        try {
            $totalMissions = $this->missionRepository->count([]);
            $activeMissions = $this->missionRepository->countActive();
            $completedAssignments = $this->assignmentRepository->countByStatus('completed');
            $inProgressAssignments = $this->assignmentRepository->countByStatus('in_progress');

            $statistics = [
                'total_missions' => $totalMissions,
                'active_missions' => $activeMissions,
                'completed_assignments' => $completedAssignments,
                'in_progress_assignments' => $inProgressAssignments,
                'completion_rate' => $this->calculateCompletionRate(),
                'average_duration' => $this->calculateAverageMissionDuration(),
                'complexity_distribution' => $this->missionRepository->getComplexityDistribution(),
                'recent_missions' => $this->missionRepository->findRecentMissions(5),
            ];

            $this->logger->debug('MissionController: Mission statistics calculated', [
                'statistics' => $statistics,
            ]);

            return $statistics;
        } catch (Exception $e) {
            $this->logger->error('MissionController: Error calculating mission statistics', [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty statistics to prevent breaking the page
            return [
                'total_missions' => 0,
                'active_missions' => 0,
                'completed_assignments' => 0,
                'in_progress_assignments' => 0,
                'completion_rate' => 0,
                'average_duration' => 0,
                'complexity_distribution' => [],
                'recent_missions' => [],
            ];
        }
    }

    private function calculateCompletionRate(): float
    {
        try {
            $totalAssignments = $this->assignmentRepository->count([]);
            $completedAssignments = $this->assignmentRepository->countByStatus('completed');

            $rate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 1) : 0;

            $this->logger->debug('MissionController: Calculated completion rate', [
                'total_assignments' => $totalAssignments,
                'completed_assignments' => $completedAssignments,
                'completion_rate' => $rate,
            ]);

            return $rate;
        } catch (Exception $e) {
            $this->logger->error('MissionController: Error calculating completion rate', [
                'error_message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private function calculateAverageMissionDuration(): float
    {
        try {
            // This would calculate based on actual assignment data
            // For now, return a placeholder
            $averageDuration = 14.5; // days

            $this->logger->debug('MissionController: Calculated average mission duration', [
                'average_duration' => $averageDuration,
            ]);

            return $averageDuration;
        } catch (Exception $e) {
            $this->logger->error('MissionController: Error calculating average mission duration', [
                'error_message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private function exportMissions(array $missions, string $format): string
    {
        $this->logger->debug('MissionController: Starting export data generation', [
            'format' => $format,
            'missions_count' => count($missions),
        ]);

        try {
            if ($format === 'csv') {
                $output = fopen('php://temp', 'r+');

                if (!$output) {
                    throw new Exception('Impossible de créer le fichier temporaire pour l\'export CSV');
                }

                // Headers
                $headers = [
                    'Titre',
                    'Description',
                    'Difficulté',
                    'Durée estimée',
                    'Statut',
                    'Date de création',
                ];

                fputcsv($output, $headers);

                $this->logger->debug('MissionController: CSV headers written', [
                    'headers' => $headers,
                ]);

                // Data
                $rowCount = 0;
                foreach ($missions as $mission) {
                    $row = [
                        $mission->getTitle(),
                        $mission->getDescription(),
                        $mission->getComplexityLabel(),
                        $mission->getDuration(),
                        $mission->isActive() ? 'Actif' : 'Inactif',
                        $mission->getCreatedAt()->format('d/m/Y'),
                    ];

                    fputcsv($output, $row);
                    $rowCount++;
                }

                $this->logger->debug('MissionController: CSV data written', [
                    'rows_written' => $rowCount,
                ]);

                rewind($output);
                $content = stream_get_contents($output);
                fclose($output);

                if ($content === false) {
                    throw new Exception('Erreur lors de la lecture du contenu CSV');
                }

                $this->logger->debug('MissionController: CSV export completed', [
                    'content_length' => strlen($content),
                ]);

                return $content;
            }

            throw new InvalidArgumentException("Format d'export non supporté: {$format}");
        } catch (Exception $e) {
            $this->logger->error('MissionController: Error during export data generation', [
                'format' => $format,
                'missions_count' => count($missions),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
