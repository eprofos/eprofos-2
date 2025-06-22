<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\NeedsAnalysisRequest;
use App\Form\CompanyNeedsAnalysisType;
use App\Form\IndividualNeedsAnalysisType;
use App\Service\NeedsAnalysisService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Public controller for needs analysis forms
 * 
 * Handles public access to needs analysis forms via secure tokens.
 * Provides form display and submission for both company and individual analyses.
 */
#[Route('/needs-analysis', name: 'needs_analysis_public_')]
class NeedsAnalysisPublicController extends AbstractController
{
    public function __construct(
        private readonly NeedsAnalysisService $needsAnalysisService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Display the needs analysis form for a given token
     */
    #[Route('/form/{token}', name: 'form', methods: ['GET', 'POST'])]
    public function form(string $token, Request $request): Response
    {
        // Find the request by token
        $needsAnalysisRequest = $this->needsAnalysisService->findRequestByToken($token);
        
        if (!$needsAnalysisRequest) {
            $this->logger->warning('Invalid token accessed', ['token' => $token]);
            throw $this->createNotFoundException('Lien invalide ou expiré.');
        }

        // Check if request is accessible
        if (!$this->needsAnalysisService->isRequestAccessible($needsAnalysisRequest)) {
            $this->logger->warning('Inaccessible request accessed', [
                'token' => $token,
                'status' => $needsAnalysisRequest->getStatus(),
                'expired' => $needsAnalysisRequest->isExpired()
            ]);
            
            return $this->render('public/needs_analysis/expired.html.twig', [
                'request' => $needsAnalysisRequest,
            ]);
        }

        // Check if already completed
        if ($needsAnalysisRequest->isCompleted()) {
            return $this->render('public/needs_analysis/completed.html.twig', [
                'request' => $needsAnalysisRequest,
            ]);
        }

        // Route to appropriate form based on type
        if ($needsAnalysisRequest->getType() === NeedsAnalysisRequest::TYPE_COMPANY) {
            return $this->handleCompanyForm($needsAnalysisRequest, $request);
        } else {
            return $this->handleIndividualForm($needsAnalysisRequest, $request);
        }
    }

    /**
     * Handle company needs analysis form
     */
    private function handleCompanyForm(NeedsAnalysisRequest $needsAnalysisRequest, Request $request): Response
    {
        // Check if analysis already exists
        $existingAnalysis = $needsAnalysisRequest->getCompanyAnalysis();
        
        $form = $this->createForm(CompanyNeedsAnalysisType::class, null, [
            'needs_analysis_request' => $needsAnalysisRequest,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $analysisData = $form->getData();
                
                $this->needsAnalysisService->submitCompanyAnalysis(
                    $needsAnalysisRequest,
                    $this->convertFormDataToArray($analysisData)
                );

                $this->logger->info('Company needs analysis submitted', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'token' => $needsAnalysisRequest->getToken(),
                ]);

                return $this->render('public/needs_analysis/success.html.twig', [
                    'request' => $needsAnalysisRequest,
                    'type' => 'company',
                ]);
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to submit company analysis', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'error' => $e->getMessage(),
                ]);
                
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi du formulaire. Veuillez réessayer.');
            }
        }

        return $this->render('public/needs_analysis/company_form.html.twig', [
            'form' => $form,
            'request' => $needsAnalysisRequest,
            'existing_analysis' => $existingAnalysis,
        ]);
    }

    /**
     * Handle individual needs analysis form
     */
    private function handleIndividualForm(NeedsAnalysisRequest $needsAnalysisRequest, Request $request): Response
    {
        // Check if analysis already exists
        $existingAnalysis = $needsAnalysisRequest->getIndividualAnalysis();
        
        $form = $this->createForm(IndividualNeedsAnalysisType::class, null, [
            'needs_analysis_request' => $needsAnalysisRequest,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $analysisData = $form->getData();
                
                $this->needsAnalysisService->submitIndividualAnalysis(
                    $needsAnalysisRequest,
                    $this->convertFormDataToArray($analysisData)
                );

                $this->logger->info('Individual needs analysis submitted', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'token' => $needsAnalysisRequest->getToken(),
                ]);

                return $this->render('public/needs_analysis/success.html.twig', [
                    'request' => $needsAnalysisRequest,
                    'type' => 'individual',
                ]);
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to submit individual analysis', [
                    'request_id' => $needsAnalysisRequest->getId(),
                    'error' => $e->getMessage(),
                ]);
                
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi du formulaire. Veuillez réessayer.');
            }
        }

        return $this->render('public/needs_analysis/individual_form.html.twig', [
            'form' => $form,
            'request' => $needsAnalysisRequest,
            'existing_analysis' => $existingAnalysis,
        ]);
    }

    /**
     * Convert form data to array for service consumption
     */
    private function convertFormDataToArray($formData): array
    {
        // This method will convert the form data object to an array
        // The exact implementation depends on how the form types are structured
        // For now, we'll assume the form data is already in the correct format
        if (is_array($formData)) {
            return $formData;
        }
        
        // If it's an object, we might need to extract properties
        // This will be implemented when we create the form types
        return [];
    }

    /**
     * Display information about the analysis request (before form)
     */
    #[Route('/info/{token}', name: 'info', methods: ['GET'])]
    public function info(string $token): Response
    {
        $needsAnalysisRequest = $this->needsAnalysisService->findRequestByToken($token);
        
        if (!$needsAnalysisRequest) {
            throw $this->createNotFoundException('Lien invalide ou expiré.');
        }

        if (!$this->needsAnalysisService->isRequestAccessible($needsAnalysisRequest)) {
            return $this->render('public/needs_analysis/expired.html.twig', [
                'request' => $needsAnalysisRequest,
            ]);
        }

        return $this->render('public/needs_analysis/info.html.twig', [
            'request' => $needsAnalysisRequest,
        ]);
    }
}