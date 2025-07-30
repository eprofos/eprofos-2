<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\Training\Formation;
use App\Repository\Training\CategoryRepository;
use App\Repository\Training\FormationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Formation controller for the public formation catalog.
 *
 * Handles formation listing, filtering, search, and detailed views
 * with Ajax support for dynamic filtering and pagination.
 */
#[Route('/formations')]
class FormationController extends AbstractController
{
    public function __construct(
        private FormationRepository $formationRepository,
        private CategoryRepository $categoryRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Display the formation catalog with filtering capabilities.
     */
    #[Route('', name: 'app_formations_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get filter parameters from request
        $filters = $this->extractFilters($request);

        // Get formations based on filters
        $queryBuilder = $this->formationRepository->createCatalogQueryBuilder($filters);

        // Handle pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12; // Formations per page
        $offset = ($page - 1) * $limit;

        $formations = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        // Get total count for pagination
        $totalFormations = $this->formationRepository->countByFilters($filters);
        $totalPages = ceil($totalFormations / $limit);

        // Get filter options for the form
        $filterOptions = $this->getFilterOptions();

        // Get active categories for navigation
        $categories = $this->categoryRepository->findCategoriesWithActiveFormations();

        // If it's an Ajax request, return only the formations list
        if ($request->isXmlHttpRequest()) {
            return $this->render('public/formation/_formations_list.html.twig', [
                'formations' => $formations,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_formations' => $totalFormations,
            ]);
        }

        return $this->render('public/formation/index.html.twig', [
            'formations' => $formations,
            'categories' => $categories,
            'filter_options' => $filterOptions,
            'current_filters' => $filters,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_formations' => $totalFormations,
        ]);
    }

    /**
     * Display formations by category.
     */
    #[Route('/categorie/{slug}', name: 'app_formations_by_category', methods: ['GET'])]
    public function byCategory(string $slug, Request $request): Response
    {
        $category = $this->categoryRepository->findBySlugWithActiveFormations($slug);

        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        // Add category filter and redirect to main index
        $queryParams = $request->query->all();
        $queryParams['category'] = $slug;

        return $this->redirectToRoute('app_formations_index', $queryParams);
    }

    /**
     * Display detailed view of a formation.
     */
    #[Route('/{slug}', name: 'app_formation_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $formation = $this->formationRepository->findBySlugWithCategory($slug);

        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        // Get upcoming sessions for this formation
        $upcomingSessions = $formation->getUpcomingSessions();

        // Get open sessions (available for registration)
        $openSessions = $formation->getOpenSessions();

        // Get similar formations
        $similarFormations = $this->formationRepository->findSimilarFormations($formation, 4);

        // Log formation view for analytics
        $this->logger->info('Formation viewed', [
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'upcoming_sessions_count' => $upcomingSessions->count(),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        return $this->render('public/formation/show.html.twig', [
            'formation' => $formation,
            'upcoming_sessions' => $upcomingSessions,
            'open_sessions' => $openSessions,
            'similar_formations' => $similarFormations,
        ]);
    }



    /**
     * Ajax endpoint for formation filtering.
     */
    #[Route('/api/filter', name: 'app_formations_ajax_filter', methods: ['GET'])]
    public function ajaxFilter(Request $request): Response
    {
        // Get filter parameters from request
        $filters = $this->extractFilters($request);

        // Get formations based on filters
        $queryBuilder = $this->formationRepository->createCatalogQueryBuilder($filters);

        // Handle pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12; // Formations per page
        $offset = ($page - 1) * $limit;

        $formations = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        // Get total count for pagination
        $totalFormations = $this->formationRepository->countByFilters($filters);
        $totalPages = ceil($totalFormations / $limit);

        return $this->render('public/formation/_formations_list.html.twig', [
            'formations' => $formations,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_formations' => $totalFormations,
        ]);
    }

    /**
     * Extract filters from request parameters.
     *
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request): array
    {
        $filters = [];

        // Category filter
        if ($category = $request->query->get('category')) {
            $filters['category'] = $category;
        }

        // Level filter
        if ($level = $request->query->get('level')) {
            $filters['level'] = $level;
        }

        // Format filter
        if ($format = $request->query->get('format')) {
            $filters['format'] = $format;
        }

        // Price range filters
        if ($minPrice = $request->query->get('min_price')) {
            $filters['minPrice'] = (float) $minPrice;
        }
        if ($maxPrice = $request->query->get('max_price')) {
            $filters['maxPrice'] = (float) $maxPrice;
        }

        // Duration range filters
        if ($minDuration = $request->query->get('min_duration')) {
            $filters['minDuration'] = (int) $minDuration;
        }
        if ($maxDuration = $request->query->get('max_duration')) {
            $filters['maxDuration'] = (int) $maxDuration;
        }

        // Search filter
        if ($search = $request->query->get('search')) {
            $filters['search'] = trim($search);
        }

        // Sorting
        $filters['sortBy'] = $request->query->get('sort_by', 'createdAt');
        $filters['sortOrder'] = $request->query->get('sort_order', 'DESC');

        return $filters;
    }

    /**
     * Get available filter options for the filter form.
     *
     * @return array<string, mixed>
     */
    private function getFilterOptions(): array
    {
        return [
            'categories' => $this->categoryRepository->findCategoriesWithActiveFormations(),
            'levels' => $this->formationRepository->getAvailableLevels(),
            'formats' => $this->formationRepository->getAvailableFormats(),
            'price_range' => $this->formationRepository->getPriceRange(),
            'duration_range' => $this->formationRepository->getDurationRange(),
        ];
    }
}
