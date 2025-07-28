<?php

declare(strict_types=1);

namespace App\Controller\Admin\CRM;

use App\Entity\CRM\Prospect;
use App\Entity\CRM\ProspectNote;
use App\Form\ProspectNoteType;
use App\Repository\CRM\ProspectNoteRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Prospect Note Controller.
 *
 * Handles CRUD operations for prospect notes in the admin interface.
 * Manages interactions, tasks, and follow-ups for prospects.
 */
#[Route('/admin/prospect-notes', name: 'admin_prospect_note_')]
#[IsGranted('ROLE_ADMIN')]
class ProspectNoteController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * List all prospect notes with filtering.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, ProspectNoteRepository $noteRepository): Response
    {
        $this->logger->info('Admin prospect notes list accessed', [
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        $type = $request->query->get('type');
        $status = $request->query->get('status');
        $important = $request->query->get('important');
        $search = $request->query->get('search');

        $queryBuilder = $noteRepository->createQueryBuilder('pn')
            ->leftJoin('pn.prospect', 'p')
            ->leftJoin('pn.createdBy', 'u')
            ->addSelect('p', 'u')
            ->orderBy('pn.createdAt', 'DESC')
        ;

        if ($type) {
            $queryBuilder->andWhere('pn.type = :type')
                ->setParameter('type', $type)
            ;
        }

        if ($status) {
            $queryBuilder->andWhere('pn.status = :status')
                ->setParameter('status', $status)
            ;
        }

        if ($important) {
            $queryBuilder->andWhere('pn.isImportant = :important')
                ->setParameter('important', $important === 'true')
            ;
        }

        if ($search) {
            $searchTerms = '%' . strtolower($search) . '%';
            $queryBuilder->andWhere(
                'LOWER(pn.title) LIKE :search OR 
                 LOWER(pn.content) LIKE :search OR 
                 LOWER(p.firstName) LIKE :search OR 
                 LOWER(p.lastName) LIKE :search OR 
                 LOWER(p.company) LIKE :search',
            )->setParameter('search', $searchTerms);
        }

        $notes = $queryBuilder->getQuery()->getResult();

        // Get statistics
        $statistics = $noteRepository->getActivityStatistics();
        $typeCounts = $noteRepository->countByType();
        $statusCounts = $noteRepository->countByStatus();

        return $this->render('admin/prospect_note/index.html.twig', [
            'notes' => $notes,
            'statistics' => $statistics,
            'type_counts' => $typeCounts,
            'status_counts' => $statusCounts,
            'current_type' => $type,
            'current_status' => $status,
            'current_important' => $important,
            'current_search' => $search,
            'page_title' => 'Gestion des notes prospects',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Notes prospects', 'url' => null],
            ],
        ]);
    }

    /**
     * Show note details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ProspectNote $note): Response
    {
        $this->logger->info('Admin prospect note details viewed', [
            'note_id' => $note->getId(),
            'prospect_id' => $note->getProspect()?->getId(),
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $this->render('admin/prospect_note/show.html.twig', [
            'note' => $note,
            'page_title' => 'Détails de la note',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Notes prospects', 'url' => $this->generateUrl('admin_prospect_note_index')],
                ['label' => 'Note #' . $note->getId(), 'url' => null],
            ],
        ]);
    }

    /**
     * Create a new note.
     */
    #[Route('/new', name: 'new_standalone', methods: ['GET', 'POST'])]
    public function newStandalone(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->new($request, $entityManager, null);
    }

    /**
     * Create a new note for a specific prospect.
     */
    #[Route('/new/{prospect}', name: 'new', methods: ['GET', 'POST'], requirements: ['prospect' => '\d+'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ?Prospect $prospect = null): Response
    {
        $note = new ProspectNote();
        $note->setCreatedBy($this->getUser());

        // Set the prospect if provided via URL parameter
        if ($prospect) {
            $note->setProspect($prospect);
        }

        $form = $this->createForm(ProspectNoteType::class, $note, [
            'show_created_by' => true,
            'prospect_context' => $prospect !== null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update prospect's last contact date if note is completed
            if ($note->isCompleted() && $note->getProspect()) {
                $note->getProspect()->setLastContactDate(new DateTime());
            }

            $entityManager->persist($note);
            $entityManager->flush();

            $this->logger->info('New prospect note created', [
                'note_id' => $note->getId(),
                'prospect_id' => $note->getProspect()?->getId(),
                'note_type' => $note->getType(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La note a été créée avec succès.');

            // Redirect to prospect show page if we came from a prospect context
            if ($prospect) {
                return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
            }

            return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
        }

        $breadcrumb = [
            ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ['label' => 'Notes prospects', 'url' => $this->generateUrl('admin_prospect_note_index')],
        ];

        // Add prospect to breadcrumb if we're in prospect context
        if ($prospect) {
            $breadcrumb[] = ['label' => 'Prospects', 'url' => $this->generateUrl('admin_prospect_index')];
            $breadcrumb[] = ['label' => $prospect->getFullName(), 'url' => $this->generateUrl('admin_prospect_show', ['id' => $prospect->getId()])];
        }

        $breadcrumb[] = ['label' => 'Nouvelle note', 'url' => null];

        return $this->render('admin/prospect_note/new.html.twig', [
            'note' => $note,
            'form' => $form,
            'prospect' => $prospect,
            'page_title' => $prospect ? 'Nouvelle note pour ' . $prospect->getFullName() : 'Nouvelle note',
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * Edit an existing note.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, ProspectNote $note, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProspectNoteType::class, $note, [
            'show_created_by' => true,
            'prospect_context' => true,  // In edit mode, prospect is already determined
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update prospect's last contact date if note is completed
            if ($note->isCompleted() && $note->getProspect()) {
                $note->getProspect()->setLastContactDate(new DateTime());
            }

            $entityManager->flush();

            $this->logger->info('Prospect note updated', [
                'note_id' => $note->getId(),
                'prospect_id' => $note->getProspect()?->getId(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La note a été modifiée avec succès.');

            return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
        }

        return $this->render('admin/prospect_note/edit.html.twig', [
            'note' => $note,
            'form' => $form,
            'page_title' => 'Modifier la note',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Notes prospects', 'url' => $this->generateUrl('admin_prospect_note_index')],
                ['label' => 'Note #' . $note->getId(), 'url' => $this->generateUrl('admin_prospect_note_show', ['id' => $note->getId()])],
                ['label' => 'Modifier', 'url' => null],
            ],
        ]);
    }

    /**
     * Delete a note.
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ProspectNote $note, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $note->getId(), $request->getPayload()->get('_token'))) {
            $noteId = $note->getId();
            $prospectId = $note->getProspect()?->getId();

            $entityManager->remove($note);
            $entityManager->flush();

            $this->logger->info('Prospect note deleted', [
                'note_id' => $noteId,
                'prospect_id' => $prospectId,
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La note a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_prospect_note_index');
    }

    /**
     * Update note status.
     */
    #[Route('/{id}/status', name: 'update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(Request $request, ProspectNote $note, EntityManagerInterface $entityManager): Response
    {
        $newStatus = $request->getPayload()->get('status');

        if ($this->isCsrfTokenValid('update_status' . $note->getId(), $request->getPayload()->get('_token'))) {
            $oldStatus = $note->getStatus();
            $note->setStatus($newStatus);

            // Update prospect's last contact date if note is completed
            if ($note->isCompleted() && $note->getProspect()) {
                $note->getProspect()->setLastContactDate(new DateTime());
            }

            $entityManager->flush();

            $this->logger->info('Prospect note status updated', [
                'note_id' => $note->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Le statut de la note a été mis à jour avec succès.');
        }

        return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
    }

    /**
     * Mark note as important.
     */
    #[Route('/{id}/toggle-important', name: 'toggle_important', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleImportant(Request $request, ProspectNote $note, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_important' . $note->getId(), $request->getPayload()->get('_token'))) {
            $note->setIsImportant(!$note->isImportant());
            $entityManager->flush();

            $this->logger->info('Prospect note importance toggled', [
                'note_id' => $note->getId(),
                'is_important' => $note->isImportant(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $message = $note->isImportant()
                ? 'La note a été marquée comme importante.'
                : 'La note n\'est plus marquée comme importante.';

            $this->addFlash('success', $message);
        }

        return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
    }

    /**
     * List pending tasks.
     */
    #[Route('/tasks/pending', name: 'pending_tasks', methods: ['GET'])]
    public function pendingTasks(ProspectNoteRepository $noteRepository): Response
    {
        $this->logger->info('Pending prospect tasks accessed', [
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        $pendingNotes = $noteRepository->findPendingNotes();
        $overdueNotes = $noteRepository->findOverdueNotes();
        $todayNotes = $noteRepository->findScheduledForToday();

        return $this->render('admin/prospect_note/pending_tasks.html.twig', [
            'pending_notes' => $pendingNotes,
            'overdue_notes' => $overdueNotes,
            'today_notes' => $todayNotes,
            'page_title' => 'Tâches en attente',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Notes prospects', 'url' => $this->generateUrl('admin_prospect_note_index')],
                ['label' => 'Tâches en attente', 'url' => null],
            ],
        ]);
    }

    /**
     * List important notes.
     */
    #[Route('/important', name: 'important', methods: ['GET'])]
    public function important(ProspectNoteRepository $noteRepository): Response
    {
        $this->logger->info('Important prospect notes accessed', [
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        $importantNotes = $noteRepository->findImportantNotes();

        return $this->render('admin/prospect_note/important.html.twig', [
            'important_notes' => $importantNotes,
            'page_title' => 'Notes importantes',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Notes prospects', 'url' => $this->generateUrl('admin_prospect_note_index')],
                ['label' => 'Notes importantes', 'url' => null],
            ],
        ]);
    }

    /**
     * Export notes to CSV.
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(ProspectNoteRepository $noteRepository): Response
    {
        $notes = $noteRepository->findAll();

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="prospect_notes_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'ID',
            'Titre',
            'Type',
            'Statut',
            'Prospect',
            'Entreprise',
            'Créé par',
            'Important',
            'Privé',
            'Planifié le',
            'Créé le',
            'Terminé le',
        ]);

        // CSV data
        foreach ($notes as $note) {
            fputcsv($output, [
                $note->getId(),
                $note->getTitle(),
                $note->getTypeLabel(),
                $note->getStatusLabel(),
                $note->getProspect()?->getFullName(),
                $note->getProspect()?->getCompany(),
                $note->getCreatedBy()?->getFullName(),
                $note->isImportant() ? 'Oui' : 'Non',
                $note->isPrivate() ? 'Oui' : 'Non',
                $note->getScheduledAt()?->format('d/m/Y H:i'),
                $note->getCreatedAt()?->format('d/m/Y H:i'),
                $note->getCompletedAt()?->format('d/m/Y H:i'),
            ]);
        }

        fclose($output);

        $this->logger->info('Prospect notes exported', [
            'count' => count($notes),
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $response;
    }
}
