<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Form\Training\ModuleType;
use App\Repository\Training\FormationRepository;
use App\Repository\Training\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {}

    #[Route('', name: 'admin_modules_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $formationId = $request->query->get('formation');
        $formation = null;

        if ($formationId) {
            $formation = $this->formationRepository->find($formationId);
            if (!$formation) {
                throw $this->createNotFoundException('Formation not found');
            }
            $modules = $this->moduleRepository->findByFormationOrdered($formationId);
        } else {
            $modules = $this->moduleRepository->findBy([], ['orderIndex' => 'ASC']);
        }

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/modules/index.html.twig', [
            'modules' => $modules,
            'formations' => $formations,
            'selectedFormation' => $formation,
        ]);
    }

    #[Route('/new', name: 'admin_modules_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $module = new Module();
        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Auto-generate slug if not provided
            if (!$module->getSlug()) {
                $slug = $this->slugger->slug($module->getTitle())->lower()->toString();
                $module->setSlug($slug);
            }

            // Set order index if not provided
            if (!$module->getOrderIndex()) {
                $nextOrder = $this->moduleRepository->getNextOrderIndex($module->getFormation()->getId());
                $module->setOrderIndex($nextOrder);
            }

            $this->entityManager->persist($module);
            $this->entityManager->flush();

            $this->addFlash('success', 'Module créé avec succès.');

            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $module->getFormation()->getId(),
            ]);
        }

        return $this->render('admin/modules/new.html.twig', [
            'module' => $module,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_modules_show', methods: ['GET'])]
    public function show(Module $module): Response
    {
        return $this->render('admin/modules/show.html.twig', [
            'module' => $module,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_modules_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Module $module): Response
    {
        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Module modifié avec succès.');

            return $this->redirectToRoute('admin_modules_index', [
                'formation' => $module->getFormation()->getId(),
            ]);
        }

        return $this->render('admin/modules/edit.html.twig', [
            'module' => $module,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_modules_delete', methods: ['POST'])]
    public function delete(Request $request, Module $module): Response
    {
        if ($this->isCsrfTokenValid('delete' . $module->getId(), $request->request->get('_token'))) {
            $formationId = $module->getFormation()->getId();
            $this->entityManager->remove($module);
            $this->entityManager->flush();

            $this->addFlash('success', 'Module supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_modules_index', [
            'formation' => $formationId ?? null,
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'admin_modules_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, Module $module): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $module->getId(), $request->request->get('_token'))) {
            $module->setIsActive(!$module->isActive());
            $this->entityManager->flush();

            $status = $module->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "Module {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_modules_index', [
            'formation' => $module->getFormation()->getId(),
        ]);
    }

    #[Route('/reorder', name: 'admin_modules_reorder', methods: ['POST'])]
    public function reorder(Request $request): Response
    {
        $moduleIds = $request->request->all('modules');

        if (!empty($moduleIds)) {
            $this->moduleRepository->updateOrderIndexes($moduleIds);
            $this->addFlash('success', 'Ordre des modules mis à jour avec succès.');
        }

        return $this->redirectToRoute('admin_modules_index', [
            'formation' => $request->request->get('formation_id'),
        ]);
    }

    #[Route('/by-formation/{id}', name: 'admin_modules_by_formation', methods: ['GET'])]
    public function byFormation(Formation $formation): Response
    {
        $modules = $this->moduleRepository->findByFormationOrdered($formation->getId());

        return $this->render('admin/modules/by_formation.html.twig', [
            'modules' => $modules,
            'formation' => $formation,
        ]);
    }
}
