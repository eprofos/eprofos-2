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
    #[Route('', name: 'public_formations_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('formation_index_', true);
        
        try {
            $this->logger->info('Formation catalog index requested', [
                'request_id' => $requestId,
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'is_ajax' => $request->isXmlHttpRequest(),
                'query_params' => $request->query->all(),
            ]);

            // Get filter parameters from request
            $filters = $this->extractFilters($request);
            $this->logger->debug('Filters extracted', [
                'request_id' => $requestId,
                'filters' => $filters,
                'filter_count' => count($filters),
            ]);

            // Get formations based on filters
            $queryBuilder = $this->formationRepository->createCatalogQueryBuilder($filters);
            $this->logger->debug('Query builder created for catalog', [
                'request_id' => $requestId,
                'filters_applied' => array_keys($filters),
            ]);

            // Handle pagination
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 12; // Formations per page
            $offset = ($page - 1) * $limit;
            
            $this->logger->debug('Pagination parameters calculated', [
                'request_id' => $requestId,
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            $formations = $queryBuilder
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
            ;
            
            $this->logger->debug('Formations retrieved from database', [
                'request_id' => $requestId,
                'formations_count' => count($formations),
                'page' => $page,
                'limit' => $limit,
            ]);

            // Get total count for pagination
            $totalFormations = $this->formationRepository->countByFilters($filters);
            $totalPages = ceil($totalFormations / $limit);
            
            $this->logger->debug('Pagination metadata calculated', [
                'request_id' => $requestId,
                'total_formations' => $totalFormations,
                'total_pages' => $totalPages,
                'current_page' => $page,
            ]);

            // Get filter options for the form
            $filterOptions = $this->getFilterOptions();
            $this->logger->debug('Filter options retrieved', [
                'request_id' => $requestId,
                'categories_count' => count($filterOptions['categories'] ?? []),
                'levels_count' => count($filterOptions['levels'] ?? []),
                'formats_count' => count($filterOptions['formats'] ?? []),
            ]);

            // Get active categories for navigation
            $categories = $this->categoryRepository->findCategoriesWithActiveFormations();
            $this->logger->debug('Active categories retrieved', [
                'request_id' => $requestId,
                'active_categories_count' => count($categories),
            ]);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            // If it's an Ajax request, return only the formations list
            if ($request->isXmlHttpRequest()) {
                $this->logger->info('Ajax formation list response prepared', [
                    'request_id' => $requestId,
                    'formations_count' => count($formations),
                    'total_formations' => $totalFormations,
                    'processing_time_ms' => $processingTime,
                ]);

                return $this->render('public/formation/_formations_list.html.twig', [
                    'formations' => $formations,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_formations' => $totalFormations,
                ]);
            }

            $this->logger->info('Formation catalog index response prepared', [
                'request_id' => $requestId,
                'formations_count' => count($formations),
                'categories_count' => count($categories),
                'total_formations' => $totalFormations,
                'processing_time_ms' => $processingTime,
                'template' => 'public/formation/index.html.twig',
            ]);

            return $this->render('public/formation/index.html.twig', [
                'formations' => $formations,
                'categories' => $categories,
                'filter_options' => $filterOptions,
                'current_filters' => $filters,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_formations' => $totalFormations,
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in formation catalog index', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'filters' => $filters ?? [],
            ]);

            throw $this->createNotFoundException('Erreur lors du chargement du catalogue de formations');

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in formation catalog index', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'filters' => $filters ?? [],
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $this->createNotFoundException('Une erreur inattendue s\'est produite');
        }
    }

    /**
     * Display formations by category.
     */
    #[Route('/categorie/{slug}', name: 'public_formations_by_category', methods: ['GET'])]
    public function byCategory(string $slug, Request $request): Response
    {
        $requestId = uniqid('formation_by_category_', true);
        
        try {
            $this->logger->info('Formation by category requested', [
                'request_id' => $requestId,
                'category_slug' => $slug,
                'user_ip' => $request->getClientIp(),
                'query_params' => $request->query->all(),
            ]);

            $category = $this->categoryRepository->findBySlugWithActiveFormations($slug);

            if (!$category) {
                $this->logger->warning('Category not found', [
                    'request_id' => $requestId,
                    'category_slug' => $slug,
                    'user_ip' => $request->getClientIp(),
                ]);

                throw $this->createNotFoundException('Catégorie non trouvée');
            }

            $this->logger->debug('Category found, preparing redirect', [
                'request_id' => $requestId,
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'category_slug' => $slug,
            ]);

            // Add category filter and redirect to main index
            $queryParams = $request->query->all();
            $queryParams['category'] = $slug;

            $this->logger->info('Redirecting to formations index with category filter', [
                'request_id' => $requestId,
                'category_slug' => $slug,
                'final_query_params' => $queryParams,
            ]);

            return $this->redirectToRoute('public_formations_index', $queryParams);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in formation by category', [
                'request_id' => $requestId,
                'category_slug' => $slug,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $this->createNotFoundException('Erreur lors du chargement de la catégorie');

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in formation by category', [
                'request_id' => $requestId,
                'category_slug' => $slug,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw $this->createNotFoundException('Une erreur inattendue s\'est produite');
        }
    }

    /**
     * Display detailed view of a formation.
     */
    #[Route('/{slug}', name: 'public_formation_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('formation_show_', true);
        
        try {
            $this->logger->info('Formation detail view requested', [
                'request_id' => $requestId,
                'formation_slug' => $slug,
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            ]);

            $formation = $this->formationRepository->findBySlugWithCategory($slug);

            if (!$formation) {
                $this->logger->warning('Formation not found', [
                    'request_id' => $requestId,
                    'formation_slug' => $slug,
                    'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                throw $this->createNotFoundException('Formation non trouvée');
            }

            $this->logger->debug('Formation found, retrieving sessions', [
                'request_id' => $requestId,
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'formation_category' => $formation->getCategory()?->getName(),
            ]);

            // Get upcoming sessions for this formation
            $upcomingSessions = $formation->getUpcomingSessions();
            $this->logger->debug('Upcoming sessions retrieved', [
                'request_id' => $requestId,
                'formation_id' => $formation->getId(),
                'upcoming_sessions_count' => $upcomingSessions->count(),
            ]);

            // Get open sessions (available for registration)
            $openSessions = $formation->getOpenSessions();
            $this->logger->debug('Open sessions retrieved', [
                'request_id' => $requestId,
                'formation_id' => $formation->getId(),
                'open_sessions_count' => $openSessions->count(),
            ]);

            // Get similar formations
            $similarFormations = $this->formationRepository->findSimilarFormations($formation, 4);
            $this->logger->debug('Similar formations retrieved', [
                'request_id' => $requestId,
                'formation_id' => $formation->getId(),
                'similar_formations_count' => count($similarFormations),
                'similar_formation_ids' => array_map(fn($f) => $f->getId(), $similarFormations),
            ]);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            // Log formation view for analytics
            $this->logger->info('Formation viewed', [
                'request_id' => $requestId,
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'formation_category' => $formation->getCategory()?->getName(),
                'formation_level' => $formation->getLevel(),
                'formation_price' => $formation->getPrice(),
                'upcoming_sessions_count' => $upcomingSessions->count(),
                'open_sessions_count' => $openSessions->count(),
                'similar_formations_count' => count($similarFormations),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'processing_time_ms' => $processingTime,
                'template' => 'public/formation/show.html.twig',
            ]);

            return $this->render('public/formation/show.html.twig', [
                'formation' => $formation,
                'upcoming_sessions' => $upcomingSessions,
                'open_sessions' => $openSessions,
                'similar_formations' => $similarFormations,
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in formation show', [
                'request_id' => $requestId,
                'formation_slug' => $slug,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $this->createNotFoundException('Erreur lors du chargement de la formation');

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in formation show', [
                'request_id' => $requestId,
                'formation_slug' => $slug,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $this->createNotFoundException('Une erreur inattendue s\'est produite');
        }
    }



    /**
     * Ajax endpoint for formation filtering.
     */
    #[Route('/api/filter', name: 'public_formations_ajax_filter', methods: ['GET'])]
    public function ajaxFilter(Request $request): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('formation_ajax_filter_', true);
        
        try {
            $this->logger->info('Ajax formation filter requested', [
                'request_id' => $requestId,
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'query_params' => $request->query->all(),
                'referer' => $request->headers->get('Referer'),
            ]);

            // Get filter parameters from request
            $filters = $this->extractFilters($request);
            $this->logger->debug('Ajax filters extracted', [
                'request_id' => $requestId,
                'filters' => $filters,
                'filter_count' => count($filters),
                'active_filters' => array_keys($filters),
            ]);

            // Get formations based on filters
            $queryBuilder = $this->formationRepository->createCatalogQueryBuilder($filters);
            $this->logger->debug('Ajax query builder created', [
                'request_id' => $requestId,
                'filters_applied' => array_keys($filters),
            ]);

            // Handle pagination
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 12; // Formations per page
            $offset = ($page - 1) * $limit;
            
            $this->logger->debug('Ajax pagination calculated', [
                'request_id' => $requestId,
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            $formations = $queryBuilder
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
            ;
            
            $this->logger->debug('Ajax formations retrieved', [
                'request_id' => $requestId,
                'formations_count' => count($formations),
                'formation_ids' => array_map(fn($f) => $f->getId(), $formations),
                'page' => $page,
            ]);

            // Get total count for pagination
            $totalFormations = $this->formationRepository->countByFilters($filters);
            $totalPages = ceil($totalFormations / $limit);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info('Ajax formation filter response prepared', [
                'request_id' => $requestId,
                'formations_count' => count($formations),
                'total_formations' => $totalFormations,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'processing_time_ms' => $processingTime,
                'template' => 'public/formation/_formations_list.html.twig',
            ]);

            return $this->render('public/formation/_formations_list.html.twig', [
                'formations' => $formations,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_formations' => $totalFormations,
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in ajax formation filter', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'filters' => $filters ?? [],
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $this->json([
                'error' => 'Erreur lors du filtrage des formations',
                'message' => 'Une erreur de base de données s\'est produite'
            ], 500);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in ajax formation filter', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
                'filters' => $filters ?? [],
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $this->json([
                'error' => 'Erreur inattendue lors du filtrage',
                'message' => 'Une erreur inattendue s\'est produite'
            ], 500);
        }
    }

    /**
     * Extract filters from request parameters.
     *
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request): array
    {
        $requestId = uniqid('extract_filters_', true);
        
        try {
            $this->logger->debug('Starting filter extraction', [
                'request_id' => $requestId,
                'query_params' => $request->query->all(),
                'query_count' => $request->query->count(),
            ]);

            $filters = [];

            // Category filter
            if ($category = $request->query->get('category')) {
                $filters['category'] = $category;
                $this->logger->debug('Category filter applied', [
                    'request_id' => $requestId,
                    'category' => $category,
                ]);
            }

            // Level filter
            if ($level = $request->query->get('level')) {
                $filters['level'] = $level;
                $this->logger->debug('Level filter applied', [
                    'request_id' => $requestId,
                    'level' => $level,
                ]);
            }

            // Format filter
            if ($format = $request->query->get('format')) {
                $filters['format'] = $format;
                $this->logger->debug('Format filter applied', [
                    'request_id' => $requestId,
                    'format' => $format,
                ]);
            }

            // Price range filters
            if ($minPrice = $request->query->get('min_price')) {
                $filters['minPrice'] = (float) $minPrice;
                $this->logger->debug('Min price filter applied', [
                    'request_id' => $requestId,
                    'min_price' => $filters['minPrice'],
                    'raw_value' => $minPrice,
                ]);
            }
            if ($maxPrice = $request->query->get('max_price')) {
                $filters['maxPrice'] = (float) $maxPrice;
                $this->logger->debug('Max price filter applied', [
                    'request_id' => $requestId,
                    'max_price' => $filters['maxPrice'],
                    'raw_value' => $maxPrice,
                ]);
            }

            // Duration range filters
            if ($minDuration = $request->query->get('min_duration')) {
                $filters['minDuration'] = (int) $minDuration;
                $this->logger->debug('Min duration filter applied', [
                    'request_id' => $requestId,
                    'min_duration' => $filters['minDuration'],
                    'raw_value' => $minDuration,
                ]);
            }
            if ($maxDuration = $request->query->get('max_duration')) {
                $filters['maxDuration'] = (int) $maxDuration;
                $this->logger->debug('Max duration filter applied', [
                    'request_id' => $requestId,
                    'max_duration' => $filters['maxDuration'],
                    'raw_value' => $maxDuration,
                ]);
            }

            // Search filter
            if ($search = $request->query->get('search')) {
                $filters['search'] = trim($search);
                $this->logger->debug('Search filter applied', [
                    'request_id' => $requestId,
                    'search_term' => $filters['search'],
                    'search_length' => strlen($filters['search']),
                    'raw_value' => $search,
                ]);
            }

            // Sorting
            $filters['sortBy'] = $request->query->get('sort_by', 'createdAt');
            $filters['sortOrder'] = $request->query->get('sort_order', 'DESC');
            $this->logger->debug('Sorting parameters applied', [
                'request_id' => $requestId,
                'sort_by' => $filters['sortBy'],
                'sort_order' => $filters['sortOrder'],
            ]);

            $this->logger->debug('Filter extraction completed', [
                'request_id' => $requestId,
                'total_filters' => count($filters),
                'active_filters' => array_keys($filters),
                'filters_summary' => array_map(function($value) {
                    return is_string($value) ? substr($value, 0, 50) : $value;
                }, $filters),
            ]);

            return $filters;

        } catch (\Exception $e) {
            $this->logger->error('Error during filter extraction', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'query_params' => $request->query->all(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return empty filters array on error
            return [];
        }
    }

    /**
     * Get available filter options for the filter form.
     *
     * @return array<string, mixed>
     */
    private function getFilterOptions(): array
    {
        $requestId = uniqid('get_filter_options_', true);
        
        try {
            $this->logger->debug('Starting filter options retrieval', [
                'request_id' => $requestId,
            ]);

            // Get categories with active formations
            $categories = $this->categoryRepository->findCategoriesWithActiveFormations();
            $this->logger->debug('Categories with active formations retrieved', [
                'request_id' => $requestId,
                'categories_count' => count($categories),
                'category_ids' => array_map(fn($c) => $c->getId(), $categories),
            ]);

            // Get available levels
            $levels = $this->formationRepository->getAvailableLevels();
            $this->logger->debug('Available levels retrieved', [
                'request_id' => $requestId,
                'levels_count' => count($levels),
                'levels' => $levels,
            ]);

            // Get available formats
            $formats = $this->formationRepository->getAvailableFormats();
            $this->logger->debug('Available formats retrieved', [
                'request_id' => $requestId,
                'formats_count' => count($formats),
                'formats' => $formats,
            ]);

            // Get price range
            $priceRange = $this->formationRepository->getPriceRange();
            $this->logger->debug('Price range retrieved', [
                'request_id' => $requestId,
                'price_range' => $priceRange,
                'min_price' => $priceRange['min'] ?? null,
                'max_price' => $priceRange['max'] ?? null,
            ]);

            // Get duration range
            $durationRange = $this->formationRepository->getDurationRange();
            $this->logger->debug('Duration range retrieved', [
                'request_id' => $requestId,
                'duration_range' => $durationRange,
                'min_duration' => $durationRange['min'] ?? null,
                'max_duration' => $durationRange['max'] ?? null,
            ]);

            $filterOptions = [
                'categories' => $categories,
                'levels' => $levels,
                'formats' => $formats,
                'price_range' => $priceRange,
                'duration_range' => $durationRange,
            ];

            $this->logger->info('Filter options retrieval completed', [
                'request_id' => $requestId,
                'categories_count' => count($categories),
                'levels_count' => count($levels),
                'formats_count' => count($formats),
                'has_price_range' => !empty($priceRange),
                'has_duration_range' => !empty($durationRange),
            ]);

            return $filterOptions;

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during filter options retrieval', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return minimal filter options on database error
            return [
                'categories' => [],
                'levels' => [],
                'formats' => [],
                'price_range' => ['min' => 0, 'max' => 10000],
                'duration_range' => ['min' => 1, 'max' => 365],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during filter options retrieval', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Return minimal filter options on unexpected error
            return [
                'categories' => [],
                'levels' => [],
                'formats' => [],
                'price_range' => ['min' => 0, 'max' => 10000],
                'duration_range' => ['min' => 1, 'max' => 365],
            ];
        }
    }
}
