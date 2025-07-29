<?php

declare(strict_types=1);

namespace App\Controller\Admin\CRM;

use App\Entity\CRM\Prospect;
use App\Entity\CRM\ProspectNote;
use App\Form\CRM\ProspectNoteType;
use App\Form\CRM\ProspectType;
use App\Repository\CRM\ProspectNoteRepository;
use App\Repository\CRM\ProspectRepository;
use App\Repository\User\AdminRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Prospect Controller.
 *
 * Handles CRUD operations for prospects in the admin interface.
 * Provides comprehensive prospect management capabilities for EPROFOS.
 */
#[Route('/admin/prospects')]
#[IsGranted('ROLE_ADMIN')]
class ProspectController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * List all prospects with filtering and search.
     */
    #[Route('/', name: 'admin_prospect_index', methods: ['GET'])]
    public function index(Request $request, ProspectRepository $prospectRepository, AdminRepository $userRepository): Response
    {
        $this->logger->info('Admin prospects list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $source = $request->query->get('source');
        $assignedTo = $request->query->get('assigned_to');
        $search = $request->query->get('search');

        $queryBuilder = $prospectRepository->createQueryBuilder('p')
            ->leftJoin('p.assignedTo', 'u')
            ->leftJoin('p.interestedFormations', 'f')
            ->leftJoin('p.interestedServices', 's')
            ->addSelect('u', 'f', 's')
            ->orderBy('p.updatedAt', 'DESC')
        ;

        if ($status) {
            $queryBuilder->andWhere('p.status = :status')
                ->setParameter('status', $status)
            ;
        }

        if ($priority) {
            $queryBuilder->andWhere('p.priority = :priority')
                ->setParameter('priority', $priority)
            ;
        }

        if ($source) {
            $queryBuilder->andWhere('p.source = :source')
                ->setParameter('source', $source)
            ;
        }

        if ($assignedTo) {
            $queryBuilder->andWhere('p.assignedTo = :assignedTo')
                ->setParameter('assignedTo', $assignedTo)
            ;
        }

        if ($search) {
            $searchTerms = '%' . strtolower($search) . '%';
            $queryBuilder->andWhere(
                'LOWER(p.firstName) LIKE :search OR 
                 LOWER(p.lastName) LIKE :search OR 
                 LOWER(p.email) LIKE :search OR 
                 LOWER(p.company) LIKE :search',
            )->setParameter('search', $searchTerms);
        }

        $prospects = $queryBuilder->getQuery()->getResult();

        // Get statistics for dashboard
        $statistics = $prospectRepository->getDashboardStatistics();
        $statusCounts = $prospectRepository->countByStatus();
        $priorityCounts = $prospectRepository->countByPriority();
        $sourceCounts = $prospectRepository->countBySource();

        // Get all users for assignment filter
        $users = $userRepository->findAll();

        return $this->render('admin/prospect/index.html.twig', [
            'prospects' => $prospects,
            'statistics' => $statistics,
            'status_counts' => $statusCounts,
            'priority_counts' => $priorityCounts,
            'source_counts' => $sourceCounts,
            'users' => $users,
            'current_status' => $status,
            'current_priority' => $priority,
            'current_source' => $source,
            'current_assigned_to' => $assignedTo,
            'current_search' => $search,
            'page_title' => 'Gestion des prospects',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Prospects', 'url' => null],
            ],
        ]);
    }

    /**
     * Show prospect details with notes.
     */
    #[Route('/{id}', name: 'admin_prospect_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Prospect $prospect, ProspectNoteRepository $noteRepository): Response
    {
        $this->logger->info('Admin prospect details viewed', [
            'prospect_id' => $prospect->getId(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        $notes = $noteRepository->findByProspect($prospect);
        $noteStatistics = $noteRepository->getProspectNoteStatistics($prospect);

        return $this->render('admin/prospect/show.html.twig', [
            'prospect' => $prospect,
            'notes' => $notes,
            'note_statistics' => $noteStatistics,
            'page_title' => 'Détails du prospect',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Prospects', 'url' => $this->generateUrl('admin_prospect_index')],
                ['label' => $prospect->getFullName(), 'url' => null],
            ],
        ]);
    }

    /**
     * Create a new prospect.
     */
    #[Route('/new', name: 'admin_prospect_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $prospect = new Prospect();
        $form = $this->createForm(ProspectType::class, $prospect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($prospect);
            $entityManager->flush();

            $this->logger->info('New prospect created', [
                'prospect_id' => $prospect->getId(),
                'prospect_name' => $prospect->getFullName(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Le prospect a été créé avec succès.');

            return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
        }

        return $this->render('admin/prospect/new.html.twig', [
            'prospect' => $prospect,
            'form' => $form,
            'page_title' => 'Nouveau prospect',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Prospects', 'url' => $this->generateUrl('admin_prospect_index')],
                ['label' => 'Nouveau', 'url' => null],
            ],
        ]);
    }

    /**
     * Edit an existing prospect.
     */
    #[Route('/{id}/edit', name: 'admin_prospect_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Prospect $prospect, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProspectType::class, $prospect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->logger->info('Prospect updated', [
                'prospect_id' => $prospect->getId(),
                'prospect_name' => $prospect->getFullName(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Le prospect a été modifié avec succès.');

            return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
        }

        return $this->render('admin/prospect/edit.html.twig', [
            'prospect' => $prospect,
            'form' => $form,
            'page_title' => 'Modifier le prospect',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Prospects', 'url' => $this->generateUrl('admin_prospect_index')],
                ['label' => $prospect->getFullName(), 'url' => $this->generateUrl('admin_prospect_show', ['id' => $prospect->getId()])],
                ['label' => 'Modifier', 'url' => null],
            ],
        ]);
    }

    /**
     * Delete a prospect.
     */
    #[Route('/{id}', name: 'admin_prospect_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Prospect $prospect, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $prospect->getId(), $request->getPayload()->get('_token'))) {
            $prospectName = $prospect->getFullName();
            $prospectId = $prospect->getId();

            $entityManager->remove($prospect);
            $entityManager->flush();

            $this->logger->info('Prospect deleted', [
                'prospect_id' => $prospectId,
                'prospect_name' => $prospectName,
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Le prospect a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_prospect_index');
    }

    /**
     * Update prospect status.
     */
    #[Route('/{id}/status', name: 'admin_prospect_update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(Request $request, Prospect $prospect, EntityManagerInterface $entityManager): Response
    {
        $newStatus = $request->getPayload()->get('status');

        if ($this->isCsrfTokenValid('update_status' . $prospect->getId(), $request->getPayload()->get('_token'))) {
            $oldStatus = $prospect->getStatus();
            $prospect->setStatus($newStatus);

            // Update last contact date if moving to customer or lost
            if (in_array($newStatus, ['customer', 'lost'], true)) {
                $prospect->setLastContactDate(new DateTime());
            }

            $entityManager->flush();

            $this->logger->info('Prospect status updated', [
                'prospect_id' => $prospect->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Le statut du prospect a été mis à jour avec succès.');
        }

        return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
    }

    /**
     * Add a note to a prospect.
     */
    #[Route('/{id}/notes/new', name: 'admin_prospect_add_note', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function addNote(Request $request, Prospect $prospect, EntityManagerInterface $entityManager): Response
    {
        $note = new ProspectNote();
        $note->setProspect($prospect);
        $note->setCreatedBy($this->getUser());

        $form = $this->createForm(ProspectNoteType::class, $note, [
            'prospect_context' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update prospect's last contact date
            $prospect->setLastContactDate(new DateTime());

            $entityManager->persist($note);
            $entityManager->flush();

            $this->logger->info('Note added to prospect', [
                'prospect_id' => $prospect->getId(),
                'note_id' => $note->getId(),
                'note_type' => $note->getType(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La note a été ajoutée avec succès.');

            return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
        }

        return $this->render('admin/prospect/add_note.html.twig', [
            'prospect' => $prospect,
            'form' => $form,
            'page_title' => 'Ajouter une note',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Prospects', 'url' => $this->generateUrl('admin_prospect_index')],
                ['label' => $prospect->getFullName(), 'url' => $this->generateUrl('admin_prospect_show', ['id' => $prospect->getId()])],
                ['label' => 'Ajouter une note', 'url' => null],
            ],
        ]);
    }

    /**
     * Export prospects to CSV.
     */
    #[Route('/export', name: 'admin_prospect_export', methods: ['GET'])]
    public function export(ProspectRepository $prospectRepository): Response
    {
        $prospects = $prospectRepository->findAll();

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="prospects_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'ID',
            'Prénom',
            'Nom',
            'Email',
            'Téléphone',
            'Entreprise',
            'Poste',
            'Statut',
            'Priorité',
            'Source',
            'Budget estimé',
            'Assigné à',
            'Dernière contact',
            'Prochain suivi',
            'Créé le',
            'Mis à jour le',
        ]);

        // CSV data
        foreach ($prospects as $prospect) {
            fputcsv($output, [
                $prospect->getId(),
                $prospect->getFirstName(),
                $prospect->getLastName(),
                $prospect->getEmail(),
                $prospect->getPhone(),
                $prospect->getCompany(),
                $prospect->getPosition(),
                $prospect->getStatusLabel(),
                $prospect->getPriorityLabel(),
                $prospect->getSourceLabel(),
                $prospect->getEstimatedBudget(),
                $prospect->getAssignedTo()?->getFullName(),
                $prospect->getLastContactDate()?->format('d/m/Y'),
                $prospect->getNextFollowUpDate()?->format('d/m/Y'),
                $prospect->getCreatedAt()?->format('d/m/Y H:i'),
                $prospect->getUpdatedAt()?->format('d/m/Y H:i'),
            ]);
        }

        fclose($output);

        $this->logger->info('Prospects exported', [
            'count' => count($prospects),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $response;
    }

    /**
     * Dashboard view for prospects requiring attention.
     */
    #[Route('/dashboard', name: 'admin_prospect_dashboard', methods: ['GET'])]
    public function dashboard(ProspectRepository $prospectRepository, ProspectNoteRepository $noteRepository): Response
    {
        $this->logger->info('Prospect dashboard accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        $needingFollowUp = $prospectRepository->findNeedingFollowUp();
        $overdueProspects = $prospectRepository->findOverdueProspects();
        $highPriorityProspects = $prospectRepository->findByPriority('urgent');
        $recentActivity = $noteRepository->findRecentActivity();
        $todayTasks = $noteRepository->findScheduledForToday();
        $overdueTasks = $noteRepository->findOverdueNotes();

        $statistics = $prospectRepository->getDashboardStatistics();
        $conversionStats = $prospectRepository->getConversionStatistics();
        $activityStats = $noteRepository->getActivityStatistics();

        return $this->render('admin/prospect/dashboard.html.twig', [
            'needing_follow_up' => $needingFollowUp,
            'overdue_prospects' => $overdueProspects,
            'high_priority_prospects' => $highPriorityProspects,
            'recent_activity' => $recentActivity,
            'today_tasks' => $todayTasks,
            'overdue_tasks' => $overdueTasks,
            'statistics' => $statistics,
            'conversion_stats' => $conversionStats,
            'activity_stats' => $activityStats,
            'page_title' => 'Tableau de bord prospects',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Prospects', 'url' => $this->generateUrl('admin_prospect_index')],
                ['label' => 'Tableau de bord', 'url' => null],
            ],
        ]);
    }
}
