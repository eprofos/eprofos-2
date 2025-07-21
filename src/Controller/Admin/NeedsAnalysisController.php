<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NeedsAnalysisRequest;
use App\Entity\User\User;
use App\Form\NeedsAnalysisRequestType;
use App\Repository\NeedsAnalysisRequestRepository;
use App\Service\NeedsAnalysisService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin controller for managing needs analysis requests
 * 
 * Handles CRUD operations for needs analysis requests in the admin interface.
 * Provides functionality for creating, viewing, editing, and managing the lifecycle
 * of needs analysis requests including sending invitations and tracking responses.
 */
#[Route('/admin/needs-analysis', name: 'admin_needs_analysis_')]
#[IsGranted('ROLE_ADMIN')]
class NeedsAnalysisController extends AbstractController
{
    public function __construct(
        private readonly NeedsAnalysisRequestRepository $repository,
        private readonly NeedsAnalysisService $needsAnalysisService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Display the list of all needs analysis requests with filtering and pagination
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status');
        $type = $request->query->get('type');
        $search = $request->query->get('search');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }
        if ($type) {
            $criteria['type'] = $type;
        }

        if ($search) {
            $requests = $this->repository->findBySearchTerm($search, $criteria, $page, $limit);
            $total = $this->repository->countBySearchTerm($search, $criteria);
        } else {
            $requests = $this->repository->findByCriteria($criteria, $page, $limit);
            $total = $this->repository->countByCriteria($criteria);
        }

        $totalPages = (int) ceil($total / $limit);

        // Get statistics for dashboard
        $stats = [
            'total' => $this->repository->count([]),
            'pending' => $this->repository->count(['status' => 'pending']),
            'sent' => $this->repository->count(['status' => 'sent']),
            'completed' => $this->repository->count(['status' => 'completed']),
            'expired' => $this->repository->count(['status' => 'expired']),
        ];

