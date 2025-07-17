<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\LegalDocumentRepository;
use App\Repository\SessionRegistrationRepository;
use App\Entity\SessionRegistration;
use ZipArchive;

/**
 * Legal controller for legal pages
 * 
 * Handles the display of legal information including
 * terms of service, privacy policy, and legal notices.
 */
class LegalController extends AbstractController
{
    public function __construct(
        private LegalDocumentRepository $legalDocumentRepository,
        private SessionRegistrationRepository $sessionRegistrationRepository
    ) {
    }
    /**
     * Display legal notices
     */
    #[Route('/mentions-legales', name: 'app_legal_notices', methods: ['GET'])]
    public function notices(): Response
    {
        $legalInfo = [
            'company_name' => 'EPROFOS',
            'legal_form' => 'SARL',
            'capital' => '50 000 €',
            'siret' => '123 456 789 00012',
            'rcs' => 'RCS Paris B 123 456 789',
            'vat_number' => 'FR12 123456789',
            'address' => [
                'street' => '123 Avenue de la Formation',
                'postal_code' => '75001',
                'city' => 'Paris',
                'country' => 'France',
            ],
            'phone' => '+33 1 23 45 67 89',
            'email' => 'contact@eprofos.fr',
            'director' => 'Marie Dubois',
            'hosting' => [
                'provider' => 'OVH',
                'address' => '2 rue Kellermann, 59100 Roubaix, France',
            ],
        ];

        return $this->render('public/legal/notices.html.twig', [
            'legal_info' => $legalInfo,
        ]);
    }

