<?php

namespace App\Controller\Admin\Document;

use App\Entity\Document\Document;
use App\Form\Document\DocumentType as DocumentFormType;
use App\Repository\Document\DocumentRepository;
use App\Service\Document\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document Controller
 * 
 * Handles CRUD operations for documents in the admin interface.
 * Provides complete document management with type-specific features.
 */
#[Route('/admin/documents', name: 'admin_document_')]
#[IsGranted('ROLE_ADMIN')]
class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentService $documentService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * List all documents with filtering and pagination
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository): Response
    {
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

        // Build query with filters
        $queryBuilder = $documentRepository->createAdminQueryBuilder($filters);
        $totalItems = count($queryBuilder->getQuery()->getResult());
        
        // Apply pagination
        $documents = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalItems / $limit);

        // Get statistics for the dashboard
        $stats = [
            'total' => $documentRepository->count([]),
            'published' => $documentRepository->count(['status' => Document::STATUS_PUBLISHED]),
            'draft' => $documentRepository->count(['status' => Document::STATUS_DRAFT]),
            'review' => $documentRepository->count(['status' => Document::STATUS_REVIEW]),
        ];

        return $this->render('admin/document/index.html.twig', [
            'documents' => $documents,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    /**
     * Show a specific document
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Document $document): Response
    {
        return $this->render('admin/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    /**
     * Create a new document
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $document = new Document();
        
        // Pre-select document type if provided
        $typeId = $request->query->get('type');
        if ($typeId) {
            $documentType = $this->documentService->getDocumentTypeById((int) $typeId);
            if ($documentType) {
                $document->setDocumentType($documentType);
            }
        }

        $form = $this->createForm(DocumentFormType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $result = $this->documentService->createDocument($document);
                
                if ($result['success']) {
                    $this->addFlash('success', 'Document créé avec succès.');
                    return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
                } else {
                    foreach ($result['errors'] as $error) {
                        $this->addFlash('error', $error);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Error creating document', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', 'Une erreur est survenue lors de la création du document.');
            }
        }

        return $this->render('admin/document/new.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    /**
     * Edit an existing document
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Document $document): Response
    {
        $form = $this->createForm(DocumentFormType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Get version management data from form
                $versionType = $form->get('versionType')->getData() ?? 'minor';
                $versionMessage = $form->get('versionMessage')->getData();

                // Validate version message for new versions
                if ($versionType !== 'none' && !$versionMessage) {
                    $this->addFlash('error', 'Un message de version est obligatoire pour créer une nouvelle version.');
                    return $this->render('admin/document/edit.html.twig', [
                        'document' => $document,
                        'form' => $form,
                    ]);
                }

                // Pass version data to service
                $versionData = [
                    'type' => $versionType,
                    'message' => $versionMessage
                ];

                $result = $this->documentService->updateDocument($document, $versionData);
                
                if ($result['success']) {
                    $message = 'Document modifié avec succès.';
                    if (isset($result['new_version'])) {
                        $message .= sprintf(' Nouvelle version %s créée.', $result['new_version']->getVersion());
                    }
                    $this->addFlash('success', $message);
                    return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
                } else {
                    foreach ($result['errors'] as $error) {
                        $this->addFlash('error', $error);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Error updating document', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', 'Une erreur est survenue lors de la modification du document.');
            }
        }

        return $this->render('admin/document/edit.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    /**
     * Delete a document
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Document $document): Response
    {
        // CSRF protection
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            try {
                $result = $this->documentService->deleteDocument($document);
                
                if ($result['success']) {
                    $this->addFlash('success', 'Document supprimé avec succès.');
                } else {
                    foreach ($result['errors'] as $error) {
                        $this->addFlash('error', $error);
                    }
                    return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
                }
            } catch (\Exception $e) {
                $this->logger->error('Error deleting document', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression du document.');
                return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        }

        return $this->redirectToRoute('admin_document_index');
    }

    /**
     * Publish a document
     */
    #[Route('/{id}/publish', name: 'publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(Request $request, Document $document): Response
    {
        if ($this->isCsrfTokenValid('publish'.$document->getId(), $request->request->get('_token'))) {
            try {
                $result = $this->documentService->publishDocument($document);
                
                if ($result['success']) {
                    $this->addFlash('success', 'Document publié avec succès.');
                } else {
                    foreach ($result['errors'] as $error) {
                        $this->addFlash('error', $error);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Error publishing document', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', 'Une erreur est survenue lors de la publication.');
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
    }

    /**
     * Archive a document
     */
    #[Route('/{id}/archive', name: 'archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archive(Request $request, Document $document): Response
    {
        if ($this->isCsrfTokenValid('archive'.$document->getId(), $request->request->get('_token'))) {
            try {
                $result = $this->documentService->archiveDocument($document);
                
                if ($result['success']) {
                    $this->addFlash('success', 'Document archivé avec succès.');
                } else {
                    foreach ($result['errors'] as $error) {
                        $this->addFlash('error', $error);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Error archiving document', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', 'Une erreur est survenue lors de l\'archivage.');
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
    }

    /**
     * Duplicate a document
     */
    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, Document $document): Response
    {
        if ($this->isCsrfTokenValid('duplicate'.$document->getId(), $request->request->get('_token'))) {
            try {
                $result = $this->documentService->duplicateDocument($document);
                
                if ($result['success']) {
                    $this->addFlash('success', 'Document dupliqué avec succès.');
                    return $this->redirectToRoute('admin_document_edit', ['id' => $result['document']->getId()]);
                } else {
                    foreach ($result['errors'] as $error) {
                        $this->addFlash('error', $error);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Error duplicating document', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', 'Une erreur est survenue lors de la duplication.');
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
    }
}
