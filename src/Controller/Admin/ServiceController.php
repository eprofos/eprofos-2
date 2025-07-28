<?php

namespace App\Controller\Admin;

use App\Entity\Service\Service;
use App\Form\ServiceType;
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
 * Admin Service Controller
 * 
 * Handles CRUD operations for services in the admin interface.
 * Provides full management capabilities for EPROFOS services.
 */
#[Route('/admin/services', name: 'admin_service_')]
#[IsGranted('ROLE_ADMIN')]
class ServiceController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private SluggerInterface $slugger
    ) {
    }

    /**
     * List all services with pagination and filtering
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        $this->logger->info('Admin services list accessed', [
            'admin' => $this->getUser()?->getUserIdentifier()
        ]);

        $services = $serviceRepository->createQueryBuilder('s')
            ->leftJoin('s.serviceCategory', 'sc')
            ->addSelect('sc')
            ->orderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/service/index.html.twig', [
            'services' => $services,
            'page_title' => 'Gestion des services',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Services', 'url' => null]
            ]
        ]);
    }

    /**
     * Show service details
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Service $service): Response
    {
        $this->logger->info('Admin service details viewed', [
            'service_id' => $service->getId(),
            'admin' => $this->getUser()?->getUserIdentifier()
        ]);

        return $this->render('admin/service/show.html.twig', [
            'service' => $service,
            'page_title' => 'Détails du service',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Services', 'url' => $this->generateUrl('admin_service_index')],
                ['label' => $service->getTitle(), 'url' => null]
            ]
        ]);
    }

    /**
     * Create a new service
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate slug from title
            $slug = $this->slugger->slug($service->getTitle())->lower();
            $service->setSlug($slug);

            $entityManager->persist($service);
            $entityManager->flush();

            $this->logger->info('New service created', [
                'service_id' => $service->getId(),
                'service_title' => $service->getTitle(),
                'admin' => $this->getUser()?->getUserIdentifier()
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
                ['label' => 'Nouveau service', 'url' => null]
            ]
        ]);
    }

    /**
     * Edit an existing service
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update slug if title changed
            $slug = $this->slugger->slug($service->getTitle())->lower();
            $service->setSlug($slug);

            $entityManager->flush();

            $this->logger->info('Service updated', [
                'service_id' => $service->getId(),
                'service_title' => $service->getTitle(),
                'admin' => $this->getUser()?->getUserIdentifier()
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
                ['label' => 'Modifier', 'url' => null]
            ]
        ]);
    }

    /**
     * Delete a service
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->getPayload()->get('_token'))) {
            $serviceTitle = $service->getTitle();
            $entityManager->remove($service);
            $entityManager->flush();

            $this->logger->info('Service deleted', [
                'service_id' => $service->getId(),
                'service_title' => $serviceTitle,
                'admin' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', 'Le service a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_service_index');
    }

    /**
     * Toggle service active status
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_status'.$service->getId(), $request->getPayload()->get('_token'))) {
            $service->setIsActive(!$service->isActive());
            $entityManager->flush();

            $status = $service->isActive() ? 'activé' : 'désactivé';
            $this->logger->info('Service status toggled', [
                'service_id' => $service->getId(),
                'service_title' => $service->getTitle(),
                'new_status' => $service->isActive(),
                'admin' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', "Le service a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_service_index');
    }
}