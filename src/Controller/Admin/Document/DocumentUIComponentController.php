<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentUITemplate;
use App\Form\DocumentUIComponentType;
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
#[Route('/admin/document-ui-templates/{templateId}/components', name: 'admin_document_ui_component_')]
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
    #[Route('/', name: 'index', methods: ['GET'], requirements: ['templateId' => '\d+'])]
    public function index(int $templateId, DocumentUIComponentRepository $componentRepository): Response
    {
        $template = $this->entityManager->find(DocumentUITemplate::class, $templateId);

        if (!$template) {
            throw $this->createNotFoundException('Template not found');
        }

        $components = $componentRepository->findByTemplate($template);

        // Group components by zone
        $componentsByZone = [];
        foreach ($components as $component) {
            $componentsByZone[$component->getZone()][] = $component;
        }

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
    }

    /**
     * Show component details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function show(int $templateId, DocumentUIComponent $component): Response
    {
        $template = $component->getUiTemplate();

        if (!$template || $template->getId() !== $templateId) {
            throw $this->createNotFoundException('Component not found in this template');
        }

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
    }

    /**
     * Create new component.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'], requirements: ['templateId' => '\d+'])]
    public function new(Request $request, int $templateId, DocumentUIComponentRepository $componentRepository): Response
    {
        $template = $this->entityManager->find(DocumentUITemplate::class, $templateId);

        if (!$template) {
            throw $this->createNotFoundException('Template not found');
        }

        $component = new DocumentUIComponent();
        $component->setUiTemplate($template);
        $component->setSortOrder($componentRepository->getNextSortOrder($template));

        // Pre-fill zone if provided
        $zone = $request->query->get('zone');
        if ($zone && in_array($zone, array_keys(DocumentUITemplate::ZONES), true)) {
            $component->setZone($zone);
        }

        $form = $this->createForm(DocumentUIComponentType::class, $component);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->uiTemplateService->addComponent($template, $component);

            if ($result['success']) {
                $this->addFlash('success', 'Le composant a été créé avec succès.');

                return $this->redirectToRoute('admin_document_ui_component_show', [
                    'templateId' => $template->getId(),
                    'id' => $component->getId(),
                ]);
            }
            $this->addFlash('error', $result['error']);
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
    }

    /**
     * Edit component.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function edit(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $template = $component->getUiTemplate();

        if (!$template || $template->getId() !== $templateId) {
            throw $this->createNotFoundException('Component not found in this template');
        }

        $form = $this->createForm(DocumentUIComponentType::class, $component);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $component->setUpdatedAt(new DateTimeImmutable());
                $this->entityManager->flush();

                $this->addFlash('success', 'Le composant a été modifié avec succès.');

                return $this->redirectToRoute('admin_document_ui_component_show', [
                    'templateId' => $template->getId(),
                    'id' => $component->getId(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du composant.');
                $this->logger->error('Error updating UI component', [
                    'component_id' => $component->getId(),
                    'error' => $e->getMessage(),
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
    }

    /**
     * Delete component.
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function delete(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $template = $component->getUiTemplate();

        if (!$template || $template->getId() !== $templateId) {
            throw $this->createNotFoundException('Component not found in this template');
        }

        if ($this->isCsrfTokenValid('delete' . $component->getId(), $request->getPayload()->get('_token'))) {
            try {
                $componentName = $component->getName();
                $this->entityManager->remove($component);
                $this->entityManager->flush();

                $this->addFlash('success', "Le composant \"{$componentName}\" a été supprimé avec succès.");
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du composant.');
                $this->logger->error('Error deleting UI component', [
                    'component_id' => $component->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->redirectToRoute('admin_document_ui_component_index', ['templateId' => $templateId]);
    }

    /**
     * Toggle component active status.
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function toggleStatus(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $template = $component->getUiTemplate();

        if (!$template || $template->getId() !== $templateId) {
            throw $this->createNotFoundException('Component not found in this template');
        }

        if ($this->isCsrfTokenValid('toggle' . $component->getId(), $request->getPayload()->get('_token'))) {
            try {
                $newStatus = !$component->isActive();
                $component->setIsActive($newStatus);
                $component->setUpdatedAt(new DateTimeImmutable());
                $this->entityManager->flush();

                $statusText = $newStatus ? 'activé' : 'désactivé';
                $this->addFlash('success', "Le composant a été {$statusText} avec succès.");
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors du changement de statut du composant.');
                $this->logger->error('Error toggling UI component status', [
                    'component_id' => $component->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->redirectToRoute('admin_document_ui_component_index', ['templateId' => $templateId]);
    }

    /**
     * Duplicate component.
     */
    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function duplicate(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $template = $component->getUiTemplate();

        if (!$template || $template->getId() !== $templateId) {
            throw $this->createNotFoundException('Component not found in this template');
        }

        if ($this->isCsrfTokenValid('duplicate' . $component->getId(), $request->getPayload()->get('_token'))) {
            try {
                $clonedComponent = $component->cloneComponent();
                $clonedComponent->setUiTemplate($template);

                $this->entityManager->persist($clonedComponent);
                $this->entityManager->flush();

                $this->addFlash('success', 'Le composant a été dupliqué avec succès.');

                return $this->redirectToRoute('admin_document_ui_component_edit', [
                    'templateId' => $templateId,
                    'id' => $clonedComponent->getId(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la duplication du composant.');
                $this->logger->error('Error duplicating UI component', [
                    'component_id' => $component->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->redirectToRoute('admin_document_ui_component_index', ['templateId' => $templateId]);
    }

    /**
     * Preview component.
     */
    #[Route('/{id}/preview', name: 'preview', methods: ['GET', 'POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function preview(Request $request, int $templateId, DocumentUIComponent $component): Response
    {
        $template = $component->getUiTemplate();

        if (!$template || $template->getId() !== $templateId) {
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

        $renderedHtml = $component->renderHtml($previewData);

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
    }

    /**
     * Update component sort order via AJAX.
     */
    #[Route('/sort', name: 'sort', methods: ['POST'], requirements: ['templateId' => '\d+'])]
    public function sort(Request $request, int $templateId): JsonResponse
    {
        $template = $this->entityManager->find(DocumentUITemplate::class, $templateId);

        if (!$template) {
            return new JsonResponse(['success' => false, 'error' => 'Template not found'], 404);
        }

        $componentIds = $request->request->get('component_ids');

        if (!is_array($componentIds)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid data'], 400);
        }

        $result = $this->uiTemplateService->updateComponentSortOrders($template, $componentIds);

        return new JsonResponse($result);
    }

    /**
     * Get component configuration via AJAX.
     */
    #[Route('/{id}/config', name: 'config', methods: ['GET'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function getConfig(int $templateId, DocumentUIComponent $component): JsonResponse
    {
        $template = $component->getUiTemplate();

        if (!$template || $template->getId() !== $templateId) {
            return new JsonResponse(['error' => 'Component not found'], 404);
        }

        return new JsonResponse([
            'style_config' => $component->getStyleConfig(),
            'position_config' => $component->getPositionConfig(),
            'data_binding' => $component->getDataBinding(),
            'conditional_display' => $component->getConditionalDisplay(),
        ]);
    }

    /**
     * Update component configuration via AJAX.
     */
    #[Route('/{id}/config', name: 'update_config', methods: ['POST'], requirements: ['templateId' => '\d+', 'id' => '\d+'])]
    public function updateConfig(Request $request, int $templateId, DocumentUIComponent $component): JsonResponse
    {
        $template = $component->getUiTemplate();

        if (!$template || $template->getId() !== $templateId) {
            return new JsonResponse(['error' => 'Component not found'], 404);
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['style_config'])) {
                $component->setStyleConfig($data['style_config']);
            }

            if (isset($data['position_config'])) {
                $component->setPositionConfig($data['position_config']);
            }

            if (isset($data['data_binding'])) {
                $component->setDataBinding($data['data_binding']);
            }

            if (isset($data['conditional_display'])) {
                $component->setConditionalDisplay($data['conditional_display']);
            }

            $component->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Error updating component config', [
                'component_id' => $component->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour de la configuration',
            ], 500);
        }
    }
}
