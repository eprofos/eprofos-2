<?php

declare(strict_types=1);

namespace App\Service\Analysis;

use App\Entity\Analysis\CompanyNeedsAnalysis;
use App\Entity\Analysis\IndividualNeedsAnalysis;
use App\Entity\Analysis\NeedsAnalysisRequest;
use App\Entity\Training\Formation;
use App\Entity\User\Admin;
use App\Repository\Analysis\CompanyNeedsAnalysisRepository;
use App\Repository\Analysis\IndividualNeedsAnalysisRepository;
use App\Repository\Analysis\NeedsAnalysisRequestRepository;
use App\Service\Core\TokenGeneratorService;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

/**
 * Needs Analysis Service.
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
        private LoggerInterface $logger,
    ) {}

    /**
     * Create a new needs analysis request.
     */
    public function createNeedsAnalysisRequest(
        string $type,
        string $recipientName,
        string $recipientEmail,
        ?string $companyName = null,
        ?Formation $formation = null,
        ?UserInterface $createdBy = null,
        ?string $notes = null,
    ): NeedsAnalysisRequest {
        $requestContext = [
            'method' => 'createNeedsAnalysisRequest',
            'type' => $type,
            'recipient_name' => $recipientName,
            'recipient_email' => $recipientEmail,
            'company_name' => $companyName,
            'formation_id' => $formation?->getId(),
            'formation_title' => $formation?->getTitle(),
            'created_by_type' => $createdBy ? $createdBy::class : null,
            'created_by_id' => $createdBy instanceof Admin ? $createdBy->getId() : null,
            'has_notes' => !empty($notes),
        ];

        $this->logger->info('Starting creation of new needs analysis request', $requestContext);

        try {
            // Validate input parameters
            $this->logger->debug('Validating input parameters', $requestContext);

            if (empty(trim($type))) {
                $this->logger->error('Type parameter is empty', $requestContext);

                throw new InvalidArgumentException('Type parameter cannot be empty');
            }

            if (empty(trim($recipientName))) {
                $this->logger->error('Recipient name parameter is empty', $requestContext);

                throw new InvalidArgumentException('Recipient name parameter cannot be empty');
            }

            if (empty(trim($recipientEmail))) {
                $this->logger->error('Recipient email parameter is empty', $requestContext);

                throw new InvalidArgumentException('Recipient email parameter cannot be empty');
            }

            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $this->logger->error('Invalid email format provided', $requestContext);

                throw new InvalidArgumentException('Invalid email format provided');
            }

            // Validate type
            if (!in_array($type, [NeedsAnalysisRequest::TYPE_COMPANY, NeedsAnalysisRequest::TYPE_INDIVIDUAL], true)) {
                $this->logger->error('Invalid needs analysis type provided', array_merge($requestContext, [
                    'allowed_types' => [NeedsAnalysisRequest::TYPE_COMPANY, NeedsAnalysisRequest::TYPE_INDIVIDUAL],
                ]));

                throw new InvalidArgumentException('Invalid needs analysis type');
            }

            $this->logger->debug('Input parameters validation completed successfully', $requestContext);

            // Generate token and expiration
            $this->logger->debug('Generating security token and expiration', $requestContext);

            try {
                $tokenData = $this->tokenGeneratorService->generateTokenWithExpiration();
                $this->logger->debug('Token generated successfully', array_merge($requestContext, [
                    'token_length' => strlen($tokenData['token']),
                    'expires_at' => $tokenData['expires_at']->format('Y-m-d H:i:s'),
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Failed to generate token', array_merge($requestContext, [
                    'token_error' => $e->getMessage(),
                    'token_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Failed to generate security token: ' . $e->getMessage(), 0, $e);
            }

            // Create the request entity
            $this->logger->debug('Creating NeedsAnalysisRequest entity', $requestContext);

            try {
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
                    ->setAdminNotes($notes)
                ;

                $this->logger->debug('NeedsAnalysisRequest entity created successfully', array_merge($requestContext, [
                    'entity_status' => $request->getStatus(),
                    'entity_token' => substr($request->getToken(), 0, 8) . '...',
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Failed to create NeedsAnalysisRequest entity', array_merge($requestContext, [
                    'entity_error' => $e->getMessage(),
                    'entity_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Failed to create needs analysis request entity: ' . $e->getMessage(), 0, $e);
            }

            // Persist to database
            $this->logger->debug('Persisting entity to database', $requestContext);

            try {
                $this->entityManager->persist($request);
                $this->entityManager->flush();

                $finalContext = array_merge($requestContext, [
                    'request_id' => $request->getId(),
                    'request_token' => substr($request->getToken(), 0, 8) . '...',
                    'request_status' => $request->getStatus(),
                    'created_at' => $request->getCreatedAt()?->format('Y-m-d H:i:s'),
                ]);

                $this->logger->info('Needs analysis request created and persisted successfully', $finalContext);

                return $request;
            } catch (DBALException|ORMException $e) {
                $this->logger->error('Database error while persisting needs analysis request', array_merge($requestContext, [
                    'db_error' => $e->getMessage(),
                    'db_error_code' => $e->getCode(),
                    'db_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Database error while creating needs analysis request: ' . $e->getMessage(), 0, $e);
            } catch (Throwable $e) {
                $this->logger->error('Unexpected error while persisting needs analysis request', array_merge($requestContext, [
                    'unexpected_error' => $e->getMessage(),
                    'unexpected_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Unexpected error while creating needs analysis request: ' . $e->getMessage(), 0, $e);
            }
        } catch (InvalidArgumentException|RuntimeException $e) {
            // Re-throw validation and runtime exceptions
            throw $e;
        } catch (Throwable $e) {
            $this->logger->critical('Critical error in createNeedsAnalysisRequest', array_merge($requestContext, [
                'critical_error' => $e->getMessage(),
                'critical_error_class' => $e::class,
                'critical_error_trace' => $e->getTraceAsString(),
            ]));

            throw new RuntimeException('Critical error while creating needs analysis request: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send a needs analysis request via email.
     */
    public function sendNeedsAnalysisRequest(NeedsAnalysisRequest $request): bool
    {
        $sendContext = [
            'method' => 'sendNeedsAnalysisRequest',
            'request_id' => $request->getId(),
            'request_type' => $request->getType(),
            'recipient_email' => $request->getRecipientEmail(),
            'recipient_name' => $request->getRecipientName(),
            'current_status' => $request->getStatus(),
            'company_name' => $request->getCompanyName(),
            'formation_id' => $request->getFormation()?->getId(),
            'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
        ];

        $this->logger->info('Starting to send needs analysis request', $sendContext);

        try {
            // Validate request status
            $this->logger->debug('Validating request status for sending', $sendContext);

            if ($request->getStatus() !== NeedsAnalysisRequest::STATUS_PENDING) {
                $this->logger->warning('Attempted to send request with invalid status', array_merge($sendContext, [
                    'expected_status' => NeedsAnalysisRequest::STATUS_PENDING,
                    'validation_error' => 'Can only send pending requests',
                ]));

                throw new LogicException('Can only send pending requests');
            }

            // Validate request data
            $this->logger->debug('Validating request data before sending', $sendContext);

            if (empty($request->getRecipientEmail())) {
                $this->logger->error('Request has no recipient email', $sendContext);

                throw new InvalidArgumentException('Request must have a recipient email');
            }

            if (empty($request->getToken())) {
                $this->logger->error('Request has no token', $sendContext);

                throw new InvalidArgumentException('Request must have a valid token');
            }

            if (!$request->getExpiresAt() || $request->getExpiresAt() <= new DateTimeImmutable()) {
                $this->logger->error('Request token is expired or invalid', array_merge($sendContext, [
                    'token_expired' => true,
                    'current_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]));

                throw new InvalidArgumentException('Request token is expired or invalid');
            }

            $this->logger->debug('Request validation completed successfully', $sendContext);

            // Send email notification
            $this->logger->debug('Sending email notification via AnalysisEmailNotificationService', $sendContext);

            try {
                $emailSent = $this->emailNotificationService->sendNeedsAnalysisRequest($request);

                $this->logger->debug('Email notification service call completed', array_merge($sendContext, [
                    'email_sent_result' => $emailSent,
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Email notification service failed', array_merge($sendContext, [
                    'email_service_error' => $e->getMessage(),
                    'email_service_error_class' => $e::class,
                    'email_service_error_trace' => $e->getTraceAsString(),
                ]));

                return false;
            }

            if ($emailSent) {
                $this->logger->debug('Email sent successfully, updating request status', $sendContext);

                try {
                    // Update status to sent
                    $sentAt = new DateTimeImmutable();
                    $request->setStatus(NeedsAnalysisRequest::STATUS_SENT)
                        ->setSentAt($sentAt)
                    ;

                    $this->entityManager->flush();

                    $finalContext = array_merge($sendContext, [
                        'new_status' => $request->getStatus(),
                        'sent_at' => $sentAt->format('Y-m-d H:i:s'),
                        'operation_successful' => true,
                    ]);

                    $this->logger->info('Needs analysis request sent successfully', $finalContext);

                    return true;
                } catch (DBALException|ORMException $e) {
                    $this->logger->error('Database error while updating request status after email sent', array_merge($sendContext, [
                        'db_update_error' => $e->getMessage(),
                        'db_update_error_code' => $e->getCode(),
                        'email_was_sent' => true,
                    ]));

                    // Email was sent but status update failed - this is a critical issue
                    throw new RuntimeException('Email was sent but failed to update database status: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $this->logger->warning('Email notification service returned false', array_merge($sendContext, [
                    'email_sent_result' => false,
                    'operation_successful' => false,
                ]));

                return false;
            }
        } catch (InvalidArgumentException|LogicException $e) {
            $this->logger->error('Validation error in sendNeedsAnalysisRequest', array_merge($sendContext, [
                'validation_error' => $e->getMessage(),
                'validation_error_class' => $e::class,
            ]));

            throw $e;
        } catch (RuntimeException $e) {
            // Re-throw runtime exceptions (like database errors after email sent)
            throw $e;
        } catch (Throwable $e) {
            $this->logger->critical('Unexpected error in sendNeedsAnalysisRequest', array_merge($sendContext, [
                'unexpected_error' => $e->getMessage(),
                'unexpected_error_class' => $e::class,
                'unexpected_error_trace' => $e->getTraceAsString(),
            ]));

            return false;
        }
    }

    /**
     * Find a request by token.
     */
    public function findRequestByToken(string $token): ?NeedsAnalysisRequest
    {
        $findContext = [
            'method' => 'findRequestByToken',
            'token_length' => strlen($token),
            'token_preview' => substr($token, 0, 8) . '...',
        ];

        $this->logger->debug('Starting token-based request lookup', $findContext);

        try {
            // Validate token format
            $this->logger->debug('Validating token format', $findContext);

            if (empty(trim($token))) {
                $this->logger->warning('Empty token provided for lookup', $findContext);

                return null;
            }

            try {
                if (!$this->tokenGeneratorService->isValidTokenFormat($token)) {
                    $this->logger->warning('Invalid token format provided', array_merge($findContext, [
                        'token_format_valid' => false,
                    ]));

                    return null;
                }

                $this->logger->debug('Token format validation passed', array_merge($findContext, [
                    'token_format_valid' => true,
                ]));
            } catch (Throwable $e) {
                $this->logger->warning('Token format validation failed with exception', array_merge($findContext, [
                    'validation_error' => $e->getMessage(),
                    'validation_error_class' => $e::class,
                ]));

                return null;
            }

            // Search in repository
            $this->logger->debug('Searching for request in repository', $findContext);

            try {
                $request = $this->needsAnalysisRequestRepository->findByToken($token);

                if ($request) {
                    $resultContext = array_merge($findContext, [
                        'request_found' => true,
                        'request_id' => $request->getId(),
                        'request_status' => $request->getStatus(),
                        'request_type' => $request->getType(),
                        'recipient_email' => $request->getRecipientEmail(),
                        'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
                        'created_at' => $request->getCreatedAt()?->format('Y-m-d H:i:s'),
                    ]);

                    $this->logger->info('Request found by token', $resultContext);
                } else {
                    $this->logger->info('No request found for provided token', array_merge($findContext, [
                        'request_found' => false,
                    ]));
                }

                return $request;
            } catch (DBALException|ORMException $e) {
                $this->logger->error('Database error during token lookup', array_merge($findContext, [
                    'db_error' => $e->getMessage(),
                    'db_error_code' => $e->getCode(),
                    'db_error_class' => $e::class,
                ]));

                return null;
            }
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error during token lookup', array_merge($findContext, [
                'unexpected_error' => $e->getMessage(),
                'unexpected_error_class' => $e::class,
                'unexpected_error_trace' => $e->getTraceAsString(),
            ]));

            return null;
        }
    }

    /**
     * Check if a request is accessible (not expired, not completed).
     */
    public function isRequestAccessible(NeedsAnalysisRequest $request): bool
    {
        $accessContext = [
            'method' => 'isRequestAccessible',
            'request_id' => $request->getId(),
            'request_status' => $request->getStatus(),
            'request_type' => $request->getType(),
            'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
            'current_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->logger->debug('Checking request accessibility', $accessContext);

        try {
            // Check if expired
            $this->logger->debug('Checking token expiration', $accessContext);

            $expiresAt = $request->getExpiresAt();
            if (!$expiresAt) {
                $this->logger->warning('Request has no expiration date', array_merge($accessContext, [
                    'expiration_check' => 'no_expiration_date',
                    'accessible' => false,
                ]));

                return false;
            }

            try {
                $isExpired = $this->tokenGeneratorService->isTokenExpired($expiresAt);

                if ($isExpired) {
                    $this->logger->info('Request token is expired', array_merge($accessContext, [
                        'expiration_check' => 'expired',
                        'accessible' => false,
                    ]));

                    return false;
                }

                $this->logger->debug('Token expiration check passed', array_merge($accessContext, [
                    'expiration_check' => 'valid',
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Error checking token expiration', array_merge($accessContext, [
                    'expiration_error' => $e->getMessage(),
                    'expiration_error_class' => $e::class,
                ]));

                return false;
            }

            // Check status
            $this->logger->debug('Checking request status accessibility', $accessContext);

            $allowedStatuses = [
                NeedsAnalysisRequest::STATUS_SENT,
                NeedsAnalysisRequest::STATUS_PENDING,
            ];

            $isStatusAccessible = in_array($request->getStatus(), $allowedStatuses, true);

            $finalContext = array_merge($accessContext, [
                'allowed_statuses' => $allowedStatuses,
                'status_accessible' => $isStatusAccessible,
                'accessible' => $isStatusAccessible,
            ]);

            if ($isStatusAccessible) {
                $this->logger->debug('Request is accessible', $finalContext);
            } else {
                $this->logger->info('Request is not accessible due to status', $finalContext);
            }

            return $isStatusAccessible;
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error checking request accessibility', array_merge($accessContext, [
                'unexpected_error' => $e->getMessage(),
                'unexpected_error_class' => $e::class,
                'accessible' => false,
            ]));

            return false;
        }
    }

    /**
     * Submit a company needs analysis.
     */
    public function submitCompanyAnalysis(
        NeedsAnalysisRequest $request,
        array $analysisData,
    ): CompanyNeedsAnalysis {
        $submitContext = [
            'method' => 'submitCompanyAnalysis',
            'request_id' => $request->getId(),
            'request_type' => $request->getType(),
            'request_status' => $request->getStatus(),
            'data_keys' => array_keys($analysisData),
            'data_count' => count($analysisData),
            'company_name' => $analysisData['company_name'] ?? null,
            'responsible_person' => $analysisData['responsible_person'] ?? null,
            'contact_email' => $analysisData['contact_email'] ?? null,
        ];

        $this->logger->info('Starting company needs analysis submission', $submitContext);

        try {
            // Validate request type
            $this->logger->debug('Validating request type for company analysis', $submitContext);

            if ($request->getType() !== NeedsAnalysisRequest::TYPE_COMPANY) {
                $this->logger->error('Invalid request type for company analysis', array_merge($submitContext, [
                    'expected_type' => NeedsAnalysisRequest::TYPE_COMPANY,
                    'validation_error' => 'Request is not for company analysis',
                ]));

                throw new InvalidArgumentException('Request is not for company analysis');
            }

            // Check accessibility
            $this->logger->debug('Checking request accessibility', $submitContext);

            try {
                if (!$this->isRequestAccessible($request)) {
                    $this->logger->warning('Request is not accessible for submission', array_merge($submitContext, [
                        'accessibility_check' => 'failed',
                        'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
                    ]));

                    throw new LogicException('Request is not accessible');
                }

                $this->logger->debug('Request accessibility check passed', array_merge($submitContext, [
                    'accessibility_check' => 'passed',
                ]));
            } catch (LogicException $e) {
                throw $e;
            } catch (Throwable $e) {
                $this->logger->error('Error during accessibility check', array_merge($submitContext, [
                    'accessibility_error' => $e->getMessage(),
                    'accessibility_error_class' => $e::class,
                ]));

                throw new RuntimeException('Failed to verify request accessibility: ' . $e->getMessage(), 0, $e);
            }

            // Validate analysis data
            $this->logger->debug('Validating analysis data', $submitContext);

            $requiredFields = ['company_name', 'responsible_person', 'contact_email'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (empty($analysisData[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $this->logger->error('Missing required fields in analysis data', array_merge($submitContext, [
                    'missing_fields' => $missingFields,
                    'required_fields' => $requiredFields,
                ]));

                throw new InvalidArgumentException('Missing required fields: ' . implode(', ', $missingFields));
            }

            // Validate email format
            if (!filter_var($analysisData['contact_email'], FILTER_VALIDATE_EMAIL)) {
                $this->logger->error('Invalid email format in analysis data', array_merge($submitContext, [
                    'invalid_email' => $analysisData['contact_email'],
                ]));

                throw new InvalidArgumentException('Invalid email format provided');
            }

            $this->logger->debug('Analysis data validation completed successfully', $submitContext);

            // Create company analysis entity
            $this->logger->debug('Creating CompanyNeedsAnalysis entity', $submitContext);

            try {
                $analysis = new CompanyNeedsAnalysis();
                $analysis->setNeedsAnalysisRequest($request);

                // Populate with data
                $this->logger->debug('Populating company analysis with provided data', $submitContext);
                $this->populateCompanyAnalysis($analysis, $analysisData);

                $this->logger->debug('Company analysis entity populated successfully', array_merge($submitContext, [
                    'analysis_company_name' => $analysis->getCompanyName(),
                    'analysis_training_title' => $analysis->getTrainingTitle(),
                    'analysis_duration_hours' => $analysis->getTrainingDurationHours(),
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Error creating or populating company analysis entity', array_merge($submitContext, [
                    'entity_error' => $e->getMessage(),
                    'entity_error_class' => $e::class,
                    'entity_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Failed to create company analysis entity: ' . $e->getMessage(), 0, $e);
            }

            // Update request status and persist
            $this->logger->debug('Updating request status and persisting to database', $submitContext);

            try {
                $completedAt = new DateTimeImmutable();
                $request->setStatus(NeedsAnalysisRequest::STATUS_COMPLETED)
                    ->setCompletedAt($completedAt)
                ;

                $this->entityManager->persist($analysis);
                $this->entityManager->flush();

                $finalContext = array_merge($submitContext, [
                    'analysis_id' => $analysis->getId(),
                    'new_request_status' => $request->getStatus(),
                    'completed_at' => $completedAt->format('Y-m-d H:i:s'),
                    'operation_successful' => true,
                ]);

                $this->logger->info('Company needs analysis submitted and persisted successfully', $finalContext);
            } catch (DBALException|ORMException $e) {
                $this->logger->error('Database error while persisting company analysis', array_merge($submitContext, [
                    'db_error' => $e->getMessage(),
                    'db_error_code' => $e->getCode(),
                    'db_error_class' => $e::class,
                    'db_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Database error while saving company analysis: ' . $e->getMessage(), 0, $e);
            }

            // Send notification to admin
            $this->logger->debug('Sending completion notification to admin', array_merge($finalContext, [
                'notification_step' => 'starting',
            ]));

            try {
                $notificationSent = $this->emailNotificationService->sendAnalysisCompletedNotification($request);

                $this->logger->info('Admin notification process completed', array_merge($finalContext, [
                    'notification_sent' => $notificationSent,
                    'notification_step' => 'completed',
                ]));
            } catch (Throwable $e) {
                $this->logger->warning('Failed to send admin notification but analysis was saved', array_merge($finalContext, [
                    'notification_error' => $e->getMessage(),
                    'notification_error_class' => $e::class,
                    'notification_step' => 'failed',
                ]));
                // Don't fail the whole operation if notification fails
            }

            return $analysis;
        } catch (InvalidArgumentException|LogicException $e) {
            $this->logger->error('Validation error in submitCompanyAnalysis', array_merge($submitContext, [
                'validation_error' => $e->getMessage(),
                'validation_error_class' => $e::class,
            ]));

            throw $e;
        } catch (RuntimeException $e) {
            // Re-throw runtime exceptions
            throw $e;
        } catch (Throwable $e) {
            $this->logger->critical('Critical error in submitCompanyAnalysis', array_merge($submitContext, [
                'critical_error' => $e->getMessage(),
                'critical_error_class' => $e::class,
                'critical_error_trace' => $e->getTraceAsString(),
            ]));

            throw new RuntimeException('Critical error while submitting company analysis: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Submit an individual needs analysis.
     */
    public function submitIndividualAnalysis(
        NeedsAnalysisRequest $request,
        array $analysisData,
    ): IndividualNeedsAnalysis {
        $submitContext = [
            'method' => 'submitIndividualAnalysis',
            'request_id' => $request->getId(),
            'request_type' => $request->getType(),
            'request_status' => $request->getStatus(),
            'data_keys' => array_keys($analysisData),
            'data_count' => count($analysisData),
            'first_name' => $analysisData['first_name'] ?? null,
            'last_name' => $analysisData['last_name'] ?? null,
            'email' => $analysisData['email'] ?? null,
        ];

        $this->logger->info('Starting individual needs analysis submission', $submitContext);

        try {
            // Validate request type
            $this->logger->debug('Validating request type for individual analysis', $submitContext);

            if ($request->getType() !== NeedsAnalysisRequest::TYPE_INDIVIDUAL) {
                $this->logger->error('Invalid request type for individual analysis', array_merge($submitContext, [
                    'expected_type' => NeedsAnalysisRequest::TYPE_INDIVIDUAL,
                    'validation_error' => 'Request is not for individual analysis',
                ]));

                throw new InvalidArgumentException('Request is not for individual analysis');
            }

            // Check accessibility
            $this->logger->debug('Checking request accessibility', $submitContext);

            try {
                if (!$this->isRequestAccessible($request)) {
                    $this->logger->warning('Request is not accessible for submission', array_merge($submitContext, [
                        'accessibility_check' => 'failed',
                        'expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
                    ]));

                    throw new LogicException('Request is not accessible');
                }

                $this->logger->debug('Request accessibility check passed', array_merge($submitContext, [
                    'accessibility_check' => 'passed',
                ]));
            } catch (LogicException $e) {
                throw $e;
            } catch (Throwable $e) {
                $this->logger->error('Error during accessibility check', array_merge($submitContext, [
                    'accessibility_error' => $e->getMessage(),
                    'accessibility_error_class' => $e::class,
                ]));

                throw new RuntimeException('Failed to verify request accessibility: ' . $e->getMessage(), 0, $e);
            }

            // Validate analysis data
            $this->logger->debug('Validating analysis data', $submitContext);

            $requiredFields = ['first_name', 'last_name', 'email'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (empty($analysisData[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $this->logger->error('Missing required fields in analysis data', array_merge($submitContext, [
                    'missing_fields' => $missingFields,
                    'required_fields' => $requiredFields,
                ]));

                throw new InvalidArgumentException('Missing required fields: ' . implode(', ', $missingFields));
            }

            // Validate email format
            if (!filter_var($analysisData['email'], FILTER_VALIDATE_EMAIL)) {
                $this->logger->error('Invalid email format in analysis data', array_merge($submitContext, [
                    'invalid_email' => $analysisData['email'],
                ]));

                throw new InvalidArgumentException('Invalid email format provided');
            }

            $this->logger->debug('Analysis data validation completed successfully', $submitContext);

            // Create individual analysis entity
            $this->logger->debug('Creating IndividualNeedsAnalysis entity', $submitContext);

            try {
                $analysis = new IndividualNeedsAnalysis();
                $analysis->setNeedsAnalysisRequest($request);

                // Populate with data
                $this->logger->debug('Populating individual analysis with provided data', $submitContext);
                $this->populateIndividualAnalysis($analysis, $analysisData);

                $this->logger->debug('Individual analysis entity populated successfully', array_merge($submitContext, [
                    'analysis_full_name' => $analysis->getFirstName() . ' ' . $analysis->getLastName(),
                    'analysis_training_title' => $analysis->getDesiredTrainingTitle(),
                    'analysis_duration_hours' => $analysis->getDesiredDurationHours(),
                    'analysis_status' => $analysis->getStatus(),
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Error creating or populating individual analysis entity', array_merge($submitContext, [
                    'entity_error' => $e->getMessage(),
                    'entity_error_class' => $e::class,
                    'entity_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Failed to create individual analysis entity: ' . $e->getMessage(), 0, $e);
            }

            // Update request status and persist
            $this->logger->debug('Updating request status and persisting to database', $submitContext);

            try {
                $completedAt = new DateTimeImmutable();
                $request->setStatus(NeedsAnalysisRequest::STATUS_COMPLETED)
                    ->setCompletedAt($completedAt)
                ;

                $this->entityManager->persist($analysis);
                $this->entityManager->flush();

                $finalContext = array_merge($submitContext, [
                    'analysis_id' => $analysis->getId(),
                    'new_request_status' => $request->getStatus(),
                    'completed_at' => $completedAt->format('Y-m-d H:i:s'),
                    'operation_successful' => true,
                ]);

                $this->logger->info('Individual needs analysis submitted and persisted successfully', $finalContext);
            } catch (DBALException|ORMException $e) {
                $this->logger->error('Database error while persisting individual analysis', array_merge($submitContext, [
                    'db_error' => $e->getMessage(),
                    'db_error_code' => $e->getCode(),
                    'db_error_class' => $e::class,
                    'db_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Database error while saving individual analysis: ' . $e->getMessage(), 0, $e);
            }

            // Send notification to admin
            $this->logger->debug('Sending completion notification to admin', array_merge($finalContext, [
                'notification_step' => 'starting',
            ]));

            try {
                $notificationSent = $this->emailNotificationService->sendAnalysisCompletedNotification($request);

                $this->logger->info('Admin notification process completed', array_merge($finalContext, [
                    'notification_sent' => $notificationSent,
                    'notification_step' => 'completed',
                ]));
            } catch (Throwable $e) {
                $this->logger->warning('Failed to send admin notification but analysis was saved', array_merge($finalContext, [
                    'notification_error' => $e->getMessage(),
                    'notification_error_class' => $e::class,
                    'notification_step' => 'failed',
                ]));
                // Don't fail the whole operation if notification fails
            }

            return $analysis;
        } catch (InvalidArgumentException|LogicException $e) {
            $this->logger->error('Validation error in submitIndividualAnalysis', array_merge($submitContext, [
                'validation_error' => $e->getMessage(),
                'validation_error_class' => $e::class,
            ]));

            throw $e;
        } catch (RuntimeException $e) {
            // Re-throw runtime exceptions
            throw $e;
        } catch (Throwable $e) {
            $this->logger->critical('Critical error in submitIndividualAnalysis', array_merge($submitContext, [
                'critical_error' => $e->getMessage(),
                'critical_error_class' => $e::class,
                'critical_error_trace' => $e->getTraceAsString(),
            ]));

            throw new RuntimeException('Critical error while submitting individual analysis: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Cancel a needs analysis request.
     */
    public function cancelRequest(NeedsAnalysisRequest $request, ?string $reason = null): void
    {
        $cancelContext = [
            'method' => 'cancelRequest',
            'request_id' => $request->getId(),
            'current_status' => $request->getStatus(),
            'request_type' => $request->getType(),
            'recipient_email' => $request->getRecipientEmail(),
            'has_reason' => !empty($reason),
            'reason_length' => $reason ? strlen($reason) : 0,
        ];

        $this->logger->info('Starting request cancellation', $cancelContext);

        try {
            // Validate current status
            $this->logger->debug('Validating request status for cancellation', $cancelContext);

            if ($request->getStatus() === NeedsAnalysisRequest::STATUS_COMPLETED) {
                $this->logger->warning('Attempted to cancel completed request', array_merge($cancelContext, [
                    'validation_error' => 'Cannot cancel completed request',
                    'completed_at' => $request->getCompletedAt()?->format('Y-m-d H:i:s'),
                ]));

                throw new LogicException('Cannot cancel completed request');
            }

            $this->logger->debug('Status validation passed for cancellation', $cancelContext);

            // Update status and notes
            $this->logger->debug('Updating request status to cancelled', $cancelContext);

            try {
                $oldStatus = $request->getStatus();
                $request->setStatus(NeedsAnalysisRequest::STATUS_CANCELLED);

                if ($reason) {
                    $this->logger->debug('Adding cancellation reason to admin notes', array_merge($cancelContext, [
                        'reason_preview' => substr($reason, 0, 100) . (strlen($reason) > 100 ? '...' : ''),
                    ]));

                    $currentNotes = $request->getAdminNotes();
                    $newNote = 'Annulé: ' . $reason;
                    $updatedNotes = $currentNotes ? $currentNotes . "\n" . $newNote : $newNote;
                    $request->setAdminNotes($updatedNotes);

                    $this->logger->debug('Cancellation reason added to notes', array_merge($cancelContext, [
                        'notes_updated' => true,
                        'new_notes_length' => strlen($updatedNotes),
                    ]));
                }

                // Persist changes
                $this->logger->debug('Persisting cancellation changes to database', $cancelContext);

                $this->entityManager->flush();

                $finalContext = array_merge($cancelContext, [
                    'old_status' => $oldStatus,
                    'new_status' => $request->getStatus(),
                    'operation_successful' => true,
                ]);

                $this->logger->info('Needs analysis request cancelled successfully', $finalContext);
            } catch (DBALException|ORMException $e) {
                $this->logger->error('Database error while cancelling request', array_merge($cancelContext, [
                    'db_error' => $e->getMessage(),
                    'db_error_code' => $e->getCode(),
                    'db_error_class' => $e::class,
                    'db_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Database error while cancelling request: ' . $e->getMessage(), 0, $e);
            }
        } catch (LogicException $e) {
            $this->logger->error('Validation error in cancelRequest', array_merge($cancelContext, [
                'validation_error' => $e->getMessage(),
                'validation_error_class' => $e::class,
            ]));

            throw $e;
        } catch (RuntimeException $e) {
            // Re-throw runtime exceptions
            throw $e;
        } catch (Throwable $e) {
            $this->logger->critical('Critical error in cancelRequest', array_merge($cancelContext, [
                'critical_error' => $e->getMessage(),
                'critical_error_class' => $e::class,
                'critical_error_trace' => $e->getTraceAsString(),
            ]));

            throw new RuntimeException('Critical error while cancelling request: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Mark expired requests as expired.
     */
    public function markExpiredRequests(): int
    {
        $markContext = [
            'method' => 'markExpiredRequests',
            'started_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->logger->info('Starting to mark expired requests', $markContext);

        try {
            $this->logger->debug('Calling repository to mark expired requests', $markContext);

            try {
                $count = $this->needsAnalysisRequestRepository->markExpiredRequests();

                $finalContext = array_merge($markContext, [
                    'expired_count' => $count,
                    'operation_successful' => true,
                    'completed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);

                $this->logger->info('Successfully marked expired requests', $finalContext);

                return $count;
            } catch (DBALException|ORMException $e) {
                $this->logger->error('Database error while marking expired requests', array_merge($markContext, [
                    'db_error' => $e->getMessage(),
                    'db_error_code' => $e->getCode(),
                    'db_error_class' => $e::class,
                    'db_error_trace' => $e->getTraceAsString(),
                ]));

                throw new RuntimeException('Database error while marking expired requests: ' . $e->getMessage(), 0, $e);
            }
        } catch (RuntimeException $e) {
            // Re-throw runtime exceptions
            throw $e;
        } catch (Throwable $e) {
            $this->logger->critical('Critical error in markExpiredRequests', array_merge($markContext, [
                'critical_error' => $e->getMessage(),
                'critical_error_class' => $e::class,
                'critical_error_trace' => $e->getTraceAsString(),
            ]));

            throw new RuntimeException('Critical error while marking expired requests: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStatistics(): array
    {
        $statsContext = [
            'method' => 'getDashboardStatistics',
            'requested_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->logger->debug('Starting dashboard statistics collection', $statsContext);

        try {
            $statistics = [];

            // Get requests statistics
            $this->logger->debug('Fetching requests statistics', $statsContext);

            try {
                $statistics['requests'] = $this->needsAnalysisRequestRepository->getStatistics();
                $this->logger->debug('Requests statistics retrieved', array_merge($statsContext, [
                    'requests_stats_keys' => array_keys($statistics['requests']),
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Error fetching requests statistics', array_merge($statsContext, [
                    'requests_error' => $e->getMessage(),
                    'requests_error_class' => $e::class,
                ]));
                $statistics['requests'] = [];
            }

            // Get company analyses statistics
            $this->logger->debug('Fetching company analyses statistics', $statsContext);

            try {
                $statistics['company_analyses'] = $this->companyNeedsAnalysisRepository->getCompanyStatistics();
                $this->logger->debug('Company analyses statistics retrieved', array_merge($statsContext, [
                    'company_stats_keys' => array_keys($statistics['company_analyses']),
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Error fetching company analyses statistics', array_merge($statsContext, [
                    'company_error' => $e->getMessage(),
                    'company_error_class' => $e::class,
                ]));
                $statistics['company_analyses'] = [];
            }

            // Get individual analyses statistics
            $this->logger->debug('Fetching individual analyses statistics', $statsContext);

            try {
                $statistics['individual_analyses'] = $this->individualNeedsAnalysisRepository->getIndividualStatistics();
                $this->logger->debug('Individual analyses statistics retrieved', array_merge($statsContext, [
                    'individual_stats_keys' => array_keys($statistics['individual_analyses']),
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Error fetching individual analyses statistics', array_merge($statsContext, [
                    'individual_error' => $e->getMessage(),
                    'individual_error_class' => $e::class,
                ]));
                $statistics['individual_analyses'] = [];
            }

            $finalContext = array_merge($statsContext, [
                'statistics_keys' => array_keys($statistics),
                'operation_successful' => true,
                'completed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $this->logger->debug('Dashboard statistics collection completed', $finalContext);

            return $statistics;
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error collecting dashboard statistics', array_merge($statsContext, [
                'unexpected_error' => $e->getMessage(),
                'unexpected_error_class' => $e::class,
                'unexpected_error_trace' => $e->getTraceAsString(),
            ]));

            // Return empty statistics rather than failing
            return [
                'requests' => [],
                'company_analyses' => [],
                'individual_analyses' => [],
            ];
        }
    }

    /**
     * Get requests expiring soon.
     */
    public function getRequestsExpiringSoon(int $days = 7): array
    {
        $expiringContext = [
            'method' => 'getRequestsExpiringSoon',
            'days_threshold' => $days,
            'requested_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->logger->debug('Starting search for requests expiring soon', $expiringContext);

        try {
            // Validate days parameter
            if ($days < 1) {
                $this->logger->warning('Invalid days parameter provided', array_merge($expiringContext, [
                    'validation_error' => 'Days must be positive',
                ]));

                throw new InvalidArgumentException('Days parameter must be positive');
            }

            $this->logger->debug('Calling repository to find expiring requests', $expiringContext);

            try {
                $requests = $this->needsAnalysisRequestRepository->findRequestsExpiringSoon($days);

                $finalContext = array_merge($expiringContext, [
                    'found_count' => count($requests),
                    'operation_successful' => true,
                    'completed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);

                if (count($requests) > 0) {
                    $this->logger->info('Found requests expiring soon', array_merge($finalContext, [
                        'request_ids' => array_map(static fn ($r) => $r->getId(), $requests),
                    ]));
                } else {
                    $this->logger->debug('No requests found expiring soon', $finalContext);
                }

                return $requests;
            } catch (DBALException|ORMException $e) {
                $this->logger->error('Database error while searching for expiring requests', array_merge($expiringContext, [
                    'db_error' => $e->getMessage(),
                    'db_error_code' => $e->getCode(),
                    'db_error_class' => $e::class,
                ]));

                return [];
            }
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Validation error in getRequestsExpiringSoon', array_merge($expiringContext, [
                'validation_error' => $e->getMessage(),
            ]));

            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error searching for expiring requests', array_merge($expiringContext, [
                'unexpected_error' => $e->getMessage(),
                'unexpected_error_class' => $e::class,
            ]));

            return [];
        }
    }

    /**
     * Resend a needs analysis request.
     */
    public function resendRequest(NeedsAnalysisRequest $request): bool
    {
        $resendContext = [
            'method' => 'resendRequest',
            'request_id' => $request->getId(),
            'current_status' => $request->getStatus(),
            'recipient_email' => $request->getRecipientEmail(),
            'original_token' => substr($request->getToken(), 0, 8) . '...',
            'original_expires_at' => $request->getExpiresAt()?->format('Y-m-d H:i:s'),
        ];

        $this->logger->info('Starting request resend process', $resendContext);

        try {
            // Validate status
            $this->logger->debug('Validating request status for resend', $resendContext);

            if ($request->getStatus() !== NeedsAnalysisRequest::STATUS_SENT) {
                $this->logger->warning('Attempted to resend request with invalid status', array_merge($resendContext, [
                    'expected_status' => NeedsAnalysisRequest::STATUS_SENT,
                    'validation_error' => 'Can only resend sent requests',
                ]));

                throw new LogicException('Can only resend sent requests');
            }

            $this->logger->debug('Status validation passed for resend', $resendContext);

            // Generate new token and expiration
            $this->logger->debug('Generating new token and expiration for resend', $resendContext);

            try {
                $tokenData = $this->tokenGeneratorService->generateTokenWithExpiration();

                $this->logger->debug('New token generated for resend', array_merge($resendContext, [
                    'new_token' => substr($tokenData['token'], 0, 8) . '...',
                    'new_expires_at' => $tokenData['expires_at']->format('Y-m-d H:i:s'),
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Failed to generate new token for resend', array_merge($resendContext, [
                    'token_error' => $e->getMessage(),
                    'token_error_class' => $e::class,
                ]));

                throw new RuntimeException('Failed to generate new token for resend: ' . $e->getMessage(), 0, $e);
            }

            // Update request with new token
            $this->logger->debug('Updating request with new token and expiration', $resendContext);

            try {
                $request->setToken($tokenData['token'])
                    ->setExpiresAt($tokenData['expires_at'])
                ;

                $this->entityManager->flush();

                $this->logger->debug('Request updated with new token successfully', array_merge($resendContext, [
                    'token_updated' => true,
                ]));
            } catch (DBALException|ORMException $e) {
                $this->logger->error('Database error while updating request for resend', array_merge($resendContext, [
                    'db_error' => $e->getMessage(),
                    'db_error_code' => $e->getCode(),
                    'db_error_class' => $e::class,
                ]));

                throw new RuntimeException('Database error while updating request for resend: ' . $e->getMessage(), 0, $e);
            }

            // Send email
            $this->logger->debug('Sending resend email notification', $resendContext);

            try {
                $emailSent = $this->emailNotificationService->sendNeedsAnalysisRequest($request);

                $finalContext = array_merge($resendContext, [
                    'email_sent' => $emailSent,
                    'operation_successful' => $emailSent,
                    'completed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);

                if ($emailSent) {
                    $this->logger->info('Request resent successfully', $finalContext);
                } else {
                    $this->logger->warning('Request token updated but email failed to send', $finalContext);
                }

                return $emailSent;
            } catch (Throwable $e) {
                $this->logger->error('Email service failed during resend', array_merge($resendContext, [
                    'email_error' => $e->getMessage(),
                    'email_error_class' => $e::class,
                    'token_was_updated' => true,
                ]));

                return false;
            }
        } catch (LogicException $e) {
            $this->logger->error('Validation error in resendRequest', array_merge($resendContext, [
                'validation_error' => $e->getMessage(),
            ]));

            throw $e;
        } catch (RuntimeException $e) {
            // Re-throw runtime exceptions
            throw $e;
        } catch (Throwable $e) {
            $this->logger->critical('Critical error in resendRequest', array_merge($resendContext, [
                'critical_error' => $e->getMessage(),
                'critical_error_class' => $e::class,
                'critical_error_trace' => $e->getTraceAsString(),
            ]));

            return false;
        }
    }

    /**
     * Populate company analysis with data.
     */
    private function populateCompanyAnalysis(CompanyNeedsAnalysis $analysis, array $data): void
    {
        $populateContext = [
            'method' => 'populateCompanyAnalysis',
            'data_keys' => array_keys($data),
            'data_count' => count($data),
            'company_name' => $data['company_name'] ?? null,
        ];

        $this->logger->debug('Starting company analysis population', $populateContext);

        try {
            $this->logger->debug('Setting company analysis fields', $populateContext);

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
                ->setSpecificNeeds($data['specific_needs'] ?? '')
            ;

            $this->logger->debug('Company analysis populated successfully', array_merge($populateContext, [
                'populated_company_name' => $analysis->getCompanyName(),
                'populated_training_title' => $analysis->getTrainingTitle(),
                'populated_duration_hours' => $analysis->getTrainingDurationHours(),
                'populated_employee_count' => $analysis->getEmployeeCount(),
                'has_trainees_info' => !empty($analysis->getTraineesInfo()),
                'operation_successful' => true,
            ]));
        } catch (Throwable $e) {
            $this->logger->error('Error populating company analysis', array_merge($populateContext, [
                'population_error' => $e->getMessage(),
                'population_error_class' => $e::class,
                'population_error_trace' => $e->getTraceAsString(),
            ]));

            throw new RuntimeException('Failed to populate company analysis: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Populate individual analysis with data.
     */
    private function populateIndividualAnalysis(IndividualNeedsAnalysis $analysis, array $data): void
    {
        $populateContext = [
            'method' => 'populateIndividualAnalysis',
            'data_keys' => array_keys($data),
            'data_count' => count($data),
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
        ];

        $this->logger->debug('Starting individual analysis population', $populateContext);

        try {
            $this->logger->debug('Setting individual analysis fields', $populateContext);

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
                ->setSpecificNeeds($data['specific_needs'] ?? '')
            ;

            $this->logger->debug('Individual analysis populated successfully', array_merge($populateContext, [
                'populated_full_name' => $analysis->getFirstName() . ' ' . $analysis->getLastName(),
                'populated_training_title' => $analysis->getDesiredTrainingTitle(),
                'populated_duration_hours' => $analysis->getDesiredDurationHours(),
                'populated_status' => $analysis->getStatus(),
                'populated_funding_type' => $analysis->getFundingType(),
                'operation_successful' => true,
            ]));
        } catch (Throwable $e) {
            $this->logger->error('Error populating individual analysis', array_merge($populateContext, [
                'population_error' => $e->getMessage(),
                'population_error_class' => $e::class,
                'population_error_trace' => $e->getTraceAsString(),
            ]));

            throw new RuntimeException('Failed to populate individual analysis: ' . $e->getMessage(), 0, $e);
        }
    }
}
