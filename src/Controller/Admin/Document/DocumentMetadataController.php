<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentMetadata;
use App\Form\Document\DocumentMetadataType;
use App\Repository\Document\DocumentMetadataRepository;
use App\Service\Document\DocumentMetadataService;
use Exception;
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
#[Route('/admin/document-metadata')]
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
    #[Route('/', name: 'admin_document_metadata_index', methods: ['GET'])]
    public function index(Request $request, DocumentMetadataRepository $documentMetadataRepository): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->info('Admin document metadata list access initiated', [
            'user' => $userId,
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'query_params' => $request->query->all(),
        ]);

        try {
            // Handle filtering
            $filters = [
                'document' => $request->query->get('document'),
                'key' => $request->query->get('key'),
                'value_type' => $request->query->get('value_type'),
            ];

            $this->logger->debug('Applying filters to document metadata list', [
                'filters' => $filters,
                'user' => $userId,
            ]);

            // Get metadata with statistics
            $this->logger->debug('Fetching metadata with statistics', ['user' => $userId]);
            $metadataWithStats = $this->documentMetadataService->getMetadataWithStats($filters);
            $this->logger->info('Metadata with statistics retrieved successfully', [
                'count' => count($metadataWithStats),
                'user' => $userId,
            ]);

            // Get aggregate statistics
            $this->logger->debug('Fetching aggregate statistics', ['user' => $userId]);
            $statistics = $this->documentMetadataService->getAggregateStatistics();
            $this->logger->info('Aggregate statistics retrieved successfully', [
                'statistics_keys' => array_keys($statistics),
                'user' => $userId,
            ]);

            $this->logger->info('Admin document metadata list rendered successfully', [
                'metadata_count' => count($metadataWithStats),
                'applied_filters' => array_filter($filters),
                'user' => $userId,
            ]);

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
        } catch (Exception $e) {
            $this->logger->error('Error loading document metadata list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
                'filters' => $filters ?? [],
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des métadonnées.');

            return $this->render('admin/document_metadata/index.html.twig', [
                'metadata_with_stats' => [],
                'statistics' => [],
                'filters' => $filters ?? [],
                'page_title' => 'Métadonnées des documents',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => 'Métadonnées', 'url' => null],
                ],
            ]);
        }
    }

    /**
     * Show document metadata details.
     */
    #[Route('/{id}', name: 'admin_document_metadata_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentMetadata $documentMetadata): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $metadataId = $documentMetadata->getId();

        $this->logger->info('Admin document metadata details view initiated', [
            'metadata_id' => $metadataId,
            'metadata_key' => $documentMetadata->getMetaKey(),
            'document_id' => $documentMetadata->getDocument()?->getId(),
            'user' => $userId,
        ]);

        try {
            $this->logger->debug('Rendering document metadata details view', [
                'metadata_id' => $metadataId,
                'metadata_key' => $documentMetadata->getMetaKey(),
                'metadata_value' => $documentMetadata->getMetaValue(),
                'user' => $userId,
            ]);

            $this->logger->info('Admin document metadata details rendered successfully', [
                'metadata_id' => $metadataId,
                'metadata_key' => $documentMetadata->getMetaKey(),
                'user' => $userId,
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
        } catch (Exception $e) {
            $this->logger->error('Error rendering document metadata details', [
                'metadata_id' => $metadataId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage des détails de la métadonnée.');

            return $this->redirectToRoute('admin_document_metadata_index');
        }
    }

    /**
     * Create a new document metadata.
     */
    #[Route('/new', name: 'admin_document_metadata_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $documentId = $request->query->get('document');

        $this->logger->info('Admin document metadata creation initiated', [
            'user' => $userId,
            'document_id' => $documentId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $documentMetadata = new DocumentMetadata();

            // Pre-select document if provided
            if ($documentId) {
                $this->logger->debug('Pre-selecting document for new metadata', [
                    'document_id' => $documentId,
                    'user' => $userId,
                ]);

                $document = $this->documentMetadataService->getDocumentById((int) $documentId);
                if ($document) {
                    $documentMetadata->setDocument($document);
                    $this->logger->info('Document pre-selected for metadata creation', [
                        'document_id' => $documentId,
                        'document_title' => $document->getTitle(),
                        'user' => $userId,
                    ]);
                } else {
                    $this->logger->warning('Document not found for pre-selection', [
                        'document_id' => $documentId,
                        'user' => $userId,
                    ]);
                }
            }

            $form = $this->createForm(DocumentMetadataType::class, $documentMetadata);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('Document metadata form submitted and valid', [
                    'metadata_key' => $documentMetadata->getMetaKey(),
                    'metadata_value' => $documentMetadata->getMetaValue(),
                    'document_id' => $documentMetadata->getDocument()?->getId(),
                    'user' => $userId,
                ]);

                $result = $this->documentMetadataService->createDocumentMetadata($documentMetadata);

                if ($result['success']) {
                    $this->logger->info('Document metadata created successfully', [
                        'metadata_id' => $documentMetadata->getId(),
                        'metadata_key' => $documentMetadata->getMetaKey(),
                        'document_id' => $documentMetadata->getDocument()?->getId(),
                        'user' => $userId,
                    ]);

                    $this->addFlash('success', 'La métadonnée a été créée avec succès.');

                    return $this->redirectToRoute('admin_document_metadata_show', ['id' => $documentMetadata->getId()]);
                }

                $this->logger->error('Failed to create document metadata', [
                    'error' => $result['error'],
                    'metadata_key' => $documentMetadata->getMetaKey(),
                    'user' => $userId,
                ]);
                $this->addFlash('error', $result['error']);
            } elseif ($form->isSubmitted()) {
                $this->logger->warning('Document metadata form submitted but invalid', [
                    'form_errors' => (string) $form->getErrors(true),
                    'user' => $userId,
                ]);
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
        } catch (Exception $e) {
            $this->logger->error('Error during document metadata creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
                'document_id' => $documentId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création de la métadonnée.');

            return $this->redirectToRoute('admin_document_metadata_index');
        }
    }

    /**
     * Edit an existing document metadata.
     */
    #[Route('/{id}/edit', name: 'admin_document_metadata_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentMetadata $documentMetadata): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $metadataId = $documentMetadata->getId();

        $this->logger->info('Admin document metadata edit initiated', [
            'metadata_id' => $metadataId,
            'metadata_key' => $documentMetadata->getMetaKey(),
            'user' => $userId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $originalKey = $documentMetadata->getMetaKey();
            $originalValue = $documentMetadata->getMetaValue();

            $form = $this->createForm(DocumentMetadataType::class, $documentMetadata);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('Document metadata edit form submitted and valid', [
                    'metadata_id' => $metadataId,
                    'original_key' => $originalKey,
                    'new_key' => $documentMetadata->getMetaKey(),
                    'original_value' => $originalValue,
                    'new_value' => $documentMetadata->getMetaValue(),
                    'user' => $userId,
                ]);

                $result = $this->documentMetadataService->updateDocumentMetadata($documentMetadata);

                if ($result['success']) {
                    $this->logger->info('Document metadata updated successfully', [
                        'metadata_id' => $metadataId,
                        'metadata_key' => $documentMetadata->getMetaKey(),
                        'changes' => [
                            'key_changed' => $originalKey !== $documentMetadata->getMetaKey(),
                            'value_changed' => $originalValue !== $documentMetadata->getMetaValue(),
                        ],
                        'user' => $userId,
                    ]);

                    $this->addFlash('success', 'La métadonnée a été modifiée avec succès.');

                    return $this->redirectToRoute('admin_document_metadata_show', ['id' => $documentMetadata->getId()]);
                }

                $this->logger->error('Failed to update document metadata', [
                    'metadata_id' => $metadataId,
                    'error' => $result['error'],
                    'user' => $userId,
                ]);
                $this->addFlash('error', $result['error']);
            } elseif ($form->isSubmitted()) {
                $this->logger->warning('Document metadata edit form submitted but invalid', [
                    'metadata_id' => $metadataId,
                    'form_errors' => (string) $form->getErrors(true),
                    'user' => $userId,
                ]);
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
        } catch (Exception $e) {
            $this->logger->error('Error during document metadata edit', [
                'metadata_id' => $metadataId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification de la métadonnée.');

            return $this->redirectToRoute('admin_document_metadata_show', ['id' => $metadataId]);
        }
    }

    /**
     * Delete a document metadata.
     */
    #[Route('/{id}', name: 'admin_document_metadata_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentMetadata $documentMetadata): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $metadataId = $documentMetadata->getId();
        $metadataKey = $documentMetadata->getMetaKey();

        $this->logger->info('Admin document metadata deletion initiated', [
            'metadata_id' => $metadataId,
            'metadata_key' => $metadataKey,
            'document_id' => $documentMetadata->getDocument()?->getId(),
            'user' => $userId,
            'ip' => $request->getClientIp(),
        ]);

        try {
            $token = $request->getPayload()->get('_token');
            $this->logger->debug('Validating CSRF token for metadata deletion', [
                'metadata_id' => $metadataId,
                'user' => $userId,
            ]);

            if ($this->isCsrfTokenValid('delete' . $metadataId, $token)) {
                $this->logger->info('CSRF token validated, proceeding with metadata deletion', [
                    'metadata_id' => $metadataId,
                    'metadata_key' => $metadataKey,
                    'user' => $userId,
                ]);

                $result = $this->documentMetadataService->deleteDocumentMetadata($documentMetadata);

                if ($result['success']) {
                    $this->logger->info('Document metadata deleted successfully', [
                        'metadata_id' => $metadataId,
                        'metadata_key' => $metadataKey,
                        'user' => $userId,
                    ]);

                    $this->addFlash('success', 'La métadonnée a été supprimée avec succès.');
                } else {
                    $this->logger->error('Failed to delete document metadata', [
                        'metadata_id' => $metadataId,
                        'error' => $result['error'],
                        'user' => $userId,
                    ]);

                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for metadata deletion', [
                    'metadata_id' => $metadataId,
                    'user' => $userId,
                    'ip' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error during document metadata deletion', [
                'metadata_id' => $metadataId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la métadonnée.');
        }

        return $this->redirectToRoute('admin_document_metadata_index');
    }

    /**
     * Bulk delete selected metadata.
     */
    #[Route('/bulk-delete', name: 'admin_document_metadata_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin document metadata bulk deletion initiated', [
            'user' => $userId,
            'ip' => $request->getClientIp(),
        ]);

        try {
            $ids = $request->request->all('selected_metadata');

            $this->logger->debug('Bulk deletion metadata IDs received', [
                'ids' => $ids,
                'count' => count($ids),
                'user' => $userId,
            ]);

            if (empty($ids)) {
                $this->logger->warning('Bulk deletion attempted with no metadata selected', [
                    'user' => $userId,
                ]);

                $this->addFlash('warning', 'Aucune métadonnée sélectionnée.');

                return $this->redirectToRoute('admin_document_metadata_index');
            }

            $token = $request->request->get('_token');
            $this->logger->debug('Validating CSRF token for bulk deletion', [
                'metadata_count' => count($ids),
                'user' => $userId,
            ]);

            if ($this->isCsrfTokenValid('bulk_delete', $token)) {
                $this->logger->info('CSRF token validated, proceeding with bulk deletion', [
                    'metadata_ids' => $ids,
                    'count' => count($ids),
                    'user' => $userId,
                ]);

                $result = $this->documentMetadataService->bulkDeleteMetadata($ids);

                if ($result['success']) {
                    $this->logger->info('Bulk metadata deletion completed successfully', [
                        'deleted_count' => $result['deleted_count'],
                        'total_requested' => count($ids),
                        'user' => $userId,
                    ]);

                    $this->addFlash('success', sprintf(
                        '%d métadonnée(s) supprimée(s) avec succès.',
                        $result['deleted_count'],
                    ));
                } else {
                    $this->logger->error('Bulk metadata deletion failed', [
                        'error' => $result['error'],
                        'requested_ids' => $ids,
                        'user' => $userId,
                    ]);

                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for bulk deletion', [
                    'user' => $userId,
                    'ip' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error during bulk metadata deletion', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
                'ids' => $ids ?? [],
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression en masse.');
        }

        return $this->redirectToRoute('admin_document_metadata_index');
    }

    /**
     * Export metadata to CSV.
     */
    #[Route('/export', name: 'admin_document_metadata_export', methods: ['GET', 'POST'])]
    public function export(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin document metadata export initiated', [
            'user' => $userId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $filters = [
                'document' => $request->query->get('document'),
                'key' => $request->query->get('key'),
                'value_type' => $request->query->get('value_type'),
            ];

            $this->logger->debug('Export filters applied', [
                'filters' => array_filter($filters),
                'user' => $userId,
            ]);

            $result = $this->documentMetadataService->exportMetadataToCSV($filters);

            if (!$result['success']) {
                $this->logger->error('Metadata export failed', [
                    'error' => $result['error'],
                    'filters' => $filters,
                    'user' => $userId,
                ]);

                $this->addFlash('error', $result['error']);

                return $this->redirectToRoute('admin_document_metadata_index');
            }

            $this->logger->info('Metadata export completed successfully', [
                'filters' => array_filter($filters),
                'user' => $userId,
            ]);

            return $result['response'];
        } catch (Exception $e) {
            $this->logger->error('Error during metadata export', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
                'filters' => $filters ?? [],
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'export.');

            return $this->redirectToRoute('admin_document_metadata_index');
        }
    }

    /**
     * Get metadata statistics by key (AJAX).
     */
    #[Route('/statistics/{key}', name: 'admin_document_metadata_statistics', methods: ['GET'], requirements: ['key' => '.+'])]
    public function getStatistics(string $key, Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin metadata statistics request initiated', [
            'key' => $key,
            'user' => $userId,
            'is_ajax' => $request->isXmlHttpRequest(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            if (!$request->isXmlHttpRequest()) {
                $this->logger->warning('Non-AJAX request to statistics endpoint', [
                    'key' => $key,
                    'user' => $userId,
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);

                throw $this->createNotFoundException();
            }

            $this->logger->debug('Fetching metadata statistics for key', [
                'key' => $key,
                'user' => $userId,
            ]);

            $statistics = $this->documentMetadataService->getMetadataStatisticsByKey($key);

            $this->logger->info('Metadata statistics retrieved successfully', [
                'key' => $key,
                'statistics_count' => count($statistics),
                'user' => $userId,
            ]);

            return $this->json($statistics);
        } catch (Exception $e) {
            $this->logger->error('Error retrieving metadata statistics', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            return $this->json(['error' => 'Erreur lors de la récupération des statistiques'], 500);
        }
    }

    /**
     * Get available metadata keys (AJAX).
     *
     * Provides autocomplete data for metadata key fields in forms.
     * Returns available keys with usage statistics, sorted by popularity.
     * Used by:
     * - New metadata form (autocomplete datalist)
     * - Edit metadata form (autocomplete datalist)
     * - Index page filter (autocomplete datalist)
     *
     * @return JsonResponse Array of objects with 'key' and 'usage_count' fields
     */
    #[Route('/keys', name: 'admin_document_metadata_keys', methods: ['GET'])]
    public function getAvailableKeys(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $search = $request->query->get('search', '');

        $this->logger->info('Admin metadata keys request initiated', [
            'search' => $search,
            'user' => $userId,
            'is_ajax' => $request->isXmlHttpRequest(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            if (!$request->isXmlHttpRequest()) {
                $this->logger->warning('Non-AJAX request to metadata keys endpoint', [
                    'search' => $search,
                    'user' => $userId,
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);

                throw $this->createNotFoundException();
            }

            $this->logger->debug('Fetching available metadata keys', [
                'search' => $search,
                'user' => $userId,
            ]);

            $keys = $this->documentMetadataService->getAvailableMetadataKeys($search);

            $this->logger->info('Available metadata keys retrieved successfully', [
                'search' => $search,
                'keys_count' => count($keys),
                'user' => $userId,
            ]);

            return $this->json($keys);
        } catch (Exception $e) {
            $this->logger->error('Error retrieving available metadata keys', [
                'search' => $search,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            return $this->json(['error' => 'Erreur lors de la récupération des clés'], 500);
        }
    }
}
