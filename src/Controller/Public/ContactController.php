<?php

namespace App\Controller\Public;

use App\Entity\CRM\ContactRequest;
use App\Entity\Training\Formation;
use App\Repository\ContactRequestRepository;
use App\Repository\FormationRepository;
use App\Service\ProspectManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * Contact controller for handling contact forms
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
        private ProspectManagementService $prospectService
    ) {
    }

    /**
     * Display the main contact page with all form types
     */
    #[Route('', name: 'app_contact_index', methods: ['GET'])]
    public function index(): Response
    {
        // Get formations for quick registration dropdown
        $formations = $this->formationRepository->findActiveFormations();

        return $this->render('public/contact/index.html.twig', [
            'formations' => $formations,
        ]);
    }

    /**
     * Handle quote request form submission
     */
    /**
     * Display quote request form (GET)
     */
    #[Route('/devis', name: 'app_contact_quote', methods: ['GET'])]
    public function quoteForm(Request $request): Response
    {
        $this->logger->info('Quote form accessed via GET', [
            'service_id' => $request->query->get('service'),
            'referer' => $request->headers->get('referer'),
            'user_agent' => $request->headers->get('user-agent')
        ]);

        // Redirect to contact page with quote section
        return $this->redirectToRoute('app_contact_index', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Handle quote request form submission (POST)
     */
    #[Route('/devis', name: 'app_contact_quote_submit', methods: ['POST'])]
    public function quote(Request $request): Response
    {
        $contactRequest = new ContactRequest();
        $contactRequest->setType('quote');

        return $this->handleContactForm($request, $contactRequest, 'quote');
    }

    /**
     * Handle consultation request form submission
     */
    #[Route('/conseil', name: 'app_contact_consultation', methods: ['POST'])]
    public function consultation(Request $request): Response
    {
        $contactRequest = new ContactRequest();
        $contactRequest->setType('advice');

        return $this->handleContactForm($request, $contactRequest, 'consultation');
    }

    /**
     * Handle general information request form submission
     */
    #[Route('/information', name: 'app_contact_information', methods: ['POST'])]
    public function information(Request $request): Response
    {
        $contactRequest = new ContactRequest();
        $contactRequest->setType('information');

        return $this->handleContactForm($request, $contactRequest, 'information');
    }

    /**
     * Handle quick registration form submission
     */
    #[Route('/inscription-rapide', name: 'app_contact_quick_registration', methods: ['POST'])]
    public function quickRegistration(Request $request): Response
    {
        $contactRequest = new ContactRequest();
        $contactRequest->setType('quick_registration');

        return $this->handleContactForm($request, $contactRequest, 'quick_registration');
    }

    /**
     * Display quote form for a specific formation
     */
    #[Route('/devis/formation/{slug}', name: 'app_contact_formation_quote', methods: ['GET'])]
    public function formationQuote(string $slug): Response
    {
        $formation = $this->formationRepository->findBySlugWithCategory($slug);
        
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        return $this->render('public/contact/formation_quote.html.twig', [
            'formation' => $formation,
        ]);
    }

    /**
     * Handle formation-specific quote request
     */
    #[Route('/devis/formation/{slug}', name: 'app_contact_formation_quote_submit', methods: ['POST'])]
    public function formationQuoteSubmit(string $slug, Request $request): Response
    {
        $formation = $this->formationRepository->findBySlugWithCategory($slug);
        
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        $contactRequest = new ContactRequest();
        $contactRequest->setType('quote');
        $contactRequest->setFormation($formation);

        return $this->handleContactForm($request, $contactRequest, 'quote', $formation);
    }

    /**
     * Common method to handle all contact form types
     */
    private function handleContactForm(
        Request $request, 
        ContactRequest $contactRequest, 
        string $formType,
        ?Formation $formation = null
    ): Response {
        // Extract form data
        $firstName = trim($request->request->get('first_name', ''));
        $lastName = trim($request->request->get('last_name', ''));
        $email = trim($request->request->get('email', ''));
        $phone = trim($request->request->get('phone', ''));
        $company = trim($request->request->get('company', ''));
        $message = trim($request->request->get('message', ''));

        // Handle formation selection for quick registration
        if ($formType === 'quick_registration' && !$formation) {
            $formationId = $request->request->getInt('formation_id');
            if ($formationId) {
                $formation = $this->formationRepository->find($formationId);
                $contactRequest->setFormation($formation);
            }
        }

        // Populate contact request
        $contactRequest
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setPhone($phone ?: null)
            ->setCompany($company ?: null)
            ->setMessage($message);

        // Validate the contact request
        $errors = $this->validator->validate($contactRequest);

        if (count($errors) > 0) {
            // Return to form with errors
            $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire.');
            
            // Store errors in session for display
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            $this->addFlash('form_errors', $errorMessages);
            
            return $this->redirectToRoute('app_contact_index');
        }

        try {
            // Save to database
            $this->contactRequestRepository->save($contactRequest, true);

            // Create or update prospect
            try {
                $prospect = $this->prospectService->createProspectFromContactRequest($contactRequest);
                
                $this->logger->info('Prospect created/updated from contact request', [
                    'prospect_id' => $prospect->getId(),
                    'contact_request_id' => $contactRequest->getId()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to create prospect from contact request', [
                    'contact_request_id' => $contactRequest->getId(),
                    'error' => $e->getMessage()
                ]);
                // Don't fail the contact form if prospect creation fails
            }

            // Send email notification
            $this->sendEmailNotification($contactRequest);

            // Log the contact request
            $this->logger->info('Contact request submitted', [
                'type' => $contactRequest->getType(),
                'email' => $contactRequest->getEmail(),
                'formation_id' => $formation?->getId(),
            ]);

            // Success message
            $this->addFlash('success', 'Votre demande a été envoyée avec succès. Nous vous recontacterons dans les plus brefs délais.');

        } catch (\Exception $e) {
            $this->logger->error('Failed to process contact request', [
                'error' => $e->getMessage(),
                'type' => $contactRequest->getType(),
                'email' => $contactRequest->getEmail(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de votre demande. Veuillez réessayer.');
        }

        // Redirect based on context
        if ($formation) {
            return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
        }

        return $this->redirectToRoute('app_contact_index');
    }

    /**
     * Send email notification for contact request
     */
    private function sendEmailNotification(ContactRequest $contactRequest): void
    {
        try {
            // Email to EPROFOS team
            $adminEmail = (new Email())
                ->from('noreply@eprofos.fr')
                ->replyTo($contactRequest->getEmail())
                ->to('contact@eprofos.fr') // Replace with actual EPROFOS email
                ->subject('Nouvelle demande: ' . $contactRequest->getTypeLabel())
                ->text($this->generateEmailContent($contactRequest));

            $this->mailer->send($adminEmail);

            // Confirmation email to user
            $confirmationEmail = (new Email())
                ->from('contact@eprofos.fr')
                ->to($contactRequest->getEmail())
                ->subject('Confirmation de votre demande - EPROFOS')
                ->text($this->generateConfirmationEmailContent($contactRequest));

            $this->mailer->send($confirmationEmail);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send contact email', [
                'error' => $e->getMessage(),
                'contact_request_id' => $contactRequest->getId(),
            ]);
        }
    }

    /**
     * Generate email content for admin notification
     */
    private function generateEmailContent(ContactRequest $contactRequest): string
    {
        $content = "Nouvelle demande de contact reçue\n\n";
        $content .= "Type: " . $contactRequest->getTypeLabel() . "\n";
        $content .= "Nom: " . $contactRequest->getFullName() . "\n";
        $content .= "Email: " . $contactRequest->getEmail() . "\n";
        
        if ($contactRequest->getPhone()) {
            $content .= "Téléphone: " . $contactRequest->getPhone() . "\n";
        }
        
        if ($contactRequest->getCompany()) {
            $content .= "Entreprise: " . $contactRequest->getCompany() . "\n";
        }
        
        if ($contactRequest->getFormation()) {
            $content .= "Formation: " . $contactRequest->getFormation()->getTitle() . "\n";
        }
        
        $content .= "\nMessage:\n" . $contactRequest->getMessage() . "\n\n";
        $content .= "Date de la demande: " . $contactRequest->getCreatedAt()->format('d/m/Y à H:i') . "\n";
        
        return $content;
    }

    /**
     * Generate confirmation email content for user
     */
    private function generateConfirmationEmailContent(ContactRequest $contactRequest): string
    {
        $content = "Bonjour " . $contactRequest->getFirstName() . ",\n\n";
        $content .= "Nous avons bien reçu votre demande de " . strtolower($contactRequest->getTypeLabel()) . ".\n\n";
        $content .= "Récapitulatif de votre demande:\n";
        $content .= "- Type: " . $contactRequest->getTypeLabel() . "\n";
        $content .= "- Date: " . $contactRequest->getCreatedAt()->format('d/m/Y à H:i') . "\n";
        
        if ($contactRequest->getFormation()) {
            $content .= "- Formation: " . $contactRequest->getFormation()->getTitle() . "\n";
        }
        
        $content .= "\nNous vous recontacterons dans les plus brefs délais.\n\n";
        $content .= "Cordialement,\n";
        $content .= "L'équipe EPROFOS\n";
        $content .= "École Professionnelle de Formation Spécialisée\n\n";
        $content .= "Email: contact@eprofos.fr\n";
        $content .= "Site web: https://www.eprofos.fr";
        
        return $content;
    }

    /**
     * Handle accessibility request form submission
     */
    #[Route('/demande-accessibilite', name: 'app_contact_accessibility_request', methods: ['POST'])]
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

            // Validate the contact request
            $errors = $this->validator->validate($contactRequest);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->redirectToRoute('app_contact_index');
            }

            // Save the contact request
            $this->contactRequestRepository->save($contactRequest, true);

            // Send notification email to admin
            $this->sendAccessibilityNotificationEmail($contactRequest);

            // Send notification (which includes both admin and user confirmation)
            $this->sendEmailNotification($contactRequest);

            // Log the activity
            $this->logger->info('Accessibility request submitted', [
                'contact_request_id' => $contactRequest->getId(),
                'email' => $contactRequest->getEmail(),
            ]);

            $this->addFlash('success', 'Votre demande d\'adaptation a été envoyée avec succès. Nous vous contacterons rapidement.');
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing accessibility request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de votre demande. Veuillez réessayer.');
        }

        return $this->redirectToRoute('app_contact_index');
    }

    /**
     * Send accessibility request notification email to admin
     */
    private function sendAccessibilityNotificationEmail(ContactRequest $contactRequest): void
    {
        $email = (new Email())
            ->from('noreply@eprofos.fr')
            ->to('handicap@eprofos.fr')
            ->cc('contact@eprofos.fr')
            ->subject('Nouvelle demande d\'adaptation - Accessibilité')
            ->text($this->generateAccessibilityNotificationContent($contactRequest));

        $this->mailer->send($email);
    }

    /**
     * Generate accessibility notification email content for admin
     */
    private function generateAccessibilityNotificationContent(ContactRequest $contactRequest): string
    {
        $content = "Nouvelle demande d'adaptation pour l'accessibilité\n\n";
        $content .= "Informations du demandeur:\n";
        $content .= "- Nom: " . $contactRequest->getFirstName() . " " . $contactRequest->getLastName() . "\n";
        $content .= "- Email: " . $contactRequest->getEmail() . "\n";
        
        if ($contactRequest->getPhone()) {
            $content .= "- Téléphone: " . $contactRequest->getPhone() . "\n";
        }
        
        if ($contactRequest->getCompany()) {
            $content .= "- Entreprise: " . $contactRequest->getCompany() . "\n";
        }
        
        $content .= "- Date de la demande: " . $contactRequest->getCreatedAt()->format('d/m/Y à H:i') . "\n\n";
        $content .= "Description des besoins d'adaptation:\n";
        $content .= $contactRequest->getMessage() . "\n\n";
        $content .= "Cette demande nécessite un traitement prioritaire par le référent handicap.\n\n";
        $content .= "Accéder à la demande: " . $_SERVER['HTTP_HOST'] . "/admin/contact-requests/" . $contactRequest->getId();
        
        return $content;
    }
}