    /**
     * Display privacy policy
     */
    #[Route('/politique-de-confidentialite', name: 'app_legal_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('public/legal/privacy.html.twig');
    }

    /**
     * Display terms of service
     */
    #[Route('/conditions-generales', name: 'app_legal_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('public/legal/terms.html.twig');
    }

    /**
     * Display cookies policy
     */
    #[Route('/politique-cookies', name: 'app_legal_cookies', methods: ['GET'])]
    public function cookies(): Response
    {
        return $this->render('public/legal/cookies.html.twig');
    }

    /**
     * Display student information and legal documents required by Qualiopi
     */
    #[Route('/informations-stagiaires', name: 'app_legal_student_information', methods: ['GET'])]
    public function studentInformation(): Response
    {
        // Get published legal documents by type
        $internalRegulation = $this->legalDocumentRepository->findLatestPublishedByType('internal_regulation');
        $studentHandbook = $this->legalDocumentRepository->findLatestPublishedByType('student_handbook');
        $trainingTerms = $this->legalDocumentRepository->findLatestPublishedByType('training_terms');

        return $this->render('public/legal/student_information.html.twig', [
            'internal_regulation' => $internalRegulation,
            'student_handbook' => $studentHandbook,
            'training_terms' => $trainingTerms,
        ]);
    }

    /**
     * Display internal regulation document
     */
    #[Route('/reglement-interieur', name: 'app_legal_internal_regulation', methods: ['GET'])]
    public function internalRegulation(): Response
    {
        $document = $this->legalDocumentRepository->findLatestPublishedByType('internal_regulation');
        
        if (!$document) {
            throw new NotFoundHttpException('Règlement intérieur non disponible');
        }

        return $this->render('public/legal/document_display.html.twig', [
            'document' => $document,
            'document_type' => 'internal_regulation',
            'page_title' => 'Règlement intérieur',
        ]);
    }

    /**
     * Display student handbook document
     */
    #[Route('/livret-accueil-stagiaire', name: 'app_legal_student_handbook', methods: ['GET'])]
    public function studentHandbook(): Response
    {
        $document = $this->legalDocumentRepository->findLatestPublishedByType('student_handbook');
        
        if (!$document) {
            throw new NotFoundHttpException('Livret d\'accueil stagiaire non disponible');
        }

        return $this->render('public/legal/document_display.html.twig', [
            'document' => $document,
            'document_type' => 'student_handbook',
            'page_title' => 'Livret d\'accueil stagiaire',
        ]);
    }

    /**
     * Display training terms document
     */
    #[Route('/conditions-generales-formation', name: 'app_legal_training_terms', methods: ['GET'])]
    public function trainingTerms(): Response
    {
        $document = $this->legalDocumentRepository->findLatestPublishedByType('training_terms');
        
        if (!$document) {
            throw new NotFoundHttpException('Conditions générales de formation non disponibles');
        }

        return $this->render('public/legal/document_display.html.twig', [
            'document' => $document,
            'document_type' => 'training_terms',
            'page_title' => 'Conditions générales de formation',
        ]);
    }

    /**
     * Display accessibility policy document
     */
    #[Route('/politique-accessibilite', name: 'app_legal_accessibility_policy', methods: ['GET'])]
    public function accessibilityPolicy(): Response
    {
        $document = $this->legalDocumentRepository->findLatestPublishedByType('accessibility_policy');
        
        if (!$document) {
            throw new NotFoundHttpException('Politique d\'accessibilité non disponible');
        }

        return $this->render('public/legal/document_display.html.twig', [
            'document' => $document,
            'document_type' => 'accessibility_policy',
            'page_title' => 'Politique d\'accessibilité',
        ]);
    }

    /**
     * Display accessibility procedures document
     */
    #[Route('/procedures-accessibilite', name: 'app_legal_accessibility_procedures', methods: ['GET'])]
    public function accessibilityProcedures(): Response
    {
        $document = $this->legalDocumentRepository->findLatestPublishedByType('accessibility_procedures');
        
        if (!$document) {
            throw new NotFoundHttpException('Procédures d\'accessibilité non disponibles');
        }

        return $this->render('public/legal/document_display.html.twig', [
            'document' => $document,
            'document_type' => 'accessibility_procedures',
            'page_title' => 'Procédures d\'accessibilité',
        ]);
    }

    /**
     * Display accessibility FAQ document
     */
    #[Route('/faq-accessibilite', name: 'app_legal_accessibility_faq', methods: ['GET'])]
    public function accessibilityFaq(): Response
    {
        $document = $this->legalDocumentRepository->findLatestPublishedByType('accessibility_faq');
        
        if (!$document) {
            throw new NotFoundHttpException('FAQ Accessibilité non disponible');
        }

        return $this->render('public/legal/document_display.html.twig', [
            'document' => $document,
            'document_type' => 'accessibility_faq',
            'page_title' => 'FAQ Accessibilité',
        ]);
    }

    /**
     * Display accessibility information and disability accommodation procedures
     */
    #[Route('/accessibilite-handicap', name: 'app_legal_accessibility', methods: ['GET'])]
    public function accessibility(): Response
    {
        // Get published accessibility documents
        $accessibilityPolicy = $this->legalDocumentRepository->findLatestPublishedByType('accessibility_policy');
        $accessibilityProcedures = $this->legalDocumentRepository->findLatestPublishedByType('accessibility_procedures');
        $accessibilityFaq = $this->legalDocumentRepository->findLatestPublishedByType('accessibility_faq');

        return $this->render('public/legal/accessibility.html.twig', [
            'accessibility_policy' => $accessibilityPolicy,
            'accessibility_procedures' => $accessibilityProcedures,
            'accessibility_faq' => $accessibilityFaq,
        ]);
    }

    /**
     * Display documents download page
     */
    #[Route('/documents-telechargement', name: 'app_legal_documents_download', methods: ['GET'])]
    public function documentsDownload(): Response
    {
        // Get all published documents
        $publishedDocuments = $this->legalDocumentRepository->findAllPublished();

        return $this->render('public/legal/documents_download.html.twig', [
            'published_documents' => $publishedDocuments,
        ]);
    }

    /**
     * Download all documents as ZIP archive
     */
    #[Route('/documents-telechargement/tout', name: 'app_legal_download_all', methods: ['GET'])]
    public function downloadAll(): Response
    {
        $publishedDocuments = $this->legalDocumentRepository->findAllPublished();
        
        if (empty($publishedDocuments)) {
            throw new NotFoundHttpException('Aucun document disponible');
        }

        // Create temporary ZIP file
        $zipPath = sys_get_temp_dir() . '/eprofos_documents_' . uniqid() . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            throw new \RuntimeException('Impossible de créer l\'archive ZIP');
        }

        // Add each document to ZIP
        foreach ($publishedDocuments as $document) {
            if ($document->hasFile() && file_exists($document->getAbsoluteFilePath())) {
                $filename = sprintf('%s_v%s.pdf', 
                    str_replace(' ', '_', $document->getTitle()), 
                    $document->getVersion()
                );
                $zip->addFile($document->getAbsoluteFilePath(), $filename);
            }
        }

        $zip->close();

        // Create response
        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'EPROFOS_Documents_Legaux.zip'
        );

        // Delete temporary file after sending
        $response->deleteFileAfterSend();

        return $response;
    }

    /**
     * View a specific document by type
     */
    #[Route('/documents/{type}', name: 'app_legal_document_view', methods: ['GET'])]
    public function documentView(string $type): Response
    {
        $document = $this->legalDocumentRepository->findLatestPublishedByType($type);
        
        if (!$document) {
            throw new NotFoundHttpException('Document non trouvé');
        }

        return $this->render('public/legal/document_view.html.twig', [
            'document' => $document,
        ]);
    }

    /**
     * Handle document acknowledgment from email links
     */
    #[Route('/documents/accuse-reception/{token}', name: 'app_legal_document_acknowledgment', methods: ['GET', 'POST'])]
    public function documentAcknowledgment(string $token, Request $request): Response
    {
        // Find session registration by token
        $sessionRegistration = $this->sessionRegistrationRepository->findOneBy([
            'documentAcknowledgmentToken' => $token
        ]);

        if (!$sessionRegistration) {
            throw new NotFoundHttpException('Token invalide ou expiré');
        }

        // Check if already acknowledged
        if ($sessionRegistration->getDocumentsAcknowledgedAt()) {
            return $this->render('public/legal/document_acknowledgment.html.twig', [
                'session_registration' => $sessionRegistration,
                'already_acknowledged' => true,
            ]);
        }

        // Handle POST request (acknowledgment confirmation)
        if ($request->isMethod('POST')) {
            $sessionRegistration->setDocumentsAcknowledgedAt(new \DateTime());
            $this->sessionRegistrationRepository->save($sessionRegistration, true);

            return $this->render('public/legal/document_acknowledgment.html.twig', [
                'session_registration' => $sessionRegistration,
                'acknowledgment_confirmed' => true,
            ]);
        }

        // Display acknowledgment form
        return $this->render('public/legal/document_acknowledgment.html.twig', [
            'session_registration' => $sessionRegistration,
        ]);
    }
}