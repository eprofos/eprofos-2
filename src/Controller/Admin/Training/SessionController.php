<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Session;
use App\Form\Training\SessionType;
use App\Repository\Training\FormationRepository;
use App\Repository\Training\SessionRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Session Controller.
 *
 * Handles CRUD operations for sessions in the admin interface.
 * Provides comprehensive session management with registration tracking.
 */
#[Route('/admin/sessions', name: 'admin_session_')]
#[IsGranted('ROLE_ADMIN')]
class SessionController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * List all sessions with pagination and filtering.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, SessionRepository $sessionRepository): Response
    {
        $this->logger->info('Admin sessions list accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        // Get filter parameters
        $filters = [
            'search' => $request->query->get('search', ''),
            'formation' => $request->query->get('formation', ''),
            'status' => $request->query->get('status', ''),
            'start_date' => $request->query->get('start_date', ''),
            'end_date' => $request->query->get('end_date', ''),
            'active' => $request->query->get('active') !== '' ? $request->query->get('active') : null,
            'sort' => $request->query->get('sort', 'startDate'),
            'direction' => $request->query->get('direction', 'ASC'),
        ];

        // Get sessions with filtering
        $queryBuilder = $sessionRepository->createAdminQueryBuilder($filters);

        // Handle pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $sessions = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        // Get total count for pagination
        $totalSessions = $sessionRepository->countAdminSessions($filters);

        $totalPages = ceil($totalSessions / $limit);

        // Get statistics
        $stats = $sessionRepository->getSessionsStats();

        return $this->render('admin/session/index.html.twig', [
            'sessions' => $sessions,
            'filters' => $filters,
            'stats' => $stats,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_sessions' => $totalSessions,
        ]);
    }

    /**
     * Show session details with registrations.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Session $session): Response
    {
        $this->logger->info('Admin session details viewed', [
            'session_id' => $session->getId(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $this->render('admin/session/show.html.twig', [
            'session' => $session,
        ]);
    }

    /**
     * Create a new session.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, FormationRepository $formationRepository): Response
    {
        $session = new Session();

        // If formation ID is provided, pre-select it
        $formationId = $request->query->get('formation');
        if ($formationId) {
            $formation = $formationRepository->find($formationId);
            if ($formation) {
                $session->setFormation($formation);
                // Auto-generate session name
                $session->setName($formation->getTitle() . ' - ' . (new DateTime())->format('M Y'));
            }
        }

        $form = $this->createForm(SessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->persist($session);
                $this->entityManager->flush();

                $this->addFlash('success', 'La session a été créée avec succès.');
                $this->logger->info('New session created', [
                    'session_id' => $session->getId(),
                    'session_name' => $session->getName(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création de la session.');
                $this->logger->error('Error creating session', [
                    'error' => $e->getMessage(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->render('admin/session/new.html.twig', [
            'session' => $session,
            'form' => $form,
        ]);
    }

    /**
     * Edit an existing session.
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Session $session): Response
    {
        $form = $this->createForm(SessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();

                $this->addFlash('success', 'La session a été modifiée avec succès.');
                $this->logger->info('Session updated', [
                    'session_id' => $session->getId(),
                    'session_name' => $session->getName(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la modification de la session.');
                $this->logger->error('Error updating session', [
                    'session_id' => $session->getId(),
                    'error' => $e->getMessage(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->render('admin/session/edit.html.twig', [
            'session' => $session,
            'form' => $form,
        ]);
    }

    /**
     * Delete a session.
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Session $session): Response
    {
        if ($this->isCsrfTokenValid('delete' . $session->getId(), $request->request->get('_token'))) {
            try {
                $sessionName = $session->getName();
                $sessionId = $session->getId();

                $this->entityManager->remove($session);
                $this->entityManager->flush();

                $this->addFlash('success', 'La session a été supprimée avec succès.');
                $this->logger->info('Session deleted', [
                    'session_id' => $sessionId,
                    'session_name' => $sessionName,
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Impossible de supprimer cette session car elle contient des inscriptions.');
                $this->logger->error('Error deleting session', [
                    'session_id' => $session->getId(),
                    'error' => $e->getMessage(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->redirectToRoute('admin_session_index');
    }

    /**
     * Toggle session status.
     */
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, Session $session): Response
    {
        if ($this->isCsrfTokenValid('toggle_status' . $session->getId(), $request->request->get('_token'))) {
            try {
                $newStatus = $request->request->get('status');

                if (in_array($newStatus, ['planned', 'open', 'confirmed', 'cancelled', 'completed'], true)) {
                    $session->setStatus($newStatus);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Le statut de la session a été modifié.');
                    $this->logger->info('Session status changed', [
                        'session_id' => $session->getId(),
                        'new_status' => $newStatus,
                        'user' => $this->getUser()?->getUserIdentifier(),
                    ]);
                }
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du statut.');
                $this->logger->error('Error changing session status', [
                    'session_id' => $session->getId(),
                    'error' => $e->getMessage(),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
    }

    /**
     * Export session registrations to CSV.
     */
    #[Route('/{id}/export', name: 'export', methods: ['GET'])]
    public function export(Session $session): Response
    {
        $registrations = $session->getRegistrations();

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="inscriptions_' . $session->getId() . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Prénom',
            'Nom',
            'Email',
            'Téléphone',
            'Entreprise',
            'Poste',
            'Statut',
            'Date d\'inscription',
            'Besoins spécifiques',
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
                $registration->getStatusLabel(),
                $registration->getCreatedAt()->format('d/m/Y H:i'),
                $registration->getSpecialRequirements(),
            ], ';');
        }

        fclose($output);

        $this->logger->info('Session registrations exported', [
            'session_id' => $session->getId(),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        return $response;
    }
}
