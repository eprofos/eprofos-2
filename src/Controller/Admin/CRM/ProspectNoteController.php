<?php

declare(strict_types=1);

namespace App\Controller\Admin\CRM;

use App\Entity\CRM\Prospect;
use App\Entity\CRM\ProspectNote;
use App\Form\CRM\ProspectNoteType;
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
#[Route('/admin/prospect-notes')]
#[IsGranted('ROLE_ADMIN')]
class ProspectNoteController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * List all prospect notes with filtering.
     */
    #[Route('/', name: 'admin_prospect_note_index', methods: ['GET'])]
    public function index(Request $request, ProspectNoteRepository $noteRepository): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Admin prospect notes list accessed', [
            'admin' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'user_ip' => $request->getClientIp(),
        ]);

        try {
            // Extract and validate filter parameters
            $type = $request->query->get('type');
            $status = $request->query->get('status');
            $important = $request->query->get('important');
            $search = $request->query->get('search');

            $this->logger->debug('Filter parameters extracted', [
                'type' => $type,
                'status' => $status,
                'important' => $important,
                'search' => $search ? '[FILTERED]' : null,
                'search_length' => $search ? strlen($search) : 0,
            ]);

            // Build query with detailed logging
            $this->logger->debug('Building query builder for prospect notes');
            $queryBuilder = $noteRepository->createQueryBuilder('pn')
                ->leftJoin('pn.prospect', 'p')
                ->leftJoin('pn.createdBy', 'u')
                ->addSelect('p', 'u')
                ->orderBy('pn.createdAt', 'DESC')
            ;

            $appliedFilters = [];

            if ($type) {
                $queryBuilder->andWhere('pn.type = :type')
                    ->setParameter('type', $type)
                ;
                $appliedFilters[] = 'type';
                $this->logger->debug('Applied type filter', ['type' => $type]);
            }

            if ($status) {
                $queryBuilder->andWhere('pn.status = :status')
                    ->setParameter('status', $status)
                ;
                $appliedFilters[] = 'status';
                $this->logger->debug('Applied status filter', ['status' => $status]);
            }

            if ($important) {
                $importantValue = $important === 'true';
                $queryBuilder->andWhere('pn.isImportant = :important')
                    ->setParameter('important', $importantValue)
                ;
                $appliedFilters[] = 'important';
                $this->logger->debug('Applied important filter', ['important' => $importantValue]);
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
                $appliedFilters[] = 'search';
                $this->logger->debug('Applied search filter', ['search_terms_length' => strlen($searchTerms)]);
            }

            $this->logger->info('Query filters applied', [
                'applied_filters' => $appliedFilters,
                'filters_count' => count($appliedFilters),
            ]);

            // Execute query and measure performance
            $queryStartTime = microtime(true);
            $notes = $queryBuilder->getQuery()->getResult();
            $queryDuration = (microtime(true) - $queryStartTime) * 1000;

            $this->logger->info('Prospect notes query executed', [
                'results_count' => count($notes),
                'query_duration_ms' => round($queryDuration, 2),
            ]);

            // Get statistics with error handling
            $this->logger->debug('Fetching activity statistics');
            $statisticsStartTime = microtime(true);
            $statistics = $noteRepository->getActivityStatistics();
            $typeCounts = $noteRepository->countByType();
            $statusCounts = $noteRepository->countByStatus();
            $statisticsDuration = (microtime(true) - $statisticsStartTime) * 1000;

            $this->logger->info('Statistics calculated', [
                'statistics_duration_ms' => round($statisticsDuration, 2),
                'type_counts' => array_sum($typeCounts),
                'status_counts' => array_sum($statusCounts),
            ]);

            $totalDuration = (microtime(true) - $startTime) * 1000;
            $this->logger->info('Prospect notes index completed successfully', [
                'total_duration_ms' => round($totalDuration, 2),
                'notes_displayed' => count($notes),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

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

        } catch (\Exception $e) {
            $this->logger->error('Error in prospect notes index', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'admin' => $this->getUser()?->getUserIdentifier(),
                'request_data' => [
                    'type' => $request->query->get('type'),
                    'status' => $request->query->get('status'),
                    'important' => $request->query->get('important'),
                    'search' => $request->query->get('search') ? '[FILTERED]' : null,
                ],
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des notes prospects.');
            
            // Fallback: try to render with minimal data
            try {
                return $this->render('admin/prospect_note/index.html.twig', [
                    'notes' => [],
                    'statistics' => [],
                    'type_counts' => [],
                    'status_counts' => [],
                    'current_type' => null,
                    'current_status' => null,
                    'current_important' => null,
                    'current_search' => null,
                    'page_title' => 'Gestion des notes prospects',
                    'breadcrumb' => [
                        ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                        ['label' => 'Notes prospects', 'url' => null],
                    ],
                ]);
            } catch (\Exception $renderException) {
                $this->logger->critical('Failed to render fallback template', [
                    'render_error' => $renderException->getMessage(),
                    'original_error' => $e->getMessage(),
                ]);
                throw $e; // Re-throw original exception if fallback fails
            }
        }
    }

    /**
     * Show note details.
     */
    #[Route('/{id}', name: 'admin_prospect_note_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ProspectNote $note): Response
    {
        $this->logger->info('Admin prospect note details viewed', [
            'note_id' => $note->getId(),
            'note_title' => $note->getTitle(),
            'note_type' => $note->getType(),
            'note_status' => $note->getStatus(),
            'prospect_id' => $note->getProspect()?->getId(),
            'prospect_name' => $note->getProspect()?->getFullName(),
            'admin' => $this->getUser()?->getUserIdentifier(),
            'is_important' => $note->isImportant(),
            'is_private' => $note->isPrivate(),
            'created_at' => $note->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);

        try {
            // Log detailed note information for debugging
            $this->logger->debug('Note details loaded', [
                'note_content_length' => strlen($note->getContent() ?? ''),
                'has_scheduled_date' => $note->getScheduledAt() !== null,
                'scheduled_at' => $note->getScheduledAt()?->format('Y-m-d H:i:s'),
                'is_completed' => $note->isCompleted(),
                'completed_at' => $note->getCompletedAt()?->format('Y-m-d H:i:s'),
                'created_by' => $note->getCreatedBy()?->getUserIdentifier(),
            ]);

            // Validate note data integrity
            if (!$note->getTitle()) {
                $this->logger->warning('Note has no title', ['note_id' => $note->getId()]);
            }

            if (!$note->getProspect()) {
                $this->logger->warning('Note has no associated prospect', ['note_id' => $note->getId()]);
            }

            return $this->render('admin/prospect_note/show.html.twig', [
                'note' => $note,
                'page_title' => 'Détails de la note',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Notes prospects', 'url' => $this->generateUrl('admin_prospect_note_index')],
                    ['label' => 'Note #' . $note->getId(), 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error displaying prospect note details', [
                'note_id' => $note->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage des détails de la note.');
            return $this->redirectToRoute('admin_prospect_note_index');
        }
    }

    /**
     * Create a new note.
     */
    #[Route('/new', name: 'admin_prospect_note_new_standalone', methods: ['GET', 'POST'])]
    public function newStandalone(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->new($request, $entityManager, null);
    }

    /**
     * Create a new note for a specific prospect.
     */
    #[Route('/new/{prospect}', name: 'admin_prospect_note_new', methods: ['GET', 'POST'], requirements: ['prospect' => '\d+'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ?Prospect $prospect = null): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Creating new prospect note', [
            'prospect_id' => $prospect?->getId(),
            'prospect_name' => $prospect?->getFullName(),
            'admin' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod(),
            'is_form_submission' => $request->isMethod('POST'),
        ]);

        try {
            $note = new ProspectNote();
            $currentUser = $this->getUser();
            
            if (!$currentUser) {
                $this->logger->error('No authenticated user found when creating note');
                throw new \RuntimeException('Utilisateur non authentifié');
            }

            $note->setCreatedBy($currentUser);
            $this->logger->debug('Note created by user set', [
                'created_by' => $currentUser->getUserIdentifier(),
            ]);

            // Set the prospect if provided via URL parameter
            if ($prospect) {
                $note->setProspect($prospect);
                $this->logger->debug('Prospect assigned to note', [
                    'prospect_id' => $prospect->getId(),
                    'prospect_company' => $prospect->getCompany(),
                ]);
            }

            $formOptions = [
                'show_created_by' => true,
                'prospect_context' => $prospect !== null,
            ];

            $this->logger->debug('Creating form with options', $formOptions);
            $form = $this->createForm(ProspectNoteType::class, $note, $formOptions);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Form submitted for new note', [
                    'is_valid' => $form->isValid(),
                    'prospect_id' => $prospect?->getId(),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Form validation passed, processing note', [
                        'note_title' => $note->getTitle(),
                        'note_type' => $note->getType(),
                        'note_status' => $note->getStatus(),
                        'is_important' => $note->isImportant(),
                        'is_private' => $note->isPrivate(),
                        'scheduled_at' => $note->getScheduledAt()?->format('Y-m-d H:i:s'),
                    ]);

                    // Update prospect's last contact date if note is completed
                    if ($note->isCompleted() && $note->getProspect()) {
                        $oldLastContact = $note->getProspect()->getLastContactDate();
                        $note->getProspect()->setLastContactDate(new DateTime());
                        
                        $this->logger->info('Updated prospect last contact date', [
                            'prospect_id' => $note->getProspect()->getId(),
                            'old_last_contact' => $oldLastContact?->format('Y-m-d H:i:s'),
                            'new_last_contact' => (new DateTime())->format('Y-m-d H:i:s'),
                        ]);
                    }

                    $persistStartTime = microtime(true);
                    $entityManager->persist($note);
                    $entityManager->flush();
                    $persistDuration = (microtime(true) - $persistStartTime) * 1000;

                    $totalDuration = (microtime(true) - $startTime) * 1000;

                    $this->logger->info('New prospect note created successfully', [
                        'note_id' => $note->getId(),
                        'prospect_id' => $note->getProspect()?->getId(),
                        'note_type' => $note->getType(),
                        'note_status' => $note->getStatus(),
                        'admin' => $this->getUser()?->getUserIdentifier(),
                        'persist_duration_ms' => round($persistDuration, 2),
                        'total_duration_ms' => round($totalDuration, 2),
                    ]);

                    $this->addFlash('success', 'La note a été créée avec succès.');

                    // Redirect to prospect show page if we came from a prospect context
                    if ($prospect) {
                        $this->logger->debug('Redirecting to prospect show page', [
                            'prospect_id' => $prospect->getId(),
                        ]);
                        return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
                    }

                    $this->logger->debug('Redirecting to note show page', [
                        'note_id' => $note->getId(),
                    ]);
                    return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
                } else {
                    // Log form validation errors
                    $formErrors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $formErrors[] = [
                            'field' => $error->getOrigin()?->getName(),
                            'message' => $error->getMessage(),
                        ];
                    }

                    $this->logger->warning('Form validation failed for new note', [
                        'errors' => $formErrors,
                        'prospect_id' => $prospect?->getId(),
                        'admin' => $this->getUser()?->getUserIdentifier(),
                    ]);
                }
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

            $this->logger->debug('Rendering new note form', [
                'prospect_context' => $prospect !== null,
                'breadcrumb_items' => count($breadcrumb),
            ]);

            return $this->render('admin/prospect_note/new.html.twig', [
                'note' => $note,
                'form' => $form,
                'prospect' => $prospect,
                'page_title' => $prospect ? 'Nouvelle note pour ' . $prospect->getFullName() : 'Nouvelle note',
                'breadcrumb' => $breadcrumb,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error creating new prospect note', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'prospect_id' => $prospect?->getId(),
                'admin' => $this->getUser()?->getUserIdentifier(),
                'request_method' => $request->getMethod(),
                'form_submitted' => $request->isMethod('POST'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création de la note.');
            
            // Redirect based on context
            if ($prospect) {
                return $this->redirectToRoute('admin_prospect_show', ['id' => $prospect->getId()]);
            }
            
            return $this->redirectToRoute('admin_prospect_note_index');
        }
    }

    /**
     * Edit an existing note.
     */
    #[Route('/{id}/edit', name: 'admin_prospect_note_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, ProspectNote $note, EntityManagerInterface $entityManager): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Editing prospect note', [
            'note_id' => $note->getId(),
            'note_title' => $note->getTitle(),
            'note_type' => $note->getType(),
            'prospect_id' => $note->getProspect()?->getId(),
            'admin' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod(),
        ]);

        try {
            // Store original values for comparison
            $originalData = [
                'title' => $note->getTitle(),
                'content' => $note->getContent(),
                'type' => $note->getType(),
                'status' => $note->getStatus(),
                'is_important' => $note->isImportant(),
                'is_private' => $note->isPrivate(),
                'scheduled_at' => $note->getScheduledAt()?->format('Y-m-d H:i:s'),
            ];

            $this->logger->debug('Original note data captured', $originalData);

            $form = $this->createForm(ProspectNoteType::class, $note, [
                'show_created_by' => true,
                'prospect_context' => true,  // In edit mode, prospect is already determined
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Edit form submitted', [
                    'note_id' => $note->getId(),
                    'is_valid' => $form->isValid(),
                ]);

                if ($form->isValid()) {
                    // Log changes
                    $changes = [];
                    if ($originalData['title'] !== $note->getTitle()) {
                        $changes['title'] = ['from' => $originalData['title'], 'to' => $note->getTitle()];
                    }
                    if ($originalData['type'] !== $note->getType()) {
                        $changes['type'] = ['from' => $originalData['type'], 'to' => $note->getType()];
                    }
                    if ($originalData['status'] !== $note->getStatus()) {
                        $changes['status'] = ['from' => $originalData['status'], 'to' => $note->getStatus()];
                    }
                    if ($originalData['is_important'] !== $note->isImportant()) {
                        $changes['is_important'] = ['from' => $originalData['is_important'], 'to' => $note->isImportant()];
                    }
                    if ($originalData['is_private'] !== $note->isPrivate()) {
                        $changes['is_private'] = ['from' => $originalData['is_private'], 'to' => $note->isPrivate()];
                    }
                    
                    $newScheduledAt = $note->getScheduledAt()?->format('Y-m-d H:i:s');
                    if ($originalData['scheduled_at'] !== $newScheduledAt) {
                        $changes['scheduled_at'] = ['from' => $originalData['scheduled_at'], 'to' => $newScheduledAt];
                    }

                    $this->logger->info('Note changes detected', [
                        'note_id' => $note->getId(),
                        'changes' => $changes,
                        'changes_count' => count($changes),
                    ]);

                    // Update prospect's last contact date if note is completed
                    if ($note->isCompleted() && $note->getProspect()) {
                        $oldLastContact = $note->getProspect()->getLastContactDate();
                        $note->getProspect()->setLastContactDate(new DateTime());
                        
                        $this->logger->info('Updated prospect last contact date on note completion', [
                            'prospect_id' => $note->getProspect()->getId(),
                            'old_last_contact' => $oldLastContact?->format('Y-m-d H:i:s'),
                            'new_last_contact' => (new DateTime())->format('Y-m-d H:i:s'),
                        ]);
                    }

                    $flushStartTime = microtime(true);
                    $entityManager->flush();
                    $flushDuration = (microtime(true) - $flushStartTime) * 1000;
                    $totalDuration = (microtime(true) - $startTime) * 1000;

                    $this->logger->info('Prospect note updated successfully', [
                        'note_id' => $note->getId(),
                        'prospect_id' => $note->getProspect()?->getId(),
                        'admin' => $this->getUser()?->getUserIdentifier(),
                        'flush_duration_ms' => round($flushDuration, 2),
                        'total_duration_ms' => round($totalDuration, 2),
                        'changes_applied' => count($changes),
                    ]);

                    $this->addFlash('success', 'La note a été modifiée avec succès.');

                    return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
                } else {
                    // Log form validation errors
                    $formErrors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $formErrors[] = [
                            'field' => $error->getOrigin()?->getName(),
                            'message' => $error->getMessage(),
                        ];
                    }

                    $this->logger->warning('Form validation failed for note edit', [
                        'note_id' => $note->getId(),
                        'errors' => $formErrors,
                        'admin' => $this->getUser()?->getUserIdentifier(),
                    ]);
                }
            }

            $this->logger->debug('Rendering edit form', [
                'note_id' => $note->getId(),
                'form_submitted' => $form->isSubmitted(),
            ]);

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

        } catch (\Exception $e) {
            $this->logger->error('Error editing prospect note', [
                'note_id' => $note->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'admin' => $this->getUser()?->getUserIdentifier(),
                'request_method' => $request->getMethod(),
                'form_submitted' => $request->isMethod('POST'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification de la note.');
            return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
        }
    }

    /**
     * Delete a note.
     */
    #[Route('/{id}', name: 'admin_prospect_note_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ProspectNote $note, EntityManagerInterface $entityManager): Response
    {
        $this->logger->info('Attempting to delete prospect note', [
            'note_id' => $note->getId(),
            'note_title' => $note->getTitle(),
            'note_type' => $note->getType(),
            'prospect_id' => $note->getProspect()?->getId(),
            'prospect_name' => $note->getProspect()?->getFullName(),
            'admin' => $this->getUser()?->getUserIdentifier(),
            'csrf_token_provided' => $request->getPayload()->has('_token'),
        ]);

        try {
            $csrfToken = $request->getPayload()->get('_token');
            $expectedToken = 'delete' . $note->getId();

            $this->logger->debug('CSRF token validation', [
                'note_id' => $note->getId(),
                'token_provided' => !empty($csrfToken),
                'expected_token_prefix' => $expectedToken,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                // Store note information before deletion for logging
                $deletedNoteInfo = [
                    'id' => $note->getId(),
                    'title' => $note->getTitle(),
                    'type' => $note->getType(),
                    'status' => $note->getStatus(),
                    'prospect_id' => $note->getProspect()?->getId(),
                    'prospect_name' => $note->getProspect()?->getFullName(),
                    'created_by' => $note->getCreatedBy()?->getUserIdentifier(),
                    'created_at' => $note->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'is_important' => $note->isImportant(),
                    'is_private' => $note->isPrivate(),
                ];

                $this->logger->info('Deleting prospect note', $deletedNoteInfo);

                $deleteStartTime = microtime(true);
                $entityManager->remove($note);
                $entityManager->flush();
                $deleteDuration = (microtime(true) - $deleteStartTime) * 1000;

                $this->logger->info('Prospect note deleted successfully', [
                    'deleted_note' => $deletedNoteInfo,
                    'admin' => $this->getUser()?->getUserIdentifier(),
                    'delete_duration_ms' => round($deleteDuration, 2),
                ]);

                $this->addFlash('success', 'La note a été supprimée avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for note deletion', [
                    'note_id' => $note->getId(),
                    'admin' => $this->getUser()?->getUserIdentifier(),
                    'provided_token_length' => strlen($csrfToken ?? ''),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

        } catch (\Exception $e) {
            $this->logger->error('Error deleting prospect note', [
                'note_id' => $note->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la note.');
        }

        return $this->redirectToRoute('admin_prospect_note_index');
    }

    /**
     * Update note status.
     */
    #[Route('/{id}/status', name: 'admin_prospect_note_update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(Request $request, ProspectNote $note, EntityManagerInterface $entityManager): Response
    {
        $this->logger->info('Updating prospect note status', [
            'note_id' => $note->getId(),
            'current_status' => $note->getStatus(),
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $newStatus = $request->getPayload()->get('status');
            $csrfToken = $request->getPayload()->get('_token');
            $expectedToken = 'update_status' . $note->getId();

            $this->logger->debug('Status update request details', [
                'note_id' => $note->getId(),
                'new_status' => $newStatus,
                'csrf_token_provided' => !empty($csrfToken),
            ]);

            // Validate new status
            if (!$newStatus) {
                $this->logger->warning('No status provided for update', [
                    'note_id' => $note->getId(),
                ]);
                $this->addFlash('error', 'Statut invalide fourni.');
                return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
            }

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                $oldStatus = $note->getStatus();
                
                // Validate status transition
                $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
                if (!in_array($newStatus, $validStatuses, true)) {
                    $this->logger->warning('Invalid status provided', [
                        'note_id' => $note->getId(),
                        'provided_status' => $newStatus,
                        'valid_statuses' => $validStatuses,
                    ]);
                    $this->addFlash('error', 'Statut invalide.');
                    return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
                }

                $updateStartTime = microtime(true);
                $note->setStatus($newStatus);

                // Update prospect's last contact date if note is completed
                if ($note->isCompleted() && $note->getProspect()) {
                    $oldLastContact = $note->getProspect()->getLastContactDate();
                    $note->getProspect()->setLastContactDate(new DateTime());
                    
                    $this->logger->info('Updated prospect last contact date on status change', [
                        'prospect_id' => $note->getProspect()->getId(),
                        'old_last_contact' => $oldLastContact?->format('Y-m-d H:i:s'),
                        'new_last_contact' => (new DateTime())->format('Y-m-d H:i:s'),
                        'note_status' => $newStatus,
                    ]);
                }

                $entityManager->flush();
                $updateDuration = (microtime(true) - $updateStartTime) * 1000;

                $this->logger->info('Prospect note status updated successfully', [
                    'note_id' => $note->getId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'admin' => $this->getUser()?->getUserIdentifier(),
                    'update_duration_ms' => round($updateDuration, 2),
                    'prospect_contact_updated' => $note->isCompleted() && $note->getProspect(),
                ]);

                $this->addFlash('success', 'Le statut de la note a été mis à jour avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for status update', [
                    'note_id' => $note->getId(),
                    'admin' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

        } catch (\Exception $e) {
            $this->logger->error('Error updating prospect note status', [
                'note_id' => $note->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'admin' => $this->getUser()?->getUserIdentifier(),
                'requested_status' => $request->getPayload()->get('status'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour du statut.');
        }

        return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
    }

    /**
     * Mark note as important.
     */
    #[Route('/{id}/toggle-important', name: 'admin_prospect_note_toggle_important', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleImportant(Request $request, ProspectNote $note, EntityManagerInterface $entityManager): Response
    {
        $this->logger->info('Toggling prospect note importance', [
            'note_id' => $note->getId(),
            'current_importance' => $note->isImportant(),
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $csrfToken = $request->getPayload()->get('_token');
            $expectedToken = 'toggle_important' . $note->getId();

            $this->logger->debug('Importance toggle request details', [
                'note_id' => $note->getId(),
                'csrf_token_provided' => !empty($csrfToken),
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                $oldImportance = $note->isImportant();
                $newImportance = !$oldImportance;

                $toggleStartTime = microtime(true);
                $note->setIsImportant($newImportance);
                $entityManager->flush();
                $toggleDuration = (microtime(true) - $toggleStartTime) * 1000;

                $this->logger->info('Prospect note importance toggled successfully', [
                    'note_id' => $note->getId(),
                    'old_importance' => $oldImportance,
                    'new_importance' => $newImportance,
                    'admin' => $this->getUser()?->getUserIdentifier(),
                    'toggle_duration_ms' => round($toggleDuration, 2),
                ]);

                $message = $note->isImportant()
                    ? 'La note a été marquée comme importante.'
                    : 'La note n\'est plus marquée comme importante.';

                $this->addFlash('success', $message);
            } else {
                $this->logger->warning('Invalid CSRF token for importance toggle', [
                    'note_id' => $note->getId(),
                    'admin' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

        } catch (\Exception $e) {
            $this->logger->error('Error toggling prospect note importance', [
                'note_id' => $note->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification de l\'importance.');
        }

        return $this->redirectToRoute('admin_prospect_note_show', ['id' => $note->getId()]);
    }

    /**
     * List pending tasks.
     */
    #[Route('/tasks/pending', name: 'admin_prospect_note_pending_tasks', methods: ['GET'])]
    public function pendingTasks(ProspectNoteRepository $noteRepository): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Pending prospect tasks accessed', [
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('Fetching pending tasks data');

            $queryStartTime = microtime(true);
            $pendingNotes = $noteRepository->findPendingNotes();
            $pendingQueryDuration = (microtime(true) - $queryStartTime) * 1000;

            $overdueStartTime = microtime(true);
            $overdueNotes = $noteRepository->findOverdueNotes();
            $overdueQueryDuration = (microtime(true) - $overdueStartTime) * 1000;

            $todayStartTime = microtime(true);
            $todayNotes = $noteRepository->findScheduledForToday();
            $todayQueryDuration = (microtime(true) - $todayStartTime) * 1000;

            $totalDuration = (microtime(true) - $startTime) * 1000;

            $this->logger->info('Pending tasks data retrieved successfully', [
                'pending_count' => count($pendingNotes),
                'overdue_count' => count($overdueNotes),
                'today_count' => count($todayNotes),
                'pending_query_ms' => round($pendingQueryDuration, 2),
                'overdue_query_ms' => round($overdueQueryDuration, 2),
                'today_query_ms' => round($todayQueryDuration, 2),
                'total_duration_ms' => round($totalDuration, 2),
            ]);

            // Log statistics for dashboard insights
            $this->logger->debug('Task distribution analysis', [
                'total_tasks' => count($pendingNotes) + count($overdueNotes) + count($todayNotes),
                'overdue_percentage' => count($overdueNotes) > 0 ? round((count($overdueNotes) / (count($pendingNotes) + count($overdueNotes))) * 100, 1) : 0,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

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

        } catch (\Exception $e) {
            $this->logger->error('Error fetching pending tasks', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des tâches en attente.');
            
            // Fallback with empty data
            return $this->render('admin/prospect_note/pending_tasks.html.twig', [
                'pending_notes' => [],
                'overdue_notes' => [],
                'today_notes' => [],
                'page_title' => 'Tâches en attente',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Notes prospects', 'url' => $this->generateUrl('admin_prospect_note_index')],
                    ['label' => 'Tâches en attente', 'url' => null],
                ],
            ]);
        }
    }

    /**
     * List important notes.
     */
    #[Route('/important', name: 'admin_prospect_note_important', methods: ['GET'])]
    public function important(ProspectNoteRepository $noteRepository): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Important prospect notes accessed', [
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $queryStartTime = microtime(true);
            $importantNotes = $noteRepository->findImportantNotes();
            $queryDuration = (microtime(true) - $queryStartTime) * 1000;
            $totalDuration = (microtime(true) - $startTime) * 1000;

            $this->logger->info('Important notes retrieved successfully', [
                'important_notes_count' => count($importantNotes),
                'query_duration_ms' => round($queryDuration, 2),
                'total_duration_ms' => round($totalDuration, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            // Log distribution analysis
            if (count($importantNotes) > 0) {
                $typeDistribution = [];
                $statusDistribution = [];
                foreach ($importantNotes as $note) {
                    $type = $note->getType();
                    $status = $note->getStatus();
                    $typeDistribution[$type] = ($typeDistribution[$type] ?? 0) + 1;
                    $statusDistribution[$status] = ($statusDistribution[$status] ?? 0) + 1;
                }

                $this->logger->debug('Important notes distribution', [
                    'type_distribution' => $typeDistribution,
                    'status_distribution' => $statusDistribution,
                ]);
            }

            return $this->render('admin/prospect_note/important.html.twig', [
                'important_notes' => $importantNotes,
                'page_title' => 'Notes importantes',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Notes prospects', 'url' => $this->generateUrl('admin_prospect_note_index')],
                    ['label' => 'Notes importantes', 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error fetching important notes', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des notes importantes.');
            
            // Fallback with empty data
            return $this->render('admin/prospect_note/important.html.twig', [
                'important_notes' => [],
                'page_title' => 'Notes importantes',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Notes prospects', 'url' => $this->generateUrl('admin_prospect_note_index')],
                    ['label' => 'Notes importantes', 'url' => null],
                ],
            ]);
        }
    }

    /**
     * Export notes to CSV.
     */
    #[Route('/export', name: 'admin_prospect_note_export', methods: ['GET'])]
    public function export(ProspectNoteRepository $noteRepository): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Prospect notes export initiated', [
            'admin' => $this->getUser()?->getUserIdentifier(),
            'export_format' => 'CSV',
        ]);

        try {
            $queryStartTime = microtime(true);
            $notes = $noteRepository->findAll();
            $queryDuration = (microtime(true) - $queryStartTime) * 1000;

            $this->logger->info('Notes retrieved for export', [
                'notes_count' => count($notes),
                'query_duration_ms' => round($queryDuration, 2),
            ]);

            $response = new Response();
            $filename = 'prospect_notes_' . date('Y-m-d_H-i-s') . '.csv';
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

            $this->logger->debug('CSV response headers set', [
                'filename' => $filename,
                'content_type' => 'text/csv',
            ]);

            $csvStartTime = microtime(true);
            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8 Excel compatibility
            fwrite($output, "\xEF\xBB\xBF");

            // CSV headers
            $headers = [
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
            ];

            fputcsv($output, $headers, ';'); // Use semicolon for European CSV format

            $exportedCount = 0;
            $errorCount = 0;

            // CSV data
            foreach ($notes as $note) {
                try {
                    $row = [
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
                    ];

                    fputcsv($output, $row, ';');
                    $exportedCount++;
                } catch (\Exception $rowException) {
                    $errorCount++;
                    $this->logger->warning('Error exporting note row', [
                        'note_id' => $note->getId(),
                        'error' => $rowException->getMessage(),
                    ]);
                }
            }

            fclose($output);

            $csvDuration = (microtime(true) - $csvStartTime) * 1000;
            $totalDuration = (microtime(true) - $startTime) * 1000;

            $this->logger->info('Prospect notes exported successfully', [
                'total_notes' => count($notes),
                'exported_count' => $exportedCount,
                'error_count' => $errorCount,
                'filename' => $filename,
                'admin' => $this->getUser()?->getUserIdentifier(),
                'csv_generation_ms' => round($csvDuration, 2),
                'total_duration_ms' => round($totalDuration, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Error exporting prospect notes', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'admin' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'export des notes.');
            return $this->redirectToRoute('admin_prospect_note_index');
        }
    }
}
