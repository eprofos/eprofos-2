<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Student\Certificate;
use App\Repository\Student\CertificateRepository;
use App\Service\Student\CertificateService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Certificate management dashboard.
     */
    #[Route('/', name: 'admin_certificate_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $this->logger->info('Admin certificate index accessed', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all()
        ]);

        try {
            $search = $request->query->get('search', '');
            $status = $request->query->get('status', '');

            $this->logger->debug('Building certificate query', [
                'search' => $search,
                'status' => $status
            ]);

            $queryBuilder = $this->certificateRepository->createCertificateQueryBuilder();

            if ($search) {
                $queryBuilder
                    ->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search OR f.title LIKE :search OR c.certificateNumber LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
                    
                $this->logger->debug('Applied search filter', ['search_term' => $search]);
            }

            if ($status) {
                $queryBuilder
                    ->andWhere('c.status = :status')
                    ->setParameter('status', $status);
                    
                $this->logger->debug('Applied status filter', ['status' => $status]);
            }

            $queryBuilder->orderBy('c.issuedAt', 'DESC');

            $certificates = $paginator->paginate(
                $queryBuilder->getQuery(),
                $request->query->getInt('page', 1),
                20
            );

            $this->logger->debug('Certificates retrieved', [
                'total_items' => $certificates->getTotalItemCount(),
                'current_page' => $certificates->getCurrentPageNumber(),
                'items_per_page' => $certificates->getItemNumberPerPage()
            ]);

            // Get statistics for dashboard
            $statistics = $this->certificateService->getCertificateStatistics();
            
            $this->logger->debug('Certificate statistics loaded', [
                'statistics_keys' => array_keys($statistics)
            ]);

            $this->logger->info('Certificate index loaded successfully', [
                'certificates_count' => $certificates->getTotalItemCount(),
                'current_page' => $certificates->getCurrentPageNumber()
            ]);

            return $this->render('admin/student/certificate/index.html.twig', [
                'certificates' => $certificates,
                'statistics' => $statistics,
                'search' => $search,
                'status' => $status,
                'available_statuses' => Certificate::STATUSES,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error loading certificate index', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_params' => $request->query->all()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de la liste des certificats.');
            
            // Return empty result in case of error
            return $this->render('admin/student/certificate/index.html.twig', [
                'certificates' => [],
                'statistics' => [],
                'search' => '',
                'status' => '',
                'available_statuses' => Certificate::STATUSES,
            ]);
        }
    }

    /**
     * Show certificate details.
     */
    #[Route('/{id}', name: 'admin_certificate_show', methods: ['GET'])]
    public function show(Certificate $certificate): Response
    {
        $this->logger->info('Certificate details accessed', [
            'certificate_id' => $certificate->getId(),
            'certificate_number' => $certificate->getCertificateNumber(),
            'certificate_status' => $certificate->getStatus(),
            'user_id' => $this->getUser()?->getUserIdentifier()
        ]);

        try {
            $this->logger->debug('Loading certificate details', [
                'certificate_id' => $certificate->getId(),
                'student_id' => $certificate->getStudent()->getId(),
                'formation_id' => $certificate->getFormation()->getId(),
                'issued_at' => $certificate->getIssuedAt()?->format('Y-m-d H:i:s')
            ]);

            $this->logger->info('Certificate details loaded successfully', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber()
            ]);

            return $this->render('admin/student/certificate/show.html.twig', [
                'certificate' => $certificate,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error loading certificate details', [
                'certificate_id' => $certificate->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails du certificat.');
            
            return $this->redirectToRoute('admin_certificate_index');
        }
    }

    /**
     * Generate certificate for enrollment.
     */
    #[Route('/generate/{enrollment}', name: 'admin_certificate_generate', methods: ['POST'])]
    public function generate(StudentEnrollment $enrollment): Response
    {
        $this->logger->info('Certificate generation requested', [
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()->getId(),
            'student_name' => $enrollment->getStudent()->getFullName(),
            'formation_id' => $enrollment->getFormation()->getId(),
            'formation_title' => $enrollment->getFormation()->getTitle(),
            'enrollment_status' => $enrollment->getStatus(),
            'user_id' => $this->getUser()?->getUserIdentifier()
        ]);

        try {
            $this->logger->debug('Validating enrollment for certificate generation', [
                'enrollment_id' => $enrollment->getId(),
                'enrollment_status' => $enrollment->getStatus(),
                'is_completed' => $enrollment->isCompleted(),
                'completion_date' => $enrollment->getCompletedAt()?->format('Y-m-d H:i:s')
            ]);

            // Check if enrollment is eligible for certificate
            if (!$enrollment->isCompleted()) {
                $this->logger->warning('Certificate generation attempted for incomplete enrollment', [
                    'enrollment_id' => $enrollment->getId(),
                    'enrollment_status' => $enrollment->getStatus(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);
                
                throw new \InvalidArgumentException('L\'inscription doit être terminée pour générer un certificat');
            }

            $this->logger->debug('Calling certificate service for generation', [
                'enrollment_id' => $enrollment->getId()
            ]);

            $certificate = $this->certificateService->generateCertificate($enrollment);

            $this->logger->info('Certificate generated successfully', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber(),
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $enrollment->getStudent()->getId(),
                'formation_id' => $enrollment->getFormation()->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', sprintf(
                'Certificat généré avec succès (N° %s)',
                $certificate->getCertificateNumber()
            ));

            return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Certificate generation validation error', [
                'enrollment_id' => $enrollment->getId(),
                'validation_error' => $e->getMessage(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('admin_student_enrollment_show', ['id' => $enrollment->getId()]);
        } catch (\Exception $e) {
            $this->logger->error('Error during certificate generation', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $enrollment->getStudent()->getId(),
                'formation_id' => $enrollment->getFormation()->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

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
        $this->logger->info('Certificate revocation requested', [
            'certificate_id' => $certificate->getId(),
            'certificate_number' => $certificate->getCertificateNumber(),
            'current_status' => $certificate->getStatus(),
            'student_id' => $certificate->getStudent()->getId(),
            'formation_id' => $certificate->getFormation()->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier()
        ]);

        try {
            $reason = $request->request->get('revocation_reason');

            $this->logger->debug('Validating revocation request', [
                'certificate_id' => $certificate->getId(),
                'has_reason' => !empty($reason),
                'reason_length' => $reason ? strlen($reason) : 0
            ]);

            if (!$reason) {
                $this->logger->warning('Certificate revocation attempted without reason', [
                    'certificate_id' => $certificate->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);
                
                $this->addFlash('error', 'Une raison de révocation est requise.');
                return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
            }

            if (strlen($reason) < 10) {
                $this->logger->warning('Certificate revocation attempted with insufficient reason', [
                    'certificate_id' => $certificate->getId(),
                    'reason_length' => strlen($reason),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);
                
                $this->addFlash('error', 'La raison de révocation doit contenir au moins 10 caractères.');
                return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
            }

            $this->logger->debug('Calling certificate service for revocation', [
                'certificate_id' => $certificate->getId(),
                'reason' => $reason
            ]);

            $this->certificateService->revokeCertificate($certificate, $reason);

            $this->logger->info('Certificate revoked successfully', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber(),
                'revocation_reason' => $reason,
                'student_id' => $certificate->getStudent()->getId(),
                'formation_id' => $certificate->getFormation()->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', 'Certificat révoqué avec succès.');
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Certificate revocation validation error', [
                'certificate_id' => $certificate->getId(),
                'validation_error' => $e->getMessage(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('warning', $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Error during certificate revocation', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

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
        $this->logger->info('Certificate reissuance requested', [
            'certificate_id' => $certificate->getId(),
            'certificate_number' => $certificate->getCertificateNumber(),
            'current_status' => $certificate->getStatus(),
            'student_id' => $certificate->getStudent()->getId(),
            'formation_id' => $certificate->getFormation()->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier()
        ]);

        try {
            $this->logger->debug('Validating certificate for reissuance', [
                'certificate_id' => $certificate->getId(),
                'current_status' => $certificate->getStatus()
            ]);

            // Additional validation before reissuance
            if ($certificate->getStatus() === Certificate::STATUS_REVOKED) {
                $this->logger->warning('Certificate reissuance attempted for revoked certificate', [
                    'certificate_id' => $certificate->getId(),
                    'current_status' => $certificate->getStatus(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);
                
                throw new \InvalidArgumentException('Un certificat révoqué ne peut pas être réémis');
            }

            $this->logger->debug('Calling certificate service for reissuance', [
                'certificate_id' => $certificate->getId()
            ]);

            $newCertificate = $this->certificateService->reissueCertificate($certificate);

            $this->logger->info('Certificate reissued successfully', [
                'original_certificate_id' => $certificate->getId(),
                'original_certificate_number' => $certificate->getCertificateNumber(),
                'new_certificate_id' => $newCertificate->getId(),
                'new_certificate_number' => $newCertificate->getCertificateNumber(),
                'student_id' => $certificate->getStudent()->getId(),
                'formation_id' => $certificate->getFormation()->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', sprintf(
                'Certificat réémis avec succès (N° %s)',
                $newCertificate->getCertificateNumber()
            ));

            return $this->redirectToRoute('admin_certificate_show', ['id' => $newCertificate->getId()]);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Certificate reissuance validation error', [
                'certificate_id' => $certificate->getId(),
                'validation_error' => $e->getMessage(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
        } catch (\Exception $e) {
            $this->logger->error('Error during certificate reissuance', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

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
        $this->logger->info('Certificate download requested', [
            'certificate_id' => $certificate->getId(),
            'certificate_number' => $certificate->getCertificateNumber(),
            'certificate_status' => $certificate->getStatus(),
            'student_id' => $certificate->getStudent()->getId(),
            'formation_id' => $certificate->getFormation()->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier()
        ]);

        try {
            $this->logger->debug('Validating certificate download eligibility', [
                'certificate_id' => $certificate->getId(),
                'can_be_downloaded' => $certificate->canBeDownloaded(),
                'certificate_status' => $certificate->getStatus()
            ]);

            if (!$certificate->canBeDownloaded()) {
                $this->logger->warning('Certificate download attempted for ineligible certificate', [
                    'certificate_id' => $certificate->getId(),
                    'certificate_status' => $certificate->getStatus(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);
                
                $this->addFlash('error', 'Ce certificat ne peut pas être téléchargé.');
                return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
            }

            $filePath = $certificate->getFullPdfPath();
            $absolutePath = $this->getParameter('kernel.project_dir') . '/public' . $filePath;

            $this->logger->debug('Checking certificate file existence', [
                'certificate_id' => $certificate->getId(),
                'file_path' => $filePath,
                'absolute_path' => $absolutePath,
                'file_exists' => file_exists($absolutePath)
            ]);

            if (!file_exists($absolutePath)) {
                $this->logger->error('Certificate PDF file not found', [
                    'certificate_id' => $certificate->getId(),
                    'certificate_number' => $certificate->getCertificateNumber(),
                    'expected_path' => $absolutePath,
                    'relative_path' => $filePath,
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);
                
                $this->addFlash('error', 'Le fichier PDF du certificat est introuvable.');
                return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
            }

            $fileSize = filesize($absolutePath);
            $this->logger->debug('Preparing certificate file download', [
                'certificate_id' => $certificate->getId(),
                'file_size' => $fileSize,
                'file_path' => $absolutePath
            ]);

            $response = new StreamedResponse(function () use ($absolutePath, $certificate) {
                $this->logger->debug('Starting certificate file stream', [
                    'certificate_id' => $certificate->getId(),
                    'file_path' => $absolutePath
                ]);
                
                try {
                    readfile($absolutePath);
                    
                    $this->logger->info('Certificate file streamed successfully', [
                        'certificate_id' => $certificate->getId(),
                        'certificate_number' => $certificate->getCertificateNumber()
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Error streaming certificate file', [
                        'certificate_id' => $certificate->getId(),
                        'error_message' => $e->getMessage(),
                        'file_path' => $absolutePath
                    ]);
                    throw $e;
                }
            });

            $filename = 'Certificat_' . $certificate->getCertificateNumber() . '.pdf';
            
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 
                $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $filename
                )
            );

            $this->logger->info('Certificate download initiated successfully', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber(),
                'filename' => $filename,
                'file_size' => $fileSize,
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Error during certificate download', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du téléchargement du certificat.');
            return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
        }
    }

    /**
     * Bulk certificate generation interface.
     */
    #[Route('/bulk/generate', name: 'admin_certificate_bulk_generate', methods: ['GET', 'POST'])]
    public function bulkGenerate(Request $request): Response
    {
        $this->logger->info('Bulk certificate generation accessed', [
            'method' => $request->getMethod(),
            'user_id' => $this->getUser()?->getUserIdentifier()
        ]);

        if ($request->isMethod('POST')) {
            try {
                $enrollmentIds = array_map('intval', (array) $request->request->get('enrollment_ids', []));

                $this->logger->debug('Processing bulk certificate generation request', [
                    'enrollment_ids_count' => count($enrollmentIds),
                    'enrollment_ids' => $enrollmentIds,
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                if (empty($enrollmentIds)) {
                    $this->logger->warning('Bulk certificate generation attempted without enrollments', [
                        'user_id' => $this->getUser()?->getUserIdentifier()
                    ]);
                    
                    $this->addFlash('error', 'Aucune inscription sélectionnée.');
                    return $this->redirectToRoute('admin_certificate_bulk_generate');
                }

                // Validate enrollment IDs exist and are eligible
                $validEnrollments = $this->entityManager->getRepository(StudentEnrollment::class)
                    ->findBy(['id' => $enrollmentIds]);

                $this->logger->debug('Validated enrollments for bulk generation', [
                    'requested_count' => count($enrollmentIds),
                    'valid_count' => count($validEnrollments),
                    'valid_enrollment_ids' => array_map(fn($e) => $e->getId(), $validEnrollments)
                ]);

                if (count($validEnrollments) !== count($enrollmentIds)) {
                    $this->logger->warning('Some enrollment IDs not found for bulk generation', [
                        'requested_ids' => $enrollmentIds,
                        'valid_ids' => array_map(fn($e) => $e->getId(), $validEnrollments),
                        'user_id' => $this->getUser()?->getUserIdentifier()
                    ]);
                }

                $this->logger->info('Starting bulk certificate generation', [
                    'enrollment_count' => count($enrollmentIds),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                $results = $this->certificateService->bulkGenerateCertificates($enrollmentIds);

                $this->logger->info('Bulk certificate generation completed', [
                    'results' => $results,
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

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
                        $this->logger->warning('Bulk generation specific error', [
                            'error' => $error,
                            'user_id' => $this->getUser()?->getUserIdentifier()
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Error during bulk certificate generation', [
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'enrollment_ids' => $enrollmentIds ?? [],
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->addFlash('error', 'Erreur lors de la génération en lot : ' . $e->getMessage());
            }
        }

        try {
            // Get eligible enrollments (completed but without certificates)
            $this->logger->debug('Loading eligible enrollments for bulk generation');

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

            $this->logger->debug('Eligible enrollments loaded', [
                'count' => count($eligibleEnrollments),
                'enrollment_ids' => array_map(fn($e) => $e->getId(), $eligibleEnrollments)
            ]);

            return $this->render('admin/student/certificate/bulk_generate.html.twig', [
                'eligible_enrollments' => $eligibleEnrollments,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error loading eligible enrollments for bulk generation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des inscriptions éligibles.');
            
            return $this->render('admin/student/certificate/bulk_generate.html.twig', [
                'eligible_enrollments' => [],
            ]);
        }
    }

    /**
     * Certificate analytics dashboard.
     */
    #[Route('/analytics', name: 'admin_certificate_analytics', methods: ['GET'])]
    public function analytics(): Response
    {
        $this->logger->info('Certificate analytics dashboard accessed', [
            'user_id' => $this->getUser()?->getUserIdentifier()
        ]);

        try {
            $this->logger->debug('Loading certificate statistics for analytics');

            $statistics = $this->certificateService->getCertificateStatistics();
            
            $this->logger->debug('Certificate statistics loaded', [
                'statistics_keys' => array_keys($statistics)
            ]);

            $this->logger->debug('Loading formation-specific certificate statistics');

            $formationStats = $this->certificateRepository->countCertificatesByFormation();
            
            $this->logger->debug('Formation statistics loaded', [
                'formations_count' => count($formationStats)
            ]);

            $this->logger->debug('Loading average scores by formation');

            $averageScores = $this->certificateRepository->getAverageScoresByFormation();
            
            $this->logger->debug('Average scores loaded', [
                'formations_with_scores' => count($averageScores)
            ]);

            $this->logger->info('Certificate analytics data loaded successfully', [
                'statistics_count' => count($statistics),
                'formations_count' => count($formationStats),
                'formations_with_scores' => count($averageScores),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            return $this->render('admin/student/certificate/analytics.html.twig', [
                'statistics' => $statistics,
                'formation_stats' => $formationStats,
                'average_scores' => $averageScores,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error loading certificate analytics', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des analytics.');
            
            // Return empty analytics in case of error
            return $this->render('admin/student/certificate/analytics.html.twig', [
                'statistics' => [],
                'formation_stats' => [],
                'average_scores' => [],
            ]);
        }
    }

    /**
     * Send certificate email manually.
     */
    #[Route('/{id}/send-email', name: 'admin_certificate_send_email', methods: ['POST'])]
    public function sendEmail(Certificate $certificate): Response
    {
        $this->logger->info('Manual certificate email send requested', [
            'certificate_id' => $certificate->getId(),
            'certificate_number' => $certificate->getCertificateNumber(),
            'certificate_status' => $certificate->getStatus(),
            'student_id' => $certificate->getStudent()->getId(),
            'student_email' => $certificate->getStudent()->getEmail(),
            'formation_id' => $certificate->getFormation()->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier()
        ]);

        try {
            $this->logger->debug('Validating certificate for email sending', [
                'certificate_id' => $certificate->getId(),
                'certificate_status' => $certificate->getStatus(),
                'student_email' => $certificate->getStudent()->getEmail(),
                'has_pdf_path' => !empty($certificate->getPdfPath())
            ]);

            // Validate certificate can be emailed
            if ($certificate->getStatus() === Certificate::STATUS_REVOKED) {
                $this->logger->warning('Email send attempted for revoked certificate', [
                    'certificate_id' => $certificate->getId(),
                    'certificate_status' => $certificate->getStatus(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);
                
                throw new \InvalidArgumentException('Un certificat révoqué ne peut pas être envoyé par email');
            }

            if (empty($certificate->getStudent()->getEmail())) {
                $this->logger->warning('Email send attempted for student without email', [
                    'certificate_id' => $certificate->getId(),
                    'student_id' => $certificate->getStudent()->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);
                
                throw new \InvalidArgumentException('L\'étudiant n\'a pas d\'adresse email valide');
            }

            $this->logger->debug('Calling certificate service to send email', [
                'certificate_id' => $certificate->getId(),
                'recipient_email' => $certificate->getStudent()->getEmail()
            ]);

            $this->certificateService->sendCertificateEmail($certificate);

            $this->logger->info('Certificate email sent successfully', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber(),
                'student_id' => $certificate->getStudent()->getId(),
                'student_email' => $certificate->getStudent()->getEmail(),
                'formation_id' => $certificate->getFormation()->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('success', 'Email de certificat envoyé avec succès.');
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Certificate email send validation error', [
                'certificate_id' => $certificate->getId(),
                'validation_error' => $e->getMessage(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('warning', $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Error sending certificate email', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber(),
                'student_id' => $certificate->getStudent()->getId(),
                'student_email' => $certificate->getStudent()->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_certificate_show', ['id' => $certificate->getId()]);
    }
}
