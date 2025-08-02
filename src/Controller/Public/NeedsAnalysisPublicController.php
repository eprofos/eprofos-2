<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\Analysis\NeedsAnalysisRequest;
use App\Form\Analysis\CompanyNeedsAnalysisType;
use App\Form\Analysis\IndividualNeedsAnalysisType;
use App\Service\Analysis\NeedsAnalysisService;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Public controller for needs analysis forms.
 *
 * Handles public access to needs analysis forms via secure tokens.
 * Provides form display and submission for both company and individual analyses.
 */
#[Route('/needs-analysis')]
class NeedsAnalysisPublicController extends AbstractController
{
    public function __construct(
        private readonly NeedsAnalysisService $needsAnalysisService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Display the needs analysis form for a given token.
     */
    #[Route('/form/{token}', name: 'needs_analysis_public_form', methods: ['GET', 'POST'])]
    public function form(string $token, Request $request): Response
    {
        $this->logger->info('Needs analysis form accessed', [
            'token' => $token,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('Referer'),
        ]);

        try {
            // Find the request by token
            $this->logger->debug('Searching for needs analysis request by token', ['token' => $token]);
            $needsAnalysisRequest = $this->needsAnalysisService->findRequestByToken($token);

            if (!$needsAnalysisRequest) {
                $this->logger->warning('Invalid token accessed - request not found', [
                    'token' => $token,
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]);

                throw $this->createNotFoundException('Lien invalide ou expiré.');
            }

            $this->logger->info('Needs analysis request found', [
                'request_id' => $needsAnalysisRequest->getId(),
                'token' => $token,
                'type' => $needsAnalysisRequest->getType(),
                'status' => $needsAnalysisRequest->getStatus(),
                'created_at' => $needsAnalysisRequest->getCreatedAt()?->format('Y-m-d H:i:s'),
                'expires_at' => $needsAnalysisRequest->getExpiresAt()?->format('Y-m-d H:i:s'),
                'is_completed' => $needsAnalysisRequest->isCompleted(),
                'is_expired' => $needsAnalysisRequest->isExpired(),
            ]);

            // Check if already completed first
            if ($needsAnalysisRequest->isCompleted()) {
                $this->logger->info('Request already completed - showing completion page', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'token' => $token,
                    'completed_at' => $needsAnalysisRequest->getCompletedAt()?->format('Y-m-d H:i:s'),
                ]);

                return $this->render('public/needs_analysis/completed.html.twig', [
                    'request' => $needsAnalysisRequest,
                ]);
            }

            // Check if request is accessible
            $isAccessible = $this->needsAnalysisService->isRequestAccessible($needsAnalysisRequest);
            $this->logger->debug('Checking request accessibility', [
                'request_id' => $needsAnalysisRequest->getId(),
                'is_accessible' => $isAccessible,
                'status' => $needsAnalysisRequest->getStatus(),
                'is_expired' => $needsAnalysisRequest->isExpired(),
                'expires_at' => $needsAnalysisRequest->getExpiresAt()?->format('Y-m-d H:i:s'),
            ]);

            if (!$isAccessible) {
                $this->logger->warning('Inaccessible request accessed', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'token' => $token,
                    'status' => $needsAnalysisRequest->getStatus(),
                    'expired' => $needsAnalysisRequest->isExpired(),
                    'expires_at' => $needsAnalysisRequest->getExpiresAt()?->format('Y-m-d H:i:s'),
                    'ip' => $request->getClientIp(),
                ]);

                return $this->render('public/needs_analysis/expired.html.twig', [
                    'request' => $needsAnalysisRequest,
                ]);
            }

            // Route to appropriate form based on type
            $this->logger->info('Routing to appropriate form handler', [
                'request_id' => $needsAnalysisRequest->getId(),
                'type' => $needsAnalysisRequest->getType(),
                'method' => $request->getMethod(),
            ]);

            if ($needsAnalysisRequest->getType() === NeedsAnalysisRequest::TYPE_COMPANY) {
                return $this->handleCompanyForm($needsAnalysisRequest, $request);
            }

            return $this->handleIndividualForm($needsAnalysisRequest, $request);
        } catch (Exception $e) {
            $this->logger->error('Unexpected error in needs analysis form', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $request->getClientIp(),
                'method' => $request->getMethod(),
            ]);

            // Re-throw if it's a not found exception
            if ($e instanceof NotFoundHttpException) {
                throw $e;
            }

            // For other exceptions, show a generic error page
            $this->addFlash('error', 'Une erreur inattendue est survenue. Veuillez réessayer ou contacter le support.');