        return $this->render('admin/needs_analysis/index.html.twig', [
            'requests' => $requests,
            'stats' => $stats,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'filters' => [
                'status' => $status,
                'type' => $type,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display details of a specific needs analysis request
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(NeedsAnalysisRequest $request): Response
    {
        return $this->render('admin/needs_analysis/show.html.twig', [
            'request' => $request,
        ]);
    }

    /**
     * Create a new needs analysis request
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $needsAnalysisRequest = new NeedsAnalysisRequest();
        $form = $this->createForm(NeedsAnalysisRequestType::class, $needsAnalysisRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Create the request using the service
                $createdRequest = $this->needsAnalysisService->createNeedsAnalysisRequest(
                    $needsAnalysisRequest->getType(),
                    $needsAnalysisRequest->getRecipientName(),
                    $needsAnalysisRequest->getRecipientEmail(),
                    $needsAnalysisRequest->getCompanyName(),
                    $needsAnalysisRequest->getFormation(),
                    $this->getUser(),
                    $needsAnalysisRequest->getAdminNotes()
                );

                $this->addFlash('success', 'Demande d\'analyse des besoins créée avec succès.');
                
                $this->logger->info('Needs analysis request created', [
                    'request_id' => $createdRequest->getId(),
                    'type' => $createdRequest->getType(),
                    'created_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                return $this->redirectToRoute('admin_needs_analysis_show', [
                    'id' => $createdRequest->getId()
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création de la demande : ' . $e->getMessage());
                
                $this->logger->error('Failed to create needs analysis request', [
                    'error' => $e->getMessage(),
                    'created_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->render('admin/needs_analysis/new.html.twig', [
            'form' => $form,
            'request' => $needsAnalysisRequest,
        ]);
    }

    /**
     * Edit an existing needs analysis request
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        // Only allow editing if request is in pending status
        if ($needsAnalysisRequest->getStatus() !== 'pending') {
            $this->addFlash('warning', 'Seules les demandes en attente peuvent être modifiées.');
            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }

        $form = $this->createForm(NeedsAnalysisRequestType::class, $needsAnalysisRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();

                $this->addFlash('success', 'Demande d\'analyse des besoins modifiée avec succès.');
                
                $this->logger->info('Needs analysis request updated', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'updated_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                return $this->redirectToRoute('admin_needs_analysis_show', [
                    'id' => $needsAnalysisRequest->getId()
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());
                
                $this->logger->error('Failed to update needs analysis request', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'error' => $e->getMessage(),
                    'updated_by' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->render('admin/needs_analysis/edit.html.twig', [
            'form' => $form,
            'request' => $needsAnalysisRequest,
        ]);
    }

    /**
     * Send the needs analysis request to the beneficiary
     */
    #[Route('/{id}/send', name: 'send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function send(NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        if ($needsAnalysisRequest->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cette demande ne peut pas être envoyée.');
            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }

        try {
            $this->needsAnalysisService->sendNeedsAnalysisRequest($needsAnalysisRequest);
            
            $this->addFlash('success', 'Demande d\'analyse envoyée avec succès au bénéficiaire.');
            
            $this->logger->info('Needs analysis request sent', [
                'request_id' => $needsAnalysisRequest->getId(),
                'sent_by' => $this->getUser()?->getUserIdentifier(),
                'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
            
            $this->logger->error('Failed to send needs analysis request', [
                'request_id' => $needsAnalysisRequest->getId(),
                'error' => $e->getMessage(),
                'sent_by' => $this->getUser()?->getUserIdentifier(),
            ]);
        }

        return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
    }

    /**
     * Cancel a needs analysis request
     */
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(Request $request, NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        if (!in_array($needsAnalysisRequest->getStatus(), ['pending', 'sent'])) {
            $this->addFlash('error', 'Cette demande ne peut pas être annulée.');
            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }

        $reason = $request->request->get('reason', 'Annulée par l\'administrateur');

        try {
            $this->needsAnalysisService->cancelRequest($needsAnalysisRequest, $reason);
            
            $this->addFlash('success', 'Demande d\'analyse annulée avec succès.');
            
            $this->logger->info('Needs analysis request cancelled', [
                'request_id' => $needsAnalysisRequest->getId(),
                'cancelled_by' => $this->getUser()?->getUserIdentifier(),
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'annulation : ' . $e->getMessage());
            
            $this->logger->error('Failed to cancel needs analysis request', [
                'request_id' => $needsAnalysisRequest->getId(),
                'error' => $e->getMessage(),
                'cancelled_by' => $this->getUser()?->getUserIdentifier(),
            ]);
        }

        return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
    }

    /**
     * Delete a needs analysis request (only if pending)
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        if ($needsAnalysisRequest->getStatus() !== 'pending') {
            $this->addFlash('error', 'Seules les demandes en attente peuvent être supprimées.');
            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }

        try {
            $requestId = $needsAnalysisRequest->getId();
            $this->entityManager->remove($needsAnalysisRequest);
            $this->entityManager->flush();

            $this->addFlash('success', 'Demande d\'analyse supprimée avec succès.');
            
            $this->logger->info('Needs analysis request deleted', [
                'request_id' => $requestId,
                'deleted_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return $this->redirectToRoute('admin_needs_analysis_index');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            
            $this->logger->error('Failed to delete needs analysis request', [
                'request_id' => $needsAnalysisRequest->getId(),
                'error' => $e->getMessage(),
                'deleted_by' => $this->getUser()?->getUserIdentifier(),
            ]);

            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }
    }

    /**
     * Add admin notes to a needs analysis request
     */
    #[Route('/{id}/notes', name: 'add_notes', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addNotes(Request $request, NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        $newNote = trim($request->request->get('note', ''));
        
        if (empty($newNote)) {
            $this->addFlash('error', 'La note ne peut pas être vide.');
            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }

        try {
            $currentNotes = $needsAnalysisRequest->getAdminNotes();
            $timestamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $adminName = $this->getUser()?->getUserIdentifier() ?? 'Admin';
            $formattedNote = "[{$timestamp}] {$adminName}: {$newNote}";
            $updatedNotes = $currentNotes ? $currentNotes . "\n" . $formattedNote : $formattedNote;
            
            $needsAnalysisRequest->setAdminNotes($updatedNotes);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Note ajoutée avec succès.');
            
            $this->logger->info('Admin note added to needs analysis request', [
                'request_id' => $needsAnalysisRequest->getId(),
                'added_by' => $this->getUser()?->getUserIdentifier(),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'ajout de la note : ' . $e->getMessage());
            
            $this->logger->error('Failed to add admin note', [
                'request_id' => $needsAnalysisRequest->getId(),
                'error' => $e->getMessage(),
                'added_by' => $this->getUser()?->getUserIdentifier(),
            ]);
        }

        return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
    }
}