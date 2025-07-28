<?php

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentUITemplate;
use App\Form\DocumentUITemplateType;
use App\Repository\Document\DocumentUIComponentRepository;
use App\Repository\Document\DocumentUITemplateRepository;
use App\Service\Document\DocumentUITemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document UI Template Controller
 * 
 * Handles CRUD operations for document UI templates in the admin interface.
 * Provides management for configurable UI layouts and PDF generation templates.
 */
#[Route('/admin/document-ui-templates', name: 'admin_document_ui_template_')]
#[IsGranted('ROLE_ADMIN')]
class DocumentUITemplateController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentUITemplateService $uiTemplateService
    ) {
    }

    /**
     * List all document UI templates with statistics
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(DocumentUITemplateRepository $uiTemplateRepository): Response
    {
        $this->logger->info('Admin document UI templates list accessed', [
            'user' => $this->getUser()?->getUserIdentifier()
        ]);

        // Get templates with statistics
        $templatesWithStats = $this->uiTemplateService->getTemplatesWithStats();

        return $this->render('admin/document_ui_template/index.html.twig', [
            'templates_with_stats' => $templatesWithStats,
            'page_title' => 'Modèles UI de documents',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles UI', 'url' => null]
            ]
        ]);
    }

    /**
     * List all UI components across all templates
     */
    #[Route('/components', name: 'all_components', methods: ['GET'])]
    public function allComponents(DocumentUIComponentRepository $componentRepository): Response
    {
        $this->logger->info('Admin all document UI components list accessed', [
            'user' => $this->getUser()?->getUserIdentifier()
        ]);

        // Get all components with their templates
        $components = $componentRepository->findAllWithTemplates();
        
        // Group components by template and zone
        $componentsByTemplate = [];
        foreach ($components as $component) {
            $template = $component->getUiTemplate();
            $templateId = $template->getId();
            
            if (!isset($componentsByTemplate[$templateId])) {
                $componentsByTemplate[$templateId] = [
                    'template' => $template,
                    'components' => []
                ];
            }
            
            $componentsByTemplate[$templateId]['components'][$component->getZone()][] = $component;
        }

        return $this->render('admin/document_ui_template/all_components.html.twig', [
            'components_by_template' => $componentsByTemplate,
            'total_components' => count($components),
            'zones' => DocumentUITemplate::ZONES,
            'component_types' => DocumentUIComponent::TYPES,
            'page_title' => 'Tous les composants UI',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                ['label' => 'Composants UI', 'url' => null]
            ]
        ]);
    }

    /**
     * Show UI template details
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentUITemplate $uiTemplate): Response
    {
        $this->logger->info('Admin document UI template details viewed', [
            'template_id' => $uiTemplate->getId(),
            'user' => $this->getUser()?->getUserIdentifier()
        ]);

        // Get template components
        $components = $uiTemplate->getComponents();

        // Get template statistics including recent usage
        $stats = [
            'usage_count' => $uiTemplate->getUsageCount(),
            'component_count' => $components->count(),
            'document_type' => $uiTemplate->getDocumentType()?->getName() ?? 'Global',
            'is_default' => $uiTemplate->isDefault(),
            'is_global' => $uiTemplate->isGlobal(),
            'page_format' => $uiTemplate->getPaperSize() . ' ' . $uiTemplate->getOrientation(),
            'validation_errors' => $uiTemplate->validateConfiguration(),
            'recent_usage' => [] // TODO: Implement recent usage tracking if needed
        ];

        return $this->render('admin/document_ui_template/show.html.twig', [
            'ui_template' => $uiTemplate,
            'components' => $components,
            'stats' => $stats,
            'page_title' => 'Modèle UI: ' . $uiTemplate->getName(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                ['label' => $uiTemplate->getName(), 'url' => null]
            ]
        ]);
    }

    /**
     * Create a new UI template
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $uiTemplate = new DocumentUITemplate();
        
        // Set default values
        $uiTemplate->setIsActive(true);
        $uiTemplate->setUsageCount(0);
        $uiTemplate->setSortOrder($this->uiTemplateService->getNextSortOrder());

        // Pre-select document type if provided
        $typeId = $request->query->get('type');
        if ($typeId) {
            $documentType = $this->uiTemplateService->getDocumentTypeById((int) $typeId);
            if ($documentType) {
                $uiTemplate->setDocumentType($documentType);
            }
        }

        $form = $this->createForm(DocumentUITemplateType::class, $uiTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->uiTemplateService->createUITemplate($uiTemplate);
            
            if ($result['success']) {
                $this->addFlash('success', 'Le modèle UI a été créé avec succès.');
                return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $uiTemplate->getId()]);
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->render('admin/document_ui_template/new.html.twig', [
            'ui_template' => $uiTemplate,
            'form' => $form,
            'page_title' => 'Nouveau modèle UI',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                ['label' => 'Nouveau', 'url' => null]
            ]
        ]);
    }

    /**
     * Edit an existing UI template
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        $form = $this->createForm(DocumentUITemplateType::class, $uiTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->uiTemplateService->updateUITemplate($uiTemplate);
            
            if ($result['success']) {
                $this->addFlash('success', 'Le modèle UI a été modifié avec succès.');
                return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $uiTemplate->getId()]);
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->render('admin/document_ui_template/edit.html.twig', [
            'ui_template' => $uiTemplate,
            'form' => $form,
            'page_title' => 'Modifier: ' . $uiTemplate->getName(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                ['label' => $uiTemplate->getName(), 'url' => $this->generateUrl('admin_document_ui_template_show', ['id' => $uiTemplate->getId()])],
                ['label' => 'Modifier', 'url' => null]
            ]
        ]);
    }

    /**
     * Delete a UI template
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        if ($this->isCsrfTokenValid('delete'.$uiTemplate->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->uiTemplateService->deleteUITemplate($uiTemplate);
            
            if ($result['success']) {
                $this->addFlash('success', 'Le modèle UI a été supprimé avec succès.');
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_ui_template_index');
    }

    /**
     * Toggle UI template active status
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$uiTemplate->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->uiTemplateService->toggleActiveStatus($uiTemplate);
            
            if ($result['success']) {
                $this->addFlash('success', $result['message']);
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_ui_template_index');
    }

    /**
     * Duplicate a UI template
     */
    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        if ($this->isCsrfTokenValid('duplicate'.$uiTemplate->getId(), $request->getPayload()->get('_token'))) {
            $result = $this->uiTemplateService->duplicateUITemplate($uiTemplate);
            
            if ($result['success']) {
                $this->addFlash('success', 'Le modèle UI a été dupliqué avec succès.');
                return $this->redirectToRoute('admin_document_ui_template_edit', ['id' => $result['template']->getId()]);
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->redirectToRoute('admin_document_ui_template_index');
    }

    /**
     * Preview UI template
     */
    #[Route('/{id}/preview', name: 'preview', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function preview(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        // Get preview data from request or use defaults
        $previewData = $request->request->all() ?: [
            'title' => 'Document de démonstration',
            'content' => 'Ceci est un contenu de démonstration pour prévisualiser le modèle UI. Ce texte permet de voir comment le contenu principal sera affiché dans le document final.',
            'date' => date('d/m/Y'),
            'author' => 'Nom de l\'auteur',
            'organization' => 'EPROFOS',
            'company_name' => 'EPROFOS',
            'document_reference' => 'DOC-' . date('Y') . '-001',
            'legal_reference' => 'REF-LEGAL-2025',
            'effective_date' => date('d/m/Y'),
            'review_date' => date('d/m/Y', strtotime('+1 year')),
            'page_number' => '1',
            'total_pages' => '3',
        ];

        $result = $this->uiTemplateService->renderTemplate($uiTemplate, $previewData);
        
        if (!$result['success']) {
            $this->addFlash('error', $result['error']);
            return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $uiTemplate->getId()]);
        }

        return $this->render('admin/document_ui_template/preview.html.twig', [
            'ui_template' => $uiTemplate,
            'rendered_html' => $result['html'],
            'rendered_css' => $result['css'],
            'components' => $result['components'],
            'preview_data' => $previewData,
            'page_title' => 'Aperçu: ' . $uiTemplate->getName(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                ['label' => $uiTemplate->getName(), 'url' => $this->generateUrl('admin_document_ui_template_show', ['id' => $uiTemplate->getId()])],
                ['label' => 'Aperçu', 'url' => null]
            ]
        ]);
    }

    /**
     * Export UI template configuration
     */
    #[Route('/{id}/export', name: 'export', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function export(DocumentUITemplate $uiTemplate): Response
    {
        $config = $this->uiTemplateService->exportTemplate($uiTemplate);
        
        $filename = 'ui-template-' . $uiTemplate->getSlug() . '.json';
        $response = new JsonResponse($config);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        return $response;
    }

    /**
     * Import UI template configuration
     */
    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $uploadedFile = $request->files->get('template_file');
            $documentTypeId = $request->request->get('document_type_id');
            
            if (!$uploadedFile) {
                $this->addFlash('error', 'Veuillez sélectionner un fichier.');
            } else {
                try {
                    $config = json_decode(file_get_contents($uploadedFile->getPathname()), true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->addFlash('error', 'Fichier JSON invalide.');
                    } else {
                        $documentType = null;
                        if ($documentTypeId) {
                            $documentType = $this->uiTemplateService->getDocumentTypeById((int) $documentTypeId);
                        }
                        
                        $result = $this->uiTemplateService->importTemplate($config, $documentType);
                        
                        if ($result['success']) {
                            $this->addFlash('success', 'Modèle UI importé avec succès.');
                            return $this->redirectToRoute('admin_document_ui_template_edit', [
                                'id' => $result['template']->getId()
                            ]);
                        } else {
                            $this->addFlash('error', $result['error']);
                        }
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'importation: ' . $e->getMessage());
                }
            }
        }

        return $this->render('admin/document_ui_template/import.html.twig', [
            'page_title' => 'Importer un modèle UI',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                ['label' => 'Importer', 'url' => null]
            ]
        ]);
    }

    /**
     * Manage UI template components
     */
    #[Route('/{id}/components', name: 'components', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function components(DocumentUITemplate $uiTemplate): Response
    {
        $components = $uiTemplate->getComponents();
        
        // Group components by zone
        $componentsByZone = [];
        foreach ($components as $component) {
            $componentsByZone[$component->getZone()][] = $component;
        }

        return $this->render('admin/document_ui_template/components.html.twig', [
            'ui_template' => $uiTemplate,
            'components_by_zone' => $componentsByZone,
            'zones' => DocumentUITemplate::ZONES,
            'page_title' => 'Composants: ' . $uiTemplate->getName(),
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                ['label' => $uiTemplate->getName(), 'url' => $this->generateUrl('admin_document_ui_template_show', ['id' => $uiTemplate->getId()])],
                ['label' => 'Composants', 'url' => null]
            ]
        ]);
    }

    /**
     * Update component sort orders via AJAX
     */
    #[Route('/{id}/components/sort', name: 'sort_components', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sortComponents(Request $request, DocumentUITemplate $uiTemplate): JsonResponse
    {
        $componentIds = $request->request->get('component_ids');
        
        if (!is_array($componentIds)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid data'], 400);
        }
        
        $result = $this->uiTemplateService->updateComponentSortOrders($uiTemplate, $componentIds);
        
        return new JsonResponse($result);
    }

    /**
     * Generate PDF preview
     */
    #[Route('/{id}/pdf-preview', name: 'pdf_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pdfPreview(DocumentUITemplate $uiTemplate): Response
    {
        // This would integrate with a PDF generation service
        // For now, return a placeholder
        return new Response('PDF Preview functionality to be implemented', 200, [
            'Content-Type' => 'text/plain'
        ]);
    }
}
