<?php

namespace App\Controller\Admin;

use App\Entity\LegalDocument;
use App\Form\LegalDocumentType;
use App\Repository\LegalDocumentRepository;
use App\Service\LegalDocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Legal Document Controller
 * 
 * Handles legal document management in the admin interface.
 * Each document type has its own dedicated page and management interface.
 */
#[Route('/admin/legal-documents', name: 'admin_legal_document_')]
#[IsGranted('ROLE_ADMIN')]
class LegalDocumentController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private LegalDocumentService $legalDocumentService
    ) {
    }

    /**
     * Dashboard/overview of all legal documents
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, LegalDocumentRepository $documentRepository): Response
    {
        $this->logger->info('Admin accessing legal documents dashboard', [
            'user' => $this->getUser()?->getUserIdentifier()
        ]);

        // Get filter parameters
        $filters = [
            'search' => $request->query->get('search', ''),
            'type' => $request->query->get('type', ''),
            'status' => $request->query->get('status', ''),
        ];

        // Get statistics for each document type
        $statistics = $documentRepository->getStatistics();
        
        // Get filtered documents if any filters are applied
        $documents = [];
        $hasFilters = !empty($filters['search']) || !empty($filters['type']) || !empty($filters['status']);
        
        if ($hasFilters) {
            $queryBuilder = $documentRepository->createAdminQueryBuilder($filters);
            $documents = $queryBuilder->getQuery()->getResult();
        }

        return $this->render('admin/legal_document/index.html.twig', [
            'statistics' => $statistics,
            'documents' => $documents,
            'filters' => $filters,
            'hasFilters' => $hasFilters,
            'page_title' => 'Documents légaux - Dashboard',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Documents légaux', 'url' => null]
            ]
        ]);
    }

    /**
     * Internal Regulations management
     */
    #[Route('/reglements-interieurs', name: 'internal_regulation', methods: ['GET'])]
    public function internalRegulation(Request $request, LegalDocumentRepository $documentRepository): Response
    {
        return $this->renderDocumentTypePage('internal_regulation', 'Règlements intérieurs', $request, $documentRepository);
    }

    /**
     * Student Handbooks management
     */
    #[Route('/livrets-accueil', name: 'student_handbook', methods: ['GET'])]
    public function studentHandbook(Request $request, LegalDocumentRepository $documentRepository): Response
    {
        return $this->renderDocumentTypePage('student_handbook', 'Livrets d\'accueil', $request, $documentRepository);
    }

    /**
     * Training Terms management
     */
    #[Route('/conditions-formation', name: 'training_terms', methods: ['GET'])]
    public function trainingTerms(Request $request, LegalDocumentRepository $documentRepository): Response
    {
        return $this->renderDocumentTypePage('training_terms', 'Conditions de formation', $request, $documentRepository);
    }

    /**
     * Accessibility Policy management
     */
    #[Route('/politique-accessibilite', name: 'accessibility_policy', methods: ['GET'])]
    public function accessibilityPolicy(Request $request, LegalDocumentRepository $documentRepository): Response
    {
        return $this->renderDocumentTypePage('accessibility_policy', 'Politiques d\'accessibilité', $request, $documentRepository);
    }

    /**
     * Accessibility Procedures management
     */
    #[Route('/procedures-accessibilite', name: 'accessibility_procedures', methods: ['GET'])]
    public function accessibilityProcedures(Request $request, LegalDocumentRepository $documentRepository): Response
    {
        return $this->renderDocumentTypePage('accessibility_procedures', 'Procédures d\'accessibilité', $request, $documentRepository);
    }

    /**
     * Accessibility FAQ management
     */
    #[Route('/faq-accessibilite', name: 'accessibility_faq', methods: ['GET'])]
    public function accessibilityFaq(Request $request, LegalDocumentRepository $documentRepository): Response
    {
        return $this->renderDocumentTypePage('accessibility_faq', 'FAQ Accessibilité', $request, $documentRepository);
    }

    /**
     * Helper method to render document type specific pages
     */
    private function renderDocumentTypePage(string $type, string $title, Request $request, LegalDocumentRepository $documentRepository): Response
    {
        $this->logger->info('Admin accessing legal documents by type', [
            'type' => $type,
            'user' => $this->getUser()?->getUserIdentifier()
        ]);

        // Get filter parameters
        $filters = [
            'search' => $request->query->get('search', ''),
            'status' => $request->query->get('status', ''),
            'type' => $type, // Fixed type for this page
        ];

        $queryBuilder = $documentRepository->createAdminQueryBuilder($filters);
        $documents = $queryBuilder->getQuery()->getResult();

        // Get statistics for this specific type
        $typeStatistics = $documentRepository->getTypeStatistics($type);

        return $this->render('admin/legal_document/type_page.html.twig', [
            'documents' => $documents,
            'filters' => $filters,
            'type' => $type,
            'type_title' => $title,
            'type_statistics' => $typeStatistics,
            'page_title' => $title,
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Documents légaux', 'url' => $this->generateUrl('admin_legal_document_index')],
                ['label' => $title, 'url' => null]
            ]
        ]);
    }

    /**
     * Show a specific legal document
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(LegalDocument $document): Response
    {
        $this->logger->info('Admin viewing legal document details', [
            'document_id' => $document->getId(),
            'user' => $this->getUser()?->getUserIdentifier()
        ]);

        // Get publication status for this document type
        $typeStatus = $this->legalDocumentService->getTypePublicationStatus($document->getType());
        
        // Check if document can be published
        $publishValidation = $this->legalDocumentService->canPublish($document);

        return $this->render('admin/legal_document/show.html.twig', [
            'document' => $document,
            'type_status' => $typeStatus,
            'publish_validation' => $publishValidation,
            'page_title' => 'Document: ' . $document->getTitle(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Documents légaux', 'url' => $this->generateUrl('admin_legal_document_index')],
                ['label' => $document->getTitle(), 'url' => null]
            ]
        ]);
    }

    /**
     * Create a new legal document
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $document = new LegalDocument();
        
        // Pre-fill type if provided in query parameter
        $requestedType = $request->query->get('type');
        if ($requestedType && in_array($requestedType, LegalDocument::getValidTypes())) {
            $document->setType($requestedType);
        }
        
        $form = $this->createForm(LegalDocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if document is being created as published
            if ($document->getStatus() === LegalDocument::STATUS_PUBLISHED) {
                // Handle automatic archiving of other documents of the same type
                $result = $this->legalDocumentService->publishDocument($document);
                
                if ($result['success']) {
                    $archivedCount = $result['archived_count'];
                    if ($archivedCount > 0) {
                        $this->addFlash('success', sprintf(
                            'Le document légal a été créé et publié avec succès. %d document(s) précédent(s) ont été archivé(s).',
                            $archivedCount
                        ));
                    } else {
                        $this->addFlash('success', 'Le document légal a été créé et publié avec succès.');
                    }
                } else {
                    $this->addFlash('error', 'Une erreur est survenue lors de la publication : ' . $result['error']);
                    return $this->redirectToRoute('admin_legal_document_new');
                }
            } else {
                // Normal save without publish logic
                $entityManager->persist($document);
                $entityManager->flush();
                
                $this->logger->info('Legal document created', [
                    'document_id' => $document->getId(),
                    'type' => $document->getType(),
                    'status' => $document->getStatus(),
                    'user' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->addFlash('success', 'Le document légal a été créé avec succès.');
            }

            return $this->redirectToRoute('admin_legal_document_show', ['id' => $document->getId()]);
        }

        return $this->render('admin/legal_document/new.html.twig', [
            'document' => $document,
            'form' => $form,
            'page_title' => 'Nouveau document légal',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Documents légaux', 'url' => $this->generateUrl('admin_legal_document_index')],
                ['label' => 'Nouveau', 'url' => null]
            ]
        ]);
    }

    /**
     * Edit an existing legal document
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, LegalDocument $document, EntityManagerInterface $entityManager): Response
    {
        $originalStatus = $document->getStatus();
        
        $form = $this->createForm(LegalDocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentStatus = $document->getStatus();
            
            // Check if document transitioned to published status
            if ($originalStatus !== LegalDocument::STATUS_PUBLISHED && $currentStatus === LegalDocument::STATUS_PUBLISHED) {
                // Handle automatic archiving of other documents of the same type
                $result = $this->legalDocumentService->publishDocument($document);
                
                if ($result['success']) {
                    $archivedCount = $result['archived_count'];
                    if ($archivedCount > 0) {
                        $this->addFlash('success', sprintf(
                            'Le document légal a été modifié et publié avec succès. %d document(s) précédent(s) ont été archivé(s).',
                            $archivedCount
                        ));
                    } else {
                        $this->addFlash('success', 'Le document légal a été modifié et publié avec succès.');
                    }
                } else {
                    $this->addFlash('error', 'Une erreur est survenue lors de la publication : ' . $result['error']);
                    return $this->redirectToRoute('admin_legal_document_edit', ['id' => $document->getId()]);
                }
            } else {
                // Normal save without publish logic
                $entityManager->flush();
                
                $this->logger->info('Legal document updated', [
                    'document_id' => $document->getId(),
                    'type' => $document->getType(),
                    'status' => $document->getStatus(),
                    'user' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->addFlash('success', 'Le document légal a été modifié avec succès.');
            }

            return $this->redirectToRoute('admin_legal_document_show', ['id' => $document->getId()]);
        }

        return $this->render('admin/legal_document/edit.html.twig', [
            'document' => $document,
            'form' => $form,
            'page_title' => 'Modifier: ' . $document->getTitle(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Documents légaux', 'url' => $this->generateUrl('admin_legal_document_index')],
                ['label' => $document->getTitle(), 'url' => $this->generateUrl('admin_legal_document_show', ['id' => $document->getId()])],
                ['label' => 'Modifier', 'url' => null]
            ]
        ]);
    }

    /**
     * Delete a legal document
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, LegalDocument $document, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->getPayload()->get('_token'))) {
            $documentId = $document->getId();
            $entityManager->remove($document);
            $entityManager->flush();

            $this->logger->info('Legal document deleted', [
                'document_id' => $documentId,
                'user' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', 'Le document légal a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_legal_document_index');
    }

    /**
     * Publish/unpublish a legal document
     */
    #[Route('/{id}/toggle-publish', name: 'toggle_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function togglePublish(Request $request, LegalDocument $document): Response
    {
        if ($this->isCsrfTokenValid('publish'.$document->getId(), $request->getPayload()->get('_token'))) {
            
            if ($document->getStatus() === LegalDocument::STATUS_PUBLISHED) {
                // Unpublish the document (set to draft)
                $result = $this->legalDocumentService->unpublishDocument($document);
                
                if ($result['success']) {
                    $this->addFlash('success', 'Le document a été dépublié avec succès.');
                } else {
                    $this->addFlash('error', 'Erreur lors de la dépublication : ' . $result['error']);
                }
            } else {
                // Check if document can be published
                $validation = $this->legalDocumentService->canPublish($document);
                
                if (!$validation['can_publish']) {
                    $this->addFlash('error', 'Impossible de publier le document : ' . implode(', ', $validation['issues']));
                } else {
                    // Publish the document (this will automatically archive other documents of the same type)
                    $result = $this->legalDocumentService->publishDocument($document);
                    
                    if ($result['success']) {
                        $archivedCount = $result['archived_count'];
                        if ($archivedCount > 0) {
                            $this->addFlash('success', sprintf(
                                'Le document a été publié avec succès. %d document(s) précédent(s) ont été archivé(s).',
                                $archivedCount
                            ));
                        } else {
                            $this->addFlash('success', 'Le document a été publié avec succès.');
                        }
                    } else {
                        $this->addFlash('error', 'Erreur lors de la publication : ' . $result['error']);
                    }
                }
            }
        }

        return $this->redirectToRoute('admin_legal_document_show', ['id' => $document->getId()]);
    }
}
