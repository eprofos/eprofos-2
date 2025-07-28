<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CRM\ContactRequest;
use App\Form\ContactRequestType;
use App\Repository\CRM\ContactRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Contact Request Controller.
 *
 * Handles CRUD operations for contact requests in the admin interface.
 * Provides full management capabilities for EPROFOS contact requests.
 */
#[Route('/admin/contact-requests', name: 'admin_contact_request_')]
#[IsGranted('ROLE_ADMIN')]
class ContactRequestController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * List all contact requests with filtering and pagination.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, ContactRequestRepository $contactRequestRepository): Response
    {
        $this->logger->info('Admin contact requests list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        $status = $request->query->get('status');
        $type = $request->query->get('type');

        $queryBuilder = $contactRequestRepository->createQueryBuilder('cr')
            ->leftJoin('cr.formation', 'f')
            ->leftJoin('cr.service', 's')
            ->addSelect('f', 's')
            ->orderBy('cr.createdAt', 'DESC')
        ;

        if ($status) {
            $queryBuilder->andWhere('cr.status = :status')
                ->setParameter('status', $status)
            ;
        }

        if ($type) {
            $queryBuilder->andWhere('cr.type = :type')
                ->setParameter('type', $type)
            ;
        }

        $contactRequests = $queryBuilder->getQuery()->getResult();

        // Get statistics for filters
        $statistics = $contactRequestRepository->getStatistics();
        $statusCounts = $contactRequestRepository->countByStatus();
        $typeCounts = $contactRequestRepository->countByType();

        return $this->render('admin/contact_request/index.html.twig', [
            'contact_requests' => $contactRequests,
            'statistics' => $statistics,
            'status_counts' => $statusCounts,
            'type_counts' => $typeCounts,
            'current_status' => $status,
            'current_type' => $type,
            'page_title' => 'Gestion des demandes de contact',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Demandes de contact', 'url' => null],
            ],
        ]);
    }

    /**
     * Show contact request details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ContactRequest $contactRequest): Response
    {
        $this->logger->info('Admin contact request details viewed', [
            'contact_request_id' => $contactRequest->getId(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $this->render('admin/contact_request/show.html.twig', [
            'contact_request' => $contactRequest,
            'page_title' => 'Détails de la demande',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Demandes de contact', 'url' => $this->generateUrl('admin_contact_request_index')],
                ['label' => 'Demande #' . $contactRequest->getId(), 'url' => null],
            ],
        ]);
    }

    /**
     * Edit an existing contact request (status and admin notes).
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, ContactRequest $contactRequest, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ContactRequestType::class, $contactRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mark as processed if status changed from pending
            if ($contactRequest->getStatus() !== 'pending' && !$contactRequest->getProcessedAt()) {
                $contactRequest->markAsProcessed();
            }

            $entityManager->flush();

            $this->logger->info('Contact request updated', [
                'contact_request_id' => $contactRequest->getId(),
                'new_status' => $contactRequest->getStatus(),
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La demande de contact a été modifiée avec succès.');

            return $this->redirectToRoute('admin_contact_request_show', ['id' => $contactRequest->getId()]);
        }

        return $this->render('admin/contact_request/edit.html.twig', [
            'contact_request' => $contactRequest,
            'form' => $form,
            'page_title' => 'Modifier la demande',
            'breadcrumb' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Demandes de contact', 'url' => $this->generateUrl('admin_contact_request_index')],
                ['label' => 'Demande #' . $contactRequest->getId(), 'url' => $this->generateUrl('admin_contact_request_show', ['id' => $contactRequest->getId()])],
                ['label' => 'Modifier', 'url' => null],
            ],
        ]);
    }

    /**
     * Update contact request status.
     */
    #[Route('/{id}/status', name: 'update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(Request $request, ContactRequest $contactRequest, EntityManagerInterface $entityManager): Response
    {
        $newStatus = $request->getPayload()->get('status');

        if ($this->isCsrfTokenValid('update_status' . $contactRequest->getId(), $request->getPayload()->get('_token'))) {
            $oldStatus = $contactRequest->getStatus();
            $contactRequest->setStatus($newStatus);

            // Mark as processed if status changed from pending
            if ($newStatus !== 'pending' && !$contactRequest->getProcessedAt()) {
                $contactRequest->markAsProcessed();
            }

            $entityManager->flush();

            $this->logger->info('Contact request status updated', [
                'contact_request_id' => $contactRequest->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Le statut de la demande a été mis à jour avec succès.');
        }

        return $this->redirectToRoute('admin_contact_request_show', ['id' => $contactRequest->getId()]);
    }

    /**
     * Delete a contact request.
     */
    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ContactRequest $contactRequest, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $contactRequest->getId(), $request->getPayload()->get('_token'))) {
            $contactRequestId = $contactRequest->getId();
            $entityManager->remove($contactRequest);
            $entityManager->flush();

            $this->logger->info('Contact request deleted', [
                'contact_request_id' => $contactRequestId,
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'La demande de contact a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_contact_request_index');
    }

    /**
     * Export contact requests to CSV.
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(ContactRequestRepository $contactRequestRepository): Response
    {
        $contactRequests = $contactRequestRepository->findAll();

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="demandes_contact_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'ID',
            'Type',
            'Prénom',
            'Nom',
            'Email',
            'Téléphone',
            'Entreprise',
            'Sujet',
            'Message',
            'Statut',
            'Formation',
            'Service',
            'Créé le',
            'Traité le',
        ]);

        // CSV data
        foreach ($contactRequests as $request) {
            fputcsv($output, [
                $request->getId(),
                $request->getTypeLabel(),
                $request->getFirstName(),
                $request->getLastName(),
                $request->getEmail(),
                $request->getPhone(),
                $request->getCompany(),
                $request->getSubject(),
                $request->getMessage(),
                $request->getStatusLabel(),
                $request->getFormation()?->getTitle(),
                $request->getService()?->getTitle(),
                $request->getCreatedAt()?->format('d/m/Y H:i'),
                $request->getProcessedAt()?->format('d/m/Y H:i'),
            ]);
        }

        fclose($output);

        $this->logger->info('Contact requests exported', [
            'count' => count($contactRequests),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $response;
    }
}
