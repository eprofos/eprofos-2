<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Chapter;
use App\Entity\Training\Module;
use App\Form\Training\ChapterType;
use App\Repository\Training\ChapterRepository;
use App\Repository\Training\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {}

    #[Route('', name: 'admin_chapters_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $moduleId = $request->query->get('module');
        $formationId = $request->query->get('formation');
        $search = $request->query->get('search');
        $active = $request->query->get('active');

        $filters = [];

        if ($moduleId) {
            $filters['module'] = $moduleId;
        }

        if ($formationId) {
            $filters['formation'] = $formationId;
        }

        if ($search) {
            $filters['search'] = $search;
        }

        if ($active !== null) {
            $filters['active'] = $active === '1';
        }

        $chapters = $this->chapterRepository->findWithFilters($filters);

        // Get module for context if specified
        $module = null;
        if ($moduleId) {
            $module = $this->moduleRepository->find($moduleId);
            if (!$module) {
                throw $this->createNotFoundException('Module not found');
            }
        }

        // Get all modules for filter dropdown
        $modules = $this->moduleRepository->findBy([], ['orderIndex' => 'ASC']);

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
    }

    #[Route('/new', name: 'admin_chapters_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $chapter = new Chapter();

        // Pre-select module if provided
        $moduleId = $request->query->get('module');
        if ($moduleId) {
            $module = $this->moduleRepository->find($moduleId);
            if ($module) {
                $chapter->setModule($module);
            }
        }

        $form = $this->createForm(ChapterType::class, $chapter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Auto-generate slug if not provided
            if (!$chapter->getSlug()) {
                $slug = $this->slugger->slug($chapter->getTitle())->lower();
                $chapter->setSlug((string)$slug);
            }

            // Set order index if not provided
            if (!$chapter->getOrderIndex()) {
                $nextOrder = $this->chapterRepository->getNextOrderIndex($chapter->getModule()->getId());
                $chapter->setOrderIndex($nextOrder);
            }

            $this->entityManager->persist($chapter);
            $this->entityManager->flush();

            $this->addFlash('success', 'Chapitre créé avec succès.');

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $chapter->getModule()->getId(),
            ]);
        }

        return $this->render('admin/chapters/new.html.twig', [
            'chapter' => $chapter,
            'form' => $form,
        ]);
    }

    #[Route('/statistics', name: 'admin_chapters_statistics', methods: ['GET'])]
    public function statistics(): Response
    {
        $totalChapters = $this->chapterRepository->count([]);
        $activeChapters = $this->chapterRepository->count(['isActive' => true]);
        $inactiveChapters = $totalChapters - $activeChapters;

        // Get chapters by module
        $chaptersByModule = $this->entityManager
            ->createQuery('
                SELECT m.title as moduleTitle, COUNT(c.id) as chapterCount
                FROM App\Entity\Chapter c
                JOIN c.module m
                GROUP BY m.id, m.title
                ORDER BY chapterCount DESC
            ')
            ->getResult()
        ;

        // Get average duration
        $avgDuration = $this->entityManager
            ->createQuery('SELECT AVG(c.durationMinutes) FROM App\Entity\Chapter c WHERE c.isActive = true')
            ->getSingleScalarResult()
        ;

        return $this->render('admin/chapters/statistics.html.twig', [
            'totalChapters' => $totalChapters,
            'activeChapters' => $activeChapters,
            'inactiveChapters' => $inactiveChapters,
            'chaptersByModule' => $chaptersByModule,
            'avgDuration' => round($avgDuration ?? 0, 2),
        ]);
    }

    #[Route('/{id}', name: 'admin_chapters_show', methods: ['GET'])]
    public function show(Chapter $chapter): Response
    {
        return $this->render('admin/chapters/show.html.twig', [
            'chapter' => $chapter,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_chapters_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Chapter $chapter): Response
    {
        $form = $this->createForm(ChapterType::class, $chapter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Chapitre modifié avec succès.');

            return $this->redirectToRoute('admin_chapters_index', [
                'module' => $chapter->getModule()->getId(),
            ]);
        }

        return $this->render('admin/chapters/edit.html.twig', [
            'chapter' => $chapter,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_chapters_delete', methods: ['POST'])]
    public function delete(Request $request, Chapter $chapter): Response
    {
        if ($this->isCsrfTokenValid('delete' . $chapter->getId(), $request->request->get('_token'))) {
            $moduleId = $chapter->getModule()->getId();
            $this->entityManager->remove($chapter);
            $this->entityManager->flush();

            $this->addFlash('success', 'Chapitre supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_chapters_index', [
            'module' => $moduleId ?? null,
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'admin_chapters_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, Chapter $chapter): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $chapter->getId(), $request->request->get('_token'))) {
            $chapter->setIsActive(!$chapter->isActive());
            $this->entityManager->flush();

            $status = $chapter->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "Chapitre {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_chapters_index', [
            'module' => $chapter->getModule()->getId(),
        ]);
    }

    #[Route('/reorder', name: 'admin_chapters_reorder', methods: ['POST'])]
    public function reorder(Request $request): Response
    {
        $chapterIds = $request->request->all('chapters');

        if (!empty($chapterIds)) {
            $this->chapterRepository->updateOrderIndexes($chapterIds);
            $this->addFlash('success', 'Ordre des chapitres mis à jour avec succès.');
        }

        return $this->redirectToRoute('admin_chapters_index', [
            'module' => $request->request->get('module_id'),
        ]);
    }

    #[Route('/by-module/{id}', name: 'admin_chapters_by_module', methods: ['GET'])]
    public function byModule(Module $module): Response
    {
        $chapters = $this->chapterRepository->findAllByModuleOrdered($module->getId());

        return $this->render('admin/chapters/by_module.html.twig', [
            'chapters' => $chapters,
            'module' => $module,
        ]);
    }

    #[Route('/duplicate/{id}', name: 'admin_chapters_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, Chapter $chapter): Response
    {
        if ($this->isCsrfTokenValid('duplicate' . $chapter->getId(), $request->request->get('_token'))) {
            $duplicatedChapter = new Chapter();
            $duplicatedChapter->setTitle($chapter->getTitle() . ' (Copie)');
            $duplicatedChapter->setSlug($chapter->getSlug() . '-copy-' . time());
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

            $this->entityManager->persist($duplicatedChapter);
            $this->entityManager->flush();

            $this->addFlash('success', 'Chapitre dupliqué avec succès.');
        }

        return $this->redirectToRoute('admin_chapters_index', [
            'module' => $chapter->getModule()->getId(),
        ]);
    }
}
