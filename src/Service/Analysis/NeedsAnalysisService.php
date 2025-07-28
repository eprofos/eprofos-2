<?php

namespace App\Service\Analysis;

use App\Entity\Analysis\NeedsAnalysisRequest;
use App\Entity\Analysis\CompanyNeedsAnalysis;
use App\Entity\Analysis\IndividualNeedsAnalysis;
use App\Entity\User\Admin;
use App\Entity\Training\Formation;
use App\Repository\Analysis\NeedsAnalysisRequestRepository;
use App\Repository\Analysis\CompanyNeedsAnalysisRepository;
use App\Repository\Analysis\IndividualNeedsAnalysisRepository;
use App\Service\Core\TokenGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Needs Analysis Service
 * 
 * Main service for managing needs analysis requests and their lifecycle.
 * Handles creation, validation, status updates, and business logic.
 */
class NeedsAnalysisService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NeedsAnalysisRequestRepository $needsAnalysisRequestRepository,
        private CompanyNeedsAnalysisRepository $companyNeedsAnalysisRepository,
        private IndividualNeedsAnalysisRepository $individualNeedsAnalysisRepository,
        private TokenGeneratorService $tokenGeneratorService,
        private AnalysisEmailNotificationService $emailNotificationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a new needs analysis request
     */
    public function createNeedsAnalysisRequest(
        string $type,
        string $recipientName,
        string $recipientEmail,
        ?string $companyName = null,
        ?Formation $formation = null,
        ?UserInterface $createdBy = null,
        ?string $notes = null
    ): NeedsAnalysisRequest {
        $this->logger->info('Creating new needs analysis request', [
            'type' => $type,
            'recipient_email' => $recipientEmail,
            'company_name' => $companyName
        ]);

        // Validate type
        if (!in_array($type, [NeedsAnalysisRequest::TYPE_COMPANY, NeedsAnalysisRequest::TYPE_INDIVIDUAL])) {
            throw new \InvalidArgumentException('Invalid needs analysis type');
        }

        // Generate token and expiration
        $tokenData = $this->tokenGeneratorService->generateTokenWithExpiration();

        // Create the request
        $request = new NeedsAnalysisRequest();
        $request->setType($type)
                ->setRecipientName($recipientName)
                ->setRecipientEmail($recipientEmail)
                ->setCompanyName($companyName)
                ->setFormation($formation)
                ->setCreatedByAdmin($createdBy instanceof Admin ? $createdBy : null)
                ->setToken($tokenData['token'])
                ->setExpiresAt($tokenData['expires_at'])
                ->setStatus(NeedsAnalysisRequest::STATUS_PENDING)
                ->setAdminNotes($notes);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        $this->logger->info('Needs analysis request created successfully', [
            'id' => $request->getId(),
            'token' => $request->getToken()
        ]);

        return $request;
    }

    /**
     * Send a needs analysis request via email
     */
    public function sendNeedsAnalysisRequest(NeedsAnalysisRequest $request): bool
    {
        if ($request->getStatus() !== NeedsAnalysisRequest::STATUS_PENDING) {
            throw new \LogicException('Can only send pending requests');
        }

        try {
            $this->logger->info('Sending needs analysis request', [
                'id' => $request->getId(),
                'recipient_email' => $request->getRecipientEmail()
            ]);

            // Send email notification
            $emailSent = $this->emailNotificationService->sendNeedsAnalysisRequest($request);

            if ($emailSent) {
                // Update status to sent
                $request->setStatus(NeedsAnalysisRequest::STATUS_SENT)
                        ->setSentAt(new \DateTimeImmutable());

                $this->entityManager->flush();

                $this->logger->info('Needs analysis request sent successfully', [
                    'id' => $request->getId()
                ]);

                return true;
            }

            $this->logger->error('Failed to send needs analysis request email', [
                'id' => $request->getId()
            ]);

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Error sending needs analysis request', [
                'id' => $request->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Find a request by token
     */
    public function findRequestByToken(string $token): ?NeedsAnalysisRequest
    {
        if (!$this->tokenGeneratorService->isValidTokenFormat($token)) {
            return null;
        }

        return $this->needsAnalysisRequestRepository->findByToken($token);
    }

    /**
     * Check if a request is accessible (not expired, not completed)
     */
    public function isRequestAccessible(NeedsAnalysisRequest $request): bool
    {
        // Check if expired
        if ($this->tokenGeneratorService->isTokenExpired($request->getExpiresAt())) {
            return false;
        }

        // Check status
        return in_array($request->getStatus(), [
            NeedsAnalysisRequest::STATUS_SENT,
            NeedsAnalysisRequest::STATUS_PENDING
        ]);
    }

    /**
     * Submit a company needs analysis
     */
    public function submitCompanyAnalysis(
        NeedsAnalysisRequest $request,
        array $analysisData
    ): CompanyNeedsAnalysis {
        if ($request->getType() !== NeedsAnalysisRequest::TYPE_COMPANY) {
            throw new \InvalidArgumentException('Request is not for company analysis');
        }

        if (!$this->isRequestAccessible($request)) {
            throw new \LogicException('Request is not accessible');
        }

        $this->logger->info('Submitting company needs analysis', [
            'request_id' => $request->getId()
        ]);

        // Create company analysis
        $analysis = new CompanyNeedsAnalysis();
        $analysis->setNeedsAnalysisRequest($request);

        // Set all the data
        $this->populateCompanyAnalysis($analysis, $analysisData);

        // Update request status
        $request->setStatus(NeedsAnalysisRequest::STATUS_COMPLETED)
                ->setCompletedAt(new \DateTimeImmutable());

        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        $this->logger->info('Company needs analysis submitted successfully', [
            'request_id' => $request->getId(),
            'analysis_id' => $analysis->getId()
        ]);

        // Send notification to admin
        $this->emailNotificationService->sendAnalysisCompletedNotification($request);

        return $analysis;
    }

    /**
     * Submit an individual needs analysis
     */
    public function submitIndividualAnalysis(
        NeedsAnalysisRequest $request,
        array $analysisData
    ): IndividualNeedsAnalysis {
        if ($request->getType() !== NeedsAnalysisRequest::TYPE_INDIVIDUAL) {
            throw new \InvalidArgumentException('Request is not for individual analysis');
        }

        if (!$this->isRequestAccessible($request)) {
            throw new \LogicException('Request is not accessible');
        }

        $this->logger->info('Submitting individual needs analysis', [
            'request_id' => $request->getId()
        ]);

        // Create individual analysis
        $analysis = new IndividualNeedsAnalysis();
        $analysis->setNeedsAnalysisRequest($request);

        // Set all the data
        $this->populateIndividualAnalysis($analysis, $analysisData);

        // Update request status
        $request->setStatus(NeedsAnalysisRequest::STATUS_COMPLETED)
                ->setCompletedAt(new \DateTimeImmutable());

        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        $this->logger->info('Individual needs analysis submitted successfully', [
            'request_id' => $request->getId(),
            'analysis_id' => $analysis->getId()
        ]);

        // Send notification to admin
        $this->emailNotificationService->sendAnalysisCompletedNotification($request);

        return $analysis;
    }

    /**
     * Cancel a needs analysis request
     */
    public function cancelRequest(NeedsAnalysisRequest $request, ?string $reason = null): void
    {
        if ($request->getStatus() === NeedsAnalysisRequest::STATUS_COMPLETED) {
            throw new \LogicException('Cannot cancel completed request');
        }

        $this->logger->info('Cancelling needs analysis request', [
            'id' => $request->getId(),
            'reason' => $reason
        ]);

        $request->setStatus(NeedsAnalysisRequest::STATUS_CANCELLED);
        
        if ($reason) {
            $currentNotes = $request->getAdminNotes();
            $newNote = 'Annulé: ' . $reason;
            $request->setAdminNotes($currentNotes ? $currentNotes . "\n" . $newNote : $newNote);
        }

        $this->entityManager->flush();

        $this->logger->info('Needs analysis request cancelled', [
            'id' => $request->getId()
        ]);
    }

    /**
     * Mark expired requests as expired
     */
    public function markExpiredRequests(): int
    {
        $this->logger->info('Marking expired requests');

        $count = $this->needsAnalysisRequestRepository->markExpiredRequests();

        $this->logger->info('Marked expired requests', [
            'count' => $count
        ]);

        return $count;
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStatistics(): array
    {
        return [
            'requests' => $this->needsAnalysisRequestRepository->getStatistics(),
            'company_analyses' => $this->companyNeedsAnalysisRepository->getCompanyStatistics(),
            'individual_analyses' => $this->individualNeedsAnalysisRepository->getIndividualStatistics(),
        ];
    }

    /**
     * Get requests expiring soon
     */
    public function getRequestsExpiringSoon(int $days = 7): array
    {
        return $this->needsAnalysisRequestRepository->findRequestsExpiringSoon($days);
    }

    /**
     * Resend a needs analysis request
     */
    public function resendRequest(NeedsAnalysisRequest $request): bool
    {
        if ($request->getStatus() !== NeedsAnalysisRequest::STATUS_SENT) {
            throw new \LogicException('Can only resend sent requests');
        }

        // Generate new token and expiration
        $tokenData = $this->tokenGeneratorService->generateTokenWithExpiration();
        
        $request->setToken($tokenData['token'])
                ->setExpiresAt($tokenData['expires_at']);

        $this->entityManager->flush();

        return $this->emailNotificationService->sendNeedsAnalysisRequest($request);
    }

    /**
     * Populate company analysis with data
     */
    private function populateCompanyAnalysis(CompanyNeedsAnalysis $analysis, array $data): void
    {
        $analysis->setCompanyName($data['company_name'] ?? '')
                 ->setResponsiblePerson($data['responsible_person'] ?? '')
                 ->setContactEmail($data['contact_email'] ?? '')
                 ->setContactPhone($data['contact_phone'] ?? '')
                 ->setCompanyAddress($data['company_address'] ?? '')
                 ->setActivitySector($data['activity_sector'] ?? '')
                 ->setNafCode($data['naf_code'] ?? null)
                 ->setSiret($data['siret'] ?? null)
                 ->setEmployeeCount($data['employee_count'] ?? 0)
                 ->setOpco($data['opco'] ?? null)
                 ->setTraineesInfo($data['trainees_info'] ?? [])
                 ->setTrainingTitle($data['training_title'] ?? '')
                 ->setTrainingDurationHours($data['training_duration_hours'] ?? 0)
                 ->setPreferredStartDate($data['preferred_start_date'] ?? null)
                 ->setPreferredEndDate($data['preferred_end_date'] ?? null)
                 ->setTrainingLocationPreference($data['training_location_preference'] ?? '')
                 ->setLocationAppropriationNeeds($data['location_appropriation_needs'] ?? null)
                 ->setDisabilityAccommodations($data['disability_accommodations'] ?? null)
                 ->setTrainingExpectations($data['training_expectations'] ?? '')
                 ->setSpecificNeeds($data['specific_needs'] ?? '');
    }

    /**
     * Populate individual analysis with data
     */
    private function populateIndividualAnalysis(IndividualNeedsAnalysis $analysis, array $data): void
    {
        $analysis->setFirstName($data['first_name'] ?? '')
                 ->setLastName($data['last_name'] ?? '')
                 ->setAddress($data['address'] ?? '')
                 ->setPhone($data['phone'] ?? '')
                 ->setEmail($data['email'] ?? '')
                 ->setStatus($data['status'] ?? '')
                 ->setStatusOtherDetails($data['status_other_details'] ?? null)
                 ->setFundingType($data['funding_type'] ?? '')
                 ->setFundingOtherDetails($data['funding_other_details'] ?? null)
                 ->setDesiredTrainingTitle($data['desired_training_title'] ?? '')
                 ->setProfessionalObjective($data['professional_objective'] ?? '')
                 ->setCurrentLevel($data['current_level'] ?? '')
                 ->setDesiredDurationHours($data['desired_duration_hours'] ?? 0)
                 ->setPreferredStartDate($data['preferred_start_date'] ?? null)
                 ->setPreferredEndDate($data['preferred_end_date'] ?? null)
                 ->setTrainingLocationPreference($data['training_location_preference'] ?? '')
                 ->setDisabilityAccommodations($data['disability_accommodations'] ?? null)
                 ->setTrainingExpectations($data['training_expectations'] ?? '')
                 ->setSpecificNeeds($data['specific_needs'] ?? '');
    }
}