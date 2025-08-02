<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\SessionRegistration;
use App\Repository\Training\SessionRegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Session Registration Controller.
 *
 * Handles session registration management in the admin interface.
 */
#[Route('/admin/session-registrations')]
#[IsGranted('ROLE_ADMIN')]
class SessionRegistrationController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * List all session registrations with filtering.
     */
    #[Route('/', name: 'admin_session_registration_index', methods: ['GET'])]
    public function index(Request $request, SessionRegistrationRepository $registrationRepository): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $this->logger->info('Starting session registrations index page', [
                'user' => $userIdentifier,
                'request_uri' => $request->getRequestUri(),
                'request_method' => $request->getMethod(),
                'client_ip' => $request->getClientIp(),
            ]);

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

            $this->logger->debug('Applied filters for session registrations listing', [
                'user' => $userIdentifier,
                'filters' => $filters,
            ]);

            // Get registrations with filtering
            $this->logger->debug('Creating admin query builder with filters', [
                'user' => $userIdentifier,
                'filters_count' => count(array_filter($filters)),
            ]);

            $queryBuilder = $registrationRepository->createAdminQueryBuilder($filters);

            // Handle pagination
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $this->logger->debug('Pagination parameters calculated', [
                'user' => $userIdentifier,
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            $this->logger->debug('Executing query to retrieve session registrations', [
                'user' => $userIdentifier,
                'pagination' => ['page' => $page, 'limit' => $limit, 'offset' => $offset],
            ]);

            $registrations = $queryBuilder
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
            ;

            $this->logger->debug('Session registrations retrieved successfully', [
                'user' => $userIdentifier,
                'registrations_count' => count($registrations),
                'page' => $page,
            ]);

            // Get total count for pagination
            $this->logger->debug('Counting total registrations with filters', [
                'user' => $userIdentifier,
            ]);

            $totalRegistrations = $registrationRepository->countWithAdminFilters($filters);
            $totalPages = ceil($totalRegistrations / $limit);

            $this->logger->info('Session registrations index page loaded successfully', [
                'user' => $userIdentifier,
                'total_registrations' => $totalRegistrations,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/session_registration/index.html.twig', [
                'registrations' => $registrations,
                'filters' => $filters,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_registrations' => $totalRegistrations,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Error loading session registrations index page', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors du chargement de la liste des inscriptions.');
            
            // Return empty result in case of error
            return $this->render('admin/session_registration/index.html.twig', [
                'registrations' => [],
                'filters' => $filters ?? [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_registrations' => 0,
            ]);
        }
    }

    /**
     * Show registration details.
     */
    #[Route('/{id}', name: 'admin_session_registration_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(SessionRegistration $registration): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $this->logger->info('Starting registration details view', [
                'user' => $userIdentifier,
                'registration_id' => $registration->getId(),
                'registration_status' => $registration->getStatus(),
                'session_id' => $registration->getSession()?->getId(),
                'formation_id' => $registration->getSession()?->getFormation()?->getId(),
                'participant_email' => $registration->getEmail(),
            ]);

            $this->logger->debug('Loading registration details with related entities', [
                'user' => $userIdentifier,
                'registration_id' => $registration->getId(),
                'session_name' => $registration->getSession()?->getName(),
                'formation_title' => $registration->getSession()?->getFormation()?->getTitle(),
                'participant_name' => $registration->getFirstName() . ' ' . $registration->getLastName(),
                'company' => $registration->getCompany(),
                'position' => $registration->getPosition(),
            ]);

            $this->logger->info('Registration details loaded successfully', [
                'user' => $userIdentifier,
                'registration_id' => $registration->getId(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/session_registration/show.html.twig', [
                'registration' => $registration,
            ]);

        } catch (Exception $e) {
            $this->logger->error('Error loading registration details', [
                'user' => $userIdentifier,
                'registration_id' => $registration->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors du chargement des détails de l\'inscription.');
            
            return $this->redirectToRoute('admin_session_registration_index');
        }
    }

    /**
     * Confirm a registration.
     */
    #[Route('/{id}/confirm', name: 'admin_session_registration_confirm', methods: ['POST'])]
    public function confirm(Request $request, SessionRegistration $registration): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $registrationId = $registration->getId();
        $csrfToken = $request->request->get('_token');

        $this->logger->info('Starting registration confirmation process', [
            'user' => $userIdentifier,
            'registration_id' => $registrationId,
            'current_status' => $registration->getStatus(),
            'session_id' => $registration->getSession()?->getId(),
            'participant_email' => $registration->getEmail(),
            'csrf_token_provided' => !empty($csrfToken),
        ]);

        try {
            $this->logger->debug('Validating CSRF token for registration confirmation', [
                'user' => $userIdentifier,
                'registration_id' => $registrationId,
                'token_intention' => 'confirm' . $registrationId,
            ]);

            if ($this->isCsrfTokenValid('confirm' . $registrationId, $csrfToken)) {
                $this->logger->debug('CSRF token validated successfully, proceeding with confirmation', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                ]);

                // Get current session before changes for logging
                $session = $registration->getSession();
                $previousRegistrationsCount = $session->getConfirmedRegistrationsCount();
                
                $this->logger->debug('Current session state before confirmation', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                    'session_name' => $session->getName(),
                    'previous_confirmed_count' => $previousRegistrationsCount,
                    'session_max_participants' => $session->getMaxCapacity(),
                ]);

                // Confirm the registration
                $this->logger->debug('Calling registration confirm method', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'previous_status' => $registration->getStatus(),
                ]);

                $registration->confirm();

                $this->logger->debug('Registration status updated to confirmed', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'new_status' => $registration->getStatus(),
                ]);

                // Update session registration count
                $this->logger->debug('Updating session registrations count', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                ]);

                $session->updateRegistrationsCount();
                $newRegistrationsCount = $session->getConfirmedRegistrationsCount();

                $this->logger->debug('Session registrations count updated', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                    'previous_confirmed_count' => $previousRegistrationsCount,
                    'new_confirmed_count' => $newRegistrationsCount,
                    'count_difference' => $newRegistrationsCount - $previousRegistrationsCount,
                ]);

                // Persist changes to database
                $this->logger->debug('Flushing entity manager to persist changes', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                ]);

                $this->entityManager->flush();

                $this->logger->info('Registration confirmed successfully', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                    'participant_email' => $registration->getEmail(),
                    'participant_name' => $registration->getFirstName() . ' ' . $registration->getLastName(),
                    'formation_title' => $session->getFormation()?->getTitle(),
                    'session_name' => $session->getName(),
                    'previous_confirmed_count' => $previousRegistrationsCount,
                    'new_confirmed_count' => $newRegistrationsCount,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                $this->addFlash('success', 'L\'inscription a été confirmée.');
                
            } else {
                $this->logger->warning('Invalid CSRF token for registration confirmation', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'token_intention' => 'confirm' . $registrationId,
                    'provided_token' => $csrfToken,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

        } catch (Exception $e) {
            $this->logger->error('Error confirming registration', [
                'user' => $userIdentifier,
                'registration_id' => $registrationId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'session_id' => $registration->getSession()?->getId(),
                'participant_email' => $registration->getEmail(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors de la confirmation de l\'inscription.');
        }

        return $this->redirectToRoute('admin_session_registration_show', ['id' => $registrationId]);
    }

    /**
     * Cancel a registration.
     */
    #[Route('/{id}/cancel', name: 'admin_session_registration_cancel', methods: ['POST'])]
    public function cancel(Request $request, SessionRegistration $registration): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $registrationId = $registration->getId();
        $csrfToken = $request->request->get('_token');

        $this->logger->info('Starting registration cancellation process', [
            'user' => $userIdentifier,
            'registration_id' => $registrationId,
            'current_status' => $registration->getStatus(),
            'session_id' => $registration->getSession()?->getId(),
            'participant_email' => $registration->getEmail(),
            'csrf_token_provided' => !empty($csrfToken),
        ]);

        try {
            $this->logger->debug('Validating CSRF token for registration cancellation', [
                'user' => $userIdentifier,
                'registration_id' => $registrationId,
                'token_intention' => 'cancel' . $registrationId,
            ]);

            if ($this->isCsrfTokenValid('cancel' . $registrationId, $csrfToken)) {
                $this->logger->debug('CSRF token validated successfully, proceeding with cancellation', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                ]);

                // Get current session before changes for logging
                $session = $registration->getSession();
                $previousRegistrationsCount = $session->getConfirmedRegistrationsCount();
                
                $this->logger->debug('Current session state before cancellation', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                    'session_name' => $session->getName(),
                    'previous_confirmed_count' => $previousRegistrationsCount,
                    'session_max_participants' => $session->getMaxCapacity(),
                ]);

                // Cancel the registration
                $this->logger->debug('Calling registration cancel method', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'previous_status' => $registration->getStatus(),
                ]);

                $registration->cancel();

                $this->logger->debug('Registration status updated to cancelled', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'new_status' => $registration->getStatus(),
                ]);

                // Update session registration count
                $this->logger->debug('Updating session registrations count', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                ]);

                $session->updateRegistrationsCount();
                $newRegistrationsCount = $session->getConfirmedRegistrationsCount();

                $this->logger->debug('Session registrations count updated', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                    'previous_confirmed_count' => $previousRegistrationsCount,
                    'new_confirmed_count' => $newRegistrationsCount,
                    'count_difference' => $newRegistrationsCount - $previousRegistrationsCount,
                ]);

                // Persist changes to database
                $this->logger->debug('Flushing entity manager to persist changes', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                ]);

                $this->entityManager->flush();

                $this->logger->info('Registration cancelled successfully', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                    'participant_email' => $registration->getEmail(),
                    'participant_name' => $registration->getFirstName() . ' ' . $registration->getLastName(),
                    'formation_title' => $session->getFormation()?->getTitle(),
                    'session_name' => $session->getName(),
                    'previous_confirmed_count' => $previousRegistrationsCount,
                    'new_confirmed_count' => $newRegistrationsCount,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                $this->addFlash('success', 'L\'inscription a été annulée.');
                
            } else {
                $this->logger->warning('Invalid CSRF token for registration cancellation', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'token_intention' => 'cancel' . $registrationId,
                    'provided_token' => $csrfToken,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

        } catch (Exception $e) {
            $this->logger->error('Error cancelling registration', [
                'user' => $userIdentifier,
                'registration_id' => $registrationId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'session_id' => $registration->getSession()?->getId(),
                'participant_email' => $registration->getEmail(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'annulation de l\'inscription.');
        }

        return $this->redirectToRoute('admin_session_registration_show', ['id' => $registrationId]);
    }

    /**
     * Update registration status.
     */
    #[Route('/{id}/update-status', name: 'admin_session_registration_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, SessionRegistration $registration): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $registrationId = $registration->getId();
        $csrfToken = $request->request->get('_token');
        $newStatus = $request->request->get('status');

        $this->logger->info('Starting registration status update process', [
            'user' => $userIdentifier,
            'registration_id' => $registrationId,
            'current_status' => $registration->getStatus(),
            'requested_status' => $newStatus,
            'session_id' => $registration->getSession()?->getId(),
            'participant_email' => $registration->getEmail(),
            'csrf_token_provided' => !empty($csrfToken),
        ]);

        try {
            $this->logger->debug('Validating CSRF token for status update', [
                'user' => $userIdentifier,
                'registration_id' => $registrationId,
                'token_intention' => 'update_status' . $registrationId,
            ]);

            if ($this->isCsrfTokenValid('update_status' . $registrationId, $csrfToken)) {
                $this->logger->debug('CSRF token validated successfully, proceeding with status update', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                ]);

                $validStatuses = ['pending', 'confirmed', 'cancelled', 'attended', 'no_show'];
                
                $this->logger->debug('Validating requested status', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'requested_status' => $newStatus,
                    'valid_statuses' => $validStatuses,
                    'is_valid' => in_array($newStatus, $validStatuses, true),
                ]);

                if (in_array($newStatus, $validStatuses, true)) {
                    // Get current session and status before changes for logging
                    $session = $registration->getSession();
                    $previousStatus = $registration->getStatus();
                    $previousRegistrationsCount = $session->getConfirmedRegistrationsCount();
                    
                    $this->logger->debug('Current state before status update', [
                        'user' => $userIdentifier,
                        'registration_id' => $registrationId,
                        'session_id' => $session->getId(),
                        'session_name' => $session->getName(),
                        'previous_status' => $previousStatus,
                        'new_status' => $newStatus,
                        'previous_confirmed_count' => $previousRegistrationsCount,
                        'session_max_participants' => $session->getMaxCapacity(),
                    ]);

                    // Update the registration status
                    $this->logger->debug('Setting new registration status', [
                        'user' => $userIdentifier,
                        'registration_id' => $registrationId,
                        'previous_status' => $previousStatus,
                        'new_status' => $newStatus,
                    ]);

                    $registration->setStatus($newStatus);

                    $this->logger->debug('Registration status updated', [
                        'user' => $userIdentifier,
                        'registration_id' => $registrationId,
                        'updated_status' => $registration->getStatus(),
                    ]);

                    // Update session registration count
                    $this->logger->debug('Updating session registrations count', [
                        'user' => $userIdentifier,
                        'registration_id' => $registrationId,
                        'session_id' => $session->getId(),
                    ]);

                    $session->updateRegistrationsCount();
                    $newRegistrationsCount = $session->getConfirmedRegistrationsCount();

                    $this->logger->debug('Session registrations count updated', [
                        'user' => $userIdentifier,
                        'registration_id' => $registrationId,
                        'session_id' => $session->getId(),
                        'previous_confirmed_count' => $previousRegistrationsCount,
                        'new_confirmed_count' => $newRegistrationsCount,
                        'count_difference' => $newRegistrationsCount - $previousRegistrationsCount,
                    ]);

                    // Persist changes to database
                    $this->logger->debug('Flushing entity manager to persist changes', [
                        'user' => $userIdentifier,
                        'registration_id' => $registrationId,
                    ]);

                    $this->entityManager->flush();

                    $this->logger->info('Registration status updated successfully', [
                        'user' => $userIdentifier,
                        'registration_id' => $registrationId,
                        'session_id' => $session->getId(),
                        'participant_email' => $registration->getEmail(),
                        'participant_name' => $registration->getFirstName() . ' ' . $registration->getLastName(),
                        'formation_title' => $session->getFormation()?->getTitle(),
                        'session_name' => $session->getName(),
                        'previous_status' => $previousStatus,
                        'new_status' => $newStatus,
                        'previous_confirmed_count' => $previousRegistrationsCount,
                        'new_confirmed_count' => $newRegistrationsCount,
                        'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    ]);

                    $this->addFlash('success', 'Le statut de l\'inscription a été modifié.');
                    
                } else {
                    $this->logger->warning('Invalid status provided for registration update', [
                        'user' => $userIdentifier,
                        'registration_id' => $registrationId,
                        'requested_status' => $newStatus,
                        'valid_statuses' => $validStatuses,
                    ]);

                    $this->addFlash('error', 'Statut invalide fourni.');
                }
                
            } else {
                $this->logger->warning('Invalid CSRF token for registration status update', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'token_intention' => 'update_status' . $registrationId,
                    'provided_token' => $csrfToken,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            }

        } catch (Exception $e) {
            $this->logger->error('Error updating registration status', [
                'user' => $userIdentifier,
                'registration_id' => $registrationId,
                'requested_status' => $newStatus,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'session_id' => $registration->getSession()?->getId(),
                'participant_email' => $registration->getEmail(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors de la modification du statut.');
        }

        return $this->redirectToRoute('admin_session_registration_show', ['id' => $registrationId]);
    }

    /**
     * Delete a registration.
     */
    #[Route('/{id}/delete', name: 'admin_session_registration_delete', methods: ['POST'])]
    public function delete(Request $request, SessionRegistration $registration): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();
        $registrationId = $registration->getId();
        $csrfToken = $request->request->get('_token');

        // Capture registration data before deletion for logging
        $registrationData = [
            'id' => $registrationId,
            'email' => $registration->getEmail(),
            'name' => $registration->getFirstName() . ' ' . $registration->getLastName(),
            'company' => $registration->getCompany(),
            'status' => $registration->getStatus(),
            'session_id' => $registration->getSession()?->getId(),
            'session_name' => $registration->getSession()?->getName(),
            'formation_title' => $registration->getSession()?->getFormation()?->getTitle(),
            'created_at' => $registration->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];

        $this->logger->info('Starting registration deletion process', [
            'user' => $userIdentifier,
            'registration_data' => $registrationData,
            'csrf_token_provided' => !empty($csrfToken),
        ]);

        try {
            $this->logger->debug('Validating CSRF token for registration deletion', [
                'user' => $userIdentifier,
                'registration_id' => $registrationId,
                'token_intention' => 'delete' . $registrationId,
            ]);

            if ($this->isCsrfTokenValid('delete' . $registrationId, $csrfToken)) {
                $this->logger->debug('CSRF token validated successfully, proceeding with deletion', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                ]);

                // Get current session before deletion for logging
                $session = $registration->getSession();
                $previousRegistrationsCount = $session->getConfirmedRegistrationsCount();
                
                $this->logger->debug('Current session state before deletion', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                    'session_name' => $session->getName(),
                    'previous_confirmed_count' => $previousRegistrationsCount,
                    'session_max_participants' => $session->getMaxCapacity(),
                ]);

                // Remove the registration from the entity manager
                $this->logger->debug('Removing registration from entity manager', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                ]);

                $this->entityManager->remove($registration);

                $this->logger->debug('Registration marked for removal', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                ]);

                // Update session registration count
                $this->logger->debug('Updating session registrations count', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                ]);

                $session->updateRegistrationsCount();
                $newRegistrationsCount = $session->getConfirmedRegistrationsCount();

                $this->logger->debug('Session registrations count updated', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'session_id' => $session->getId(),
                    'previous_confirmed_count' => $previousRegistrationsCount,
                    'new_confirmed_count' => $newRegistrationsCount,
                    'count_difference' => $newRegistrationsCount - $previousRegistrationsCount,
                ]);

                // Persist changes to database
                $this->logger->debug('Flushing entity manager to persist deletion', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                ]);

                $this->entityManager->flush();

                $this->logger->info('Registration deleted successfully', [
                    'user' => $userIdentifier,
                    'deleted_registration' => $registrationData,
                    'session_id' => $session->getId(),
                    'session_name' => $session->getName(),
                    'previous_confirmed_count' => $previousRegistrationsCount,
                    'new_confirmed_count' => $newRegistrationsCount,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                $this->addFlash('success', 'L\'inscription a été supprimée.');
                
            } else {
                $this->logger->warning('Invalid CSRF token for registration deletion', [
                    'user' => $userIdentifier,
                    'registration_id' => $registrationId,
                    'token_intention' => 'delete' . $registrationId,
                    'provided_token' => $csrfToken,
                ]);

                $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
                return $this->redirectToRoute('admin_session_registration_show', ['id' => $registrationId]);
            }

        } catch (Exception $e) {
            $this->logger->error('Error deleting registration', [
                'user' => $userIdentifier,
                'registration_data' => $registrationData,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors de la suppression de l\'inscription.');
            return $this->redirectToRoute('admin_session_registration_show', ['id' => $registrationId]);
        }

        return $this->redirectToRoute('admin_session_registration_index');
    }

    /**
     * Export all registrations to CSV.
     */
    #[Route('/export', name: 'admin_session_registration_export', methods: ['GET'])]
    public function export(Request $request, SessionRegistrationRepository $registrationRepository): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $this->logger->info('Starting session registrations export', [
                'user' => $userIdentifier,
                'request_uri' => $request->getRequestUri(),
                'client_ip' => $request->getClientIp(),
            ]);

            // Get filter parameters
            $filters = [
                'search' => $request->query->get('search', ''),
                'session' => $request->query->get('session', ''),
                'formation' => $request->query->get('formation', ''),
                'status' => $request->query->get('status', ''),
                'date_from' => $request->query->get('date_from', ''),
                'date_to' => $request->query->get('date_to', ''),
            ];

            $this->logger->debug('Export filters applied', [
                'user' => $userIdentifier,
                'filters' => $filters,
            ]);

            $this->logger->debug('Executing query to retrieve all registrations for export', [
                'user' => $userIdentifier,
                'filters_applied' => count(array_filter($filters)),
            ]);

            $registrations = $registrationRepository->createAdminQueryBuilder($filters)
                ->getQuery()
                ->getResult()
            ;

            $registrationsCount = count($registrations);

            $this->logger->debug('Registrations retrieved for export', [
                'user' => $userIdentifier,
                'registrations_count' => $registrationsCount,
            ]);

            $this->logger->debug('Setting up CSV response headers', [
                'user' => $userIdentifier,
                'filename' => 'inscriptions_sessions.csv',
            ]);

            $response = new Response();
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="inscriptions_sessions.csv"');

            $output = fopen('php://output', 'w');

            if ($output === false) {
                throw new Exception('Failed to open output stream for CSV export');
            }

            $this->logger->debug('Writing CSV headers', [
                'user' => $userIdentifier,
            ]);

            // CSV headers
            $csvHeaders = [
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
                'Besoins spécifiques',
            ];

            fputcsv($output, $csvHeaders, ';');

            $this->logger->debug('Writing CSV data rows', [
                'user' => $userIdentifier,
                'rows_to_write' => $registrationsCount,
            ]);

            // CSV data
            $rowsWritten = 0;
            foreach ($registrations as $registration) {
                try {
                    $csvRow = [
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
                        $registration->getSpecialRequirements(),
                    ];

                    fputcsv($output, $csvRow, ';');
                    $rowsWritten++;

                } catch (Exception $e) {
                    $this->logger->warning('Error writing CSV row for registration', [
                        'user' => $userIdentifier,
                        'registration_id' => $registration->getId(),
                        'error_message' => $e->getMessage(),
                        'row_number' => $rowsWritten + 1,
                    ]);
                    
                    // Continue with next registration instead of failing the entire export
                    continue;
                }
            }

            $this->logger->debug('Closing CSV output stream', [
                'user' => $userIdentifier,
                'rows_written' => $rowsWritten,
            ]);

            fclose($output);

            $this->logger->info('Session registrations exported successfully', [
                'user' => $userIdentifier,
                'total_registrations' => $registrationsCount,
                'rows_written' => $rowsWritten,
                'filters_applied' => $filters,
                'filename' => 'inscriptions_sessions.csv',
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $response;

        } catch (Exception $e) {
            $this->logger->error('Error exporting session registrations', [
                'user' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'export des inscriptions.');
            
            return $this->redirectToRoute('admin_session_registration_index');
        }
    }
}
