<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Formation;
use App\Form\Training\FormationType;
use App\Repository\Training\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Admin Formation Controller.
 *
 * Handles CRUD operations for formations in the admin interface.
 * Provides comprehensive management capabilities for EPROFOS formations
 * with Qualiopi compliance and image upload support.
 */
#[Route('/admin/formations')]
#[IsGranted('ROLE_ADMIN')]
class FormationController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private SluggerInterface $slugger,
    ) {}

    /**
     * List all formations with pagination and filtering.
     */
    #[Route('/', name: 'admin_formation_index', methods: ['GET'])]
    public function index(Request $request, FormationRepository $formationRepository): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin formations list access started', [
            'user' => $userIdentifier,
            'request_uri' => $request->getRequestUri(),
            'method' => $request->getMethod(),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        try {
            // Get filter parameters
            $filters = [
                'search' => $request->query->get('search', ''),
                'category' => $request->query->get('category', ''),
                'level' => $request->query->get('level', ''),
                'format' => $request->query->get('format', ''),
                'status' => $request->query->get('status', ''),
                'sortBy' => $request->query->get('sortBy', 'createdAt'),
                'sortOrder' => $request->query->get('sortOrder', 'DESC'),
            ];

            $this->logger->debug('Filter parameters extracted', [
                'user' => $userIdentifier,
                'filters' => $filters,
                'has_filters' => !empty(array_filter($filters, static fn ($value) => $value !== null && $value !== '')),
            ]);

            // Create a copy for query building (without empty values)
            $activeFilters = array_filter($filters, static fn ($value) => $value !== null && $value !== '');

            $this->logger->debug('Active filters processed', [
                'user' => $userIdentifier,
                'active_filters' => $activeFilters,
                'active_filters_count' => count($activeFilters),
            ]);

            // Build query with filters
            $queryBuilder = $formationRepository->createQueryBuilder('f')
                ->leftJoin('f.category', 'c')
                ->addSelect('c')
            ;

            $this->logger->debug('Base query builder created', [
                'user' => $userIdentifier,
                'query_alias' => 'f',
                'joined_entities' => ['category'],
            ]);

            // Apply search filter
            if (!empty($activeFilters['search'])) {
                $this->logger->debug('Applying search filter', [
                    'user' => $userIdentifier,
                    'search_term' => $activeFilters['search'],
                ]);
                
                $queryBuilder
                    ->andWhere('f.title LIKE :search OR f.description LIKE :search')
                    ->setParameter('search', '%' . $activeFilters['search'] . '%')
                ;
            }

            // Apply category filter
            if (!empty($activeFilters['category'])) {
                $this->logger->debug('Applying category filter', [
                    'user' => $userIdentifier,
                    'category_slug' => $activeFilters['category'],
                ]);
                
                $queryBuilder
                    ->andWhere('c.slug = :category')
                    ->setParameter('category', $activeFilters['category'])
                ;
            }

            // Apply level filter
            if (!empty($activeFilters['level'])) {
                $this->logger->debug('Applying level filter', [
                    'user' => $userIdentifier,
                    'level' => $activeFilters['level'],
                ]);
                
                $queryBuilder
                    ->andWhere('f.level = :level')
                    ->setParameter('level', $activeFilters['level'])
                ;
            }

            // Apply format filter
            if (!empty($activeFilters['format'])) {
                $this->logger->debug('Applying format filter', [
                    'user' => $userIdentifier,
                    'format' => $activeFilters['format'],
                ]);
                
                $queryBuilder
                    ->andWhere('f.format = :format')
                    ->setParameter('format', $activeFilters['format'])
                ;
            }

            // Apply status filter
            if (!empty($activeFilters['status'])) {
                $isActive = $activeFilters['status'] === 'active';
                $this->logger->debug('Applying status filter', [
                    'user' => $userIdentifier,
                    'status' => $activeFilters['status'],
                    'is_active_value' => $isActive,
                ]);
                
                $queryBuilder
                    ->andWhere('f.isActive = :status')
                    ->setParameter('status', $isActive)
                ;
            }

            // Apply sorting
            $sortBy = $filters['sortBy'] ?? 'createdAt';
            $sortOrder = $filters['sortOrder'] ?? 'DESC';

            $this->logger->debug('Applying sorting', [
                'user' => $userIdentifier,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]);

            switch ($sortBy) {
                case 'title':
                    $queryBuilder->orderBy('f.title', $sortOrder);
                    break;

                case 'category':
                    $queryBuilder->orderBy('c.name', $sortOrder);
                    break;

                case 'price':
                    $queryBuilder->orderBy('f.price', $sortOrder);
                    break;

                case 'level':
                    $queryBuilder->orderBy('f.level', $sortOrder);
                    break;

                default:
                    $queryBuilder->orderBy('f.createdAt', $sortOrder);
            }

            $this->logger->debug('Executing formations query', [
                'user' => $userIdentifier,
                'query_dql' => $queryBuilder->getQuery()->getDQL(),
                'query_parameters' => $queryBuilder->getQuery()->getParameters()->toArray(),
            ]);

            $formations = $queryBuilder->getQuery()->getResult();
            $formationsCount = count($formations);

            $this->logger->info('Formations query executed successfully', [
                'user' => $userIdentifier,
                'formations_count' => $formationsCount,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            // Get filter options for dropdowns
            $this->logger->debug('Loading filter options', [
                'user' => $userIdentifier,
            ]);

            $categories = $formationRepository->createQueryBuilder('f')
                ->select('DISTINCT c.name, c.slug')
                ->leftJoin('f.category', 'c')
                ->where('c.id IS NOT NULL')
                ->orderBy('c.name', 'ASC')
                ->getQuery()
                ->getResult()
            ;

            $levels = $formationRepository->getAvailableLevels();
            $formats = $formationRepository->getAvailableFormats();

            $this->logger->debug('Filter options loaded', [
                'user' => $userIdentifier,
                'categories_count' => count($categories),
                'levels_count' => count($levels),
                'formats_count' => count($formats),
            ]);

            $endTime = microtime(true);
            $this->logger->info('Admin formations list completed successfully', [
                'user' => $userIdentifier,
                'total_execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
                'formations_count' => $formationsCount,
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/formation/index.html.twig', [
                'formations' => $formations,
                'filters' => $filters,
                'categories' => $categories,
                'levels' => $levels,
                'formats' => $formats,
                'page_title' => 'Gestion des formations',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Formations', 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in admin formations list', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des formations. Veuillez réessayer.');
            
            // Return basic template with empty data in case of error
            return $this->render('admin/formation/index.html.twig', [
                'formations' => [],
                'filters' => $filters ?? [],
                'categories' => [],
                'levels' => [],
                'formats' => [],
                'page_title' => 'Gestion des formations',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Formations', 'url' => null],
                ],
            ]);
        }
    }

    /**
     * Show formation details.
     */
    #[Route('/{id}', name: 'admin_formation_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Formation $formation): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin formation details view started', [
            'user' => $userIdentifier,
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'formation_slug' => $formation->getSlug(),
        ]);

        try {
            $this->logger->debug('Loading formation details', [
                'user' => $userIdentifier,
                'formation_id' => $formation->getId(),
                'formation_category' => $formation->getCategory()?->getName(),
                'formation_level' => $formation->getLevel(),
                'formation_format' => $formation->getFormat(),
                'formation_status' => $formation->isActive() ? 'active' : 'inactive',
                'formation_featured' => $formation->isFeatured() ? 'yes' : 'no',
                'formation_price' => $formation->getPrice(),
                'modules_count' => $formation->getModules()->count(),
                'contact_requests_count' => $formation->getContactRequests()->count(),
            ]);

            $endTime = microtime(true);
            $this->logger->info('Admin formation details view completed successfully', [
                'user' => $userIdentifier,
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/formation/show.html.twig', [
                'formation' => $formation,
                'page_title' => 'Détails de la formation',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Formations', 'url' => $this->generateUrl('admin_formation_index')],
                    ['label' => $formation->getTitle(), 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in admin formation details view', [
                'user' => $userIdentifier,
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails de la formation.');
            
            return $this->redirectToRoute('admin_formation_index');
        }
    }

    /**
     * Create a new formation.
     */
    #[Route('/new', name: 'admin_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin new formation process started', [
            'user' => $userIdentifier,
            'method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            $formation = new Formation();
            $form = $this->createForm(FormationType::class, $formation);
            
            $this->logger->debug('Formation form created', [
                'user' => $userIdentifier,
                'form_name' => $form->getName(),
                'formation_entity_created' => true,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Formation form submitted', [
                    'user' => $userIdentifier,
                    'form_valid' => $form->isValid(),
                    'form_errors_count' => $form->getErrors(true)->count(),
                ]);

                if (!$form->isValid()) {
                    $formErrors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $formErrors[] = $error->getMessage();
                    }
                    
                    $this->logger->warning('Formation form validation failed', [
                        'user' => $userIdentifier,
                        'form_errors' => $formErrors,
                        'submitted_data' => $form->getData(),
                    ]);
                }

                if ($form->isValid()) {
                    $this->logger->debug('Processing valid formation form', [
                        'user' => $userIdentifier,
                        'formation_title' => $formation->getTitle(),
                        'formation_category' => $formation->getCategory()?->getName(),
                        'formation_level' => $formation->getLevel(),
                        'formation_format' => $formation->getFormat(),
                        'formation_price' => $formation->getPrice(),
                    ]);

                    // Generate slug from title
                    $originalSlug = $this->slugger->slug($formation->getTitle())->lower()->toString();
                    $formation->setSlug($originalSlug);
                    
                    $this->logger->debug('Formation slug generated', [
                        'user' => $userIdentifier,
                        'formation_title' => $formation->getTitle(),
                        'generated_slug' => $originalSlug,
                    ]);

                    // Handle image upload
                    $imageFile = $form->get('imageFile')->getData();
                    if ($imageFile) {
                        $this->logger->debug('Processing image upload', [
                            'user' => $userIdentifier,
                            'original_filename' => $imageFile->getClientOriginalName(),
                            'file_size' => $imageFile->getSize(),
                            'mime_type' => $imageFile->getMimeType(),
                        ]);

                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $this->slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                        $uploadDirectory = $this->getParameter('formations_images_directory') ?? 'public/uploads/formations';

                        try {
                            $imageFile->move($uploadDirectory, $newFilename);
                            $formation->setImage($newFilename);
                            
                            $this->logger->info('Formation image uploaded successfully', [
                                'user' => $userIdentifier,
                                'original_filename' => $imageFile->getClientOriginalName(),
                                'new_filename' => $newFilename,
                                'upload_directory' => $uploadDirectory,
                                'file_size' => $imageFile->getSize(),
                            ]);
                            
                        } catch (FileException $e) {
                            $this->logger->error('Failed to upload formation image', [
                                'user' => $userIdentifier,
                                'original_filename' => $imageFile->getClientOriginalName(),
                                'target_filename' => $newFilename,
                                'upload_directory' => $uploadDirectory,
                                'error_message' => $e->getMessage(),
                                'error_code' => $e->getCode(),
                                'stack_trace' => $e->getTraceAsString(),
                            ]);
                            
                            $this->addFlash('error', 'Erreur lors du téléchargement de l\'image.');
                        }
                    } else {
                        $this->logger->debug('No image file provided for formation', [
                            'user' => $userIdentifier,
                            'formation_title' => $formation->getTitle(),
                        ]);
                    }

                    $this->logger->debug('Persisting new formation to database', [
                        'user' => $userIdentifier,
                        'formation_title' => $formation->getTitle(),
                        'formation_slug' => $formation->getSlug(),
                        'has_image' => $formation->getImage() !== null,
                    ]);

                    $entityManager->persist($formation);
                    $entityManager->flush();

                    $endTime = microtime(true);
                    $this->logger->info('New formation created successfully', [
                        'user' => $userIdentifier,
                        'formation_id' => $formation->getId(),
                        'formation_title' => $formation->getTitle(),
                        'formation_slug' => $formation->getSlug(),
                        'formation_category' => $formation->getCategory()?->getName(),
                        'formation_level' => $formation->getLevel(),
                        'formation_format' => $formation->getFormat(),
                        'formation_price' => $formation->getPrice(),
                        'has_image' => $formation->getImage() !== null,
                        'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
                        'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    ]);

                    $this->addFlash('success', 'La formation a été créée avec succès.');

                    return $this->redirectToRoute('admin_formation_index');
                }
            } else {
                $this->logger->debug('Formation form not submitted, displaying form', [
                    'user' => $userIdentifier,
                    'method' => $request->getMethod(),
                ]);
            }

            $endTime = microtime(true);
            $this->logger->debug('Rendering new formation form', [
                'user' => $userIdentifier,
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
                'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
            ]);

            return $this->render('admin/formation/new.html.twig', [
                'formation' => $formation,
                'form' => $form,
                'page_title' => 'Nouvelle formation',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Formations', 'url' => $this->generateUrl('admin_formation_index')],
                    ['label' => 'Nouvelle formation', 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in new formation process', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'form_submitted' => isset($form) ? $form->isSubmitted() : false,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création de la formation. Veuillez réessayer.');
            
            return $this->redirectToRoute('admin_formation_index');
        }
    }

    /**
     * Edit an existing formation.
     */
    #[Route('/{id}/edit', name: 'admin_formation_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin formation edit process started', [
            'user' => $userIdentifier,
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'formation_slug' => $formation->getSlug(),
            'method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            // Store original values for comparison
            $originalTitle = $formation->getTitle();
            $originalSlug = $formation->getSlug();
            $originalImage = $formation->getImage();
            $originalCategory = $formation->getCategory()?->getName();
            $originalLevel = $formation->getLevel();
            $originalFormat = $formation->getFormat();
            $originalPrice = $formation->getPrice();
            $originalStatus = $formation->isActive();

            $this->logger->debug('Original formation values stored for comparison', [
                'user' => $userIdentifier,
                'formation_id' => $formation->getId(),
                'original_title' => $originalTitle,
                'original_slug' => $originalSlug,
                'original_image' => $originalImage,
                'original_category' => $originalCategory,
                'original_level' => $originalLevel,
                'original_format' => $originalFormat,
                'original_price' => $originalPrice,
                'original_status' => $originalStatus ? 'active' : 'inactive',
            ]);

            $form = $this->createForm(FormationType::class, $formation);
            
            $this->logger->debug('Formation edit form created', [
                'user' => $userIdentifier,
                'formation_id' => $formation->getId(),
                'form_name' => $form->getName(),
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Formation edit form submitted', [
                    'user' => $userIdentifier,
                    'formation_id' => $formation->getId(),
                    'form_valid' => $form->isValid(),
                    'form_errors_count' => $form->getErrors(true)->count(),
                ]);

                if (!$form->isValid()) {
                    $formErrors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $formErrors[] = $error->getMessage();
                    }
                    
                    $this->logger->warning('Formation edit form validation failed', [
                        'user' => $userIdentifier,
                        'formation_id' => $formation->getId(),
                        'form_errors' => $formErrors,
                    ]);
                }

                if ($form->isValid()) {
                    $this->logger->debug('Processing valid formation edit form', [
                        'user' => $userIdentifier,
                        'formation_id' => $formation->getId(),
                        'new_title' => $formation->getTitle(),
                        'new_category' => $formation->getCategory()?->getName(),
                        'new_level' => $formation->getLevel(),
                        'new_format' => $formation->getFormat(),
                        'new_price' => $formation->getPrice(),
                        'title_changed' => $originalTitle !== $formation->getTitle(),
                    ]);

                    // Update slug if title changed
                    if ($originalTitle !== $formation->getTitle()) {
                        $newSlug = $this->slugger->slug($formation->getTitle())->lower()->toString();
                        $formation->setSlug($newSlug);
                        
                        $this->logger->info('Formation slug updated due to title change', [
                            'user' => $userIdentifier,
                            'formation_id' => $formation->getId(),
                            'original_title' => $originalTitle,
                            'new_title' => $formation->getTitle(),
                            'original_slug' => $originalSlug,
                            'new_slug' => $newSlug,
                        ]);
                    }

                    // Handle image upload
                    $imageFile = $form->get('imageFile')->getData();
                    if ($imageFile) {
                        $this->logger->debug('Processing new image upload for formation edit', [
                            'user' => $userIdentifier,
                            'formation_id' => $formation->getId(),
                            'original_filename' => $imageFile->getClientOriginalName(),
                            'file_size' => $imageFile->getSize(),
                            'mime_type' => $imageFile->getMimeType(),
                            'had_previous_image' => $originalImage !== null,
                        ]);

                        // Delete old image if exists
                        if ($originalImage) {
                            $uploadDirectory = $this->getParameter('formations_images_directory') ?? 'public/uploads/formations';
                            $oldImagePath = $uploadDirectory . '/' . $originalImage;
                            
                            try {
                                if (file_exists($oldImagePath)) {
                                    unlink($oldImagePath);
                                    $this->logger->info('Old formation image deleted successfully', [
                                        'user' => $userIdentifier,
                                        'formation_id' => $formation->getId(),
                                        'deleted_image_path' => $oldImagePath,
                                        'deleted_image_name' => $originalImage,
                                    ]);
                                } else {
                                    $this->logger->warning('Old formation image file not found for deletion', [
                                        'user' => $userIdentifier,
                                        'formation_id' => $formation->getId(),
                                        'expected_image_path' => $oldImagePath,
                                        'image_name' => $originalImage,
                                    ]);
                                }
                            } catch (\Exception $e) {
                                $this->logger->error('Failed to delete old formation image', [
                                    'user' => $userIdentifier,
                                    'formation_id' => $formation->getId(),
                                    'image_path' => $oldImagePath,
                                    'error_message' => $e->getMessage(),
                                    'error_code' => $e->getCode(),
                                ]);
                            }
                        }

                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $this->slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                        $uploadDirectory = $this->getParameter('formations_images_directory') ?? 'public/uploads/formations';

                        try {
                            $imageFile->move($uploadDirectory, $newFilename);
                            $formation->setImage($newFilename);
                            
                            $this->logger->info('New formation image uploaded successfully', [
                                'user' => $userIdentifier,
                                'formation_id' => $formation->getId(),
                                'original_filename' => $imageFile->getClientOriginalName(),
                                'new_filename' => $newFilename,
                                'upload_directory' => $uploadDirectory,
                                'file_size' => $imageFile->getSize(),
                                'replaced_image' => $originalImage,
                            ]);
                            
                        } catch (FileException $e) {
                            $this->logger->error('Failed to upload new formation image', [
                                'user' => $userIdentifier,
                                'formation_id' => $formation->getId(),
                                'original_filename' => $imageFile->getClientOriginalName(),
                                'target_filename' => $newFilename,
                                'upload_directory' => $uploadDirectory,
                                'error_message' => $e->getMessage(),
                                'error_code' => $e->getCode(),
                                'stack_trace' => $e->getTraceAsString(),
                            ]);
                            
                            $this->addFlash('error', 'Erreur lors du téléchargement de l\'image.');
                        }
                    } else {
                        $this->logger->debug('No new image file provided for formation edit', [
                            'user' => $userIdentifier,
                            'formation_id' => $formation->getId(),
                            'keeps_existing_image' => $originalImage !== null,
                        ]);
                    }

                    // Log changes before saving
                    $changes = [];
                    if ($originalTitle !== $formation->getTitle()) {
                        $changes['title'] = ['from' => $originalTitle, 'to' => $formation->getTitle()];
                    }
                    if ($originalCategory !== $formation->getCategory()?->getName()) {
                        $changes['category'] = ['from' => $originalCategory, 'to' => $formation->getCategory()?->getName()];
                    }
                    if ($originalLevel !== $formation->getLevel()) {
                        $changes['level'] = ['from' => $originalLevel, 'to' => $formation->getLevel()];
                    }
                    if ($originalFormat !== $formation->getFormat()) {
                        $changes['format'] = ['from' => $originalFormat, 'to' => $formation->getFormat()];
                    }
                    if ($originalPrice !== $formation->getPrice()) {
                        $changes['price'] = ['from' => $originalPrice, 'to' => $formation->getPrice()];
                    }
                    if ($originalStatus !== $formation->isActive()) {
                        $changes['status'] = ['from' => $originalStatus ? 'active' : 'inactive', 'to' => $formation->isActive() ? 'active' : 'inactive'];
                    }
                    if ($originalImage !== $formation->getImage()) {
                        $changes['image'] = ['from' => $originalImage, 'to' => $formation->getImage()];
                    }

                    $this->logger->debug('Saving formation changes to database', [
                        'user' => $userIdentifier,
                        'formation_id' => $formation->getId(),
                        'changes_count' => count($changes),
                        'changes' => $changes,
                    ]);

                    $entityManager->flush();

                    $endTime = microtime(true);
                    $this->logger->info('Formation updated successfully', [
                        'user' => $userIdentifier,
                        'formation_id' => $formation->getId(),
                        'formation_title' => $formation->getTitle(),
                        'formation_slug' => $formation->getSlug(),
                        'changes_applied' => $changes,
                        'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
                        'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    ]);

                    $this->addFlash('success', 'La formation a été modifiée avec succès.');

                    return $this->redirectToRoute('admin_formation_index');
                }
            } else {
                $this->logger->debug('Formation edit form not submitted, displaying form', [
                    'user' => $userIdentifier,
                    'formation_id' => $formation->getId(),
                    'method' => $request->getMethod(),
                ]);
            }

            $endTime = microtime(true);
            $this->logger->debug('Rendering formation edit form', [
                'user' => $userIdentifier,
                'formation_id' => $formation->getId(),
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null,
                'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
            ]);

            return $this->render('admin/formation/edit.html.twig', [
                'formation' => $formation,
                'form' => $form,
                'page_title' => 'Modifier la formation',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Formations', 'url' => $this->generateUrl('admin_formation_index')],
                    ['label' => $formation->getTitle(), 'url' => $this->generateUrl('admin_formation_show', ['id' => $formation->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in formation edit process', [
                'user' => $userIdentifier,
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'form_submitted' => isset($form) ? $form->isSubmitted() : false,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification de la formation. Veuillez réessayer.');
            
            return $this->redirectToRoute('admin_formation_index');
        }
    }

    /**
     * Delete a formation.
     */
    #[Route('/{id}', name: 'admin_formation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $formationId = $formation->getId();
        $formationTitle = $formation->getTitle();
        $formationSlug = $formation->getSlug();
        $formationImage = $formation->getImage();
        
        $this->logger->info('Admin formation deletion process started', [
            'user' => $userIdentifier,
            'formation_id' => $formationId,
            'formation_title' => $formationTitle,
            'formation_slug' => $formationSlug,
            'formation_image' => $formationImage,
            'request_uri' => $request->getRequestUri(),
            'ip_address' => $request->getClientIp(),
            'csrf_token_provided' => $request->getPayload()->has('_token'),
        ]);

        try {
            $csrfToken = $request->getPayload()->get('_token');
            $expectedTokenId = 'delete' . $formationId;
            
            $this->logger->debug('Validating CSRF token for formation deletion', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'token_id' => $expectedTokenId,
                'token_provided' => $csrfToken !== null,
            ]);

            if (!$this->isCsrfTokenValid($expectedTokenId, $csrfToken)) {
                $this->logger->warning('Invalid CSRF token for formation deletion', [
                    'user' => $userIdentifier,
                    'formation_id' => $formationId,
                    'formation_title' => $formationTitle,
                    'token_id' => $expectedTokenId,
                    'provided_token' => $csrfToken,
                    'ip_address' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
                return $this->redirectToRoute('admin_formation_index');
            }

            $this->logger->debug('CSRF token validated successfully', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
            ]);

            // Check if formation has contact requests
            $contactRequestsCount = $formation->getContactRequests()->count();
            $modulesCount = $formation->getModules()->count();
            
            $this->logger->debug('Checking formation dependencies before deletion', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'contact_requests_count' => $contactRequestsCount,
                'modules_count' => $modulesCount,
            ]);

            if ($contactRequestsCount > 0) {
                $this->logger->warning('Formation deletion blocked due to existing contact requests', [
                    'user' => $userIdentifier,
                    'formation_id' => $formationId,
                    'formation_title' => $formationTitle,
                    'contact_requests_count' => $contactRequestsCount,
                    'modules_count' => $modulesCount,
                ]);

                $this->addFlash('error', 'Impossible de supprimer cette formation car elle a des demandes de contact associées.');
                return $this->redirectToRoute('admin_formation_index');
            }

            $this->logger->info('Formation dependencies check passed, proceeding with deletion', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'can_delete' => true,
            ]);

            // Delete image if exists
            if ($formationImage) {
                $uploadDirectory = $this->getParameter('formations_images_directory') ?? 'public/uploads/formations';
                $imagePath = $uploadDirectory . '/' . $formationImage;
                
                $this->logger->debug('Attempting to delete formation image file', [
                    'user' => $userIdentifier,
                    'formation_id' => $formationId,
                    'image_name' => $formationImage,
                    'image_path' => $imagePath,
                    'file_exists' => file_exists($imagePath),
                ]);

                try {
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                        $this->logger->info('Formation image file deleted successfully', [
                            'user' => $userIdentifier,
                            'formation_id' => $formationId,
                            'formation_title' => $formationTitle,
                            'deleted_image_path' => $imagePath,
                            'deleted_image_name' => $formationImage,
                        ]);
                    } else {
                        $this->logger->warning('Formation image file not found for deletion', [
                            'user' => $userIdentifier,
                            'formation_id' => $formationId,
                            'formation_title' => $formationTitle,
                            'expected_image_path' => $imagePath,
                            'image_name' => $formationImage,
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Failed to delete formation image file', [
                        'user' => $userIdentifier,
                        'formation_id' => $formationId,
                        'formation_title' => $formationTitle,
                        'image_path' => $imagePath,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'stack_trace' => $e->getTraceAsString(),
                    ]);
                    // Continue with entity deletion even if image deletion fails
                }
            } else {
                $this->logger->debug('No image file to delete for formation', [
                    'user' => $userIdentifier,
                    'formation_id' => $formationId,
                    'formation_title' => $formationTitle,
                ]);
            }

            $this->logger->debug('Removing formation entity from database', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'formation_slug' => $formationSlug,
            ]);

            $entityManager->remove($formation);
            $entityManager->flush();

            $endTime = microtime(true);
            $this->logger->info('Formation deleted successfully', [
                'user' => $userIdentifier,
                'deleted_formation_id' => $formationId,
                'deleted_formation_title' => $formationTitle,
                'deleted_formation_slug' => $formationSlug,
                'had_image' => $formationImage !== null,
                'deleted_image' => $formationImage,
                'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('success', 'La formation a été supprimée avec succès.');

        } catch (\Exception $e) {
            $this->logger->error('Error in formation deletion process', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la formation. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_formation_index');
    }

    /**
     * Toggle formation active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_formation_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $formationId = $formation->getId();
        $formationTitle = $formation->getTitle();
        $currentStatus = $formation->isActive();
        
        $this->logger->info('Admin formation toggle status process started', [
            'user' => $userIdentifier,
            'formation_id' => $formationId,
            'formation_title' => $formationTitle,
            'current_status' => $currentStatus ? 'active' : 'inactive',
            'target_status' => $currentStatus ? 'inactive' : 'active',
            'request_uri' => $request->getRequestUri(),
            'ip_address' => $request->getClientIp(),
            'csrf_token_provided' => $request->getPayload()->has('_token'),
        ]);

        try {
            $csrfToken = $request->getPayload()->get('_token');
            $expectedTokenId = 'toggle_status' . $formationId;
            
            $this->logger->debug('Validating CSRF token for formation status toggle', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'token_id' => $expectedTokenId,
                'token_provided' => $csrfToken !== null,
            ]);

            if (!$this->isCsrfTokenValid($expectedTokenId, $csrfToken)) {
                $this->logger->warning('Invalid CSRF token for formation status toggle', [
                    'user' => $userIdentifier,
                    'formation_id' => $formationId,
                    'formation_title' => $formationTitle,
                    'token_id' => $expectedTokenId,
                    'provided_token' => $csrfToken,
                    'ip_address' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
                return $this->redirectToRoute('admin_formation_index');
            }

            $this->logger->debug('CSRF token validated successfully', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
            ]);

            $newStatus = !$currentStatus;
            $formation->setIsActive($newStatus);
            
            $this->logger->debug('Formation status changed in memory', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'previous_status' => $currentStatus ? 'active' : 'inactive',
                'new_status' => $newStatus ? 'active' : 'inactive',
            ]);

            $entityManager->flush();

            $status = $newStatus ? 'activée' : 'désactivée';
            
            $endTime = microtime(true);
            $this->logger->info('Formation status toggled successfully', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'previous_status' => $currentStatus ? 'active' : 'inactive',
                'new_status' => $newStatus ? 'active' : 'inactive',
                'status_text' => $status,
                'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('success', "La formation a été {$status} avec succès.");

        } catch (\Exception $e) {
            $this->logger->error('Error in formation status toggle process', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'current_status' => $currentStatus ? 'active' : 'inactive',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du changement de statut. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_formation_index');
    }

    /**
     * Toggle formation featured status.
     */
    #[Route('/{id}/toggle-featured', name: 'admin_formation_toggle_featured', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleFeatured(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $formationId = $formation->getId();
        $formationTitle = $formation->getTitle();
        $currentFeaturedStatus = $formation->isFeatured();
        
        $this->logger->info('Admin formation toggle featured status process started', [
            'user' => $userIdentifier,
            'formation_id' => $formationId,
            'formation_title' => $formationTitle,
            'current_featured_status' => $currentFeaturedStatus ? 'featured' : 'not_featured',
            'target_featured_status' => $currentFeaturedStatus ? 'not_featured' : 'featured',
            'request_uri' => $request->getRequestUri(),
            'ip_address' => $request->getClientIp(),
            'csrf_token_provided' => $request->getPayload()->has('_token'),
        ]);

        try {
            $csrfToken = $request->getPayload()->get('_token');
            $expectedTokenId = 'toggle_featured' . $formationId;
            
            $this->logger->debug('Validating CSRF token for formation featured status toggle', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'token_id' => $expectedTokenId,
                'token_provided' => $csrfToken !== null,
            ]);

            if (!$this->isCsrfTokenValid($expectedTokenId, $csrfToken)) {
                $this->logger->warning('Invalid CSRF token for formation featured status toggle', [
                    'user' => $userIdentifier,
                    'formation_id' => $formationId,
                    'formation_title' => $formationTitle,
                    'token_id' => $expectedTokenId,
                    'provided_token' => $csrfToken,
                    'ip_address' => $request->getClientIp(),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
                return $this->redirectToRoute('admin_formation_index');
            }

            $this->logger->debug('CSRF token validated successfully', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
            ]);

            $newFeaturedStatus = !$currentFeaturedStatus;
            $formation->setIsFeatured($newFeaturedStatus);
            
            $this->logger->debug('Formation featured status changed in memory', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'previous_featured_status' => $currentFeaturedStatus ? 'featured' : 'not_featured',
                'new_featured_status' => $newFeaturedStatus ? 'featured' : 'not_featured',
            ]);

            $entityManager->flush();

            $status = $newFeaturedStatus ? 'mise en avant' : 'retirée de la mise en avant';
            
            $endTime = microtime(true);
            $this->logger->info('Formation featured status toggled successfully', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'previous_featured_status' => $currentFeaturedStatus ? 'featured' : 'not_featured',
                'new_featured_status' => $newFeaturedStatus ? 'featured' : 'not_featured',
                'status_text' => $status,
                'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('success', "La formation a été {$status} avec succès.");

        } catch (\Exception $e) {
            $this->logger->error('Error in formation featured status toggle process', [
                'user' => $userIdentifier,
                'formation_id' => $formationId,
                'formation_title' => $formationTitle,
                'current_featured_status' => $currentFeaturedStatus ? 'featured' : 'not_featured',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du changement de statut de mise en avant. Veuillez réessayer.');
        }

        return $this->redirectToRoute('admin_formation_index');
    }
}
