<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentUITemplate;
use App\Form\Document\DocumentUIComponentType;
use App\Repository\Document\DocumentUIComponentRepository;
use App\Service\Document\DocumentUITemplateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document UI Component Controller.
 *
 * Handles CRUD operations for document UI components within templates.
 */
#[Route('/admin/document-ui-templates/{templateId}/components')]
#[IsGranted('ROLE_ADMIN')]
class DocumentUIComponentController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentUITemplateService $uiTemplateService,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * List components for a template.
     */
    #[Route('/', name: 'admin_document_ui_component_index', methods: ['GET'], requirements: ['templateId' => '\d+'])]
    public function index(int $templateId, DocumentUIComponentRepository $componentRepository): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Document UI components list accessed', [
            'template_id' => $templateId,
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'index',
            'controller' => 'DocumentUIComponentController',
        ]);

        try {
            $this->logger->debug('Fetching template entity', [
                'template_id' => $templateId,
                'user' => $userId,
            ]);

            $template = $this->entityManager->find(DocumentUITemplate::class, $templateId);

            if (!$template) {
                $this->logger->warning('Template not found', [
                    'template_id' => $templateId,
                    'user' => $userId,
                ]);
                
                throw $this->createNotFoundException('Template not found');
            }

            $this->logger->debug('Template found, fetching components', [
                'template_id' => $templateId,
                'template_name' => $template->getName(),
                'user' => $userId,
            ]);

            $components = $componentRepository->findByTemplate($template);

            $this->logger->debug('Components retrieved, grouping by zone', [
                'template_id' => $templateId,
                'user' => $userId,
                'total_components' => count($components),
            ]);

            // Group components by zone
            $componentsByZone = [];
            foreach ($components as $component) {
                $componentsByZone[$component->getZone()][] = $component;
            }

            $this->logger->info('Document UI components successfully grouped by zone', [
                'template_id' => $templateId,
                'template_name' => $template->getName(),
                'user' => $userId,
                'total_components' => count($components),
                'zones_with_components' => array_keys($componentsByZone),
                'components_per_zone' => array_map('count', $componentsByZone),
            ]);

            return $this->render('admin/document_ui_component/index.html.twig', [
                'template' => $template,
                'components' => $components,
                'components_by_zone' => $componentsByZone,
                'zones' => DocumentUITemplate::ZONES,
                'component_types' => DocumentUIComponent::TYPES,
                'page_title' => 'Composants: ' . $template->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                    ['label' => $template->getName(), 'url' => $this->generateUrl('admin_document_ui_template_show', ['id' => $template->getId()])],
                    ['label' => 'Composants', 'url' => null],
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving document UI components', [
                'template_id' => $templateId,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des composants.');
            
            return $this->redirectToRoute('admin_document_ui_template_index');
        }
    }

    /**
     * Show component details.
     */
    #[Route('/{id}', name: 'admin_document_ui_component_show', methods: ['GET'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function show(int $templateId, DocumentUIComponent $component): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $componentId = $component->getId();
        
        $this->logger->info('Document UI component details accessed', [
            'template_id' => $templateId,
            'component_id' => $componentId,
            'component_name' => $component->getName(),
            'component_type' => $component->getType(),
            'component_zone' => $component->getZone(),
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'show',
        ]);

        try {
            $template = $component->getUiTemplate();

            $this->logger->debug('Validating component-template relationship', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'template_exists' => $template !== null,
                'template_matches' => $template && $template->getId() === $templateId,
            ]);

            if (!$template || $template->getId() !== $templateId) {
                $this->logger->warning('Component not found in specified template', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'user' => $userId,
                    'actual_template_id' => $template?->getId(),
                ]);
                
                throw $this->createNotFoundException('Component not found in this template');
            }

            $this->logger->info('Document UI component details successfully retrieved', [
                'template_id' => $templateId,
                'template_name' => $template->getName(),
                'component_id' => $componentId,
                'component_name' => $component->getName(),
                'component_details' => [
                    'type' => $component->getType(),
                    'zone' => $component->getZone(),
                    'sort_order' => $component->getSortOrder(),
                    'is_active' => $component->isActive(),
                    'created_at' => $component->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updated_at' => $component->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ],
                'user' => $userId,
            ]);

            return $this->render('admin/document_ui_component/show.html.twig', [
                'template' => $template,
                'component' => $component,
                'page_title' => 'Composant: ' . $component->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                    ['label' => $template->getName(), 'url' => $this->generateUrl('admin_document_ui_template_show', ['id' => $template->getId()])],
                    ['label' => 'Composants', 'url' => $this->generateUrl('admin_document_ui_component_index', ['templateId' => $template->getId()])],
                    ['label' => $component->getName(), 'url' => null],
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error displaying document UI component details', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails du composant.');
            
            return $this->redirectToRoute('admin_document_ui_component_index', ['templateId' => $templateId]);
        }
    }

    /**
     * Create new component.
     */
    #[Route('/new', name: 'admin_document_ui_component_new', methods: ['GET', 'POST'], requirements: ['templateId' => '\d+'])]
    public function new(Request $request, int $templateId, DocumentUIComponentRepository $componentRepository): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Starting new document UI component creation', [
            'template_id' => $templateId,
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'new',
            'method' => $request->getMethod(),
        ]);

        try {
            $this->logger->debug('Fetching template for new component', [
                'template_id' => $templateId,
                'user' => $userId,
            ]);

            $template = $this->entityManager->find(DocumentUITemplate::class, $templateId);

            if (!$template) {
                $this->logger->warning('Template not found for new component', [
                    'template_id' => $templateId,
                    'user' => $userId,
                ]);
                
                throw $this->createNotFoundException('Template not found');
            }

            $this->logger->debug('Creating new component instance with defaults', [
                'template_id' => $templateId,
                'template_name' => $template->getName(),
                'user' => $userId,
            ]);

            $component = new DocumentUIComponent();
            $component->setUiTemplate($template);
            
            $nextSortOrder = $componentRepository->getNextSortOrder($template);
            $component->setSortOrder($nextSortOrder);

            // Pre-fill zone if provided
            $zone = $request->query->get('zone');
            if ($zone && in_array($zone, array_keys(DocumentUITemplate::ZONES), true)) {
                $component->setZone($zone);
                
                $this->logger->debug('Pre-filled zone from query parameter', [
                    'template_id' => $templateId,
                    'user' => $userId,
                    'zone' => $zone,
                ]);
            }

            $this->logger->debug('Component defaults set', [
                'template_id' => $templateId,
                'user' => $userId,
                'sort_order' => $nextSortOrder,
                'pre_filled_zone' => $zone,
            ]);

            $form = $this->createForm(DocumentUIComponentType::class, $component);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Component creation form submitted', [
                    'template_id' => $templateId,
                    'user' => $userId,
                    'form_valid' => $form->isValid(),
                    'form_errors' => $form->isValid() ? [] : (string) $form->getErrors(true),
                    'submitted_data' => [
                        'name' => $component->getName(),
                        'type' => $component->getType(),
                        'zone' => $component->getZone(),
                        'sort_order' => $component->getSortOrder(),
                    ],
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Calling UI template service to add component', [
                        'template_id' => $templateId,
                        'user' => $userId,
                        'component_data' => [
                            'name' => $component->getName(),
                            'type' => $component->getType(),
                            'zone' => $component->getZone(),
                            'sort_order' => $component->getSortOrder(),
                            'html_content' => $component->getHtmlContent(),
                        ],
                    ]);

                    $result = $this->uiTemplateService->addComponent($template, $component);

                    if ($result['success']) {
                        $componentId = $component->getId();
                        
                        $this->logger->info('Document UI component created successfully', [
                            'template_id' => $templateId,
                            'component_id' => $componentId,
                            'component_name' => $component->getName(),
                            'component_type' => $component->getType(),
                            'component_zone' => $component->getZone(),
                            'user' => $userId,
                        ]);

                        $this->addFlash('success', 'Le composant a été créé avec succès.');

                        return $this->redirectToRoute('admin_document_ui_component_show', [
                            'templateId' => $template->getId(),
                            'id' => $componentId,
                        ]);
                    }
                    
                    $this->logger->warning('Component creation failed via UI template service', [
                        'template_id' => $templateId,
                        'user' => $userId,
                        'error' => $result['error'],
                        'component_data' => [
                            'name' => $component->getName(),
                            'type' => $component->getType(),
                            'zone' => $component->getZone(),
                        ],
                    ]);
                    
                    $this->addFlash('error', $result['error']);
                }
            }

            return $this->render('admin/document_ui_component/new.html.twig', [
                'template' => $template,
                'component' => $component,
                'form' => $form,
                'zones' => DocumentUITemplate::ZONES,
                'component_types' => DocumentUIComponent::TYPES,
                'page_title' => 'Nouveau composant',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                    ['label' => $template->getName(), 'url' => $this->generateUrl('admin_document_ui_template_show', ['id' => $template->getId()])],
                    ['label' => 'Composants', 'url' => $this->generateUrl('admin_document_ui_component_index', ['templateId' => $template->getId()])],
                    ['label' => 'Nouveau', 'url' => null],
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in document UI component creation process', [
                'template_id' => $templateId,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du composant.');
            
            return $this->redirectToRoute('admin_document_ui_component_index', ['templateId' => $templateId]);
        }
    }

    /**
     * Edit component.
     */
    #[Route('/{id}/edit', name: 'admin_document_ui_component_edit', methods: ['GET', 'POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function edit(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $componentId = $component->getId();
        
        $this->logger->info('Starting document UI component edit', [
            'template_id' => $templateId,
            'component_id' => $componentId,
            'component_name' => $component->getName(),
            'component_type' => $component->getType(),
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'edit',
            'method' => $request->getMethod(),
        ]);

        try {
            $template = $component->getUiTemplate();

            $this->logger->debug('Validating component-template relationship for edit', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'template_exists' => $template !== null,
                'template_matches' => $template && $template->getId() === $templateId,
            ]);

            if (!$template || $template->getId() !== $templateId) {
                $this->logger->warning('Component not found in specified template for edit', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'user' => $userId,
                    'actual_template_id' => $template?->getId(),
                ]);
                
                throw $this->createNotFoundException('Component not found in this template');
            }

            $originalData = [
                'name' => $component->getName(),
                'type' => $component->getType(),
                'zone' => $component->getZone(),
                'sort_order' => $component->getSortOrder(),
                'is_active' => $component->isActive(),
                'html_content' => $component->getHtmlContent(),
            ];

            $this->logger->debug('Original component data captured for edit', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'original_data' => $originalData,
            ]);

            $form = $this->createForm(DocumentUIComponentType::class, $component);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Component edit form submitted', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'user' => $userId,
                    'form_valid' => $form->isValid(),
                    'form_errors' => $form->isValid() ? [] : (string) $form->getErrors(true),
                    'updated_data' => [
                        'name' => $component->getName(),
                        'type' => $component->getType(),
                        'zone' => $component->getZone(),
                        'sort_order' => $component->getSortOrder(),
                        'is_active' => $component->isActive(),
                    ],
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Updating component with new data', [
                        'template_id' => $templateId,
                        'component_id' => $componentId,
                        'user' => $userId,
                        'changes_detected' => $originalData !== [
                            'name' => $component->getName(),
                            'type' => $component->getType(),
                            'zone' => $component->getZone(),
                            'sort_order' => $component->getSortOrder(),
                            'is_active' => $component->isActive(),
                            'html_content' => $component->getHtmlContent(),
                        ],
                    ]);

                    $component->setUpdatedAt(new DateTimeImmutable());
                    $this->entityManager->flush();

                    $this->logger->info('Document UI component updated successfully', [
                        'template_id' => $templateId,
                        'component_id' => $componentId,
                        'component_name' => $component->getName(),
                        'user' => $userId,
                        'original_data' => $originalData,
                        'updated_data' => [
                            'name' => $component->getName(),
                            'type' => $component->getType(),
                            'zone' => $component->getZone(),
                            'sort_order' => $component->getSortOrder(),
                            'is_active' => $component->isActive(),
                        ],
                    ]);

                    $this->addFlash('success', 'Le composant a été modifié avec succès.');

                    return $this->redirectToRoute('admin_document_ui_component_show', [
                        'templateId' => $template->getId(),
                        'id' => $component->getId(),
                    ]);
                }
            }

            return $this->render('admin/document_ui_component/edit.html.twig', [
                'template' => $template,
                'component' => $component,
                'form' => $form,
                'zones' => DocumentUITemplate::ZONES,
                'component_types' => DocumentUIComponent::TYPES,
                'page_title' => 'Modifier: ' . $component->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                    ['label' => $template->getName(), 'url' => $this->generateUrl('admin_document_ui_template_show', ['id' => $template->getId()])],
                    ['label' => 'Composants', 'url' => $this->generateUrl('admin_document_ui_component_index', ['templateId' => $template->getId()])],
                    ['label' => $component->getName(), 'url' => $this->generateUrl('admin_document_ui_component_show', ['templateId' => $template->getId(), 'id' => $component->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in document UI component edit process', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du composant.');
            
            return $this->redirectToRoute('admin_document_ui_component_show', [
                'templateId' => $templateId,
                'id' => $componentId,
            ]);
        }
    }

    /**
     * Delete component.
     */
    #[Route('/{id}', name: 'admin_document_ui_component_delete', methods: ['POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function delete(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $componentId = $component->getId();
        $componentName = $component->getName();
        
        $this->logger->info('Document UI component deletion attempt', [
            'template_id' => $templateId,
            'component_id' => $componentId,
            'component_name' => $componentName,
            'component_type' => $component->getType(),
            'component_zone' => $component->getZone(),
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'delete',
        ]);

        try {
            $template = $component->getUiTemplate();

            $this->logger->debug('Validating component-template relationship for deletion', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'template_exists' => $template !== null,
                'template_matches' => $template && $template->getId() === $templateId,
            ]);

            if (!$template || $template->getId() !== $templateId) {
                $this->logger->warning('Component not found in specified template for deletion', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'user' => $userId,
                    'actual_template_id' => $template?->getId(),
                ]);
                
                throw $this->createNotFoundException('Component not found in this template');
            }

            $token = $request->getPayload()->get('_token');
            $expectedToken = 'delete' . $componentId;
            
            $this->logger->debug('CSRF token validation for component deletion', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'delete' . $componentId,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $token)) {
                $this->logger->debug('CSRF token valid, proceeding with component deletion', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'component_name' => $componentName,
                    'user' => $userId,
                    'component_details' => [
                        'type' => $component->getType(),
                        'zone' => $component->getZone(),
                        'sort_order' => $component->getSortOrder(),
                        'is_active' => $component->isActive(),
                    ],
                ]);

                $this->entityManager->remove($component);
                $this->entityManager->flush();

                $this->logger->info('Document UI component deleted successfully', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'component_name' => $componentName,
                    'user' => $userId,
                ]);

                $this->addFlash('success', "Le composant \"{$componentName}\" a été supprimé avec succès.");
            } else {
                $this->logger->warning('Invalid CSRF token for component deletion', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'user' => $userId,
                    'token_provided' => !empty($token),
                ]);
                
                $this->addFlash('error', 'Token de sécurité invalide.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error during document UI component deletion', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'component_name' => $componentName,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de la suppression du composant.');
        }

        return $this->redirectToRoute('admin_document_ui_component_index', ['templateId' => $templateId]);
    }

    /**
     * Toggle component active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_document_ui_component_toggle_status', methods: ['POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function toggleStatus(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $componentId = $component->getId();
        $componentName = $component->getName();
        $currentStatus = $component->isActive();
        
        $this->logger->info('Document UI component status toggle attempt', [
            'template_id' => $templateId,
            'component_id' => $componentId,
            'component_name' => $componentName,
            'current_status' => $currentStatus,
            'target_status' => !$currentStatus,
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'toggle_status',
        ]);

        try {
            $template = $component->getUiTemplate();

            $this->logger->debug('Validating component-template relationship for status toggle', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'template_exists' => $template !== null,
                'template_matches' => $template && $template->getId() === $templateId,
            ]);

            if (!$template || $template->getId() !== $templateId) {
                $this->logger->warning('Component not found in specified template for status toggle', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'user' => $userId,
                    'actual_template_id' => $template?->getId(),
                ]);
                
                throw $this->createNotFoundException('Component not found in this template');
            }

            $token = $request->getPayload()->get('_token');
            $expectedToken = 'toggle' . $componentId;
            
            $this->logger->debug('CSRF token validation for component status toggle', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'toggle' . $componentId,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $token)) {
                $this->logger->debug('CSRF token valid, proceeding with component status toggle', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'user' => $userId,
                    'current_status' => $currentStatus,
                ]);

                $newStatus = !$currentStatus;
                $component->setIsActive($newStatus);
                $component->setUpdatedAt(new DateTimeImmutable());
                $this->entityManager->flush();

                $statusText = $newStatus ? 'activé' : 'désactivé';
                
                $this->logger->info('Document UI component status toggled successfully', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'component_name' => $componentName,
                    'previous_status' => $currentStatus,
                    'new_status' => $newStatus,
                    'status_text' => $statusText,
                    'user' => $userId,
                ]);

                $this->addFlash('success', "Le composant a été {$statusText} avec succès.");
            } else {
                $this->logger->warning('Invalid CSRF token for component status toggle', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'user' => $userId,
                    'token_provided' => !empty($token),
                ]);
                
                $this->addFlash('error', 'Token de sécurité invalide.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error during document UI component status toggle', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'component_name' => $componentName,
                'user' => $userId,
                'current_status' => $currentStatus,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors du changement de statut du composant.');
        }

        return $this->redirectToRoute('admin_document_ui_component_index', ['templateId' => $templateId]);
    }

    /**
     * Duplicate component.
     */
    #[Route('/{id}/duplicate', name: 'admin_document_ui_component_duplicate', methods: ['POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function duplicate(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $sourceComponentId = $component->getId();
        $sourceComponentName = $component->getName();
        
        $this->logger->info('Document UI component duplication attempt', [
            'template_id' => $templateId,
            'source_component_id' => $sourceComponentId,
            'source_component_name' => $sourceComponentName,
            'source_component_type' => $component->getType(),
            'source_component_zone' => $component->getZone(),
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'duplicate',
        ]);

        try {
            $template = $component->getUiTemplate();

            $this->logger->debug('Validating component-template relationship for duplication', [
                'template_id' => $templateId,
                'source_component_id' => $sourceComponentId,
                'user' => $userId,
                'template_exists' => $template !== null,
                'template_matches' => $template && $template->getId() === $templateId,
            ]);

            if (!$template || $template->getId() !== $templateId) {
                $this->logger->warning('Component not found in specified template for duplication', [
                    'template_id' => $templateId,
                    'source_component_id' => $sourceComponentId,
                    'user' => $userId,
                    'actual_template_id' => $template?->getId(),
                ]);
                
                throw $this->createNotFoundException('Component not found in this template');
            }

            $token = $request->getPayload()->get('_token');
            $expectedToken = 'duplicate' . $sourceComponentId;
            
            $this->logger->debug('CSRF token validation for component duplication', [
                'template_id' => $templateId,
                'source_component_id' => $sourceComponentId,
                'user' => $userId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'duplicate' . $sourceComponentId,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $token)) {
                $this->logger->debug('CSRF token valid, cloning component', [
                    'template_id' => $templateId,
                    'source_component_id' => $sourceComponentId,
                    'user' => $userId,
                    'source_properties' => [
                        'name' => $sourceComponentName,
                        'type' => $component->getType(),
                        'zone' => $component->getZone(),
                        'sort_order' => $component->getSortOrder(),
                        'is_active' => $component->isActive(),
                    ],
                ]);

                $clonedComponent = $component->cloneComponent();
                $clonedComponent->setUiTemplate($template);

                $this->logger->debug('Cloned component configured', [
                    'template_id' => $templateId,
                    'source_component_id' => $sourceComponentId,
                    'user' => $userId,
                    'cloned_data' => [
                        'name' => $clonedComponent->getName(),
                        'type' => $clonedComponent->getType(),
                        'zone' => $clonedComponent->getZone(),
                        'sort_order' => $clonedComponent->getSortOrder(),
                        'is_active' => $clonedComponent->isActive(),
                    ],
                ]);

                $this->entityManager->persist($clonedComponent);
                $this->entityManager->flush();

                $clonedComponentId = $clonedComponent->getId();

                $this->logger->info('Document UI component duplicated successfully', [
                    'template_id' => $templateId,
                    'source_component_id' => $sourceComponentId,
                    'source_component_name' => $sourceComponentName,
                    'cloned_component_id' => $clonedComponentId,
                    'cloned_component_name' => $clonedComponent->getName(),
                    'user' => $userId,
                ]);

                $this->addFlash('success', 'Le composant a été dupliqué avec succès.');

                return $this->redirectToRoute('admin_document_ui_component_edit', [
                    'templateId' => $templateId,
                    'id' => $clonedComponentId,
                ]);
            } else {
                $this->logger->warning('Invalid CSRF token for component duplication', [
                    'template_id' => $templateId,
                    'source_component_id' => $sourceComponentId,
                    'user' => $userId,
                    'token_provided' => !empty($token),
                ]);
                
                $this->addFlash('error', 'Token de sécurité invalide.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error during document UI component duplication', [
                'template_id' => $templateId,
                'source_component_id' => $sourceComponentId,
                'source_component_name' => $sourceComponentName,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur lors de la duplication du composant.');
        }

        return $this->redirectToRoute('admin_document_ui_component_index', ['templateId' => $templateId]);
    }

    /**
     * Preview component.
     */
    #[Route('/{id}/preview', name: 'admin_document_ui_component_preview', methods: ['GET', 'POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function preview(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $componentId = $component->getId();
        
        $this->logger->info('Document UI component preview accessed', [
            'template_id' => $templateId,
            'component_id' => $componentId,
            'component_name' => $component->getName(),
            'component_type' => $component->getType(),
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'preview',
            'method' => $request->getMethod(),
        ]);

        try {
            $template = $component->getUiTemplate();

            $this->logger->debug('Validating component-template relationship for preview', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'template_exists' => $template !== null,
                'template_matches' => $template && $template->getId() === $templateId,
            ]);

            if (!$template || $template->getId() !== $templateId) {
                $this->logger->warning('Component not found in specified template for preview', [
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'user' => $userId,
                    'actual_template_id' => $template?->getId(),
                ]);
                
                throw $this->createNotFoundException('Component not found in this template');
            }

            // Get preview data from request or use defaults
            $previewData = $request->request->all() ?: [
                'title' => 'Titre de démonstration',
                'content' => 'Contenu de démonstration',
                'author' => 'Nom de l\'auteur',
                'date' => date('d/m/Y'),
                'organization' => 'EPROFOS',
                'image_src' => '/images/logo.png',
                'signature_name' => 'Jean Dupont',
                'signature_title' => 'Directeur',
            ];

            $this->logger->debug('Preparing component preview with data', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'user' => $userId,
                'preview_data_provided' => !empty($request->request->all()),
                'preview_data_keys' => array_keys($previewData),
            ]);

            $renderedHtml = $component->renderHtml($previewData);

            $this->logger->info('Document UI component preview rendered successfully', [
                'template_id' => $templateId,
                'template_name' => $template->getName(),
                'component_id' => $componentId,
                'component_name' => $component->getName(),
                'component_type' => $component->getType(),
                'user' => $userId,
                'preview_data_used' => $previewData,
                'rendered_html_length' => strlen($renderedHtml),
            ]);

            return $this->render('admin/document_ui_component/preview.html.twig', [
                'template' => $template,
                'component' => $component,
                'rendered_html' => $renderedHtml,
                'preview_data' => $previewData,
                'page_title' => 'Aperçu: ' . $component->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Modèles UI', 'url' => $this->generateUrl('admin_document_ui_template_index')],
                    ['label' => $template->getName(), 'url' => $this->generateUrl('admin_document_ui_template_show', ['id' => $template->getId()])],
                    ['label' => 'Composants', 'url' => $this->generateUrl('admin_document_ui_component_index', ['templateId' => $template->getId()])],
                    ['label' => $component->getName(), 'url' => $this->generateUrl('admin_document_ui_component_show', ['templateId' => $template->getId(), 'id' => $component->getId()])],
                    ['label' => 'Aperçu', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error during document UI component preview', [
                'template_id' => $templateId,
                'component_id' => $componentId,
                'component_name' => $component->getName(),
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération de l\'aperçu du composant.');
            
            return $this->redirectToRoute('admin_document_ui_component_show', [
                'templateId' => $templateId,
                'id' => $componentId,
            ]);
        }
    }

}
