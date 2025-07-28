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
#[Route('/mentor/missions', name: 'mentor_missions_')]
#[IsGranted('ROLE_MENTOR')]
class MissionController extends AbstractController
{
    public function __construct(
        private CompanyMissionService $missionService,
        private CompanyMissionRepository $missionRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * List all missions created by the mentor.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Filter and pagination parameters
        $status = $request->query->get('status', 'all');
        $complexity = $request->query->get('complexity', 'all');
        $term = $request->query->get('term', 'all');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

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
        }

        if ($complexity !== 'all') {
            $queryBuilder->andWhere('m.complexity = :complexity')
                ->setParameter('complexity', $complexity)
            ;
        }

        if ($term !== 'all') {
            $queryBuilder->andWhere('m.term = :term')
                ->setParameter('term', $term)
            ;
        }

        // Get total count
        $totalCount = count($queryBuilder->getQuery()->getResult());
        $totalPages = ceil($totalCount / $limit);

        // Apply pagination
        $missions = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        // Get statistics for the mentor
        $stats = $this->missionService->getMentorMissionStats($mentor);

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
    }

    /**
     * Show mission details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(CompanyMission $mission): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Security check: ensure mentor owns this mission
        if ($mission->getSupervisor() !== $mentor) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette mission.');
        }

        // Get mission statistics
        $stats = [
            'total_assignments' => $mission->getAssignments()->count(),
            'active_assignments' => $mission->getActiveAssignmentsCount(),
            'completed_assignments' => $mission->getCompletedAssignmentsCount(),
            'complexity_level' => $mission->getComplexityLabel(),
            'term_type' => $mission->getTermLabel(),
        ];

        return $this->render('mentor/missions/show.html.twig', [
            'mission' => $mission,
            'mentor' => $mentor,
            'stats' => $stats,
            'page_title' => $mission->getTitle(),
        ]);
    }

    /**
     * Create a new mission.
     */
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        $mission = new CompanyMission();
        $form = $this->createForm(CompanyMissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Set the mentor as supervisor
                $mission->setSupervisor($mentor);

                // Use the service to create the mission (handles business logic)
                $createdMission = $this->missionService->createMission([
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
                ], $mentor);

                $this->addFlash('success', 'Mission créée avec succès !');

                return $this->redirectToRoute('mentor_missions_show', ['id' => $createdMission->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création de la mission : ' . $e->getMessage());
            }
        }

        return $this->render('mentor/missions/create.html.twig', [
            'form' => $form,
            'mentor' => $mentor,
            'page_title' => 'Créer une Mission',
        ]);
    }

    /**
     * Edit an existing mission.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, CompanyMission $mission): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Security check: ensure mentor owns this mission
        if ($mission->getSupervisor() !== $mentor) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette mission.');
        }

        // Check if mission can be edited (no active assignments)
        if ($mission->getActiveAssignmentsCount() > 0) {
            $this->addFlash('warning', 'Cette mission ne peut pas être modifiée car elle a des assignations actives.');

            return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
        }

        $form = $this->createForm(CompanyMissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Use the service to update the mission
                $this->missionService->updateMission($mission, [
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
                ]);

                $this->addFlash('success', 'Mission mise à jour avec succès !');

                return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }

        return $this->render('mentor/missions/edit.html.twig', [
            'form' => $form,
            'mission' => $mission,
            'mentor' => $mentor,
            'page_title' => 'Modifier ' . $mission->getTitle(),
        ]);
    }

    /**
     * Toggle mission active status.
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(CompanyMission $mission): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Security check: ensure mentor owns this mission
        if ($mission->getSupervisor() !== $mentor) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette mission.');
        }

        try {
            $newStatus = !$mission->isActive();
            $mission->setIsActive($newStatus);
            $this->entityManager->flush();

            $statusText = $newStatus ? 'activée' : 'désactivée';
            $this->addFlash('success', "Mission {$statusText} avec succès !");
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors du changement de statut : ' . $e->getMessage());
        }

        return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
    }

    /**
     * Delete a mission (soft delete by deactivating).
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(CompanyMission $mission): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Security check: ensure mentor owns this mission
        if ($mission->getSupervisor() !== $mentor) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette mission.');
        }

        // Check if mission can be deleted (no assignments)
        if ($mission->getAssignments()->count() > 0) {
            $this->addFlash('error', 'Cette mission ne peut pas être supprimée car elle a des assignations.');

            return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
        }

        try {
            $missionTitle = $mission->getTitle();
            $this->entityManager->remove($mission);
            $this->entityManager->flush();

            $this->addFlash('success', "Mission \"{$missionTitle}\" supprimée avec succès !");
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());

            return $this->redirectToRoute('mentor_missions_show', ['id' => $mission->getId()]);
        }

        return $this->redirectToRoute('mentor_missions_index');
    }

    /**
     * Get recommended next missions for progression.
     */
    #[Route('/recommendations', name: 'recommendations', methods: ['GET'])]
    public function recommendations(): Response
    {
        /** @var Mentor $mentor */
        $mentor = $this->getUser();

        // Get mentor's missions that need attention
        $recommendations = $this->missionService->getMissionsRequiringAttention($mentor);

        return $this->render('mentor/missions/recommendations.html.twig', [
            'recommendations' => $recommendations,
            'mentor' => $mentor,
            'page_title' => 'Recommandations de Missions',
        ]);
    }
}
