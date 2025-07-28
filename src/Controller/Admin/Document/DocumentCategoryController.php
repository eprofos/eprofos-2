<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentCategory;
use App\Form\Document\DocumentCategoryType;
use App\Repository\Document\DocumentCategoryRepository;
use App\Service\Document\DocumentCategoryService;
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
#[Route('/admin/document-categories', name: 'admin_document_category_')]
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
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(DocumentCategoryRepository $documentCategoryRepository): Response
    {
        $this->logger->info('Admin document categories list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        // Get category tree with statistics
        $categoryTree = $this->documentCategoryService->getCategoryTreeWithStats();

        return $this->render('admin/document_category/index.html.twig', [
            'category_tree' => $categoryTree,
            'page_title' => 'Catégories de documents',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Catégories de documents', 'url' => null],
            ],
        ]);
    }

    /**
     * Show document category details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentCategory $documentCategory): Response
    {
        $this->logger->info('Admin document category details viewed', [
            'category_id' => $documentCategory->getId(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        // Get category statistics
        $stats = [
            'document_count' => $documentCategory->getDocuments()->count(),
            'children_count' => $documentCategory->getChildren()->count(),
            'level' => $documentCategory->getLevel(),
            'parent' => $documentCategory->getParent(),
        ];

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
    }

    /**
     * Create a new document category.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, DocumentCategoryRepository $categoryRepository): Response
    {
        $documentCategory = new DocumentCategory();

        // Set default values
        $documentCategory->setIsActive(true);

        // If parent ID is provided in query, set the parent
        $parentId = $request->query->get('parent');
        if ($parentId) {
            $parentCategory = $categoryRepository->find((int) $parentId);
            if ($parentCategory) {
                $documentCategory->setParent($parentCategory);
            }
        }

        $form = $this->createForm(DocumentCategoryType::class, $documentCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->documentCategoryService->createDocumentCategory($documentCategory);

            if ($result['success']) {
                $this->addFlash('success', 'La catégorie de document a été créée avec succès.');

                return $this->redirectToRoute('admin_document_category_show', ['id' => $documentCategory->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

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
    }

    /**
     * Edit an existing document category.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentCategory $documentCategory): Response
    {
        $form = $this->createForm(DocumentCategoryType::class, $documentCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->documentCategoryService->updateDocumentCategory($documentCategory);

            if ($result['success']) {
                $this->addFlash('success', 'La catégorie de document a été modifiée avec succès.');

                return $this->redirectToRoute('admin_document_category_show', ['id' => $documentCategory->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

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
    }

    /**
     * Delete a document category.
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentCategory $documentCategory): Response
    {
        if ($this->isCsrfTokenValid('delete' . $documentCategory->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->documentCategoryService->deleteDocumentCategory($documentCategory);

            if ($result['success']) {
                $this->addFlash('success', 'La catégorie de document a été supprimée avec succès.');
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_category_index');
    }

    /**
     * Toggle document category active status.
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, DocumentCategory $documentCategory): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $documentCategory->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->documentCategoryService->toggleActiveStatus($documentCategory);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_category_index');
    }

    /**
     * Move category to new parent (AJAX endpoint).
     */
    #[Route('/{id}/move', name: 'move', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function move(Request $request, DocumentCategory $documentCategory, DocumentCategoryRepository $categoryRepository): Response
    {
        if (!$this->isCsrfTokenValid('move' . $documentCategory->getId(), $request->getPayload()->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide.'], 400);
        }

        $newParentId = $request->getPayload()->get('parent_id');
        $newParent = null;

        if ($newParentId) {
            $newParent = $categoryRepository->find($newParentId);
            if (!$newParent) {
                return $this->json(['success' => false, 'error' => 'Catégorie parente introuvable.'], 404);
            }
        }

        $result = $this->documentCategoryService->moveCategory($documentCategory, $newParent);

        if ($result['success']) {
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

        return $this->json(['success' => false, 'error' => $result['error']], 400);
    }
}
