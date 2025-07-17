<?php

namespace App\Controller\Admin;

use App\Entity\SessionRegistration;
use App\Repository\SessionRegistrationRepository;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Session Registration Controller
 * 
 * Handles session registration management in the admin interface.
 */
#[Route('/admin/session-registrations', name: 'admin_session_registration_')]
#[IsGranted('ROLE_ADMIN')]
class SessionRegistrationController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * List all session registrations with filtering
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, SessionRegistrationRepository $registrationRepository): Response
    {
        // Get filter parameters
        $filters = [
            'search' => $request->query->get('search', ''),
            'session' => $request->query->get('session', ''),
            'formation' => $request->query->get('formation', ''),
            'status' => $request->query->get('status', ''),
            'date_from' => $request->query->get('date_from', ''),
            'date_to' => $request->query->get('date_to', ''),
            'sort' => $request->query->get('sort', 'createdAt'),
            'direction' => $request->query->get('direction', 'DESC'),
        ];

        // Get registrations with filtering
        $queryBuilder = $registrationRepository->createAdminQueryBuilder($filters);

        // Handle pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $registrations = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Get total count for pagination
        $totalRegistrations = $registrationRepository->countWithAdminFilters($filters);

        $totalPages = ceil($totalRegistrations / $limit);

        return $this->render('admin/session_registration/index.html.twig', [
            'registrations' => $registrations,
            'filters' => $filters,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_registrations' => $totalRegistrations,
        ]);
    }

    /**
     * Show registration details
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(SessionRegistration $registration): Response
    {
        return $this->render('admin/session_registration/show.html.twig', [
            'registration' => $registration,
        ]);
    }

    /**
     * Confirm a registration
     */
    #[Route('/{id}/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(Request $request, SessionRegistration $registration): Response
    {
        if ($this->isCsrfTokenValid('confirm' . $registration->getId(), $request->request->get('_token'))) {
            try {
                $registration->confirm();
                
                // Update session registration count
                $session = $registration->getSession();
                $session->updateRegistrationsCount();
                
                $this->entityManager->flush();

                $this->addFlash('success', 'L\'inscription a été confirmée.');
                $this->logger->info('Registration confirmed', [
                    'registration_id' => $registration->getId(),
                    'user' => $this->getUser()?->getUserIdentifier()
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la confirmation de l\'inscription.');
                $this->logger->error('Error confirming registration', [
                    'registration_id' => $registration->getId(),
                    'error' => $e->getMessage(),
                    'user' => $this->getUser()?->getUserIdentifier()
                ]);
            }
        }

        return $this->redirectToRoute('admin_session_registration_show', ['id' => $registration->getId()]);
    }

    /**
     * Cancel a registration
     */
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Request $request, SessionRegistration $registration): Response
    {
        if ($this->isCsrfTokenValid('cancel' . $registration->getId(), $request->request->get('_token'))) {
            try {
                $registration->cancel();
                
                // Update session registration count
                $session = $registration->getSession();
                $session->updateRegistrationsCount();
                
                $this->entityManager->flush();

                $this->addFlash('success', 'L\'inscription a été annulée.');
                $this->logger->info('Registration cancelled', [
                    'registration_id' => $registration->getId(),
                    'user' => $this->getUser()?->getUserIdentifier()
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'annulation de l\'inscription.');
                $this->logger->error('Error cancelling registration', [
                    'registration_id' => $registration->getId(),
                    'error' => $e->getMessage(),
                    'user' => $this->getUser()?->getUserIdentifier()
                ]);
            }
        }

        return $this->redirectToRoute('admin_session_registration_show', ['id' => $registration->getId()]);
    }

    /**
     * Update registration status
     */
    #[Route('/{id}/update-status', name: 'update_status', methods: ['POST'])]
    public function updateStatus(Request $request, SessionRegistration $registration): Response
    {
        if ($this->isCsrfTokenValid('update_status' . $registration->getId(), $request->request->get('_token'))) {
            try {
                $newStatus = $request->request->get('status');
                
                if (in_array($newStatus, ['pending', 'confirmed', 'cancelled', 'attended', 'no_show'])) {
                    $registration->setStatus($newStatus);
                    
                    // Update session registration count
                    $session = $registration->getSession();
                    $session->updateRegistrationsCount();
                    
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Le statut de l\'inscription a été modifié.');
                    $this->logger->info('Registration status updated', [
                        'registration_id' => $registration->getId(),
                        'new_status' => $newStatus,
                        'user' => $this->getUser()?->getUserIdentifier()
                    ]);
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du statut.');
                $this->logger->error('Error updating registration status', [
                    'registration_id' => $registration->getId(),
                    'error' => $e->getMessage(),
                    'user' => $this->getUser()?->getUserIdentifier()
                ]);
            }
        }

        return $this->redirectToRoute('admin_session_registration_show', ['id' => $registration->getId()]);
    }

    /**
     * Delete a registration
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, SessionRegistration $registration): Response
    {
        if ($this->isCsrfTokenValid('delete' . $registration->getId(), $request->request->get('_token'))) {
            try {
                $session = $registration->getSession();
                
                $this->entityManager->remove($registration);
                
                // Update session registration count
                $session->updateRegistrationsCount();
                
                $this->entityManager->flush();

                $this->addFlash('success', 'L\'inscription a été supprimée.');
                $this->logger->info('Registration deleted', [
                    'registration_id' => $registration->getId(),
                    'user' => $this->getUser()?->getUserIdentifier()
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression de l\'inscription.');
                $this->logger->error('Error deleting registration', [
                    'registration_id' => $registration->getId(),
                    'error' => $e->getMessage(),
                    'user' => $this->getUser()?->getUserIdentifier()
                ]);
            }
        }

        return $this->redirectToRoute('admin_session_registration_index');
    }

    /**
     * Export all registrations to CSV
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request, SessionRegistrationRepository $registrationRepository): Response
    {
        // Get filter parameters
        $filters = [
            'search' => $request->query->get('search', ''),
            'session' => $request->query->get('session', ''),
            'formation' => $request->query->get('formation', ''),
            'status' => $request->query->get('status', ''),
            'date_from' => $request->query->get('date_from', ''),
            'date_to' => $request->query->get('date_to', ''),
        ];

        $registrations = $registrationRepository->createAdminQueryBuilder($filters)
            ->getQuery()
            ->getResult();
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="inscriptions_sessions.csv"');

        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Prénom',
            'Nom',
            'Email',
            'Téléphone',
            'Entreprise',
            'Poste',
            'Formation',
            'Session',
            'Statut',
            'Date d\'inscription',
            'Besoins spécifiques'
        ], ';');

        // CSV data
        foreach ($registrations as $registration) {
            fputcsv($output, [
                $registration->getFirstName(),
                $registration->getLastName(),
                $registration->getEmail(),
                $registration->getPhone(),
                $registration->getCompany(),
                $registration->getPosition(),
                $registration->getSession()->getFormation()->getTitle(),
                $registration->getSession()->getName(),
                $registration->getStatusLabel(),
                $registration->getCreatedAt()->format('d/m/Y H:i'),
                $registration->getSpecialRequirements()
            ], ';');
        }

        fclose($output);

        $this->logger->info('All registrations exported', [
            'user' => $this->getUser()?->getUserIdentifier()
        ]);

        return $response;
    }
}
