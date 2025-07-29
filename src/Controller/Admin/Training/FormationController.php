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
        $this->logger->info('Admin formations list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

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

        // Create a copy for query building (without empty values)
        $activeFilters = array_filter($filters, static fn ($value) => $value !== null && $value !== '');

        // Build query with filters
        $queryBuilder = $formationRepository->createQueryBuilder('f')
            ->leftJoin('f.category', 'c')
            ->addSelect('c')
        ;

        // Apply search filter
        if (!empty($activeFilters['search'])) {
            $queryBuilder
                ->andWhere('f.title LIKE :search OR f.description LIKE :search')
                ->setParameter('search', '%' . $activeFilters['search'] . '%')
            ;
        }

        // Apply category filter
        if (!empty($activeFilters['category'])) {
            $queryBuilder
                ->andWhere('c.slug = :category')
                ->setParameter('category', $activeFilters['category'])
            ;
        }

        // Apply level filter
        if (!empty($activeFilters['level'])) {
            $queryBuilder
                ->andWhere('f.level = :level')
                ->setParameter('level', $activeFilters['level'])
            ;
        }

        // Apply format filter
        if (!empty($activeFilters['format'])) {
            $queryBuilder
                ->andWhere('f.format = :format')
                ->setParameter('format', $activeFilters['format'])
            ;
        }

        // Apply status filter
        if (!empty($activeFilters['status'])) {
            $isActive = $activeFilters['status'] === 'active';
            $queryBuilder
                ->andWhere('f.isActive = :status')
                ->setParameter('status', $isActive)
            ;
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'createdAt';
        $sortOrder = $filters['sortOrder'] ?? 'DESC';

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

        $formations = $queryBuilder->getQuery()->getResult();

        // Get filter options for dropdowns
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
    }

    /**
     * Show formation details.
     */
    #[Route('/{id}', name: 'admin_formation_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Formation $formation): Response
    {
        $this->logger->info('Admin formation details viewed', [
            'formation_id' => $formation->getId(),
            'user' => $this->getUser()?->getUserIdentifier(),
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
    }

    /**
     * Create a new formation.
     */
    #[Route('/new', name: 'admin_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate slug from title
            $slug = $this->slugger->slug($formation->getTitle())->lower()->toString();
            $formation->setSlug($slug);

            // Handle image upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('formations_images_directory') ?? 'public/uploads/formations',
                        $newFilename,
                    );
                    $formation->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image.');
                }
            }

            $entityManager->persist($formation);
            $entityManager->flush();

            $this->logger->info('New formation created', [
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La formation a été créée avec succès.');

            return $this->redirectToRoute('admin_formation_index');
        }

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
    }

    /**
     * Edit an existing formation.
     */
    #[Route('/{id}/edit', name: 'admin_formation_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update slug if title changed
            $slug = $this->slugger->slug($formation->getTitle())->lower()->toString();
            $formation->setSlug($slug);

            // Handle image upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                // Delete old image if exists
                if ($formation->getImage()) {
                    $oldImagePath = ($this->getParameter('formations_images_directory') ?? 'public/uploads/formations') . '/' . $formation->getImage();
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('formations_images_directory') ?? 'public/uploads/formations',
                        $newFilename,
                    );
                    $formation->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image.');
                }
            }

            $entityManager->flush();

            $this->logger->info('Formation updated', [
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La formation a été modifiée avec succès.');

            return $this->redirectToRoute('admin_formation_index');
        }

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
    }

    /**
     * Delete a formation.
     */
    #[Route('/{id}', name: 'admin_formation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $formation->getId(), $request->getPayload()->get('_token'))) {
            // Check if formation has contact requests
            if ($formation->getContactRequests()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer cette formation car elle a des demandes de contact associées.');

                return $this->redirectToRoute('admin_formation_index');
            }

            // Delete image if exists
            if ($formation->getImage()) {
                $imagePath = ($this->getParameter('formations_images_directory') ?? 'public/uploads/formations') . '/' . $formation->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $formationTitle = $formation->getTitle();
            $entityManager->remove($formation);
            $entityManager->flush();

            $this->logger->info('Formation deleted', [
                'formation_id' => $formation->getId(),
                'formation_title' => $formationTitle,
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La formation a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_formation_index');
    }

    /**
     * Toggle formation active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_formation_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_status' . $formation->getId(), $request->getPayload()->get('_token'))) {
            $formation->setIsActive(!$formation->isActive());
            $entityManager->flush();

            $status = $formation->isActive() ? 'activée' : 'désactivée';
            $this->logger->info('Formation status toggled', [
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'new_status' => $formation->isActive(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', "La formation a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_formation_index');
    }

    /**
     * Toggle formation featured status.
     */
    #[Route('/{id}/toggle-featured', name: 'admin_formation_toggle_featured', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleFeatured(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_featured' . $formation->getId(), $request->getPayload()->get('_token'))) {
            $formation->setIsFeatured(!$formation->isFeatured());
            $entityManager->flush();

            $status = $formation->isFeatured() ? 'mise en avant' : 'retirée de la mise en avant';
            $this->logger->info('Formation featured status toggled', [
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'new_featured_status' => $formation->isFeatured(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', "La formation a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_formation_index');
    }
}
