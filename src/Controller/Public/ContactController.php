<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\CRM\ContactRequest;
use App\Entity\Training\Formation;
use App\Repository\CRM\ContactRequestRepository;
use App\Repository\Training\FormationRepository;
use App\Service\CRM\ProspectManagementService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contact controller for handling contact forms.
 *
 * Manages different types of contact requests: quote, consultation,
 * information, and quick registration with email notifications.
 */
#[Route('/contact')]
class ContactController extends AbstractController
{
    public function __construct(
        private ContactRequestRepository $contactRequestRepository,
        private FormationRepository $formationRepository,
        private ValidatorInterface $validator,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private ProspectManagementService $prospectService,
    ) {}

    /**
     * Display the main contact page with all form types.
     */
    #[Route('', name: 'public_contact_index', methods: ['GET'])]
    public function index(): Response
    {
        try {
            $this->logger->info('Contact index page accessed', [
                'route' => 'public_contact_index',
                'method' => 'GET',
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            // Get formations for quick registration dropdown
            $this->logger->debug('Fetching active formations for contact page');
            $formations = $this->formationRepository->findActiveFormations();
            
            $this->logger->info('Active formations retrieved successfully', [
                'formations_count' => count($formations),
            ]);

            return $this->render('public/contact/index.html.twig', [
                'formations' => $formations,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error displaying contact index page', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de la page de contact.');
            
            // Return a minimal contact page without formations
            return $this->render('public/contact/index.html.twig', [
                'formations' => [],
            ]);
        }
    }

    /**
     * Display quote request form (GET).
     */
    #[Route('/devis', name: 'public_contact_quote', methods: ['GET'])]
    public function quoteForm(Request $request): Response
    {
        $this->logger->info('Quote form accessed via GET', [
            'service_id' => $request->query->get('service'),
            'referer' => $request->headers->get('referer'),
            'user_agent' => $request->headers->get('user-agent'),
        ]);

        // Redirect to contact page with quote section
        return $this->redirectToRoute('public_contact_index', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Handle consultation request form submission.
     */
    #[Route('/conseil', name: 'public_contact_consultation', methods: ['POST'])]
    public function consultation(Request $request): Response
    {
        try {
            $this->logger->info('Consultation request initiated', [
                'route' => 'public_contact_consultation',
                'method' => 'POST',
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referer' => $request->headers->get('referer'),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $contactRequest = new ContactRequest();
            $contactRequest->setType('advice');

            $this->logger->debug('ContactRequest entity created for consultation', [
                'type' => 'advice',
                'entity_id' => spl_object_id($contactRequest),
            ]);

            return $this->handleContactForm($request, $contactRequest, 'consultation');
        } catch (Exception $e) {
            $this->logger->error('Error processing consultation request', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'first_name' => $request->request->get('first_name'),
                    'last_name' => $request->request->get('last_name'),
                    'email' => $request->request->get('email'),
                    'company' => $request->request->get('company'),
                ],
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du traitement de votre demande de conseil. Veuillez réessayer.');
            return $this->redirectToRoute('public_contact_index');
        }
    }

    /**
     * Handle general information request form submission.
     */
    #[Route('/information', name: 'public_contact_information', methods: ['POST'])]
    public function information(Request $request): Response
    {
        try {
            $this->logger->info('Information request initiated', [
                'route' => 'public_contact_information',
                'method' => 'POST',
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referer' => $request->headers->get('referer'),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $contactRequest = new ContactRequest();
            $contactRequest->setType('information');

            $this->logger->debug('ContactRequest entity created for information', [
                'type' => 'information',
                'entity_id' => spl_object_id($contactRequest),
            ]);

            return $this->handleContactForm($request, $contactRequest, 'information');
        } catch (Exception $e) {
            $this->logger->error('Error processing information request', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'first_name' => $request->request->get('first_name'),
                    'last_name' => $request->request->get('last_name'),
                    'email' => $request->request->get('email'),
                    'company' => $request->request->get('company'),
                ],
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du traitement de votre demande d\'information. Veuillez réessayer.');
            return $this->redirectToRoute('public_contact_index');
        }
    }

    /**
     * Handle quick registration form submission.
     */
    #[Route('/inscription-rapide', name: 'public_contact_quick_registration', methods: ['POST'])]
    public function quickRegistration(Request $request): Response
    {
        try {
            $this->logger->info('Quick registration request initiated', [
                'route' => 'public_contact_quick_registration',
                'method' => 'POST',
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referer' => $request->headers->get('referer'),
                'formation_id' => $request->request->get('formation_id'),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $contactRequest = new ContactRequest();
            $contactRequest->setType('quick_registration');

            $this->logger->debug('ContactRequest entity created for quick registration', [
                'type' => 'quick_registration',
                'entity_id' => spl_object_id($contactRequest),
            ]);

            return $this->handleContactForm($request, $contactRequest, 'quick_registration');
        } catch (Exception $e) {
            $this->logger->error('Error processing quick registration request', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'first_name' => $request->request->get('first_name'),
                    'last_name' => $request->request->get('last_name'),
                    'email' => $request->request->get('email'),
                    'formation_id' => $request->request->get('formation_id'),
                ],
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du traitement de votre inscription rapide. Veuillez réessayer.');
            return $this->redirectToRoute('public_contact_index');
        }
    }

    /**
     * Display quote form for a specific formation.
     */
    #[Route('/devis/formation/{slug}', name: 'public_contact_formation_quote', methods: ['GET'])]
    public function formationQuote(string $slug): Response
    {
        try {
            $this->logger->info('Formation quote form accessed', [
                'route' => 'public_contact_formation_quote',
                'method' => 'GET',
                'formation_slug' => $slug,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $this->logger->debug('Searching for formation by slug', [
                'slug' => $slug,
            ]);

            $formation = $this->formationRepository->findBySlugWithCategory($slug);

            if (!$formation) {
                $this->logger->warning('Formation not found for quote request', [
                    'slug' => $slug,
                    'route' => 'public_contact_formation_quote',
                ]);

                throw $this->createNotFoundException('Formation non trouvée');
            }

            $this->logger->info('Formation found for quote request', [
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'slug' => $slug,
            ]);

            return $this->render('public/contact/formation_quote.html.twig', [
                'formation' => $formation,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error displaying formation quote form', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'slug' => $slug,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                throw $e; // Re-throw 404 exceptions
            }

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du formulaire de devis.');
            return $this->redirectToRoute('public_contact_index');
        }
    }

    /**
     * Handle formation-specific quote request.
     */
    #[Route('/devis/formation/{slug}', name: 'public_contact_formation_quote_submit', methods: ['POST'])]
    public function formationQuoteSubmit(string $slug, Request $request): Response
    {
        try {
            $this->logger->info('Formation quote submission initiated', [
                'route' => 'public_contact_formation_quote_submit',
                'method' => 'POST',
                'formation_slug' => $slug,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $this->logger->debug('Searching for formation by slug for quote submission', [
                'slug' => $slug,
            ]);

            $formation = $this->formationRepository->findBySlugWithCategory($slug);

            if (!$formation) {
                $this->logger->warning('Formation not found for quote submission', [
                    'slug' => $slug,
                    'route' => 'public_contact_formation_quote_submit',
                ]);

                throw $this->createNotFoundException('Formation non trouvée');
            }

            $this->logger->info('Formation found for quote submission', [
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'slug' => $slug,
            ]);

            $contactRequest = new ContactRequest();
            $contactRequest->setType('quote');
            $contactRequest->setFormation($formation);

            $this->logger->debug('ContactRequest entity created for formation quote', [
                'type' => 'quote',
                'formation_id' => $formation->getId(),
                'entity_id' => spl_object_id($contactRequest),
            ]);

            return $this->handleContactForm($request, $contactRequest, 'quote', $formation);
        } catch (Exception $e) {
            $this->logger->error('Error processing formation quote submission', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'slug' => $slug,
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'first_name' => $request->request->get('first_name'),
                    'last_name' => $request->request->get('last_name'),
                    'email' => $request->request->get('email'),
                ],
            ]);

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                throw $e; // Re-throw 404 exceptions
            }

            $this->addFlash('error', 'Une erreur est survenue lors du traitement de votre demande de devis. Veuillez réessayer.');
            return $this->redirectToRoute('public_contact_index');
        }
    }

    /**
     * Handle accessibility request form submission.
     */
    #[Route('/demande-accessibilite', name: 'public_contact_accessibility_request', methods: ['POST'])]
    public function accessibilityRequest(Request $request): Response
    {
        try {
            // Create and populate ContactRequest entity
            $contactRequest = new ContactRequest();
            $contactRequest->setType('accessibility_request');
            $contactRequest->setFirstName($request->request->get('first_name'));
            $contactRequest->setLastName($request->request->get('last_name'));
            $contactRequest->setEmail($request->request->get('email'));
            $contactRequest->setPhone($request->request->get('phone'));
            $contactRequest->setCompany($request->request->get('company'));
            $contactRequest->setMessage($request->request->get('message'));

            // Handle formation selection
            $formationId = $request->request->getInt('formation_id');
            if ($formationId) {
                $formation = $this->formationRepository->find($formationId);
                if ($formation) {
                    $contactRequest->setFormation($formation);
                }
            }

            // Validate the contact request
            $errors = $this->validator->validate($contactRequest);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }

                return $this->redirectToRoute('public_contact_index');
            }

            // Save the contact request
            $this->contactRequestRepository->save($contactRequest, true);

            // Create or update prospect
            try {
                $prospect = $this->prospectService->createProspectFromContactRequest($contactRequest);

                $this->logger->info('Prospect created/updated from accessibility request', [
                    'prospect_id' => $prospect->getId(),
                    'contact_request_id' => $contactRequest->getId(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to create prospect from accessibility request', [
                    'contact_request_id' => $contactRequest->getId(),
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the request if prospect creation fails
            }

            // Send notification email to admin
            $this->sendAccessibilityNotificationEmail($contactRequest);

            // Send notification (which includes both admin and user confirmation)
            $this->sendEmailNotification($contactRequest);

            // Log the activity
            $this->logger->info('Accessibility request submitted', [
                'contact_request_id' => $contactRequest->getId(),
                'email' => $contactRequest->getEmail(),
                'formation_id' => $formationId ?: null,
            ]);

            $this->addFlash('success', 'Votre demande d\'adaptation a été envoyée avec succès. Notre référent handicap vous contactera rapidement.');
        } catch (Exception $e) {
            $this->logger->error('Error processing accessibility request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de votre demande. Veuillez réessayer.');
        }

        // Redirect to formation page if request came from a formation, otherwise to contact page
        $formationId = $request->request->getInt('formation_id');
        if ($formationId) {
            $formation = $this->formationRepository->find($formationId);
            if ($formation) {
                return $this->redirectToRoute('public_formation_show', ['slug' => $formation->getSlug()]);
            }
        }

        return $this->redirectToRoute('public_contact_index');
    }

    /**
     * Common method to handle all contact form types.
     */
    private function handleContactForm(
        Request $request,
        ContactRequest $contactRequest,
        string $formType,
        ?Formation $formation = null,
    ): Response {
        try {
            $this->logger->info('Processing contact form', [
                'form_type' => $formType,
                'formation_id' => $formation?->getId(),
                'formation_title' => $formation?->getTitle(),
                'contact_request_id' => spl_object_id($contactRequest),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            // Extract form data
            $firstName = trim($request->request->get('first_name', ''));
            $lastName = trim($request->request->get('last_name', ''));
            $email = trim($request->request->get('email', ''));
            $phone = trim($request->request->get('phone', ''));
            $company = trim($request->request->get('company', ''));
            $message = trim($request->request->get('message', ''));

            $this->logger->debug('Form data extracted', [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone ? 'provided' : 'not_provided',
                'company' => $company ? 'provided' : 'not_provided',
                'message_length' => strlen($message),
                'form_type' => $formType,
            ]);

            // Handle formation selection for quick registration
            if ($formType === 'quick_registration' && !$formation) {
                $formationId = $request->request->getInt('formation_id');
                
                $this->logger->debug('Handling formation selection for quick registration', [
                    'formation_id' => $formationId,
                ]);

                if ($formationId) {
                    try {
                        $formation = $this->formationRepository->find($formationId);
                        
                        if ($formation) {
                            $contactRequest->setFormation($formation);
                            $this->logger->info('Formation assigned to quick registration', [
                                'formation_id' => $formation->getId(),
                                'formation_title' => $formation->getTitle(),
                            ]);
                        } else {
                            $this->logger->warning('Formation not found for quick registration', [
                                'formation_id' => $formationId,
                            ]);
                        }
                    } catch (Exception $e) {
                        $this->logger->error('Error finding formation for quick registration', [
                            'formation_id' => $formationId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Populate contact request
            $contactRequest
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setEmail($email)
                ->setPhone($phone ?: null)
                ->setCompany($company ?: null)
                ->setMessage($message)
            ;

            $this->logger->debug('ContactRequest entity populated', [
                'entity_id' => spl_object_id($contactRequest),
                'type' => $contactRequest->getType(),
                'full_name' => $contactRequest->getFullName(),
                'email' => $contactRequest->getEmail(),
                'formation_assigned' => $contactRequest->getFormation() ? 'yes' : 'no',
            ]);

            // Validate the contact request
            $this->logger->debug('Starting validation of contact request');
            $errors = $this->validator->validate($contactRequest);

            if (count($errors) > 0) {
                $this->logger->warning('Contact request validation failed', [
                    'error_count' => count($errors),
                    'errors' => array_map(fn($error) => [
                        'property' => $error->getPropertyPath(),
                        'message' => $error->getMessage(),
                        'invalid_value' => $error->getInvalidValue(),
                    ], iterator_to_array($errors)),
                    'form_type' => $formType,
                    'email' => $email,
                ]);

                // Return to form with errors
                $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire.');

                // Store errors in session for display
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                $this->addFlash('form_errors', $errorMessages);

                return $this->redirectToRoute('public_contact_index');
            }

            $this->logger->info('Contact request validation successful');

            // Save to database
            $this->logger->debug('Saving contact request to database');
            $this->contactRequestRepository->save($contactRequest, true);
            
            $this->logger->info('Contact request saved successfully', [
                'contact_request_id' => $contactRequest->getId(),
                'type' => $contactRequest->getType(),
                'email' => $contactRequest->getEmail(),
            ]);

            // Create or update prospect
            try {
                $this->logger->debug('Creating prospect from contact request');
                $prospect = $this->prospectService->createProspectFromContactRequest($contactRequest);

                $this->logger->info('Prospect created/updated from contact request', [
                    'prospect_id' => $prospect->getId(),
                    'contact_request_id' => $contactRequest->getId(),
                    'prospect_status' => $prospect->getStatus(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to create prospect from contact request', [
                    'contact_request_id' => $contactRequest->getId(),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Don't fail the contact form if prospect creation fails
            }

            // Send email notification
            try {
                $this->logger->debug('Sending email notification');
                $this->sendEmailNotification($contactRequest);
                $this->logger->info('Email notification sent successfully');
            } catch (Exception $e) {
                $this->logger->error('Failed to send email notification', [
                    'contact_request_id' => $contactRequest->getId(),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // Don't fail the contact form if email sending fails
            }

            // Log the contact request
            $this->logger->info('Contact request submitted successfully', [
                'type' => $contactRequest->getType(),
                'email' => $contactRequest->getEmail(),
                'formation_id' => $formation?->getId(),
                'contact_request_id' => $contactRequest->getId(),
                'processing_duration' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            // Success message
            $this->addFlash('success', 'Votre demande a été envoyée avec succès. Nous vous recontacterons dans les plus brefs délais.');

        } catch (Exception $e) {
            $this->logger->error('Failed to process contact request', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'type' => $contactRequest->getType(),
                'email' => $email ?? 'unknown',
                'form_type' => $formType,
                'formation_id' => $formation?->getId(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de votre demande. Veuillez réessayer.');
            
            // Redirect to appropriate page even on error
            if ($formation) {
                return $this->redirectToRoute('public_formation_show', ['slug' => $formation->getSlug()]);
            }
            return $this->redirectToRoute('public_contact_index');
        }

        // Redirect based on context
        if ($formation) {
            $this->logger->debug('Redirecting to formation page', [
                'formation_slug' => $formation->getSlug(),
            ]);
            return $this->redirectToRoute('public_formation_show', ['slug' => $formation->getSlug()]);
        }

        $this->logger->debug('Redirecting to contact index page');
        return $this->redirectToRoute('public_contact_index');
    }

    /**
     * Send email notification for contact request.
     */
    private function sendEmailNotification(ContactRequest $contactRequest): void
    {
        try {
            $this->logger->info('Starting email notification process', [
                'contact_request_id' => $contactRequest->getId(),
                'type' => $contactRequest->getType(),
                'email' => $contactRequest->getEmail(),
            ]);

            // Email to EPROFOS team
            $this->logger->debug('Preparing admin notification email');
            $adminEmailContent = $this->generateEmailContent($contactRequest);
            
            $adminEmail = (new Email())
                ->from('noreply@eprofos.com')
                ->replyTo($contactRequest->getEmail())
                ->to('contact@eprofos.com') // Replace with actual EPROFOS email
                ->subject('Nouvelle demande: ' . $contactRequest->getTypeLabel())
                ->text($adminEmailContent)
            ;

            $this->logger->debug('Sending admin notification email', [
                'from' => 'noreply@eprofos.com',
                'to' => 'contact@eprofos.com',
                'reply_to' => $contactRequest->getEmail(),
                'subject' => 'Nouvelle demande: ' . $contactRequest->getTypeLabel(),
            ]);

            $this->mailer->send($adminEmail);
            
            $this->logger->info('Admin notification email sent successfully', [
                'contact_request_id' => $contactRequest->getId(),
            ]);

            // Confirmation email to user
            $this->logger->debug('Preparing user confirmation email');
            $confirmationEmailContent = $this->generateConfirmationEmailContent($contactRequest);
            
            $confirmationEmail = (new Email())
                ->from('contact@eprofos.com')
                ->to($contactRequest->getEmail())
                ->subject('Confirmation de votre demande - EPROFOS')
                ->text($confirmationEmailContent)
            ;

            $this->logger->debug('Sending user confirmation email', [
                'from' => 'contact@eprofos.com',
                'to' => $contactRequest->getEmail(),
                'subject' => 'Confirmation de votre demande - EPROFOS',
            ]);

            $this->mailer->send($confirmationEmail);
            
            $this->logger->info('User confirmation email sent successfully', [
                'contact_request_id' => $contactRequest->getId(),
                'user_email' => $contactRequest->getEmail(),
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to send contact email', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'contact_request_id' => $contactRequest->getId(),
                'user_email' => $contactRequest->getEmail(),
                'contact_type' => $contactRequest->getType(),
            ]);

            // Re-throw the exception to be handled by the calling method
            throw $e;
        }
    }

    /**
     * Generate email content for admin notification.
     */
    private function generateEmailContent(ContactRequest $contactRequest): string
    {
        try {
            $this->logger->debug('Generating admin email content', [
                'contact_request_id' => $contactRequest->getId(),
                'type' => $contactRequest->getType(),
            ]);

            $content = "Nouvelle demande de contact reçue\n\n";
            $content .= 'Type: ' . $contactRequest->getTypeLabel() . "\n";
            $content .= 'Nom: ' . $contactRequest->getFullName() . "\n";
            $content .= 'Email: ' . $contactRequest->getEmail() . "\n";

            if ($contactRequest->getPhone()) {
                $content .= 'Téléphone: ' . $contactRequest->getPhone() . "\n";
            }

            if ($contactRequest->getCompany()) {
                $content .= 'Entreprise: ' . $contactRequest->getCompany() . "\n";
            }

            if ($contactRequest->getFormation()) {
                $content .= 'Formation: ' . $contactRequest->getFormation()->getTitle() . "\n";
                
                $this->logger->debug('Formation included in email content', [
                    'formation_id' => $contactRequest->getFormation()->getId(),
                    'formation_title' => $contactRequest->getFormation()->getTitle(),
                ]);
            }

            $content .= "\nMessage:\n" . $contactRequest->getMessage() . "\n\n";
            $content .= 'Date de la demande: ' . $contactRequest->getCreatedAt()->format('d/m/Y à H:i') . "\n";

            $this->logger->debug('Admin email content generated successfully', [
                'content_length' => strlen($content),
                'contact_request_id' => $contactRequest->getId(),
            ]);

            return $content;
        } catch (Exception $e) {
            $this->logger->error('Error generating admin email content', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'contact_request_id' => $contactRequest->getId(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate confirmation email content for user.
     */
    private function generateConfirmationEmailContent(ContactRequest $contactRequest): string
    {
        try {
            $this->logger->debug('Generating user confirmation email content', [
                'contact_request_id' => $contactRequest->getId(),
                'user_email' => $contactRequest->getEmail(),
                'type' => $contactRequest->getType(),
            ]);

            $content = 'Bonjour ' . $contactRequest->getFirstName() . ",\n\n";
            $content .= 'Nous avons bien reçu votre demande de ' . strtolower($contactRequest->getTypeLabel()) . ".\n\n";
            $content .= "Récapitulatif de votre demande:\n";
            $content .= '- Type: ' . $contactRequest->getTypeLabel() . "\n";
            $content .= '- Date: ' . $contactRequest->getCreatedAt()->format('d/m/Y à H:i') . "\n";

            if ($contactRequest->getFormation()) {
                $content .= '- Formation: ' . $contactRequest->getFormation()->getTitle() . "\n";
                
                $this->logger->debug('Formation included in confirmation email', [
                    'formation_id' => $contactRequest->getFormation()->getId(),
                    'formation_title' => $contactRequest->getFormation()->getTitle(),
                ]);
            }

            $content .= "\nNous vous recontacterons dans les plus brefs délais.\n\n";
            $content .= "Cordialement,\n";
            $content .= "L'équipe EPROFOS\n";
            $content .= "École Professionnelle de Formation Spécialisée\n\n";
            $content .= "Email: contact@eprofos.com\n";
            $content .= 'Site web: https://www.eprofos.com';

            $this->logger->debug('User confirmation email content generated successfully', [
                'content_length' => strlen($content),
                'contact_request_id' => $contactRequest->getId(),
            ]);

            return $content;
        } catch (Exception $e) {
            $this->logger->error('Error generating user confirmation email content', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'contact_request_id' => $contactRequest->getId(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Send accessibility request notification email to admin.
     */
    private function sendAccessibilityNotificationEmail(ContactRequest $contactRequest): void
    {
        try {
            $this->logger->info('Starting accessibility notification email process', [
                'contact_request_id' => $contactRequest->getId(),
                'user_email' => $contactRequest->getEmail(),
                'formation_id' => $contactRequest->getFormation()?->getId(),
            ]);

            $emailContent = $this->generateAccessibilityNotificationContent($contactRequest);

            $email = (new Email())
                ->from('noreply@eprofos.com')
                ->to('handicap@eprofos.com')
                ->cc('contact@eprofos.com')
                ->subject('Nouvelle demande d\'adaptation - Accessibilité')
                ->text($emailContent)
            ;

            $this->logger->debug('Sending accessibility notification email', [
                'from' => 'noreply@eprofos.com',
                'to' => 'handicap@eprofos.com',
                'cc' => 'contact@eprofos.com',
                'subject' => 'Nouvelle demande d\'adaptation - Accessibilité',
                'contact_request_id' => $contactRequest->getId(),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Accessibility notification email sent successfully', [
                'contact_request_id' => $contactRequest->getId(),
                'recipients' => ['handicap@eprofos.com', 'contact@eprofos.com'],
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to send accessibility notification email', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'contact_request_id' => $contactRequest->getId(),
                'user_email' => $contactRequest->getEmail(),
            ]);

            // Re-throw the exception to be handled by the calling method
            throw $e;
        }
    }

    /**
     * Generate accessibility notification email content for admin.
     */
    private function generateAccessibilityNotificationContent(ContactRequest $contactRequest): string
    {
        try {
            $this->logger->debug('Generating accessibility notification email content', [
                'contact_request_id' => $contactRequest->getId(),
                'user_email' => $contactRequest->getEmail(),
                'formation_id' => $contactRequest->getFormation()?->getId(),
            ]);

            $content = "Nouvelle demande d'adaptation pour l'accessibilité\n\n";
            $content .= "Informations du demandeur:\n";
            $content .= '- Nom: ' . $contactRequest->getFirstName() . ' ' . $contactRequest->getLastName() . "\n";
            $content .= '- Email: ' . $contactRequest->getEmail() . "\n";

            if ($contactRequest->getPhone()) {
                $content .= '- Téléphone: ' . $contactRequest->getPhone() . "\n";
            }

            if ($contactRequest->getCompany()) {
                $content .= '- Entreprise: ' . $contactRequest->getCompany() . "\n";
            }

            if ($contactRequest->getFormation()) {
                $content .= '- Formation concernée: ' . $contactRequest->getFormation()->getTitle() . "\n";
                
                $this->logger->debug('Formation included in accessibility notification', [
                    'formation_id' => $contactRequest->getFormation()->getId(),
                    'formation_title' => $contactRequest->getFormation()->getTitle(),
                ]);
            }

            $content .= '- Date de la demande: ' . $contactRequest->getCreatedAt()->format('d/m/Y à H:i') . "\n\n";
            $content .= "Description des besoins d'adaptation:\n";
            $content .= $contactRequest->getMessage() . "\n\n";
            $content .= "Cette demande nécessite un traitement prioritaire par le référent handicap.\n\n";
            
            // Add admin URL if available
            if (isset($_SERVER['HTTP_HOST'])) {
                $content .= 'Accéder à la demande: ' . $_SERVER['HTTP_HOST'] . '/admin/contact-requests/' . $contactRequest->getId();
            }

            $this->logger->debug('Accessibility notification email content generated successfully', [
                'content_length' => strlen($content),
                'contact_request_id' => $contactRequest->getId(),
            ]);

            return $content;
        } catch (Exception $e) {
            $this->logger->error('Error generating accessibility notification email content', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'contact_request_id' => $contactRequest->getId(),
            ]);
            
            throw $e;
        }
    }
}
