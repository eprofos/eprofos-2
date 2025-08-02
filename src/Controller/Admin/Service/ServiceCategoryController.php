<?php

declare(strict_types=1);

namespace App\Controller\Admin\Service;

use App\Entity\Service\ServiceCategory;
use App\Form\Service\ServiceCategoryType;
use App\Repository\Service\ServiceCategoryRepository;
use DateTime;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Admin Service Category Controller.
 *
 * Handles CRUD operations for service categories in the admin interface.
 * Provides full management capabilities for service categories.
 */
#[Route('/admin/service-categories')]
#[IsGranted('ROLE_ADMIN')]
class ServiceCategoryController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private SluggerInterface $slugger,
    ) {}

    /**
     * List all service categories with pagination and filtering.
     */
    #[Route('/', name: 'admin_service_category_index', methods: ['GET'])]
    public function index(ServiceCategoryRepository $serviceCategoryRepository): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin service categories list access started', [
            'admin' => $adminId,
            'timestamp' => new DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);

        try {
            $this->logger->debug('Starting service categories query', [
                'admin' => $adminId,
                'query_method' => 'findAllOrdered',
            ]);

            $serviceCategories = $serviceCategoryRepository->findAllOrdered();
            $categoriesCount = count($serviceCategories);

            $this->logger->info('Service categories list successfully retrieved', [
                'admin' => $adminId,
                'categories_count' => $categoriesCount,
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            // Log detailed categories statistics
            $categoriesWithServices = array_filter($serviceCategories, static fn ($category) => $category->getServices()->count() > 0);
            $totalServices = array_sum(array_map(static fn ($category) => $category->getServices()->count(), $serviceCategories));

            $this->logger->debug('Service categories statistics calculated', [
                'admin' => $adminId,
                'total_categories' => $categoriesCount,
                'categories_with_services' => count($categoriesWithServices),
                'empty_categories' => $categoriesCount - count($categoriesWithServices),
                'total_services_across_categories' => $totalServices,
            ]);

            return $this->render('admin/service_category/index.html.twig', [
                'service_categories' => $serviceCategories,
                'page_title' => 'Gestion des catégories de services',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de services', 'url' => null],
                ],
            ]);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error while retrieving service categories list', [
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors du chargement des catégories de services.');

            return $this->render('admin/service_category/index.html.twig', [
                'service_categories' => [],
                'page_title' => 'Gestion des catégories de services',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de services', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error while accessing service categories list', [
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer ou contacter l\'administrateur.');

            return $this->render('admin/service_category/index.html.twig', [
                'service_categories' => [],
                'page_title' => 'Gestion des catégories de services',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de services', 'url' => null],
                ],
            ]);
        }
    }

    /**
     * Show service category details.
     */
    #[Route('/{id}', name: 'admin_service_category_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ServiceCategory $serviceCategory): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin service category details view started', [
            'service_category_id' => $serviceCategory->getId(),
            'service_category_name' => $serviceCategory->getName(),
            'admin' => $adminId,
            'timestamp' => new DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            // Log category details for audit
            $servicesCount = $serviceCategory->getServices()->count();
            $activeServicesCount = $serviceCategory->getServices()->filter(static fn ($service) => $service->isActive())->count();

            $this->logger->debug('Service category details being displayed', [
                'service_category_id' => $serviceCategory->getId(),
                'service_category_name' => $serviceCategory->getName(),
                'service_category_slug' => $serviceCategory->getSlug(),
                'admin' => $adminId,
                'services_count' => $servicesCount,
                'active_services_count' => $activeServicesCount,
                'inactive_services_count' => $servicesCount - $activeServicesCount,
                'has_description' => !empty($serviceCategory->getDescription()),
                'description_length' => strlen($serviceCategory->getDescription() ?? ''),
            ]);

            $this->logger->info('Service category details successfully displayed', [
                'service_category_id' => $serviceCategory->getId(),
                'admin' => $adminId,
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            return $this->render('admin/service_category/show.html.twig', [
                'service_category' => $serviceCategory,
                'page_title' => 'Détails de la catégorie de service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de services', 'url' => $this->generateUrl('admin_service_category_index')],
                    ['label' => $serviceCategory->getName(), 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error while displaying service category details', [
                'service_category_id' => $serviceCategory->getId(),
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur s\'est produite lors de l\'affichage des détails de la catégorie de service.');

            return $this->redirectToRoute('admin_service_category_index');
        }
    }

    /**
     * Create a new service category.
     */
    #[Route('/new', name: 'admin_service_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin service category creation started', [
            'admin' => $adminId,
            'method' => $request->getMethod(),
            'timestamp' => new DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            $serviceCategory = new ServiceCategory();
            $form = $this->createForm(ServiceCategoryType::class, $serviceCategory);

            $this->logger->debug('Service category creation form initialized', [
                'admin' => $adminId,
                'form_type' => ServiceCategoryType::class,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Service category creation form submitted', [
                    'admin' => $adminId,
                    'form_valid' => $form->isValid(),
                    'submitted_data' => [
                        'name' => $serviceCategory->getName(),
                        'description_length' => strlen($serviceCategory->getDescription() ?? ''),
                    ],
                ]);

                if (!$form->isValid()) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }

                    $this->logger->warning('Service category creation form validation failed', [
                        'admin' => $adminId,
                        'validation_errors' => $errors,
                        'submitted_name' => $serviceCategory->getName(),
                    ]);
                }
            }

            if ($form->isSubmitted() && $form->isValid()) {
                // Generate slug from name
                $originalName = $serviceCategory->getName();
                $slug = $this->slugger->slug($originalName)->lower()->toString();
                $serviceCategory->setSlug($slug);

                $this->logger->debug('Service category slug generated', [
                    'admin' => $adminId,
                    'original_name' => $originalName,
                    'generated_slug' => $slug,
                ]);

                $entityManager->persist($serviceCategory);
                $entityManager->flush();

                $this->logger->info('New service category created successfully', [
                    'service_category_id' => $serviceCategory->getId(),
                    'service_category_name' => $serviceCategory->getName(),
                    'service_category_slug' => $serviceCategory->getSlug(),
                    'admin' => $adminId,
                    'creation_timestamp' => new DateTime(),
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
                    ['label' => 'Nouvelle catégorie', 'url' => null],
                ],
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->error('Service category creation failed: unique constraint violation', [
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'attempted_name' => $serviceCategory->getName() ?? 'unknown',
                'attempted_slug' => $serviceCategory->getSlug() ?? 'unknown',
                'constraint_details' => $e->getPrevious()?->getMessage(),
            ]);

            $this->addFlash('error', 'Une catégorie avec ce nom existe déjà. Veuillez choisir un nom différent.');

            return $this->render('admin/service_category/new.html.twig', [
                'service_category' => $serviceCategory,
                'form' => $form,
                'page_title' => 'Nouvelle catégorie de service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de services', 'url' => $this->generateUrl('admin_service_category_index')],
                    ['label' => 'Nouvelle catégorie', 'url' => null],
                ],
            ]);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during service category creation', [
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'attempted_data' => [
                    'name' => $serviceCategory->getName() ?? 'unknown',
                    'slug' => $serviceCategory->getSlug() ?? 'unknown',
                ],
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors de la création de la catégorie de service.');

            return $this->render('admin/service_category/new.html.twig', [
                'service_category' => $serviceCategory,
                'form' => $form,
                'page_title' => 'Nouvelle catégorie de service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de services', 'url' => $this->generateUrl('admin_service_category_index')],
                    ['label' => 'Nouvelle catégorie', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during service category creation', [
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->request->all(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer ou contacter l\'administrateur.');

            return $this->redirectToRoute('admin_service_category_index');
        }
    }

    /**
     * Edit an existing service category.
     */
    #[Route('/{id}/edit', name: 'admin_service_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, ServiceCategory $serviceCategory, EntityManagerInterface $entityManager): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin service category edit started', [
            'service_category_id' => $serviceCategory->getId(),
            'service_category_name' => $serviceCategory->getName(),
            'admin' => $adminId,
            'method' => $request->getMethod(),
            'timestamp' => new DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            // Store original values for comparison
            $originalData = [
                'name' => $serviceCategory->getName(),
                'slug' => $serviceCategory->getSlug(),
                'description' => $serviceCategory->getDescription(),
            ];

            $this->logger->debug('Original service category data captured', [
                'service_category_id' => $serviceCategory->getId(),
                'admin' => $adminId,
                'original_data' => $originalData,
            ]);

            $form = $this->createForm(ServiceCategoryType::class, $serviceCategory);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Service category edit form submitted', [
                    'service_category_id' => $serviceCategory->getId(),
                    'admin' => $adminId,
                    'form_valid' => $form->isValid(),
                    'submitted_data' => [
                        'name' => $serviceCategory->getName(),
                        'description_length' => strlen($serviceCategory->getDescription() ?? ''),
                    ],
                ]);

                if (!$form->isValid()) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }

                    $this->logger->warning('Service category edit form validation failed', [
                        'service_category_id' => $serviceCategory->getId(),
                        'admin' => $adminId,
                        'validation_errors' => $errors,
                    ]);
                }
            }

            if ($form->isSubmitted() && $form->isValid()) {
                // Update slug if name changed
                $newName = $serviceCategory->getName();
                $slug = $this->slugger->slug($newName)->lower()->toString();
                $serviceCategory->setSlug($slug);

                // Log what changed
                $changes = [];
                if ($originalData['name'] !== $newName) {
                    $changes['name'] = ['from' => $originalData['name'], 'to' => $newName];
                }
                if ($originalData['slug'] !== $slug) {
                    $changes['slug'] = ['from' => $originalData['slug'], 'to' => $slug];
                }
                if ($originalData['description'] !== $serviceCategory->getDescription()) {
                    $changes['description'] = [
                        'from_length' => strlen($originalData['description'] ?? ''),
                        'to_length' => strlen($serviceCategory->getDescription() ?? ''),
                    ];
                }

                $this->logger->debug('Service category changes detected', [
                    'service_category_id' => $serviceCategory->getId(),
                    'admin' => $adminId,
                    'changes' => $changes,
                    'total_changes' => count($changes),
                ]);

                $entityManager->flush();

                $this->logger->info('Service category updated successfully', [
                    'service_category_id' => $serviceCategory->getId(),
                    'service_category_name' => $serviceCategory->getName(),
                    'admin' => $adminId,
                    'changes_made' => $changes,
                    'update_timestamp' => new DateTime(),
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
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->error('Service category update failed: unique constraint violation', [
                'service_category_id' => $serviceCategory->getId(),
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'attempted_name' => $serviceCategory->getName(),
                'attempted_slug' => $serviceCategory->getSlug(),
                'constraint_details' => $e->getPrevious()?->getMessage(),
            ]);

            $this->addFlash('error', 'Une catégorie avec ce nom existe déjà. Veuillez choisir un nom différent.');

            return $this->render('admin/service_category/edit.html.twig', [
                'service_category' => $serviceCategory,
                'form' => $form,
                'page_title' => 'Modifier la catégorie de service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de services', 'url' => $this->generateUrl('admin_service_category_index')],
                    ['label' => $serviceCategory->getName(), 'url' => $this->generateUrl('admin_service_category_show', ['id' => $serviceCategory->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during service category update', [
                'service_category_id' => $serviceCategory->getId(),
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors de la modification de la catégorie de service.');

            return $this->render('admin/service_category/edit.html.twig', [
                'service_category' => $serviceCategory,
                'form' => $form,
                'page_title' => 'Modifier la catégorie de service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories de services', 'url' => $this->generateUrl('admin_service_category_index')],
                    ['label' => $serviceCategory->getName(), 'url' => $this->generateUrl('admin_service_category_show', ['id' => $serviceCategory->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during service category update', [
                'service_category_id' => $serviceCategory->getId(),
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer ou contacter l\'administrateur.');

            return $this->redirectToRoute('admin_service_category_index');
        }
    }

    /**
     * Delete a service category.
     */
    #[Route('/{id}', name: 'admin_service_category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ServiceCategory $serviceCategory, EntityManagerInterface $entityManager): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();
        $categoryId = $serviceCategory->getId();
        $categoryName = $serviceCategory->getName();

        $this->logger->info('Admin service category deletion started', [
            'service_category_id' => $categoryId,
            'service_category_name' => $categoryName,
            'admin' => $adminId,
            'timestamp' => new DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            $tokenValue = $request->getPayload()->get('_token');
            $expectedToken = 'delete' . $categoryId;

            $this->logger->debug('CSRF token validation started for category deletion', [
                'service_category_id' => $categoryId,
                'admin' => $adminId,
                'token_provided' => !empty($tokenValue),
                'expected_token_prefix' => $expectedToken,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $tokenValue)) {
                $this->logger->debug('CSRF token validation successful for category deletion', [
                    'service_category_id' => $categoryId,
                    'admin' => $adminId,
                ]);

                // Check if category has services
                $servicesCount = $serviceCategory->getServices()->count();
                $activeServicesCount = $serviceCategory->getServices()->filter(static fn ($service) => $service->isActive())->count();

                $this->logger->debug('Checking category dependencies before deletion', [
                    'service_category_id' => $categoryId,
                    'admin' => $adminId,
                    'total_services' => $servicesCount,
                    'active_services' => $activeServicesCount,
                ]);

                if ($servicesCount > 0) {
                    $this->logger->warning('Service category deletion prevented: category has services', [
                        'service_category_id' => $categoryId,
                        'service_category_name' => $categoryName,
                        'admin' => $adminId,
                        'total_services' => $servicesCount,
                        'active_services' => $activeServicesCount,
                        'service_titles' => $serviceCategory->getServices()->map(static fn ($service) => $service->getTitle())->toArray(),
                    ]);

                    $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle contient des services.');

                    return $this->redirectToRoute('admin_service_category_index');
                }

                // Log category details before deletion for audit trail
                $categoryDetails = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'slug' => $serviceCategory->getSlug(),
                    'description_length' => strlen($serviceCategory->getDescription() ?? ''),
                    'services_count' => $servicesCount,
                ];

                $this->logger->info('Service category details captured before deletion', [
                    'admin' => $adminId,
                    'category_details' => $categoryDetails,
                ]);

                $entityManager->remove($serviceCategory);
                $entityManager->flush();

                $this->logger->info('Service category deleted successfully', [
                    'service_category_id' => $categoryId,
                    'service_category_name' => $categoryName,
                    'admin' => $adminId,
                    'deletion_timestamp' => new DateTime(),
                    'deleted_category_details' => $categoryDetails,
                ]);

                $this->addFlash('success', 'La catégorie de service a été supprimée avec succès.');
            } else {
                $this->logger->warning('Service category deletion failed: invalid CSRF token', [
                    'service_category_id' => $categoryId,
                    'service_category_name' => $categoryName,
                    'admin' => $adminId,
                    'token_provided' => !empty($tokenValue),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

            return $this->redirectToRoute('admin_service_category_index');
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error('Service category deletion failed: foreign key constraint violation', [
                'service_category_id' => $categoryId,
                'service_category_name' => $categoryName,
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'constraint_details' => $e->getPrevious()?->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle est référencée par d\'autres éléments du système.');

            return $this->redirectToRoute('admin_service_category_index');
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during service category deletion', [
                'service_category_id' => $categoryId,
                'service_category_name' => $categoryName,
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors de la suppression de la catégorie de service.');

            return $this->redirectToRoute('admin_service_category_index');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during service category deletion', [
                'service_category_id' => $categoryId,
                'service_category_name' => $categoryName,
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer ou contacter l\'administrateur.');

            return $this->redirectToRoute('admin_service_category_index');
        }
    }
}
