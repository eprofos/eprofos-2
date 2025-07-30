<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Student\Certificate;
use App\Repository\Student\CertificateRepository;
use App\Service\Student\CertificateService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CertificateController manages certificates in the admin interface.
 *
 * Provides comprehensive certificate management including generation,
 * revocation, reissuance, bulk operations, and analytics.
 */
#[Route('/admin/student/certificate')]
#[IsGranted('ROLE_ADMIN')]
class CertificateController extends AbstractController
{
    public function __construct(
        private readonly CertificateService $certificateService,
        private readonly CertificateRepository $certificateRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Certificate management dashboard.
     */
    #[Route('/', name: 'admin_certificate_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');

        $queryBuilder = $this->certificateRepository->createCertificateQueryBuilder();

        if ($search) {
            $queryBuilder
                ->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search OR f.title LIKE :search OR c.certificateNumber LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $queryBuilder
                ->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        $queryBuilder->orderBy('c.issuedAt', 'DESC');

        $certificates = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        // Get statistics for dashboard
        $statistics = $this->certificateService->getCertificateStatistics();

        return $this->render('admin/student/certificate/index.html.twig', [
            'certificates' => $certificates,
            'statistics' => $statistics,
            'search' => $search,
            'status' => $status,
            'available_statuses' => Certificate::STATUSES,
        ]);
    }

    /**
     * Show certificate details.
     */
    #[Route('/{id}', name: 'admin_certificate_show', methods: ['GET'])]
    public function show(Certificate $certificate): Response
    {
        return $this->render('admin/student/certificate/show.html.twig', [
            'certificate' => $certificate,
        ]);
    }

    /**
     * Generate certificate for enrollment.
     */
    #[Route('/generate/{enrollment}', name: 'admin_certificate_generate', methods: ['POST'])]
    public function generate(StudentEnrollment $enrollment): Response
    {
        try {
            $certificate = $this->certificateService->generateCertificate($enrollment);

            $this->addFlash('success', sprintf(
                'Certificat généré avec succès (N° %s)',
                $certificate->getCertificateNumber()
            ));

            return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du certificat : ' . $e->getMessage());

            return $this->redirectToRoute('admin_student_enrollment_show', ['id' => $enrollment->getId()]);
        }
    }

    /**
     * Revoke certificate.
     */
    #[Route('/{id}/revoke', name: 'admin_certificate_revoke', methods: ['POST'])]
    public function revoke(Certificate $certificate, Request $request): Response
    {
        $reason = $request->request->get('revocation_reason');

        if (!$reason) {
            $this->addFlash('error', 'Une raison de révocation est requise.');
            return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
        }

        try {
            $this->certificateService->revokeCertificate($certificate, $reason);

            $this->addFlash('success', 'Certificat révoqué avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la révocation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
    }

    /**
     * Reissue certificate.
     */
    #[Route('/{id}/reissue', name: 'admin_certificate_reissue', methods: ['POST'])]
    public function reissue(Certificate $certificate): Response
    {
        try {
            $newCertificate = $this->certificateService->reissueCertificate($certificate);

            $this->addFlash('success', sprintf(
                'Certificat réémis avec succès (N° %s)',
                $newCertificate->getCertificateNumber()
            ));

            return $this->redirectToRoute('admin_certificate_show', ['id' => $newCertificate->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la réémission : ' . $e->getMessage());

            return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
        }
    }

    /**
     * Download certificate PDF.
     */
    #[Route('/{id}/download', name: 'admin_certificate_download', methods: ['GET'])]
    public function download(Certificate $certificate): Response
    {
        if (!$certificate->canBeDownloaded()) {
            $this->addFlash('error', 'Ce certificat ne peut pas être téléchargé.');
            return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
        }

        $filePath = $certificate->getFullPdfPath();
        $absolutePath = $this->getParameter('kernel.project_dir') . '/public' . $filePath;

        if (!file_exists($absolutePath)) {
            $this->addFlash('error', 'Le fichier PDF du certificat est introuvable.');
            return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
        }

        $response = new StreamedResponse(function () use ($absolutePath) {
            readfile($absolutePath);
        });

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'Certificat_' . $certificate->getCertificateNumber() . '.pdf'
            )
        );

        return $response;
    }

    /**
     * Bulk certificate generation interface.
     */
    #[Route('/bulk/generate', name: 'admin_certificate_bulk_generate', methods: ['GET', 'POST'])]
    public function bulkGenerate(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $enrollmentIds = array_map('intval', (array) $request->request->get('enrollment_ids', []));

            if (empty($enrollmentIds)) {
                $this->addFlash('error', 'Aucune inscription sélectionnée.');
                return $this->redirectToRoute('admin_certificate_bulk_generate');
            }

            try {
                $results = $this->certificateService->bulkGenerateCertificates($enrollmentIds);

                $this->addFlash('success', sprintf(
                    'Génération en lot terminée : %d traités, %d générés, %d ignorés, %d erreurs',
                    $results['processed'],
                    $results['generated'],
                    $results['skipped'],
                    $results['errors']
                ));

                if (!empty($results['errors_details'])) {
                    foreach ($results['errors_details'] as $error) {
                        $this->addFlash('warning', $error);
                    }
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la génération en lot : ' . $e->getMessage());
            }
        }

        // Get eligible enrollments (completed but without certificates)
        $eligibleEnrollments = $this->entityManager->getRepository(StudentEnrollment::class)
            ->createQueryBuilder('se')
            ->leftJoin('se.student', 's')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 'sess')
            ->leftJoin('sess.formation', 'f')
            ->leftJoin('App\Entity\Student\Certificate', 'cert', 'WITH', 'cert.student = s AND cert.formation = f AND cert.status != :revoked')
            ->where('se.status = :completed')
            ->andWhere('cert.id IS NULL')
            ->setParameter('completed', StudentEnrollment::STATUS_COMPLETED)
            ->setParameter('revoked', Certificate::STATUS_REVOKED)
            ->orderBy('se.completedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/student/certificate/bulk_generate.html.twig', [
            'eligible_enrollments' => $eligibleEnrollments,
        ]);
    }

    /**
     * Certificate analytics dashboard.
     */
    #[Route('/analytics', name: 'admin_certificate_analytics', methods: ['GET'])]
    public function analytics(): Response
    {
        $statistics = $this->certificateService->getCertificateStatistics();
        $formationStats = $this->certificateRepository->countCertificatesByFormation();
        $averageScores = $this->certificateRepository->getAverageScoresByFormation();

        return $this->render('admin/student/certificate/analytics.html.twig', [
            'statistics' => $statistics,
            'formation_stats' => $formationStats,
            'average_scores' => $averageScores,
        ]);
    }

    /**
     * Send certificate email manually.
     */
    #[Route('/{id}/send-email', name: 'admin_certificate_send_email', methods: ['POST'])]
    public function sendEmail(Certificate $certificate): Response
    {
        try {
            $this->certificateService->sendCertificateEmail($certificate);

            $this->addFlash('success', 'Email de certificat envoyé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
    }
}
