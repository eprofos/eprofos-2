<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Form\Training\ModuleType;
use App\Repository\Training\FormationRepository;
use App\Repository\Training\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/modules')]
#[IsGranted('ROLE_ADMIN')]
class ModuleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ModuleRepository $moduleRepository,
        private FormationRepository $formationRepository,
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_modules_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('Module index page accessed', [
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
        ]);

        try {
            $formationId = $request->query->get('formation');
            $formation = null;

            $this->logger->debug('Processing module index request', [
                'formation_id' => $formationId,
                'has_formation_filter' => !empty($formationId),
            ]);

            if ($formationId) {
                $this->logger->debug('Searching for formation', ['formation_id' => $formationId]);
                
                $formation = $this->formationRepository->find($formationId);
                if (!$formation) {
                    $this->logger->warning('Formation not found for module filtering', [
                        'formation_id' => $formationId,
                        'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    ]);
                    throw $this->createNotFoundException('Formation not found');
                }

                $this->logger->info('Formation found, filtering modules', [
                    'formation_id' => $formationId,
                    'formation_title' => $formation->getTitle(),
                ]);

                $modules = $this->moduleRepository->findByFormationOrdered($formationId);
                
                $this->logger->debug('Modules filtered by formation', [
                    'formation_id' => $formationId,
                    'modules_count' => count($modules),
                ]);
            } else {
                $this->logger->debug('No formation filter, loading all modules');
                $modules = $this->moduleRepository->findBy([], ['orderIndex' => 'ASC']);
                
                $this->logger->debug('All modules loaded', [
                    'modules_count' => count($modules),
                ]);
            }

            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);
            
            $this->logger->info('Module index data prepared successfully', [
                'modules_count' => count($modules),
                'formations_count' => count($formations),
                'selected_formation_id' => $formation?->getId(),
            ]);

            return $this->render('admin/modules/index.html.twig', [
                'modules' => $modules,
                'formations' => $formations,
                'selectedFormation' => $formation,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in module index', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'formation_id' => $formationId ?? null,
                'user_identifier' => $this->getUser()?->getUserIdentifier(),
            ]);

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                throw $e;
            }

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des modules.');
            
            // Return minimal safe view on database errors
            return $this->render('admin/modules/index.html.twig', [
                'modules' => [],
                'formations' => [],
                'selectedFormation' => null,
            ]);
        }
    }

    #[Route('/new', name: 'admin_modules_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('Module creation form accessed', [
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod(),
            'request_data' => $request->request->all(),
        ]);

        try {
            $module = new Module();
            
            $this->logger->debug('New module entity created', [
                'module_class' => get_class($module),
            ]);

            $form = $this->createForm(ModuleType::class, $module);
            $form->handleRequest($request);

            $this->logger->debug('Module form created and request handled', [
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() && $form->isValid(),
            ]);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('Module form submitted with valid data', [
                    'module_title' => $module->getTitle(),
                    'formation_id' => $module->getFormation()?->getId(),
                    'formation_title' => $module->getFormation()?->getTitle(),
                ]);

                // Auto-generate slug if not provided
                if (!$module->getSlug()) {
                    $slug = $this->slugger->slug($module->getTitle())->lower()->toString();
                    $module->setSlug($slug);
                    
                    $this->logger->debug('Auto-generated slug for module', [
                        'original_title' => $module->getTitle(),
                        'generated_slug' => $slug,
                    ]);
                }

                // Set order index if not provided
                if (!$module->getOrderIndex()) {
                    $nextOrder = $this->moduleRepository->getNextOrderIndex($module->getFormation()->getId());
                    $module->setOrderIndex($nextOrder);
                    
                    $this->logger->debug('Auto-assigned order index for module', [
                        'formation_id' => $module->getFormation()->getId(),
                        'assigned_order' => $nextOrder,
                    ]);
                }

                $this->logger->debug('Persisting new module to database', [
                    'module_title' => $module->getTitle(),
                    'module_slug' => $module->getSlug(),
                    'order_index' => $module->getOrderIndex(),
                ]);

                $this->entityManager->persist($module);
                $this->entityManager->flush();

                $this->logger->info('Module created successfully', [
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle(),
                    'formation_id' => $module->getFormation()->getId(),
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Module créé avec succès.');

                return $this->redirectToRoute('admin_modules_index', [
                    'formation' => $module->getFormation()->getId(),
                ]);
            }

            $this->logger->debug('Rendering module creation form', [
                'form_errors' => $form->getErrors(true)->count(),
                'has_form_errors' => !$form->isValid() && $form->isSubmitted(),
            ]);

            return $this->render('admin/modules/new.html.twig', [
                'module' => $module,
                'form' => $form,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in module creation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_identifier' => $this->getUser()?->getUserIdentifier(),
                'request_method' => $request->getMethod(),
                'form_submitted' => isset($form) && $form->isSubmitted(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du module.');
            
            // Return form with safe defaults on error
            $module = new Module();
            $form = $this->createForm(ModuleType::class, $module);
            
            return $this->render('admin/modules/new.html.twig', [
                'module' => $module,
                'form' => $form,
            ]);
        }
    }

    #[Route('/{id}', name: 'admin_modules_show', methods: ['GET'])]
    public function show(Module $module): Response
    {
        $this->logger->info('Module show page accessed', [
            'module_id' => $module->getId(),
            'module_title' => $module->getTitle(),
            'formation_id' => $module->getFormation()?->getId(),
            'formation_title' => $module->getFormation()?->getTitle(),
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('Preparing module show data', [
                'module_id' => $module->getId(),
                'module_slug' => $module->getSlug(),
                'module_active' => $module->isActive(),
                'order_index' => $module->getOrderIndex(),
                'chapters_count' => $module->getChapters()->count(),
            ]);

            return $this->render('admin/modules/show.html.twig', [
                'module' => $module,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in module show', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'module_id' => $module->getId(),
                'user_identifier' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage du module.');
            
            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $module->getFormation()?->getId(),
            ]);
        }
    }

    #[Route('/{id}/edit', name: 'admin_modules_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Module $module): Response
    {
        $this->logger->info('Module edit form accessed', [
            'module_id' => $module->getId(),
            'module_title' => $module->getTitle(),
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod(),
        ]);

        try {
            // Store original values for comparison
            $originalTitle = $module->getTitle();
            $originalSlug = $module->getSlug();
            $originalActive = $module->isActive();

            $form = $this->createForm(ModuleType::class, $module);
            $form->handleRequest($request);

            $this->logger->debug('Module edit form created and request handled', [
                'module_id' => $module->getId(),
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() && $form->isValid(),
                'original_title' => $originalTitle,
            ]);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('Module edit form submitted with valid data', [
                    'module_id' => $module->getId(),
                    'title_changed' => $originalTitle !== $module->getTitle(),
                    'slug_changed' => $originalSlug !== $module->getSlug(),
                    'active_changed' => $originalActive !== $module->isActive(),
                    'new_title' => $module->getTitle(),
                    'new_slug' => $module->getSlug(),
                ]);

                $this->logger->debug('Flushing module changes to database', [
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle(),
                ]);

                $this->entityManager->flush();

                $this->logger->info('Module updated successfully', [
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle(),
                    'formation_id' => $module->getFormation()->getId(),
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Module modifié avec succès.');

                return $this->redirectToRoute('admin_modules_index', [
                    'formation' => $module->getFormation()->getId(),
                ]);
            }

            $this->logger->debug('Rendering module edit form', [
                'module_id' => $module->getId(),
                'form_errors' => $form->getErrors(true)->count(),
                'has_form_errors' => !$form->isValid() && $form->isSubmitted(),
            ]);

            return $this->render('admin/modules/edit.html.twig', [
                'module' => $module,
                'form' => $form,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in module edit', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'module_id' => $module->getId(),
                'user_identifier' => $this->getUser()?->getUserIdentifier(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du module.');
            
            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $module->getFormation()->getId(),
            ]);
        }
    }

    #[Route('/{id}/delete', name: 'admin_modules_delete', methods: ['POST'])]
    public function delete(Request $request, Module $module): Response
    {
        $moduleId = $module->getId();
        $moduleTitle = $module->getTitle();
        $formationId = $module->getFormation()->getId();
        $formationTitle = $module->getFormation()->getTitle();

        $this->logger->info('Module deletion attempt', [
            'module_id' => $moduleId,
            'module_title' => $moduleTitle,
            'formation_id' => $formationId,
            'formation_title' => $formationTitle,
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'csrf_token' => $request->request->get('_token'),
        ]);

        try {
            $this->logger->debug('Validating CSRF token for module deletion', [
                'module_id' => $moduleId,
                'token_name' => 'delete' . $moduleId,
            ]);

            if ($this->isCsrfTokenValid('delete' . $moduleId, $request->request->get('_token'))) {
                $this->logger->info('CSRF token valid, proceeding with module deletion', [
                    'module_id' => $moduleId,
                    'module_title' => $moduleTitle,
                    'chapters_count' => $module->getChapters()->count(),
                ]);

                // Log associated data before deletion
                $chaptersCount = $module->getChapters()->count();
                $this->logger->warning('Deleting module and associated data', [
                    'module_id' => $moduleId,
                    'module_title' => $moduleTitle,
                    'chapters_to_delete' => $chaptersCount,
                    'formation_id' => $formationId,
                ]);

                $this->entityManager->remove($module);
                $this->entityManager->flush();

                $this->logger->info('Module deleted successfully', [
                    'deleted_module_id' => $moduleId,
                    'deleted_module_title' => $moduleTitle,
                    'formation_id' => $formationId,
                    'deleted_chapters_count' => $chaptersCount,
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Module supprimé avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for module deletion', [
                    'module_id' => $moduleId,
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'provided_token' => $request->request->get('_token'),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $formationId,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in module deletion', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'module_id' => $moduleId,
                'module_title' => $moduleTitle,
                'formation_id' => $formationId,
                'user_identifier' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du module.');
            
            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $formationId,
            ]);
        }
    }

    #[Route('/{id}/toggle-active', name: 'admin_modules_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, Module $module): Response
    {
        $moduleId = $module->getId();
        $moduleTitle = $module->getTitle();
        $currentStatus = $module->isActive();

        $this->logger->info('Module toggle active attempt', [
            'module_id' => $moduleId,
            'module_title' => $moduleTitle,
            'current_status' => $currentStatus,
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'csrf_token' => $request->request->get('_token'),
        ]);

        try {
            $this->logger->debug('Validating CSRF token for module toggle', [
                'module_id' => $moduleId,
                'token_name' => 'toggle' . $moduleId,
            ]);

            if ($this->isCsrfTokenValid('toggle' . $moduleId, $request->request->get('_token'))) {
                $newStatus = !$currentStatus;
                
                $this->logger->info('CSRF token valid, toggling module status', [
                    'module_id' => $moduleId,
                    'module_title' => $moduleTitle,
                    'old_status' => $currentStatus,
                    'new_status' => $newStatus,
                ]);

                $module->setIsActive($newStatus);
                $this->entityManager->flush();

                $status = $newStatus ? 'activé' : 'désactivé';
                
                $this->logger->info('Module status toggled successfully', [
                    'module_id' => $moduleId,
                    'module_title' => $moduleTitle,
                    'final_status' => $newStatus,
                    'status_text' => $status,
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', "Module {$status} avec succès.");
            } else {
                $this->logger->warning('Invalid CSRF token for module toggle', [
                    'module_id' => $moduleId,
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                    'provided_token' => $request->request->get('_token'),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $module->getFormation()->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in module toggle active', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'module_id' => $moduleId,
                'module_title' => $moduleTitle,
                'current_status' => $currentStatus,
                'user_identifier' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du statut du module.');
            
            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $module->getFormation()->getId(),
            ]);
        }
    }

    #[Route('/reorder', name: 'admin_modules_reorder', methods: ['POST'])]
    public function reorder(Request $request): Response
    {
        $this->logger->info('Module reorder attempt', [
            'user_identifier' => $this->getUser()?->getUserIdentifier(),
            'request_data' => $request->request->all(),
        ]);

        try {
            $moduleIds = $request->request->all('modules');
            $formationId = $request->request->get('formation_id');

            $this->logger->debug('Processing module reorder request', [
                'formation_id' => $formationId,
                'module_ids' => $moduleIds,
                'modules_count' => count($moduleIds),
            ]);

            if (!empty($moduleIds)) {
                $this->logger->info('Updating module order indexes', [
                    'formation_id' => $formationId,
                    'modules_count' => count($moduleIds),
                    'new_order' => $moduleIds,
                ]);

                $this->moduleRepository->updateOrderIndexes($moduleIds);
                
                $this->logger->info('Module order updated successfully', [
                    'formation_id' => $formationId,
                    'updated_modules_count' => count($moduleIds),
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Ordre des modules mis à jour avec succès.');
            } else {
                $this->logger->warning('No module IDs provided for reordering', [
                    'formation_id' => $formationId,
                    'user_identifier' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('warning', 'Aucun module à réorganiser.');
            }

            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $formationId,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in module reorder', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'formation_id' => $request->request->get('formation_id'),
                'module_ids' => $request->request->all('modules'),
                'user_identifier' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la réorganisation des modules.');
            
            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $request->request->get('formation_id'),
            ]);
        }
    }
}
