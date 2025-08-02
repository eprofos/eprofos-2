<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentUITemplate;
use App\Form\Document\DocumentUITemplateType;
use App\Repository\Document\DocumentTypeRepository;
use App\Repository\Document\DocumentUIComponentRepository;
use App\Repository\Document\DocumentUITemplateRepository;
use App\Service\Document\DocumentUITemplateService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document UI Template Controller.
 *
 * Handles CRUD operations for document UI templates in the admin interface.
 * Provides management for configurable UI layouts and PDF generation templates.
 */
#[Route('/admin/document-ui-templates')]
#[IsGranted('ROLE_ADMIN')]
class DocumentUITemplateController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentUITemplateService $uiTemplateService,
        private DocumentTypeRepository $documentTypeRepository,
    ) {}

    /**
     * List all document UI templates with statistics.
     */
    #[Route('/', name: 'admin_document_ui_template_index', methods: ['GET'])]
    public function index(DocumentUITemplateRepository $uiTemplateRepository): Response
    {
        $this->logger->info('Admin document UI templates list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_index',
        ]);

        try {
            $this->logger->debug('Starting to fetch templates with statistics');
            
            // Get templates with statistics
            $templatesWithStats = $this->uiTemplateService->getTemplatesWithStats();
            
            $this->logger->info('Successfully fetched document UI templates', [
                'templates_count' => count($templatesWithStats),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->logger->debug('Rendering document UI templates index view', [
                'template_path' => 'admin/document_ui_template/index.html.twig',
                'templates_count' => count($templatesWithStats),
            ]);

            return $this->render('admin/document_ui_template/index.html.twig', [
                'templates_with_stats' => $templatesWithStats,
                'page_title' => 'Modèles UI de documents',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => 'Modèles UI', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error accessing document UI templates list', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des modèles UI.');
            
            return $this->redirectToRoute('admin_dashboard');
        }
    }

    /**
     * List all UI components across all templates.
     */
    #[Route('/components', name: 'admin_document_ui_template_all_components', methods: ['GET'])]
    public function allComponents(DocumentUIComponentRepository $componentRepository): Response
    {
        $this->logger->info('Admin all document UI components list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_all_components',
        ]);

        try {
            $this->logger->debug('Starting to fetch all UI components with templates');
            
            // Get all components with their templates
            $components = $componentRepository->findAllWithTemplates();
            
            $this->logger->info('Successfully fetched all UI components', [
                'total_components' => count($components),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->logger->debug('Processing components grouping by template and zone');
            
            // Group components by template and zone
            $componentsByTemplate = [];
            foreach ($components as $component) {
                $template = $component->getUiTemplate();
                $templateId = $template->getId();

                if (!isset($componentsByTemplate[$templateId])) {
                    $componentsByTemplate[$templateId] = [
                        'template' => $template,
                        'components' => [],
                    ];
                }

                $componentsByTemplate[$templateId]['components'][$component->getZone()][] = $component;
            }

            $this->logger->debug('Successfully grouped components', [
                'templates_with_components' => count($componentsByTemplate),
                'total_components' => count($components),
            ]);

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
                    ['label' => 'Composants UI', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error accessing all document UI components', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des composants UI.');
            
            return $this->redirectToRoute('admin_document_ui_template_index');
        }
    }

    /**
     * Show UI template details.
     */
    #[Route('/{id}', name: 'admin_document_ui_template_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentUITemplate $uiTemplate): Response
    {
        $this->logger->info('Admin document UI template details viewed', [
            'template_id' => $uiTemplate->getId(),
            'template_name' => $uiTemplate->getName(),
            'template_slug' => $uiTemplate->getSlug(),
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_show',
        ]);

        try {
            $this->logger->debug('Fetching template components and statistics', [
                'template_id' => $uiTemplate->getId(),
                'template_name' => $uiTemplate->getName(),
            ]);

            // Get template components
            $components = $uiTemplate->getComponents();
            
            $this->logger->debug('Successfully fetched template components', [
                'template_id' => $uiTemplate->getId(),
                'components_count' => $components->count(),
            ]);

            // Get template statistics including recent usage
            $stats = [
                'usage_count' => $uiTemplate->getUsageCount(),
                'component_count' => $components->count(),
                'document_type' => $uiTemplate->getDocumentType()?->getName() ?? 'Global',
                'is_default' => $uiTemplate->isDefault(),
                'is_global' => $uiTemplate->isGlobal(),
                'page_format' => $uiTemplate->getPaperSize() . ' ' . $uiTemplate->getOrientation(),
                'validation_errors' => $uiTemplate->validateConfiguration(),
                'recent_usage' => [], // TODO: Implement recent usage tracking if needed
            ];

            $this->logger->info('Successfully prepared template statistics', [
                'template_id' => $uiTemplate->getId(),
                'stats' => [
                    'usage_count' => $stats['usage_count'],
                    'component_count' => $stats['component_count'],
                    'document_type' => $stats['document_type'],
                    'validation_errors_count' => count($stats['validation_errors']),
                ],
            ]);

            $this->logger->debug('Rendering template details view', [
                'template_path' => 'admin/document_ui_template/show.html.twig',
                'template_id' => $uiTemplate->getId(),
            ]);

            return $this->render('admin/document_ui_template/show.html.twig', [
                'ui_template' => $uiTemplate,
                'components' => $components,
                'stats' => $stats,
                'page_title' => 'Modèle UI: ' . $uiTemplate->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                    ['label' => $uiTemplate->getName(), 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error viewing document UI template details', [
                'template_id' => $uiTemplate->getId(),
                'template_name' => $uiTemplate->getName(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails du modèle UI.');
            
            return $this->redirectToRoute('admin_document_ui_template_index');
        }
    }

    /**
     * Create a new UI template.
     */
    #[Route('/new', name: 'admin_document_ui_template_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('Admin new document UI template creation accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'method' => $request->getMethod(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_new',
        ]);

        try {
            $uiTemplate = new DocumentUITemplate();

            $this->logger->debug('Created new DocumentUITemplate instance');

            // Set default values
            $uiTemplate->setIsActive(true);
            $uiTemplate->setUsageCount(0);
            
            $this->logger->debug('Getting next sort order for new template');
            $nextSortOrder = $this->uiTemplateService->getNextSortOrder();
            $uiTemplate->setSortOrder($nextSortOrder);
            
            $this->logger->debug('Set default values for new template', [
                'is_active' => true,
                'usage_count' => 0,
                'sort_order' => $nextSortOrder,
            ]);

            // Pre-select document type if provided
            $typeId = $request->query->get('type');
            if ($typeId) {
                $this->logger->debug('Document type ID provided in query', ['type_id' => $typeId]);
                
                $documentType = $this->uiTemplateService->getDocumentTypeById((int) $typeId);
                if ($documentType) {
                    $uiTemplate->setDocumentType($documentType);
                    $this->logger->debug('Pre-selected document type', [
                        'type_id' => $typeId,
                        'type_name' => $documentType->getName(),
                    ]);
                } else {
                    $this->logger->warning('Document type not found for provided ID', ['type_id' => $typeId]);
                }
            }

            $form = $this->createForm(DocumentUITemplateType::class, $uiTemplate);
            $this->logger->debug('Created DocumentUITemplateType form');

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('UI template creation form submitted', [
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'is_valid' => $form->isValid(),
                    'template_name' => $uiTemplate->getName(),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Form is valid, creating UI template', [
                        'template_name' => $uiTemplate->getName(),
                        'document_type' => $uiTemplate->getDocumentType()?->getName(),
                    ]);

                    $result = $this->uiTemplateService->createUITemplate($uiTemplate);

                    if ($result['success']) {
                        $this->logger->info('UI template created successfully', [
                            'template_id' => $uiTemplate->getId(),
                            'template_name' => $uiTemplate->getName(),
                            'user' => $this->getUser()?->getUserIdentifier(),
                        ]);

                        $this->addFlash('success', 'Le modèle UI a été créé avec succès.');

                        return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $uiTemplate->getId()]);
                    }
                    
                    $this->logger->error('Failed to create UI template', [
                        'error' => $result['error'],
                        'template_name' => $uiTemplate->getName(),
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                    
                    $this->addFlash('error', $result['error']);
                } else {
                    $this->logger->warning('UI template creation form validation failed', [
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                }
            }

            $this->logger->debug('Rendering new template form', [
                'template_path' => 'admin/document_ui_template/new.html.twig',
                'is_submitted' => $form->isSubmitted(),
            ]);

            return $this->render('admin/document_ui_template/new.html.twig', [
                'ui_template' => $uiTemplate,
                'form' => $form,
                'page_title' => 'Nouveau modèle UI',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                    ['label' => 'Nouveau', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error during UI template creation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'request_data' => $request->request->all(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du modèle UI.');
            
            return $this->redirectToRoute('admin_document_ui_template_index');
        }
    }

    /**
     * Edit an existing UI template.
     */
    #[Route('/{id}/edit', name: 'admin_document_ui_template_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        $this->logger->info('Admin edit document UI template accessed', [
            'template_id' => $uiTemplate->getId(),
            'template_name' => $uiTemplate->getName(),
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'method' => $request->getMethod(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_edit',
        ]);

        try {
            $originalData = [
                'name' => $uiTemplate->getName(),
                'description' => $uiTemplate->getDescription(),
                'is_active' => $uiTemplate->isActive(),
                'is_default' => $uiTemplate->isDefault(),
                'document_type' => $uiTemplate->getDocumentType()?->getId(),
            ];

            $this->logger->debug('Captured original template data for comparison', [
                'template_id' => $uiTemplate->getId(),
                'original_data' => $originalData,
            ]);

            $form = $this->createForm(DocumentUITemplateType::class, $uiTemplate);
            $this->logger->debug('Created DocumentUITemplateType form for editing');

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('UI template edit form submitted', [
                    'template_id' => $uiTemplate->getId(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'is_valid' => $form->isValid(),
                    'template_name' => $uiTemplate->getName(),
                ]);

                if ($form->isValid()) {
                    $newData = [
                        'name' => $uiTemplate->getName(),
                        'description' => $uiTemplate->getDescription(),
                        'is_active' => $uiTemplate->isActive(),
                        'is_default' => $uiTemplate->isDefault(),
                        'document_type' => $uiTemplate->getDocumentType()?->getId(),
                    ];

                    $this->logger->debug('Form is valid, updating UI template', [
                        'template_id' => $uiTemplate->getId(),
                        'original_data' => $originalData,
                        'new_data' => $newData,
                        'changes' => array_diff_assoc($newData, $originalData),
                    ]);

                    $result = $this->uiTemplateService->updateUITemplate($uiTemplate);

                    if ($result['success']) {
                        $this->logger->info('UI template updated successfully', [
                            'template_id' => $uiTemplate->getId(),
                            'template_name' => $uiTemplate->getName(),
                            'user' => $this->getUser()?->getUserIdentifier(),
                            'changes_made' => array_diff_assoc($newData, $originalData),
                        ]);

                        $this->addFlash('success', 'Le modèle UI a été modifié avec succès.');

                        return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $uiTemplate->getId()]);
                    }
                    
                    $this->logger->error('Failed to update UI template', [
                        'template_id' => $uiTemplate->getId(),
                        'error' => $result['error'],
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                    
                    $this->addFlash('error', $result['error']);
                } else {
                    $this->logger->warning('UI template edit form validation failed', [
                        'template_id' => $uiTemplate->getId(),
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                }
            }

            $this->logger->debug('Rendering edit template form', [
                'template_path' => 'admin/document_ui_template/edit.html.twig',
                'template_id' => $uiTemplate->getId(),
                'is_submitted' => $form->isSubmitted(),
            ]);

            return $this->render('admin/document_ui_template/edit.html.twig', [
                'ui_template' => $uiTemplate,
                'form' => $form,
                'page_title' => 'Modifier: ' . $uiTemplate->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                    ['label' => $uiTemplate->getName(), 'url' => $this->generateUrl('admin_document_ui_template_show', ['id' => $uiTemplate->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error during UI template editing', [
                'template_id' => $uiTemplate->getId(),
                'template_name' => $uiTemplate->getName(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'request_data' => $request->request->all(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du modèle UI.');
            
            return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $uiTemplate->getId()]);
        }
    }

    /**
     * Delete a UI template.
     */
    #[Route('/{id}', name: 'admin_document_ui_template_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();

        $this->logger->info('Admin delete document UI template requested', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_delete',
        ]);

        try {
            $this->logger->debug('Validating CSRF token for template deletion', [
                'template_id' => $templateId,
                'expected_token_id' => 'delete' . $templateId,
            ]);

            if ($this->isCsrfTokenValid('delete' . $templateId, $request->getPayload()->get('_token'))) {
                $this->logger->debug('CSRF token validation successful, proceeding with deletion', [
                    'template_id' => $templateId,
                ]);

                $this->logger->info('Attempting to delete UI template', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'usage_count' => $uiTemplate->getUsageCount(),
                    'is_default' => $uiTemplate->isDefault(),
                ]);

                $result = $this->uiTemplateService->deleteUITemplate($uiTemplate);

                if ($result['success']) {
                    $this->logger->info('UI template deleted successfully', [
                        'template_id' => $templateId,
                        'template_name' => $templateName,
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('success', 'Le modèle UI a été supprimé avec succès.');
                } else {
                    $this->logger->error('Failed to delete UI template', [
                        'template_id' => $templateId,
                        'template_name' => $templateName,
                        'error' => $result['error'],
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('CSRF token validation failed for template deletion', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'provided_token' => $request->getPayload()->get('_token'),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_document_ui_template_index');
        } catch (Exception $e) {
            $this->logger->error('Error during UI template deletion', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du modèle UI.');
            
            return $this->redirectToRoute('admin_document_ui_template_index');
        }
    }

    /**
     * Toggle UI template active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_document_ui_template_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();
        $currentStatus = $uiTemplate->isActive();

        $this->logger->info('Admin toggle UI template status requested', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'current_status' => $currentStatus,
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_toggle_status',
        ]);

        try {
            $this->logger->debug('Validating CSRF token for status toggle', [
                'template_id' => $templateId,
                'expected_token_id' => 'toggle' . $templateId,
            ]);

            if ($this->isCsrfTokenValid('toggle' . $templateId, $request->getPayload()->get('_token'))) {
                $this->logger->debug('CSRF token validation successful, proceeding with status toggle', [
                    'template_id' => $templateId,
                    'current_status' => $currentStatus,
                ]);

                $result = $this->uiTemplateService->toggleActiveStatus($uiTemplate);

                if ($result['success']) {
                    $newStatus = $uiTemplate->isActive();
                    
                    $this->logger->info('UI template status toggled successfully', [
                        'template_id' => $templateId,
                        'template_name' => $templateName,
                        'old_status' => $currentStatus,
                        'new_status' => $newStatus,
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('success', $result['message']);
                } else {
                    $this->logger->error('Failed to toggle UI template status', [
                        'template_id' => $templateId,
                        'template_name' => $templateName,
                        'current_status' => $currentStatus,
                        'error' => $result['error'],
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('CSRF token validation failed for status toggle', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'provided_token' => $request->getPayload()->get('_token'),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_document_ui_template_index');
        } catch (Exception $e) {
            $this->logger->error('Error during UI template status toggle', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'current_status' => $currentStatus,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du changement de statut.');
            
            return $this->redirectToRoute('admin_document_ui_template_index');
        }
    }

    /**
     * Duplicate a UI template.
     */
    #[Route('/{id}/duplicate', name: 'admin_document_ui_template_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();

        $this->logger->info('Admin duplicate UI template requested', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_duplicate',
        ]);

        try {
            $this->logger->debug('Validating CSRF token for template duplication', [
                'template_id' => $templateId,
                'expected_token_id' => 'duplicate' . $templateId,
            ]);

            if ($this->isCsrfTokenValid('duplicate' . $templateId, $request->getPayload()->get('_token'))) {
                $this->logger->debug('CSRF token validation successful, proceeding with duplication', [
                    'template_id' => $templateId,
                    'components_count' => $uiTemplate->getComponents()->count(),
                ]);

                $this->logger->info('Attempting to duplicate UI template', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'components_count' => $uiTemplate->getComponents()->count(),
                    'document_type' => $uiTemplate->getDocumentType()?->getName(),
                ]);

                $result = $this->uiTemplateService->duplicateUITemplate($uiTemplate);

                if ($result['success']) {
                    $duplicatedTemplate = $result['template'];
                    
                    $this->logger->info('UI template duplicated successfully', [
                        'original_template_id' => $templateId,
                        'original_template_name' => $templateName,
                        'duplicated_template_id' => $duplicatedTemplate->getId(),
                        'duplicated_template_name' => $duplicatedTemplate->getName(),
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('success', 'Le modèle UI a été dupliqué avec succès.');

                    return $this->redirectToRoute('admin_document_ui_template_edit', ['id' => $duplicatedTemplate->getId()]);
                }
                
                $this->logger->error('Failed to duplicate UI template', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'error' => $result['error'],
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
                
                $this->addFlash('error', $result['error']);
            } else {
                $this->logger->warning('CSRF token validation failed for template duplication', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'provided_token' => $request->getPayload()->get('_token'),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_document_ui_template_index');
        } catch (Exception $e) {
            $this->logger->error('Error during UI template duplication', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la duplication du modèle UI.');
            
            return $this->redirectToRoute('admin_document_ui_template_index');
        }
    }

    /**
     * Preview UI template.
     */
    #[Route('/{id}/preview', name: 'admin_document_ui_template_preview', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function preview(Request $request, DocumentUITemplate $uiTemplate): Response
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();

        $this->logger->info('Admin preview UI template requested', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'method' => $request->getMethod(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_preview',
        ]);

        try {
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

            $this->logger->debug('Prepared preview data for template rendering', [
                'template_id' => $templateId,
                'preview_data_keys' => array_keys($previewData),
                'is_post_request' => $request->isMethod('POST'),
                'custom_data_provided' => $request->isMethod('POST') && !empty($request->request->all()),
            ]);

            $this->logger->info('Attempting to render UI template preview', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'components_count' => $uiTemplate->getComponents()->count(),
            ]);

            $result = $this->uiTemplateService->renderTemplate($uiTemplate, $previewData);

            if (!$result['success']) {
                $this->logger->error('Failed to render UI template preview', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'error' => $result['error'],
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', $result['error']);

                return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $templateId]);
            }

            $this->logger->info('UI template preview rendered successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'rendered_components_count' => count($result['components']),
                'html_length' => strlen($result['html']),
                'css_length' => strlen($result['css']),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->logger->debug('Rendering preview template view', [
                'template_path' => 'admin/document_ui_template/preview.html.twig',
                'template_id' => $templateId,
                'preview_data_count' => count($previewData),
            ]);

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
                    ['label' => 'Aperçu', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error during UI template preview', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'preview_data' => $previewData ?? [],
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération de l\'aperçu.');
            
            return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $templateId]);
        }
    }

    /**
     * Export UI template configuration.
     */
    #[Route('/{id}/export', name: 'admin_document_ui_template_export', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function export(DocumentUITemplate $uiTemplate): Response
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();

        $this->logger->info('Admin export UI template requested', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_export',
        ]);

        try {
            $this->logger->debug('Starting template export process', [
                'template_id' => $templateId,
                'template_slug' => $uiTemplate->getSlug(),
                'components_count' => $uiTemplate->getComponents()->count(),
            ]);

            $config = $this->uiTemplateService->exportTemplate($uiTemplate);

            $this->logger->info('UI template configuration exported successfully', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'config_size' => strlen(json_encode($config)),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $filename = 'ui-template-' . $uiTemplate->getSlug() . '.json';
            
            $this->logger->debug('Preparing export response', [
                'template_id' => $templateId,
                'filename' => $filename,
                'config_keys' => array_keys($config),
            ]);

            $response = new JsonResponse($config);
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error during UI template export', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'export du modèle UI.');
            
            return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $templateId]);
        }
    }

    /**
     * Import UI template configuration.
     */
    #[Route('/import', name: 'admin_document_ui_template_import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        $this->logger->info('Admin import UI template accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'method' => $request->getMethod(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_import',
        ]);

        try {
            if ($request->isMethod('POST')) {
                $this->logger->debug('Processing UI template import form submission');

                $uploadedFile = $request->files->get('template_file');
                $documentTypeId = $request->request->get('document_type_id');

                $this->logger->debug('Import form data received', [
                    'has_file' => $uploadedFile !== null,
                    'file_name' => $uploadedFile?->getClientOriginalName(),
                    'file_size' => $uploadedFile?->getSize(),
                    'document_type_id' => $documentTypeId,
                ]);

                if (!$uploadedFile) {
                    $this->logger->warning('Import attempted without file upload', [
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('error', 'Veuillez sélectionner un fichier.');
                } else {
                    try {
                        $this->logger->debug('Reading uploaded file content', [
                            'file_path' => $uploadedFile->getPathname(),
                            'original_name' => $uploadedFile->getClientOriginalName(),
                        ]);

                        $config = json_decode(file_get_contents($uploadedFile->getPathname()), true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $this->logger->error('Invalid JSON file uploaded for import', [
                                'json_error' => json_last_error_msg(),
                                'file_name' => $uploadedFile->getClientOriginalName(),
                                'user' => $this->getUser()?->getUserIdentifier(),
                            ]);

                            $this->addFlash('error', 'Fichier JSON invalide.');
                        } else {
                            $this->logger->debug('Successfully parsed JSON configuration', [
                                'config_keys' => array_keys($config),
                                'file_name' => $uploadedFile->getClientOriginalName(),
                            ]);

                            $documentType = null;
                            if ($documentTypeId) {
                                $this->logger->debug('Looking up document type for import', [
                                    'document_type_id' => $documentTypeId,
                                ]);

                                $documentType = $this->uiTemplateService->getDocumentTypeById((int) $documentTypeId);
                                
                                if ($documentType) {
                                    $this->logger->debug('Document type found for import', [
                                        'document_type_id' => $documentTypeId,
                                        'document_type_name' => $documentType->getName(),
                                    ]);
                                } else {
                                    $this->logger->warning('Document type not found for import', [
                                        'document_type_id' => $documentTypeId,
                                    ]);
                                }
                            }

                            $this->logger->info('Attempting to import UI template', [
                                'file_name' => $uploadedFile->getClientOriginalName(),
                                'document_type' => $documentType?->getName(),
                                'user' => $this->getUser()?->getUserIdentifier(),
                            ]);

                            $result = $this->uiTemplateService->importTemplate($config, $documentType);

                            if ($result['success']) {
                                $importedTemplate = $result['template'];
                                
                                $this->logger->info('UI template imported successfully', [
                                    'imported_template_id' => $importedTemplate->getId(),
                                    'imported_template_name' => $importedTemplate->getName(),
                                    'file_name' => $uploadedFile->getClientOriginalName(),
                                    'user' => $this->getUser()?->getUserIdentifier(),
                                ]);

                                $this->addFlash('success', 'Modèle UI importé avec succès.');

                                return $this->redirectToRoute('admin_document_ui_template_edit', [
                                    'id' => $importedTemplate->getId(),
                                ]);
                            }
                            
                            $this->logger->error('Failed to import UI template', [
                                'error' => $result['error'],
                                'file_name' => $uploadedFile->getClientOriginalName(),
                                'user' => $this->getUser()?->getUserIdentifier(),
                            ]);
                            
                            $this->addFlash('error', $result['error']);
                        }
                    } catch (Exception $e) {
                        $this->logger->error('Exception during template import processing', [
                            'error_message' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'error_file' => $e->getFile(),
                            'error_line' => $e->getLine(),
                            'file_name' => $uploadedFile?->getClientOriginalName(),
                            'user' => $this->getUser()?->getUserIdentifier(),
                        ]);

                        $this->addFlash('error', 'Erreur lors de l\'importation: ' . $e->getMessage());
                    }
                }
            }

            $this->logger->debug('Fetching active document types for import form');
            $documentTypes = $this->documentTypeRepository->findAllActive();

            $this->logger->debug('Rendering import template form', [
                'template_path' => 'admin/document_ui_template/import.html.twig',
                'document_types_count' => count($documentTypes),
            ]);

            return $this->render('admin/document_ui_template/import.html.twig', [
                'document_types' => $documentTypes,
                'page_title' => 'Importer un modèle UI',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                    ['label' => 'Importer', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error during UI template import process', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'request_data' => $request->request->all(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'importation.');
            
            return $this->redirectToRoute('admin_document_ui_template_index');
        }
    }

    /**
     * Manage UI template components.
     */
    #[Route('/{id}/components', name: 'admin_document_ui_template_components', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function components(DocumentUITemplate $uiTemplate): Response
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();

        $this->logger->info('Admin manage UI template components accessed', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_components',
        ]);

        try {
            $this->logger->debug('Fetching template components', [
                'template_id' => $templateId,
            ]);

            $components = $uiTemplate->getComponents();

            $this->logger->debug('Successfully fetched template components', [
                'template_id' => $templateId,
                'total_components' => $components->count(),
            ]);

            // Group components by zone
            $componentsByZone = [];
            foreach ($components as $component) {
                $componentsByZone[$component->getZone()][] = $component;
            }

            $this->logger->info('Grouped components by zone for display', [
                'template_id' => $templateId,
                'total_components' => $components->count(),
                'zones_with_components' => array_keys($componentsByZone),
                'zone_counts' => array_map('count', $componentsByZone),
            ]);

            $this->logger->debug('Rendering components management view', [
                'template_path' => 'admin/document_ui_template/components.html.twig',
                'template_id' => $templateId,
                'zones_count' => count($componentsByZone),
            ]);

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
                    ['label' => 'Composants', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error accessing UI template components', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des composants.');
            
            return $this->redirectToRoute('admin_document_ui_template_show', ['id' => $templateId]);
        }
    }

    /**
     * Update component sort orders via AJAX.
     */
    #[Route('/{id}/components/sort', name: 'admin_document_ui_template_sort_components', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sortComponents(Request $request, DocumentUITemplate $uiTemplate): JsonResponse
    {
        $templateId = $uiTemplate->getId();
        $templateName = $uiTemplate->getName();

        $this->logger->info('Admin sort UI template components requested', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'user' => $this->getUser()?->getUserIdentifier(),
            'user_roles' => $this->getUser()?->getRoles(),
            'timestamp' => new \DateTimeImmutable(),
            'route' => 'admin_document_ui_template_sort_components',
        ]);

        try {
            $componentIds = $request->request->get('component_ids');

            $this->logger->debug('Component sort data received', [
                'template_id' => $templateId,
                'component_ids' => $componentIds,
                'component_ids_type' => gettype($componentIds),
                'component_ids_count' => is_array($componentIds) ? count($componentIds) : 0,
            ]);

            if (!is_array($componentIds)) {
                $this->logger->warning('Invalid component sort data provided', [
                    'template_id' => $templateId,
                    'provided_data' => $componentIds,
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                return new JsonResponse(['success' => false, 'error' => 'Invalid data'], 400);
            }

            $this->logger->info('Attempting to update component sort orders', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'components_to_sort' => count($componentIds),
                'component_ids' => $componentIds,
            ]);

            $result = $this->uiTemplateService->updateComponentSortOrders($uiTemplate, $componentIds);

            if ($result['success']) {
                $this->logger->info('Component sort orders updated successfully', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'components_sorted' => count($componentIds),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
            } else {
                $this->logger->error('Failed to update component sort orders', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'error' => $result['error'] ?? 'Unknown error',
                    'component_ids' => $componentIds,
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
            }

            return new JsonResponse($result);
        } catch (Exception $e) {
            $this->logger->error('Error during component sort operation', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user' => $this->getUser()?->getUserIdentifier(),
                'request_data' => $request->request->all(),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'Une erreur est survenue lors du tri des composants.',
            ], 500);
        }
    }
}
