<?php

namespace App\Controller\Admin;

use App\Entity\Student\Certificate;
use App\Repository\Student\CertificateRepository;
use App\Service\Student\CertificateService;
use App\Service\Student\CertificatePDFService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class CertificateController extends AbstractController
{
    public function __construct(
        private CertificateRepository $certificateRepository,
        private CertificateService $certificateService,
        private CertificatePDFService $certificatePDFService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/certificates', name: 'admin_certificate_index', methods: ['GET'])]
    public function index(): Response
    {
        $certificates = $this->certificateRepository->findBy([], ['issuedAt' => 'DESC']);

        return $this->render('admin/certificate/index.html.twig', [
            'certificates' => $certificates,
        ]);
    }

    #[Route('/certificates/{id}', name: 'admin_certificate_show', methods: ['GET'])]
    public function show(Certificate $certificate): Response
    {
        return $this->render('admin/certificate/show.html.twig', [
            'certificate' => $certificate,
        ]);
    }
    
    #[Route('/certificates/{id}/download', name: 'admin_certificate_download_pdf', methods: ['GET'])]
    public function downloadPdf(Certificate $certificate): Response
    {
        $pdfContent = $this->certificatePDFService->generateCertificatePDF($certificate);
        
        $filename = sprintf(
            'certificate_%s_%s.pdf',
            $certificate->getStudent()->getLastName(),
            $certificate->getFormation()->getSlug()
        );
        
        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
    
    #[Route('/certificates/{id}/resend', name: 'admin_certificate_resend', methods: ['POST'])]
    public function resend(Certificate $certificate): JsonResponse
    {
        try {
            $this->certificateService->sendCertificateEmail($certificate);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Certificate email sent successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to send certificate email: ' . $e->getMessage()
            ], 400);
        }
    }
    
    #[Route('/certificates/{id}/regenerate', name: 'admin_certificate_regenerate', methods: ['POST'])]
    public function regenerate(Certificate $certificate): JsonResponse
    {
        try {
            $newCertificate = $this->certificateService->regenerateCertificate($certificate);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Certificate regenerated successfully',
                'newCode' => $newCertificate->getVerificationCode()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to regenerate certificate: ' . $e->getMessage()
            ], 400);
        }
    }
    
    #[Route('/certificates/{id}', name: 'admin_certificate_delete', methods: ['DELETE'])]
    public function delete(Certificate $certificate): JsonResponse
    {
        try {
            $this->entityManager->remove($certificate);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Certificate deleted successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to delete certificate: ' . $e->getMessage()
            ], 400);
        }
    }
    
    #[Route('/certificates/generate-missing', name: 'admin_certificate_generate_missing', methods: ['POST'])]
    public function generateMissing(): JsonResponse
    {
        try {
            $count = $this->certificateService->generateMissingCertificates();
            
            return new JsonResponse([
                'success' => true,
                'message' => sprintf('Generated %d certificates', $count),
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to generate certificates: ' . $e->getMessage()
            ], 400);
        }
    }
}
