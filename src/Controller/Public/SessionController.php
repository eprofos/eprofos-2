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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Public Session Controller.
 *
 * Handles session display and registration for public users.
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
    #[Route('/{id}', name: 'app_session_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Session $session): Response
    {
        // Check if session is active and visible
        if (!$session->isActive()) {
            throw $this->createNotFoundException('Cette session n\'est pas disponible.');
        }

        $this->logger->info('Session details viewed', [
            'session_id' => $session->getId(),
            'session_name' => $session->getName(),
        ]);

        return $this->render('public/session/show.html.twig', [
            'session' => $session,
        ]);
    }

    /**
     * Register for a session.
     */
    #[Route('/{id}/register', name: 'app_session_register', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function register(Request $request, Session $session): Response
    {
        // Check if session is available for registration
        if (!$session->isRegistrationOpen()) {
            $this->addFlash('error', 'Les inscriptions pour cette session ne sont plus ouvertes.');

            return $this->redirectToRoute('app_session_show', ['id' => $session->getId()]);
        }

        $registration = new SessionRegistration();
        $registration->setSession($session);

        $form = $this->createForm(SessionRegistrationType::class, $registration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Check if email is already registered for this session
                if ($this->registrationRepository->isEmailRegisteredForSession($registration->getEmail(), $session)) {
                    $this->addFlash('error', 'Cette adresse email est déjà inscrite pour cette session.');

                    return $this->redirectToRoute('app_session_register', ['id' => $session->getId()]);
                }

                // Check if session is still available
                if ($session->isFull()) {
                    $this->addFlash('error', 'Cette session est maintenant complète.');

                    return $this->redirectToRoute('app_session_show', ['id' => $session->getId()]);
                }

                // Save registration
                $this->entityManager->persist($registration);

                // Update session registration count
                $session->setCurrentRegistrations($session->getCurrentRegistrations() + 1);

                $this->entityManager->flush();

                // Create or update prospect
                try {
                    $prospect = $this->prospectService->createProspectFromSessionRegistration($registration);

                    $this->logger->info('Prospect created/updated from session registration', [
                        'prospect_id' => $prospect->getId(),
                        'registration_id' => $registration->getId(),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to create prospect from registration', [
                        'registration_id' => $registration->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the registration if prospect creation fails
                }

                // Send confirmation email
                $this->sendRegistrationConfirmationEmail($registration);

                $this->addFlash('success', 'Votre inscription a été enregistrée avec succès. Vous recevrez un email de confirmation.');
                $this->logger->info('New session registration', [
                    'session_id' => $session->getId(),
                    'registration_id' => $registration->getId(),
                    'email' => $registration->getEmail(),
                ]);

                return $this->redirectToRoute('app_session_show', ['id' => $session->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de votre inscription. Veuillez réessayer.');
                $this->logger->error('Error creating session registration', [
                    'session_id' => $session->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->render('public/session/register.html.twig', [
            'session' => $session,
            'form' => $form,
        ]);
    }

    /**
     * Cancel a registration (with token).
     */
    #[Route('/registration/{id}/cancel/{token}', name: 'app_session_registration_cancel', methods: ['GET', 'POST'])]
    public function cancelRegistration(Request $request, SessionRegistration $registration, string $token): Response
    {
        // Simple token validation (in production, use a more secure approach)
        $expectedToken = md5($registration->getEmail() . $registration->getId() . $registration->getCreatedAt()->getTimestamp());

        if (!hash_equals($expectedToken, $token)) {
            throw $this->createNotFoundException('Token invalide.');
        }

        if ($registration->isCancelled()) {
            $this->addFlash('info', 'Cette inscription est déjà annulée.');

            return $this->render('public/session/registration_cancelled.html.twig', [
                'registration' => $registration,
            ]);
        }

        if ($request->isMethod('POST')) {
            try {
                $registration->cancel();

                // Update session registration count
                $session = $registration->getSession();
                $session->setCurrentRegistrations(max(0, $session->getCurrentRegistrations() - 1));

                $this->entityManager->flush();

                // Send cancellation confirmation email
                $this->sendCancellationConfirmationEmail($registration);

                $this->addFlash('success', 'Votre inscription a été annulée avec succès.');
                $this->logger->info('Session registration cancelled', [
                    'registration_id' => $registration->getId(),
                    'session_id' => $registration->getSession()->getId(),
                ]);

                return $this->render('public/session/registration_cancelled.html.twig', [
                    'registration' => $registration,
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'annulation.');
                $this->logger->error('Error cancelling registration', [
                    'registration_id' => $registration->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->render('public/session/cancel_registration.html.twig', [
            'registration' => $registration,
        ]);
    }

    /**
     * Send registration confirmation email.
     */
    private function sendRegistrationConfirmationEmail(SessionRegistration $registration): void
    {
        try {
            $session = $registration->getSession();
            $formation = $session->getFormation();

            $cancelToken = md5($registration->getEmail() . $registration->getId() . $registration->getCreatedAt()->getTimestamp());
            $cancelUrl = $this->generateUrl('app_session_registration_cancel', [
                'id' => $registration->getId(),
                'token' => $cancelToken,
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new Email())
                ->from('contact@eprofos.fr')
                ->to($registration->getEmail())
                ->subject('Confirmation d\'inscription - ' . $formation->getTitle())
                ->html($this->renderView('emails/session_registration_confirmation.html.twig', [
                    'registration' => $registration,
                    'session' => $session,
                    'formation' => $formation,
                    'cancel_url' => $cancelUrl,
                ]))
            ;

            $this->mailer->send($email);
        } catch (Exception $e) {
            $this->logger->error('Error sending registration confirmation email', [
                'registration_id' => $registration->getId(),
                'error' => $e->getMessage(),
            ]);
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

            $email = (new Email())
                ->from('contact@eprofos.fr')
                ->to($registration->getEmail())
                ->subject('Annulation d\'inscription - ' . $formation->getTitle())
                ->html($this->renderView('emails/session_registration_cancellation.html.twig', [
                    'registration' => $registration,
                    'session' => $session,
                    'formation' => $formation,
                ]))
            ;

            $this->mailer->send($email);
        } catch (Exception $e) {
            $this->logger->error('Error sending cancellation confirmation email', [
                'registration_id' => $registration->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
