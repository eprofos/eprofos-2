<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentTemplate;
use App\Form\Document\DocumentTemplateType;
use App\Repository\Document\DocumentTemplateRepository;
use App\Service\Document\DocumentTemplateService;
use Exception;
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
#[Route('/admin/document-templates')]
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
    #[Route('/', name: 'admin_document_template_index', methods: ['GET'])]
    public function index(DocumentTemplateRepository $documentTemplateRepository): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin document templates list access initiated', [
            'user' => $userId,
        ]);

        try {
            $this->logger->debug('Fetching templates with statistics', ['user' => $userId]);

            // Get templates with statistics
            $templatesWithStats = $this->documentTemplateService->getTemplatesWithStats();

            $this->logger->info('Document templates with statistics retrieved successfully', [
                'templates_count' => count($templatesWithStats),
                'user' => $userId,
            ]);

            $this->logger->info('Admin document templates list rendered successfully', [
                'templates_count' => count($templatesWithStats),
                'user' => $userId,
            ]);

            return $this->render('admin/document_template/index.html.twig', [
                'templates_with_stats' => $templatesWithStats,
                'page_title' => 'Modèles de documents',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => 'Modèles de documents', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading document templates list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des modèles de documents.');

            return $this->render('admin/document_template/index.html.twig', [
                'templates_with_stats' => [],
                'page_title' => 'Modèles de documents',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Gestion documentaire', 'url' => $this->generateUrl('admin_document_index')],
                    ['label' => 'Modèles de documents', 'url' => null],
                ],
            ]);
        }
    }

    /**
     * Show document template details.
     */
    #[Route('/{id}', name: 'admin_document_template_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentTemplate $documentTemplate): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $templateId = $documentTemplate->getId();

        $this->logger->info('Admin document template details view initiated', [
            'template_id' => $templateId,
            'template_name' => $documentTemplate->getName(),
            'user' => $userId,
        ]);

        try {
            $this->logger->debug('Calculating template statistics', [
                'template_id' => $templateId,
                'user' => $userId,
            ]);

            // Get template statistics
            $stats = [
                'usage_count' => $documentTemplate->getUsageCount(),
                'document_type' => $documentTemplate->getDocumentType()?->getName(),
                'placeholders_count' => count($documentTemplate->getPlaceholders() ?? []),
                'is_default' => $documentTemplate->isDefault(),
            ];

            $this->logger->info('Document template statistics calculated', [
                'template_id' => $templateId,
                'stats' => $stats,
                'user' => $userId,
            ]);

            $this->logger->info('Admin document template details rendered successfully', [
                'template_id' => $templateId,
                'template_name' => $documentTemplate->getName(),
                'user' => $userId,
            ]);

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
        } catch (Exception $e) {
            $this->logger->error('Error rendering document template details', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage des détails du modèle.');

            return $this->redirectToRoute('admin_document_template_index');
        }
    }

    /**
     * Create a new document template.
     */
    #[Route('/new', name: 'admin_document_template_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $typeId = $request->query->get('type');

        $this->logger->info('Admin document template creation initiated', [
            'user' => $userId,
            'type_id' => $typeId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $documentTemplate = new DocumentTemplate();

            // Set default values
            $documentTemplate->setIsActive(true);
            $documentTemplate->setUsageCount(0);

            $this->logger->debug('Getting next sort order for template', ['user' => $userId]);
            $sortOrder = $this->documentTemplateService->getNextSortOrder();
            $documentTemplate->setSortOrder($sortOrder);

            $this->logger->info('Default values set for new template', [
                'sort_order' => $sortOrder,
                'user' => $userId,
            ]);

            // Pre-select document type if provided
            if ($typeId) {
                $this->logger->debug('Pre-selecting document type for new template', [
                    'type_id' => $typeId,
                    'user' => $userId,
                ]);

                $documentType = $this->documentTemplateService->getDocumentTypeById((int) $typeId);
                if ($documentType) {
                    $documentTemplate->setDocumentType($documentType);
                    $this->logger->info('Document type pre-selected for template creation', [
                        'type_id' => $typeId,
                        'type_name' => $documentType->getName(),
                        'user' => $userId,
                    ]);
                } else {
                    $this->logger->warning('Document type not found for pre-selection', [
                        'type_id' => $typeId,
                        'user' => $userId,
                    ]);
                }
            }

            $form = $this->createForm(DocumentTemplateType::class, $documentTemplate);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('Document template form submitted and valid', [
                    'template_name' => $documentTemplate->getName(),
                    'template_description' => $documentTemplate->getDescription(),
                    'is_default' => $documentTemplate->isDefault(),
                    'user' => $userId,
                ]);

                $result = $this->documentTemplateService->createDocumentTemplate($documentTemplate);

                if ($result['success']) {
                    $this->logger->info('Document template created successfully', [
                        'template_id' => $documentTemplate->getId(),
                        'template_name' => $documentTemplate->getName(),
                        'user' => $userId,
                    ]);

                    $this->addFlash('success', 'Le modèle de document a été créé avec succès.');

                    return $this->redirectToRoute('admin_document_template_show', ['id' => $documentTemplate->getId()]);
                }

                $this->logger->error('Failed to create document template', [
                    'error' => $result['error'],
                    'template_name' => $documentTemplate->getName(),
                    'user' => $userId,
                ]);
                $this->addFlash('error', $result['error']);
            } elseif ($form->isSubmitted()) {
                $this->logger->warning('Document template form submitted but invalid', [
                    'form_errors' => (string) $form->getErrors(true),
                    'user' => $userId,
                ]);
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
        } catch (Exception $e) {
            $this->logger->error('Error during document template creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
                'type_id' => $typeId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du modèle de document.');

            return $this->redirectToRoute('admin_document_template_index');
        }
    }

    /**
     * Edit an existing document template.
     */
    #[Route('/{id}/edit', name: 'admin_document_template_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentTemplate $documentTemplate): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $templateId = $documentTemplate->getId();

        $this->logger->info('Admin document template edit initiated', [
            'template_id' => $templateId,
            'template_name' => $documentTemplate->getName(),
            'user' => $userId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $originalName = $documentTemplate->getName();
            $originalDescription = $documentTemplate->getDescription();
            $originalIsActive = $documentTemplate->isActive();
            $originalIsDefault = $documentTemplate->isDefault();

            $form = $this->createForm(DocumentTemplateType::class, $documentTemplate);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('Document template edit form submitted and valid', [
                    'template_id' => $templateId,
                    'original_name' => $originalName,
                    'new_name' => $documentTemplate->getName(),
                    'original_description' => $originalDescription,
                    'new_description' => $documentTemplate->getDescription(),
                    'original_is_active' => $originalIsActive,
                    'new_is_active' => $documentTemplate->isActive(),
                    'original_is_default' => $originalIsDefault,
                    'new_is_default' => $documentTemplate->isDefault(),
                    'user' => $userId,
                ]);

                $result = $this->documentTemplateService->updateDocumentTemplate($documentTemplate);

                if ($result['success']) {
                    $this->logger->info('Document template updated successfully', [
                        'template_id' => $templateId,
                        'template_name' => $documentTemplate->getName(),
                        'changes' => [
                            'name_changed' => $originalName !== $documentTemplate->getName(),
                            'description_changed' => $originalDescription !== $documentTemplate->getDescription(),
                            'active_changed' => $originalIsActive !== $documentTemplate->isActive(),
                            'default_changed' => $originalIsDefault !== $documentTemplate->isDefault(),
                        ],
                        'user' => $userId,
                    ]);

                    $this->addFlash('success', 'Le modèle de document a été modifié avec succès.');

                    return $this->redirectToRoute('admin_document_template_show', ['id' => $documentTemplate->getId()]);
                }

                $this->logger->error('Failed to update document template', [
                    'template_id' => $templateId,
                    'error' => $result['error'],
                    'user' => $userId,
                ]);
                $this->addFlash('error', $result['error']);
            } elseif ($form->isSubmitted()) {
                $this->logger->warning('Document template edit form submitted but invalid', [
                    'template_id' => $templateId,
                    'form_errors' => (string) $form->getErrors(true),
                    'user' => $userId,
                ]);
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
        } catch (Exception $e) {
            $this->logger->error('Error during document template edit', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du modèle de document.');

            return $this->redirectToRoute('admin_document_template_show', ['id' => $templateId]);
        }
    }

    /**
     * Delete a document template.
     */
    #[Route('/{id}', name: 'admin_document_template_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentTemplate $documentTemplate): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $templateId = $documentTemplate->getId();
        $templateName = $documentTemplate->getName();

        $this->logger->info('Admin document template deletion initiated', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'usage_count' => $documentTemplate->getUsageCount(),
            'user' => $userId,
            'ip' => $request->getClientIp(),
        ]);

        try {
            $token = $request->getPayload()->get('_token');
            $this->logger->debug('Validating CSRF token for template deletion', [
                'template_id' => $templateId,
                'user' => $userId,
            ]);

            if ($this->isCsrfTokenValid('delete' . $templateId, $token)) {
                $this->logger->info('CSRF token validated, proceeding with template deletion', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'user' => $userId,
                ]);

                $result = $this->documentTemplateService->deleteDocumentTemplate($documentTemplate);

                if ($result['success']) {
                    $this->logger->info('Document template deleted successfully', [
                        'template_id' => $templateId,
                        'template_name' => $templateName,
                        'user' => $userId,
                    ]);

                    $this->addFlash('success', 'Le modèle de document a été supprimé avec succès.');
                } else {
                    $this->logger->error('Failed to delete document template', [
                        'template_id' => $templateId,
                        'error' => $result['error'],
                        'user' => $userId,
                    ]);

                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for template deletion', [
                    'template_id' => $templateId,
                    'user' => $userId,
                    'ip' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error during document template deletion', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du modèle de document.');
        }

        return $this->redirectToRoute('admin_document_template_index');
    }

    /**
     * Toggle document template active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_document_template_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, DocumentTemplate $documentTemplate): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $templateId = $documentTemplate->getId();
        $templateName = $documentTemplate->getName();
        $currentStatus = $documentTemplate->isActive();

        $this->logger->info('Admin document template status toggle initiated', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'current_status' => $currentStatus,
            'user' => $userId,
            'ip' => $request->getClientIp(),
        ]);

        try {
            $token = $request->getPayload()->get('_token');
            $this->logger->debug('Validating CSRF token for template status toggle', [
                'template_id' => $templateId,
                'user' => $userId,
            ]);

            if ($this->isCsrfTokenValid('toggle' . $templateId, $token)) {
                $this->logger->info('CSRF token validated, proceeding with status toggle', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'current_status' => $currentStatus,
                    'user' => $userId,
                ]);

                $result = $this->documentTemplateService->toggleActiveStatus($documentTemplate);

                if ($result['success']) {
                    $this->logger->info('Document template status toggled successfully', [
                        'template_id' => $templateId,
                        'template_name' => $templateName,
                        'old_status' => $currentStatus,
                        'new_status' => !$currentStatus,
                        'message' => $result['message'],
                        'user' => $userId,
                    ]);

                    $this->addFlash('success', $result['message']);
                } else {
                    $this->logger->error('Failed to toggle document template status', [
                        'template_id' => $templateId,
                        'error' => $result['error'],
                        'user' => $userId,
                    ]);

                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for template status toggle', [
                    'template_id' => $templateId,
                    'user' => $userId,
                    'ip' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error during document template status toggle', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du changement de statut.');
        }

        return $this->redirectToRoute('admin_document_template_index');
    }

    /**
     * Duplicate a document template.
     */
    #[Route('/{id}/duplicate', name: 'admin_document_template_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, DocumentTemplate $documentTemplate): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $templateId = $documentTemplate->getId();
        $templateName = $documentTemplate->getName();

        $this->logger->info('Admin document template duplication initiated', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'user' => $userId,
            'ip' => $request->getClientIp(),
        ]);

        try {
            $token = $request->getPayload()->get('_token');
            $this->logger->debug('Validating CSRF token for template duplication', [
                'template_id' => $templateId,
                'user' => $userId,
            ]);

            if ($this->isCsrfTokenValid('duplicate' . $templateId, $token)) {
                $this->logger->info('CSRF token validated, proceeding with template duplication', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'user' => $userId,
                ]);

                $result = $this->documentTemplateService->duplicateDocumentTemplate($documentTemplate);

                if ($result['success']) {
                    $newTemplate = $result['template'];
                    $this->logger->info('Document template duplicated successfully', [
                        'original_template_id' => $templateId,
                        'new_template_id' => $newTemplate->getId(),
                        'original_name' => $templateName,
                        'new_name' => $newTemplate->getName(),
                        'user' => $userId,
                    ]);

                    $this->addFlash('success', 'Le modèle de document a été dupliqué avec succès.');

                    return $this->redirectToRoute('admin_document_template_edit', ['id' => $newTemplate->getId()]);
                }

                $this->logger->error('Failed to duplicate document template', [
                    'template_id' => $templateId,
                    'error' => $result['error'],
                    'user' => $userId,
                ]);
                $this->addFlash('error', $result['error']);
            } else {
                $this->logger->warning('Invalid CSRF token for template duplication', [
                    'template_id' => $templateId,
                    'user' => $userId,
                    'ip' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error during document template duplication', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la duplication du modèle.');
        }

        return $this->redirectToRoute('admin_document_template_index');
    }

    /**
     * Create document from template.
     */
    #[Route('/{id}/create-document', name: 'admin_document_template_create_document', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function createDocument(Request $request, DocumentTemplate $documentTemplate): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $templateId = $documentTemplate->getId();
        $templateName = $documentTemplate->getName();

        $this->logger->info('Admin document creation from template initiated', [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'user' => $userId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $requestData = $request->request->all();
            $this->logger->debug('Creating document from template with request data', [
                'template_id' => $templateId,
                'request_data_keys' => array_keys($requestData),
                'user' => $userId,
            ]);

            $result = $this->documentTemplateService->createDocumentFromTemplate($documentTemplate, $requestData);

            if ($result['success']) {
                $newDocument = $result['document'];
                $this->logger->info('Document created successfully from template', [
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'new_document_id' => $newDocument->getId(),
                    'new_document_title' => $newDocument->getTitle(),
                    'user' => $userId,
                ]);

                $this->addFlash('success', 'Document créé avec succès à partir du modèle.');

                return $this->redirectToRoute('admin_document_edit', ['id' => $newDocument->getId()]);
            }

            $this->logger->error('Failed to create document from template', [
                'template_id' => $templateId,
                'error' => $result['error'],
                'user' => $userId,
            ]);
            $this->addFlash('error', $result['error']);
        } catch (Exception $e) {
            $this->logger->error('Error during document creation from template', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du document à partir du modèle.');
        }

        return $this->redirectToRoute('admin_document_template_show', ['id' => $templateId]);
    }
}
