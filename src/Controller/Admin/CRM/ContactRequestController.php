<?php

declare(strict_types=1);

namespace App\Controller\Admin\CRM;

use App\Entity\CRM\ContactRequest;
use App\Form\CRM\ContactRequestType;
use App\Repository\CRM\ContactRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Contact Request Controller.
 *
 * Handles CRUD operations for contact requests in the admin interface.
 * Provides full management capabilities for EPROFOS contact requests.
 */
#[Route('/admin/contact-requests')]
#[IsGranted('ROLE_ADMIN')]
class ContactRequestController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * List all contact requests with filtering and pagination.
     */
    #[Route('/', name: 'admin_contact_request_index', methods: ['GET'])]
    public function index(Request $request, ContactRequestRepository $contactRequestRepository): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin contact requests list access initiated', [
            'user' => $userIdentifier,
            'request_id' => uniqid('contact_request_index_', true),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        try {
            // Extract and validate request parameters
            $status = $request->query->get('status');
            $type = $request->query->get('type');

            $this->logger->debug('Processing contact request filters', [
                'user' => $userIdentifier,
                'filters' => [
                    'status' => $status,
                    'type' => $type,
                ],
            ]);

            // Build the query with detailed logging
            $this->logger->debug('Building contact request query', [
                'user' => $userIdentifier,
                'action' => 'query_builder_creation',
            ]);

            $queryBuilder = $contactRequestRepository->createQueryBuilder('cr')
                ->leftJoin('cr.formation', 'f')
                ->leftJoin('cr.service', 's')
                ->addSelect('f', 's')
                ->orderBy('cr.createdAt', 'DESC')
            ;

            // Apply filters with detailed logging
            if ($status) {
                $this->logger->debug('Applying status filter to contact requests', [
                    'user' => $userIdentifier,
                    'filter_type' => 'status',
                    'filter_value' => $status,
                ]);
                $queryBuilder->andWhere('cr.status = :status')
                    ->setParameter('status', $status)
                ;
            }

            if ($type) {
                $this->logger->debug('Applying type filter to contact requests', [
                    'user' => $userIdentifier,
                    'filter_type' => 'type',
                    'filter_value' => $type,
                ]);
                $queryBuilder->andWhere('cr.type = :type')
                    ->setParameter('type', $type)
                ;
            }

            // Execute query and measure performance
            $queryStartTime = microtime(true);
            $contactRequests = $queryBuilder->getQuery()->getResult();
            $queryExecutionTime = microtime(true) - $queryStartTime;

            $this->logger->info('Contact requests query executed successfully', [
                'user' => $userIdentifier,
                'contact_requests_count' => count($contactRequests),
                'query_execution_time' => number_format($queryExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ]);

            // Get statistics with error handling
            $this->logger->debug('Fetching contact request statistics', [
                'user' => $userIdentifier,
                'action' => 'statistics_fetch',
            ]);

            $statisticsStartTime = microtime(true);
            $statistics = $contactRequestRepository->getStatistics();
            $statusCounts = $contactRequestRepository->countByStatus();
            $typeCounts = $contactRequestRepository->countByType();
            $statisticsExecutionTime = microtime(true) - $statisticsStartTime;

            $this->logger->debug('Contact request statistics fetched successfully', [
                'user' => $userIdentifier,
                'statistics_fetch_time' => number_format($statisticsExecutionTime, 4) . 's',
                'statistics_summary' => [
                    'total_requests' => $statistics['total'] ?? 0,
                    'status_variations' => count($statusCounts),
                    'type_variations' => count($typeCounts),
                ],
            ]);

            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->info('Admin contact requests list rendered successfully', [
                'user' => $userIdentifier,
                'total_execution_time' => number_format($totalExecutionTime, 4) . 's',
                'requests_displayed' => count($contactRequests),
                'filters_applied' => array_filter([
                    'status' => $status,
                    'type' => $type,
                ]),
                'final_memory_usage' => memory_get_usage(true),
            ]);

            return $this->render('admin/contact_request/index.html.twig', [
                'contact_requests' => $contactRequests,
                'statistics' => $statistics,
                'status_counts' => $statusCounts,
                'type_counts' => $typeCounts,
                'current_status' => $status,
                'current_type' => $type,
                'page_title' => 'Gestion des demandes de contact',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Demandes de contact', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in contact request index action', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_parameters' => [
                    'status' => $status ?? null,
                    'type' => $type ?? null,
                ],
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de la liste des demandes de contact. Veuillez réessayer.');

            // Return a minimal view with error state
            return $this->render('admin/contact_request/index.html.twig', [
                'contact_requests' => [],
                'statistics' => ['total' => 0, 'pending' => 0, 'processed' => 0, 'closed' => 0],
                'status_counts' => [],
                'type_counts' => [],
                'current_status' => null,
                'current_type' => null,
                'page_title' => 'Gestion des demandes de contact',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Demandes de contact', 'url' => null],
                ],
                'error_state' => true,
            ]);
        }
    }

    /**
     * Show contact request details.
     */
    #[Route('/{id}', name: 'admin_contact_request_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ContactRequest $contactRequest): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $contactRequestId = $contactRequest->getId();

        $this->logger->info('Admin contact request details view initiated', [
            'user' => $userIdentifier,
            'contact_request_id' => $contactRequestId,
            'contact_request_type' => $contactRequest->getType(),
            'contact_request_status' => $contactRequest->getStatus(),
            'requester_name' => $contactRequest->getFirstName() . ' ' . $contactRequest->getLastName(),
            'requester_email' => $contactRequest->getEmail(),
            'request_id' => uniqid('contact_request_show_', true),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $this->logger->debug('Contact request details accessed', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'contact_request_data' => [
                    'type' => $contactRequest->getType(),
                    'status' => $contactRequest->getStatus(),
                    'subject' => $contactRequest->getSubject(),
                    'requester' => $contactRequest->getFirstName() . ' ' . $contactRequest->getLastName(),
                    'email' => $contactRequest->getEmail(),
                    'phone' => $contactRequest->getPhone(),
                    'company' => $contactRequest->getCompany(),
                    'formation_id' => $contactRequest->getFormation()?->getId(),
                    'formation_title' => $contactRequest->getFormation()?->getTitle(),
                    'service_id' => $contactRequest->getService()?->getId(),
                    'service_title' => $contactRequest->getService()?->getTitle(),
                    'created_at' => $contactRequest->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'processed_at' => $contactRequest->getProcessedAt()?->format('Y-m-d H:i:s'),
                ],
            ]);

            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->info('Admin contact request details rendered successfully', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'total_execution_time' => number_format($totalExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
                'contact_request_metadata' => [
                    'type' => $contactRequest->getType(),
                    'status' => $contactRequest->getStatus(),
                    'has_formation' => $contactRequest->getFormation() !== null,
                    'has_service' => $contactRequest->getService() !== null,
                    'is_processed' => $contactRequest->getProcessedAt() !== null,
                ],
            ]);

            return $this->render('admin/contact_request/show.html.twig', [
                'contact_request' => $contactRequest,
                'page_title' => 'Détails de la demande',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Demandes de contact', 'url' => $this->generateUrl('admin_contact_request_index')],
                    ['label' => 'Demande #' . $contactRequest->getId(), 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in contact request show action', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails de la demande de contact. Veuillez réessayer.');

            return $this->redirectToRoute('admin_contact_request_index');
        }
    }

    /**
     * Edit an existing contact request (status and admin notes).
     */
    #[Route('/{id}/edit', name: 'admin_contact_request_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, ContactRequest $contactRequest, EntityManagerInterface $entityManager): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $contactRequestId = $contactRequest->getId();

        $this->logger->info('Admin contact request edit initiated', [
            'user' => $userIdentifier,
            'contact_request_id' => $contactRequestId,
            'contact_request_status' => $contactRequest->getStatus(),
            'contact_request_type' => $contactRequest->getType(),
            'request_method' => $request->getMethod(),
            'request_id' => uniqid('contact_request_edit_', true),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Store original data for comparison
            $originalData = [
                'status' => $contactRequest->getStatus(),
                'admin_notes' => $contactRequest->getAdminNotes(),
                'processed_at' => $contactRequest->getProcessedAt()?->format('Y-m-d H:i:s'),
            ];

            $this->logger->debug('Original contact request data captured', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'original_data' => $originalData,
            ]);

            $form = $this->createForm(ContactRequestType::class, $contactRequest);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('Contact request edit form submitted', [
                    'user' => $userIdentifier,
                    'contact_request_id' => $contactRequestId,
                    'form_valid' => $form->isValid(),
                ]);

                if ($form->isValid()) {
                    // Capture changes
                    $newData = [
                        'status' => $contactRequest->getStatus(),
                        'admin_notes' => $contactRequest->getAdminNotes(),
                        'processed_at' => $contactRequest->getProcessedAt()?->format('Y-m-d H:i:s'),
                    ];

                    $changes = [];
                    foreach ($originalData as $field => $originalValue) {
                        if ($originalValue !== $newData[$field]) {
                            $changes[$field] = [
                                'from' => $originalValue,
                                'to' => $newData[$field],
                            ];
                        }
                    }

                    $this->logger->debug('Contact request changes detected', [
                        'user' => $userIdentifier,
                        'contact_request_id' => $contactRequestId,
                        'changes_count' => count($changes),
                        'changes' => $changes,
                    ]);

                    // Mark as processed if status changed from pending
                    if ($contactRequest->getStatus() !== 'pending' && !$contactRequest->getProcessedAt()) {
                        $contactRequest->markAsProcessed();
                        $this->logger->debug('Contact request marked as processed', [
                            'user' => $userIdentifier,
                            'contact_request_id' => $contactRequestId,
                            'new_status' => $contactRequest->getStatus(),
                            'processed_timestamp' => $contactRequest->getProcessedAt()?->format('Y-m-d H:i:s'),
                        ]);
                    }

                    $persistStartTime = microtime(true);
                    $entityManager->flush();
                    $persistExecutionTime = microtime(true) - $persistStartTime;

                    $this->logger->info('Contact request updated successfully', [
                        'contact_request_id' => $contactRequestId,
                        'user' => $userIdentifier,
                        'changes_applied' => $changes,
                        'persist_time' => number_format($persistExecutionTime, 4) . 's',
                        'total_execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                        'final_status' => $contactRequest->getStatus(),
                        'is_processed' => $contactRequest->getProcessedAt() !== null,
                    ]);

                    $this->addFlash('success', 'La demande de contact a été modifiée avec succès.');

                    return $this->redirectToRoute('admin_contact_request_show', ['id' => $contactRequest->getId()]);
                }
                $this->logger->warning('Contact request edit form validation failed', [
                    'user' => $userIdentifier,
                    'contact_request_id' => $contactRequestId,
                    'form_errors' => (string) $form->getErrors(true),
                ]);
            }

            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->debug('Contact request edit form rendered', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'request_method' => $request->getMethod(),
                'execution_time' => number_format($totalExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

            return $this->render('admin/contact_request/edit.html.twig', [
                'contact_request' => $contactRequest,
                'form' => $form,
                'page_title' => 'Modifier la demande',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Demandes de contact', 'url' => $this->generateUrl('admin_contact_request_index')],
                    ['label' => 'Demande #' . $contactRequest->getId(), 'url' => $this->generateUrl('admin_contact_request_show', ['id' => $contactRequest->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in contact request edit action', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'request_method' => $request->getMethod(),
                'form_submitted' => $request->isMethod('POST'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification de la demande de contact. Veuillez réessayer.');

            return $this->redirectToRoute('admin_contact_request_show', ['id' => $contactRequest->getId()]);
        }
    }

    /**
     * Update contact request status.
     */
    #[Route('/{id}/status', name: 'admin_contact_request_update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(Request $request, ContactRequest $contactRequest, EntityManagerInterface $entityManager): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $contactRequestId = $contactRequest->getId();

        $this->logger->info('Admin contact request status update initiated', [
            'user' => $userIdentifier,
            'contact_request_id' => $contactRequestId,
            'current_status' => $contactRequest->getStatus(),
            'request_id' => uniqid('contact_status_update_', true),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            $newStatus = $request->getPayload()->get('status');
            $csrfToken = $request->getPayload()->get('_token');
            $expectedToken = 'update_status' . $contactRequestId;

            $this->logger->debug('Status update parameters received', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'old_status' => $contactRequest->getStatus(),
                'new_status' => $newStatus,
                'token_provided' => !empty($csrfToken),
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                $oldStatus = $contactRequest->getStatus();
                $contactRequest->setStatus($newStatus);

                // Mark as processed if status changed from pending
                if ($newStatus !== 'pending' && !$contactRequest->getProcessedAt()) {
                    $contactRequest->markAsProcessed();
                    $this->logger->debug('Contact request marked as processed during status update', [
                        'user' => $userIdentifier,
                        'contact_request_id' => $contactRequestId,
                        'processed_timestamp' => $contactRequest->getProcessedAt()?->format('Y-m-d H:i:s'),
                    ]);
                }

                $persistStartTime = microtime(true);
                $entityManager->flush();
                $persistExecutionTime = microtime(true) - $persistStartTime;

                $this->logger->info('Contact request status updated successfully', [
                    'contact_request_id' => $contactRequestId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'user' => $userIdentifier,
                    'persist_time' => number_format($persistExecutionTime, 4) . 's',
                    'total_execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                    'is_now_processed' => $contactRequest->getProcessedAt() !== null,
                ]);

                $this->addFlash('success', 'Le statut de la demande a été mis à jour avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for contact request status update', [
                    'user' => $userIdentifier,
                    'contact_request_id' => $contactRequestId,
                    'old_status' => $contactRequest->getStatus(),
                    'attempted_new_status' => $newStatus,
                    'expected_token_name' => $expectedToken,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error in contact request status update action', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'current_status' => $contactRequest->getStatus(),
                'attempted_new_status' => $newStatus ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour du statut. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_contact_request_show', ['id' => $contactRequest->getId()]);
    }

    /**
     * Delete a contact request.
     */
    #[Route('/{id}', name: 'admin_contact_request_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ContactRequest $contactRequest, EntityManagerInterface $entityManager): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $contactRequestId = $contactRequest->getId();

        $this->logger->info('Admin contact request deletion initiated', [
            'user' => $userIdentifier,
            'contact_request_id' => $contactRequestId,
            'contact_request_type' => $contactRequest->getType(),
            'contact_request_status' => $contactRequest->getStatus(),
            'requester' => $contactRequest->getFirstName() . ' ' . $contactRequest->getLastName(),
            'requester_email' => $contactRequest->getEmail(),
            'request_id' => uniqid('contact_request_delete_', true),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            $csrfToken = $request->getPayload()->get('_token');
            $expectedToken = 'delete' . $contactRequestId;

            $this->logger->debug('CSRF token validation for contact request deletion', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'token_provided' => !empty($csrfToken),
                'token_length' => $csrfToken ? strlen($csrfToken) : 0,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                // Capture contact request data before deletion for audit
                $contactRequestDataSnapshot = [
                    'id' => $contactRequestId,
                    'type' => $contactRequest->getType(),
                    'status' => $contactRequest->getStatus(),
                    'first_name' => $contactRequest->getFirstName(),
                    'last_name' => $contactRequest->getLastName(),
                    'email' => $contactRequest->getEmail(),
                    'phone' => $contactRequest->getPhone(),
                    'company' => $contactRequest->getCompany(),
                    'subject' => $contactRequest->getSubject(),
                    'message' => strlen($contactRequest->getMessage() ?? ''),
                    'formation_id' => $contactRequest->getFormation()?->getId(),
                    'formation_title' => $contactRequest->getFormation()?->getTitle(),
                    'service_id' => $contactRequest->getService()?->getId(),
                    'service_title' => $contactRequest->getService()?->getTitle(),
                    'created_at' => $contactRequest->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'processed_at' => $contactRequest->getProcessedAt()?->format('Y-m-d H:i:s'),
                    'admin_notes' => strlen($contactRequest->getAdminNotes() ?? ''),
                ];

                $this->logger->debug('Contact request data snapshot captured before deletion', [
                    'user' => $userIdentifier,
                    'contact_request_data' => $contactRequestDataSnapshot,
                ]);

                $deleteStartTime = microtime(true);
                $entityManager->remove($contactRequest);
                $entityManager->flush();
                $deleteExecutionTime = microtime(true) - $deleteStartTime;

                $this->logger->info('Contact request deleted successfully', [
                    'contact_request_id' => $contactRequestId,
                    'user' => $userIdentifier,
                    'deleted_data' => $contactRequestDataSnapshot,
                    'delete_time' => number_format($deleteExecutionTime, 4) . 's',
                    'total_execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                    'deletion_timestamp' => date('Y-m-d H:i:s'),
                ]);

                $this->addFlash('success', 'La demande de contact a été supprimée avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for contact request deletion', [
                    'user' => $userIdentifier,
                    'contact_request_id' => $contactRequestId,
                    'token_provided' => !empty($csrfToken),
                    'expected_token_name' => $expectedToken,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error in contact request delete action', [
                'user' => $userIdentifier,
                'contact_request_id' => $contactRequestId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'csrf_token_valid' => isset($csrfToken) ? 'checked' : 'not_checked',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la demande de contact. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_contact_request_index');
    }

    /**
     * Export contact requests to CSV.
     */
    #[Route('/export', name: 'admin_contact_request_export', methods: ['GET'])]
    public function export(ContactRequestRepository $contactRequestRepository): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin contact requests export initiated', [
            'user' => $userIdentifier,
            'request_id' => uniqid('contact_request_export_', true),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $this->logger->debug('Fetching all contact requests for export', [
                'user' => $userIdentifier,
                'action' => 'fetch_all_contact_requests',
            ]);

            $fetchStartTime = microtime(true);
            $contactRequests = $contactRequestRepository->findAll();
            $fetchExecutionTime = microtime(true) - $fetchStartTime;

            $this->logger->debug('Contact requests fetched for export', [
                'user' => $userIdentifier,
                'contact_requests_count' => count($contactRequests),
                'fetch_time' => number_format($fetchExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

            $response = new Response();
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="demandes_contact_' . date('Y-m-d') . '.csv"');

            $this->logger->debug('Creating CSV export', [
                'user' => $userIdentifier,
                'filename' => 'demandes_contact_' . date('Y-m-d') . '.csv',
                'records_to_export' => count($contactRequests),
            ]);

            $csvStartTime = microtime(true);
            $output = fopen('php://output', 'w');

            // CSV headers
            fputcsv($output, [
                'ID',
                'Type',
                'Prénom',
                'Nom',
                'Email',
                'Téléphone',
                'Entreprise',
                'Sujet',
                'Message',
                'Statut',
                'Formation',
                'Service',
                'Créé le',
                'Traité le',
            ]);

            // CSV data with detailed tracking
            $exportedRecords = 0;
            foreach ($contactRequests as $request) {
                fputcsv($output, [
                    $request->getId(),
                    $request->getTypeLabel(),
                    $request->getFirstName(),
                    $request->getLastName(),
                    $request->getEmail(),
                    $request->getPhone(),
                    $request->getCompany(),
                    $request->getSubject(),
                    $request->getMessage(),
                    $request->getStatusLabel(),
                    $request->getFormation()?->getTitle(),
                    $request->getService()?->getTitle(),
                    $request->getCreatedAt()?->format('d/m/Y H:i'),
                    $request->getProcessedAt()?->format('d/m/Y H:i'),
                ]);
                $exportedRecords++;
            }

            fclose($output);
            $csvExecutionTime = microtime(true) - $csvStartTime;
            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->info('Contact requests exported successfully', [
                'user' => $userIdentifier,
                'total_records' => count($contactRequests),
                'exported_records' => $exportedRecords,
                'filename' => 'demandes_contact_' . date('Y-m-d') . '.csv',
                'fetch_time' => number_format($fetchExecutionTime, 4) . 's',
                'csv_generation_time' => number_format($csvExecutionTime, 4) . 's',
                'total_execution_time' => number_format($totalExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'export_timestamp' => date('Y-m-d H:i:s'),
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error in contact request export action', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'export des demandes de contact. Veuillez réessayer.');

            return $this->redirectToRoute('admin_contact_request_index');
        }
    }
}
