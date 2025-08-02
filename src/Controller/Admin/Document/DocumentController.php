<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\Document;
use App\Form\Document\DocumentType as DocumentFormType;
use App\Repository\Document\DocumentRepository;
use App\Service\Document\DocumentService;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document Controller.
 *
 * Handles CRUD operations for documents in the admin interface.
 * Provides complete document management with type-specific features.
 */
#[Route('/admin/documents')]
#[IsGranted('ROLE_ADMIN')]
class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentService $documentService,
        private LoggerInterface $logger,
    ) {}

    /**
     * List all documents with filtering and pagination.
     */
    #[Route('/', name: 'admin_document_index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository): Response
    {
        $this->logger->info('Starting document index action', [
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'request_uri' => $request->getRequestUri(),
            'query_params' => $request->query->all(),
        ]);

        try {
            // Get filter parameters
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 20;
            $filters = [
                'search' => $request->query->get('search'),
                'type' => $request->query->get('type'),
                'category' => $request->query->get('category'),
                'status' => $request->query->get('status'),
                'author' => $request->query->get('author'),
            ];

            $this->logger->debug('Document index filters applied', [
                'page' => $page,
                'limit' => $limit,
                'filters' => array_filter($filters), // Only log non-null filters
                'active_filters_count' => count(array_filter($filters)),
            ]);

            // Build query with filters
            $this->logger->debug('Building document query with filters');
            $queryBuilder = $documentRepository->createAdminQueryBuilder($filters);

            // Get total count for pagination
            $this->logger->debug('Calculating total items count');
            $totalItems = count($queryBuilder->getQuery()->getResult());
            $this->logger->debug('Total items calculated', ['total_items' => $totalItems]);

            // Apply pagination
            $this->logger->debug('Applying pagination to query', [
                'first_result' => ($page - 1) * $limit,
                'max_results' => $limit,
            ]);

            $documents = $queryBuilder
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
            ;

            $totalPages = ceil($totalItems / $limit);

            $this->logger->debug('Documents retrieved successfully', [
                'documents_count' => count($documents),
                'total_pages' => $totalPages,
                'current_page' => $page,
            ]);

            // Get statistics for the dashboard
            $this->logger->debug('Calculating document statistics');
            $stats = [
                'total' => $documentRepository->count([]),
                'published' => $documentRepository->count(['status' => Document::STATUS_PUBLISHED]),
                'draft' => $documentRepository->count(['status' => Document::STATUS_DRAFT]),
                'review' => $documentRepository->count(['status' => Document::STATUS_REVIEW]),
            ];

            $this->logger->info('Document statistics calculated', [
                'stats' => $stats,
                'stats_total_check' => $stats['published'] + $stats['draft'] + $stats['review'],
            ]);

            $this->logger->info('Document index action completed successfully', [
                'documents_returned' => count($documents),
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                ],
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            return $this->render('admin/document/index.html.twig', [
                'documents' => $documents,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'filters' => $filters,
                'stats' => $stats,
            ]);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in document index', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'previous_exception' => $e->getPrevious()?->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => $filters ?? [],
                'page' => $page ?? 1,
            ]);
            $this->addFlash('error', 'Erreur de base de données lors de la récupération des documents.');

            return $this->render('admin/document/index.html.twig', [
                'documents' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_items' => 0,
                'filters' => [],
                'stats' => ['total' => 0, 'published' => 0, 'draft' => 0, 'review' => 0],
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in document index', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'method' => $request->getMethod(),
                    'uri' => $request->getRequestUri(),
                    'query_params' => $request->query->all(),
                ],
                'user_context' => [
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                ],
            ]);
            $this->addFlash('error', 'Une erreur inattendue est survenue lors du chargement des documents.');

            return $this->render('admin/document/index.html.twig', [
                'documents' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_items' => 0,
                'filters' => [],
                'stats' => ['total' => 0, 'published' => 0, 'draft' => 0, 'review' => 0],
            ]);
        }
    }

    /**
     * Show a specific document.
     */
    #[Route('/{id}', name: 'admin_document_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Document $document): Response
    {
        $this->logger->info('Starting document show action', [
            'document_id' => $document->getId(),
            'document_title' => $document->getTitle(),
            'document_slug' => $document->getSlug(),
            'document_status' => $document->getStatus(),
            'document_type' => $document->getDocumentType()?->getName(),
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('Loading document details for display', [
                'document_id' => $document->getId(),
                'has_content' => !empty($document->getContent()),
                'content_length' => $document->getContent() ? strlen($document->getContent()) : 0,
                'has_metadata' => !empty($document->getMetadata()),
                'metadata_count' => $document->getMetadata() ? count($document->getMetadata()) : 0,
                'has_versions' => $document->getVersions()->count() > 0,
                'versions_count' => $document->getVersions()->count(),
                'current_version' => $document->getCurrentVersion()?->getVersion(),
                'created_at' => $document->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $document->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'created_by' => $document->getCreatedBy()?->getFullName(),
                'updated_by' => $document->getUpdatedBy()?->getFullName(),
            ]);

            // Log access for audit purposes
            $this->logger->info('Document accessed successfully', [
                'document_id' => $document->getId(),
                'document_title' => $document->getTitle(),
                'accessed_by_user' => $this->getUser()?->getUserIdentifier(),
                'access_timestamp' => new DateTimeImmutable(),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            return $this->render('admin/document/show.html.twig', [
                'document' => $document,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error displaying document', [
                'document_id' => $document->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_context' => [
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                ],
            ]);

            $this->addFlash('error', 'Erreur lors de l\'affichage du document.');

            return $this->redirectToRoute('admin_document_index');
        }
    }

    /**
     * Create a new document.
     */
    #[Route('/new', name: 'admin_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('Starting document creation process', [
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'query_params' => $request->query->all(),
            'user_agent' => $request->headers->get('User-Agent'),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            $document = new Document();

            // Pre-select document type if provided
            $typeId = $request->query->get('type');
            if ($typeId) {
                $this->logger->debug('Pre-selecting document type from query parameter', [
                    'type_id' => $typeId,
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                try {
                    $documentType = $this->documentService->getDocumentTypeById((int) $typeId);
                    if ($documentType) {
                        $document->setDocumentType($documentType);
                        $this->logger->info('Document type pre-selected successfully', [
                            'type_id' => $typeId,
                            'type_name' => $documentType->getName(),
                            'user' => $this->getUser()?->getUserIdentifier(),
                        ]);
                    } else {
                        $this->logger->warning('Document type not found for pre-selection', [
                            'type_id' => $typeId,
                            'user' => $this->getUser()?->getUserIdentifier(),
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Error pre-selecting document type', [
                        'type_id' => $typeId,
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                }
            }

            $this->logger->debug('Creating document form', [
                'document_has_type' => $document->getDocumentType() !== null,
                'pre_selected_type' => $document->getDocumentType()?->getName(),
            ]);

            $form = $this->createForm(DocumentFormType::class, $document);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Document creation form submitted', [
                    'is_valid' => $form->isValid(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'form_data' => [
                        'title' => $form->get('title')?->getData(),
                        'slug' => $form->get('slug')?->getData(),
                        'status' => $form->get('status')?->getData(),
                        'document_type' => $form->get('documentType')?->getData()?->getName(),
                        'category' => $form->get('category')?->getData()?->getName(),
                        'is_active' => $form->get('isActive')?->getData(),
                        'is_public' => $form->get('isPublic')?->getData(),
                        'has_content' => !empty($form->get('content')?->getData()),
                        'content_length' => $form->get('content')?->getData() ? strlen($form->get('content')->getData()) : 0,
                        'tags_count' => $form->get('tags')?->getData() ? count($form->get('tags')->getData()) : 0,
                    ],
                    'form_errors' => $form->isValid() ? [] : $this->getFormErrorsAsArray($form),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Form validation passed, attempting to create document');

                    try {
                        $startTime = microtime(true);
                        $result = $this->documentService->createDocument($document);
                        $executionTime = microtime(true) - $startTime;

                        $this->logger->info('Document service create operation completed', [
                            'result_success' => $result['success'],
                            'execution_time' => $executionTime,
                            'user' => $this->getUser()?->getUserIdentifier(),
                            'document_id' => $result['success'] ? $document->getId() : null,
                            'document_title' => $document->getTitle(),
                            'document_slug' => $document->getSlug(),
                            'errors_count' => $result['success'] ? 0 : count($result['errors']),
                        ]);

                        if ($result['success']) {
                            $this->logger->info('Document created successfully', [
                                'document_id' => $document->getId(),
                                'document_title' => $document->getTitle(),
                                'document_slug' => $document->getSlug(),
                                'document_type' => $document->getDocumentType()?->getName(),
                                'document_status' => $document->getStatus(),
                                'created_by' => $this->getUser()?->getUserIdentifier(),
                                'creation_timestamp' => $document->getCreatedAt()?->format('Y-m-d H:i:s'),
                                'total_execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                            ]);

                            $this->addFlash('success', 'Document créé avec succès.');

                            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
                        }

                        // Log service errors
                        foreach ($result['errors'] as $error) {
                            $this->logger->warning('Document creation service error', [
                                'error' => $error,
                                'document_title' => $document->getTitle(),
                                'user' => $this->getUser()?->getUserIdentifier(),
                            ]);
                            $this->addFlash('error', $error);
                        }
                    } catch (Exception $e) {
                        $this->logger->error('Exception during document creation', [
                            'error_message' => $e->getMessage(),
                            'error_class' => get_class($e),
                            'error_file' => $e->getFile(),
                            'error_line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                            'document_data' => [
                                'title' => $document->getTitle(),
                                'slug' => $document->getSlug(),
                                'type' => $document->getDocumentType()?->getName(),
                                'status' => $document->getStatus(),
                            ],
                            'user' => $this->getUser()?->getUserIdentifier(),
                        ]);
                        $this->addFlash('error', 'Une erreur est survenue lors de la création du document.');
                    }
                } else {
                    $this->logger->warning('Document creation form validation failed', [
                        'user' => $this->getUser()?->getUserIdentifier(),
                        'form_errors_count' => count($this->getFormErrorsAsArray($form)),
                        'form_errors' => $this->getFormErrorsAsArray($form),
                    ]);
                }
            } else {
                $this->logger->debug('Document creation form displayed (GET request)', [
                    'pre_selected_type' => $document->getDocumentType()?->getName(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
            }

            return $this->render('admin/document/new.html.twig', [
                'document' => $document,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Critical error in document creation process', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'method' => $request->getMethod(),
                    'uri' => $request->getRequestUri(),
                    'query_params' => $request->query->all(),
                    'post_data_keys' => $request->request->keys(),
                ],
                'user_context' => [
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                ],
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue. Veuillez réessayer.');

            return $this->redirectToRoute('admin_document_index');
        }
    }

    /**
     * Edit an existing document.
     */
    #[Route('/{id}/edit', name: 'admin_document_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Document $document): Response
    {
        $this->logger->info('Starting document edit process', [
            'document_id' => $document->getId(),
            'document_title' => $document->getTitle(),
            'document_slug' => $document->getSlug(),
            'document_status' => $document->getStatus(),
            'document_type' => $document->getDocumentType()?->getName(),
            'current_version' => $document->getVersion(),
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        try {
            // Store original data for comparison
            $originalData = [
                'title' => $document->getTitle(),
                'slug' => $document->getSlug(),
                'content' => $document->getContent(),
                'description' => $document->getDescription(),
                'status' => $document->getStatus(),
                'version' => $document->getVersion(),
                'is_active' => $document->isActive(),
                'is_public' => $document->isPublic(),
                'tags' => $document->getTags(),
                'updated_at' => $document->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];

            $this->logger->debug('Original document data captured for comparison', [
                'document_id' => $document->getId(),
                'original_data_keys' => array_keys($originalData),
                'content_length' => $originalData['content'] ? strlen($originalData['content']) : 0,
                'tags_count' => $originalData['tags'] ? count($originalData['tags']) : 0,
            ]);

            $form = $this->createForm(DocumentFormType::class, $document);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Document edit form submitted', [
                    'document_id' => $document->getId(),
                    'is_valid' => $form->isValid(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'form_data' => [
                        'title' => $form->get('title')?->getData(),
                        'slug' => $form->get('slug')?->getData(),
                        'status' => $form->get('status')?->getData(),
                        'document_type' => $form->get('documentType')?->getData()?->getName(),
                        'category' => $form->get('category')?->getData()?->getName(),
                        'is_active' => $form->get('isActive')?->getData(),
                        'is_public' => $form->get('isPublic')?->getData(),
                        'has_content' => !empty($form->get('content')?->getData()),
                        'content_length' => $form->get('content')?->getData() ? strlen($form->get('content')->getData()) : 0,
                        'tags_count' => $form->get('tags')?->getData() ? count($form->get('tags')->getData()) : 0,
                    ],
                    'form_errors' => $form->isValid() ? [] : $this->getFormErrorsAsArray($form),
                ]);

                if ($form->isValid()) {
                    try {
                        // Get version management data from form
                        $versionType = $form->get('versionType')->getData() ?? 'minor';
                        $versionMessage = $form->get('versionMessage')->getData();

                        $this->logger->debug('Version management data extracted from form', [
                            'document_id' => $document->getId(),
                            'version_type' => $versionType,
                            'has_version_message' => !empty($versionMessage),
                            'version_message_length' => $versionMessage ? strlen($versionMessage) : 0,
                            'current_version' => $document->getVersion(),
                        ]);

                        // Validate version message for new versions
                        if ($versionType !== 'none' && !$versionMessage) {
                            $this->logger->warning('Version message missing for new version creation', [
                                'document_id' => $document->getId(),
                                'version_type' => $versionType,
                                'user' => $this->getUser()?->getUserIdentifier(),
                            ]);

                            $this->addFlash('error', 'Un message de version est obligatoire pour créer une nouvelle version.');

                            return $this->render('admin/document/edit.html.twig', [
                                'document' => $document,
                                'form' => $form,
                            ]);
                        }

                        // Detect changes made
                        $changes = [];
                        $newData = [
                            'title' => $document->getTitle(),
                            'slug' => $document->getSlug(),
                            'content' => $document->getContent(),
                            'description' => $document->getDescription(),
                            'status' => $document->getStatus(),
                            'is_active' => $document->isActive(),
                            'is_public' => $document->isPublic(),
                            'tags' => $document->getTags(),
                        ];

                        foreach ($newData as $field => $newValue) {
                            if ($originalData[$field] !== $newValue) {
                                $changes[$field] = [
                                    'old' => $originalData[$field],
                                    'new' => $newValue,
                                ];
                            }
                        }

                        $this->logger->info('Document changes detected', [
                            'document_id' => $document->getId(),
                            'changes_count' => count($changes),
                            'changed_fields' => array_keys($changes),
                            'has_content_changes' => array_key_exists('content', $changes),
                            'user' => $this->getUser()?->getUserIdentifier(),
                        ]);

                        // Pass version data to service
                        $versionData = [
                            'type' => $versionType,
                            'message' => $versionMessage,
                        ];

                        $this->logger->debug('Calling document service for update', [
                            'document_id' => $document->getId(),
                            'version_data' => $versionData,
                            'changes_count' => count($changes),
                        ]);

                        $startTime = microtime(true);
                        $result = $this->documentService->updateDocument($document, $versionData);
                        $executionTime = microtime(true) - $startTime;

                        $this->logger->info('Document service update operation completed', [
                            'document_id' => $document->getId(),
                            'result_success' => $result['success'],
                            'execution_time' => $executionTime,
                            'user' => $this->getUser()?->getUserIdentifier(),
                            'errors_count' => $result['success'] ? 0 : count($result['errors']),
                            'new_version_created' => isset($result['new_version']),
                            'new_version_number' => $result['new_version']?->getVersion() ?? null,
                        ]);

                        if ($result['success']) {
                            $message = 'Document modifié avec succès.';
                            if (isset($result['new_version'])) {
                                $message .= sprintf(' Nouvelle version %s créée.', $result['new_version']->getVersion());

                                $this->logger->info('New document version created during update', [
                                    'document_id' => $document->getId(),
                                    'new_version' => $result['new_version']->getVersion(),
                                    'version_type' => $versionType,
                                    'version_message' => $versionMessage,
                                    'user' => $this->getUser()?->getUserIdentifier(),
                                ]);
                            }

                            $this->logger->info('Document updated successfully', [
                                'document_id' => $document->getId(),
                                'document_title' => $document->getTitle(),
                                'changes_applied' => count($changes),
                                'updated_by' => $this->getUser()?->getUserIdentifier(),
                                'update_timestamp' => $document->getUpdatedAt()?->format('Y-m-d H:i:s'),
                                'total_execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                            ]);

                            $this->addFlash('success', $message);

                            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
                        }

                        // Log service errors
                        foreach ($result['errors'] as $error) {
                            $this->logger->warning('Document update service error', [
                                'error' => $error,
                                'document_id' => $document->getId(),
                                'user' => $this->getUser()?->getUserIdentifier(),
                            ]);
                            $this->addFlash('error', $error);
                        }
                    } catch (Exception $e) {
                        $this->logger->error('Exception during document update', [
                            'document_id' => $document->getId(),
                            'error_message' => $e->getMessage(),
                            'error_class' => get_class($e),
                            'error_file' => $e->getFile(),
                            'error_line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                            'user' => $this->getUser()?->getUserIdentifier(),
                        ]);
                        $this->addFlash('error', 'Une erreur est survenue lors de la modification du document.');
                    }
                } else {
                    $this->logger->warning('Document edit form validation failed', [
                        'document_id' => $document->getId(),
                        'user' => $this->getUser()?->getUserIdentifier(),
                        'form_errors_count' => count($this->getFormErrorsAsArray($form)),
                        'form_errors' => $this->getFormErrorsAsArray($form),
                    ]);
                }
            } else {
                $this->logger->debug('Document edit form displayed (GET request)', [
                    'document_id' => $document->getId(),
                    'document_title' => $document->getTitle(),
                    'current_version' => $document->getVersion(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
            }

            return $this->render('admin/document/edit.html.twig', [
                'document' => $document,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Critical error in document edit process', [
                'document_id' => $document->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'method' => $request->getMethod(),
                    'uri' => $request->getRequestUri(),
                    'post_data_keys' => $request->request->keys(),
                ],
                'user_context' => [
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                ],
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue. Veuillez réessayer.');

            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }
    }

    /**
     * Delete a document.
     */
    #[Route('/{id}/delete', name: 'admin_document_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Document $document): Response
    {
        $this->logger->info('Starting document deletion process', [
            'document_id' => $document->getId(),
            'document_title' => $document->getTitle(),
            'document_slug' => $document->getSlug(),
            'document_status' => $document->getStatus(),
            'document_type' => $document->getDocumentType()?->getName(),
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('Referer'),
        ]);

        try {
            // Store document info for post-deletion logging
            $documentInfo = [
                'id' => $document->getId(),
                'title' => $document->getTitle(),
                'slug' => $document->getSlug(),
                'status' => $document->getStatus(),
                'type' => $document->getDocumentType()?->getName(),
                'category' => $document->getCategory()?->getName(),
                'created_at' => $document->getCreatedAt()?->format('Y-m-d H:i:s'),
                'versions_count' => $document->getVersions()->count(),
                'metadata_count' => $document->getMetadata()->count(),
                'download_count' => $document->getDownloadCount(),
                'created_by' => $document->getCreatedBy()?->getFullName(),
                'updated_by' => $document->getUpdatedBy()?->getFullName(),
            ];

            $this->logger->debug('Document information captured before deletion', [
                'document_info' => $documentInfo,
            ]);

            // CSRF protection
            $csrfToken = $request->request->get('_token');
            $expectedToken = 'delete' . $document->getId();

            $this->logger->debug('CSRF token validation for document deletion', [
                'document_id' => $document->getId(),
                'has_token' => !empty($csrfToken),
                'token_length' => $csrfToken ? strlen($csrfToken) : 0,
                'expected_token_pattern' => 'delete' . $document->getId(),
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                $this->logger->info('CSRF token validated successfully for document deletion', [
                    'document_id' => $document->getId(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                try {
                    $startTime = microtime(true);
                    $result = $this->documentService->deleteDocument($document);
                    $executionTime = microtime(true) - $startTime;

                    $this->logger->info('Document service delete operation completed', [
                        'result_success' => $result['success'],
                        'execution_time' => $executionTime,
                        'user' => $this->getUser()?->getUserIdentifier(),
                        'errors_count' => $result['success'] ? 0 : count($result['errors']),
                        'document_info' => $documentInfo,
                    ]);

                    if ($result['success']) {
                        $this->logger->info('Document deleted successfully', [
                            'deleted_document' => $documentInfo,
                            'deleted_by' => $this->getUser()?->getUserIdentifier(),
                            'deletion_timestamp' => new DateTimeImmutable(),
                            'total_execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                        ]);

                        $this->addFlash('success', 'Document supprimé avec succès.');
                    } else {
                        foreach ($result['errors'] as $error) {
                            $this->logger->warning('Document deletion service error', [
                                'error' => $error,
                                'document_id' => $document->getId(),
                                'user' => $this->getUser()?->getUserIdentifier(),
                            ]);
                            $this->addFlash('error', $error);
                        }

                        $this->logger->warning('Document deletion failed, redirecting to show page', [
                            'document_id' => $document->getId(),
                            'errors' => $result['errors'],
                        ]);

                        return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Exception during document deletion', [
                        'document_id' => $document->getId(),
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'document_info' => $documentInfo,
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de la suppression du document.');

                    return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
                }
            } else {
                $this->logger->warning('CSRF token validation failed for document deletion', [
                    'document_id' => $document->getId(),
                    'provided_token_length' => $csrfToken ? strlen($csrfToken) : 0,
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');

                return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
            }
        } catch (Exception $e) {
            $this->logger->critical('Critical error in document deletion process', [
                'document_id' => $document->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'method' => $request->getMethod(),
                    'uri' => $request->getRequestUri(),
                    'post_data_keys' => $request->request->keys(),
                ],
                'user_context' => [
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                ],
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue. Veuillez réessayer.');

            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        return $this->redirectToRoute('admin_document_index');
    }

    /**
     * Publish a document.
     */
    #[Route('/{id}/publish', name: 'admin_document_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(Request $request, Document $document): Response
    {
        $this->logger->info('Starting document publish process', [
            'document_id' => $document->getId(),
            'document_title' => $document->getTitle(),
            'document_slug' => $document->getSlug(),
            'current_status' => $document->getStatus(),
            'document_type' => $document->getDocumentType()?->getName(),
            'is_currently_published' => $document->isPublished(),
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('Referer'),
        ]);

        try {
            $csrfToken = $request->request->get('_token');
            $expectedToken = 'publish' . $document->getId();

            $this->logger->debug('CSRF token validation for document publish', [
                'document_id' => $document->getId(),
                'has_token' => !empty($csrfToken),
                'token_length' => $csrfToken ? strlen($csrfToken) : 0,
                'expected_token_pattern' => 'publish' . $document->getId(),
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                $this->logger->info('CSRF token validated successfully for document publish', [
                    'document_id' => $document->getId(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                try {
                    $originalStatus = $document->getStatus();
                    $originalPublishedAt = $document->getPublishedAt();

                    $this->logger->debug('Document state before publish attempt', [
                        'document_id' => $document->getId(),
                        'original_status' => $originalStatus,
                        'original_published_at' => $originalPublishedAt?->format('Y-m-d H:i:s'),
                        'is_active' => $document->isActive(),
                        'is_public' => $document->isPublic(),
                        'has_content' => !empty($document->getContent()),
                        'content_length' => $document->getContent() ? strlen($document->getContent()) : 0,
                    ]);

                    $startTime = microtime(true);
                    $result = $this->documentService->publishDocument($document);
                    $executionTime = microtime(true) - $startTime;

                    $this->logger->info('Document service publish operation completed', [
                        'document_id' => $document->getId(),
                        'result_success' => $result['success'],
                        'execution_time' => $executionTime,
                        'user' => $this->getUser()?->getUserIdentifier(),
                        'errors_count' => $result['success'] ? 0 : count($result['errors']),
                        'status_changed' => $originalStatus !== $document->getStatus(),
                        'new_status' => $document->getStatus(),
                        'new_published_at' => $document->getPublishedAt()?->format('Y-m-d H:i:s'),
                    ]);

                    if ($result['success']) {
                        $this->logger->info('Document published successfully', [
                            'document_id' => $document->getId(),
                            'document_title' => $document->getTitle(),
                            'document_slug' => $document->getSlug(),
                            'status_change' => [
                                'from' => $originalStatus,
                                'to' => $document->getStatus(),
                            ],
                            'published_at' => $document->getPublishedAt()?->format('Y-m-d H:i:s'),
                            'published_by' => $this->getUser()?->getUserIdentifier(),
                            'publication_timestamp' => new DateTimeImmutable(),
                            'total_execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                        ]);

                        $this->addFlash('success', 'Document publié avec succès.');
                    } else {
                        foreach ($result['errors'] as $error) {
                            $this->logger->warning('Document publish service error', [
                                'error' => $error,
                                'document_id' => $document->getId(),
                                'user' => $this->getUser()?->getUserIdentifier(),
                            ]);
                            $this->addFlash('error', $error);
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->error('Exception during document publish', [
                        'document_id' => $document->getId(),
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de la publication.');
                }
            } else {
                $this->logger->warning('CSRF token validation failed for document publish', [
                    'document_id' => $document->getId(),
                    'provided_token_length' => $csrfToken ? strlen($csrfToken) : 0,
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } catch (Exception $e) {
            $this->logger->critical('Critical error in document publish process', [
                'document_id' => $document->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'method' => $request->getMethod(),
                    'uri' => $request->getRequestUri(),
                    'post_data_keys' => $request->request->keys(),
                ],
                'user_context' => [
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                ],
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
    }

    /**
     * Archive a document.
     */
    #[Route('/{id}/archive', name: 'admin_document_archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archive(Request $request, Document $document): Response
    {
        $this->logger->info('Starting document archive process', [
            'document_id' => $document->getId(),
            'document_title' => $document->getTitle(),
            'document_slug' => $document->getSlug(),
            'current_status' => $document->getStatus(),
            'document_type' => $document->getDocumentType()?->getName(),
            'is_currently_archived' => $document->isArchived(),
            'is_currently_published' => $document->isPublished(),
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('Referer'),
        ]);

        try {
            $csrfToken = $request->request->get('_token');
            $expectedToken = 'archive' . $document->getId();

            $this->logger->debug('CSRF token validation for document archive', [
                'document_id' => $document->getId(),
                'has_token' => !empty($csrfToken),
                'token_length' => $csrfToken ? strlen($csrfToken) : 0,
                'expected_token_pattern' => 'archive' . $document->getId(),
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                $this->logger->info('CSRF token validated successfully for document archive', [
                    'document_id' => $document->getId(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                try {
                    $originalStatus = $document->getStatus();
                    $originalPublishedAt = $document->getPublishedAt();

                    $this->logger->debug('Document state before archive attempt', [
                        'document_id' => $document->getId(),
                        'original_status' => $originalStatus,
                        'original_published_at' => $originalPublishedAt?->format('Y-m-d H:i:s'),
                        'is_active' => $document->isActive(),
                        'is_public' => $document->isPublic(),
                        'download_count' => $document->getDownloadCount(),
                        'versions_count' => $document->getVersions()->count(),
                    ]);

                    $startTime = microtime(true);
                    $result = $this->documentService->archiveDocument($document);
                    $executionTime = microtime(true) - $startTime;

                    $this->logger->info('Document service archive operation completed', [
                        'document_id' => $document->getId(),
                        'result_success' => $result['success'],
                        'execution_time' => $executionTime,
                        'user' => $this->getUser()?->getUserIdentifier(),
                        'errors_count' => $result['success'] ? 0 : count($result['errors']),
                        'status_changed' => $originalStatus !== $document->getStatus(),
                        'new_status' => $document->getStatus(),
                    ]);

                    if ($result['success']) {
                        $this->logger->info('Document archived successfully', [
                            'document_id' => $document->getId(),
                            'document_title' => $document->getTitle(),
                            'document_slug' => $document->getSlug(),
                            'status_change' => [
                                'from' => $originalStatus,
                                'to' => $document->getStatus(),
                            ],
                            'was_published' => $originalPublishedAt !== null,
                            'archived_by' => $this->getUser()?->getUserIdentifier(),
                            'archive_timestamp' => new DateTimeImmutable(),
                            'total_execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                        ]);

                        $this->addFlash('success', 'Document archivé avec succès.');
                    } else {
                        foreach ($result['errors'] as $error) {
                            $this->logger->warning('Document archive service error', [
                                'error' => $error,
                                'document_id' => $document->getId(),
                                'user' => $this->getUser()?->getUserIdentifier(),
                            ]);
                            $this->addFlash('error', $error);
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->error('Exception during document archive', [
                        'document_id' => $document->getId(),
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'archivage.');
                }
            } else {
                $this->logger->warning('CSRF token validation failed for document archive', [
                    'document_id' => $document->getId(),
                    'provided_token_length' => $csrfToken ? strlen($csrfToken) : 0,
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } catch (Exception $e) {
            $this->logger->critical('Critical error in document archive process', [
                'document_id' => $document->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'method' => $request->getMethod(),
                    'uri' => $request->getRequestUri(),
                    'post_data_keys' => $request->request->keys(),
                ],
                'user_context' => [
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                ],
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
    }

    /**
     * Duplicate a document.
     */
    #[Route('/{id}/duplicate', name: 'admin_document_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, Document $document): Response
    {
        $this->logger->info('Starting document duplication process', [
            'source_document_id' => $document->getId(),
            'source_document_title' => $document->getTitle(),
            'source_document_slug' => $document->getSlug(),
            'source_status' => $document->getStatus(),
            'source_type' => $document->getDocumentType()?->getName(),
            'source_category' => $document->getCategory()?->getName(),
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('Referer'),
        ]);

        try {
            $csrfToken = $request->request->get('_token');
            $expectedToken = 'duplicate' . $document->getId();

            $this->logger->debug('CSRF token validation for document duplication', [
                'source_document_id' => $document->getId(),
                'has_token' => !empty($csrfToken),
                'token_length' => $csrfToken ? strlen($csrfToken) : 0,
                'expected_token_pattern' => 'duplicate' . $document->getId(),
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                $this->logger->info('CSRF token validated successfully for document duplication', [
                    'source_document_id' => $document->getId(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                try {
                    // Capture source document details for logging
                    $sourceDocumentInfo = [
                        'id' => $document->getId(),
                        'title' => $document->getTitle(),
                        'slug' => $document->getSlug(),
                        'status' => $document->getStatus(),
                        'type' => $document->getDocumentType()?->getName(),
                        'category' => $document->getCategory()?->getName(),
                        'is_active' => $document->isActive(),
                        'is_public' => $document->isPublic(),
                        'content_length' => $document->getContent() ? strlen($document->getContent()) : 0,
                        'versions_count' => $document->getVersions()->count(),
                        'metadata_count' => $document->getMetadata()->count(),
                        'tags_count' => $document->getTags() ? count($document->getTags()) : 0,
                        'download_count' => $document->getDownloadCount(),
                        'created_at' => $document->getCreatedAt()?->format('Y-m-d H:i:s'),
                        'updated_at' => $document->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    ];

                    $this->logger->debug('Source document information captured for duplication', [
                        'source_document_info' => $sourceDocumentInfo,
                    ]);

                    $startTime = microtime(true);
                    $result = $this->documentService->duplicateDocument($document);
                    $executionTime = microtime(true) - $startTime;

                    $this->logger->info('Document service duplication operation completed', [
                        'result_success' => $result['success'],
                        'execution_time' => $executionTime,
                        'user' => $this->getUser()?->getUserIdentifier(),
                        'errors_count' => $result['success'] ? 0 : count($result['errors']),
                        'source_document_id' => $document->getId(),
                        'duplicate_document_id' => $result['success'] ? $result['document']->getId() : null,
                    ]);

                    if ($result['success']) {
                        $duplicateDocument = $result['document'];

                        $this->logger->info('Document duplicated successfully', [
                            'source_document' => $sourceDocumentInfo,
                            'duplicate_document' => [
                                'id' => $duplicateDocument->getId(),
                                'title' => $duplicateDocument->getTitle(),
                                'slug' => $duplicateDocument->getSlug(),
                                'status' => $duplicateDocument->getStatus(),
                                'type' => $duplicateDocument->getDocumentType()?->getName(),
                                'created_at' => $duplicateDocument->getCreatedAt()?->format('Y-m-d H:i:s'),
                                'created_by' => $duplicateDocument->getCreatedBy()?->getFullName(),
                            ],
                            'duplicated_by' => $this->getUser()?->getUserIdentifier(),
                            'duplication_timestamp' => new DateTimeImmutable(),
                            'total_execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                        ]);

                        $this->addFlash('success', 'Document dupliqué avec succès.');

                        return $this->redirectToRoute('admin_document_edit', ['id' => $duplicateDocument->getId()]);
                    }

                    // Log service errors
                    foreach ($result['errors'] as $error) {
                        $this->logger->warning('Document duplication service error', [
                            'error' => $error,
                            'source_document_id' => $document->getId(),
                            'user' => $this->getUser()?->getUserIdentifier(),
                        ]);
                        $this->addFlash('error', $error);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Exception during document duplication', [
                        'source_document_id' => $document->getId(),
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de la duplication.');
                }
            } else {
                $this->logger->warning('CSRF token validation failed for document duplication', [
                    'source_document_id' => $document->getId(),
                    'provided_token_length' => $csrfToken ? strlen($csrfToken) : 0,
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } catch (Exception $e) {
            $this->logger->critical('Critical error in document duplication process', [
                'source_document_id' => $document->getId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'method' => $request->getMethod(),
                    'uri' => $request->getRequestUri(),
                    'post_data_keys' => $request->request->keys(),
                ],
                'user_context' => [
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'ip_address' => $request->getClientIp(),
                ],
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
    }

    /**
     * Helper method to extract form errors as array for logging.
     *
     * @param mixed $form
     */
    private function getFormErrorsAsArray($form): array
    {
        $errors = [];

        // Get form-level errors
        foreach ($form->getErrors() as $error) {
            $errors['form'][] = $error->getMessage();
        }

        // Get field-level errors
        foreach ($form->all() as $child) {
            foreach ($child->getErrors() as $error) {
                $errors[$child->getName()][] = $error->getMessage();
            }
        }

        return $errors;
    }
}
