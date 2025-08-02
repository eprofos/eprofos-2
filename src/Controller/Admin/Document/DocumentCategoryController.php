<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentCategory;
use App\Form\Document\DocumentCategoryType;
use App\Repository\Document\DocumentCategoryRepository;
use App\Service\Document\DocumentCategoryService;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document Category Controller.
 *
 * Handles CRUD operations for document categories in the admin interface.
 * Provides hierarchical organization management for documents.
 */
#[Route('/admin/document-categories')]
#[IsGranted('ROLE_ADMIN')]
class DocumentCategoryController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentCategoryService $documentCategoryService,
    ) {}

    /**
     * List all document categories with hierarchical tree view.
     */
    #[Route('/', name: 'admin_document_category_index', methods: ['GET'])]
    public function index(DocumentCategoryRepository $documentCategoryRepository): Response
    {
        $user = $this->getUser();
        $userIdentifier = $user?->getUserIdentifier();

        $this->logger->info('Admin document categories index accessed', [
            'user_identifier' => $userIdentifier,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => new DateTimeImmutable(),
        ]);

        try {
            $this->logger->debug('Starting to retrieve category tree with statistics', [
                'user_identifier' => $userIdentifier,
                'service_class' => DocumentCategoryService::class,
            ]);

            // Get category tree with statistics
            $categoryTree = $this->documentCategoryService->getCategoryTreeWithStats();

            $this->logger->info('Category tree successfully retrieved', [
                'user_identifier' => $userIdentifier,
                'categories_count' => count($categoryTree),
                'execution_time' => microtime(true),
            ]);

            $this->logger->debug('Rendering admin document category index template', [
                'user_identifier' => $userIdentifier,
                'template' => 'admin/document_category/index.html.twig',
                'categories_data_size' => count($categoryTree),
            ]);

            return $this->render('admin/document_category/index.html.twig', [
                'category_tree' => $categoryTree,
                'page_title' => 'Catégories de documents',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de documents', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error retrieving document categories tree', [
                'user_identifier' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'timestamp' => new DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des catégories de documents.');

            // Return minimal view in case of error
            return $this->render('admin/document_category/index.html.twig', [
                'category_tree' => [],
                'page_title' => 'Catégories de documents',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de documents', 'url' => null],
                ],
            ]);
        }
    }

    /**
     * Show document category details.
     */
    #[Route('/{id}', name: 'admin_document_category_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentCategory $documentCategory): Response
    {
        $user = $this->getUser();
        $userIdentifier = $user?->getUserIdentifier();
        $categoryId = $documentCategory->getId();

        $this->logger->info('Admin document category details view accessed', [
            'category_id' => $categoryId,
            'category_name' => $documentCategory->getName(),
            'category_slug' => $documentCategory->getSlug(),
            'user_identifier' => $userIdentifier,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTimeImmutable(),
        ]);

        try {
            $this->logger->debug('Starting to gather category statistics', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
            ]);

            // Get category statistics
            $documentsCollection = $documentCategory->getDocuments();
            $childrenCollection = $documentCategory->getChildren();
            $parentCategory = $documentCategory->getParent();

            $stats = [
                'document_count' => $documentsCollection->count(),
                'children_count' => $childrenCollection->count(),
                'level' => $documentCategory->getLevel(),
                'parent' => $parentCategory,
            ];

            $this->logger->info('Category statistics successfully calculated', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
                'document_count' => $stats['document_count'],
                'children_count' => $stats['children_count'],
                'level' => $stats['level'],
                'has_parent' => $parentCategory !== null,
                'parent_id' => $parentCategory?->getId(),
                'parent_name' => $parentCategory?->getName(),
            ]);

            $this->logger->debug('Rendering category details template', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
                'template' => 'admin/document_category/show.html.twig',
            ]);

            return $this->render('admin/document_category/show.html.twig', [
                'document_category' => $documentCategory,
                'stats' => $stats,
                'page_title' => 'Catégorie: ' . $documentCategory->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de documents', 'url' => $this->generateUrl('admin_document_category_index')],
                    ['label' => $documentCategory->getName(), 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error displaying document category details', [
                'category_id' => $categoryId,
                'category_name' => $documentCategory->getName(),
                'user_identifier' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'timestamp' => new DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage des détails de la catégorie.');

            // Redirect to index in case of error
            return $this->redirectToRoute('admin_document_category_index');
        }
    }

    /**
     * Create a new document category.
     */
    #[Route('/new', name: 'admin_document_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, DocumentCategoryRepository $categoryRepository): Response
    {
        $user = $this->getUser();
        $userIdentifier = $user?->getUserIdentifier();
        $requestMethod = $request->getMethod();

        $this->logger->info('Admin document category creation form accessed', [
            'user_identifier' => $userIdentifier,
            'request_method' => $requestMethod,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTimeImmutable(),
        ]);

        try {
            $documentCategory = new DocumentCategory();

            // Set default values
            $documentCategory->setIsActive(true);

            $this->logger->debug('New document category entity created with defaults', [
                'user_identifier' => $userIdentifier,
                'default_active_status' => true,
            ]);

            // If parent ID is provided in query, set the parent
            $parentId = $request->query->get('parent');
            if ($parentId) {
                $this->logger->debug('Parent category ID provided in query parameters', [
                    'user_identifier' => $userIdentifier,
                    'parent_id' => $parentId,
                ]);

                try {
                    $parentCategory = $categoryRepository->find((int) $parentId);
                    if ($parentCategory) {
                        $documentCategory->setParent($parentCategory);
                        $this->logger->info('Parent category set successfully', [
                            'user_identifier' => $userIdentifier,
                            'parent_id' => $parentId,
                            'parent_name' => $parentCategory->getName(),
                            'parent_level' => $parentCategory->getLevel(),
                        ]);
                    } else {
                        $this->logger->warning('Parent category not found', [
                            'user_identifier' => $userIdentifier,
                            'requested_parent_id' => $parentId,
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Error finding parent category', [
                        'user_identifier' => $userIdentifier,
                        'parent_id' => $parentId,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                    ]);
                }
            }

            $this->logger->debug('Creating form for document category', [
                'user_identifier' => $userIdentifier,
                'form_class' => DocumentCategoryType::class,
                'has_parent' => $documentCategory->getParent() !== null,
            ]);

            $form = $this->createForm(DocumentCategoryType::class, $documentCategory);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Document category form submitted', [
                    'user_identifier' => $userIdentifier,
                    'form_valid' => $form->isValid(),
                    'category_name' => $documentCategory->getName(),
                    'category_description' => $documentCategory->getDescription(),
                    'is_active' => $documentCategory->isActive(),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Form validation passed, creating document category', [
                        'user_identifier' => $userIdentifier,
                        'service_class' => DocumentCategoryService::class,
                    ]);

                    $result = $this->documentCategoryService->createDocumentCategory($documentCategory);

                    if ($result['success']) {
                        $this->logger->info('Document category created successfully', [
                            'user_identifier' => $userIdentifier,
                            'category_id' => $documentCategory->getId(),
                            'category_name' => $documentCategory->getName(),
                            'category_slug' => $documentCategory->getSlug(),
                            'category_level' => $documentCategory->getLevel(),
                            'parent_id' => $documentCategory->getParent()?->getId(),
                        ]);

                        $this->addFlash('success', 'La catégorie de document a été créée avec succès.');

                        return $this->redirectToRoute('admin_document_category_show', ['id' => $documentCategory->getId()]);
                    }
                    $this->logger->error('Failed to create document category', [
                        'user_identifier' => $userIdentifier,
                        'category_name' => $documentCategory->getName(),
                        'service_error' => $result['error'],
                    ]);

                    $this->addFlash('error', $result['error']);
                } else {
                    $this->logger->warning('Document category form validation failed', [
                        'user_identifier' => $userIdentifier,
                        'form_errors' => $this->getFormErrorsAsArray($form),
                    ]);
                }
            }

            $this->logger->debug('Rendering new document category template', [
                'user_identifier' => $userIdentifier,
                'template' => 'admin/document_category/new.html.twig',
                'request_method' => $requestMethod,
            ]);

            return $this->render('admin/document_category/new.html.twig', [
                'document_category' => $documentCategory,
                'form' => $form,
                'page_title' => 'Nouvelle catégorie de document',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de documents', 'url' => $this->generateUrl('admin_document_category_index')],
                    ['label' => 'Nouvelle', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in document category creation', [
                'user_identifier' => $userIdentifier,
                'request_method' => $requestMethod,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'timestamp' => new DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la création de la catégorie.');

            return $this->redirectToRoute('admin_document_category_index');
        }
    }

    /**
     * Edit an existing document category.
     */
    #[Route('/{id}/edit', name: 'admin_document_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentCategory $documentCategory): Response
    {
        $user = $this->getUser();
        $userIdentifier = $user?->getUserIdentifier();
        $categoryId = $documentCategory->getId();
        $requestMethod = $request->getMethod();

        $this->logger->info('Admin document category edit form accessed', [
            'category_id' => $categoryId,
            'category_name' => $documentCategory->getName(),
            'user_identifier' => $userIdentifier,
            'request_method' => $requestMethod,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTimeImmutable(),
        ]);

        try {
            // Store original values for comparison
            $originalName = $documentCategory->getName();
            $originalDescription = $documentCategory->getDescription();
            $originalIsActive = $documentCategory->isActive();
            $originalParentId = $documentCategory->getParent()?->getId();

            $this->logger->debug('Original category values stored for comparison', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
                'original_name' => $originalName,
                'original_is_active' => $originalIsActive,
                'original_parent_id' => $originalParentId,
            ]);

            $form = $this->createForm(DocumentCategoryType::class, $documentCategory);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Document category edit form submitted', [
                    'category_id' => $categoryId,
                    'user_identifier' => $userIdentifier,
                    'form_valid' => $form->isValid(),
                    'new_name' => $documentCategory->getName(),
                    'new_is_active' => $documentCategory->isActive(),
                    'new_parent_id' => $documentCategory->getParent()?->getId(),
                ]);

                if ($form->isValid()) {
                    // Log changes made
                    $changes = [];
                    if ($originalName !== $documentCategory->getName()) {
                        $changes['name'] = ['from' => $originalName, 'to' => $documentCategory->getName()];
                    }
                    if ($originalDescription !== $documentCategory->getDescription()) {
                        $changes['description'] = ['from' => $originalDescription, 'to' => $documentCategory->getDescription()];
                    }
                    if ($originalIsActive !== $documentCategory->isActive()) {
                        $changes['is_active'] = ['from' => $originalIsActive, 'to' => $documentCategory->isActive()];
                    }
                    if ($originalParentId !== $documentCategory->getParent()?->getId()) {
                        $changes['parent_id'] = ['from' => $originalParentId, 'to' => $documentCategory->getParent()?->getId()];
                    }

                    $this->logger->info('Changes detected in category edit', [
                        'category_id' => $categoryId,
                        'user_identifier' => $userIdentifier,
                        'changes' => $changes,
                        'changes_count' => count($changes),
                    ]);

                    $this->logger->debug('Updating document category via service', [
                        'category_id' => $categoryId,
                        'user_identifier' => $userIdentifier,
                        'service_class' => DocumentCategoryService::class,
                    ]);

                    $result = $this->documentCategoryService->updateDocumentCategory($documentCategory);

                    if ($result['success']) {
                        $this->logger->info('Document category updated successfully', [
                            'category_id' => $categoryId,
                            'user_identifier' => $userIdentifier,
                            'category_name' => $documentCategory->getName(),
                            'category_slug' => $documentCategory->getSlug(),
                            'changes_applied' => $changes,
                        ]);

                        $this->addFlash('success', 'La catégorie de document a été modifiée avec succès.');

                        return $this->redirectToRoute('admin_document_category_show', ['id' => $documentCategory->getId()]);
                    }
                    $this->logger->error('Failed to update document category', [
                        'category_id' => $categoryId,
                        'user_identifier' => $userIdentifier,
                        'service_error' => $result['error'],
                        'attempted_changes' => $changes,
                    ]);

                    $this->addFlash('error', $result['error']);
                } else {
                    $this->logger->warning('Document category edit form validation failed', [
                        'category_id' => $categoryId,
                        'user_identifier' => $userIdentifier,
                        'form_errors' => $this->getFormErrorsAsArray($form),
                    ]);
                }
            }

            $this->logger->debug('Rendering edit document category template', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
                'template' => 'admin/document_category/edit.html.twig',
                'request_method' => $requestMethod,
            ]);

            return $this->render('admin/document_category/edit.html.twig', [
                'document_category' => $documentCategory,
                'form' => $form,
                'page_title' => 'Modifier: ' . $documentCategory->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de documents', 'url' => $this->generateUrl('admin_document_category_index')],
                    ['label' => $documentCategory->getName(), 'url' => $this->generateUrl('admin_document_category_show', ['id' => $documentCategory->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in document category edit', [
                'category_id' => $categoryId,
                'category_name' => $documentCategory->getName(),
                'user_identifier' => $userIdentifier,
                'request_method' => $requestMethod,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'timestamp' => new DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la modification de la catégorie.');

            return $this->redirectToRoute('admin_document_category_show', ['id' => $categoryId]);
        }
    }

    /**
     * Delete a document category.
     */
    #[Route('/{id}', name: 'admin_document_category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentCategory $documentCategory): Response
    {
        $user = $this->getUser();
        $userIdentifier = $user?->getUserIdentifier();
        $categoryId = $documentCategory->getId();
        $categoryName = $documentCategory->getName();

        $this->logger->info('Admin document category deletion requested', [
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'user_identifier' => $userIdentifier,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTimeImmutable(),
        ]);

        try {
            // Log category details before deletion
            $categoryData = [
                'id' => $categoryId,
                'name' => $categoryName,
                'slug' => $documentCategory->getSlug(),
                'description' => $documentCategory->getDescription(),
                'level' => $documentCategory->getLevel(),
                'is_active' => $documentCategory->isActive(),
                'parent_id' => $documentCategory->getParent()?->getId(),
                'parent_name' => $documentCategory->getParent()?->getName(),
                'children_count' => $documentCategory->getChildren()->count(),
                'documents_count' => $documentCategory->getDocuments()->count(),
            ];

            $this->logger->debug('Category data before deletion', [
                'user_identifier' => $userIdentifier,
                'category_data' => $categoryData,
            ]);

            // Validate CSRF token
            $token = $request->getPayload()->get('_token');
            $this->logger->debug('CSRF token validation for category deletion', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
                'token_provided' => !empty($token),
            ]);

            if ($this->isCsrfTokenValid('delete' . $categoryId, $token)) {
                $this->logger->info('CSRF token validated successfully for category deletion', [
                    'category_id' => $categoryId,
                    'user_identifier' => $userIdentifier,
                ]);

                $this->logger->debug('Attempting to delete category via service', [
                    'category_id' => $categoryId,
                    'user_identifier' => $userIdentifier,
                    'service_class' => DocumentCategoryService::class,
                ]);

                $result = $this->documentCategoryService->deleteDocumentCategory($documentCategory);

                if ($result['success']) {
                    $this->logger->info('Document category deleted successfully', [
                        'category_id' => $categoryId,
                        'category_name' => $categoryName,
                        'user_identifier' => $userIdentifier,
                        'deleted_category_data' => $categoryData,
                        'timestamp' => new DateTimeImmutable(),
                    ]);

                    $this->addFlash('success', 'La catégorie de document a été supprimée avec succès.');
                } else {
                    $this->logger->error('Failed to delete document category', [
                        'category_id' => $categoryId,
                        'category_name' => $categoryName,
                        'user_identifier' => $userIdentifier,
                        'service_error' => $result['error'],
                        'category_data' => $categoryData,
                    ]);

                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for category deletion', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'user_identifier' => $userIdentifier,
                    'token_provided' => !empty($token),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }

            return $this->redirectToRoute('admin_document_category_index');
        } catch (Exception $e) {
            $this->logger->error('Critical error during document category deletion', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user_identifier' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'timestamp' => new DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la suppression de la catégorie.');

            return $this->redirectToRoute('admin_document_category_index');
        }
    }

    /**
     * Toggle document category active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_document_category_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, DocumentCategory $documentCategory): Response
    {
        $user = $this->getUser();
        $userIdentifier = $user?->getUserIdentifier();
        $categoryId = $documentCategory->getId();
        $categoryName = $documentCategory->getName();
        $currentStatus = $documentCategory->isActive();

        $this->logger->info('Admin document category status toggle requested', [
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'current_status' => $currentStatus,
            'expected_new_status' => !$currentStatus,
            'user_identifier' => $userIdentifier,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTimeImmutable(),
        ]);

        try {
            // Validate CSRF token
            $token = $request->getPayload()->get('_token');
            $this->logger->debug('CSRF token validation for status toggle', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
                'token_provided' => !empty($token),
            ]);

            if ($this->isCsrfTokenValid('toggle' . $categoryId, $token)) {
                $this->logger->info('CSRF token validated successfully for status toggle', [
                    'category_id' => $categoryId,
                    'user_identifier' => $userIdentifier,
                ]);

                $this->logger->debug('Attempting to toggle category status via service', [
                    'category_id' => $categoryId,
                    'user_identifier' => $userIdentifier,
                    'service_class' => DocumentCategoryService::class,
                    'current_status' => $currentStatus,
                ]);

                $result = $this->documentCategoryService->toggleActiveStatus($documentCategory);

                if ($result['success']) {
                    $newStatus = $documentCategory->isActive();
                    $this->logger->info('Document category status toggled successfully', [
                        'category_id' => $categoryId,
                        'category_name' => $categoryName,
                        'user_identifier' => $userIdentifier,
                        'previous_status' => $currentStatus,
                        'new_status' => $newStatus,
                        'service_message' => $result['message'],
                        'timestamp' => new DateTimeImmutable(),
                    ]);

                    $this->addFlash('success', $result['message']);
                } else {
                    $this->logger->error('Failed to toggle document category status', [
                        'category_id' => $categoryId,
                        'category_name' => $categoryName,
                        'user_identifier' => $userIdentifier,
                        'current_status' => $currentStatus,
                        'service_error' => $result['error'],
                    ]);

                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for category status toggle', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'user_identifier' => $userIdentifier,
                    'token_provided' => !empty($token),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }

            return $this->redirectToRoute('admin_document_category_index');
        } catch (Exception $e) {
            $this->logger->error('Critical error during document category status toggle', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user_identifier' => $userIdentifier,
                'current_status' => $currentStatus,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'timestamp' => new DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors du changement de statut.');

            return $this->redirectToRoute('admin_document_category_index');
        }
    }

    /**
     * Move category to new parent (AJAX endpoint).
     */
    #[Route('/{id}/move', name: 'admin_document_category_move', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function move(Request $request, DocumentCategory $documentCategory, DocumentCategoryRepository $categoryRepository): Response
    {
        $user = $this->getUser();
        $userIdentifier = $user?->getUserIdentifier();
        $categoryId = $documentCategory->getId();
        $categoryName = $documentCategory->getName();
        $currentParentId = $documentCategory->getParent()?->getId();

        $this->logger->info('Admin document category move operation requested', [
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'current_parent_id' => $currentParentId,
            'user_identifier' => $userIdentifier,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTimeImmutable(),
        ]);

        try {
            // Validate CSRF token
            $token = $request->getPayload()->get('_token');
            $this->logger->debug('CSRF token validation for category move', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
                'token_provided' => !empty($token),
            ]);

            if (!$this->isCsrfTokenValid('move' . $categoryId, $token)) {
                $this->logger->warning('Invalid CSRF token for category move operation', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'user_identifier' => $userIdentifier,
                    'token_provided' => !empty($token),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                return $this->json(['success' => false, 'error' => 'Token CSRF invalide.'], 400);
            }

            $this->logger->info('CSRF token validated successfully for category move', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
            ]);

            $newParentId = $request->getPayload()->get('parent_id');
            $newParent = null;

            $this->logger->debug('Processing new parent assignment', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
                'new_parent_id' => $newParentId,
                'new_parent_is_null' => $newParentId === null,
            ]);

            if ($newParentId) {
                try {
                    $newParent = $categoryRepository->find($newParentId);
                    if (!$newParent) {
                        $this->logger->error('New parent category not found for move operation', [
                            'category_id' => $categoryId,
                            'user_identifier' => $userIdentifier,
                            'requested_parent_id' => $newParentId,
                        ]);

                        return $this->json(['success' => false, 'error' => 'Catégorie parente introuvable.'], 404);
                    }

                    $this->logger->info('New parent category found successfully', [
                        'category_id' => $categoryId,
                        'user_identifier' => $userIdentifier,
                        'new_parent_id' => $newParentId,
                        'new_parent_name' => $newParent->getName(),
                        'new_parent_level' => $newParent->getLevel(),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error finding new parent category', [
                        'category_id' => $categoryId,
                        'user_identifier' => $userIdentifier,
                        'requested_parent_id' => $newParentId,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                    ]);

                    return $this->json(['success' => false, 'error' => 'Erreur lors de la recherche de la catégorie parente.'], 500);
                }
            }

            $this->logger->debug('Attempting to move category via service', [
                'category_id' => $categoryId,
                'user_identifier' => $userIdentifier,
                'service_class' => DocumentCategoryService::class,
                'current_parent_id' => $currentParentId,
                'new_parent_id' => $newParent?->getId(),
            ]);

            $result = $this->documentCategoryService->moveCategory($documentCategory, $newParent);

            if ($result['success']) {
                $this->logger->info('Document category moved successfully', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'user_identifier' => $userIdentifier,
                    'previous_parent_id' => $currentParentId,
                    'new_parent_id' => $newParent?->getId(),
                    'new_parent_name' => $newParent?->getName(),
                    'new_level' => $documentCategory->getLevel(),
                    'timestamp' => new DateTimeImmutable(),
                ]);

                return $this->json([
                    'success' => true,
                    'message' => 'La catégorie a été déplacée avec succès.',
                    'category' => [
                        'id' => $documentCategory->getId(),
                        'name' => $documentCategory->getName(),
                        'level' => $documentCategory->getLevel(),
                        'parent_id' => $newParent?->getId(),
                    ],
                ]);
            }
            $this->logger->error('Failed to move document category', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user_identifier' => $userIdentifier,
                'current_parent_id' => $currentParentId,
                'requested_parent_id' => $newParent?->getId(),
                'service_error' => $result['error'],
            ]);

            return $this->json(['success' => false, 'error' => $result['error']], 400);
        } catch (Exception $e) {
            $this->logger->error('Critical error during document category move operation', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user_identifier' => $userIdentifier,
                'current_parent_id' => $currentParentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'timestamp' => new DateTimeImmutable(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Une erreur critique est survenue lors du déplacement de la catégorie.',
            ], 500);
        }
    }

    /**
     * Extract form errors as an array for logging purposes.
     *
     * @param mixed $form
     */
    private function getFormErrorsAsArray($form): array
    {
        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $errors[] = $error->getMessage();
        }

        return $errors;
    }
}
