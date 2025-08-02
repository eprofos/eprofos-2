<?php

declare(strict_types=1);

namespace App\Controller\Admin\Service;

use App\Entity\Service\Service;
use App\Form\Service\ServiceType;
use App\Repository\Service\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Admin Service Controller.
 *
 * Handles CRUD operations for services in the admin interface.
 * Provides full management capabilities for EPROFOS services.
 */
#[Route('/admin/services')]
#[IsGranted('ROLE_ADMIN')]
class ServiceController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private SluggerInterface $slugger,
    ) {}

    /**
     * List all services with pagination and filtering.
     */
    #[Route('/', name: 'admin_service_index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin services list access started', [
            'admin' => $adminId,
            'timestamp' => new \DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);

        try {
            $this->logger->debug('Starting services query with category join', [
                'admin' => $adminId,
                'query_type' => 'services_with_categories',
            ]);

            $services = $serviceRepository->createQueryBuilder('s')
                ->leftJoin('s.serviceCategory', 'sc')
                ->addSelect('sc')
                ->orderBy('s.title', 'ASC')
                ->getQuery()
                ->getResult()
            ;

            $servicesCount = count($services);
            
            $this->logger->info('Services list successfully retrieved', [
                'admin' => $adminId,
                'services_count' => $servicesCount,
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            // Log detailed service statistics
            $activeServices = array_filter($services, fn($service) => $service->isActive());
            $categorizedServices = array_filter($services, fn($service) => $service->getServiceCategory() !== null);
            
            $this->logger->debug('Services statistics calculated', [
                'admin' => $adminId,
                'total_services' => $servicesCount,
                'active_services' => count($activeServices),
                'categorized_services' => count($categorizedServices),
                'uncategorized_services' => $servicesCount - count($categorizedServices),
            ]);

            return $this->render('admin/service/index.html.twig', [
                'services' => $services,
                'page_title' => 'Gestion des services',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => null],
                ],
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error while retrieving services list', [
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors du chargement des services.');
            
            return $this->render('admin/service/index.html.twig', [
                'services' => [],
                'page_title' => 'Gestion des services',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error while accessing services list', [
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
            
            return $this->render('admin/service/index.html.twig', [
                'services' => [],
                'page_title' => 'Gestion des services',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => null],
                ],
            ]);
        }
    }

    /**
     * Show service details.
     */
    #[Route('/{id}', name: 'admin_service_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Service $service): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin service details view started', [
            'service_id' => $service->getId(),
            'service_title' => $service->getTitle(),
            'admin' => $adminId,
            'timestamp' => new \DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            // Log service details for audit
            $this->logger->debug('Service details being displayed', [
                'service_id' => $service->getId(),
                'service_title' => $service->getTitle(),
                'service_slug' => $service->getSlug(),
                'service_active' => $service->isActive(),
                'service_category' => $service->getServiceCategory()?->getName(),
                'service_category_id' => $service->getServiceCategory()?->getId(),
                'admin' => $adminId,
                'has_description' => !empty($service->getDescription()),
                'description_length' => strlen($service->getDescription() ?? ''),
                'has_image' => !empty($service->getImage()),
            ]);

            $this->logger->info('Service details successfully displayed', [
                'service_id' => $service->getId(),
                'admin' => $adminId,
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            return $this->render('admin/service/show.html.twig', [
                'service' => $service,
                'page_title' => 'Détails du service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => $this->generateUrl('admin_service_index')],
                    ['label' => $service->getTitle(), 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error while displaying service details', [
                'service_id' => $service->getId(),
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur s\'est produite lors de l\'affichage des détails du service.');
            
            return $this->redirectToRoute('admin_service_index');
        }
    }

    /**
     * Create a new service.
     */
    #[Route('/new', name: 'admin_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin service creation started', [
            'admin' => $adminId,
            'method' => $request->getMethod(),
            'timestamp' => new \DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            $service = new Service();
            $form = $this->createForm(ServiceType::class, $service);
            
            $this->logger->debug('Service creation form initialized', [
                'admin' => $adminId,
                'form_type' => ServiceType::class,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Service creation form submitted', [
                    'admin' => $adminId,
                    'form_valid' => $form->isValid(),
                    'submitted_data' => [
                        'title' => $service->getTitle(),
                        'description_length' => strlen($service->getDescription() ?? ''),
                        'category_id' => $service->getServiceCategory()?->getId(),
                        'is_active' => $service->isActive(),
                    ],
                ]);

                if (!$form->isValid()) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }
                    
                    $this->logger->warning('Service creation form validation failed', [
                        'admin' => $adminId,
                        'validation_errors' => $errors,
                        'submitted_title' => $service->getTitle(),
                    ]);
                }
            }

            if ($form->isSubmitted() && $form->isValid()) {
                // Generate slug from title
                $originalTitle = $service->getTitle();
                $slug = $this->slugger->slug($originalTitle)->lower()->toString();
                $service->setSlug($slug);

                $this->logger->debug('Service slug generated', [
                    'admin' => $adminId,
                    'original_title' => $originalTitle,
                    'generated_slug' => $slug,
                ]);

                $entityManager->persist($service);
                $entityManager->flush();

                $this->logger->info('New service created successfully', [
                    'service_id' => $service->getId(),
                    'service_title' => $service->getTitle(),
                    'service_slug' => $service->getSlug(),
                    'service_category' => $service->getServiceCategory()?->getName(),
                    'admin' => $adminId,
                    'creation_timestamp' => new \DateTime(),
                ]);

                $this->addFlash('success', 'Le service a été créé avec succès.');

                return $this->redirectToRoute('admin_service_index');
            }

            return $this->render('admin/service/new.html.twig', [
                'service' => $service,
                'form' => $form,
                'page_title' => 'Nouveau service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => $this->generateUrl('admin_service_index')],
                    ['label' => 'Nouveau service', 'url' => null],
                ],
            ]);

        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $this->logger->error('Service creation failed: unique constraint violation', [
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'attempted_title' => $service->getTitle() ?? 'unknown',
                'attempted_slug' => $service->getSlug() ?? 'unknown',
                'constraint_details' => $e->getPrevious()?->getMessage(),
            ]);

            $this->addFlash('error', 'Un service avec ce titre existe déjà. Veuillez choisir un titre différent.');
            
            return $this->render('admin/service/new.html.twig', [
                'service' => $service,
                'form' => $form,
                'page_title' => 'Nouveau service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => $this->generateUrl('admin_service_index')],
                    ['label' => 'Nouveau service', 'url' => null],
                ],
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during service creation', [
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'attempted_data' => [
                    'title' => $service->getTitle() ?? 'unknown',
                    'slug' => $service->getSlug() ?? 'unknown',
                ],
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors de la création du service.');
            
            return $this->render('admin/service/new.html.twig', [
                'service' => $service,
                'form' => $form,
                'page_title' => 'Nouveau service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => $this->generateUrl('admin_service_index')],
                    ['label' => 'Nouveau service', 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during service creation', [
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
            
            return $this->redirectToRoute('admin_service_index');
        }
    }

    /**
     * Edit an existing service.
     */
    #[Route('/{id}/edit', name: 'admin_service_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin service edit started', [
            'service_id' => $service->getId(),
            'service_title' => $service->getTitle(),
            'admin' => $adminId,
            'method' => $request->getMethod(),
            'timestamp' => new \DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            // Store original values for comparison
            $originalData = [
                'title' => $service->getTitle(),
                'slug' => $service->getSlug(),
                'description' => $service->getDescription(),
                'category_id' => $service->getServiceCategory()?->getId(),
                'is_active' => $service->isActive(),
                'image' => $service->getImage(),
            ];

            $this->logger->debug('Original service data captured', [
                'service_id' => $service->getId(),
                'admin' => $adminId,
                'original_data' => $originalData,
            ]);

            $form = $this->createForm(ServiceType::class, $service);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Service edit form submitted', [
                    'service_id' => $service->getId(),
                    'admin' => $adminId,
                    'form_valid' => $form->isValid(),
                    'submitted_data' => [
                        'title' => $service->getTitle(),
                        'description_length' => strlen($service->getDescription() ?? ''),
                        'category_id' => $service->getServiceCategory()?->getId(),
                        'is_active' => $service->isActive(),
                    ],
                ]);

                if (!$form->isValid()) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }
                    
                    $this->logger->warning('Service edit form validation failed', [
                        'service_id' => $service->getId(),
                        'admin' => $adminId,
                        'validation_errors' => $errors,
                    ]);
                }
            }

            if ($form->isSubmitted() && $form->isValid()) {
                // Update slug if title changed
                $newTitle = $service->getTitle();
                $slug = $this->slugger->slug($newTitle)->lower()->toString();
                $service->setSlug($slug);

                // Log what changed
                $changes = [];
                if ($originalData['title'] !== $newTitle) {
                    $changes['title'] = ['from' => $originalData['title'], 'to' => $newTitle];
                }
                if ($originalData['slug'] !== $slug) {
                    $changes['slug'] = ['from' => $originalData['slug'], 'to' => $slug];
                }
                if ($originalData['description'] !== $service->getDescription()) {
                    $changes['description'] = [
                        'from_length' => strlen($originalData['description'] ?? ''),
                        'to_length' => strlen($service->getDescription() ?? ''),
                    ];
                }
                if ($originalData['category_id'] !== $service->getServiceCategory()?->getId()) {
                    $changes['category_id'] = [
                        'from' => $originalData['category_id'],
                        'to' => $service->getServiceCategory()?->getId(),
                    ];
                }
                if ($originalData['is_active'] !== $service->isActive()) {
                    $changes['is_active'] = ['from' => $originalData['is_active'], 'to' => $service->isActive()];
                }
                if ($originalData['image'] !== $service->getImage()) {
                    $changes['image'] = ['from' => $originalData['image'], 'to' => $service->getImage()];
                }

                $this->logger->debug('Service changes detected', [
                    'service_id' => $service->getId(),
                    'admin' => $adminId,
                    'changes' => $changes,
                    'total_changes' => count($changes),
                ]);

                $entityManager->flush();

                $this->logger->info('Service updated successfully', [
                    'service_id' => $service->getId(),
                    'service_title' => $service->getTitle(),
                    'admin' => $adminId,
                    'changes_made' => $changes,
                    'update_timestamp' => new \DateTime(),
                ]);

                $this->addFlash('success', 'Le service a été modifié avec succès.');

                return $this->redirectToRoute('admin_service_index');
            }

            return $this->render('admin/service/edit.html.twig', [
                'service' => $service,
                'form' => $form,
                'page_title' => 'Modifier le service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => $this->generateUrl('admin_service_index')],
                    ['label' => $service->getTitle(), 'url' => $this->generateUrl('admin_service_show', ['id' => $service->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);

        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $this->logger->error('Service update failed: unique constraint violation', [
                'service_id' => $service->getId(),
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'attempted_title' => $service->getTitle(),
                'attempted_slug' => $service->getSlug(),
                'constraint_details' => $e->getPrevious()?->getMessage(),
            ]);

            $this->addFlash('error', 'Un service avec ce titre existe déjà. Veuillez choisir un titre différent.');
            
            return $this->render('admin/service/edit.html.twig', [
                'service' => $service,
                'form' => $form,
                'page_title' => 'Modifier le service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => $this->generateUrl('admin_service_index')],
                    ['label' => $service->getTitle(), 'url' => $this->generateUrl('admin_service_show', ['id' => $service->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during service update', [
                'service_id' => $service->getId(),
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors de la modification du service.');
            
            return $this->render('admin/service/edit.html.twig', [
                'service' => $service,
                'form' => $form,
                'page_title' => 'Modifier le service',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Services', 'url' => $this->generateUrl('admin_service_index')],
                    ['label' => $service->getTitle(), 'url' => $this->generateUrl('admin_service_show', ['id' => $service->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during service update', [
                'service_id' => $service->getId(),
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer ou contacter l\'administrateur.');
            
            return $this->redirectToRoute('admin_service_index');
        }
    }

    /**
     * Delete a service.
     */
    #[Route('/{id}', name: 'admin_service_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();
        $serviceId = $service->getId();
        $serviceTitle = $service->getTitle();
        
        $this->logger->info('Admin service deletion started', [
            'service_id' => $serviceId,
            'service_title' => $serviceTitle,
            'admin' => $adminId,
            'timestamp' => new \DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            $tokenValue = $request->getPayload()->get('_token');
            $expectedToken = 'delete' . $serviceId;
            
            $this->logger->debug('CSRF token validation started', [
                'service_id' => $serviceId,
                'admin' => $adminId,
                'token_provided' => !empty($tokenValue),
                'expected_token_prefix' => $expectedToken,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $tokenValue)) {
                $this->logger->debug('CSRF token validation successful', [
                    'service_id' => $serviceId,
                    'admin' => $adminId,
                ]);

                // Log service details before deletion for audit trail
                $serviceDetails = [
                    'id' => $serviceId,
                    'title' => $serviceTitle,
                    'slug' => $service->getSlug(),
                    'description_length' => strlen($service->getDescription() ?? ''),
                    'category' => $service->getServiceCategory()?->getName(),
                    'category_id' => $service->getServiceCategory()?->getId(),
                    'is_active' => $service->isActive(),
                    'image' => $service->getImage(),
                    'created_at' => $service->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updated_at' => $service->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ];

                $this->logger->info('Service details captured before deletion', [
                    'admin' => $adminId,
                    'service_details' => $serviceDetails,
                ]);

                $entityManager->remove($service);
                $entityManager->flush();

                $this->logger->info('Service deleted successfully', [
                    'service_id' => $serviceId,
                    'service_title' => $serviceTitle,
                    'admin' => $adminId,
                    'deletion_timestamp' => new \DateTime(),
                    'deleted_service_details' => $serviceDetails,
                ]);

                $this->addFlash('success', 'Le service a été supprimé avec succès.');

            } else {
                $this->logger->warning('Service deletion failed: invalid CSRF token', [
                    'service_id' => $serviceId,
                    'service_title' => $serviceTitle,
                    'admin' => $adminId,
                    'token_provided' => !empty($tokenValue),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

            return $this->redirectToRoute('admin_service_index');

        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
            $this->logger->error('Service deletion failed: foreign key constraint violation', [
                'service_id' => $serviceId,
                'service_title' => $serviceTitle,
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'constraint_details' => $e->getPrevious()?->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Impossible de supprimer ce service car il est référencé par d\'autres éléments du système.');
            
            return $this->redirectToRoute('admin_service_index');

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during service deletion', [
                'service_id' => $serviceId,
                'service_title' => $serviceTitle,
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors de la suppression du service.');
            
            return $this->redirectToRoute('admin_service_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during service deletion', [
                'service_id' => $serviceId,
                'service_title' => $serviceTitle,
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer ou contacter l\'administrateur.');
            
            return $this->redirectToRoute('admin_service_index');
        }
    }

    /**
     * Toggle service active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_service_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $adminId = $this->getUser()?->getUserIdentifier();
        $serviceId = $service->getId();
        $serviceTitle = $service->getTitle();
        $currentStatus = $service->isActive();
        
        $this->logger->info('Admin service status toggle started', [
            'service_id' => $serviceId,
            'service_title' => $serviceTitle,
            'current_status' => $currentStatus,
            'admin' => $adminId,
            'timestamp' => new \DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            $tokenValue = $request->getPayload()->get('_token');
            $expectedToken = 'toggle_status' . $serviceId;
            
            $this->logger->debug('CSRF token validation for status toggle', [
                'service_id' => $serviceId,
                'admin' => $adminId,
                'token_provided' => !empty($tokenValue),
                'expected_token_prefix' => $expectedToken,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $tokenValue)) {
                $this->logger->debug('CSRF token validation successful for status toggle', [
                    'service_id' => $serviceId,
                    'admin' => $adminId,
                ]);

                $newStatus = !$currentStatus;
                $service->setIsActive($newStatus);
                
                $this->logger->debug('Service status change prepared', [
                    'service_id' => $serviceId,
                    'admin' => $adminId,
                    'status_change' => ['from' => $currentStatus, 'to' => $newStatus],
                ]);

                $entityManager->flush();

                $status = $newStatus ? 'activé' : 'désactivé';
                
                $this->logger->info('Service status toggled successfully', [
                    'service_id' => $serviceId,
                    'service_title' => $serviceTitle,
                    'old_status' => $currentStatus,
                    'new_status' => $newStatus,
                    'status_text' => $status,
                    'admin' => $adminId,
                    'toggle_timestamp' => new \DateTime(),
                ]);

                $this->addFlash('success', "Le service a été {$status} avec succès.");

            } else {
                $this->logger->warning('Service status toggle failed: invalid CSRF token', [
                    'service_id' => $serviceId,
                    'service_title' => $serviceTitle,
                    'current_status' => $currentStatus,
                    'admin' => $adminId,
                    'token_provided' => !empty($tokenValue),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

            return $this->redirectToRoute('admin_service_index');

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error during service status toggle', [
                'service_id' => $serviceId,
                'service_title' => $serviceTitle,
                'current_status' => $currentStatus,
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors du changement de statut du service.');
            
            return $this->redirectToRoute('admin_service_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during service status toggle', [
                'service_id' => $serviceId,
                'service_title' => $serviceTitle,
                'current_status' => $currentStatus,
                'admin' => $adminId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer ou contacter l\'administrateur.');
            
            return $this->redirectToRoute('admin_service_index');
        }
    }
}
