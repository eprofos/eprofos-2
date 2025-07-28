<?php

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\CompanyMission;
use App\Entity\Alternance\MissionAssignment;
use App\Form\Alternance\CompanyMissionType;
use App\Repository\Alternance\CompanyMissionRepository;
use App\Repository\Alternance\MissionAssignmentRepository;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Service\Alternance\CompanyMissionService;
use App\Service\Alternance\MissionAssignmentService;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_alternance_mission_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
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

        $missions = $this->missionRepository->findPaginatedMissions($filters, $page, $perPage);
        $totalPages = ceil($this->missionRepository->countFilteredMissions($filters) / $perPage);

        // Get mission statistics
        $statistics = $this->getMissionStatistics();

        return $this->render('admin/alternance/mission/index.html.twig', [
            'missions' => $missions,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/new', name: 'admin_alternance_mission_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $mission = new CompanyMission();
        $form = $this->createForm(CompanyMissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->persist($mission);
                $this->entityManager->flush();
                
                $this->addFlash('success', 'Mission créée avec succès.');

                return $this->redirectToRoute('admin_alternance_mission_show', [
                    'id' => $mission->getId()
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création de la mission : ' . $e->getMessage());
            }
        }

        return $this->render('admin/alternance/mission/new.html.twig', [
            'mission' => $mission,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_alternance_mission_show', methods: ['GET'])]
    public function show(CompanyMission $mission): Response
    {
        // Get mission assignments
        $assignments = $this->assignmentRepository->findBy(['mission' => $mission]);
        
        // Get mission progress data
        $progressData = $this->missionService->getMissionProgressData($mission);

        return $this->render('admin/alternance/mission/show.html.twig', [
            'mission' => $mission,
            'assignments' => $assignments,
            'progress_data' => $progressData,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_alternance_mission_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CompanyMission $mission): Response
    {
        $form = $this->createForm(CompanyMissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();
                $this->addFlash('success', 'Mission modifiée avec succès.');

                return $this->redirectToRoute('admin_alternance_mission_show', [
                    'id' => $mission->getId()
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification de la mission : ' . $e->getMessage());
            }
        }

        return $this->render('admin/alternance/mission/edit.html.twig', [
            'mission' => $mission,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/assignments', name: 'admin_alternance_mission_assignments', methods: ['GET'])]
    public function assignments(CompanyMission $mission): Response
    {
        $assignments = $this->assignmentRepository->findBy(['mission' => $mission], ['createdAt' => 'DESC']);

        return $this->render('admin/alternance/mission/assignments.html.twig', [
            'mission' => $mission,
            'assignments' => $assignments,
        ]);
    }

    #[Route('/{id}/assign', name: 'admin_alternance_mission_assign', methods: ['POST'])]
    public function assign(Request $request, CompanyMission $mission): Response
    {
        $contractId = $request->request->get('contract_id');
        $contract = $this->contractRepository->find($contractId);

        if (!$contract) {
            $this->addFlash('error', 'Contrat non trouvé.');
            return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
        }

        try {
            $assignment = $this->assignmentService->assignMissionToContract($mission, $contract);
            $this->addFlash('success', 'Mission assignée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'assignation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_alternance_mission_delete', methods: ['POST'])]
    public function delete(Request $request, CompanyMission $mission): Response
    {
        if ($this->isCsrfTokenValid('delete'.$mission->getId(), $request->request->get('_token'))) {
            try {
                // Check if mission has assignments
                $assignments = $this->assignmentRepository->findBy(['mission' => $mission]);
                if (!empty($assignments)) {
                    $this->addFlash('error', 'Impossible de supprimer une mission avec des assignations.');
                    return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $mission->getId()]);
                }

                $this->entityManager->remove($mission);
                $this->entityManager->flush();
                $this->addFlash('success', 'Mission supprimée avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_alternance_mission_index');
    }

    #[Route('/assignments/{id}/status', name: 'admin_alternance_mission_assignment_status', methods: ['POST'])]
    public function changeAssignmentStatus(Request $request, MissionAssignment $assignment): Response
    {
        $newStatus = $request->request->get('status');
        
        if (!in_array($newStatus, ['assigned', 'in_progress', 'completed', 'cancelled'])) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $assignment->getMission()->getId()]);
        }

        try {
            $assignment->setStatus($newStatus);
            // Note: setCompletedAt method doesn't exist on the entity
            // In a real implementation, you might need to add this field
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Statut de l\'assignation modifié avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du changement de statut : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_mission_show', ['id' => $assignment->getMission()->getId()]);
    }

    #[Route('/bulk/actions', name: 'admin_alternance_mission_bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): Response
    {
        $missionIds = $request->request->all('mission_ids');
        $action = $request->request->get('action');
        
        if (empty($missionIds) || !$action) {
            $this->addFlash('error', 'Veuillez sélectionner des missions et une action.');
            return $this->redirectToRoute('admin_alternance_mission_index');
        }

        try {
            $missions = $this->missionRepository->findBy(['id' => $missionIds]);
            $processed = 0;
            
            foreach ($missions as $mission) {
                switch ($action) {
                    case 'activate':
                        $mission->setIsActive(true);
                        $processed++;
                        break;
                    case 'deactivate':
                        $mission->setIsActive(false);
                        $processed++;
                        break;
                }
            }
            
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('%d mission(s) traitée(s) avec succès.', $processed));
        } catch (\Exception $e) {
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

        try {
            $missions = $this->missionRepository->findForExport($filters);
            $data = $this->exportMissions($missions, $format);

            $response = new Response($data);
            $response->headers->set('Content-Type', $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="missions_export.'.$format.'"');
            
            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());
            return $this->redirectToRoute('admin_alternance_mission_index');
        }
    }

    private function getMissionStatistics(): array
    {
        $totalMissions = $this->missionRepository->count([]);
        $activeMissions = $this->missionRepository->countActive();
        $completedAssignments = $this->assignmentRepository->countByStatus('completed');
        $inProgressAssignments = $this->assignmentRepository->countByStatus('in_progress');

        return [
            'total_missions' => $totalMissions,
            'active_missions' => $activeMissions,
            'completed_assignments' => $completedAssignments,
            'in_progress_assignments' => $inProgressAssignments,
            'completion_rate' => $this->calculateCompletionRate(),
            'average_duration' => $this->calculateAverageMissionDuration(),
            'complexity_distribution' => $this->missionRepository->getComplexityDistribution(),
            'recent_missions' => $this->missionRepository->findRecentMissions(5),
        ];
    }

    private function calculateCompletionRate(): float
    {
        $totalAssignments = $this->assignmentRepository->count([]);
        $completedAssignments = $this->assignmentRepository->countByStatus('completed');
        
        return $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 1) : 0;
    }

    private function calculateAverageMissionDuration(): float
    {
        // This would calculate based on actual assignment data
        // For now, return a placeholder
        return 14.5; // days
    }

    private function exportMissions(array $missions, string $format): string
    {
        if ($format === 'csv') {
            $output = fopen('php://temp', 'r+');
            
            // Headers
            fputcsv($output, [
                'Titre',
                'Description',
                'Difficulté',
                'Durée estimée',
                'Statut',
                'Date de création'
            ]);
            
            // Data
            foreach ($missions as $mission) {
                fputcsv($output, [
                    $mission->getTitle(),
                    $mission->getDescription(),
                    $mission->getComplexityLabel(),
                    $mission->getDuration(),
                    $mission->isActive() ? 'Actif' : 'Inactif',
                    $mission->getCreatedAt()->format('d/m/Y')
                ]);
            }
            
            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);
            
            return $content;
        }

        throw new \InvalidArgumentException("Format d'export non supporté: {$format}");
    }
}
