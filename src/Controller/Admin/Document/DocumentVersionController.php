<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentVersion;
use App\Entity\User\Admin;
use App\Repository\Document\DocumentVersionRepository;
use App\Service\Document\DocumentService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document Version Controller.
 *
 * Handles version management operations for documents in the admin interface.
 * Provides version history, comparison, rollback, and detailed version operations.
 */
#[Route('/admin/document-versions')]
#[IsGranted('ROLE_ADMIN')]
class DocumentVersionController extends AbstractController
{
    public function __construct(
        private DocumentService $documentService,
        private LoggerInterface $logger,
    ) {}

    /**
     * List all versions for a specific document.
     */
    #[Route('/document/{id}', name: 'admin_document_version_index', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function index(Document $document, DocumentVersionRepository $versionRepository): Response
    {
        try {
            $this->logger->info('Starting document versions listing', [
                'document_id' => $document->getId(),
                'document_title' => $document->getTitle(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'request_time' => new DateTimeImmutable(),
            ]);

            $versions = $versionRepository->findByDocument($document);

            $this->logger->info('Document versions retrieved successfully', [
                'document_id' => $document->getId(),
                'versions_count' => count($versions),
                'versions_ids' => array_map(static fn ($v) => $v->getId(), $versions),
                'current_version' => $versions ? array_filter($versions, static fn ($v) => $v->isCurrent())[0]?->getVersion() ?? 'none' : 'none',
            ]);

            return $this->render('admin/document_version/index.html.twig', [
                'document' => $document,
                'versions' => $versions,
                'page_title' => 'Versions de: ' . $document->getTitle(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Documents', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => $document->getTitle(), 'url' => $this->generateUrl('admin_document_show', ['id' => $document->getId()])],
                    ['label' => 'Versions', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error occurred while listing document versions', [
                'document_id' => $document->getId(),
                'document_title' => $document->getTitle(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la récupération des versions du document.');

            return $this->redirectToRoute('admin_document_index');
        }
    }

    /**
     * Show details of a specific version.
     */
    #[Route('/{id}', name: 'admin_document_version_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentVersion $version): Response
    {
        try {
            $this->logger->info('Starting document version details display', [
                'version_id' => $version->getId(),
                'version_number' => $version->getVersion(),
                'document_id' => $version->getDocument()->getId(),
                'document_title' => $version->getDocument()->getTitle(),
                'is_current' => $version->isCurrent(),
                'created_at' => $version->getCreatedAt()->format('Y-m-d H:i:s'),
                'created_by' => $version->getCreatedByName(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'request_time' => new DateTimeImmutable(),
            ]);

            $this->logger->debug('Document version metadata', [
                'version_id' => $version->getId(),
                'content_length' => $version->getContentLength(),
                'file_size' => $version->getFileSize(),
                'checksum' => $version->getChecksum(),
                'change_log' => $version->getChangeLog(),
                'title' => $version->getTitle(),
            ]);

            return $this->render('admin/document_version/show.html.twig', [
                'version' => $version,
                'document' => $version->getDocument(),
                'page_title' => 'Version ' . $version->getVersion() . ' - ' . $version->getTitle(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Documents', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => $version->getDocument()->getTitle(), 'url' => $this->generateUrl('admin_document_show', ['id' => $version->getDocument()->getId()])],
                    ['label' => 'Versions', 'url' => $this->generateUrl('admin_document_version_index', ['id' => $version->getDocument()->getId()])],
                    ['label' => 'v' . $version->getVersion(), 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error occurred while displaying document version details', [
                'version_id' => $version->getId(),
                'version_number' => $version->getVersion(),
                'document_id' => $version->getDocument()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage des détails de la version.');

            return $this->redirectToRoute('admin_document_version_index', ['id' => $version->getDocument()->getId()]);
        }
    }

    /**
     * Compare two versions of a document.
     */
    #[Route('/compare/{id1}/{id2}', name: 'admin_document_version_compare', methods: ['GET'], requirements: ['id1' => '\d+', 'id2' => '\d+'])]
    public function compare(int $id1, int $id2, DocumentVersionRepository $versionRepository): Response
    {
        try {
            $this->logger->info('Starting document version comparison', [
                'version_id_1' => $id1,
                'version_id_2' => $id2,
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'request_time' => new DateTimeImmutable(),
            ]);

            // Find both versions
            $version1 = $versionRepository->find($id1);
            $version2 = $versionRepository->find($id2);

            // Check if both versions exist
            if (!$version1) {
                $this->logger->warning('Version 1 not found for comparison', [
                    'requested_version_id' => $id1,
                    'version_id_2' => $id2,
                    'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                ]);

                $this->addFlash('error', 'La version avec l\'ID ' . $id1 . ' n\'existe pas.');

                return $this->redirectToRoute('admin_document_index');
            }

            if (!$version2) {
                $this->logger->warning('Version 2 not found for comparison', [
                    'version_id_1' => $id1,
                    'requested_version_id' => $id2,
                    'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                ]);

                $this->addFlash('error', 'La version avec l\'ID ' . $id2 . ' n\'existe pas.');

                return $this->redirectToRoute('admin_document_index');
            }

            $this->logger->debug('Both versions found for comparison', [
                'version1_id' => $version1->getId(),
                'version1_number' => $version1->getVersion(),
                'version1_document_id' => $version1->getDocument()->getId(),
                'version2_id' => $version2->getId(),
                'version2_number' => $version2->getVersion(),
                'version2_document_id' => $version2->getDocument()->getId(),
            ]);

            // Ensure both versions belong to the same document
            if ($version1->getDocument()->getId() !== $version2->getDocument()->getId()) {
                $this->logger->warning('Attempted to compare versions from different documents', [
                    'version1_id' => $version1->getId(),
                    'version1_document_id' => $version1->getDocument()->getId(),
                    'version1_document_title' => $version1->getDocument()->getTitle(),
                    'version2_id' => $version2->getId(),
                    'version2_document_id' => $version2->getDocument()->getId(),
                    'version2_document_title' => $version2->getDocument()->getTitle(),
                    'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                ]);

                $this->addFlash('error', 'Les versions doivent appartenir au même document.');

                return $this->redirectToRoute('admin_document_index');
            }

            // Ensure version1 is older than version2 for consistent comparison
            $originalOrder = ['version1' => $version1, 'version2' => $version2];
            if ($version1->getCreatedAt() > $version2->getCreatedAt()) {
                [$version1, $version2] = [$version2, $version1];

                $this->logger->debug('Swapped version order for consistent comparison', [
                    'original_version1_id' => $originalOrder['version1']->getId(),
                    'original_version2_id' => $originalOrder['version2']->getId(),
                    'new_version1_id' => $version1->getId(),
                    'new_version2_id' => $version2->getId(),
                ]);
            }

            $this->logger->info('Document version comparison prepared successfully', [
                'document_id' => $version1->getDocument()->getId(),
                'document_title' => $version1->getDocument()->getTitle(),
                'older_version_id' => $version1->getId(),
                'older_version_number' => $version1->getVersion(),
                'newer_version_id' => $version2->getId(),
                'newer_version_number' => $version2->getVersion(),
                'version1_created_at' => $version1->getCreatedAt()->format('Y-m-d H:i:s'),
                'version2_created_at' => $version2->getCreatedAt()->format('Y-m-d H:i:s'),
                'content_length_diff' => $version2->getContentLength() - $version1->getContentLength(),
            ]);

            return $this->render('admin/document_version/compare.html.twig', [
                'version1' => $version1,
                'version2' => $version2,
                'document' => $version1->getDocument(),
                'page_title' => 'Comparaison: v' . $version1->getVersion() . ' vs v' . $version2->getVersion(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Documents', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => $version1->getDocument()->getTitle(), 'url' => $this->generateUrl('admin_document_show', ['id' => $version1->getDocument()->getId()])],
                    ['label' => 'Versions', 'url' => $this->generateUrl('admin_document_version_index', ['id' => $version1->getDocument()->getId()])],
                    ['label' => 'Comparaison', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error occurred during document version comparison', [
                'version_id_1' => $id1,
                'version_id_2' => $id2,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la comparaison des versions.');

            return $this->redirectToRoute('admin_document_index');
        }
    }

    /**
     * Rollback document to a specific version.
     */
    #[Route('/{id}/rollback', name: 'admin_document_version_rollback', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function rollback(Request $request, DocumentVersion $version, EntityManagerInterface $entityManager): Response
    {
        $documentId = $version->getDocument()->getId();

        try {
            $this->logger->info('Starting document rollback process', [
                'version_id' => $version->getId(),
                'version_number' => $version->getVersion(),
                'document_id' => $documentId,
                'document_title' => $version->getDocument()->getTitle(),
                'target_version_created_at' => $version->getCreatedAt()->format('Y-m-d H:i:s'),
                'target_version_created_by' => $version->getCreatedByName(),
                'is_current_version' => $version->isCurrent(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'csrf_token_provided' => $request->request->has('_token'),
                'request_time' => new DateTimeImmutable(),
            ]);

            if (!$this->isCsrfTokenValid('rollback' . $version->getId(), $request->request->get('_token'))) {
                $this->logger->warning('Invalid CSRF token for document rollback', [
                    'version_id' => $version->getId(),
                    'document_id' => $documentId,
                    'provided_token' => $request->request->get('_token'),
                    'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');

                return $this->redirectToRoute('admin_document_version_index', ['id' => $documentId]);
            }

            $document = $version->getDocument();
            $originalTitle = $document->getTitle();
            $originalContent = $document->getContent();

            $this->logger->debug('Document current state before rollback', [
                'document_id' => $document->getId(),
                'current_title' => $originalTitle,
                'current_content_length' => strlen($originalContent ?? ''),
                'target_title' => $version->getTitle(),
                'target_content_length' => $version->getContentLength(),
            ]);

            // Create new version from rollback
            $nextVersionNumber = $this->getNextVersionNumber($document);
            $changeLog = 'Restauration vers la version ' . $version->getVersion();

            $this->logger->debug('Creating new version for rollback', [
                'document_id' => $document->getId(),
                'new_version_number' => $nextVersionNumber,
                'change_log' => $changeLog,
                'rollback_source_version' => $version->getVersion(),
            ]);

            $newVersion = $document->createVersion(
                $nextVersionNumber,
                $changeLog,
                $this->getUser(),
            );

            // Copy content from the target version
            $document->setTitle($version->getTitle());
            $document->setContent($version->getContent());
            $newVersion->setTitle($version->getTitle());
            $newVersion->setContent($version->getContent());

            $this->logger->debug('Document content updated for rollback', [
                'document_id' => $document->getId(),
                'previous_title' => $originalTitle,
                'new_title' => $document->getTitle(),
                'previous_content_length' => strlen($originalContent ?? ''),
                'new_content_length' => strlen($document->getContent() ?? ''),
                'new_version_id' => $newVersion->getId(),
                'new_version_number' => $newVersion->getVersion(),
            ]);

            $entityManager->flush();

            $this->logger->info('Document rollback completed successfully', [
                'document_id' => $document->getId(),
                'document_title' => $document->getTitle(),
                'rollback_target_version' => $version->getVersion(),
                'new_version_created' => $newVersion->getVersion(),
                'new_version_id' => $newVersion->getId(),
                'change_log' => $changeLog,
                'title_changed' => $originalTitle !== $document->getTitle(),
                'content_changed' => $originalContent !== $document->getContent(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'completion_time' => new DateTimeImmutable(),
            ]);

            $this->addFlash('success', sprintf(
                'Le document a été restauré à la version %s avec succès. Nouvelle version %s créée.',
                $version->getVersion(),
                $newVersion->getVersion(),
            ));

            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);
        } catch (Exception $e) {
            $this->logger->error('Error during document rollback process', [
                'version_id' => $version->getId(),
                'version_number' => $version->getVersion(),
                'document_id' => $documentId,
                'document_title' => $version->getDocument()->getTitle(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'failure_time' => new DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la restauration.');

            return $this->redirectToRoute('admin_document_version_index', ['id' => $documentId]);
        }
    }

    /**
     * Delete a version (only if not current).
     */
    #[Route('/{id}/delete', name: 'admin_document_version_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentVersion $version, EntityManagerInterface $entityManager): Response
    {
        $documentId = $version->getDocument()->getId();
        $versionNumber = $version->getVersion();

        try {
            $this->logger->info('Starting document version deletion process', [
                'version_id' => $version->getId(),
                'version_number' => $versionNumber,
                'document_id' => $documentId,
                'document_title' => $version->getDocument()->getTitle(),
                'is_current_version' => $version->isCurrent(),
                'version_created_at' => $version->getCreatedAt()->format('Y-m-d H:i:s'),
                'version_created_by' => $version->getCreatedByName(),
                'content_length' => $version->getContentLength(),
                'file_size' => $version->getFileSize(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'csrf_token_provided' => $request->request->has('_token'),
                'request_time' => new DateTimeImmutable(),
            ]);

            if (!$this->isCsrfTokenValid('delete' . $version->getId(), $request->request->get('_token'))) {
                $this->logger->warning('Invalid CSRF token for document version deletion', [
                    'version_id' => $version->getId(),
                    'version_number' => $versionNumber,
                    'document_id' => $documentId,
                    'provided_token' => $request->request->get('_token'),
                    'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');

                return $this->redirectToRoute('admin_document_version_index', ['id' => $documentId]);
            }

            if ($version->isCurrent()) {
                $this->logger->warning('Attempted to delete current document version', [
                    'version_id' => $version->getId(),
                    'version_number' => $versionNumber,
                    'document_id' => $documentId,
                    'document_title' => $version->getDocument()->getTitle(),
                    'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                ]);

                $this->addFlash('error', 'Impossible de supprimer la version actuelle.');

                return $this->redirectToRoute('admin_document_version_index', ['id' => $documentId]);
            }

            // Additional validation before deletion
            $totalVersions = $version->getDocument()->getVersions()->count();
            $this->logger->debug('Version deletion validation', [
                'version_id' => $version->getId(),
                'total_versions_for_document' => $totalVersions,
                'is_only_version' => $totalVersions <= 1,
                'checksum' => $version->getChecksum(),
                'change_log' => $version->getChangeLog(),
            ]);

            $entityManager->remove($version);
            $entityManager->flush();

            $this->logger->info('Document version deleted successfully', [
                'deleted_version_id' => $version->getId(),
                'deleted_version_number' => $versionNumber,
                'document_id' => $documentId,
                'document_title' => $version->getDocument()->getTitle(),
                'remaining_versions_count' => $totalVersions - 1,
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'deletion_time' => new DateTimeImmutable(),
            ]);

            $this->addFlash('success', 'La version ' . $versionNumber . ' a été supprimée avec succès.');

            return $this->redirectToRoute('admin_document_version_index', ['id' => $documentId]);
        } catch (Exception $e) {
            $this->logger->error('Error occurred during document version deletion', [
                'version_id' => $version->getId(),
                'version_number' => $versionNumber,
                'document_id' => $documentId,
                'document_title' => $version->getDocument()->getTitle(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'failure_time' => new DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression.');

            return $this->redirectToRoute('admin_document_version_index', ['id' => $documentId]);
        }
    }

    /**
     * Export version history as JSON.
     */
    #[Route('/document/{id}/export', name: 'admin_document_version_export', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function export(Document $document, DocumentVersionRepository $versionRepository): Response
    {
        try {
            $this->logger->info('Starting document version history export', [
                'document_id' => $document->getId(),
                'document_title' => $document->getTitle(),
                'document_slug' => $document->getSlug(),
                'document_type' => $document->getDocumentType()?->getName(),
                'document_category' => $document->getCategory()?->getName(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'request_time' => new DateTimeImmutable(),
            ]);

            $versions = $versionRepository->findByDocument($document);

            $this->logger->debug('Document versions retrieved for export', [
                'document_id' => $document->getId(),
                'versions_count' => count($versions),
                'versions_ids' => array_map(static fn ($v) => $v->getId(), $versions),
                'versions_numbers' => array_map(static fn ($v) => $v->getVersion(), $versions),
                'current_version' => $versions ? array_filter($versions, static fn ($v) => $v->isCurrent())[0]?->getVersion() ?? 'none' : 'none',
            ]);

            $exportData = [
                'document' => [
                    'id' => $document->getId(),
                    'title' => $document->getTitle(),
                    'slug' => $document->getSlug(),
                    'type' => $document->getDocumentType()?->getName(),
                    'category' => $document->getCategory()?->getName(),
                ],
                'versions' => array_map(static fn ($version) => [
                    'id' => $version->getId(),
                    'version' => $version->getVersion(),
                    'title' => $version->getTitle(),
                    'content_length' => $version->getContentLength(),
                    'change_log' => $version->getChangeLog(),
                    'is_current' => $version->isCurrent(),
                    'file_size' => $version->getFileSize(),
                    'checksum' => $version->getChecksum(),
                    'created_at' => $version->getCreatedAt()->format('Y-m-d H:i:s'),
                    'created_by' => $version->getCreatedByName(),
                ], $versions),
                'exported_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'exported_by' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : null,
            ];

            $this->logger->debug('Export data structure prepared', [
                'document_id' => $document->getId(),
                'export_data_size' => strlen(json_encode($exportData)),
                'versions_exported' => count($exportData['versions']),
                'export_timestamp' => $exportData['exported_at'],
            ]);

            $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($jsonContent === false) {
                throw new Exception('Failed to encode export data to JSON: ' . json_last_error_msg());
            }

            $filename = 'versions-' . $document->getSlug() . '-' . date('Y-m-d') . '.json';

            $this->logger->info('Document version history export completed successfully', [
                'document_id' => $document->getId(),
                'document_title' => $document->getTitle(),
                'export_filename' => $filename,
                'export_file_size' => strlen($jsonContent),
                'versions_exported' => count($versions),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'completion_time' => new DateTimeImmutable(),
            ]);

            $response = new Response($jsonContent);
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error occurred during document version history export', [
                'document_id' => $document->getId(),
                'document_title' => $document->getTitle(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user' => ($admin = $this->getUser()) instanceof Admin ? $admin->getEmail() : 'anonymous',
                'failure_time' => new DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'export des versions.');

            return $this->redirectToRoute('admin_document_version_index', ['id' => $document->getId()]);
        }
    }

    /**
     * Get next version number for rollback.
     */
    private function getNextVersionNumber(Document $document): string
    {
        try {
            $this->logger->debug('Calculating next version number for document', [
                'document_id' => $document->getId(),
                'document_title' => $document->getTitle(),
                'total_versions' => $document->getVersions()->count(),
            ]);

            $latestVersion = $document->getVersions()->filter(static fn ($v) => $v->isCurrent())->first();

            if (!$latestVersion) {
                $this->logger->debug('No current version found, defaulting to version 1.0', [
                    'document_id' => $document->getId(),
                ]);

                return '1.0';
            }

            $currentVersionNumber = $latestVersion->getVersion();
            $this->logger->debug('Current version found', [
                'document_id' => $document->getId(),
                'current_version_id' => $latestVersion->getId(),
                'current_version_number' => $currentVersionNumber,
            ]);

            if (preg_match('/^(\d+)\.(\d+)$/', $currentVersionNumber, $matches)) {
                $major = (int) $matches[1];
                $minor = (int) $matches[2];
                $nextVersionNumber = $major . '.' . ($minor + 1);

                $this->logger->debug('Version number incremented successfully', [
                    'document_id' => $document->getId(),
                    'current_version' => $currentVersionNumber,
                    'next_version' => $nextVersionNumber,
                    'major' => $major,
                    'minor' => $minor,
                ]);

                return $nextVersionNumber;
            }

            $this->logger->warning('Unable to parse current version number, defaulting to 1.0', [
                'document_id' => $document->getId(),
                'current_version_number' => $currentVersionNumber,
            ]);

            return '1.0';
        } catch (Exception $e) {
            $this->logger->error('Error calculating next version number', [
                'document_id' => $document->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return default version in case of error
            return '1.0';
        }
    }
}
