<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Chapter;
use App\Entity\Training\Module;
use App\Form\Training\ChapterType;
use App\Repository\Training\ChapterRepository;
use App\Repository\Training\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/chapters')]
#[IsGranted('ROLE_ADMIN')]
class ChapterController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChapterRepository $chapterRepository,
        private ModuleRepository $moduleRepository,
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_chapters_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('Chapter listing requested', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'query_params' => $request->query->all(),
            'client_ip' => $request->getClientIp(),
        ]);

        try {
            $moduleId = $request->query->get('module');
            $formationId = $request->query->get('formation');
            $search = $request->query->get('search');
            $active = $request->query->get('active');

            $filters = [];

            if ($moduleId) {
                $filters['module'] = $moduleId;
                $this->logger->debug('Filter by module applied', ['module_id' => $moduleId]);
            }

            if ($formationId) {
                $filters['formation'] = $formationId;
                $this->logger->debug('Filter by formation applied', ['formation_id' => $formationId]);
            }

            if ($search) {
                $filters['search'] = $search;
                $this->logger->debug('Search filter applied', ['search_term' => $search]);
            }

            if ($active !== null) {
                $filters['active'] = $active === '1';
                $this->logger->debug('Active status filter applied', ['active' => $active === '1']);
            }

            $this->logger->debug('Fetching chapters with filters', ['filters' => $filters]);
            $chapters = $this->chapterRepository->findWithFilters($filters);
            $this->logger->info('Chapters fetched successfully', ['chapter_count' => count($chapters)]);

            // Get module for context if specified
            $module = null;
            if ($moduleId) {
                $this->logger->debug('Fetching module for context', ['module_id' => $moduleId]);
                $module = $this->moduleRepository->find($moduleId);
                if (!$module) {
                    $this->logger->warning('Module not found for context', ['module_id' => $moduleId]);

                    throw $this->createNotFoundException('Module not found');
                }
                $this->logger->debug('Module found for context', [
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle(),
                ]);
            }

            // Get all modules for filter dropdown
            $this->logger->debug('Fetching all modules for filter dropdown');
            $modules = $this->moduleRepository->findBy([], ['orderIndex' => 'ASC']);
            $this->logger->debug('Modules fetched for filter dropdown', ['module_count' => count($modules)]);

            $this->logger->info('Chapter index page rendered successfully', [
                'chapter_count' => count($chapters),
                'module_count' => count($modules),
                'selected_module_id' => $module?->getId(),
                'applied_filters' => $filters,
            ]);

            return $this->render('admin/chapters/index.html.twig', [
                'chapters' => $chapters,
                'modules' => $modules,
                'selectedModule' => $module,
                'filters' => [
                    'module' => $moduleId,
                    'formation' => $formationId,
                    'search' => $search,
                    'active' => $active,
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapter index action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'query_params' => $request->query->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des chapitres.');

            // Try to render a minimal version or redirect
            try {
                $modules = $this->moduleRepository->findBy([], ['orderIndex' => 'ASC']);

                return $this->render('admin/chapters/index.html.twig', [
                    'chapters' => [],
                    'modules' => $modules,
                    'selectedModule' => null,
                    'filters' => [],
                ]);
            } catch (Exception $fallbackException) {
                $this->logger->critical('Fallback rendering failed in chapter index', [
                    'original_error' => $e->getMessage(),
                    'fallback_error' => $fallbackException->getMessage(),
                ]);

                throw $e; // Re-throw original exception
            }
        }
    }

    #[Route('/new', name: 'admin_chapters_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('Chapter creation form requested', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
            'module_id' => $request->query->get('module'),
            'client_ip' => $request->getClientIp(),
        ]);

        try {
            $chapter = new Chapter();
            $this->logger->debug('New chapter entity created');

            // Pre-select module if provided
            $moduleId = $request->query->get('module');
            if ($moduleId) {
                $this->logger->debug('Pre-selecting module from query parameter', ['module_id' => $moduleId]);
                $module = $this->moduleRepository->find($moduleId);
                if ($module) {
                    $chapter->setModule($module);
                    $this->logger->debug('Module pre-selected successfully', [
                        'module_id' => $module->getId(),
                        'module_title' => $module->getTitle(),
                    ]);
                } else {
                    $this->logger->warning('Module not found for pre-selection', ['module_id' => $moduleId]);
                }
            }

            $form = $this->createForm(ChapterType::class, $chapter);
            $this->logger->debug('Chapter form created');

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Chapter form submitted', [
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'chapter_title' => $chapter->getTitle(),
                    'module_id' => $chapter->getModule()?->getId(),
                    'is_valid' => $form->isValid(),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Chapter form validation passed');

                    // Auto-generate slug if not provided
                    if (!$chapter->getSlug()) {
                        $slug = $this->slugger->slug($chapter->getTitle())->lower()->toString();
                        $chapter->setSlug($slug);
                        $this->logger->debug('Auto-generated chapter slug', ['slug' => $slug]);
                    }

                    // Set order index if not provided
                    if (!$chapter->getOrderIndex()) {
                        $nextOrder = $this->chapterRepository->getNextOrderIndex($chapter->getModule()->getId());
                        $chapter->setOrderIndex($nextOrder);
                        $this->logger->debug('Auto-generated order index', [
                            'order_index' => $nextOrder,
                            'module_id' => $chapter->getModule()->getId(),
                        ]);
                    }

                    $this->logger->info('Persisting new chapter', [
                        'chapter_title' => $chapter->getTitle(),
                        'chapter_slug' => $chapter->getSlug(),
                        'module_id' => $chapter->getModule()->getId(),
                        'order_index' => $chapter->getOrderIndex(),
                        'duration_minutes' => $chapter->getDurationMinutes(),
                        'is_active' => $chapter->isActive(),
                    ]);

                    $this->entityManager->persist($chapter);
                    $this->entityManager->flush();

                    $this->logger->info('Chapter created successfully', [
                        'chapter_id' => $chapter->getId(),
                        'chapter_title' => $chapter->getTitle(),
                        'user_id' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('success', 'Chapitre créé avec succès.');

                    return $this->redirectToRoute('admin_chapters_index', [
                        'module' => $chapter->getModule()->getId(),
                    ]);
                }
                $this->logger->warning('Chapter form validation failed', [
                    'form_errors' => (string) $form->getErrors(true),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);
            }

            $this->logger->debug('Rendering chapter creation form');

            return $this->render('admin/chapters/new.html.twig', [
                'chapter' => $chapter,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapter creation action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'module_id' => $request->query->get('module'),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du chapitre.');

            return $this->redirectToRoute('admin_chapters_index');
        }
    }

    #[Route('/statistics', name: 'admin_chapters_statistics', methods: ['GET'])]
    public function statistics(): Response
    {
        $this->logger->info('Chapter statistics requested', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('Calculating total chapters count');
            $totalChapters = $this->chapterRepository->count([]);
            $this->logger->debug('Total chapters counted', ['total' => $totalChapters]);

            $this->logger->debug('Calculating active chapters count');
            $activeChapters = $this->chapterRepository->count(['isActive' => true]);
            $this->logger->debug('Active chapters counted', ['active' => $activeChapters]);

            $inactiveChapters = $totalChapters - $activeChapters;
            $this->logger->debug('Inactive chapters calculated', ['inactive' => $inactiveChapters]);

            // Get chapters by module
            $this->logger->debug('Fetching chapters grouped by module');
            $chaptersByModule = $this->entityManager
                ->createQuery('
                    SELECT m.title as moduleTitle, COUNT(c.id) as chapterCount
                    FROM App\Entity\Training\Chapter c
                    JOIN c.module m
                    GROUP BY m.id, m.title
                    ORDER BY chapterCount DESC
                ')
                ->getResult()
            ;
            $this->logger->debug('Chapters by module fetched', [
                'module_count' => count($chaptersByModule),
                'top_module' => $chaptersByModule[0] ?? null,
            ]);

            // Get average duration
            $this->logger->debug('Calculating average chapter duration');
            $avgDuration = $this->entityManager
                ->createQuery('SELECT AVG(c.durationMinutes) FROM App\Entity\Training\Chapter c WHERE c.isActive = true')
                ->getSingleScalarResult()
            ;
            $avgDurationRounded = round($avgDuration ?? 0, 2);
            $this->logger->debug('Average duration calculated', [
                'raw_duration' => $avgDuration,
                'rounded_duration' => $avgDurationRounded,
            ]);

            $this->logger->info('Chapter statistics calculated successfully', [
                'total_chapters' => $totalChapters,
                'active_chapters' => $activeChapters,
                'inactive_chapters' => $inactiveChapters,
                'modules_with_chapters' => count($chaptersByModule),
                'avg_duration' => $avgDurationRounded,
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            return $this->render('admin/chapters/statistics.html.twig', [
                'totalChapters' => $totalChapters,
                'activeChapters' => $activeChapters,
                'inactiveChapters' => $inactiveChapters,
                'chaptersByModule' => $chaptersByModule,
                'avgDuration' => $avgDurationRounded,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapter statistics action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du calcul des statistiques.');

            // Return empty statistics instead of failing completely
            return $this->render('admin/chapters/statistics.html.twig', [
                'totalChapters' => 0,
                'activeChapters' => 0,
                'inactiveChapters' => 0,
                'chaptersByModule' => [],
                'avgDuration' => 0,
            ]);
        }
    }

    #[Route('/{id}', name: 'admin_chapters_show', methods: ['GET'])]
    public function show(Chapter $chapter): Response
    {
        $this->logger->info('Chapter details requested', [
            'chapter_id' => $chapter->getId(),
            'chapter_title' => $chapter->getTitle(),
            'module_id' => $chapter->getModule()->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('Chapter details loaded successfully', [
                'chapter_id' => $chapter->getId(),
                'chapter_slug' => $chapter->getSlug(),
                'is_active' => $chapter->isActive(),
                'duration_minutes' => $chapter->getDurationMinutes(),
                'order_index' => $chapter->getOrderIndex(),
                'module_title' => $chapter->getModule()->getTitle(),
                'has_description' => !empty($chapter->getDescription()),
                'has_objectives' => !empty($chapter->getLearningObjectives()),
                'has_outcomes' => !empty($chapter->getLearningOutcomes()),
            ]);

            return $this->render('admin/chapters/show.html.twig', [
                'chapter' => $chapter,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapter show action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'chapter_id' => $chapter->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du chapitre.');

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $chapter->getModule()->getId(),
            ]);
        }
    }

    #[Route('/{id}/edit', name: 'admin_chapters_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Chapter $chapter): Response
    {
        $this->logger->info('Chapter edit form requested', [
            'chapter_id' => $chapter->getId(),
            'chapter_title' => $chapter->getTitle(),
            'method' => $request->getMethod(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            // Store original values for comparison
            $originalTitle = $chapter->getTitle();
            $originalSlug = $chapter->getSlug();
            $originalIsActive = $chapter->isActive();
            $originalDuration = $chapter->getDurationMinutes();

            $form = $this->createForm(ChapterType::class, $chapter);
            $this->logger->debug('Chapter edit form created');

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Chapter edit form submitted', [
                    'chapter_id' => $chapter->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'is_valid' => $form->isValid(),
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Chapter edit form validation passed');

                    // Log changes
                    $changes = [];
                    if ($originalTitle !== $chapter->getTitle()) {
                        $changes['title'] = ['from' => $originalTitle, 'to' => $chapter->getTitle()];
                    }
                    if ($originalSlug !== $chapter->getSlug()) {
                        $changes['slug'] = ['from' => $originalSlug, 'to' => $chapter->getSlug()];
                    }
                    if ($originalIsActive !== $chapter->isActive()) {
                        $changes['is_active'] = ['from' => $originalIsActive, 'to' => $chapter->isActive()];
                    }
                    if ($originalDuration !== $chapter->getDurationMinutes()) {
                        $changes['duration_minutes'] = ['from' => $originalDuration, 'to' => $chapter->getDurationMinutes()];
                    }

                    $this->logger->info('Updating chapter', [
                        'chapter_id' => $chapter->getId(),
                        'changes' => $changes,
                        'user_id' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->entityManager->flush();

                    $this->logger->info('Chapter updated successfully', [
                        'chapter_id' => $chapter->getId(),
                        'chapter_title' => $chapter->getTitle(),
                        'changes_count' => count($changes),
                        'user_id' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('success', 'Chapitre modifié avec succès.');

                    return $this->redirectToRoute('admin_chapters_index', [
                        'module' => $chapter->getModule()->getId(),
                    ]);
                }
                $this->logger->warning('Chapter edit form validation failed', [
                    'chapter_id' => $chapter->getId(),
                    'form_errors' => (string) $form->getErrors(true),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);
            }

            $this->logger->debug('Rendering chapter edit form');

            return $this->render('admin/chapters/edit.html.twig', [
                'chapter' => $chapter,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapter edit action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'chapter_id' => $chapter->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du chapitre.');

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $chapter->getModule()->getId(),
            ]);
        }
    }

    #[Route('/{id}/delete', name: 'admin_chapters_delete', methods: ['POST'])]
    public function delete(Request $request, Chapter $chapter): Response
    {
        $this->logger->info('Chapter deletion requested', [
            'chapter_id' => $chapter->getId(),
            'chapter_title' => $chapter->getTitle(),
            'module_id' => $chapter->getModule()->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $moduleId = $chapter->getModule()->getId();
            $chapterTitle = $chapter->getTitle();
            $chapterId = $chapter->getId();

            if ($this->isCsrfTokenValid('delete' . $chapter->getId(), $request->request->get('_token'))) {
                $this->logger->info('CSRF token validated for chapter deletion', [
                    'chapter_id' => $chapterId,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->logger->debug('Removing chapter from database', [
                    'chapter_id' => $chapterId,
                    'chapter_title' => $chapterTitle,
                ]);

                $this->entityManager->remove($chapter);
                $this->entityManager->flush();

                $this->logger->info('Chapter deleted successfully', [
                    'deleted_chapter_id' => $chapterId,
                    'deleted_chapter_title' => $chapterTitle,
                    'module_id' => $moduleId,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Chapitre supprimé avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for chapter deletion', [
                    'chapter_id' => $chapterId,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'provided_token' => $request->request->get('_token'),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $moduleId,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapter deletion action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'chapter_id' => $chapter->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du chapitre.');

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $chapter->getModule()->getId(),
            ]);
        }
    }

    #[Route('/{id}/toggle-active', name: 'admin_chapters_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, Chapter $chapter): Response
    {
        $this->logger->info('Chapter active status toggle requested', [
            'chapter_id' => $chapter->getId(),
            'chapter_title' => $chapter->getTitle(),
            'current_status' => $chapter->isActive(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $originalStatus = $chapter->isActive();

            if ($this->isCsrfTokenValid('toggle' . $chapter->getId(), $request->request->get('_token'))) {
                $this->logger->info('CSRF token validated for chapter status toggle', [
                    'chapter_id' => $chapter->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $chapter->setIsActive(!$chapter->isActive());
                $newStatus = $chapter->isActive();

                $this->logger->debug('Chapter status toggled in memory', [
                    'chapter_id' => $chapter->getId(),
                    'status_from' => $originalStatus,
                    'status_to' => $newStatus,
                ]);

                $this->entityManager->flush();

                $status = $chapter->isActive() ? 'activé' : 'désactivé';

                $this->logger->info('Chapter status toggled successfully', [
                    'chapter_id' => $chapter->getId(),
                    'chapter_title' => $chapter->getTitle(),
                    'old_status' => $originalStatus,
                    'new_status' => $newStatus,
                    'status_label' => $status,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', "Chapitre {$status} avec succès.");
            } else {
                $this->logger->warning('Invalid CSRF token for chapter status toggle', [
                    'chapter_id' => $chapter->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'provided_token' => $request->request->get('_token'),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $chapter->getModule()->getId(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapter toggle active action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'chapter_id' => $chapter->getId(),
                'original_status' => $originalStatus ?? $chapter->isActive(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du statut du chapitre.');

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $chapter->getModule()->getId(),
            ]);
        }
    }

    #[Route('/reorder', name: 'admin_chapters_reorder', methods: ['POST'])]
    public function reorder(Request $request): Response
    {
        $this->logger->info('Chapter reorder requested', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'module_id' => $request->request->get('module_id'),
        ]);

        try {
            $chapterIds = $request->request->all('chapters');
            $moduleId = $request->request->get('module_id');

            $this->logger->debug('Chapter reorder data received', [
                'chapter_ids' => $chapterIds,
                'module_id' => $moduleId,
                'chapter_count' => count($chapterIds),
            ]);

            if (!empty($chapterIds)) {
                $this->logger->info('Updating chapter order indexes', [
                    'chapter_ids' => $chapterIds,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->chapterRepository->updateOrderIndexes($chapterIds);

                $this->logger->info('Chapter order updated successfully', [
                    'reordered_chapters' => count($chapterIds),
                    'module_id' => $moduleId,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Ordre des chapitres mis à jour avec succès.');
            } else {
                $this->logger->warning('Empty chapter IDs array for reorder', [
                    'module_id' => $moduleId,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('warning', 'Aucun chapitre à réorganiser.');
            }

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $moduleId,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapter reorder action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'chapter_ids' => $request->request->all('chapters'),
                'module_id' => $request->request->get('module_id'),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la réorganisation des chapitres.');

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $request->request->get('module_id'),
            ]);
        }
    }

    #[Route('/by-module/{id}', name: 'admin_chapters_by_module', methods: ['GET'])]
    public function byModule(Module $module): Response
    {
        $this->logger->info('Chapters by module requested', [
            'module_id' => $module->getId(),
            'module_title' => $module->getTitle(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('Fetching chapters for module', [
                'module_id' => $module->getId(),
                'module_title' => $module->getTitle(),
            ]);

            $chapters = $this->chapterRepository->findAllByModuleOrdered($module->getId());

            $this->logger->info('Chapters fetched for module', [
                'module_id' => $module->getId(),
                'module_title' => $module->getTitle(),
                'chapter_count' => count($chapters),
                'active_chapters' => count(array_filter($chapters, static fn ($c) => $c->isActive())),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            return $this->render('admin/chapters/by_module.html.twig', [
                'chapters' => $chapters,
                'module' => $module,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapters by module action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'module_id' => $module->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des chapitres du module.');

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $module->getId(),
            ]);
        }
    }

    #[Route('/duplicate/{id}', name: 'admin_chapters_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, Chapter $chapter): Response
    {
        $this->logger->info('Chapter duplication requested', [
            'chapter_id' => $chapter->getId(),
            'chapter_title' => $chapter->getTitle(),
            'module_id' => $chapter->getModule()->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if ($this->isCsrfTokenValid('duplicate' . $chapter->getId(), $request->request->get('_token'))) {
                $this->logger->info('CSRF token validated for chapter duplication', [
                    'chapter_id' => $chapter->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $duplicatedChapter = new Chapter();
                $newTitle = $chapter->getTitle() . ' (Copie)';
                $newSlug = $chapter->getSlug() . '-copy-' . time();

                $this->logger->debug('Creating duplicated chapter', [
                    'original_id' => $chapter->getId(),
                    'new_title' => $newTitle,
                    'new_slug' => $newSlug,
                ]);

                $duplicatedChapter->setTitle($newTitle);
                $duplicatedChapter->setSlug($newSlug);
                $duplicatedChapter->setDescription($chapter->getDescription());
                $duplicatedChapter->setContentOutline($chapter->getContentOutline());
                $duplicatedChapter->setLearningObjectives($chapter->getLearningObjectives());
                $duplicatedChapter->setLearningOutcomes($chapter->getLearningOutcomes());
                $duplicatedChapter->setPrerequisites($chapter->getPrerequisites());
                $duplicatedChapter->setTeachingMethods($chapter->getTeachingMethods());
                $duplicatedChapter->setAssessmentMethods($chapter->getAssessmentMethods());
                $duplicatedChapter->setResources($chapter->getResources());
                $duplicatedChapter->setSuccessCriteria($chapter->getSuccessCriteria());
                $duplicatedChapter->setDurationMinutes($chapter->getDurationMinutes());
                $duplicatedChapter->setModule($chapter->getModule());
                $duplicatedChapter->setIsActive(false); // Deactivate duplicate by default

                // Set new order index
                $nextOrder = $this->chapterRepository->getNextOrderIndex($chapter->getModule()->getId());
                $duplicatedChapter->setOrderIndex($nextOrder);

                $this->logger->debug('Duplicated chapter configured', [
                    'original_id' => $chapter->getId(),
                    'new_order_index' => $nextOrder,
                    'is_active' => $duplicatedChapter->isActive(),
                    'duration_minutes' => $duplicatedChapter->getDurationMinutes(),
                ]);

                $this->entityManager->persist($duplicatedChapter);
                $this->entityManager->flush();

                $this->logger->info('Chapter duplicated successfully', [
                    'original_chapter_id' => $chapter->getId(),
                    'original_chapter_title' => $chapter->getTitle(),
                    'duplicated_chapter_id' => $duplicatedChapter->getId(),
                    'duplicated_chapter_title' => $duplicatedChapter->getTitle(),
                    'module_id' => $chapter->getModule()->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Chapitre dupliqué avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for chapter duplication', [
                    'chapter_id' => $chapter->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'provided_token' => $request->request->get('_token'),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $chapter->getModule()->getId(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in chapter duplication action', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'chapter_id' => $chapter->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la duplication du chapitre.');

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $chapter->getModule()->getId(),
            ]);
        }
    }
}
