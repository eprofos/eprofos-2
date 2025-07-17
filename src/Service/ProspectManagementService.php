<?php

namespace App\Service;

use App\Entity\Prospect;
use App\Entity\ContactRequest;
use App\Entity\SessionRegistration;
use App\Entity\NeedsAnalysisRequest;
use App\Entity\Formation;
use App\Entity\Service;
use App\Repository\ProspectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * ProspectManagementService
 * 
 * Handles unified prospect creation and management across all customer touchpoints.
 * Ensures every contact becomes a trackable prospect in the CRM system.
 */
class ProspectManagementService
{
    public function __construct(
        private ProspectRepository $prospectRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Find existing prospect by email or create new one
     */
    public function findOrCreateProspectFromEmail(
        string $email, 
        ?string $firstName = null, 
        ?string $lastName = null
    ): Prospect {
        // Try to find existing prospect by email
        $existingProspect = $this->prospectRepository->findOneBy(['email' => $email]);
        
        if ($existingProspect) {
            $this->logger->info('Found existing prospect', [
                'prospect_id' => $existingProspect->getId(),
                'email' => $email
            ]);
            return $existingProspect;
        }

        // Create new prospect
        $prospect = new Prospect();
        $prospect
            ->setEmail($email)
            ->setFirstName($firstName ?: 'Prénom')
            ->setLastName($lastName ?: 'Nom')
            ->setStatus('lead')
            ->setPriority('medium')
            ->setSource('website');

        $this->entityManager->persist($prospect);
        
        $this->logger->info('Created new prospect from email', [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName
        ]);

        return $prospect;
    }

    /**
     * Create prospect from contact request
     */
    public function createProspectFromContactRequest(ContactRequest $contactRequest): Prospect
    {
        $prospect = $this->findOrCreateProspectFromEmail(
            $contactRequest->getEmail(),
            $contactRequest->getFirstName(),
            $contactRequest->getLastName()
        );

        // Update prospect data with contact request info
        $this->mergeContactRequestData($prospect, $contactRequest);
        
        // Set prospect source based on contact type
        $source = match($contactRequest->getType()) {
            'quote' => 'quote_request',
            'advice' => 'consultation_request',
            'information' => 'information_request',
            'quick_registration' => 'quick_registration',
            default => 'contact_form'
        };
        
        if (!$prospect->getSource() || $prospect->getSource() === 'website') {
            $prospect->setSource($source);
        }

        // Set status based on contact type (quote = higher intent)
        if ($contactRequest->getType() === 'quote' && $prospect->getStatus() === 'lead') {
            $prospect->setStatus('prospect');
        }

        // Add formation interest if specified
        if ($contactRequest->getFormation()) {
            $this->updateProspectInterests($prospect, $contactRequest->getFormation(), null);
        }

        // Link contact request to prospect
        $contactRequest->setProspect($prospect);

        $this->entityManager->flush();

        $this->logger->info('Created prospect from contact request', [
            'prospect_id' => $prospect->getId(),
            'contact_request_id' => $contactRequest->getId(),
            'type' => $contactRequest->getType()
        ]);

        return $prospect;
    }

    /**
     * Create prospect from session registration
     */
    public function createProspectFromSessionRegistration(SessionRegistration $registration): Prospect
    {
        $prospect = $this->findOrCreateProspectFromEmail(
            $registration->getEmail(),
            $registration->getFirstName(),
            $registration->getLastName()
        );

        // Update prospect data with registration info
        $this->mergeSessionRegistrationData($prospect, $registration);
        
        // Session registration indicates high intent - upgrade status
        if (in_array($prospect->getStatus(), ['lead', 'prospect'])) {
            $prospect->setStatus('qualified');
        }

        // Set source if not already set
        if (!$prospect->getSource() || $prospect->getSource() === 'website') {
            $prospect->setSource('session_registration');
        }

        // Add formation interest
        if ($registration->getSession() && $registration->getSession()->getFormation()) {
            $this->updateProspectInterests($prospect, $registration->getSession()->getFormation(), null);
        }

        // Link registration to prospect
        $registration->setProspect($prospect);

        $this->entityManager->flush();

        $this->logger->info('Created prospect from session registration', [
            'prospect_id' => $prospect->getId(),
            'registration_id' => $registration->getId(),
            'session_id' => $registration->getSession()?->getId()
        ]);

        return $prospect;
    }

    /**
     * Create prospect from needs analysis request
     */
    public function createProspectFromNeedsAnalysis(NeedsAnalysisRequest $needsAnalysis): Prospect
    {
        $prospect = $this->findOrCreateProspectFromEmail(
            $needsAnalysis->getRecipientEmail(),
            $this->extractFirstName($needsAnalysis->getRecipientName()),
            $this->extractLastName($needsAnalysis->getRecipientName())
        );

        // Update prospect data with needs analysis info
        $this->mergeNeedsAnalysisData($prospect, $needsAnalysis);
        
        // Needs analysis indicates qualified interest
        if (in_array($prospect->getStatus(), ['lead', 'prospect'])) {
            $prospect->setStatus('qualified');
        }

        // Set source
        if (!$prospect->getSource() || $prospect->getSource() === 'website') {
            $prospect->setSource('needs_analysis');
        }

        // Add formation interest if specified
        if ($needsAnalysis->getFormation()) {
            $this->updateProspectInterests($prospect, $needsAnalysis->getFormation(), null);
        }

        // Link needs analysis to prospect
        $needsAnalysis->setProspect($prospect);

        $this->entityManager->flush();

        $this->logger->info('Created prospect from needs analysis', [
            'prospect_id' => $prospect->getId(),
            'needs_analysis_id' => $needsAnalysis->getId(),
            'type' => $needsAnalysis->getType()
        ]);

        return $prospect;
    }

    /**
     * Merge contact request data into prospect
     */
    private function mergeContactRequestData(Prospect $prospect, ContactRequest $contactRequest): void
    {
        // Update basic info if prospect data is incomplete
        if (!$prospect->getPhone() && $contactRequest->getPhone()) {
            $prospect->setPhone($contactRequest->getPhone());
        }
        
        if (!$prospect->getCompany() && $contactRequest->getCompany()) {
            $prospect->setCompany($contactRequest->getCompany());
        }

        // Update last contact date
        $prospect->setLastContactDate($contactRequest->getCreatedAt());

        // Add notes about the contact request
        $description = $prospect->getDescription() ?: '';
        $newNote = sprintf(
            "[%s] %s: %s", 
            $contactRequest->getCreatedAt()->format('d/m/Y'),
            $contactRequest->getTypeLabel(),
            $contactRequest->getMessage()
        );
        
        $prospect->setDescription($description ? $description . "\n\n" . $newNote : $newNote);
    }

    /**
     * Merge session registration data into prospect
     */
    private function mergeSessionRegistrationData(Prospect $prospect, SessionRegistration $registration): void
    {
        // Update basic info if prospect data is incomplete
        if (!$prospect->getPhone() && $registration->getPhone()) {
            $prospect->setPhone($registration->getPhone());
        }
        
        if (!$prospect->getCompany() && $registration->getCompany()) {
            $prospect->setCompany($registration->getCompany());
        }

        if (!$prospect->getPosition() && $registration->getPosition()) {
            $prospect->setPosition($registration->getPosition());
        }

        // Update last contact date
        $prospect->setLastContactDate($registration->getCreatedAt());

        // Add notes about the registration
        $description = $prospect->getDescription() ?: '';
        $session = $registration->getSession();
        $newNote = sprintf(
            "[%s] Inscription session: %s - %s", 
            $registration->getCreatedAt()->format('d/m/Y'),
            $session?->getName(),
            $session?->getFormation()?->getTitle()
        );
        
        if ($registration->getSpecialRequirements()) {
            $newNote .= "\nBesoins spécifiques: " . $registration->getSpecialRequirements();
        }
        
        $prospect->setDescription($description ? $description . "\n\n" . $newNote : $newNote);
    }

    /**
     * Merge needs analysis data into prospect
     */
    private function mergeNeedsAnalysisData(Prospect $prospect, NeedsAnalysisRequest $needsAnalysis): void
    {
        // Update company info
        if (!$prospect->getCompany() && $needsAnalysis->getCompanyName()) {
            $prospect->setCompany($needsAnalysis->getCompanyName());
        }

        // Update last contact date
        $prospect->setLastContactDate($needsAnalysis->getCreatedAt());

        // Add notes about the needs analysis
        $description = $prospect->getDescription() ?: '';
        $newNote = sprintf(
            "[%s] Analyse de besoins (%s) envoyée", 
            $needsAnalysis->getCreatedAt()->format('d/m/Y'),
            $needsAnalysis->getTypeLabel()
        );
        
        if ($needsAnalysis->getAdminNotes()) {
            $newNote .= "\nNotes admin: " . $needsAnalysis->getAdminNotes();
        }
        
        $prospect->setDescription($description ? $description . "\n\n" . $newNote : $newNote);
    }

    /**
     * Update prospect formation/service interests
     */
    public function updateProspectInterests(
        Prospect $prospect, 
        ?Formation $formation = null, 
        ?Service $service = null
    ): void {
        if ($formation) {
            // Get a fresh reference from the database to ensure it's managed by the current EntityManager
            $formationId = $formation->getId();
            $managedFormation = $this->entityManager->getRepository(Formation::class)->find($formationId);
            
            if ($managedFormation && !$prospect->getInterestedFormations()->contains($managedFormation)) {
                $prospect->addInterestedFormation($managedFormation);
                
                $this->logger->info('Added formation interest to prospect', [
                    'prospect_id' => $prospect->getId(),
                    'formation_id' => $managedFormation->getId(),
                    'formation_title' => $managedFormation->getTitle()
                ]);
            }
        }

        if ($service) {
            // Get a fresh reference from the database to ensure it's managed by the current EntityManager
            $serviceId = $service->getId();
            $managedService = $this->entityManager->getRepository(Service::class)->find($serviceId);
            
            if ($managedService && !$prospect->getInterestedServices()->contains($managedService)) {
                $prospect->addInterestedService($managedService);
                
                $this->logger->info('Added service interest to prospect', [
                    'prospect_id' => $prospect->getId(),
                    'service_id' => $managedService->getId(),
                    'service_title' => $managedService->getTitle()
                ]);
            }
        }
    }

    /**
     * Extract first name from full name
     */
    private function extractFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? 'Prénom';
    }

