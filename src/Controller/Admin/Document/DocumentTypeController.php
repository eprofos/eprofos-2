<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentType;
use App\Form\Document\DocumentTypeType;
use App\Repository\Document\DocumentTypeRepository;
use App\Service\Document\DocumentTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document Type Controller.
 *
 * Handles CRUD operations for document types in the admin interface.
 * Provides management for the flexible document type system.
 */
#[Route('/admin/document-types')]
#[IsGranted('ROLE_ADMIN')]
class DocumentTypeController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentTypeService $documentTypeService,
    ) {}

    /**
     * List all document types with statistics.
     */
    #[Route('/', name: 'admin_document_type_index', methods: ['GET'])]
    public function index(DocumentTypeRepository $documentTypeRepository): Response
    {
        $this->logger->info('Admin document types list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        // Get document types with statistics
        $typesWithStats = $this->documentTypeService->getDocumentTypesWithStats();

        return $this->render('admin/document_type/index.html.twig', [
            'types_with_stats' => $typesWithStats,
            'page_title' => 'Types de documents',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Types de documents', 'url' => null],
            ],
        ]);
    }

    /**
     * Show document type details.
     */
    #[Route('/{id}', name: 'admin_document_type_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentType $documentType): Response
    {
        $this->logger->info('Admin document type details viewed', [
            'type_id' => $documentType->getId(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        // Get type statistics
        $stats = [
            'document_count' => $documentType->getDocuments()->count(),
            'template_count' => $documentType->getTemplates()->count(),
            'published_count' => $documentType->getDocuments()->filter(static fn ($doc) => $doc->getStatus() === 'published')->count(),
            'draft_count' => $documentType->getDocuments()->filter(static fn ($doc) => $doc->getStatus() === 'draft')->count(),
        ];

        return $this->render('admin/document_type/show.html.twig', [
            'document_type' => $documentType,
            'stats' => $stats,
            'page_title' => 'Type: ' . $documentType->getName(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Types de documents', 'url' => $this->generateUrl('admin_document_type_index')],
                ['label' => $documentType->getName(), 'url' => null],
            ],
        ]);
    }

    /**
     * Create a new document type.
     */
    #[Route('/new', name: 'admin_document_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $documentType = new DocumentType();

        // Set default values
        $documentType->setIsActive(true);
        $documentType->setAllowMultiplePublished(true);
        $documentType->setSortOrder($this->documentTypeService->getNextSortOrder());

        $form = $this->createForm(DocumentTypeType::class, $documentType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->documentTypeService->createDocumentType($documentType);

            if ($result['success']) {
                $this->addFlash('success', 'Le type de document a été créé avec succès.');

                return $this->redirectToRoute('admin_document_type_show', ['id' => $documentType->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

        return $this->render('admin/document_type/new.html.twig', [
            'document_type' => $documentType,
            'form' => $form,
            'page_title' => 'Nouveau type de document',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Types de documents', 'url' => $this->generateUrl('admin_document_type_index')],
                ['label' => 'Nouveau', 'url' => null],
            ],
        ]);
    }

    /**
     * Edit an existing document type.
     */
    #[Route('/{id}/edit', name: 'admin_document_type_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentType $documentType): Response
    {
        $form = $this->createForm(DocumentTypeType::class, $documentType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->documentTypeService->updateDocumentType($documentType);

            if ($result['success']) {
                $this->addFlash('success', 'Le type de document a été modifié avec succès.');

                return $this->redirectToRoute('admin_document_type_show', ['id' => $documentType->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

        return $this->render('admin/document_type/edit.html.twig', [
            'document_type' => $documentType,
            'form' => $form,
            'page_title' => 'Modifier: ' . $documentType->getName(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Types de documents', 'url' => $this->generateUrl('admin_document_type_index')],
                ['label' => $documentType->getName(), 'url' => $this->generateUrl('admin_document_type_show', ['id' => $documentType->getId()])],
                ['label' => 'Modifier', 'url' => null],
            ],
        ]);
    }

    /**
     * Delete a document type.
     */
    #[Route('/{id}', name: 'admin_document_type_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentType $documentType): Response
    {
        if ($this->isCsrfTokenValid('delete' . $documentType->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->documentTypeService->deleteDocumentType($documentType);

            if ($result['success']) {
                $this->addFlash('success', 'Le type de document a été supprimé avec succès.');
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_type_index');
    }

    /**
     * Toggle document type active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_document_type_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, DocumentType $documentType): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $documentType->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->documentTypeService->toggleActiveStatus($documentType);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_type_index');
    }

    /**
     * Duplicate a document type.
     */
    #[Route('/{id}/duplicate', name: 'admin_document_type_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, DocumentType $documentType): Response
    {
        if ($this->isCsrfTokenValid('duplicate' . $documentType->getId(), $request->getPayload()->get('_token'))) {
            // Create a copy of the document type
            $newDocumentType = new DocumentType();
            $newDocumentType->setCode($documentType->getCode() . '_copy')
                ->setName($documentType->getName() . ' (Copie)')
                ->setDescription($documentType->getDescription())
                ->setIcon($documentType->getIcon())
                ->setColor($documentType->getColor())
                ->setRequiresApproval($documentType->isRequiresApproval())
                ->setAllowMultiplePublished($documentType->isAllowMultiplePublished())
                ->setHasExpiration($documentType->isHasExpiration())
                ->setGeneratesPdf($documentType->isGeneratesPdf())
                ->setAllowedStatuses($documentType->getAllowedStatuses())
                ->setRequiredMetadata($documentType->getRequiredMetadata())
                ->setConfiguration($documentType->getConfiguration())
                ->setIsActive(false) // Start as inactive
                ->setSortOrder($this->documentTypeService->getNextSortOrder())
            ;

            $result = $this->documentTypeService->createDocumentType($newDocumentType);

            if ($result['success']) {
                $this->addFlash('success', 'Le type de document a été dupliqué avec succès.');

                return $this->redirectToRoute('admin_document_type_edit', ['id' => $newDocumentType->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

        return $this->redirectToRoute('admin_document_type_index');
    }
}
