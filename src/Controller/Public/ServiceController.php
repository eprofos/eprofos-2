<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\Service\ServiceCategoryRepository;
use App\Repository\Service\ServiceRepository;
use Psr\Log\LoggerInterface;
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
        private LoggerInterface $logger,
    ) {}

    /**
     * Display all services grouped by category.
     */
    #[Route('', name: 'public_services_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->logger->info('ServiceController::index - Starting services index page request');
        
        try {
            $this->logger->debug('ServiceController::index - Fetching services grouped by category');
            // Get services grouped by category for organized display
            $servicesGrouped = $this->serviceRepository->findServicesGroupedByCategory();
            $this->logger->info('ServiceController::index - Found {count} service groups', [
                'count' => count($servicesGrouped),
                'groups' => array_keys($servicesGrouped)
            ]);

            $this->logger->debug('ServiceController::index - Fetching categories with service count');
            // Get categories with their service counts
            $categoriesWithCount = $this->serviceCategoryRepository->findWithServiceCount();
            $this->logger->info('ServiceController::index - Found {count} categories with service counts', [
                'count' => count($categoriesWithCount),
                'categories' => array_map(fn($item) => $item['category']->getName() ?? 'unnamed', $categoriesWithCount)
            ]);

            $this->logger->debug('ServiceController::index - Fetching all active services');
            // Get all active services for general listing
            $allServices = $this->serviceRepository->findActiveServices();
            $this->logger->info('ServiceController::index - Found {count} active services', [
                'count' => count($allServices),
                'service_ids' => array_map(fn($service) => $service->getId(), $allServices)
            ]);

            $this->logger->debug('ServiceController::index - Fetching categories with active services for navigation');
            $serviceCategories = $this->serviceCategoryRepository->findCategoriesWithActiveServices();
            $this->logger->info('ServiceController::index - Found {count} categories with active services', [
                'count' => count($serviceCategories),
                'category_slugs' => array_map(fn($cat) => $cat->getSlug(), $serviceCategories)
            ]);

            $this->logger->info('ServiceController::index - Successfully prepared all data, rendering template');

            return $this->render('public/service/index.html.twig', [
                'services_grouped' => $servicesGrouped,
                'categories_with_count' => $categoriesWithCount,
                'all_services' => $allServices,
                // Additional variables for template compatibility
                'service_categories' => $serviceCategories,
                'services' => $allServices, // Alias for template compatibility
            ]);

        } catch (\Exception $e) {
            $this->logger->error('ServiceController::index - Error occurred while loading services index page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des services. Veuillez réessayer.');
            
            // Return a minimal response with empty data to prevent complete failure
            return $this->render('public/service/index.html.twig', [
                'services_grouped' => [],
                'categories_with_count' => [],
                'all_services' => [],
                'service_categories' => [],
                'services' => [],
            ]);
        }
    }

    /**
     * Display services by category.
     */
    #[Route('/categorie/{slug}', name: 'public_services_by_category', methods: ['GET'])]
    public function byCategory(string $slug): Response
    {
        $this->logger->info('ServiceController::byCategory - Starting services by category request', [
            'category_slug' => $slug
        ]);

        try {
            $this->logger->debug('ServiceController::byCategory - Searching for category by slug', [
                'slug' => $slug
            ]);

            $category = $this->serviceCategoryRepository->findBySlugWithActiveServices($slug);

            if (!$category) {
                $this->logger->warning('ServiceController::byCategory - Category not found', [
                    'requested_slug' => $slug
                ]);
                throw $this->createNotFoundException('Catégorie de service non trouvée');
            }

            $this->logger->info('ServiceController::byCategory - Found category', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'category_slug' => $category->getSlug()
            ]);

            $activeServices = $category->getActiveServices();
            $this->logger->info('ServiceController::byCategory - Found active services in category', [
                'category_name' => $category->getName(),
                'services_count' => count($activeServices),
                'service_ids' => array_map(fn($service) => $service->getId(), $activeServices->toArray())
            ]);

            $this->logger->debug('ServiceController::byCategory - Fetching all categories for navigation');
            // Get all categories for navigation
            $allCategories = $this->serviceCategoryRepository->findCategoriesWithActiveServices();
            $this->logger->info('ServiceController::byCategory - Found {count} categories for navigation', [
                'count' => count($allCategories),
                'navigation_categories' => array_map(fn($cat) => $cat->getSlug(), $allCategories)
            ]);

            $this->logger->info('ServiceController::byCategory - Successfully prepared category data, rendering template');

            return $this->render('public/service/by_category.html.twig', [
                'category' => $category,
                'services' => $activeServices,
                'all_categories' => $allCategories,
            ]);

        } catch (\Exception $e) {
            // Don't catch NotFoundException as it should be handled by Symfony
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                $this->logger->info('ServiceController::byCategory - Category not found, throwing 404', [
                    'requested_slug' => $slug
                ]);
                throw $e;
            }

            $this->logger->error('ServiceController::byCategory - Error occurred while loading category services', [
                'category_slug' => $slug,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des services de cette catégorie. Veuillez réessayer.');
            
            // Redirect to services index instead of showing broken page
            return $this->redirectToRoute('public_services_index');
        }
    }

    /**
     * Display detailed view of a specific service.
     */
    #[Route('/{slug}', name: 'public_service_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $this->logger->info('ServiceController::show - Starting service detail request', [
            'service_slug' => $slug
        ]);

        try {
            $this->logger->debug('ServiceController::show - Searching for service by slug', [
                'slug' => $slug
            ]);

            $service = $this->serviceRepository->findBySlugWithCategory($slug);

            if (!$service) {
                $this->logger->warning('ServiceController::show - Service not found', [
                    'requested_slug' => $slug
                ]);
                throw $this->createNotFoundException('Service non trouvé');
            }

            $this->logger->info('ServiceController::show - Found service', [
                'service_id' => $service->getId(),
                'service_title' => $service->getTitle(),
                'service_slug' => $service->getSlug(),
                'service_category' => $service->getServiceCategory()?->getName() ?? 'No category'
            ]);

            // Get other services from the same category
            $relatedServices = [];
            if ($service->getServiceCategory()) {
                $this->logger->debug('ServiceController::show - Fetching related services from same category', [
                    'category_name' => $service->getServiceCategory()->getName(),
                    'category_id' => $service->getServiceCategory()->getId()
                ]);

                $relatedServices = $this->serviceRepository->findByCategory($service->getServiceCategory());
                $this->logger->info('ServiceController::show - Found {count} services in same category', [
                    'count' => count($relatedServices),
                    'category_name' => $service->getServiceCategory()->getName()
                ]);

                // Remove current service from related services
                $relatedServices = array_filter(
                    $relatedServices,
                    static fn ($relatedService) => $relatedService->getId() !== $service->getId(),
                );

                $this->logger->info('ServiceController::show - Filtered related services (excluding current)', [
                    'related_count' => count($relatedServices),
                    'related_service_ids' => array_map(fn($rs) => $rs->getId(), $relatedServices)
                ]);
            } else {
                $this->logger->info('ServiceController::show - Service has no category, no related services to fetch', [
                    'service_id' => $service->getId()
                ]);
            }

            $limitedRelatedServices = array_slice($relatedServices, 0, 3);
            $this->logger->info('ServiceController::show - Limited related services to 3 items', [
                'displayed_count' => count($limitedRelatedServices),
                'displayed_service_ids' => array_map(fn($rs) => $rs->getId(), $limitedRelatedServices)
            ]);

            $this->logger->info('ServiceController::show - Successfully prepared service data, rendering template');

            return $this->render('public/service/show.html.twig', [
                'service' => $service,
                'related_services' => $limitedRelatedServices, // Limit to 3 related services
            ]);

        } catch (\Exception $e) {
            // Don't catch NotFoundException as it should be handled by Symfony
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                $this->logger->info('ServiceController::show - Service not found, throwing 404', [
                    'requested_slug' => $slug
                ]);
                throw $e;
            }

            $this->logger->error('ServiceController::show - Error occurred while loading service details', [
                'service_slug' => $slug,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du service. Veuillez réessayer.');
            
            // Redirect to services index instead of showing broken page
            return $this->redirectToRoute('public_services_index');
        }
    }
}
