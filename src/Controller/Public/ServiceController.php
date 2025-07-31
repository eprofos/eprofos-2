<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\Service\ServiceCategoryRepository;
use App\Repository\Service\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Service controller for displaying EPROFOS services.
 *
 * Handles the presentation of services offered by EPROFOS
 * including consultation, audit, custom training, and certifications.
 */
#[Route('/services')]
class ServiceController extends AbstractController
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private ServiceCategoryRepository $serviceCategoryRepository,
    ) {}

    /**
     * Display all services grouped by category.
     */
    #[Route('', name: 'public_services_index', methods: ['GET'])]
    public function index(): Response
    {
        // Get services grouped by category for organized display
        $servicesGrouped = $this->serviceRepository->findServicesGroupedByCategory();

        // Get categories with their service counts
        $categoriesWithCount = $this->serviceCategoryRepository->findWithServiceCount();

        // Get all active services for general listing
        $allServices = $this->serviceRepository->findActiveServices();

        return $this->render('public/service/index.html.twig', [
            'services_grouped' => $servicesGrouped,
            'categories_with_count' => $categoriesWithCount,
            'all_services' => $allServices,
            // Additional variables for template compatibility
            'service_categories' => $this->serviceCategoryRepository->findCategoriesWithActiveServices(),
            'services' => $allServices, // Alias for template compatibility
        ]);
    }

    /**
     * Display services by category.
     */
    #[Route('/categorie/{slug}', name: 'public_services_by_category', methods: ['GET'])]
    public function byCategory(string $slug): Response
    {
        $category = $this->serviceCategoryRepository->findBySlugWithActiveServices($slug);

        if (!$category) {
            throw $this->createNotFoundException('Catégorie de service non trouvée');
        }

        // Get all categories for navigation
        $allCategories = $this->serviceCategoryRepository->findCategoriesWithActiveServices();

        return $this->render('public/service/by_category.html.twig', [
            'category' => $category,
            'services' => $category->getActiveServices(),
            'all_categories' => $allCategories,
        ]);
    }

    /**
     * Display detailed view of a specific service.
     */
    #[Route('/{slug}', name: 'public_service_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $service = $this->serviceRepository->findBySlugWithCategory($slug);

        if (!$service) {
            throw $this->createNotFoundException('Service non trouvé');
        }

        // Get other services from the same category
        $relatedServices = [];
        if ($service->getServiceCategory()) {
            $relatedServices = $this->serviceRepository->findByCategory($service->getServiceCategory());
            // Remove current service from related services
            $relatedServices = array_filter(
                $relatedServices,
                static fn ($relatedService) => $relatedService->getId() !== $service->getId(),
            );
        }

        return $this->render('public/service/show.html.twig', [
            'service' => $service,
            'related_services' => array_slice($relatedServices, 0, 3), // Limit to 3 related services
        ]);
    }
}
