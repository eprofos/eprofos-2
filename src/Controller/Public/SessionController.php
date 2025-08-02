<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\Training\Session;
use App\Entity\Training\SessionRegistration;
use App\Form\Training\SessionRegistrationType;
use App\Repository\Training\SessionRegistrationRepository;
use App\Repository\Training\SessionRepository;
use App\Service\CRM\ProspectManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Public Session Controller.
 *
 * Handles session display and registration for public users.
 *
 * Enhanced with comprehensive logging for:
 * - Session access tracking with user context (IP, user agent)
 * - Registration process monitoring (form validation, database operations)
 * - Cancellation workflow tracking with token validation
 * - Email sending operations (both confirmation and cancellation)
 * - Error handling with detailed context and stack traces
 * - Database transaction monitoring
 * - Prospect creation integration logging
 *
 * All operations include detailed context logging for debugging and audit purposes.
 */
#[Route('/sessions')]
class SessionController extends AbstractController
{
    public function __construct(
        private SessionRepository $sessionRepository,
        private SessionRegistrationRepository $registrationRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        private ProspectManagementService $prospectService,
    ) {}

    /**
     * Display session details.
     */
    #[Route('/{id}', name: 'public_session_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Session $session): Response
    {
        try {
            $this->logger->info('Session show request initiated', [
                'session_id' => $session->getId(),
                'session_name' => $session->getName(),
                'session_status' => $session->getStatus(),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            // Check if session is active and visible
            if (!$session->isActive()) {
                $this->logger->warning('Attempt to access inactive session', [
                    'session_id' => $session->getId(),
                    'session_status' => $session->getStatus(),
                    'session_name' => $session->getName(),
                    'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                throw $this->createNotFoundException('Cette session n\'est pas disponible.');
            }

            $this->logger->info('Session details viewed successfully', [
                'session_id' => $session->getId(),
                'session_name' => $session->getName(),
                'formation_id' => $session->getFormation()?->getId(),
                'formation_title' => $session->getFormation()?->getTitle(),
                'start_date' => $session->getStartDate()?->format('Y-m-d H:i:s'),
                'end_date' => $session->getEndDate()?->format('Y-m-d H:i:s'),
                'max_capacity' => $session->getMaxCapacity(),
                'min_capacity' => $session->getMinCapacity(),
                'current_registrations' => $session->getCurrentRegistrations(),
                'available_spots' => $session->getAvailablePlaces(),
                'is_full' => $session->isFull(),
                'location' => $session->getLocation(),
                'price' => $session->getPrice(),
                'status' => $session->getStatus(),
            ]);

            return $this->render('public/session/show.html.twig', [
                'session' => $session,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error displaying session details', [
                'session_id' => $session->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            if ($e instanceof NotFoundHttpException) {
                throw $e;
            }

            throw $this->createNotFoundException('Une erreur est survenue lors de l\'affichage de la session.');
        }
    }

    /**
     * Register for a session.
     */
    #[Route('/{id}/register', name: 'public_session_register', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function register(Request $request, Session $session): Response
    {
        try {
            $this->logger->info('Session registration request initiated', [
                'session_id' => $session->getId(),
                'session_name' => $session->getName(),
                'formation_id' => $session->getFormation()?->getId(),
                'formation_title' => $session->getFormation()?->getTitle(),
                'request_method' => $request->getMethod(),
                'is_registration_open' => $session->isRegistrationOpen(),
                'current_registrations' => $session->getCurrentRegistrations(),
                'max_capacity' => $session->getMaxCapacity(),
                'is_full' => $session->isFull(),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            // Check if session is available for registration
            if (!$session->isRegistrationOpen()) {
                $this->logger->warning('Attempt to register for closed session', [
                    'session_id' => $session->getId(),
                    'session_name' => $session->getName(),
                    'registration_status' => 'closed',
                    'start_date' => $session->getStartDate()?->format('Y-m-d H:i:s'),
                    'end_date' => $session->getEndDate()?->format('Y-m-d H:i:s'),
                    'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                $this->addFlash('error', 'Les inscriptions pour cette session ne sont plus ouvertes.');

                return $this->redirectToRoute('public_session_show', ['id' => $session->getId()]);
            }

            $registration = new SessionRegistration();
            $registration->setSession($session);

            $this->logger->debug('Session registration form initialized', [
                'session_id' => $session->getId(),
                'registration_object_created' => true,
            ]);

            $form = $this->createForm(SessionRegistrationType::class, $registration);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('Session registration form submitted and valid', [
                    'session_id' => $session->getId(),
                    'email' => $registration->getEmail(),
                    'first_name' => $registration->getFirstName(),
                    'last_name' => $registration->getLastName(),
                    'company' => $registration->getCompany(),
                    'phone' => $registration->getPhone(),
                    'form_valid' => true,
                ]);

                try {
                    // Check if email is already registered for this session
                    if ($this->registrationRepository->isEmailRegisteredForSession($registration->getEmail(), $session)) {
                        $this->logger->warning('Duplicate email registration attempt', [
                            'session_id' => $session->getId(),
                            'email' => $registration->getEmail(),
                            'duplicate_registration_blocked' => true,
                        ]);

                        $this->addFlash('error', 'Cette adresse email est déjà inscrite pour cette session.');

                        return $this->redirectToRoute('public_session_register', ['id' => $session->getId()]);
                    }

                    // Check if session is still available
                    if ($session->isFull()) {
                        $this->logger->warning('Registration attempt for full session', [
                            'session_id' => $session->getId(),
                            'email' => $registration->getEmail(),
                            'current_registrations' => $session->getCurrentRegistrations(),
                            'max_capacity' => $session->getMaxCapacity(),
                            'session_full' => true,
                        ]);

                        $this->addFlash('error', 'Cette session est maintenant complète.');

                        return $this->redirectToRoute('public_session_show', ['id' => $session->getId()]);
                    }

                    $this->logger->debug('Starting database transaction for registration', [
                        'session_id' => $session->getId(),
                        'email' => $registration->getEmail(),
                    ]);

                    // Save registration
                    $this->entityManager->persist($registration);

                    // Update session registration count
                    $previousCount = $session->getCurrentRegistrations();
                    $session->setCurrentRegistrations($session->getCurrentRegistrations() + 1);

                    $this->logger->debug('Registration count updated', [
                        'session_id' => $session->getId(),
                        'previous_count' => $previousCount,
                        'new_count' => $session->getCurrentRegistrations(),
                    ]);

                    $this->entityManager->flush();

                    $this->logger->info('Session registration saved successfully', [
                        'session_id' => $session->getId(),
                        'registration_id' => $registration->getId(),
                        'email' => $registration->getEmail(),
                        'database_saved' => true,
                    ]);

                    // Create or update prospect
                    try {
                        $this->logger->debug('Starting prospect creation from registration', [
                            'registration_id' => $registration->getId(),
                            'email' => $registration->getEmail(),
                        ]);

                        $prospect = $this->prospectService->createProspectFromSessionRegistration($registration);

                        $this->logger->info('Prospect created/updated from session registration', [
                            'prospect_id' => $prospect->getId(),
                            'registration_id' => $registration->getId(),
                            'prospect_email' => $prospect->getEmail(),
                            'prospect_status' => $prospect->getStatus(),
                            'prospect_source' => $prospect->getSource(),
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('Failed to create prospect from registration', [
                            'registration_id' => $registration->getId(),
                            'email' => $registration->getEmail(),
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'prospect_creation_failed' => true,
                        ]);
                        // Don't fail the registration if prospect creation fails
                    }

                    // Send confirmation email
                    try {
                        $this->logger->debug('Starting confirmation email sending', [
                            'registration_id' => $registration->getId(),
                            'email' => $registration->getEmail(),
                        ]);

                        $this->sendRegistrationConfirmationEmail($registration);

                        $this->logger->info('Registration confirmation email sent successfully', [
                            'registration_id' => $registration->getId(),
                            'email' => $registration->getEmail(),
                            'email_sent' => true,
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('Failed to send confirmation email', [
                            'registration_id' => $registration->getId(),
                            'email' => $registration->getEmail(),
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'email_failed' => true,
                        ]);
                        // Don't fail the registration if email fails
                    }

                    $this->addFlash('success', 'Votre inscription a été enregistrée avec succès. Vous recevrez un email de confirmation.');
                    $this->logger->info('Session registration completed successfully', [
                        'session_id' => $session->getId(),
                        'registration_id' => $registration->getId(),
                        'email' => $registration->getEmail(),
                        'full_name' => $registration->getFirstName() . ' ' . $registration->getLastName(),
                        'success' => true,
                    ]);

                    return $this->redirectToRoute('public_session_show', ['id' => $session->getId()]);
                } catch (Exception $e) {
                    $this->logger->error('Error creating session registration', [
                        'session_id' => $session->getId(),
                        'email' => $registration->getEmail() ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'registration_failed' => true,
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de votre inscription. Veuillez réessayer.');
                }
            } elseif ($form->isSubmitted()) {
                $this->logger->warning('Session registration form submitted but invalid', [
                    'session_id' => $session->getId(),
                    'form_errors' => (string) $form->getErrors(true),
                    'form_submitted' => true,
                    'form_valid' => false,
                ]);
            }

            return $this->render('public/session/register.html.twig', [
                'session' => $session,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Fatal error in session registration process', [
                'session_id' => $session->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'fatal_error' => true,
            ]);

            $this->addFlash('error', 'Une erreur inattendue est survenue. Veuillez réessayer plus tard.');

            return $this->redirectToRoute('public_session_show', ['id' => $session->getId()]);
        }
    }

    /**
     * Cancel a registration (with token).
     */
    #[Route('/registration/{id}/cancel/{token}', name: 'public_session_registration_cancel', methods: ['GET', 'POST'])]
    public function cancelRegistration(Request $request, SessionRegistration $registration, string $token): Response
    {
        try {
            $this->logger->info('Registration cancellation request initiated', [
                'registration_id' => $registration->getId(),
                'session_id' => $registration->getSession()->getId(),
                'email' => $registration->getEmail(),
                'token_provided' => !empty($token),
                'request_method' => $request->getMethod(),
                'is_already_cancelled' => $registration->isCancelled(),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            // Simple token validation (in production, use a more secure approach)
            $expectedToken = md5($registration->getEmail() . $registration->getId() . $registration->getCreatedAt()->getTimestamp());

            if (!hash_equals($expectedToken, $token)) {
                $this->logger->warning('Invalid token provided for registration cancellation', [
                    'registration_id' => $registration->getId(),
                    'email' => $registration->getEmail(),
                    'provided_token' => $token,
                    'token_valid' => false,
                    'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                throw $this->createNotFoundException('Token invalide.');
            }

            $this->logger->debug('Token validation successful', [
                'registration_id' => $registration->getId(),
                'token_valid' => true,
            ]);

            if ($registration->isCancelled()) {
                $this->logger->info('Attempt to cancel already cancelled registration', [
                    'registration_id' => $registration->getId(),
                    'email' => $registration->getEmail(),
                    'already_cancelled' => true,
                    'status' => $registration->getStatus(),
                ]);

                $this->addFlash('info', 'Cette inscription est déjà annulée.');

                return $this->render('public/session/registration_cancelled.html.twig', [
                    'registration' => $registration,
                ]);
            }

            if ($request->isMethod('POST')) {
                try {
                    $this->logger->info('Processing registration cancellation', [
                        'registration_id' => $registration->getId(),
                        'session_id' => $registration->getSession()->getId(),
                        'email' => $registration->getEmail(),
                        'current_session_registrations' => $registration->getSession()->getCurrentRegistrations(),
                    ]);

                    $session = $registration->getSession();
                    $previousCount = $session->getCurrentRegistrations();

                    $registration->cancel();

                    $this->logger->debug('Registration marked as cancelled', [
                        'registration_id' => $registration->getId(),
                        'status' => $registration->getStatus(),
                    ]);

                    // Update session registration count
                    $session->setCurrentRegistrations(max(0, $session->getCurrentRegistrations() - 1));

                    $this->logger->debug('Session registration count updated after cancellation', [
                        'session_id' => $session->getId(),
                        'previous_count' => $previousCount,
                        'new_count' => $session->getCurrentRegistrations(),
                        'count_decreased_by' => $previousCount - $session->getCurrentRegistrations(),
                    ]);

                    $this->entityManager->flush();

                    $this->logger->info('Registration cancellation saved to database', [
                        'registration_id' => $registration->getId(),
                        'session_id' => $session->getId(),
                        'database_updated' => true,
                    ]);

                    // Send cancellation confirmation email
                    try {
                        $this->logger->debug('Starting cancellation confirmation email', [
                            'registration_id' => $registration->getId(),
                            'email' => $registration->getEmail(),
                        ]);

                        $this->sendCancellationConfirmationEmail($registration);

                        $this->logger->info('Cancellation confirmation email sent successfully', [
                            'registration_id' => $registration->getId(),
                            'email' => $registration->getEmail(),
                            'email_sent' => true,
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('Failed to send cancellation confirmation email', [
                            'registration_id' => $registration->getId(),
                            'email' => $registration->getEmail(),
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'email_failed' => true,
                        ]);
                        // Don't fail the cancellation if email fails
                    }

                    $this->addFlash('success', 'Votre inscription a été annulée avec succès.');
                    $this->logger->info('Registration cancellation completed successfully', [
                        'registration_id' => $registration->getId(),
                        'session_id' => $registration->getSession()->getId(),
                        'email' => $registration->getEmail(),
                        'cancellation_success' => true,
                    ]);

                    return $this->render('public/session/registration_cancelled.html.twig', [
                        'registration' => $registration,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error during registration cancellation process', [
                        'registration_id' => $registration->getId(),
                        'session_id' => $registration->getSession()->getId(),
                        'email' => $registration->getEmail(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'cancellation_failed' => true,
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de l\'annulation.');
                }
            }

            return $this->render('public/session/cancel_registration.html.twig', [
                'registration' => $registration,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Fatal error in registration cancellation process', [
                'registration_id' => $registration->getId(),
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'fatal_error' => true,
            ]);

            if ($e instanceof NotFoundHttpException) {
                throw $e;
            }

            $this->addFlash('error', 'Une erreur inattendue est survenue lors de l\'annulation.');

            return $this->render('public/session/cancel_registration.html.twig', [
                'registration' => $registration,
            ]);
        }
    }

    /**
     * Send registration confirmation email.
     */
    private function sendRegistrationConfirmationEmail(SessionRegistration $registration): void
    {
        try {
            $session = $registration->getSession();
            $formation = $session->getFormation();

            $this->logger->info('Starting registration confirmation email preparation', [
                'registration_id' => $registration->getId(),
                'email' => $registration->getEmail(),
                'session_id' => $session->getId(),
                'formation_id' => $formation?->getId(),
                'formation_title' => $formation?->getTitle(),
            ]);

            $cancelToken = md5($registration->getEmail() . $registration->getId() . $registration->getCreatedAt()->getTimestamp());
            $cancelUrl = $this->generateUrl('public_session_registration_cancel', [
                'id' => $registration->getId(),
                'token' => $cancelToken,
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $this->logger->debug('Cancel URL generated for registration', [
                'registration_id' => $registration->getId(),
                'cancel_url_generated' => !empty($cancelUrl),
                'token_generated' => !empty($cancelToken),
            ]);

            $email = (new Email())
                ->from('contact@eprofos.com')
                ->to($registration->getEmail())
                ->subject('Confirmation d\'inscription - ' . $formation->getTitle())
                ->html($this->renderView('emails/session_registration_confirmation.html.twig', [
                    'registration' => $registration,
                    'session' => $session,
                    'formation' => $formation,
                    'cancel_url' => $cancelUrl,
                ]))
            ;

            $this->logger->debug('Email object created successfully', [
                'registration_id' => $registration->getId(),
                'from' => 'contact@eprofos.com',
                'to' => $registration->getEmail(),
                'subject' => 'Confirmation d\'inscription - ' . $formation->getTitle(),
                'template_rendered' => true,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Registration confirmation email sent successfully', [
                'registration_id' => $registration->getId(),
                'email' => $registration->getEmail(),
                'session_id' => $session->getId(),
                'formation_id' => $formation?->getId(),
                'email_sent_successfully' => true,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error sending registration confirmation email', [
                'registration_id' => $registration->getId(),
                'email' => $registration->getEmail(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email_failed' => true,
            ]);

            throw $e; // Re-throw to be handled by the calling method
        }
    }

    /**
     * Send cancellation confirmation email.
     */
    private function sendCancellationConfirmationEmail(SessionRegistration $registration): void
    {
        try {
            $session = $registration->getSession();
            $formation = $session->getFormation();

            $this->logger->info('Starting cancellation confirmation email preparation', [
                'registration_id' => $registration->getId(),
                'email' => $registration->getEmail(),
                'session_id' => $session->getId(),
                'formation_id' => $formation?->getId(),
                'formation_title' => $formation?->getTitle(),
                'registration_status' => $registration->getStatus(),
            ]);

            $email = (new Email())
                ->from('contact@eprofos.com')
                ->to($registration->getEmail())
                ->subject('Annulation d\'inscription - ' . $formation->getTitle())
                ->html($this->renderView('emails/session_registration_cancellation.html.twig', [
                    'registration' => $registration,
                    'session' => $session,
                    'formation' => $formation,
                ]))
            ;

            $this->logger->debug('Cancellation email object created successfully', [
                'registration_id' => $registration->getId(),
                'from' => 'contact@eprofos.com',
                'to' => $registration->getEmail(),
                'subject' => 'Annulation d\'inscription - ' . $formation->getTitle(),
                'template_rendered' => true,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Cancellation confirmation email sent successfully', [
                'registration_id' => $registration->getId(),
                'email' => $registration->getEmail(),
                'session_id' => $session->getId(),
                'formation_id' => $formation?->getId(),
                'email_sent_successfully' => true,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error sending cancellation confirmation email', [
                'registration_id' => $registration->getId(),
                'email' => $registration->getEmail(),
                'session_id' => $registration->getSession()->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email_failed' => true,
            ]);

            throw $e; // Re-throw to be handled by the calling method
        }
    }
}
