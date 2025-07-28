<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Training\Category;
use App\Form\Training\CategoryType;
use App\Repository\Training\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Admin Category Controller.
 *
 * Handles CRUD operations for categories in the admin interface.
 * Provides full management capabilities for formation categories.
 */
#[Route('/admin/categories', name: 'admin_category_')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private SluggerInterface $slugger,
    ) {}

    /**
     * List all categories with pagination and filtering.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $this->logger->info('Admin categories list accessed', [
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);

        $categories = $categoryRepository->findBy([], ['name' => 'ASC']);

        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories,
            'page_title' => 'Gestion des catégories',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Catégories', 'url' => null],
            ],
        ]);
    }

    /**
     * Show category details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Category $category): Response
    {
        $this->logger->info('Admin category details viewed', [
            'category_id' => $category->getId(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $this->render('admin/category/show.html.twig', [
            'category' => $category,
            'page_title' => 'Détails de la catégorie',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Catégories', 'url' => $this->generateUrl('admin_category_index')],
                ['label' => $category->getName(), 'url' => null],
            ],
        ]);
    }

    /**
     * Create a new category.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate slug from name
            $slug = $this->slugger->slug($category->getName())->lower();
            $category->setSlug((string)$slug);

            $entityManager->persist($category);
            $entityManager->flush();

            $this->logger->info('New category created', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La catégorie a été créée avec succès.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/new.html.twig', [
            'category' => $category,
            'form' => $form,
            'page_title' => 'Nouvelle catégorie',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Catégories', 'url' => $this->generateUrl('admin_category_index')],
                ['label' => 'Nouvelle catégorie', 'url' => null],
            ],
        ]);
    }

    /**
     * Edit an existing category.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update slug if name changed
            $slug = $this->slugger->slug($category->getName())->lower();
            $category->setSlug((string)$slug);

            $entityManager->flush();

            $this->logger->info('Category updated', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La catégorie a été modifiée avec succès.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
            'page_title' => 'Modifier la catégorie',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Catégories', 'url' => $this->generateUrl('admin_category_index')],
                ['label' => $category->getName(), 'url' => $this->generateUrl('admin_category_show', ['id' => $category->getId()])],
                ['label' => 'Modifier', 'url' => null],
            ],
        ]);
    }

    /**
     * Delete a category.
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->getPayload()->get('_token'))) {
            // Check if category has formations
            if ($category->getFormations()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle contient des formations.');

                return $this->redirectToRoute('admin_category_index');
            }

            $categoryName = $category->getName();
            $entityManager->remove($category);
            $entityManager->flush();

            $this->logger->info('Category deleted', [
                'category_id' => $category->getId(),
                'category_name' => $categoryName,
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La catégorie a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_category_index');
    }

    /**
     * Toggle category active status.
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $category->getId(), $request->getPayload()->get('_token'))) {
            $category->setIsActive(!$category->isActive());
            $entityManager->flush();

            $status = $category->isActive() ? 'activée' : 'désactivée';
            $this->addFlash('success', "La catégorie a été {$status} avec succès.");

            $this->logger->info('Category status toggled', [
                'category_id' => $category->getId(),
                'new_status' => $category->isActive(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);
        }

        return $this->redirectToRoute('admin_category_index');
    }
}
