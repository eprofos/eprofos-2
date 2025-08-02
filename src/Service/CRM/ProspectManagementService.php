<?php

declare(strict_types=1);

namespace App\Service\CRM;

use App\Entity\Analysis\NeedsAnalysisRequest;
use App\Entity\CRM\ContactRequest;
use App\Entity\CRM\Prospect;
use App\Entity\Service\Service;
use App\Entity\Training\Formation;
use App\Entity\Training\SessionRegistration;
use App\Repository\CRM\ProspectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * ProspectManagementService.
 *
 * Handles unified prospect creation and management across all customer touchpoints.
 * Ensures every contact becomes a trackable prospect in the CRM system.
 */
class ProspectManagementService
{
    public function __construct(
        private ProspectRepository $prospectRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Find existing prospect by email or create new one.
     */
    public function findOrCreateProspectFromEmail(
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
    ): Prospect {
        $this->logger->info('Starting findOrCreateProspectFromEmail process', [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'method' => __METHOD__,
        ]);

        try {
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->error('Invalid email format provided', [
                    'email' => $email,
                    'method' => __METHOD__,
                ]);
                throw new \InvalidArgumentException('Invalid email format: ' . $email);
            }

            $this->logger->debug('Searching for existing prospect by email', [
                'email' => $email,
                'method' => __METHOD__,
            ]);

            // Try to find existing prospect by email
            $existingProspect = $this->prospectRepository->findOneBy(['email' => $email]);

            if ($existingProspect) {
                $this->logger->info('Found existing prospect', [
                    'prospect_id' => $existingProspect->getId(),
                    'email' => $email,
                    'status' => $existingProspect->getStatus(),
                    'created_at' => $existingProspect->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'method' => __METHOD__,
                ]);

                return $existingProspect;
            }

            $this->logger->debug('No existing prospect found, creating new one', [
                'email' => $email,
                'method' => __METHOD__,
            ]);

            // Create new prospect
            $prospect = new Prospect();
            $prospect
                ->setEmail($email)
                ->setFirstName($firstName ?: 'Prénom')
                ->setLastName($lastName ?: 'Nom')
                ->setStatus('lead')
                ->setPriority('medium')
                ->setSource('website')
            ;

            $this->logger->debug('Persisting new prospect to database', [
                'email' => $email,
                'first_name' => $prospect->getFirstName(),
                'last_name' => $prospect->getLastName(),
                'status' => $prospect->getStatus(),
                'priority' => $prospect->getPriority(),
                'source' => $prospect->getSource(),
                'method' => __METHOD__,
            ]);

            $this->entityManager->persist($prospect);

            $this->logger->info('Successfully created new prospect from email', [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'prospect_status' => $prospect->getStatus(),
                'prospect_priority' => $prospect->getPriority(),
                'prospect_source' => $prospect->getSource(),
                'method' => __METHOD__,
            ]);

            return $prospect;

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in findOrCreateProspectFromEmail', [
                'email' => $email,
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in findOrCreateProspectFromEmail', [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to find or create prospect: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create prospect from contact request.
     */
    public function createProspectFromContactRequest(ContactRequest $contactRequest): Prospect
    {
        $this->logger->info('Starting createProspectFromContactRequest process', [
            'contact_request_id' => $contactRequest->getId(),
            'email' => $contactRequest->getEmail(),
            'type' => $contactRequest->getType(),
            'formation_id' => $contactRequest->getFormation()?->getId(),
            'method' => __METHOD__,
        ]);

        try {
            // Validate contact request
            if (!$contactRequest->getEmail()) {
                $this->logger->error('Contact request missing email', [
                    'contact_request_id' => $contactRequest->getId(),
                    'method' => __METHOD__,
                ]);
                throw new \InvalidArgumentException('Contact request must have an email address');
            }

            $this->logger->debug('Finding or creating prospect from contact request', [
                'contact_request_id' => $contactRequest->getId(),
                'email' => $contactRequest->getEmail(),
                'first_name' => $contactRequest->getFirstName(),
                'last_name' => $contactRequest->getLastName(),
                'method' => __METHOD__,
            ]);

            $prospect = $this->findOrCreateProspectFromEmail(
                $contactRequest->getEmail(),
                $contactRequest->getFirstName(),
                $contactRequest->getLastName(),
            );

            $this->logger->debug('Merging contact request data into prospect', [
                'prospect_id' => $prospect->getId(),
                'contact_request_id' => $contactRequest->getId(),
                'method' => __METHOD__,
            ]);

            // Update prospect data with contact request info
            $this->mergeContactRequestData($prospect, $contactRequest);

            // Set prospect source based on contact type
            $source = match ($contactRequest->getType()) {
                'quote' => 'quote_request',
                'advice' => 'consultation_request',
                'information' => 'information_request',
                'quick_registration' => 'quick_registration',
                default => 'contact_form'
            };

            $originalSource = $prospect->getSource();
            if (!$prospect->getSource() || $prospect->getSource() === 'website') {
                $prospect->setSource($source);
                $this->logger->debug('Updated prospect source', [
                    'prospect_id' => $prospect->getId(),
                    'original_source' => $originalSource,
                    'new_source' => $source,
                    'contact_type' => $contactRequest->getType(),
                    'method' => __METHOD__,
                ]);
            }

            // Set status based on contact type (quote = higher intent)
            $originalStatus = $prospect->getStatus();
            if ($contactRequest->getType() === 'quote' && $prospect->getStatus() === 'lead') {
                $prospect->setStatus('prospect');
                $this->logger->debug('Upgraded prospect status based on quote request', [
                    'prospect_id' => $prospect->getId(),
                    'original_status' => $originalStatus,
                    'new_status' => 'prospect',
                    'contact_type' => $contactRequest->getType(),
                    'method' => __METHOD__,
                ]);
            }

            // Add formation interest if specified
            if ($contactRequest->getFormation()) {
                $this->logger->debug('Adding formation interest to prospect', [
                    'prospect_id' => $prospect->getId(),
                    'formation_id' => $contactRequest->getFormation()->getId(),
                    'formation_title' => $contactRequest->getFormation()->getTitle(),
                    'method' => __METHOD__,
                ]);

                $this->updateProspectInterests($prospect, $contactRequest->getFormation(), null);
            }

            $this->logger->debug('Linking contact request to prospect', [
                'prospect_id' => $prospect->getId(),
                'contact_request_id' => $contactRequest->getId(),
                'method' => __METHOD__,
            ]);

            // Link contact request to prospect
            $contactRequest->setProspect($prospect);

            $this->logger->debug('Flushing changes to database', [
                'prospect_id' => $prospect->getId(),
                'contact_request_id' => $contactRequest->getId(),
                'method' => __METHOD__,
            ]);

            $this->entityManager->flush();

            $this->logger->info('Successfully created prospect from contact request', [
                'prospect_id' => $prospect->getId(),
                'contact_request_id' => $contactRequest->getId(),
                'type' => $contactRequest->getType(),
                'prospect_status' => $prospect->getStatus(),
                'prospect_source' => $prospect->getSource(),
                'formation_id' => $contactRequest->getFormation()?->getId(),
                'method' => __METHOD__,
            ]);

            return $prospect;

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in createProspectFromContactRequest', [
                'contact_request_id' => $contactRequest->getId(),
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in createProspectFromContactRequest', [
                'contact_request_id' => $contactRequest->getId(),
                'email' => $contactRequest->getEmail(),
                'type' => $contactRequest->getType(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to create prospect from contact request: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create prospect from session registration.
     */
    public function createProspectFromSessionRegistration(SessionRegistration $registration): Prospect
    {
        $this->logger->info('Starting createProspectFromSessionRegistration process', [
            'registration_id' => $registration->getId(),
            'email' => $registration->getEmail(),
            'session_id' => $registration->getSession()?->getId(),
            'formation_id' => $registration->getSession()?->getFormation()?->getId(),
            'method' => __METHOD__,
        ]);

        try {
            // Validate session registration
            if (!$registration->getEmail()) {
                $this->logger->error('Session registration missing email', [
                    'registration_id' => $registration->getId(),
                    'method' => __METHOD__,
                ]);
                throw new \InvalidArgumentException('Session registration must have an email address');
            }

            if (!$registration->getSession()) {
                $this->logger->warning('Session registration missing session reference', [
                    'registration_id' => $registration->getId(),
                    'email' => $registration->getEmail(),
                    'method' => __METHOD__,
                ]);
            }

            $this->logger->debug('Finding or creating prospect from session registration', [
                'registration_id' => $registration->getId(),
                'email' => $registration->getEmail(),
                'first_name' => $registration->getFirstName(),
                'last_name' => $registration->getLastName(),
                'method' => __METHOD__,
            ]);

            $prospect = $this->findOrCreateProspectFromEmail(
                $registration->getEmail(),
                $registration->getFirstName(),
                $registration->getLastName(),
            );

            $this->logger->debug('Merging session registration data into prospect', [
                'prospect_id' => $prospect->getId(),
                'registration_id' => $registration->getId(),
                'method' => __METHOD__,
            ]);

            // Update prospect data with registration info
            $this->mergeSessionRegistrationData($prospect, $registration);

            // Session registration indicates high intent - upgrade status
            $originalStatus = $prospect->getStatus();
            if (in_array($prospect->getStatus(), ['lead', 'prospect'], true)) {
                $prospect->setStatus('qualified');
                $this->logger->debug('Upgraded prospect status based on session registration', [
                    'prospect_id' => $prospect->getId(),
                    'original_status' => $originalStatus,
                    'new_status' => 'qualified',
                    'registration_id' => $registration->getId(),
                    'method' => __METHOD__,
                ]);
            }

            // Set source if not already set
            $originalSource = $prospect->getSource();
            if (!$prospect->getSource() || $prospect->getSource() === 'website') {
                $prospect->setSource('session_registration');
                $this->logger->debug('Updated prospect source', [
                    'prospect_id' => $prospect->getId(),
                    'original_source' => $originalSource,
                    'new_source' => 'session_registration',
                    'method' => __METHOD__,
                ]);
            }

            // Add formation interest
            if ($registration->getSession() && $registration->getSession()->getFormation()) {
                $formation = $registration->getSession()->getFormation();
                $this->logger->debug('Adding formation interest to prospect', [
                    'prospect_id' => $prospect->getId(),
                    'formation_id' => $formation->getId(),
                    'formation_title' => $formation->getTitle(),
                    'session_id' => $registration->getSession()->getId(),
                    'method' => __METHOD__,
                ]);

                $this->updateProspectInterests($prospect, $formation, null);
            }

            $this->logger->debug('Linking session registration to prospect', [
                'prospect_id' => $prospect->getId(),
                'registration_id' => $registration->getId(),
                'method' => __METHOD__,
            ]);

            // Link registration to prospect
            $registration->setProspect($prospect);

            $this->logger->debug('Flushing changes to database', [
                'prospect_id' => $prospect->getId(),
                'registration_id' => $registration->getId(),
                'method' => __METHOD__,
            ]);

            $this->entityManager->flush();

            $this->logger->info('Successfully created prospect from session registration', [
                'prospect_id' => $prospect->getId(),
                'registration_id' => $registration->getId(),
                'session_id' => $registration->getSession()?->getId(),
                'prospect_status' => $prospect->getStatus(),
                'prospect_source' => $prospect->getSource(),
                'formation_id' => $registration->getSession()?->getFormation()?->getId(),
                'method' => __METHOD__,
            ]);

            return $prospect;

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in createProspectFromSessionRegistration', [
                'registration_id' => $registration->getId(),
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in createProspectFromSessionRegistration', [
                'registration_id' => $registration->getId(),
                'email' => $registration->getEmail(),
                'session_id' => $registration->getSession()?->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to create prospect from session registration: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create prospect from needs analysis request.
     */
    public function createProspectFromNeedsAnalysis(NeedsAnalysisRequest $needsAnalysis): Prospect
    {
        $this->logger->info('Starting createProspectFromNeedsAnalysis process', [
            'needs_analysis_id' => $needsAnalysis->getId(),
            'recipient_email' => $needsAnalysis->getRecipientEmail(),
            'type' => $needsAnalysis->getType(),
            'formation_id' => $needsAnalysis->getFormation()?->getId(),
            'company_name' => $needsAnalysis->getCompanyName(),
            'method' => __METHOD__,
        ]);

        try {
            // Validate needs analysis request
            if (!$needsAnalysis->getRecipientEmail()) {
                $this->logger->error('Needs analysis request missing recipient email', [
                    'needs_analysis_id' => $needsAnalysis->getId(),
                    'method' => __METHOD__,
                ]);
                throw new \InvalidArgumentException('Needs analysis request must have a recipient email address');
            }

            if (!$needsAnalysis->getRecipientName()) {
                $this->logger->warning('Needs analysis request missing recipient name', [
                    'needs_analysis_id' => $needsAnalysis->getId(),
                    'recipient_email' => $needsAnalysis->getRecipientEmail(),
                    'method' => __METHOD__,
                ]);
            }

            $this->logger->debug('Extracting names from recipient name', [
                'needs_analysis_id' => $needsAnalysis->getId(),
                'recipient_name' => $needsAnalysis->getRecipientName(),
                'method' => __METHOD__,
            ]);

            $firstName = $this->extractFirstName($needsAnalysis->getRecipientName());
            $lastName = $this->extractLastName($needsAnalysis->getRecipientName());

            $this->logger->debug('Finding or creating prospect from needs analysis', [
                'needs_analysis_id' => $needsAnalysis->getId(),
                'recipient_email' => $needsAnalysis->getRecipientEmail(),
                'extracted_first_name' => $firstName,
                'extracted_last_name' => $lastName,
                'method' => __METHOD__,
            ]);

            $prospect = $this->findOrCreateProspectFromEmail(
                $needsAnalysis->getRecipientEmail(),
                $firstName,
                $lastName,
            );

            $this->logger->debug('Merging needs analysis data into prospect', [
                'prospect_id' => $prospect->getId(),
                'needs_analysis_id' => $needsAnalysis->getId(),
                'method' => __METHOD__,
            ]);

            // Update prospect data with needs analysis info
            $this->mergeNeedsAnalysisData($prospect, $needsAnalysis);

            // Needs analysis indicates qualified interest
            $originalStatus = $prospect->getStatus();
            if (in_array($prospect->getStatus(), ['lead', 'prospect'], true)) {
                $prospect->setStatus('qualified');
                $this->logger->debug('Upgraded prospect status based on needs analysis', [
                    'prospect_id' => $prospect->getId(),
                    'original_status' => $originalStatus,
                    'new_status' => 'qualified',
                    'needs_analysis_type' => $needsAnalysis->getType(),
                    'method' => __METHOD__,
                ]);
            }

            // Set source
            $originalSource = $prospect->getSource();
            if (!$prospect->getSource() || $prospect->getSource() === 'website') {
                $prospect->setSource('needs_analysis');
                $this->logger->debug('Updated prospect source', [
                    'prospect_id' => $prospect->getId(),
                    'original_source' => $originalSource,
                    'new_source' => 'needs_analysis',
                    'method' => __METHOD__,
                ]);
            }

            // Add formation interest if specified
            if ($needsAnalysis->getFormation()) {
                $formation = $needsAnalysis->getFormation();
                $this->logger->debug('Adding formation interest to prospect', [
                    'prospect_id' => $prospect->getId(),
                    'formation_id' => $formation->getId(),
                    'formation_title' => $formation->getTitle(),
                    'needs_analysis_id' => $needsAnalysis->getId(),
                    'method' => __METHOD__,
                ]);

                $this->updateProspectInterests($prospect, $formation, null);
            }

            $this->logger->debug('Linking needs analysis to prospect', [
                'prospect_id' => $prospect->getId(),
                'needs_analysis_id' => $needsAnalysis->getId(),
                'method' => __METHOD__,
            ]);

            // Link needs analysis to prospect
            $needsAnalysis->setProspect($prospect);

            $this->logger->debug('Flushing changes to database', [
                'prospect_id' => $prospect->getId(),
                'needs_analysis_id' => $needsAnalysis->getId(),
                'method' => __METHOD__,
            ]);

            $this->entityManager->flush();

            $this->logger->info('Successfully created prospect from needs analysis', [
                'prospect_id' => $prospect->getId(),
                'needs_analysis_id' => $needsAnalysis->getId(),
                'type' => $needsAnalysis->getType(),
                'prospect_status' => $prospect->getStatus(),
                'prospect_source' => $prospect->getSource(),
                'formation_id' => $needsAnalysis->getFormation()?->getId(),
                'company_name' => $needsAnalysis->getCompanyName(),
                'method' => __METHOD__,
            ]);

            return $prospect;

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in createProspectFromNeedsAnalysis', [
                'needs_analysis_id' => $needsAnalysis->getId(),
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in createProspectFromNeedsAnalysis', [
                'needs_analysis_id' => $needsAnalysis->getId(),
                'recipient_email' => $needsAnalysis->getRecipientEmail(),
                'type' => $needsAnalysis->getType(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to create prospect from needs analysis: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update prospect formation/service interests.
     */
    public function updateProspectInterests(
        Prospect $prospect,
        ?Formation $formation = null,
        ?Service $service = null,
    ): void {
        $this->logger->info('Starting updateProspectInterests process', [
            'prospect_id' => $prospect->getId(),
            'formation_id' => $formation?->getId(),
            'service_id' => $service?->getId(),
            'method' => __METHOD__,
        ]);

        try {
            if ($formation) {
                $this->logger->debug('Processing formation interest', [
                    'prospect_id' => $prospect->getId(),
                    'formation_id' => $formation->getId(),
                    'formation_title' => $formation->getTitle(),
                    'method' => __METHOD__,
                ]);

                // Get a fresh reference from the database to ensure it's managed by the current EntityManager
                $formationId = $formation->getId();
                if (!$formationId) {
                    $this->logger->error('Formation without ID provided', [
                        'prospect_id' => $prospect->getId(),
                        'formation_title' => $formation->getTitle(),
                        'method' => __METHOD__,
                    ]);
                    throw new \InvalidArgumentException('Formation must have an ID');
                }

                $managedFormation = $this->entityManager->getRepository(Formation::class)->find($formationId);

                if (!$managedFormation) {
                    $this->logger->error('Formation not found in database', [
                        'prospect_id' => $prospect->getId(),
                        'formation_id' => $formationId,
                        'method' => __METHOD__,
                    ]);
                    throw new \InvalidArgumentException('Formation not found: ' . $formationId);
                }

                if (!$prospect->getInterestedFormations()->contains($managedFormation)) {
                    $prospect->addInterestedFormation($managedFormation);

                    $this->logger->info('Added formation interest to prospect', [
                        'prospect_id' => $prospect->getId(),
                        'formation_id' => $managedFormation->getId(),
                        'formation_title' => $managedFormation->getTitle(),
                        'total_formations' => $prospect->getInterestedFormations()->count(),
                        'method' => __METHOD__,
                    ]);
                } else {
                    $this->logger->debug('Formation interest already exists for prospect', [
                        'prospect_id' => $prospect->getId(),
                        'formation_id' => $managedFormation->getId(),
                        'formation_title' => $managedFormation->getTitle(),
                        'method' => __METHOD__,
                    ]);
                }
            }

            if ($service) {
                $this->logger->debug('Processing service interest', [
                    'prospect_id' => $prospect->getId(),
                    'service_id' => $service->getId(),
                    'service_title' => $service->getTitle(),
                    'method' => __METHOD__,
                ]);

                // Get a fresh reference from the database to ensure it's managed by the current EntityManager
                $serviceId = $service->getId();
                if (!$serviceId) {
                    $this->logger->error('Service without ID provided', [
                        'prospect_id' => $prospect->getId(),
                        'service_title' => $service->getTitle(),
                        'method' => __METHOD__,
                    ]);
                    throw new \InvalidArgumentException('Service must have an ID');
                }

                $managedService = $this->entityManager->getRepository(Service::class)->find($serviceId);

                if (!$managedService) {
                    $this->logger->error('Service not found in database', [
                        'prospect_id' => $prospect->getId(),
                        'service_id' => $serviceId,
                        'method' => __METHOD__,
                    ]);
                    throw new \InvalidArgumentException('Service not found: ' . $serviceId);
                }

                if (!$prospect->getInterestedServices()->contains($managedService)) {
                    $prospect->addInterestedService($managedService);

                    $this->logger->info('Added service interest to prospect', [
                        'prospect_id' => $prospect->getId(),
                        'service_id' => $managedService->getId(),
                        'service_title' => $managedService->getTitle(),
                        'total_services' => $prospect->getInterestedServices()->count(),
                        'method' => __METHOD__,
                    ]);
                } else {
                    $this->logger->debug('Service interest already exists for prospect', [
                        'prospect_id' => $prospect->getId(),
                        'service_id' => $managedService->getId(),
                        'service_title' => $managedService->getTitle(),
                        'method' => __METHOD__,
                    ]);
                }
            }

            if (!$formation && !$service) {
                $this->logger->warning('No formation or service provided to updateProspectInterests', [
                    'prospect_id' => $prospect->getId(),
                    'method' => __METHOD__,
                ]);
            }

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in updateProspectInterests', [
                'prospect_id' => $prospect->getId(),
                'formation_id' => $formation?->getId(),
                'service_id' => $service?->getId(),
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in updateProspectInterests', [
                'prospect_id' => $prospect->getId(),
                'formation_id' => $formation?->getId(),
                'service_id' => $service?->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to update prospect interests: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Find and merge duplicate prospects by email.
     */
    public function mergeDuplicateProspects(): int
    {
        $this->logger->info('Starting mergeDuplicateProspects process', [
            'method' => __METHOD__,
        ]);

        $mergedCount = 0;

        try {
            $this->logger->debug('Searching for prospects with duplicate emails', [
                'method' => __METHOD__,
            ]);

            // Find prospects with duplicate emails
            $duplicates = $this->prospectRepository->createQueryBuilder('p')
                ->select('p.email', 'COUNT(p.id) as count')
                ->groupBy('p.email')
                ->having('COUNT(p.id) > 1')
                ->getQuery()
                ->getResult()
            ;

            $totalDuplicateGroups = count($duplicates);
            $this->logger->info('Found duplicate email groups', [
                'duplicate_groups_count' => $totalDuplicateGroups,
                'method' => __METHOD__,
            ]);

            foreach ($duplicates as $index => $duplicate) {
                $this->logger->debug('Processing duplicate group', [
                    'group_index' => $index + 1,
                    'total_groups' => $totalDuplicateGroups,
                    'email' => $duplicate['email'],
                    'prospect_count' => $duplicate['count'],
                    'method' => __METHOD__,
                ]);

                try {
                    $prospects = $this->prospectRepository->findBy(['email' => $duplicate['email']]);

                    if (count($prospects) > 1) {
                        $primaryProspect = $prospects[0]; // Keep the first one (oldest)
                        
                        $this->logger->debug('Selected primary prospect for merge', [
                            'primary_prospect_id' => $primaryProspect->getId(),
                            'email' => $duplicate['email'],
                            'total_prospects' => count($prospects),
                            'method' => __METHOD__,
                        ]);

                        for ($i = 1; $i < count($prospects); $i++) {
                            $secondaryProspect = $prospects[$i];
                            
                            $this->logger->debug('Merging prospect into primary', [
                                'primary_prospect_id' => $primaryProspect->getId(),
                                'secondary_prospect_id' => $secondaryProspect->getId(),
                                'email' => $duplicate['email'],
                                'method' => __METHOD__,
                            ]);

                            $this->mergeProspects($primaryProspect, $secondaryProspect);
                            $mergedCount++;

                            $this->logger->info('Successfully merged prospect', [
                                'primary_prospect_id' => $primaryProspect->getId(),
                                'merged_prospect_id' => $secondaryProspect->getId(),
                                'email' => $duplicate['email'],
                                'total_merged_so_far' => $mergedCount,
                                'method' => __METHOD__,
                            ]);
                        }
                    }

                } catch (\Exception $e) {
                    $this->logger->error('Error processing duplicate group', [
                        'email' => $duplicate['email'],
                        'prospect_count' => $duplicate['count'],
                        'error_message' => $e->getMessage(),
                        'method' => __METHOD__,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue with next group instead of failing completely
                    continue;
                }
            }

            $this->logger->debug('Flushing all merge changes to database', [
                'merged_count' => $mergedCount,
                'method' => __METHOD__,
            ]);

            $this->entityManager->flush();

            $this->logger->info('Successfully completed mergeDuplicateProspects process', [
                'merged_count' => $mergedCount,
                'duplicate_groups_processed' => $totalDuplicateGroups,
                'method' => __METHOD__,
            ]);

            return $mergedCount;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in mergeDuplicateProspects', [
                'merged_count' => $mergedCount,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to merge duplicate prospects: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Merge contact request data into prospect.
     */
    private function mergeContactRequestData(Prospect $prospect, ContactRequest $contactRequest): void
    {
        $this->logger->debug('Starting mergeContactRequestData', [
            'prospect_id' => $prospect->getId(),
            'contact_request_id' => $contactRequest->getId(),
            'method' => __METHOD__,
        ]);

        try {
            // Update basic info if prospect data is incomplete
            if (!$prospect->getPhone() && $contactRequest->getPhone()) {
                $prospect->setPhone($contactRequest->getPhone());
                $this->logger->debug('Updated prospect phone from contact request', [
                    'prospect_id' => $prospect->getId(),
                    'phone' => $contactRequest->getPhone(),
                    'method' => __METHOD__,
                ]);
            }

            if (!$prospect->getCompany() && $contactRequest->getCompany()) {
                $prospect->setCompany($contactRequest->getCompany());
                $this->logger->debug('Updated prospect company from contact request', [
                    'prospect_id' => $prospect->getId(),
                    'company' => $contactRequest->getCompany(),
                    'method' => __METHOD__,
                ]);
            }

            // Update last contact date
            $originalContactDate = $prospect->getLastContactDate();
            $prospect->setLastContactDate($contactRequest->getCreatedAt());
            $this->logger->debug('Updated prospect last contact date', [
                'prospect_id' => $prospect->getId(),
                'original_date' => $originalContactDate?->format('Y-m-d H:i:s'),
                'new_date' => $contactRequest->getCreatedAt()->format('Y-m-d H:i:s'),
                'method' => __METHOD__,
            ]);

            // Add notes about the contact request
            $description = $prospect->getDescription() ?: '';
            $newNote = sprintf(
                '[%s] %s: %s',
                $contactRequest->getCreatedAt()->format('d/m/Y'),
                $contactRequest->getTypeLabel(),
                $contactRequest->getMessage(),
            );

            $prospect->setDescription($description ? $description . "\n\n" . $newNote : $newNote);
            
            $this->logger->debug('Added contact request note to prospect description', [
                'prospect_id' => $prospect->getId(),
                'contact_request_id' => $contactRequest->getId(),
                'note_preview' => substr($newNote, 0, 100) . '...',
                'method' => __METHOD__,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in mergeContactRequestData', [
                'prospect_id' => $prospect->getId(),
                'contact_request_id' => $contactRequest->getId(),
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to merge contact request data: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Merge session registration data into prospect.
     */
    private function mergeSessionRegistrationData(Prospect $prospect, SessionRegistration $registration): void
    {
        $this->logger->debug('Starting mergeSessionRegistrationData', [
            'prospect_id' => $prospect->getId(),
            'registration_id' => $registration->getId(),
            'method' => __METHOD__,
        ]);

        try {
            // Update basic info if prospect data is incomplete
            if (!$prospect->getPhone() && $registration->getPhone()) {
                $prospect->setPhone($registration->getPhone());
                $this->logger->debug('Updated prospect phone from session registration', [
                    'prospect_id' => $prospect->getId(),
                    'phone' => $registration->getPhone(),
                    'method' => __METHOD__,
                ]);
            }

            if (!$prospect->getCompany() && $registration->getCompany()) {
                $prospect->setCompany($registration->getCompany());
                $this->logger->debug('Updated prospect company from session registration', [
                    'prospect_id' => $prospect->getId(),
                    'company' => $registration->getCompany(),
                    'method' => __METHOD__,
                ]);
            }

            if (!$prospect->getPosition() && $registration->getPosition()) {
                $prospect->setPosition($registration->getPosition());
                $this->logger->debug('Updated prospect position from session registration', [
                    'prospect_id' => $prospect->getId(),
                    'position' => $registration->getPosition(),
                    'method' => __METHOD__,
                ]);
            }

            // Update last contact date
            $originalContactDate = $prospect->getLastContactDate();
            $prospect->setLastContactDate($registration->getCreatedAt());
            $this->logger->debug('Updated prospect last contact date', [
                'prospect_id' => $prospect->getId(),
                'original_date' => $originalContactDate?->format('Y-m-d H:i:s'),
                'new_date' => $registration->getCreatedAt()->format('Y-m-d H:i:s'),
                'method' => __METHOD__,
            ]);

            // Add notes about the registration
            $description = $prospect->getDescription() ?: '';
            $session = $registration->getSession();
            $newNote = sprintf(
                '[%s] Inscription session: %s - %s',
                $registration->getCreatedAt()->format('d/m/Y'),
                $session?->getName(),
                $session?->getFormation()?->getTitle(),
            );

            if ($registration->getSpecialRequirements()) {
                $newNote .= "\nBesoins spécifiques: " . $registration->getSpecialRequirements();
                $this->logger->debug('Added special requirements to registration note', [
                    'prospect_id' => $prospect->getId(),
                    'registration_id' => $registration->getId(),
                    'special_requirements_length' => strlen($registration->getSpecialRequirements()),
                    'method' => __METHOD__,
                ]);
            }

            $prospect->setDescription($description ? $description . "\n\n" . $newNote : $newNote);
            
            $this->logger->debug('Added session registration note to prospect description', [
                'prospect_id' => $prospect->getId(),
                'registration_id' => $registration->getId(),
                'session_id' => $session?->getId(),
                'formation_id' => $session?->getFormation()?->getId(),
                'note_preview' => substr($newNote, 0, 100) . '...',
                'method' => __METHOD__,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in mergeSessionRegistrationData', [
                'prospect_id' => $prospect->getId(),
                'registration_id' => $registration->getId(),
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to merge session registration data: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Merge needs analysis data into prospect.
     */
    private function mergeNeedsAnalysisData(Prospect $prospect, NeedsAnalysisRequest $needsAnalysis): void
    {
        $this->logger->debug('Starting mergeNeedsAnalysisData', [
            'prospect_id' => $prospect->getId(),
            'needs_analysis_id' => $needsAnalysis->getId(),
            'method' => __METHOD__,
        ]);

        try {
            // Update company info
            if (!$prospect->getCompany() && $needsAnalysis->getCompanyName()) {
                $prospect->setCompany($needsAnalysis->getCompanyName());
                $this->logger->debug('Updated prospect company from needs analysis', [
                    'prospect_id' => $prospect->getId(),
                    'company' => $needsAnalysis->getCompanyName(),
                    'method' => __METHOD__,
                ]);
            }

            // Update last contact date
            $originalContactDate = $prospect->getLastContactDate();
            $prospect->setLastContactDate($needsAnalysis->getCreatedAt());
            $this->logger->debug('Updated prospect last contact date', [
                'prospect_id' => $prospect->getId(),
                'original_date' => $originalContactDate?->format('Y-m-d H:i:s'),
                'new_date' => $needsAnalysis->getCreatedAt()->format('Y-m-d H:i:s'),
                'method' => __METHOD__,
            ]);

            // Add notes about the needs analysis
            $description = $prospect->getDescription() ?: '';
            $newNote = sprintf(
                '[%s] Analyse de besoins (%s) envoyée',
                $needsAnalysis->getCreatedAt()->format('d/m/Y'),
                $needsAnalysis->getTypeLabel(),
            );

            if ($needsAnalysis->getAdminNotes()) {
                $newNote .= "\nNotes admin: " . $needsAnalysis->getAdminNotes();
                $this->logger->debug('Added admin notes to needs analysis note', [
                    'prospect_id' => $prospect->getId(),
                    'needs_analysis_id' => $needsAnalysis->getId(),
                    'admin_notes_length' => strlen($needsAnalysis->getAdminNotes()),
                    'method' => __METHOD__,
                ]);
            }

            $prospect->setDescription($description ? $description . "\n\n" . $newNote : $newNote);
            
            $this->logger->debug('Added needs analysis note to prospect description', [
                'prospect_id' => $prospect->getId(),
                'needs_analysis_id' => $needsAnalysis->getId(),
                'analysis_type' => $needsAnalysis->getType(),
                'note_preview' => substr($newNote, 0, 100) . '...',
                'method' => __METHOD__,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in mergeNeedsAnalysisData', [
                'prospect_id' => $prospect->getId(),
                'needs_analysis_id' => $needsAnalysis->getId(),
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to merge needs analysis data: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract first name from full name.
     */
    private function extractFirstName(string $fullName): string
    {
        $this->logger->debug('Extracting first name from full name', [
            'full_name' => $fullName,
            'method' => __METHOD__,
        ]);

        try {
            $parts = explode(' ', trim($fullName));
            $firstName = $parts[0] ?? 'Prénom';

            $this->logger->debug('Extracted first name', [
                'full_name' => $fullName,
                'extracted_first_name' => $firstName,
                'total_parts' => count($parts),
                'method' => __METHOD__,
            ]);

            return $firstName;

        } catch (\Exception $e) {
            $this->logger->error('Error extracting first name', [
                'full_name' => $fullName,
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
            ]);
            return 'Prénom';
        }
    }

    /**
     * Extract last name from full name.
     */
    private function extractLastName(string $fullName): string
    {
        $this->logger->debug('Extracting last name from full name', [
            'full_name' => $fullName,
            'method' => __METHOD__,
        ]);

        try {
            $parts = explode(' ', trim($fullName));
            if (count($parts) > 1) {
                array_shift($parts); // Remove first name
                $lastName = implode(' ', $parts);

                $this->logger->debug('Extracted last name', [
                    'full_name' => $fullName,
                    'extracted_last_name' => $lastName,
                    'total_parts' => count($parts) + 1, // +1 because we removed first name
                    'method' => __METHOD__,
                ]);

                return $lastName;
            }

            $this->logger->debug('No last name found, using default', [
                'full_name' => $fullName,
                'total_parts' => count($parts),
                'method' => __METHOD__,
            ]);

            return 'Nom';

        } catch (\Exception $e) {
            $this->logger->error('Error extracting last name', [
                'full_name' => $fullName,
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
            ]);
            return 'Nom';
        }
    }

    /**
     * Merge two prospects (move all data from source to target, then delete source).
     */
    private function mergeProspects(Prospect $target, Prospect $source): void
    {
        $this->logger->info('Starting mergeProspects process', [
            'target_prospect_id' => $target->getId(),
            'source_prospect_id' => $source->getId(),
            'email' => $target->getEmail(),
            'method' => __METHOD__,
        ]);

        try {
            // Merge basic information (keep target data, fill gaps with source)
            $updatedFields = [];

            if (!$target->getPhone() && $source->getPhone()) {
                $target->setPhone($source->getPhone());
                $updatedFields[] = 'phone';
                $this->logger->debug('Merged phone from source to target', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'phone' => $source->getPhone(),
                    'method' => __METHOD__,
                ]);
            }

            if (!$target->getCompany() && $source->getCompany()) {
                $target->setCompany($source->getCompany());
                $updatedFields[] = 'company';
                $this->logger->debug('Merged company from source to target', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'company' => $source->getCompany(),
                    'method' => __METHOD__,
                ]);
            }

            if (!$target->getPosition() && $source->getPosition()) {
                $target->setPosition($source->getPosition());
                $updatedFields[] = 'position';
                $this->logger->debug('Merged position from source to target', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'position' => $source->getPosition(),
                    'method' => __METHOD__,
                ]);
            }

            // Merge descriptions
            if ($source->getDescription()) {
                $targetDescription = $target->getDescription() ?: '';
                $target->setDescription($targetDescription . "\n\n--- Fusionné ---\n" . $source->getDescription());
                $updatedFields[] = 'description';
                $this->logger->debug('Merged description from source to target', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'source_description_length' => strlen($source->getDescription()),
                    'method' => __METHOD__,
                ]);
            }

            // Move all related entities to target prospect
            $sessionRegistrationsCount = $source->getSessionRegistrations()->count();
            foreach ($source->getSessionRegistrations() as $registration) {
                $registration->setProspect($target);
            }
            if ($sessionRegistrationsCount > 0) {
                $this->logger->debug('Moved session registrations from source to target', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'registrations_count' => $sessionRegistrationsCount,
                    'method' => __METHOD__,
                ]);
            }

            $contactRequestsCount = $source->getContactRequests()->count();
            foreach ($source->getContactRequests() as $contact) {
                $contact->setProspect($target);
            }
            if ($contactRequestsCount > 0) {
                $this->logger->debug('Moved contact requests from source to target', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'contact_requests_count' => $contactRequestsCount,
                    'method' => __METHOD__,
                ]);
            }

            $needsAnalysisCount = $source->getNeedsAnalysisRequests()->count();
            foreach ($source->getNeedsAnalysisRequests() as $analysis) {
                $analysis->setProspect($target);
            }
            if ($needsAnalysisCount > 0) {
                $this->logger->debug('Moved needs analysis requests from source to target', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'needs_analysis_count' => $needsAnalysisCount,
                    'method' => __METHOD__,
                ]);
            }

            // Merge interests
            $newFormationsCount = 0;
            foreach ($source->getInterestedFormations() as $formation) {
                if (!$target->getInterestedFormations()->contains($formation)) {
                    $target->addInterestedFormation($formation);
                    $newFormationsCount++;
                }
            }
            if ($newFormationsCount > 0) {
                $this->logger->debug('Merged formation interests from source to target', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'new_formations_count' => $newFormationsCount,
                    'method' => __METHOD__,
                ]);
            }

            $newServicesCount = 0;
            foreach ($source->getInterestedServices() as $service) {
                if (!$target->getInterestedServices()->contains($service)) {
                    $target->addInterestedService($service);
                    $newServicesCount++;
                }
            }
            if ($newServicesCount > 0) {
                $this->logger->debug('Merged service interests from source to target', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'new_services_count' => $newServicesCount,
                    'method' => __METHOD__,
                ]);
            }

            // Update dates - keep most recent
            if ($source->getLastContactDate()
                && (!$target->getLastContactDate() || $source->getLastContactDate() > $target->getLastContactDate())) {
                $originalDate = $target->getLastContactDate();
                $target->setLastContactDate($source->getLastContactDate());
                $updatedFields[] = 'last_contact_date';
                $this->logger->debug('Updated last contact date from source', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'original_date' => $originalDate?->format('Y-m-d H:i:s'),
                    'new_date' => $source->getLastContactDate()->format('Y-m-d H:i:s'),
                    'method' => __METHOD__,
                ]);
            }

            if ($source->getNextFollowUpDate()
                && (!$target->getNextFollowUpDate() || $source->getNextFollowUpDate() < $target->getNextFollowUpDate())) {
                $originalDate = $target->getNextFollowUpDate();
                $target->setNextFollowUpDate($source->getNextFollowUpDate());
                $updatedFields[] = 'next_follow_up_date';
                $this->logger->debug('Updated next follow up date from source', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'original_date' => $originalDate?->format('Y-m-d H:i:s'),
                    'new_date' => $source->getNextFollowUpDate()->format('Y-m-d H:i:s'),
                    'method' => __METHOD__,
                ]);
            }

            // Upgrade status if source has higher status
            $statusPriority = ['lead' => 1, 'prospect' => 2, 'qualified' => 3, 'negotiation' => 4, 'customer' => 5];
            $targetPriority = $statusPriority[$target->getStatus()] ?? 0;
            $sourcePriority = $statusPriority[$source->getStatus()] ?? 0;

            if ($sourcePriority > $targetPriority) {
                $originalStatus = $target->getStatus();
                $target->setStatus($source->getStatus());
                $updatedFields[] = 'status';
                $this->logger->debug('Upgraded target status from source', [
                    'target_id' => $target->getId(),
                    'source_id' => $source->getId(),
                    'original_status' => $originalStatus,
                    'new_status' => $source->getStatus(),
                    'source_priority' => $sourcePriority,
                    'target_priority' => $targetPriority,
                    'method' => __METHOD__,
                ]);
            }

            // Remove source prospect
            $this->logger->debug('Removing source prospect from entity manager', [
                'target_id' => $target->getId(),
                'source_id' => $source->getId(),
                'method' => __METHOD__,
            ]);

            $this->entityManager->remove($source);

            $this->logger->info('Successfully merged prospects', [
                'target_id' => $target->getId(),
                'source_id' => $source->getId(),
                'email' => $target->getEmail(),
                'updated_fields' => $updatedFields,
                'session_registrations_moved' => $sessionRegistrationsCount,
                'contact_requests_moved' => $contactRequestsCount,
                'needs_analysis_moved' => $needsAnalysisCount,
                'new_formations_added' => $newFormationsCount,
                'new_services_added' => $newServicesCount,
                'method' => __METHOD__,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in mergeProspects', [
                'target_id' => $target->getId(),
                'source_id' => $source->getId(),
                'email' => $target->getEmail(),
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to merge prospects: ' . $e->getMessage(), 0, $e);
        }
    }
}
