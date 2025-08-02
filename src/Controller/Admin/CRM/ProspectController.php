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
use Exception;
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
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin prospects list access initiated', [
            'user' => $userIdentifier,
            'request_id' => uniqid('prospect_index_', true),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        try {
            // Extract and validate request parameters
            $status = $request->query->get('status');
            $priority = $request->query->get('priority');
            $source = $request->query->get('source');
            $assignedTo = $request->query->get('assigned_to');
            $search = $request->query->get('search');

            $this->logger->debug('Processing prospect index filters', [
                'user' => $userIdentifier,
                'filters' => [
                    'status' => $status,
                    'priority' => $priority,
                    'source' => $source,
                    'assigned_to' => $assignedTo,
                    'search' => $search ? '[REDACTED:' . strlen($search) . ' chars]' : null,
                ],
            ]);

            // Build the query with detailed logging
            $this->logger->debug('Building prospect query', [
                'user' => $userIdentifier,
                'action' => 'query_builder_creation',
            ]);

            $queryBuilder = $prospectRepository->createQueryBuilder('p')
                ->leftJoin('p.assignedTo', 'u')
                ->leftJoin('p.interestedFormations', 'f')
                ->leftJoin('p.interestedServices', 's')
                ->addSelect('u', 'f', 's')
                ->orderBy('p.updatedAt', 'DESC')
            ;

            // Apply filters with detailed logging
            if ($status) {
                $this->logger->debug('Applying status filter', [
                    'user' => $userIdentifier,
                    'filter_type' => 'status',
                    'filter_value' => $status,
                ]);
                $queryBuilder->andWhere('p.status = :status')
                    ->setParameter('status', $status)
                ;
            }

            if ($priority) {
                $this->logger->debug('Applying priority filter', [
                    'user' => $userIdentifier,
                    'filter_type' => 'priority',
                    'filter_value' => $priority,
                ]);
                $queryBuilder->andWhere('p.priority = :priority')
                    ->setParameter('priority', $priority)
                ;
            }

            if ($source) {
                $this->logger->debug('Applying source filter', [
                    'user' => $userIdentifier,
                    'filter_type' => 'source',
                    'filter_value' => $source,
                ]);
                $queryBuilder->andWhere('p.source = :source')
                    ->setParameter('source', $source)
                ;
            }

            if ($assignedTo) {
                $this->logger->debug('Applying assigned_to filter', [
                    'user' => $userIdentifier,
                    'filter_type' => 'assigned_to',
                    'filter_value' => $assignedTo,
                ]);
                $queryBuilder->andWhere('p.assignedTo = :assignedTo')
                    ->setParameter('assignedTo', $assignedTo)
                ;
            }

            if ($search) {
                $this->logger->debug('Applying search filter', [
                    'user' => $userIdentifier,
                    'filter_type' => 'search',
                    'search_length' => strlen($search),
                ]);
                $searchTerms = '%' . strtolower($search) . '%';
                $queryBuilder->andWhere(
                    'LOWER(p.firstName) LIKE :search OR 
                     LOWER(p.lastName) LIKE :search OR 
                     LOWER(p.email) LIKE :search OR 
                     LOWER(p.company) LIKE :search',
                )->setParameter('search', $searchTerms);
            }

            // Execute query and measure performance
            $queryStartTime = microtime(true);
            $prospects = $queryBuilder->getQuery()->getResult();
            $queryExecutionTime = microtime(true) - $queryStartTime;

            $this->logger->info('Prospects query executed successfully', [
                'user' => $userIdentifier,
                'prospects_count' => count($prospects),
                'query_execution_time' => number_format($queryExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ]);

            // Get statistics with error handling
            $this->logger->debug('Fetching prospect statistics', [
                'user' => $userIdentifier,
                'action' => 'statistics_fetch',
            ]);

            $statistics = $prospectRepository->getDashboardStatistics();
            $statusCounts = $prospectRepository->countByStatus();
            $priorityCounts = $prospectRepository->countByPriority();
            $sourceCounts = $prospectRepository->countBySource();

            $this->logger->debug('Statistics fetched successfully', [
                'user' => $userIdentifier,
                'statistics_summary' => [
                    'total_prospects' => $statistics['total'] ?? 0,
                    'status_variations' => count($statusCounts),
                    'priority_variations' => count($priorityCounts),
                    'source_variations' => count($sourceCounts),
                ],
            ]);

            // Get all users for assignment filter
            $this->logger->debug('Fetching admin users for filters', [
                'user' => $userIdentifier,
                'action' => 'users_fetch',
            ]);

            $users = $userRepository->findAll();

            $this->logger->debug('Admin users fetched', [
                'user' => $userIdentifier,
                'users_count' => count($users),
            ]);

            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->info('Admin prospects list rendered successfully', [
                'user' => $userIdentifier,
                'total_execution_time' => number_format($totalExecutionTime, 4) . 's',
                'prospects_displayed' => count($prospects),
                'filters_applied' => array_filter([
                    'status' => $status,
                    'priority' => $priority,
                    'source' => $source,
                    'assigned_to' => $assignedTo,
                    'search' => $search ? true : false,
                ]),
                'final_memory_usage' => memory_get_usage(true),
            ]);

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
        } catch (Exception $e) {
            $this->logger->error('Error in prospect index action', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_parameters' => [
                    'status' => $status ?? null,
                    'priority' => $priority ?? null,
                    'source' => $source ?? null,
                    'assigned_to' => $assignedTo ?? null,
                    'search_length' => isset($search) ? strlen($search) : 0,
                ],
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de la liste des prospects. Veuillez réessayer.');

            // Return a minimal view with error state
            return $this->render('admin/prospect/index.html.twig', [
                'prospects' => [],
                'statistics' => ['total' => 0, 'new' => 0, 'in_progress' => 0, 'converted' => 0],
                'status_counts' => [],
                'priority_counts' => [],
                'source_counts' => [],
                'users' => [],
                'current_status' => null,
                'current_priority' => null,
                'current_source' => null,
                'current_assigned_to' => null,
                'current_search' => null,
                'page_title' => 'Gestion des prospects',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Prospects', 'url' => null],
                ],
                'error_state' => true,
            ]);
        }
    }

    /**
     * Show prospect details with notes.
     */
    #[Route('/{id}', name: 'admin_prospect_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Prospect $prospect, ProspectNoteRepository $noteRepository): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $prospectId = $prospect->getId();

        $this->logger->info('Admin prospect details view initiated', [
            'user' => $userIdentifier,
            'prospect_id' => $prospectId,
            'prospect_name' => $prospect->getFullName(),
            'prospect_status' => $prospect->getStatus(),
            'request_id' => uniqid('prospect_show_', true),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $this->logger->debug('Fetching prospect notes', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'action' => 'notes_fetch',
            ]);

            $notesStartTime = microtime(true);
            $notes = $noteRepository->findByProspect($prospect);
            $notesExecutionTime = microtime(true) - $notesStartTime;

            $this->logger->debug('Prospect notes fetched successfully', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'notes_count' => count($notes),
                'notes_fetch_time' => number_format($notesExecutionTime, 4) . 's',
            ]);

            $this->logger->debug('Fetching prospect note statistics', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'action' => 'note_statistics_fetch',
            ]);

            $statisticsStartTime = microtime(true);
            $noteStatistics = $noteRepository->getProspectNoteStatistics($prospect);
            $statisticsExecutionTime = microtime(true) - $statisticsStartTime;

            $this->logger->debug('Note statistics fetched successfully', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'statistics_fetch_time' => number_format($statisticsExecutionTime, 4) . 's',
                'statistics_summary' => [
                    'total_notes' => $noteStatistics['total'] ?? 0,
                    'note_types' => array_keys($noteStatistics),
                ],
            ]);

            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->info('Admin prospect details rendered successfully', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'prospect_name' => $prospect->getFullName(),
                'total_execution_time' => number_format($totalExecutionTime, 4) . 's',
                'notes_displayed' => count($notes),
                'memory_usage' => memory_get_usage(true),
                'prospect_data' => [
                    'status' => $prospect->getStatus(),
                    'priority' => $prospect->getPriority(),
                    'source' => $prospect->getSource(),
                    'assigned_to' => $prospect->getAssignedTo()?->getFullName(),
                    'created_at' => $prospect->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'last_contact' => $prospect->getLastContactDate()?->format('Y-m-d H:i:s'),
                ],
            ]);

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
        } catch (Exception $e) {
            $this->logger->error('Error in prospect show action', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'prospect_name' => $prospect->getFullName(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails du prospect. Veuillez réessayer.');

            return $this->redirectToRoute('admin_prospect_index');
        }
    }

    /**
     * Create a new prospect.
     */
    #[Route('/new', name: 'admin_prospect_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin prospect creation initiated', [
            'user' => $userIdentifier,
            'request_method' => $request->getMethod(),
            'request_id' => uniqid('prospect_new_', true),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            $prospect = new Prospect();

            $this->logger->debug('Creating prospect form', [
                'user' => $userIdentifier,
                'action' => 'form_creation',
            ]);

            $form = $this->createForm(ProspectType::class, $prospect);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('Prospect form submitted', [
                    'user' => $userIdentifier,
                    'form_valid' => $form->isValid(),
                    'submitted_data' => [
                        'first_name' => $prospect->getFirstName(),
                        'last_name' => $prospect->getLastName(),
                        'email' => $prospect->getEmail(),
                        'company' => $prospect->getCompany(),
                        'status' => $prospect->getStatus(),
                        'priority' => $prospect->getPriority(),
                        'source' => $prospect->getSource(),
                    ],
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Persisting new prospect', [
                        'user' => $userIdentifier,
                        'prospect_data' => [
                            'name' => $prospect->getFirstName() . ' ' . $prospect->getLastName(),
                            'email' => $prospect->getEmail(),
                            'company' => $prospect->getCompany(),
                            'status' => $prospect->getStatus(),
                            'priority' => $prospect->getPriority(),
                        ],
                    ]);

                    $persistStartTime = microtime(true);
                    $entityManager->persist($prospect);
                    $entityManager->flush();
                    $persistExecutionTime = microtime(true) - $persistStartTime;

                    $this->logger->info('New prospect created successfully', [
                        'prospect_id' => $prospect->getId(),
                        'prospect_name' => $prospect->getFullName(),
                        'prospect_email' => $prospect->getEmail(),
                        'prospect_company' => $prospect->getCompany(),
                        'prospect_status' => $prospect->getStatus(),
                        'prospect_priority' => $prospect->getPriority(),
                        'prospect_source' => $prospect->getSource(),
                        'admin' => $userIdentifier,
                        'persist_time' => number_format($persistExecutionTime, 4) . 's',
                        'total_execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                        'creation_timestamp' => $prospect->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ]);

                    $this->addFlash('success', 'Le prospect a été créé avec succès.');

                    return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
                }
                $this->logger->warning('Prospect form validation failed', [
                    'user' => $userIdentifier,
                    'form_errors' => (string) $form->getErrors(true),
                    'submitted_data' => [
                        'first_name' => $prospect->getFirstName(),
                        'last_name' => $prospect->getLastName(),
                        'email' => $prospect->getEmail(),
                        'company' => $prospect->getCompany(),
                    ],
                ]);
            }

            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->debug('Prospect new form rendered', [
                'user' => $userIdentifier,
                'request_method' => $request->getMethod(),
                'execution_time' => number_format($totalExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

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
        } catch (Exception $e) {
            $this->logger->error('Error in prospect new action', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'request_method' => $request->getMethod(),
                'form_submitted' => $request->isMethod('POST'),
                'memory_usage' => memory_get_usage(true),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du prospect. Veuillez réessayer.');

            return $this->redirectToRoute('admin_prospect_index');
        }
    }

    /**
     * Edit an existing prospect.
     */
    #[Route('/{id}/edit', name: 'admin_prospect_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Prospect $prospect, EntityManagerInterface $entityManager): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $prospectId = $prospect->getId();

        $this->logger->info('Admin prospect edit initiated', [
            'user' => $userIdentifier,
            'prospect_id' => $prospectId,
            'prospect_name' => $prospect->getFullName(),
            'request_method' => $request->getMethod(),
            'request_id' => uniqid('prospect_edit_', true),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Store original data for comparison
            $originalData = [
                'first_name' => $prospect->getFirstName(),
                'last_name' => $prospect->getLastName(),
                'email' => $prospect->getEmail(),
                'phone' => $prospect->getPhone(),
                'company' => $prospect->getCompany(),
                'position' => $prospect->getPosition(),
                'status' => $prospect->getStatus(),
                'priority' => $prospect->getPriority(),
                'source' => $prospect->getSource(),
                'estimated_budget' => $prospect->getEstimatedBudget(),
                'assigned_to' => $prospect->getAssignedTo()?->getId(),
            ];

            $this->logger->debug('Original prospect data captured', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'original_data' => $originalData,
            ]);

            $form = $this->createForm(ProspectType::class, $prospect);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('Prospect edit form submitted', [
                    'user' => $userIdentifier,
                    'prospect_id' => $prospectId,
                    'form_valid' => $form->isValid(),
                ]);

                if ($form->isValid()) {
                    // Capture changes
                    $newData = [
                        'first_name' => $prospect->getFirstName(),
                        'last_name' => $prospect->getLastName(),
                        'email' => $prospect->getEmail(),
                        'phone' => $prospect->getPhone(),
                        'company' => $prospect->getCompany(),
                        'position' => $prospect->getPosition(),
                        'status' => $prospect->getStatus(),
                        'priority' => $prospect->getPriority(),
                        'source' => $prospect->getSource(),
                        'estimated_budget' => $prospect->getEstimatedBudget(),
                        'assigned_to' => $prospect->getAssignedTo()?->getId(),
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

                    $this->logger->debug('Prospect changes detected', [
                        'user' => $userIdentifier,
                        'prospect_id' => $prospectId,
                        'changes_count' => count($changes),
                        'changes' => $changes,
                    ]);

                    $persistStartTime = microtime(true);
                    $entityManager->flush();
                    $persistExecutionTime = microtime(true) - $persistStartTime;

                    $this->logger->info('Prospect updated successfully', [
                        'prospect_id' => $prospectId,
                        'prospect_name' => $prospect->getFullName(),
                        'user' => $userIdentifier,
                        'changes_applied' => $changes,
                        'persist_time' => number_format($persistExecutionTime, 4) . 's',
                        'total_execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                        'updated_timestamp' => $prospect->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    ]);

                    $this->addFlash('success', 'Le prospect a été modifié avec succès.');

                    return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
                }
                $this->logger->warning('Prospect edit form validation failed', [
                    'user' => $userIdentifier,
                    'prospect_id' => $prospectId,
                    'form_errors' => (string) $form->getErrors(true),
                ]);
            }

            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->debug('Prospect edit form rendered', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'request_method' => $request->getMethod(),
                'execution_time' => number_format($totalExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

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
        } catch (Exception $e) {
            $this->logger->error('Error in prospect edit action', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'prospect_name' => $prospect->getFullName(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'request_method' => $request->getMethod(),
                'form_submitted' => $request->isMethod('POST'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du prospect. Veuillez réessayer.');

            return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
        }
    }

    /**
     * Delete a prospect.
     */
    #[Route('/{id}', name: 'admin_prospect_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Prospect $prospect, EntityManagerInterface $entityManager): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $prospectId = $prospect->getId();
        $prospectName = $prospect->getFullName();

        $this->logger->info('Admin prospect deletion initiated', [
            'user' => $userIdentifier,
            'prospect_id' => $prospectId,
            'prospect_name' => $prospectName,
            'request_id' => uniqid('prospect_delete_', true),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            $csrfToken = $request->getPayload()->get('_token');
            $expectedToken = 'delete' . $prospectId;

            $this->logger->debug('CSRF token validation for prospect deletion', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'token_provided' => !empty($csrfToken),
                'token_length' => $csrfToken ? strlen($csrfToken) : 0,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                // Capture prospect data before deletion for audit
                $prospectDataSnapshot = [
                    'id' => $prospectId,
                    'full_name' => $prospectName,
                    'email' => $prospect->getEmail(),
                    'phone' => $prospect->getPhone(),
                    'company' => $prospect->getCompany(),
                    'position' => $prospect->getPosition(),
                    'status' => $prospect->getStatus(),
                    'priority' => $prospect->getPriority(),
                    'source' => $prospect->getSource(),
                    'estimated_budget' => $prospect->getEstimatedBudget(),
                    'assigned_to' => $prospect->getAssignedTo()?->getFullName(),
                    'created_at' => $prospect->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updated_at' => $prospect->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    'last_contact_date' => $prospect->getLastContactDate()?->format('Y-m-d H:i:s'),
                    'next_follow_up_date' => $prospect->getNextFollowUpDate()?->format('Y-m-d H:i:s'),
                ];

                $this->logger->debug('Prospect data snapshot captured before deletion', [
                    'user' => $userIdentifier,
                    'prospect_data' => $prospectDataSnapshot,
                ]);

                $deleteStartTime = microtime(true);
                $entityManager->remove($prospect);
                $entityManager->flush();
                $deleteExecutionTime = microtime(true) - $deleteStartTime;

                $this->logger->info('Prospect deleted successfully', [
                    'prospect_id' => $prospectId,
                    'prospect_name' => $prospectName,
                    'user' => $userIdentifier,
                    'deleted_data' => $prospectDataSnapshot,
                    'delete_time' => number_format($deleteExecutionTime, 4) . 's',
                    'total_execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                    'deletion_timestamp' => date('Y-m-d H:i:s'),
                ]);

                $this->addFlash('success', 'Le prospect a été supprimé avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for prospect deletion', [
                    'user' => $userIdentifier,
                    'prospect_id' => $prospectId,
                    'prospect_name' => $prospectName,
                    'token_provided' => !empty($csrfToken),
                    'expected_token_name' => $expectedToken,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error in prospect delete action', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'prospect_name' => $prospectName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'csrf_token_valid' => isset($csrfToken) ? 'checked' : 'not_checked',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du prospect. Veuillez réessayer.');
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
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $prospectId = $prospect->getId();

        $this->logger->info('Admin prospect note addition initiated', [
            'user' => $userIdentifier,
            'prospect_id' => $prospectId,
            'prospect_name' => $prospect->getFullName(),
            'request_method' => $request->getMethod(),
            'request_id' => uniqid('prospect_add_note_', true),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $note = new ProspectNote();
            $note->setProspect($prospect);
            $note->setCreatedBy($this->getUser());

            $this->logger->debug('Prospect note entity created', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'note_creator' => $this->getUser()?->getUserIdentifier(),
            ]);

            $form = $this->createForm(ProspectNoteType::class, $note, [
                'prospect_context' => true,
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('Prospect note form submitted', [
                    'user' => $userIdentifier,
                    'prospect_id' => $prospectId,
                    'form_valid' => $form->isValid(),
                    'note_type' => $note->getType(),
                    'note_content_length' => strlen($note->getContent() ?? ''),
                ]);

                if ($form->isValid()) {
                    // Update prospect's last contact date
                    $prospect->setLastContactDate(new DateTime());

                    $this->logger->debug('Updating prospect last contact date', [
                        'user' => $userIdentifier,
                        'prospect_id' => $prospectId,
                        'new_last_contact_date' => $prospect->getLastContactDate()?->format('Y-m-d H:i:s'),
                    ]);

                    $persistStartTime = microtime(true);
                    $entityManager->persist($note);
                    $entityManager->flush();
                    $persistExecutionTime = microtime(true) - $persistStartTime;

                    $this->logger->info('Note added to prospect successfully', [
                        'prospect_id' => $prospectId,
                        'note_id' => $note->getId(),
                        'note_type' => $note->getType(),
                        'note_content_length' => strlen($note->getContent() ?? ''),
                        'prospect_last_contact_updated' => $prospect->getLastContactDate()?->format('Y-m-d H:i:s'),
                        'user' => $userIdentifier,
                        'persist_time' => number_format($persistExecutionTime, 4) . 's',
                        'total_execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                        'note_creation_timestamp' => $note->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ]);

                    $this->addFlash('success', 'La note a été ajoutée avec succès.');

                    return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
                }
                $this->logger->warning('Prospect note form validation failed', [
                    'user' => $userIdentifier,
                    'prospect_id' => $prospectId,
                    'form_errors' => (string) $form->getErrors(true),
                    'note_type' => $note->getType(),
                ]);
            }

            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->debug('Prospect note form rendered', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'request_method' => $request->getMethod(),
                'execution_time' => number_format($totalExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

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
        } catch (Exception $e) {
            $this->logger->error('Error in prospect add note action', [
                'user' => $userIdentifier,
                'prospect_id' => $prospectId,
                'prospect_name' => $prospect->getFullName(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'request_method' => $request->getMethod(),
                'form_submitted' => $request->isMethod('POST'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'ajout de la note. Veuillez réessayer.');

            return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
        }
    }

    /**
     * Export prospects to CSV.
     */
    #[Route('/export', name: 'admin_prospect_export', methods: ['GET'])]
    public function export(ProspectRepository $prospectRepository): Response
    {
        $requestStartTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin prospects export initiated', [
            'user' => $userIdentifier,
            'request_id' => uniqid('prospect_export_', true),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $this->logger->debug('Fetching all prospects for export', [
                'user' => $userIdentifier,
                'action' => 'fetch_all_prospects',
            ]);

            $fetchStartTime = microtime(true);
            $prospects = $prospectRepository->findAll();
            $fetchExecutionTime = microtime(true) - $fetchStartTime;

            $this->logger->debug('Prospects fetched for export', [
                'user' => $userIdentifier,
                'prospects_count' => count($prospects),
                'fetch_time' => number_format($fetchExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

            $response = new Response();
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="prospects_' . date('Y-m-d') . '.csv"');

            $this->logger->debug('Creating CSV export', [
                'user' => $userIdentifier,
                'filename' => 'prospects_' . date('Y-m-d') . '.csv',
                'records_to_export' => count($prospects),
            ]);

            $csvStartTime = microtime(true);
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

            // CSV data with detailed tracking
            $exportedRecords = 0;
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
                $exportedRecords++;
            }

            fclose($output);
            $csvExecutionTime = microtime(true) - $csvStartTime;
            $totalExecutionTime = microtime(true) - $requestStartTime;

            $this->logger->info('Prospects exported successfully', [
                'user' => $userIdentifier,
                'total_records' => count($prospects),
                'exported_records' => $exportedRecords,
                'filename' => 'prospects_' . date('Y-m-d') . '.csv',
                'fetch_time' => number_format($fetchExecutionTime, 4) . 's',
                'csv_generation_time' => number_format($csvExecutionTime, 4) . 's',
                'total_execution_time' => number_format($totalExecutionTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'export_timestamp' => date('Y-m-d H:i:s'),
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error in prospect export action', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time' => number_format(microtime(true) - $requestStartTime, 4) . 's',
                'memory_usage' => memory_get_usage(true),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'export des prospects. Veuillez réessayer.');

            return $this->redirectToRoute('admin_prospect_index');
        }
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