    /**
     * Extract last name from full name
     */
    private function extractLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        if (count($parts) > 1) {
            array_shift($parts); // Remove first name
            return implode(' ', $parts);
        }
        return 'Nom';
    }

    /**
     * Find and merge duplicate prospects by email
     */
    public function mergeDuplicateProspects(): int
    {
        $mergedCount = 0;
        
        // Find prospects with duplicate emails
        $duplicates = $this->prospectRepository->createQueryBuilder('p')
            ->select('p.email', 'COUNT(p.id) as count')
            ->groupBy('p.email')
            ->having('COUNT(p.id) > 1')
            ->getQuery()
            ->getResult();

        foreach ($duplicates as $duplicate) {
            $prospects = $this->prospectRepository->findBy(['email' => $duplicate['email']]);
            
            if (count($prospects) > 1) {
                $primaryProspect = $prospects[0]; // Keep the first one (oldest)
                
                for ($i = 1; $i < count($prospects); $i++) {
                    $this->mergeProspects($primaryProspect, $prospects[$i]);
                    $mergedCount++;
                }
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Merged duplicate prospects', [
            'merged_count' => $mergedCount
        ]);

        return $mergedCount;
    }

    /**
     * Merge two prospects (move all data from source to target, then delete source)
     */
    private function mergeProspects(Prospect $target, Prospect $source): void
    {
        // Merge basic information (keep target data, fill gaps with source)
        if (!$target->getPhone() && $source->getPhone()) {
            $target->setPhone($source->getPhone());
        }
        if (!$target->getCompany() && $source->getCompany()) {
            $target->setCompany($source->getCompany());
        }
        if (!$target->getPosition() && $source->getPosition()) {
            $target->setPosition($source->getPosition());
        }

        // Merge descriptions
        if ($source->getDescription()) {
            $targetDescription = $target->getDescription() ?: '';
            $target->setDescription($targetDescription . "\n\n--- Fusionné ---\n" . $source->getDescription());
        }

        // Move all related entities to target prospect
        foreach ($source->getSessionRegistrations() as $registration) {
            $registration->setProspect($target);
        }
        
        foreach ($source->getContactRequests() as $contact) {
            $contact->setProspect($target);
        }
        
        foreach ($source->getNeedsAnalysisRequests() as $analysis) {
            $analysis->setProspect($target);
        }

        // Merge interests
        foreach ($source->getInterestedFormations() as $formation) {
            if (!$target->getInterestedFormations()->contains($formation)) {
                $target->addInterestedFormation($formation);
            }
        }
        
        foreach ($source->getInterestedServices() as $service) {
            if (!$target->getInterestedServices()->contains($service)) {
                $target->addInterestedService($service);
            }
        }

        // Update dates - keep most recent
        if ($source->getLastContactDate() && 
            (!$target->getLastContactDate() || $source->getLastContactDate() > $target->getLastContactDate())) {
            $target->setLastContactDate($source->getLastContactDate());
        }

        if ($source->getNextFollowUpDate() && 
            (!$target->getNextFollowUpDate() || $source->getNextFollowUpDate() < $target->getNextFollowUpDate())) {
            $target->setNextFollowUpDate($source->getNextFollowUpDate());
        }

        // Upgrade status if source has higher status
        $statusPriority = ['lead' => 1, 'prospect' => 2, 'qualified' => 3, 'negotiation' => 4, 'customer' => 5];
        $targetPriority = $statusPriority[$target->getStatus()] ?? 0;
        $sourcePriority = $statusPriority[$source->getStatus()] ?? 0;
        
        if ($sourcePriority > $targetPriority) {
            $target->setStatus($source->getStatus());
        }

        // Remove source prospect
        $this->entityManager->remove($source);

        $this->logger->info('Merged prospects', [
            'target_id' => $target->getId(),
            'source_id' => $source->getId(),
            'email' => $target->getEmail()
        ]);
    }
}
