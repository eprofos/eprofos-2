<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentTemplate;
use App\Form\Document\DocumentTemplateType;
use App\Repository\Document\DocumentTemplateRepository;
use App\Service\Document\DocumentTemplateService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document Template Controller.
 *
 * Handles CRUD operations for document templates in the admin interface.
 * Provides management for reusable document templates with placeholders.
 */
#[Route('/admin/document-templates', name: 'admin_document_template_')]
#[IsGranted('ROLE_ADMIN')]
class DocumentTemplateController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentTemplateService $documentTemplateService,
    ) {}

    /**
     * List all document templates with statistics.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(DocumentTemplateRepository $documentTemplateRepository): Response
    {
        $this->logger->info('Admin document templates list accessed', [
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        // Get templates with statistics
        $templatesWithStats = $this->documentTemplateService->getTemplatesWithStats();

        return $this->render('admin/document_template/index.html.twig', [
            'templates_with_stats' => $templatesWithStats,
            'page_title' => 'Modèles de documents',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles de documents', 'url' => null],
            ],
        ]);
    }

    /**
     * Show document template details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentTemplate $documentTemplate): Response
    {
        $this->logger->info('Admin document template details viewed', [
            'template_id' => $documentTemplate->getId(),
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        // Get template statistics
        $stats = [
            'usage_count' => $documentTemplate->getUsageCount(),
            'document_type' => $documentTemplate->getDocumentType()?->getName(),
            'placeholders_count' => count($documentTemplate->getPlaceholders() ?? []),
            'is_default' => $documentTemplate->isDefault(),
        ];

        return $this->render('admin/document_template/show.html.twig', [
            'document_template' => $documentTemplate,
            'stats' => $stats,
            'page_title' => 'Modèle: ' . $documentTemplate->getName(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles de documents', 'url' => $this->generateUrl('admin_document_template_index')],
                ['label' => $documentTemplate->getName(), 'url' => null],
            ],
        ]);
    }

    /**
     * Create a new document template.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $documentTemplate = new DocumentTemplate();

        // Set default values
        $documentTemplate->setIsActive(true);
        $documentTemplate->setUsageCount(0);
        $documentTemplate->setSortOrder($this->documentTemplateService->getNextSortOrder());

        // Pre-select document type if provided
        $typeId = $request->query->get('type');
        if ($typeId) {
            $documentType = $this->documentTemplateService->getDocumentTypeById((int) $typeId);
            if ($documentType) {
                $documentTemplate->setDocumentType($documentType);
            }
        }

        $form = $this->createForm(DocumentTemplateType::class, $documentTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->documentTemplateService->createDocumentTemplate($documentTemplate);

            if ($result['success']) {
                $this->addFlash('success', 'Le modèle de document a été créé avec succès.');

                return $this->redirectToRoute('admin_document_template_show', ['id' => $documentTemplate->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

        return $this->render('admin/document_template/new.html.twig', [
            'document_template' => $documentTemplate,
            'form' => $form,
            'page_title' => 'Nouveau modèle de document',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles de documents', 'url' => $this->generateUrl('admin_document_template_index')],
                ['label' => 'Nouveau', 'url' => null],
            ],
        ]);
    }

    /**
     * Edit an existing document template.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentTemplate $documentTemplate): Response
    {
        $form = $this->createForm(DocumentTemplateType::class, $documentTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->documentTemplateService->updateDocumentTemplate($documentTemplate);

            if ($result['success']) {
                $this->addFlash('success', 'Le modèle de document a été modifié avec succès.');

                return $this->redirectToRoute('admin_document_template_show', ['id' => $documentTemplate->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

        return $this->render('admin/document_template/edit.html.twig', [
            'document_template' => $documentTemplate,
            'form' => $form,
            'page_title' => 'Modifier: ' . $documentTemplate->getName(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles de documents', 'url' => $this->generateUrl('admin_document_template_index')],
                ['label' => $documentTemplate->getName(), 'url' => $this->generateUrl('admin_document_template_show', ['id' => $documentTemplate->getId()])],
                ['label' => 'Modifier', 'url' => null],
            ],
        ]);
    }

    /**
     * Delete a document template.
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentTemplate $documentTemplate): Response
    {
        if ($this->isCsrfTokenValid('delete' . $documentTemplate->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->documentTemplateService->deleteDocumentTemplate($documentTemplate);

            if ($result['success']) {
                $this->addFlash('success', 'Le modèle de document a été supprimé avec succès.');
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_template_index');
    }

    /**
     * Toggle document template active status.
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, DocumentTemplate $documentTemplate): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $documentTemplate->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->documentTemplateService->toggleActiveStatus($documentTemplate);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_template_index');
    }

    /**
     * Duplicate a document template.
     */
    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, DocumentTemplate $documentTemplate): Response
    {
        if ($this->isCsrfTokenValid('duplicate' . $documentTemplate->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->documentTemplateService->duplicateDocumentTemplate($documentTemplate);

            if ($result['success']) {
                $this->addFlash('success', 'Le modèle de document a été dupliqué avec succès.');

                return $this->redirectToRoute('admin_document_template_edit', ['id' => $result['template']->getId()]);
            }
            $this->addFlash('error', $result['error']);
        }

        return $this->redirectToRoute('admin_document_template_index');
    }

    /**
     * Create document from template.
     */
    #[Route('/{id}/create-document', name: 'create_document', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function createDocument(Request $request, DocumentTemplate $documentTemplate): Response
    {
        $result = $this->documentTemplateService->createDocumentFromTemplate($documentTemplate, $request->request->all());

        if ($result['success']) {
            $this->addFlash('success', 'Document créé avec succès à partir du modèle.');

            return $this->redirectToRoute('admin_document_edit', ['id' => $result['document']->getId()]);
        }
        $this->addFlash('error', $result['error']);

        return $this->redirectToRoute('admin_document_template_show', ['id' => $documentTemplate->getId()]);
    }
}
