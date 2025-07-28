<?php

namespace App\Controller\Admin;

use App\Entity\Service\ServiceCategory;
use App\Form\ServiceCategoryType;
use App\Repository\ServiceCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Admin Service Category Controller
 * 
 * Handles CRUD operations for service categories in the admin interface.
 * Provides full management capabilities for service categories.
 */
#[Route('/admin/service-categories', name: 'admin_service_category_')]
#[IsGranted('ROLE_ADMIN')]
class ServiceCategoryController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private SluggerInterface $slugger
    ) {
    }

    /**
     * List all service categories with pagination and filtering
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(ServiceCategoryRepository $serviceCategoryRepository): Response
    {
        $this->logger->info('Admin service categories list accessed', [
            'user' => $this->getUser()?->getUserIdentifier()
        ]);

        $serviceCategories = $serviceCategoryRepository->findAllOrdered();

        return $this->render('admin/service_category/index.html.twig', [
            'service_categories' => $serviceCategories,
            'page_title' => 'Gestion des catégories de services',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Catégories de services', 'url' => null]
            ]
        ]);
    }

    /**
     * Show service category details
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ServiceCategory $serviceCategory): Response
    {
        $this->logger->info('Admin service category details viewed', [
            'service_category_id' => $serviceCategory->getId(),
            'user' => $this->getUser()?->getUserIdentifier()
        ]);

        return $this->render('admin/service_category/show.html.twig', [
            'service_category' => $serviceCategory,
            'page_title' => 'Détails de la catégorie de service',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Catégories de services', 'url' => $this->generateUrl('admin_service_category_index')],
                ['label' => $serviceCategory->getName(), 'url' => null]
            ]
        ]);
    }

    /**
     * Create a new service category
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $serviceCategory = new ServiceCategory();
        $form = $this->createForm(ServiceCategoryType::class, $serviceCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate slug from name
            $slug = $this->slugger->slug($serviceCategory->getName())->lower();
            $serviceCategory->setSlug($slug);

            $entityManager->persist($serviceCategory);
            $entityManager->flush();

            $this->logger->info('New service category created', [
                'service_category_id' => $serviceCategory->getId(),
                'service_category_name' => $serviceCategory->getName(),
                'user' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', 'La catégorie de service a été créée avec succès.');

            return $this->redirectToRoute('admin_service_category_index');
        }

        return $this->render('admin/service_category/new.html.twig', [
            'service_category' => $serviceCategory,
            'form' => $form,
            'page_title' => 'Nouvelle catégorie de service',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Catégories de services', 'url' => $this->generateUrl('admin_service_category_index')],
                ['label' => 'Nouvelle catégorie', 'url' => null]
            ]
        ]);
    }

    /**
     * Edit an existing service category
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, ServiceCategory $serviceCategory, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServiceCategoryType::class, $serviceCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update slug if name changed
            $slug = $this->slugger->slug($serviceCategory->getName())->lower();
            $serviceCategory->setSlug($slug);

            $entityManager->flush();

            $this->logger->info('Service category updated', [
                'service_category_id' => $serviceCategory->getId(),
                'service_category_name' => $serviceCategory->getName(),
                'user' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', 'La catégorie de service a été modifiée avec succès.');

            return $this->redirectToRoute('admin_service_category_index');
        }

        return $this->render('admin/service_category/edit.html.twig', [
            'service_category' => $serviceCategory,
            'form' => $form,
            'page_title' => 'Modifier la catégorie de service',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Catégories de services', 'url' => $this->generateUrl('admin_service_category_index')],
                ['label' => $serviceCategory->getName(), 'url' => $this->generateUrl('admin_service_category_show', ['id' => $serviceCategory->getId()])],
                ['label' => 'Modifier', 'url' => null]
            ]
        ]);
    }

    /**
     * Delete a service category
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ServiceCategory $serviceCategory, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$serviceCategory->getId(), $request->getPayload()->get('_token'))) {
            // Check if category has services
            if ($serviceCategory->getServices()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle contient des services.');
                return $this->redirectToRoute('admin_service_category_index');
            }

            $categoryName = $serviceCategory->getName();
            $entityManager->remove($serviceCategory);
            $entityManager->flush();

            $this->logger->info('Service category deleted', [
                'service_category_id' => $serviceCategory->getId(),
                'service_category_name' => $categoryName,
                'user' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', 'La catégorie de service a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_service_category_index');
    }
}