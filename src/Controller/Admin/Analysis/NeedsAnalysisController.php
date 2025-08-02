<?php

declare(strict_types=1);

namespace App\Controller\Admin\Analysis;

use App\Entity\Analysis\NeedsAnalysisRequest;
use App\Entity\User\Admin;
use App\Form\Analysis\NeedsAnalysisRequestType;
use App\Repository\Analysis\NeedsAnalysisRequestRepository;
use App\Service\Analysis\NeedsAnalysisService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin controller for managing needs analysis requests.
 *
 * Handles CRUD operations for needs analysis requests in the admin interface.
 * Provides functionality for creating, viewing, editing, and managing the lifecycle
 * of needs analysis requests including sending invitations and tracking responses.
 */
#[Route('/admin/needs-analysis')]
#[IsGranted('ROLE_ADMIN')]
class NeedsAnalysisController extends AbstractController
{
    public function __construct(
        private readonly NeedsAnalysisRequestRepository $repository,
        private readonly NeedsAnalysisService $needsAnalysisService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Display the list of all needs analysis requests with filtering and pagination.
     */
    #[Route('', name: 'admin_needs_analysis_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('Starting needs analysis index page request', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_uri' => $request->getRequestUri(),
            'query_params' => $request->query->all(),
        ]);

        try {
            $status = $request->query->get('status');
            $type = $request->query->get('type');
            $search = $request->query->get('search');
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = 20;

            $this->logger->debug('Processing filter parameters', [
                'status_filter' => $status,
                'type_filter' => $type,
                'search_term' => $search,
                'page' => $page,
                'limit' => $limit,
            ]);

            $criteria = [];
            if ($status) {
                $criteria['status'] = $status;
                $this->logger->debug('Added status filter to criteria', ['status' => $status]);
            }
            if ($type) {
                $criteria['type'] = $type;
                $this->logger->debug('Added type filter to criteria', ['type' => $type]);
            }

            $this->logger->debug('Executing repository queries', ['criteria' => $criteria]);

            if ($search) {
                $this->logger->info('Performing search query', [
                    'search_term' => $search,
                    'criteria' => $criteria,
                    'page' => $page,
                    'limit' => $limit,
                ]);

                $requests = $this->repository->findBySearchTerm($search, $criteria, $page, $limit);
                $total = $this->repository->countBySearchTerm($search, $criteria);

                $this->logger->debug('Search query completed', [
                    'results_count' => count($requests),
                    'total_matching' => $total,
                ]);
            } else {
                $this->logger->info('Performing criteria-based query', [
                    'criteria' => $criteria,
                    'page' => $page,
                    'limit' => $limit,
                ]);

                $requests = $this->repository->findByCriteria($criteria, $page, $limit);
                $total = $this->repository->countByCriteria($criteria);

                $this->logger->debug('Criteria query completed', [
                    'results_count' => count($requests),
                    'total_matching' => $total,
                ]);
            }

            $totalPages = (int) ceil($total / $limit);

            $this->logger->debug('Calculating pagination', [
                'total_items' => $total,
                'items_per_page' => $limit,
                'total_pages' => $totalPages,
                'current_page' => $page,
            ]);

            // Get statistics for dashboard
            $this->logger->debug('Fetching dashboard statistics');

            $stats = [
                'total' => $this->repository->count([]),
                'pending' => $this->repository->count(['status' => 'pending']),
                'sent' => $this->repository->count(['status' => 'sent']),
                'completed' => $this->repository->count(['status' => 'completed']),
                'expired' => $this->repository->count(['status' => 'expired']),
            ];

            $this->logger->info('Dashboard statistics calculated', $stats);

            $this->logger->info('Needs analysis index page completed successfully', [
                'requests_returned' => count($requests),
                'total_items' => $total,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

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
        } catch (Exception $e) {
            $this->logger->error('Critical error in needs analysis index page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'request_uri' => $request->getRequestUri(),
                'query_params' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du chargement de la liste des demandes d\'analyse.');

            // Return empty results to prevent complete failure
            return $this->render('admin/needs_analysis/index.html.twig', [
                'requests' => [],
                'stats' => [
                    'total' => 0,
                    'pending' => 0,
                    'sent' => 0,
                    'completed' => 0,
                    'expired' => 0,
                ],
                'current_page' => 1,
                'total_pages' => 0,
                'total_items' => 0,
                'filters' => [
                    'status' => null,
                    'type' => null,
                    'search' => null,
                ],
            ]);
        }
    }

    /**
     * Display details of a specific needs analysis request.
     */
    #[Route('/{id}', name: 'admin_needs_analysis_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(NeedsAnalysisRequest $request): Response
    {
        $this->logger->info('Starting needs analysis show page request', [
            'request_id' => $request->getId(),
            'request_status' => $request->getStatus(),
            'request_type' => $request->getType(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('Loading needs analysis request details', [
                'request_id' => $request->getId(),
                'recipient_email' => $request->getRecipientEmail(),
                'recipient_name' => $request->getRecipientName(),
                'company_name' => $request->getCompanyName(),
                'formation_id' => $request->getFormation()?->getId(),
                'formation_title' => $request->getFormation()?->getTitle(),
                'created_at' => $request->getCreatedAt()?->format('Y-m-d H:i:s'),
                'sent_at' => $request->getSentAt()?->format('Y-m-d H:i:s'),
                'completed_at' => $request->getCompletedAt()?->format('Y-m-d H:i:s'),
                'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
                'is_expired' => $request->isExpired(),
                'has_admin_notes' => !empty($request->getAdminNotes()),
                'admin_notes_length' => strlen($request->getAdminNotes() ?? ''),
            ]);

            $this->logger->info('Needs analysis show page completed successfully', [
                'request_id' => $request->getId(),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            return $this->render('admin/needs_analysis/show.html.twig', [
                'request' => $request,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in needs analysis show page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $request->getId(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du chargement des détails de la demande.');

            return $this->redirectToRoute('admin_needs_analysis_index');
        }
    }

    /**
     * Create a new needs analysis request.
     */
    #[Route('/new', name: 'admin_needs_analysis_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('Starting new needs analysis request creation', [
            'method' => $request->getMethod(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_uri' => $request->getRequestUri(),
        ]);

        $needsAnalysisRequest = new NeedsAnalysisRequest();

        try {
            $this->logger->debug('Creating form for new needs analysis request');

            $form = $this->createForm(NeedsAnalysisRequestType::class, $needsAnalysisRequest);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Form submitted for new needs analysis request', [
                    'form_valid' => $form->isValid(),
                    'form_errors' => $form->getErrors(true, false)->count(),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Form validation successful, preparing to create request', [
                        'type' => $needsAnalysisRequest->getType(),
                        'recipient_name' => $needsAnalysisRequest->getRecipientName(),
                        'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
                        'company_name' => $needsAnalysisRequest->getCompanyName(),
                        'formation_id' => $needsAnalysisRequest->getFormation()?->getId(),
                        'formation_title' => $needsAnalysisRequest->getFormation()?->getTitle(),
                        'has_admin_notes' => !empty($needsAnalysisRequest->getAdminNotes()),
                    ]);

                    try {
                        // Create the request using the service
                        $createdRequest = $this->needsAnalysisService->createNeedsAnalysisRequest(
                            $needsAnalysisRequest->getType(),
                            $needsAnalysisRequest->getRecipientName(),
                            $needsAnalysisRequest->getRecipientEmail(),
                            $needsAnalysisRequest->getCompanyName(),
                            $needsAnalysisRequest->getFormation(),
                            $this->getUser(), // $admin
                            $needsAnalysisRequest->getAdminNotes(),
                        );

                        $this->addFlash('success', 'Demande d\'analyse des besoins créée avec succès.');

                        $this->logger->info('Needs analysis request created successfully', [
                            'request_id' => $createdRequest->getId(),
                            'type' => $createdRequest->getType(),
                            'recipient_email' => $createdRequest->getRecipientEmail(),
                            'formation_id' => $createdRequest->getFormation()?->getId(),
                            'created_by' => $this->getUser()?->getUserIdentifier(),
                            'status' => $createdRequest->getStatus(),
                            'expires_at' => $createdRequest->getExpiresAt()?->format('Y-m-d H:i:s'),
                        ]);

                        return $this->redirectToRoute('admin_needs_analysis_show', [
                            'id' => $createdRequest->getId(),
                        ]);
                    } catch (Exception $e) {
                        $this->addFlash('error', 'Erreur lors de la création de la demande : ' . $e->getMessage());

                        $this->logger->error('Failed to create needs analysis request via service', [
                            'error_message' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                            'request_data' => [
                                'type' => $needsAnalysisRequest->getType(),
                                'recipient_name' => $needsAnalysisRequest->getRecipientName(),
                                'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
                                'company_name' => $needsAnalysisRequest->getCompanyName(),
                                'formation_id' => $needsAnalysisRequest->getFormation()?->getId(),
                            ],
                            'created_by' => $this->getUser()?->getUserIdentifier(),
                        ]);
                    }
                } else {
                    $this->logger->warning('Form validation failed for new needs analysis request', [
                        'errors' => array_map(static fn ($error) => $error->getMessage(), iterator_to_array($form->getErrors(true, false))),
                        'submitted_data' => $request->request->all(),
                    ]);
                }
            }

            $this->logger->debug('Rendering new needs analysis request form', [
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
            ]);

            return $this->render('admin/needs_analysis/new.html.twig', [
                'form' => $form,
                'request' => $needsAnalysisRequest,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in new needs analysis request page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'request_method' => $request->getMethod(),
                'request_uri' => $request->getRequestUri(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la création de la demande.');

            return $this->redirectToRoute('admin_needs_analysis_index');
        }
    }

    /**
     * Edit an existing needs analysis request.
     */
    #[Route('/{id}/edit', name: 'admin_needs_analysis_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        $this->logger->info('Starting needs analysis request edit', [
            'request_id' => $needsAnalysisRequest->getId(),
            'current_status' => $needsAnalysisRequest->getStatus(),
            'method' => $request->getMethod(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            // Only allow editing if request is in pending status
            if ($needsAnalysisRequest->getStatus() !== 'pending') {
                $this->logger->warning('Attempt to edit non-pending needs analysis request', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'current_status' => $needsAnalysisRequest->getStatus(),
                    'user_email' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('warning', 'Seules les demandes en attente peuvent être modifiées.');

                return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
            }

            $this->logger->debug('Creating edit form for needs analysis request', [
                'request_id' => $needsAnalysisRequest->getId(),
                'current_data' => [
                    'type' => $needsAnalysisRequest->getType(),
                    'recipient_name' => $needsAnalysisRequest->getRecipientName(),
                    'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
                    'company_name' => $needsAnalysisRequest->getCompanyName(),
                    'formation_id' => $needsAnalysisRequest->getFormation()?->getId(),
                    'has_admin_notes' => !empty($needsAnalysisRequest->getAdminNotes()),
                ],
            ]);

            $form = $this->createForm(NeedsAnalysisRequestType::class, $needsAnalysisRequest);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Edit form submitted for needs analysis request', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'form_valid' => $form->isValid(),
                    'form_errors' => $form->getErrors(true, false)->count(),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Form validation successful, preparing to update request', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'updated_data' => [
                            'type' => $needsAnalysisRequest->getType(),
                            'recipient_name' => $needsAnalysisRequest->getRecipientName(),
                            'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
                            'company_name' => $needsAnalysisRequest->getCompanyName(),
                            'formation_id' => $needsAnalysisRequest->getFormation()?->getId(),
                            'admin_notes_length' => strlen($needsAnalysisRequest->getAdminNotes() ?? ''),
                        ],
                    ]);

                    try {
                        $this->entityManager->flush();

                        $this->addFlash('success', 'Demande d\'analyse des besoins modifiée avec succès.');

                        $this->logger->info('Needs analysis request updated successfully', [
                            'request_id' => $needsAnalysisRequest->getId(),
                            'updated_by' => $this->getUser()?->getUserIdentifier(),
                            'updated_data' => [
                                'type' => $needsAnalysisRequest->getType(),
                                'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
                                'formation_id' => $needsAnalysisRequest->getFormation()?->getId(),
                            ],
                        ]);

                        return $this->redirectToRoute('admin_needs_analysis_show', [
                            'id' => $needsAnalysisRequest->getId(),
                        ]);
                    } catch (Exception $e) {
                        $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());

                        $this->logger->error('Failed to update needs analysis request in database', [
                            'request_id' => $needsAnalysisRequest->getId(),
                            'error_message' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                            'updated_by' => $this->getUser()?->getUserIdentifier(),
                        ]);
                    }
                } else {
                    $this->logger->warning('Form validation failed for needs analysis request edit', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'errors' => array_map(static fn ($error) => $error->getMessage(), iterator_to_array($form->getErrors(true, false))),
                        'submitted_data' => $request->request->all(),
                    ]);
                }
            }

            $this->logger->debug('Rendering edit needs analysis request form', [
                'request_id' => $needsAnalysisRequest->getId(),
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
            ]);

            return $this->render('admin/needs_analysis/edit.html.twig', [
                'form' => $form,
                'request' => $needsAnalysisRequest,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in edit needs analysis request page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $needsAnalysisRequest->getId(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la modification de la demande.');

            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }
    }

    /**
     * Send the needs analysis request to the beneficiary.
     */
    #[Route('/{id}/send', name: 'admin_needs_analysis_send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function send(NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        $this->logger->info('Starting to send needs analysis request', [
            'request_id' => $needsAnalysisRequest->getId(),
            'current_status' => $needsAnalysisRequest->getStatus(),
            'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
            'recipient_name' => $needsAnalysisRequest->getRecipientName(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if ($needsAnalysisRequest->getStatus() !== 'pending') {
                $this->logger->warning('Attempt to send non-pending needs analysis request', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'current_status' => $needsAnalysisRequest->getStatus(),
                    'user_email' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Cette demande ne peut pas être envoyée.');

                return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
            }

            $this->logger->debug('Validating request data before sending', [
                'request_id' => $needsAnalysisRequest->getId(),
                'type' => $needsAnalysisRequest->getType(),
                'formation_id' => $needsAnalysisRequest->getFormation()?->getId(),
                'formation_title' => $needsAnalysisRequest->getFormation()?->getTitle(),
                'company_name' => $needsAnalysisRequest->getCompanyName(),
                'expires_at' => $needsAnalysisRequest->getExpiresAt()?->format('Y-m-d H:i:s'),
                'token' => substr($needsAnalysisRequest->getToken(), 0, 8) . '...',
            ]);

            try {
                $this->needsAnalysisService->sendNeedsAnalysisRequest($needsAnalysisRequest);

                $this->addFlash('success', 'Demande d\'analyse envoyée avec succès au bénéficiaire.');

                $this->logger->info('Needs analysis request sent successfully', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'sent_by' => $this->getUser()?->getUserIdentifier(),
                    'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
                    'sent_at' => $needsAnalysisRequest->getSentAt()?->format('Y-m-d H:i:s'),
                    'new_status' => $needsAnalysisRequest->getStatus(),
                    'expires_at' => $needsAnalysisRequest->getExpiresAt()?->format('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());

                $this->logger->error('Failed to send needs analysis request via service', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'sent_by' => $this->getUser()?->getUserIdentifier(),
                    'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
                    'request_data' => [
                        'type' => $needsAnalysisRequest->getType(),
                        'formation_id' => $needsAnalysisRequest->getFormation()?->getId(),
                        'company_name' => $needsAnalysisRequest->getCompanyName(),
                    ],
                ]);
            }

            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in send needs analysis request', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $needsAnalysisRequest->getId(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'envoi de la demande.');

            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }
    }

    /**
     * Cancel a needs analysis request.
     */
    #[Route('/{id}/cancel', name: 'admin_needs_analysis_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(Request $request, NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        $this->logger->info('Starting to cancel needs analysis request', [
            'request_id' => $needsAnalysisRequest->getId(),
            'current_status' => $needsAnalysisRequest->getStatus(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if (!in_array($needsAnalysisRequest->getStatus(), ['pending', 'sent'], true)) {
                $this->logger->warning('Attempt to cancel non-cancellable needs analysis request', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'current_status' => $needsAnalysisRequest->getStatus(),
                    'user_email' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Cette demande ne peut pas être annulée.');

                return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
            }

            $reason = $request->request->get('reason', 'Annulée par l\'administrateur');

            $this->logger->debug('Processing cancellation request', [
                'request_id' => $needsAnalysisRequest->getId(),
                'current_status' => $needsAnalysisRequest->getStatus(),
                'cancellation_reason' => $reason,
                'request_data' => [
                    'type' => $needsAnalysisRequest->getType(),
                    'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
                    'formation_id' => $needsAnalysisRequest->getFormation()?->getId(),
                    'created_at' => $needsAnalysisRequest->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'sent_at' => $needsAnalysisRequest->getSentAt()?->format('Y-m-d H:i:s'),
                ],
            ]);

            try {
                $this->needsAnalysisService->cancelRequest($needsAnalysisRequest, $reason);

                $this->addFlash('success', 'Demande d\'analyse annulée avec succès.');

                $this->logger->info('Needs analysis request cancelled successfully', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'cancelled_by' => $this->getUser()?->getUserIdentifier(),
                    'reason' => $reason,
                    'previous_status' => $needsAnalysisRequest->getStatus(),
                    'new_status' => 'cancelled',
                    'cancelled_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'annulation : ' . $e->getMessage());

                $this->logger->error('Failed to cancel needs analysis request via service', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'cancelled_by' => $this->getUser()?->getUserIdentifier(),
                    'cancellation_reason' => $reason,
                ]);
            }

            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in cancel needs analysis request', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $needsAnalysisRequest->getId(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'annulation de la demande.');

            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }
    }

    /**
     * Delete a needs analysis request (only if pending).
     */
    #[Route('/{id}/delete', name: 'admin_needs_analysis_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        $requestId = $needsAnalysisRequest->getId();

        $this->logger->info('Starting to delete needs analysis request', [
            'request_id' => $requestId,
            'current_status' => $needsAnalysisRequest->getStatus(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if ($needsAnalysisRequest->getStatus() !== 'pending') {
                $this->logger->warning('Attempt to delete non-pending needs analysis request', [
                    'request_id' => $requestId,
                    'current_status' => $needsAnalysisRequest->getStatus(),
                    'user_email' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Seules les demandes en attente peuvent être supprimées.');

                return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $requestId]);
            }

            $this->logger->debug('Preparing to delete needs analysis request', [
                'request_id' => $requestId,
                'request_data' => [
                    'type' => $needsAnalysisRequest->getType(),
                    'recipient_email' => $needsAnalysisRequest->getRecipientEmail(),
                    'recipient_name' => $needsAnalysisRequest->getRecipientName(),
                    'company_name' => $needsAnalysisRequest->getCompanyName(),
                    'formation_id' => $needsAnalysisRequest->getFormation()?->getId(),
                    'formation_title' => $needsAnalysisRequest->getFormation()?->getTitle(),
                    'created_at' => $needsAnalysisRequest->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'admin_notes_length' => strlen($needsAnalysisRequest->getAdminNotes() ?? ''),
                ],
            ]);

            try {
                $this->entityManager->remove($needsAnalysisRequest);
                $this->entityManager->flush();

                $this->addFlash('success', 'Demande d\'analyse supprimée avec succès.');

                $this->logger->info('Needs analysis request deleted successfully', [
                    'request_id' => $requestId,
                    'deleted_by' => $this->getUser()?->getUserIdentifier(),
                    'deleted_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);

                return $this->redirectToRoute('admin_needs_analysis_index');
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());

                $this->logger->error('Failed to delete needs analysis request from database', [
                    'request_id' => $requestId,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'deleted_by' => $this->getUser()?->getUserIdentifier(),
                ]);

                return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $requestId]);
            }
        } catch (Exception $e) {
            $this->logger->error('Critical error in delete needs analysis request', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $requestId,
                'user_email' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la suppression de la demande.');

            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $requestId]);
        }
    }

    /**
     * Add admin notes to a needs analysis request.
     */
    #[Route('/{id}/notes', name: 'admin_needs_analysis_add_notes', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addNotes(Request $request, NeedsAnalysisRequest $needsAnalysisRequest): Response
    {
        $this->logger->info('Starting to add admin notes to needs analysis request', [
            'request_id' => $needsAnalysisRequest->getId(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $newNote = trim($request->request->get('note', ''));

            $this->logger->debug('Processing new admin note', [
                'request_id' => $needsAnalysisRequest->getId(),
                'note_length' => strlen($newNote),
                'note_preview' => substr($newNote, 0, 100) . (strlen($newNote) > 100 ? '...' : ''),
                'current_notes_length' => strlen($needsAnalysisRequest->getAdminNotes() ?? ''),
            ]);

            if (empty($newNote)) {
                $this->logger->warning('Attempt to add empty admin note', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'user_email' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'La note ne peut pas être vide.');

                return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
            }

            try {
                $currentNotes = $needsAnalysisRequest->getAdminNotes();
                $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
                $adminName = $this->getUser()?->getUserIdentifier() ?? 'Admin';
                $formattedNote = "[{$timestamp}] {$adminName}: {$newNote}";
                $updatedNotes = $currentNotes ? $currentNotes . "\n" . $formattedNote : $formattedNote;

                $this->logger->debug('Formatted admin note prepared', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'formatted_note' => $formattedNote,
                    'total_notes_length_after' => strlen($updatedNotes),
                    'notes_count_before' => $currentNotes ? substr_count($currentNotes, "\n") + 1 : 0,
                    'notes_count_after' => substr_count($updatedNotes, "\n") + 1,
                ]);

                $needsAnalysisRequest->setAdminNotes($updatedNotes);
                $this->entityManager->flush();

                $this->addFlash('success', 'Note ajoutée avec succès.');

                $this->logger->info('Admin note added successfully to needs analysis request', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'added_by' => $this->getUser()?->getUserIdentifier(),
                    'note_length' => strlen($newNote),
                    'total_notes_length' => strlen($updatedNotes),
                    'timestamp' => $timestamp,
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'ajout de la note : ' . $e->getMessage());

                $this->logger->error('Failed to add admin note to database', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'added_by' => $this->getUser()?->getUserIdentifier(),
                    'note_length' => strlen($newNote),
                ]);
            }

            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in add admin notes to needs analysis request', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $needsAnalysisRequest->getId(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'ajout de la note.');

            return $this->redirectToRoute('admin_needs_analysis_show', ['id' => $needsAnalysisRequest->getId()]);
        }
    }
}