            return $this->render('public/needs_analysis/error.html.twig', [
                'token' => $token,
                'error_message' => 'Service temporairement indisponible',
            ]);
        }
    }

    /**
     * Display success page after form submission.
     */
    #[Route('/success/{token}', name: 'needs_analysis_public_success', methods: ['GET'])]
    public function success(string $token, Request $request): Response
    {
        $this->logger->info('Success page accessed', [
            'token' => $token,
            'ip' => $request->getClientIp(),
        ]);

        try {
            $this->logger->debug('Looking up needs analysis request for success page', ['token' => $token]);
            $needsAnalysisRequest = $this->needsAnalysisService->findRequestByToken($token);

            if (!$needsAnalysisRequest) {
                $this->logger->warning('Invalid token on success page', [
                    'token' => $token,
                    'ip' => $request->getClientIp(),
                ]);

                throw $this->createNotFoundException('Lien invalide ou expiré.');
            }

            $this->logger->info('Needs analysis request found for success page', [
                'request_id' => $needsAnalysisRequest->getId(),
                'token' => $token,
                'type' => $needsAnalysisRequest->getType(),
                'is_completed' => $needsAnalysisRequest->isCompleted(),
                'has_company_analysis' => $needsAnalysisRequest->getCompanyAnalysis() !== null,
                'has_individual_analysis' => $needsAnalysisRequest->getIndividualAnalysis() !== null,
            ]);

            // Determine the type based on which analysis exists
            $type = $needsAnalysisRequest->getCompanyAnalysis() ? 'company' : 'individual';

            $this->logger->debug('Determined analysis type for success page', [
                'request_id' => $needsAnalysisRequest->getId(),
                'determined_type' => $type,
            ]);

            return $this->render('public/needs_analysis/success.html.twig', [
                'request' => $needsAnalysisRequest,
                'type' => $type,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error displaying success page', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $request->getClientIp(),
            ]);

            // Re-throw if it's a not found exception
            if ($e instanceof NotFoundHttpException) {
                throw $e;
            }

            // For other exceptions, redirect to error page
            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage de la page de confirmation.');

            return $this->render('public/needs_analysis/error.html.twig', [
                'token' => $token,
                'error_message' => 'Erreur d\'affichage de la confirmation',
            ]);
        }
    }

    /**
     * Handle company needs analysis form.
     */
    private function handleCompanyForm(NeedsAnalysisRequest $needsAnalysisRequest, Request $request): Response
    {
        $this->logger->info('Handling company needs analysis form', [
            'request_id' => $needsAnalysisRequest->getId(),
            'token' => $needsAnalysisRequest->getToken(),
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            // Check if analysis already exists
            $existingAnalysis = $needsAnalysisRequest->getCompanyAnalysis();

            $this->logger->debug('Checking for existing company analysis', [
                'request_id' => $needsAnalysisRequest->getId(),
                'has_existing_analysis' => $existingAnalysis !== null,
                'existing_analysis_id' => $existingAnalysis?->getId(),
            ]);

            $this->logger->debug('Creating company form', [
                'request_id' => $needsAnalysisRequest->getId(),
                'form_type' => CompanyNeedsAnalysisType::class,
            ]);

            $form = $this->createForm(CompanyNeedsAnalysisType::class, null, [
                'needs_analysis_request' => $needsAnalysisRequest,
            ]);

            $this->logger->debug('Form created successfully, handling request', [
                'request_id' => $needsAnalysisRequest->getId(),
                'is_post' => $request->isMethod('POST'),
                'has_content' => !empty($request->getContent()),
                'content_type' => $request->headers->get('Content-Type'),
            ]);

            $form->handleRequest($request);

            $this->logger->debug('Form request handled', [
                'request_id' => $needsAnalysisRequest->getId(),
                'is_submitted' => $form->isSubmitted(),
                'is_valid' => $form->isSubmitted() ? $form->isValid() : null,
                'form_name' => $form->getName(),
            ]);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('Company form submitted and valid, processing data', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'token' => $needsAnalysisRequest->getToken(),
                ]);

                try {
                    $analysisData = $form->getData();

                    $this->logger->debug('Form data extracted', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'data_type' => gettype($analysisData),
                        'data_keys' => is_array($analysisData) ? array_keys($analysisData) : 'not_array',
                        'data_size' => is_array($analysisData) ? count($analysisData) : 0,
                    ]);

                    $convertedData = $this->convertFormDataToArray($analysisData);

                    $this->logger->debug('Form data converted to array', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'converted_data_keys' => array_keys($convertedData),
                        'converted_data_size' => count($convertedData),
                    ]);

                    $this->logger->info('Submitting company analysis to service', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'data_fields' => array_keys($convertedData),
                    ]);

                    $this->needsAnalysisService->submitCompanyAnalysis(
                        $needsAnalysisRequest,
                        $convertedData,
                    );

                    $this->logger->info('Company needs analysis submitted successfully', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'token' => $needsAnalysisRequest->getToken(),
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent'),
                        'submission_time' => (new DateTime())->format('Y-m-d H:i:s'),
                    ]);

                    return $this->redirectToRoute('needs_analysis_public_success', [
                        'token' => $needsAnalysisRequest->getToken(),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to submit company analysis to service', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'token' => $needsAnalysisRequest->getToken(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'ip' => $request->getClientIp(),
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi du formulaire. Veuillez réessayer.');
                }
            } elseif ($form->isSubmitted()) {
                // Log validation errors for debugging
                $this->logger->warning('Company form validation failed', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'token' => $needsAnalysisRequest->getToken(),
                    'errors' => (string) $form->getErrors(true),
                    'form_data_keys' => array_keys($request->request->all()),
                    'form_data_size' => count($request->request->all()),
                    'ip' => $request->getClientIp(),
                ]);

                // Log each field error individually for better debugging
                foreach ($form->all() as $fieldName => $field) {
                    if ($field->getErrors()->count() > 0) {
                        $this->logger->debug('Field validation error', [
                            'request_id' => $needsAnalysisRequest->getId(),
                            'field_name' => $fieldName,
                            'field_errors' => (string) $field->getErrors(true),
                            'field_data' => $field->getData(),
                        ]);
                    }
                }
            } else {
                $this->logger->debug('Company form not submitted, rendering form', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'method' => $request->getMethod(),
                ]);
            }

            return $this->render('public/needs_analysis/company_form.html.twig', [
                'form' => $form,
                'request' => $needsAnalysisRequest,
                'existing_analysis' => $existingAnalysis,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Unexpected error in company form handler', [
                'request_id' => $needsAnalysisRequest->getId(),
                'token' => $needsAnalysisRequest->getToken(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $request->getClientIp(),
                'method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue est survenue. Veuillez réessayer.');

            return $this->render('public/needs_analysis/error.html.twig', [
                'token' => $needsAnalysisRequest->getToken(),
                'error_message' => 'Erreur lors du traitement du formulaire entreprise',
            ]);
        }
    }

    /**
     * Handle individual needs analysis form.
     */
    private function handleIndividualForm(NeedsAnalysisRequest $needsAnalysisRequest, Request $request): Response
    {
        $this->logger->info('Handling individual needs analysis form', [
            'request_id' => $needsAnalysisRequest->getId(),
            'token' => $needsAnalysisRequest->getToken(),
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            // Check if analysis already exists
            $existingAnalysis = $needsAnalysisRequest->getIndividualAnalysis();

            $this->logger->debug('Checking for existing individual analysis', [
                'request_id' => $needsAnalysisRequest->getId(),
                'has_existing_analysis' => $existingAnalysis !== null,
                'existing_analysis_id' => $existingAnalysis?->getId(),
            ]);

            $this->logger->debug('Creating individual form', [
                'request_id' => $needsAnalysisRequest->getId(),
                'form_type' => IndividualNeedsAnalysisType::class,
            ]);

            $form = $this->createForm(IndividualNeedsAnalysisType::class, null, [
                'needs_analysis_request' => $needsAnalysisRequest,
            ]);

            $this->logger->debug('Form created successfully, handling request', [
                'request_id' => $needsAnalysisRequest->getId(),
                'is_post' => $request->isMethod('POST'),
                'has_content' => !empty($request->getContent()),
                'content_type' => $request->headers->get('Content-Type'),
            ]);

            $form->handleRequest($request);

            $this->logger->debug('Form request handled', [
                'request_id' => $needsAnalysisRequest->getId(),
                'is_submitted' => $form->isSubmitted(),
                'is_valid' => $form->isSubmitted() ? $form->isValid() : null,
                'form_name' => $form->getName(),
            ]);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('Individual form submitted and valid, processing data', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'token' => $needsAnalysisRequest->getToken(),
                ]);

                try {
                    $analysisData = $form->getData();

                    $this->logger->debug('Form data extracted', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'data_type' => gettype($analysisData),
                        'data_keys' => is_array($analysisData) ? array_keys($analysisData) : 'not_array',
                        'data_size' => is_array($analysisData) ? count($analysisData) : 0,
                    ]);

                    $convertedData = $this->convertFormDataToArray($analysisData);

                    $this->logger->debug('Form data converted to array', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'converted_data_keys' => array_keys($convertedData),
                        'converted_data_size' => count($convertedData),
                    ]);

                    $this->logger->info('Submitting individual analysis to service', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'data_fields' => array_keys($convertedData),
                    ]);

                    $this->needsAnalysisService->submitIndividualAnalysis(
                        $needsAnalysisRequest,
                        $convertedData,
                    );

                    $this->logger->info('Individual needs analysis submitted successfully', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'token' => $needsAnalysisRequest->getToken(),
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent'),
                        'submission_time' => (new DateTime())->format('Y-m-d H:i:s'),
                    ]);

                    return $this->redirectToRoute('needs_analysis_public_success', [
                        'token' => $needsAnalysisRequest->getToken(),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to submit individual analysis to service', [
                        'request_id' => $needsAnalysisRequest->getId(),
                        'token' => $needsAnalysisRequest->getToken(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'ip' => $request->getClientIp(),
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi du formulaire. Veuillez réessayer.');
                }
            } elseif ($form->isSubmitted()) {
                // Log validation errors for debugging
                $this->logger->warning('Individual form validation failed', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'token' => $needsAnalysisRequest->getToken(),
                    'errors' => (string) $form->getErrors(true),
                    'form_data_keys' => array_keys($request->request->all()),
                    'form_data_size' => count($request->request->all()),
                    'ip' => $request->getClientIp(),
                ]);

                // Log each field error individually for better debugging
                foreach ($form->all() as $fieldName => $field) {
                    if ($field->getErrors()->count() > 0) {
                        $this->logger->debug('Field validation error', [
                            'request_id' => $needsAnalysisRequest->getId(),
                            'field_name' => $fieldName,
                            'field_errors' => (string) $field->getErrors(true),
                            'field_data' => $field->getData(),
                        ]);
                    }
                }
            } else {
                $this->logger->debug('Individual form not submitted, rendering form', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'method' => $request->getMethod(),
                ]);
            }

            return $this->render('public/needs_analysis/individual_form.html.twig', [
                'form' => $form,
                'request' => $needsAnalysisRequest,
                'existing_analysis' => $existingAnalysis,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Unexpected error in individual form handler', [
                'request_id' => $needsAnalysisRequest->getId(),
                'token' => $needsAnalysisRequest->getToken(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $request->getClientIp(),
                'method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue est survenue. Veuillez réessayer.');

            return $this->render('public/needs_analysis/error.html.twig', [
                'token' => $needsAnalysisRequest->getToken(),
                'error_message' => 'Erreur lors du traitement du formulaire individuel',
            ]);
        }
    }

    /**
     * Convert form data to array for service consumption.
     *
     * @param mixed $formData
     */
    private function convertFormDataToArray($formData): array
    {
        $this->logger->debug('Converting form data to array', [
            'data_type' => gettype($formData),
            'is_array' => is_array($formData),
            'is_object' => is_object($formData),
            'class_name' => is_object($formData) ? get_class($formData) : null,
        ]);

        try {
            // The form data comes as an array from the form submission
            if (is_array($formData)) {
                $this->logger->debug('Form data is already an array', [
                    'array_keys' => array_keys($formData),
                    'array_size' => count($formData),
                ]);

                // Log sensitive information carefully (avoid logging actual values)
                $sanitizedData = [];
                foreach ($formData as $key => $value) {
                    $sanitizedData[$key] = [
                        'type' => gettype($value),
                        'length' => is_string($value) ? strlen($value) : (is_array($value) ? count($value) : 'n/a'),
                        'is_empty' => empty($value),
                    ];
                }

                $this->logger->debug('Form data structure analysis', [
                    'fields' => $sanitizedData,
                ]);

                return $formData;
            }

            // Handle object form data (if needed)
            if (is_object($formData)) {
                $this->logger->debug('Form data is an object, attempting to convert', [
                    'class_name' => get_class($formData),
                ]);

                // If it's a form object or similar, we might need to extract data differently
                if (method_exists($formData, 'toArray')) {
                    $convertedData = $formData->toArray();
                    $this->logger->debug('Converted object to array using toArray method', [
                        'converted_keys' => array_keys($convertedData),
                    ]);

                    return $convertedData;
                }

                // Try to convert object to array using get_object_vars
                $convertedData = get_object_vars($formData);
                $this->logger->debug('Converted object to array using get_object_vars', [
                    'converted_keys' => array_keys($convertedData),
                ]);

                return $convertedData;
            }

            // Handle scalar values
            if (is_scalar($formData)) {
                $this->logger->warning('Form data is scalar, wrapping in array', [
                    'scalar_type' => gettype($formData),
                    'scalar_value_length' => is_string($formData) ? strlen($formData) : 'n/a',
                ]);

                return ['value' => $formData];
            }

            // If for some reason it's not an array, return empty array
            $this->logger->warning('Form data is neither array nor object, returning empty array', [
                'data_type' => gettype($formData),
                'data_value' => var_export($formData, true),
            ]);

            return [];
        } catch (Exception $e) {
            $this->logger->error('Error converting form data to array', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data_type' => gettype($formData),
            ]);

            // Return empty array on error to prevent further issues
            return [];
        }
    }
}
