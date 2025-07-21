<?php

namespace App\Controller\Admin\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentVersion;
use App\Entity\User\User;
use App\Repository\Document\DocumentVersionRepository;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document Version Controller
 * 
 * Handles version management operations for documents in the admin interface.
 * Provides version history, comparison, rollback, and detailed version operations.
 */
#[Route('/admin/document-versions', name: 'admin_document_version_')]
#[IsGranted('ROLE_ADMIN')]
class DocumentVersionController extends AbstractController
{
    public function __construct(
        private DocumentService $documentService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * List all versions for a specific document
     */
    #[Route('/document/{id}', name: 'index', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function index(Document $document, DocumentVersionRepository $versionRepository): Response
    {
        $versions = $versionRepository->findByDocument($document);

        return $this->render('admin/document_version/index.html.twig', [
            'document' => $document,
            'versions' => $versions,
            'page_title' => 'Versions de: ' . $document->getTitle(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Documents', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => $document->getTitle(), 'url' => $this->generateUrl('admin_document_show', ['id' => $document->getId()])],
                ['label' => 'Versions', 'url' => null]
            ]
        ]);
    }

    /**
     * Show details of a specific version
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentVersion $version): Response
    {
        return $this->render('admin/document_version/show.html.twig', [
            'version' => $version,
            'document' => $version->getDocument(),
            'page_title' => 'Version ' . $version->getVersion() . ' - ' . $version->getTitle(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Documents', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => $version->getDocument()->getTitle(), 'url' => $this->generateUrl('admin_document_show', ['id' => $version->getDocument()->getId()])],
                ['label' => 'Versions', 'url' => $this->generateUrl('admin_document_version_index', ['id' => $version->getDocument()->getId()])],
                ['label' => 'v' . $version->getVersion(), 'url' => null]
            ]
        ]);
    }

    /**
     * Compare two versions of a document
     */
    #[Route('/compare/{id1}/{id2}', name: 'compare', methods: ['GET'], requirements: ['id1' => '\d+', 'id2' => '\d+'])]
    public function compare(int $id1, int $id2, DocumentVersionRepository $versionRepository): Response
    {
        // Find both versions
        $version1 = $versionRepository->find($id1);
        $version2 = $versionRepository->find($id2);

        // Check if both versions exist
        if (!$version1) {
            $this->addFlash('error', 'La version avec l\'ID ' . $id1 . ' n\'existe pas.');
            return $this->redirectToRoute('admin_document_index');
        }

        if (!$version2) {
            $this->addFlash('error', 'La version avec l\'ID ' . $id2 . ' n\'existe pas.');
            return $this->redirectToRoute('admin_document_index');
        }

        // Ensure both versions belong to the same document
        if ($version1->getDocument()->getId() !== $version2->getDocument()->getId()) {
            $this->addFlash('error', 'Les versions doivent appartenir au même document.');
            return $this->redirectToRoute('admin_document_index');
        }

        // Ensure version1 is older than version2 for consistent comparison
        if ($version1->getCreatedAt() > $version2->getCreatedAt()) {
            [$version1, $version2] = [$version2, $version1];
        }

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
                ['label' => 'Comparaison', 'url' => null]
            ]
        ]);
    }

    /**
     * Rollback document to a specific version
     */
    #[Route('/{id}/rollback', name: 'rollback', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function rollback(Request $request, DocumentVersion $version, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('rollback' . $version->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_document_version_index', ['id' => $version->getDocument()->getId()]);
        }

        try {
            $document = $version->getDocument();
            
            // Create new version from rollback
            $newVersion = $document->createVersion(
                $this->getNextVersionNumber($document),
                'Restauration vers la version ' . $version->getVersion(),
                $this->getUser()
            );

            // Copy content from the target version
            $document->setTitle($version->getTitle());
            $document->setContent($version->getContent());
            $newVersion->setTitle($version->getTitle());
            $newVersion->setContent($version->getContent());

            $entityManager->flush();

            $this->addFlash('success', sprintf(
                'Le document a été restauré à la version %s avec succès. Nouvelle version %s créée.',
                $version->getVersion(),
                $newVersion->getVersion()
            ));

            $this->logger->info('Document rolled back to version', [
                'document_id' => $document->getId(),
                'target_version' => $version->getVersion(),
                'new_version' => $newVersion->getVersion(),
                'user' => ($user = $this->getUser()) instanceof User ? $user->getEmail() : null
            ]);

            return $this->redirectToRoute('admin_document_show', ['id' => $document->getId()]);

        } catch (\Exception $e) {
            $this->logger->error('Error during document rollback', [
                'version_id' => $version->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->addFlash('error', 'Une erreur est survenue lors de la restauration.');
            return $this->redirectToRoute('admin_document_version_index', ['id' => $version->getDocument()->getId()]);
        }
    }

    /**
     * Delete a version (only if not current)
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentVersion $version, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $version->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_document_version_index', ['id' => $version->getDocument()->getId()]);
        }

        if ($version->isCurrent()) {
            $this->addFlash('error', 'Impossible de supprimer la version actuelle.');
            return $this->redirectToRoute('admin_document_version_index', ['id' => $version->getDocument()->getId()]);
        }

        try {
            $documentId = $version->getDocument()->getId();
            $versionNumber = $version->getVersion();
            
            $entityManager->remove($version);
            $entityManager->flush();

            $this->addFlash('success', 'La version ' . $versionNumber . ' a été supprimée avec succès.');

            $this->logger->info('Document version deleted', [
                'version_id' => $version->getId(),
                'version_number' => $versionNumber,
                'document_id' => $documentId,
                'user' => ($user = $this->getUser()) instanceof User ? $user->getEmail() : null
            ]);

            return $this->redirectToRoute('admin_document_version_index', ['id' => $documentId]);

        } catch (\Exception $e) {
            $this->logger->error('Error deleting document version', [
                'version_id' => $version->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression.');
            return $this->redirectToRoute('admin_document_version_index', ['id' => $version->getDocument()->getId()]);
        }
    }

    /**
     * Get next version number for rollback
     */
    private function getNextVersionNumber(Document $document): string
    {
        $latestVersion = $document->getVersions()->filter(fn($v) => $v->isCurrent())->first();
        
        if (!$latestVersion) {
            return '1.0';
        }

        if (preg_match('/^(\d+)\.(\d+)$/', $latestVersion->getVersion(), $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            
            return $major . '.' . ($minor + 1);
        }

        return '1.0';
    }

    /**
     * Export version history as JSON
     */
    #[Route('/document/{id}/export', name: 'export', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function export(Document $document, DocumentVersionRepository $versionRepository): Response
    {
        $versions = $versionRepository->findByDocument($document);
        
        $data = [
            'document' => [
                'id' => $document->getId(),
                'title' => $document->getTitle(),
                'slug' => $document->getSlug(),
                'type' => $document->getDocumentType()?->getName(),
                'category' => $document->getCategory()?->getName(),
            ],
            'versions' => array_map(function($version) {
                return [
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
                ];
            }, $versions),
            'exported_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'exported_by' => ($user = $this->getUser()) instanceof User ? $user->getEmail() : null,
        ];

        $response = new Response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 
            'attachment; filename="versions-' . $document->getSlug() . '-' . date('Y-m-d') . '.json"'
        );

        return $response;
    }
}
