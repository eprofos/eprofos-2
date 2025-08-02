<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Session;
use App\Form\Training\SessionType;
use App\Repository\Training\FormationRepository;
use App\Repository\Training\SessionRepository;
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
 * Admin Session Controller.
 *
 * Handles CRUD operations for sessions in the admin interface.
 * Provides comprehensive session management with registration tracking.
 */
#[Route('/admin/sessions')]
#[IsGranted('ROLE_ADMIN')]
class SessionController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * List all sessions with pagination and filtering.
     */
    #[Route('/', name: 'admin_session_index', methods: ['GET'])]
    public function index(Request $request, SessionRepository $sessionRepository): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin sessions list access started', [
            'user' => $userIdentifier,
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            // Get filter parameters
            $filters = [
                'search' => $request->query->get('search', ''),
                'formation' => $request->query->get('formation', ''),
                'status' => $request->query->get('status', ''),
                'start_date' => $request->query->get('start_date', ''),
                'end_date' => $request->query->get('end_date', ''),
                'active' => $request->query->get('active') !== '' ? $request->query->get('active') : null,
                'sort' => $request->query->get('sort', 'startDate'),
                'direction' => $request->query->get('direction', 'ASC'),
            ];

            $this->logger->debug('Filters applied for sessions listing', [
                'user' => $userIdentifier,
                'filters' => $filters,
                'has_search' => !empty($filters['search']),
                'has_formation_filter' => !empty($filters['formation']),
                'has_status_filter' => !empty($filters['status']),
                'has_date_range' => !empty($filters['start_date']) || !empty($filters['end_date']),
            ]);

            // Get sessions with filtering
            $queryBuilder = $sessionRepository->createAdminQueryBuilder($filters);
            $this->logger->debug('Query builder created for sessions listing', [
                'user' => $userIdentifier,
                'sort_field' => $filters['sort'],
                'sort_direction' => $filters['direction'],
            ]);

            // Handle pagination
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $this->logger->debug('Pagination parameters calculated', [
                'user' => $userIdentifier,
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            // Execute query for sessions
            $sessions = $queryBuilder
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
            ;

            $this->logger->debug('Sessions query executed successfully', [
                'user' => $userIdentifier,
                'sessions_count' => count($sessions),
                'page' => $page,
            ]);

            // Get total count for pagination
            $totalSessions = $sessionRepository->countAdminSessions($filters);
            $totalPages = ceil($totalSessions / $limit);

            $this->logger->debug('Total sessions count calculated', [
                'user' => $userIdentifier,
                'total_sessions' => $totalSessions,
                'total_pages' => $totalPages,
            ]);

            // Get statistics
            $stats = $sessionRepository->getSessionsStats();
            
            $this->logger->debug('Sessions statistics retrieved', [
                'user' => $userIdentifier,
                'stats_keys' => array_keys($stats),
            ]);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info('Admin sessions list accessed successfully', [
                'user' => $userIdentifier,
                'sessions_displayed' => count($sessions),
                'total_sessions' => $totalSessions,
                'page' => $page,
                'execution_time_ms' => $executionTime,
                'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
            ]);

            return $this->render('admin/session/index.html.twig', [
                'sessions' => $sessions,
                'filters' => $filters,
                'stats' => $stats,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_sessions' => $totalSessions,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Error occurred while listing admin sessions', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'filters' => $filters ?? [],
                'page' => $page ?? 1,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de la liste des sessions.');
            
            // Return a minimal view with error state
            return $this->render('admin/session/index.html.twig', [
                'sessions' => [],
                'filters' => $filters ?? [],
                'stats' => [],
                'current_page' => 1,
                'total_pages' => 1,
                'total_sessions' => 0,
                'error' => true,
            ]);
        }
    }

    /**
     * Show session details with registrations.
     */
    #[Route('/{id}', name: 'admin_session_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Session $session): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $sessionId = $session->getId();
        
        $this->logger->info('Admin session details view started', [
            'user' => $userIdentifier,
            'session_id' => $sessionId,
            'session_name' => $session->getName(),
            'session_status' => $session->getStatus(),
            'formation_id' => $session->getFormation()?->getId(),
            'formation_title' => $session->getFormation()?->getTitle(),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            // Log session statistics
            $registrationsCount = $session->getRegistrations()->count();
            $activeRegistrationsCount = $session->getRegistrations()->filter(
                fn($reg) => in_array($reg->getStatus(), ['confirmed', 'pending'])
            )->count();

            $this->logger->debug('Session details loaded', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'total_registrations' => $registrationsCount,
                'active_registrations' => $activeRegistrationsCount,
                'session_capacity' => $session->getMaxCapacity(),
                'start_date' => $session->getStartDate()?->format('Y-m-d'),
                'end_date' => $session->getEndDate()?->format('Y-m-d'),
                'is_session_full' => $session->getMaxCapacity() && 
                                   $activeRegistrationsCount >= $session->getMaxCapacity(),
            ]);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Admin session details viewed successfully', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'execution_time_ms' => $executionTime,
                'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
            ]);

            return $this->render('admin/session/show.html.twig', [
                'session' => $session,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Error occurred while viewing session details', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails de la session.');
            
            return $this->redirectToRoute('admin_session_index');
        }
    }

    /**
     * Create a new session.
     */
    #[Route('/new', name: 'admin_session_new', methods: ['GET', 'POST'])]
    public function new(Request $request, FormationRepository $formationRepository): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('New session creation started', [
            'user' => $userIdentifier,
            'method' => $request->getMethod(),
            'formation_id_param' => $request->query->get('formation'),
            'ip_address' => $request->getClientIp(),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $session = new Session();

            // If formation ID is provided, pre-select it
            $formationId = $request->query->get('formation');
            if ($formationId) {
                $this->logger->debug('Formation ID provided for new session', [
                    'user' => $userIdentifier,
                    'formation_id' => $formationId,
                ]);

                $formation = $formationRepository->find($formationId);
                if ($formation) {
                    $session->setFormation($formation);
                    // Auto-generate session name
                    $generatedName = $formation->getTitle() . ' - ' . (new DateTime())->format('M Y');
                    $session->setName($generatedName);
                    
                    $this->logger->debug('Formation pre-selected for new session', [
                        'user' => $userIdentifier,
                        'formation_id' => $formation->getId(),
                        'formation_title' => $formation->getTitle(),
                        'generated_session_name' => $generatedName,
                    ]);
                } else {
                    $this->logger->warning('Formation not found for new session', [
                        'user' => $userIdentifier,
                        'formation_id' => $formationId,
                    ]);
                }
            }

            $form = $this->createForm(SessionType::class, $session);
            
            $this->logger->debug('Session form created', [
                'user' => $userIdentifier,
                'form_class' => SessionType::class,
                'has_formation_preselected' => $session->getFormation() !== null,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('Session form submitted', [
                    'user' => $userIdentifier,
                    'form_valid' => $form->isValid(),
                    'session_name' => $session->getName(),
                    'formation_id' => $session->getFormation()?->getId(),
                    'start_date' => $session->getStartDate()?->format('Y-m-d H:i'),
                    'end_date' => $session->getEndDate()?->format('Y-m-d H:i'),
                    'max_capacity' => $session->getMaxCapacity(),
                ]);

                if (!$form->isValid()) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }
                    
                    $this->logger->warning('Session form validation failed', [
                        'user' => $userIdentifier,
                        'validation_errors' => $errors,
                        'form_data' => [
                            'name' => $session->getName(),
                            'formation' => $session->getFormation()?->getId(),
                        ],
                    ]);
                }
            }

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->logger->debug('Persisting new session', [
                        'user' => $userIdentifier,
                        'session_name' => $session->getName(),
                        'formation_id' => $session->getFormation()?->getId(),
                        'formation_title' => $session->getFormation()?->getTitle(),
                        'start_date' => $session->getStartDate()?->format('Y-m-d H:i'),
                        'end_date' => $session->getEndDate()?->format('Y-m-d H:i'),
                        'max_capacity' => $session->getMaxCapacity(),
                        'min_capacity' => $session->getMinCapacity(),
                        'location' => $session->getLocation(),
                        'price' => $session->getPrice(),
                    ]);

                    $this->entityManager->persist($session);
                    $this->entityManager->flush();

                    $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                    $this->addFlash('success', 'La session a été créée avec succès.');
                    
                    $this->logger->info('New session created successfully', [
                        'user' => $userIdentifier,
                        'session_id' => $session->getId(),
                        'session_name' => $session->getName(),
                        'formation_id' => $session->getFormation()?->getId(),
                        'formation_title' => $session->getFormation()?->getTitle(),
                        'execution_time_ms' => $executionTime,
                        'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                    ]);

                    return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
                    
                } catch (Exception $e) {
                    $this->logger->error('Database error while creating session', [
                        'user' => $userIdentifier,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString(),
                        'session_data' => [
                            'name' => $session->getName(),
                            'formation_id' => $session->getFormation()?->getId(),
                            'start_date' => $session->getStartDate()?->format('Y-m-d H:i'),
                            'end_date' => $session->getEndDate()?->format('Y-m-d H:i'),
                        ],
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de la création de la session.');
                }
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->debug('Rendering new session form', [
                'user' => $userIdentifier,
                'is_form_submitted' => $form->isSubmitted(),
                'execution_time_ms' => $executionTime,
            ]);

            return $this->render('admin/session/new.html.twig', [
                'session' => $session,
                'form' => $form,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Unexpected error in new session creation', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'formation_id_param' => $formationId ?? null,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->addFlash('error', 'Une erreur inattendue est survenue lors de la création de la session.');
            
            return $this->redirectToRoute('admin_session_index');
        }
    }

    /**
     * Edit an existing session.
     */
    #[Route('/{id}/edit', name: 'admin_session_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Session $session): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $sessionId = $session->getId();
        
        $this->logger->info('Session edit started', [
            'user' => $userIdentifier,
            'method' => $request->getMethod(),
            'session_id' => $sessionId,
            'session_name' => $session->getName(),
            'session_status' => $session->getStatus(),
            'formation_id' => $session->getFormation()?->getId(),
            'ip_address' => $request->getClientIp(),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            // Store original values for comparison
            $originalData = [
                'name' => $session->getName(),
                'description' => $session->getDescription(),
                'start_date' => $session->getStartDate(),
                'end_date' => $session->getEndDate(),
                'location' => $session->getLocation(),
                'price' => $session->getPrice(),
                'max_capacity' => $session->getMaxCapacity(),
                'min_capacity' => $session->getMinCapacity(),
                'status' => $session->getStatus(),
                'formation_id' => $session->getFormation()?->getId(),
            ];

            $this->logger->debug('Original session data captured', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'original_data' => $originalData,
            ]);

            $form = $this->createForm(SessionType::class, $session);
            
            $this->logger->debug('Session edit form created', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'form_class' => SessionType::class,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('Session edit form submitted', [
                    'user' => $userIdentifier,
                    'session_id' => $sessionId,
                    'form_valid' => $form->isValid(),
                    'new_session_name' => $session->getName(),
                    'formation_id' => $session->getFormation()?->getId(),
                    'start_date' => $session->getStartDate()?->format('Y-m-d H:i'),
                    'end_date' => $session->getEndDate()?->format('Y-m-d H:i'),
                    'max_capacity' => $session->getMaxCapacity(),
                    'status' => $session->getStatus(),
                ]);

                if (!$form->isValid()) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }
                    
                    $this->logger->warning('Session edit form validation failed', [
                        'user' => $userIdentifier,
                        'session_id' => $sessionId,
                        'validation_errors' => $errors,
                        'form_data' => [
                            'name' => $session->getName(),
                            'formation' => $session->getFormation()?->getId(),
                        ],
                    ]);
                }
            }

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    // Calculate changes
                    $changes = [];
                    if ($originalData['name'] !== $session->getName()) {
                        $changes['name'] = ['from' => $originalData['name'], 'to' => $session->getName()];
                    }
                    if ($originalData['start_date'] != $session->getStartDate()) {
                        $changes['start_date'] = [
                            'from' => $originalData['start_date']?->format('Y-m-d H:i'),
                            'to' => $session->getStartDate()?->format('Y-m-d H:i')
                        ];
                    }
                    if ($originalData['end_date'] != $session->getEndDate()) {
                        $changes['end_date'] = [
                            'from' => $originalData['end_date']?->format('Y-m-d H:i'),
                            'to' => $session->getEndDate()?->format('Y-m-d H:i')
                        ];
                    }
                    if ($originalData['max_capacity'] !== $session->getMaxCapacity()) {
                        $changes['max_capacity'] = [
                            'from' => $originalData['max_capacity'],
                            'to' => $session->getMaxCapacity()
                        ];
                    }
                    if ($originalData['price'] !== $session->getPrice()) {
                        $changes['price'] = [
                            'from' => $originalData['price'],
                            'to' => $session->getPrice()
                        ];
                    }
                    if ($originalData['status'] !== $session->getStatus()) {
                        $changes['status'] = [
                            'from' => $originalData['status'],
                            'to' => $session->getStatus()
                        ];
                    }

                    $this->logger->debug('Session changes detected', [
                        'user' => $userIdentifier,
                        'session_id' => $sessionId,
                        'changes' => $changes,
                        'has_changes' => !empty($changes),
                    ]);

                    $this->entityManager->flush();

                    $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                    $this->addFlash('success', 'La session a été modifiée avec succès.');
                    
                    $this->logger->info('Session updated successfully', [
                        'user' => $userIdentifier,
                        'session_id' => $sessionId,
                        'session_name' => $session->getName(),
                        'changes' => $changes,
                        'execution_time_ms' => $executionTime,
                        'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                    ]);

                    return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
                    
                } catch (Exception $e) {
                    $this->logger->error('Database error while updating session', [
                        'user' => $userIdentifier,
                        'session_id' => $sessionId,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString(),
                        'original_data' => $originalData,
                        'new_data' => [
                            'name' => $session->getName(),
                            'start_date' => $session->getStartDate()?->format('Y-m-d H:i'),
                            'end_date' => $session->getEndDate()?->format('Y-m-d H:i'),
                        ],
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de la modification de la session.');
                }
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->debug('Rendering session edit form', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'is_form_submitted' => $form->isSubmitted(),
                'execution_time_ms' => $executionTime,
            ]);

            return $this->render('admin/session/edit.html.twig', [
                'session' => $session,
                'form' => $form,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Unexpected error in session edit', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->addFlash('error', 'Une erreur inattendue est survenue lors de la modification de la session.');
            
            return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
        }
    }

    /**
     * Delete a session.
     */
    #[Route('/{id}/delete', name: 'admin_session_delete', methods: ['POST'])]
    public function delete(Request $request, Session $session): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $sessionId = $session->getId();
        $sessionName = $session->getName();
        
        $this->logger->info('Session deletion attempt started', [
            'user' => $userIdentifier,
            'session_id' => $sessionId,
            'session_name' => $sessionName,
            'session_status' => $session->getStatus(),
            'formation_id' => $session->getFormation()?->getId(),
            'formation_title' => $session->getFormation()?->getTitle(),
            'registrations_count' => $session->getRegistrations()->count(),
            'ip_address' => $request->getClientIp(),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $tokenValid = $this->isCsrfTokenValid('delete' . $sessionId, $request->request->get('_token'));
            
            $this->logger->debug('CSRF token validation', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'token_valid' => $tokenValid,
                'provided_token' => $request->request->get('_token') ? 'provided' : 'missing',
            ]);

            if ($tokenValid) {
                try {
                    // Capture session data before deletion for logging
                    $sessionData = [
                        'id' => $sessionId,
                        'name' => $sessionName,
                        'status' => $session->getStatus(),
                        'formation_id' => $session->getFormation()?->getId(),
                        'formation_title' => $session->getFormation()?->getTitle(),
                        'start_date' => $session->getStartDate()?->format('Y-m-d H:i'),
                        'end_date' => $session->getEndDate()?->format('Y-m-d H:i'),
                        'registrations_count' => $session->getRegistrations()->count(),
                        'max_capacity' => $session->getMaxCapacity(),
                        'location' => $session->getLocation(),
                        'price' => $session->getPrice(),
                    ];

                    // Log detailed session info before deletion
                    $this->logger->debug('Session data before deletion', [
                        'user' => $userIdentifier,
                        'session_data' => $sessionData,
                    ]);

                    $this->entityManager->remove($session);
                    $this->entityManager->flush();

                    $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                    $this->addFlash('success', 'La session a été supprimée avec succès.');
                    
                    $this->logger->info('Session deleted successfully', [
                        'user' => $userIdentifier,
                        'deleted_session_data' => $sessionData,
                        'execution_time_ms' => $executionTime,
                        'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                    ]);

                } catch (Exception $e) {
                    $this->logger->error('Database error while deleting session', [
                        'user' => $userIdentifier,
                        'session_id' => $sessionId,
                        'session_name' => $sessionName,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString(),
                        'registrations_count' => $session->getRegistrations()->count(),
                        'session_status' => $session->getStatus(),
                    ]);

                    // Check if it's a foreign key constraint error
                    if (str_contains($e->getMessage(), 'foreign key constraint') || 
                        str_contains($e->getMessage(), 'FOREIGN KEY constraint')) {
                        $this->addFlash('error', 'Impossible de supprimer cette session car elle contient des inscriptions ou des données liées.');
                        
                        $this->logger->warning('Session deletion failed due to foreign key constraints', [
                            'user' => $userIdentifier,
                            'session_id' => $sessionId,
                            'registrations_count' => $session->getRegistrations()->count(),
                        ]);
                    } else {
                        $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la session.');
                    }
                }
            } else {
                $this->logger->warning('Session deletion failed - invalid CSRF token', [
                    'user' => $userIdentifier,
                    'session_id' => $sessionId,
                    'session_name' => $sessionName,
                    'ip_address' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

        } catch (Exception $e) {
            $this->logger->error('Unexpected error during session deletion', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'session_name' => $sessionName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->addFlash('error', 'Une erreur inattendue est survenue lors de la suppression de la session.');
        }

        return $this->redirectToRoute('admin_session_index');
    }

    /**
     * Toggle session status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_session_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, Session $session): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $sessionId = $session->getId();
        $currentStatus = $session->getStatus();
        
        $this->logger->info('Session status toggle started', [
            'user' => $userIdentifier,
            'session_id' => $sessionId,
            'session_name' => $session->getName(),
            'current_status' => $currentStatus,
            'requested_status' => $request->request->get('status'),
            'ip_address' => $request->getClientIp(),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $tokenValid = $this->isCsrfTokenValid('toggle_status' . $sessionId, $request->request->get('_token'));
            
            $this->logger->debug('CSRF token validation for status toggle', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'token_valid' => $tokenValid,
                'provided_token' => $request->request->get('_token') ? 'provided' : 'missing',
            ]);

            if ($tokenValid) {
                try {
                    $newStatus = $request->request->get('status');
                    $validStatuses = ['planned', 'open', 'confirmed', 'cancelled', 'completed'];

                    $this->logger->debug('Status change validation', [
                        'user' => $userIdentifier,
                        'session_id' => $sessionId,
                        'current_status' => $currentStatus,
                        'requested_status' => $newStatus,
                        'is_valid_status' => in_array($newStatus, $validStatuses, true),
                        'valid_statuses' => $validStatuses,
                    ]);

                    if (in_array($newStatus, $validStatuses, true)) {
                        // Additional business logic validation
                        $registrationsCount = $session->getRegistrations()->count();
                        $activeRegistrationsCount = $session->getRegistrations()->filter(
                            fn($reg) => in_array($reg->getStatus(), ['confirmed', 'pending'])
                        )->count();

                        $this->logger->debug('Session context for status change', [
                            'user' => $userIdentifier,
                            'session_id' => $sessionId,
                            'total_registrations' => $registrationsCount,
                            'active_registrations' => $activeRegistrationsCount,
                            'max_capacity' => $session->getMaxCapacity(),
                            'min_capacity' => $session->getMinCapacity(),
                            'start_date' => $session->getStartDate()?->format('Y-m-d H:i'),
                        ]);

                        // Log potential business rule warnings
                        if ($newStatus === 'confirmed' && $activeRegistrationsCount < $session->getMinCapacity()) {
                            $this->logger->warning('Session confirmed despite low registration count', [
                                'user' => $userIdentifier,
                                'session_id' => $sessionId,
                                'active_registrations' => $activeRegistrationsCount,
                                'min_capacity' => $session->getMinCapacity(),
                            ]);
                        }

                        if ($newStatus === 'cancelled' && $activeRegistrationsCount > 0) {
                            $this->logger->warning('Session cancelled with active registrations', [
                                'user' => $userIdentifier,
                                'session_id' => $sessionId,
                                'active_registrations' => $activeRegistrationsCount,
                            ]);
                        }

                        $session->setStatus($newStatus);
                        $this->entityManager->flush();

                        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                        $this->addFlash('success', 'Le statut de la session a été modifié.');
                        
                        $this->logger->info('Session status changed successfully', [
                            'user' => $userIdentifier,
                            'session_id' => $sessionId,
                            'session_name' => $session->getName(),
                            'old_status' => $currentStatus,
                            'new_status' => $newStatus,
                            'registrations_count' => $registrationsCount,
                            'active_registrations' => $activeRegistrationsCount,
                            'execution_time_ms' => $executionTime,
                            'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                        ]);

                    } else {
                        $this->logger->warning('Invalid status requested for session', [
                            'user' => $userIdentifier,
                            'session_id' => $sessionId,
                            'current_status' => $currentStatus,
                            'invalid_status' => $newStatus,
                            'valid_statuses' => $validStatuses,
                        ]);

                        $this->addFlash('error', 'Statut invalide demandé.');
                    }

                } catch (Exception $e) {
                    $this->logger->error('Database error while changing session status', [
                        'user' => $userIdentifier,
                        'session_id' => $sessionId,
                        'current_status' => $currentStatus,
                        'requested_status' => $request->request->get('status'),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString(),
                    ]);

                    $this->addFlash('error', 'Erreur lors de la modification du statut.');
                }
            } else {
                $this->logger->warning('Session status toggle failed - invalid CSRF token', [
                    'user' => $userIdentifier,
                    'session_id' => $sessionId,
                    'current_status' => $currentStatus,
                    'requested_status' => $request->request->get('status'),
                    'ip_address' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

        } catch (Exception $e) {
            $this->logger->error('Unexpected error during session status toggle', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'current_status' => $currentStatus,
                'requested_status' => $request->request->get('status'),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->addFlash('error', 'Une erreur inattendue est survenue lors de la modification du statut.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
    }

    /**
     * Export session registrations to CSV.
     */
    #[Route('/{id}/export', name: 'admin_session_export', methods: ['GET'])]
    public function export(Session $session): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $sessionId = $session->getId();
        
        $this->logger->info('Session export started', [
            'user' => $userIdentifier,
            'session_id' => $sessionId,
            'session_name' => $session->getName(),
            'formation_id' => $session->getFormation()?->getId(),
            'formation_title' => $session->getFormation()?->getTitle(),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $registrations = $session->getRegistrations();
            $registrationsCount = $registrations->count();

            $this->logger->debug('Session registrations loaded for export', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'registrations_count' => $registrationsCount,
            ]);

            // Analyze registration data for logging
            $statusBreakdown = [];
            $companiesCount = [];
            foreach ($registrations as $registration) {
                $status = $registration->getStatus() ?? 'unknown';
                $statusBreakdown[$status] = ($statusBreakdown[$status] ?? 0) + 1;
                
                $company = $registration->getCompany();
                if ($company) {
                    $companiesCount[$company] = ($companiesCount[$company] ?? 0) + 1;
                }
            }

            $this->logger->debug('Registration analysis for export', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'status_breakdown' => $statusBreakdown,
                'unique_companies' => count($companiesCount),
                'companies_with_multiple_registrations' => array_filter($companiesCount, fn($count) => $count > 1),
            ]);

            $response = new Response();
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $filename = 'inscriptions_session_' . $sessionId . '_' . date('Y-m-d_H-i-s') . '.csv';
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

            $this->logger->debug('CSV response headers set', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'filename' => $filename,
                'content_type' => 'text/csv; charset=utf-8',
            ]);

            // Start output buffering to capture CSV content
            ob_start();
            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8 support in Excel
            fwrite($output, "\xEF\xBB\xBF");

            // CSV headers
            $headers = [
                'Prénom',
                'Nom',
                'Email',
                'Téléphone',
                'Entreprise',
                'Poste',
                'Statut',
                'Date d\'inscription',
                'Besoins spécifiques',
            ];
            
            fputcsv($output, $headers, ';');

            $this->logger->debug('CSV headers written', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'headers' => $headers,
            ]);

            // CSV data
            $exportedCount = 0;
            foreach ($registrations as $registration) {
                $row = [
                    $registration->getFirstName() ?? '',
                    $registration->getLastName() ?? '',
                    $registration->getEmail() ?? '',
                    $registration->getPhone() ?? '',
                    $registration->getCompany() ?? '',
                    $registration->getPosition() ?? '',
                    $registration->getStatusLabel() ?? $registration->getStatus() ?? '',
                    $registration->getCreatedAt()?->format('d/m/Y H:i') ?? '',
                    $registration->getSpecialRequirements() ?? '',
                ];

                fputcsv($output, $row, ';');
                $exportedCount++;
            }

            fclose($output);
            $csvContent = ob_get_clean();
            
            $response->setContent($csvContent);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Session registrations exported successfully', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'session_name' => $session->getName(),
                'filename' => $filename,
                'total_registrations' => $registrationsCount,
                'exported_registrations' => $exportedCount,
                'file_size_bytes' => strlen($csvContent),
                'status_breakdown' => $statusBreakdown,
                'execution_time_ms' => $executionTime,
                'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
            ]);

            return $response;

        } catch (Exception $e) {
            $this->logger->error('Error occurred while exporting session registrations', [
                'user' => $userIdentifier,
                'session_id' => $sessionId,
                'session_name' => $session->getName(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'export des inscriptions.');
            
            return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
        }
    }
}
