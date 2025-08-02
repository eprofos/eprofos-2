<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Student\Certificate;
use App\Repository\Student\CertificateRepository;
use App\Service\Student\CertificatePDFService;
use App\Service\Student\CertificateService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
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
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Route('/certificates', name: 'admin_certificate_index', methods: ['GET'])]
    public function index(): Response
    {
        try {
            $this->logger->info('Admin accessing certificate index page', [
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_index',
            ]);

            $certificates = $this->certificateRepository->findBy([], ['issuedAt' => 'DESC']);

            $this->logger->info('Certificate index loaded successfully', [
                'certificate_count' => count($certificates),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
            ]);

            return $this->render('admin/certificate/index.html.twig', [
                'certificates' => $certificates,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading certificate index', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_index',
            ]);

            $this->addFlash('error', 'An error occurred while loading certificates. Please try again.');

            return $this->render('admin/certificate/index.html.twig', [
                'certificates' => [],
            ]);
        }
    }

    #[Route('/certificates/{id}', name: 'admin_certificate_show', methods: ['GET'])]
    public function show(Certificate $certificate): Response
    {
        try {
            $this->logger->info('Admin viewing certificate details', [
                'certificate_id' => $certificate->getId(),
                'student_id' => $certificate->getStudent()?->getId(),
                'formation_id' => $certificate->getFormation()?->getId(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_show',
            ]);

            return $this->render('admin/certificate/show.html.twig', [
                'certificate' => $certificate,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading certificate details', [
                'certificate_id' => $certificate->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_show',
            ]);

            $this->addFlash('error', 'An error occurred while loading certificate details. Please try again.');

            return $this->redirectToRoute('admin_certificate_index');
        }
    }

    #[Route('/certificates/{id}/download', name: 'admin_certificate_download_pdf', methods: ['GET'])]
    public function downloadPdf(Certificate $certificate): Response
    {
        try {
            $this->logger->info('Admin downloading certificate PDF', [
                'certificate_id' => $certificate->getId(),
                'student_id' => $certificate->getStudent()?->getId(),
                'formation_id' => $certificate->getFormation()?->getId(),
                'verification_code' => $certificate->getVerificationCode(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_download_pdf',
            ]);

            $pdfContent = $this->certificatePDFService->generateCertificatePDF($certificate);

            $filename = sprintf(
                'certificate_%s_%s.pdf',
                $certificate->getStudent()->getLastName(),
                $certificate->getFormation()->getSlug(),
            );

            $this->logger->info('Certificate PDF generated and downloaded successfully', [
                'certificate_id' => $certificate->getId(),
                'filename' => $filename,
                'pdf_size' => strlen($pdfContent),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new Response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error generating or downloading certificate PDF', [
                'certificate_id' => $certificate->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_download_pdf',
            ]);

            $this->addFlash('error', 'An error occurred while generating the PDF. Please try again.');

            return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
        }
    }

    #[Route('/certificates/{id}/resend', name: 'admin_certificate_resend', methods: ['POST'])]
    public function resend(Certificate $certificate): JsonResponse
    {
        try {
            $this->logger->info('Admin attempting to resend certificate email', [
                'certificate_id' => $certificate->getId(),
                'student_id' => $certificate->getStudent()?->getId(),
                'student_email' => $certificate->getStudent()?->getEmail(),
                'formation_id' => $certificate->getFormation()?->getId(),
                'verification_code' => $certificate->getVerificationCode(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_resend',
            ]);

            $this->certificateService->sendCertificateEmail($certificate);

            $this->logger->info('Certificate email resent successfully', [
                'certificate_id' => $certificate->getId(),
                'student_email' => $certificate->getStudent()?->getEmail(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Certificate email sent successfully',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error resending certificate email', [
                'certificate_id' => $certificate->getId(),
                'student_id' => $certificate->getStudent()?->getId(),
                'student_email' => $certificate->getStudent()?->getEmail(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_resend',
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to send certificate email: ' . $e->getMessage(),
            ], 400);
        }
    }

    #[Route('/certificates/{id}/regenerate', name: 'admin_certificate_regenerate', methods: ['POST'])]
    public function regenerate(Certificate $certificate): JsonResponse
    {
        try {
            $oldVerificationCode = $certificate->getVerificationCode();

            $this->logger->info('Admin attempting to regenerate certificate', [
                'certificate_id' => $certificate->getId(),
                'old_verification_code' => $oldVerificationCode,
                'student_id' => $certificate->getStudent()?->getId(),
                'formation_id' => $certificate->getFormation()?->getId(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_regenerate',
            ]);

            $newCertificate = $this->certificateService->regenerateCertificate($certificate);

            $this->logger->info('Certificate regenerated successfully', [
                'old_certificate_id' => $certificate->getId(),
                'new_certificate_id' => $newCertificate->getId(),
                'old_verification_code' => $oldVerificationCode,
                'new_verification_code' => $newCertificate->getVerificationCode(),
                'student_id' => $newCertificate->getStudent()?->getId(),
                'formation_id' => $newCertificate->getFormation()?->getId(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Certificate regenerated successfully',
                'newCode' => $newCertificate->getVerificationCode(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error regenerating certificate', [
                'certificate_id' => $certificate->getId(),
                'verification_code' => $certificate->getVerificationCode(),
                'student_id' => $certificate->getStudent()?->getId(),
                'formation_id' => $certificate->getFormation()?->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_regenerate',
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to regenerate certificate: ' . $e->getMessage(),
            ], 400);
        }
    }

    #[Route('/certificates/{id}', name: 'admin_certificate_delete', methods: ['DELETE'])]
    public function delete(Certificate $certificate): JsonResponse
    {
        try {
            $certificateData = [
                'id' => $certificate->getId(),
                'verification_code' => $certificate->getVerificationCode(),
                'student_id' => $certificate->getStudent()?->getId(),
                'student_email' => $certificate->getStudent()?->getEmail(),
                'formation_id' => $certificate->getFormation()?->getId(),
                'formation_title' => $certificate->getFormation()?->getTitle(),
                'issued_at' => $certificate->getIssuedAt()?->format('Y-m-d H:i:s'),
            ];

            $this->logger->info('Admin attempting to delete certificate', [
                'certificate_data' => $certificateData,
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_delete',
            ]);

            $this->entityManager->remove($certificate);
            $this->entityManager->flush();

            $this->logger->warning('Certificate deleted successfully', [
                'deleted_certificate_data' => $certificateData,
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'deletion_timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Certificate deleted successfully',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error deleting certificate', [
                'certificate_id' => $certificate->getId(),
                'verification_code' => $certificate->getVerificationCode(),
                'student_id' => $certificate->getStudent()?->getId(),
                'formation_id' => $certificate->getFormation()?->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_delete',
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to delete certificate: ' . $e->getMessage(),
            ], 400);
        }
    }

    #[Route('/certificates/generate-missing', name: 'admin_certificate_generate_missing', methods: ['POST'])]
    public function generateMissing(): JsonResponse
    {
        try {
            $this->logger->info('Admin attempting to generate missing certificates', [
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_generate_missing',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            $startTime = microtime(true);
            $count = $this->certificateService->generateMissingCertificates();
            $executionTime = microtime(true) - $startTime;

            $this->logger->info('Missing certificates generated successfully', [
                'generated_count' => $count,
                'execution_time_seconds' => round($executionTime, 3),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'completion_timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => sprintf('Generated %d certificates', $count),
                'count' => $count,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error generating missing certificates', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'action' => 'certificate_generate_missing',
                'failure_timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to generate certificates: ' . $e->getMessage(),
            ], 400);
        }
    }
}
