<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Category;
use App\Form\Training\CategoryType;
use App\Repository\Training\CategoryRepository;
use DateTime;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Exception;
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
#[Route('/admin/categories')]
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
    #[Route('/', name: 'admin_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin categories list access initiated', [
            'admin' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => new DateTime(),
        ]);

        try {
            $this->logger->debug('Fetching categories from repository', [
                'admin' => $userId,
                'sort_by' => 'name',
                'sort_order' => 'ASC',
            ]);

            $categories = $categoryRepository->findBy([], ['name' => 'ASC']);

            $this->logger->info('Categories successfully retrieved', [
                'admin' => $userId,
                'categories_count' => count($categories),
                'active_categories' => array_reduce($categories, static fn ($count, $cat) => $count + ($cat->isActive() ? 1 : 0), 0),
                'inactive_categories' => array_reduce($categories, static fn ($count, $cat) => $count + (!$cat->isActive() ? 1 : 0), 0),
            ]);

            $this->logger->debug('Rendering categories index template', [
                'admin' => $userId,
                'template' => 'admin/category/index.html.twig',
                'page_title' => 'Gestion des catégories',
            ]);

            return $this->render('admin/category/index.html.twig', [
                'categories' => $categories,
                'page_title' => 'Gestion des catégories',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Catégories', 'url' => null],
                ],
            ]);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error while retrieving categories', [
                'admin' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la récupération des catégories.');

            return $this->redirectToRoute('admin_dashboard');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in categories index', [
                'admin' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du chargement des catégories.');

            return $this->redirectToRoute('admin_dashboard');
        }
    }

    /**
     * Show category details.
     */
    #[Route('/{id}', name: 'admin_category_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Category $category): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Admin category details view initiated', [
            'category_id' => $category->getId(),
            'category_name' => $category->getName(),
            'category_slug' => $category->getSlug(),
            'user' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTime(),
        ]);

        try {
            $this->logger->debug('Loading category formations and metadata', [
                'category_id' => $category->getId(),
                'formations_count' => $category->getFormations()->count(),
                'is_active' => $category->isActive(),
                'user' => $userId,
            ]);

            // Log category performance metrics
            $formationsCount = $category->getFormations()->count();
            $activeFormationsCount = $category->getFormations()->filter(static fn ($f) => $f->isActive())->count();

            $this->logger->info('Category details successfully loaded', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'total_formations' => $formationsCount,
                'active_formations' => $activeFormationsCount,
                'inactive_formations' => $formationsCount - $activeFormationsCount,
                'user' => $userId,
            ]);

            $this->logger->debug('Rendering category show template', [
                'category_id' => $category->getId(),
                'template' => 'admin/category/show.html.twig',
                'user' => $userId,
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
        } catch (EntityNotFoundException $e) {
            $this->logger->warning('Category not found for show action', [
                'category_id' => $category->getId(),
                'user' => $userId,
                'error_message' => $e->getMessage(),
            ]);

            $this->addFlash('error', 'La catégorie demandée est introuvable.');

            return $this->redirectToRoute('admin_category_index');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in category show', [
                'category_id' => $category->getId(),
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'affichage de la catégorie.');

            return $this->redirectToRoute('admin_category_index');
        }
    }

    /**
     * Create a new category.
     */
    #[Route('/new', name: 'admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('New category creation initiated', [
            'user' => $userId,
            'method' => $request->getMethod(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTime(),
        ]);

        try {
            $category = new Category();

            $this->logger->debug('Category form creation started', [
                'form_type' => CategoryType::class,
                'user' => $userId,
            ]);

            $form = $this->createForm(CategoryType::class, $category);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Category form submitted', [
                    'user' => $userId,
                    'form_valid' => $form->isValid(),
                    'submitted_data' => [
                        'name' => $form->get('name')->getData(),
                        'description' => $form->get('description')->getData() ? substr($form->get('description')->getData(), 0, 100) . '...' : null,
                        'is_active' => $form->get('isActive')->getData(),
                    ],
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Generating slug for new category', [
                        'category_name' => $category->getName(),
                        'user' => $userId,
                    ]);

                    // Generate slug from name
                    $slug = $this->slugger->slug($category->getName())->lower()->toString();
                    $category->setSlug($slug);

                    $this->logger->debug('Persisting new category to database', [
                        'category_name' => $category->getName(),
                        'category_slug' => $category->getSlug(),
                        'category_active' => $category->isActive(),
                        'user' => $userId,
                    ]);

                    $entityManager->persist($category);
                    $entityManager->flush();

                    $this->logger->info('New category successfully created', [
                        'category_id' => $category->getId(),
                        'category_name' => $category->getName(),
                        'category_slug' => $category->getSlug(),
                        'user' => $userId,
                        'creation_time' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ]);

                    $this->addFlash('success', 'La catégorie a été créée avec succès.');

                    return $this->redirectToRoute('admin_category_index');
                }
                $this->logger->warning('Category form validation failed', [
                    'user' => $userId,
                    'form_errors' => array_map(static fn ($error) => $error->getMessage(), iterator_to_array($form->getErrors(true))),
                ]);
            }

            $this->logger->debug('Rendering new category form', [
                'template' => 'admin/category/new.html.twig',
                'user' => $userId,
            ]);

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
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->error('Unique constraint violation when creating category', [
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'submitted_name' => $category->getName() ?? 'unknown',
            ]);

            $this->addFlash('error', 'Une catégorie avec ce nom existe déjà.');

            return $this->redirectToRoute('admin_category_new');
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error when creating category', [
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'category_data' => [
                    'name' => $category->getName() ?? 'unknown',
                    'slug' => $category->getSlug() ?? 'unknown',
                ],
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la création de la catégorie.');

            return $this->redirectToRoute('admin_category_index');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in category creation', [
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la création de la catégorie.');

            return $this->redirectToRoute('admin_category_index');
        }
    }

    /**
     * Edit an existing category.
     */
    #[Route('/{id}/edit', name: 'admin_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();

        $this->logger->info('Category edit initiated', [
            'category_id' => $category->getId(),
            'category_name' => $category->getName(),
            'user' => $userId,
            'method' => $request->getMethod(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTime(),
        ]);

        try {
            // Store original values for comparison
            $originalName = $category->getName();
            $originalSlug = $category->getSlug();
            $originalDescription = $category->getDescription();
            $originalIsActive = $category->isActive();

            $this->logger->debug('Creating edit form for category', [
                'category_id' => $category->getId(),
                'form_type' => CategoryType::class,
                'user' => $userId,
                'original_values' => [
                    'name' => $originalName,
                    'slug' => $originalSlug,
                    'is_active' => $originalIsActive,
                ],
            ]);

            $form = $this->createForm(CategoryType::class, $category);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Category edit form submitted', [
                    'category_id' => $category->getId(),
                    'user' => $userId,
                    'form_valid' => $form->isValid(),
                    'submitted_data' => [
                        'name' => $form->get('name')->getData(),
                        'description' => $form->get('description')->getData() ? substr($form->get('description')->getData(), 0, 100) . '...' : null,
                        'is_active' => $form->get('isActive')->getData(),
                    ],
                ]);

                if ($form->isValid()) {
                    // Track changes
                    $changes = [];
                    if ($originalName !== $category->getName()) {
                        $changes['name'] = ['from' => $originalName, 'to' => $category->getName()];
                    }
                    if ($originalDescription !== $category->getDescription()) {
                        $changes['description'] = ['changed' => true];
                    }
                    if ($originalIsActive !== $category->isActive()) {
                        $changes['is_active'] = ['from' => $originalIsActive, 'to' => $category->isActive()];
                    }

                    // Update slug if name changed
                    if ($originalName !== $category->getName()) {
                        $slug = $this->slugger->slug($category->getName())->lower()->toString();
                        $category->setSlug($slug);
                        $changes['slug'] = ['from' => $originalSlug, 'to' => $slug];
                    }

                    $this->logger->debug('Persisting category changes', [
                        'category_id' => $category->getId(),
                        'user' => $userId,
                        'changes' => $changes,
                    ]);

                    $entityManager->flush();

                    $this->logger->info('Category successfully updated', [
                        'category_id' => $category->getId(),
                        'category_name' => $category->getName(),
                        'user' => $userId,
                        'changes_count' => count($changes),
                        'changes' => $changes,
                        'update_time' => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    ]);

                    $this->addFlash('success', 'La catégorie a été modifiée avec succès.');

                    return $this->redirectToRoute('admin_category_index');
                }
                $this->logger->warning('Category edit form validation failed', [
                    'category_id' => $category->getId(),
                    'user' => $userId,
                    'form_errors' => array_map(static fn ($error) => $error->getMessage(), iterator_to_array($form->getErrors(true))),
                ]);
            }

            $this->logger->debug('Rendering category edit form', [
                'category_id' => $category->getId(),
                'template' => 'admin/category/edit.html.twig',
                'user' => $userId,
            ]);

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
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->error('Unique constraint violation when editing category', [
                'category_id' => $category->getId(),
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'submitted_name' => $category->getName(),
            ]);

            $this->addFlash('error', 'Une catégorie avec ce nom existe déjà.');

            return $this->redirectToRoute('admin_category_edit', ['id' => $category->getId()]);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error when editing category', [
                'category_id' => $category->getId(),
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la modification de la catégorie.');

            return $this->redirectToRoute('admin_category_index');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in category edit', [
                'category_id' => $category->getId(),
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la modification de la catégorie.');

            return $this->redirectToRoute('admin_category_index');
        }
    }

    /**
     * Delete a category.
     */
    #[Route('/{id}', name: 'admin_category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $categoryId = $category->getId();
        $categoryName = $category->getName();

        $this->logger->info('Category deletion initiated', [
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'user' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTime(),
        ]);

        try {
            $token = $request->getPayload()->get('_token');
            $this->logger->debug('Validating CSRF token for category deletion', [
                'category_id' => $categoryId,
                'user' => $userId,
                'token_provided' => !empty($token),
            ]);

            if ($this->isCsrfTokenValid('delete' . $categoryId, $token)) {
                $this->logger->debug('CSRF token validated, checking category constraints', [
                    'category_id' => $categoryId,
                    'user' => $userId,
                ]);

                // Check if category has formations
                $formationsCount = $category->getFormations()->count();
                $this->logger->info('Checking category formations before deletion', [
                    'category_id' => $categoryId,
                    'formations_count' => $formationsCount,
                    'user' => $userId,
                ]);

                if ($formationsCount > 0) {
                    $this->logger->warning('Category deletion blocked: contains formations', [
                        'category_id' => $categoryId,
                        'category_name' => $categoryName,
                        'formations_count' => $formationsCount,
                        'user' => $userId,
                    ]);

                    $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle contient des formations.');

                    return $this->redirectToRoute('admin_category_index');
                }

                $this->logger->debug('Removing category from database', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'user' => $userId,
                ]);

                $entityManager->remove($category);
                $entityManager->flush();

                $this->logger->info('Category successfully deleted', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'user' => $userId,
                    'deletion_time' => new DateTime(),
                ]);

                $this->addFlash('success', 'La catégorie a été supprimée avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for category deletion', [
                    'category_id' => $categoryId,
                    'user' => $userId,
                    'provided_token' => $token,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_category_index');
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error('Foreign key constraint violation when deleting category', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user' => $userId,
                'error_message' => $e->getMessage(),
            ]);

            $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle est utilisée par d\'autres éléments.');

            return $this->redirectToRoute('admin_category_index');
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error when deleting category', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la suppression de la catégorie.');

            return $this->redirectToRoute('admin_category_index');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in category deletion', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la suppression de la catégorie.');

            return $this->redirectToRoute('admin_category_index');
        }
    }

    /**
     * Toggle category active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_category_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $categoryId = $category->getId();
        $categoryName = $category->getName();
        $currentStatus = $category->isActive();

        $this->logger->info('Category status toggle initiated', [
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'current_status' => $currentStatus,
            'user' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => new DateTime(),
        ]);

        try {
            $token = $request->getPayload()->get('_token');
            $this->logger->debug('Validating CSRF token for status toggle', [
                'category_id' => $categoryId,
                'user' => $userId,
                'token_provided' => !empty($token),
            ]);

            if ($this->isCsrfTokenValid('toggle' . $categoryId, $token)) {
                $newStatus = !$currentStatus;

                $this->logger->debug('Toggling category status', [
                    'category_id' => $categoryId,
                    'from_status' => $currentStatus,
                    'to_status' => $newStatus,
                    'user' => $userId,
                ]);

                $category->setIsActive($newStatus);
                $entityManager->flush();

                $status = $newStatus ? 'activée' : 'désactivée';

                $this->logger->info('Category status successfully toggled', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'old_status' => $currentStatus,
                    'new_status' => $newStatus,
                    'user' => $userId,
                    'toggle_time' => new DateTime(),
                ]);

                $this->addFlash('success', "La catégorie a été {$status} avec succès.");
            } else {
                $this->logger->warning('Invalid CSRF token for status toggle', [
                    'category_id' => $categoryId,
                    'user' => $userId,
                    'provided_token' => $token,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

            return $this->redirectToRoute('admin_category_index');
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error when toggling category status', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'attempted_status' => !$currentStatus,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de base de données lors du changement de statut de la catégorie.');

            return $this->redirectToRoute('admin_category_index');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error in category status toggle', [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'attempted_status' => !$currentStatus,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du changement de statut de la catégorie.');

            return $this->redirectToRoute('admin_category_index');
        }
    }
}
