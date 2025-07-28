<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentMetadata;
use App\Form\Document\DocumentMetadataType;
use App\Repository\Document\DocumentMetadataRepository;
use App\Service\Document\DocumentMetadataService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document Metadata Controller.
 *
 * Handles CRUD operations for document metadata in the admin interface.
 * Provides management for structured metadata fields and values.
 */
#[Route('/admin/document-metadata', name: 'admin_document_metadata_')]
#[IsGranted('ROLE_ADMIN')]
class DocumentMetadataController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentMetadataService $documentMetadataService,
    ) {}

    /**
     * List all document metadata with statistics.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, DocumentMetadataRepository $documentMetadataRepository): Response
    {
        $this->logger->info('Admin document metadata list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        // Handle filtering
        $filters = [
            'document' => $request->query->get('document'),
            'key' => $request->query->get('key'),
            'value_type' => $request->query->get('value_type'),
        ];

        // Get metadata with statistics
        $metadataWithStats = $this->documentMetadataService->getMetadataWithStats($filters);

        // Get aggregate statistics
        $statistics = $this->documentMetadataService->getAggregateStatistics();

        return $this->render('admin/document_metadata/index.html.twig', [
            'metadata_with_stats' => $metadataWithStats,
            'statistics' => $statistics,
            'filters' => $filters,
            'page_title' => 'Métadonnées des documents',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Métadonnées', 'url' => null],
            ],
        ]);
    }

    /**
     * Show document metadata details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentMetadata $documentMetadata): Response
    {
        $this->logger->info('Admin document metadata details viewed', [
            'metadata_id' => $documentMetadata->getId(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $this->render('admin/document_metadata/show.html.twig', [
            'document_metadata' => $documentMetadata,
            'page_title' => 'Métadonnée: ' . $documentMetadata->getMetaKey(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Métadonnées', 'url' => $this->generateUrl('admin_document_metadata_index')],
                ['label' => $documentMetadata->getMetaKey(), 'url' => null],
            ],
        ]);
    }

    /**
     * Create a new document metadata.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $documentMetadata = new DocumentMetadata();

        // Pre-select document if provided
        $documentId = $request->query->get('document');
        if ($documentId) {
            $document = $this->documentMetadataService->getDocumentById((int) $documentId);
            if ($document) {
                $documentMetadata->setDocument($document);
            }
        }

        $form = $this->createForm(DocumentMetadataType::class, $documentMetadata);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->documentMetadataService->createDocumentMetadata($documentMetadata);

            if ($result['success']) {
                $this->addFlash('success', 'La métadonnée a été créée avec succès.');

                return $this->redirectToRoute('admin_document_metadata_show', ['id' => $documentMetadata->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

        return $this->render('admin/document_metadata/new.html.twig', [
            'document_metadata' => $documentMetadata,
            'form' => $form,
            'page_title' => 'Nouvelle métadonnée',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Métadonnées', 'url' => $this->generateUrl('admin_document_metadata_index')],
                ['label' => 'Nouvelle', 'url' => null],
            ],
        ]);
    }

    /**
     * Edit an existing document metadata.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentMetadata $documentMetadata): Response
    {
        $form = $this->createForm(DocumentMetadataType::class, $documentMetadata);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->documentMetadataService->updateDocumentMetadata($documentMetadata);

            if ($result['success']) {
                $this->addFlash('success', 'La métadonnée a été modifiée avec succès.');

                return $this->redirectToRoute('admin_document_metadata_show', ['id' => $documentMetadata->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

        return $this->render('admin/document_metadata/edit.html.twig', [
            'document_metadata' => $documentMetadata,
            'form' => $form,
            'page_title' => 'Modifier: ' . $documentMetadata->getMetaKey(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Métadonnées', 'url' => $this->generateUrl('admin_document_metadata_index')],
                ['label' => $documentMetadata->getMetaKey(), 'url' => $this->generateUrl('admin_document_metadata_show', ['id' => $documentMetadata->getId()])],
                ['label' => 'Modifier', 'url' => null],
            ],
        ]);
    }

    /**
     * Delete a document metadata.
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentMetadata $documentMetadata): Response
    {
        if ($this->isCsrfTokenValid('delete' . $documentMetadata->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->documentMetadataService->deleteDocumentMetadata($documentMetadata);

            if ($result['success']) {
                $this->addFlash('success', 'La métadonnée a été supprimée avec succès.');
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_metadata_index');
    }

    /**
     * Bulk delete selected metadata.
     */
    #[Route('/bulk-delete', name: 'bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        $ids = $request->request->all('selected_metadata');

        if (empty($ids)) {
            $this->addFlash('warning', 'Aucune métadonnée sélectionnée.');

            return $this->redirectToRoute('admin_document_metadata_index');
        }

        if ($this->isCsrfTokenValid('bulk_delete', $request->request->get('_token'))) {
            $result = $this->documentMetadataService->bulkDeleteMetadata($ids);

            if ($result['success']) {
                $this->addFlash('success', sprintf(
                    '%d métadonnée(s) supprimée(s) avec succès.',
                    $result['deleted_count'],
                ));
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_metadata_index');
    }

    /**
     * Export metadata to CSV.
     */
    #[Route('/export', name: 'export', methods: ['GET', 'POST'])]
    public function export(Request $request): Response
    {
        $filters = [
            'document' => $request->query->get('document'),
            'key' => $request->query->get('key'),
            'value_type' => $request->query->get('value_type'),
        ];

        $result = $this->documentMetadataService->exportMetadataToCSV($filters);

        if (!$result['success']) {
            $this->addFlash('error', $result['error']);

            return $this->redirectToRoute('admin_document_metadata_index');
        }

        return $result['response'];
    }

    /**
     * Get metadata statistics by key (AJAX).
     */
    #[Route('/statistics/{key}', name: 'statistics', methods: ['GET'], requirements: ['key' => '.+'])]
    public function getStatistics(string $key, Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            throw $this->createNotFoundException();
        }

        $statistics = $this->documentMetadataService->getMetadataStatisticsByKey($key);

        return $this->json($statistics);
    }

    /**
     * Get available metadata keys (AJAX).
     */
    #[Route('/keys', name: 'keys', methods: ['GET'])]
    public function getAvailableKeys(Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            throw $this->createNotFoundException();
        }

        $search = $request->query->get('search', '');
        $keys = $this->documentMetadataService->getAvailableMetadataKeys($search);

        return $this->json($keys);
    }
}
