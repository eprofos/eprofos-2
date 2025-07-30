<?php

declare(strict_types=1);

namespace App\Service\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Student\Certificate;
use App\Entity\Training\Formation;
use App\Entity\User\Student;
use App\Repository\Student\CertificateRepository;
use App\Service\Core\StudentEnrollmentService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * CertificateService handles certificate generation, verification, and management.
 *
 * Provides comprehensive certificate functionality including automatic generation
 * upon formation completion, PDF creation, verification system, and delivery.
 */
class CertificateService
{
    private const CERTIFICATE_UPLOAD_DIR = 'uploads/certificates';
    private const QR_CODE_UPLOAD_DIR = 'uploads/qr-codes';
    
    private string $projectDir;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificateRepository $certificateRepository,
        private readonly StudentEnrollmentService $enrollmentService,
        private readonly CertificatePDFService $pdfService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        string $projectDir
    ) {
        $this->projectDir = $projectDir;
    }

    /**
     * Check if student is eligible for certificate based on completion criteria.
     */
    public function checkCompletionEligibility(StudentEnrollment $enrollment): bool
    {
        $progress = $enrollment->getProgress();
        $formation = $enrollment->getSessionRegistration()?->getSession()?->getFormation();

        if (!$progress || !$formation) {
            return false;
        }

        // Check if enrollment is marked as completed
        if ($enrollment->getStatus() !== StudentEnrollment::STATUS_COMPLETED) {
            return false;
        }

        // Check overall completion percentage
        if ($progress->getCompletionPercentage() < 100) {
            return false;
        }

        // Check all modules completed
        foreach ($formation->getActiveModules() as $module) {
            if (!$this->isModuleCompleted($progress, $module)) {
                return false;
            }
        }

        // Check minimum scores for QCMs if required
        if (!$this->validateMinimumScores($progress, $formation)) {
            return false;
        }

        // Check attendance requirements
        if (!$this->validateAttendanceRequirements($enrollment)) {
            return false;
        }

        return true;
    }

    /**
     * Generate certificate for eligible student enrollment.
     */
    public function generateCertificate(StudentEnrollment $enrollment): Certificate
    {
        if (!$this->checkCompletionEligibility($enrollment)) {
            throw new InvalidArgumentException('Student enrollment does not meet completion criteria for certification');
        }

        $student = $enrollment->getStudent();
        $formation = $enrollment->getSessionRegistration()?->getSession()?->getFormation();

        if (!$student || !$formation) {
            throw new InvalidArgumentException('Invalid enrollment: missing student or formation');
        }

        // Check if certificate already exists
        $existingCertificate = $this->certificateRepository->findStudentCertificateForFormation($student, $formation);
        if ($existingCertificate && $existingCertificate->isValid()) {
            throw new InvalidArgumentException('Valid certificate already exists for this student and formation');
        }

        // Create certificate
        $certificate = new Certificate();
        $certificate->setStudent($student);
        $certificate->setFormation($formation);
        $certificate->setEnrollment($enrollment);
        $certificate->setCertificateTemplate($this->getDefaultTemplate($formation));

        // Calculate completion data
        $completionData = $this->calculateCompletionData($enrollment);
        $certificate->setCompletionData($completionData);

        // Calculate final score and grade
        $finalScore = $completionData['final_score'] ?? 0;
        $certificate->setFinalScore((string) $finalScore);
        $certificate->calculateGrade();

        // Add metadata
        $metadata = [
            'formation_duration' => $formation->getDurationHours(),
            'formation_category' => $formation->getCategory()?->getName(),
            'session_id' => $enrollment->getSessionRegistration()?->getSession()?->getId(),
            'generated_by' => 'system',
            'generation_timestamp' => (new DateTimeImmutable())->format('c'),
        ];
        $certificate->setMetadata($metadata);

        // Persist certificate
        $this->entityManager->persist($certificate);
        $this->entityManager->flush();

        // Generate PDF
        try {
            $pdfPath = $this->createCertificatePDF($certificate);
            $certificate->setPdfPath($pdfPath);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate certificate PDF', [
                'certificate_id' => $certificate->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        // Send certificate email
        try {
            $this->sendCertificateEmail($certificate);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send certificate email', [
                'certificate_id' => $certificate->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        $this->logger->info('Certificate generated successfully', [
            'certificate_id' => $certificate->getId(),
            'student_id' => $student->getId(),
            'formation_id' => $formation->getId(),
            'certificate_number' => $certificate->getCertificateNumber(),
        ]);

        return $certificate;
    }

    /**
     * Create certificate PDF file.
     */
    public function createCertificatePDF(Certificate $certificate): string
    {
        $pdfContent = $this->pdfService->generateCertificatePDF($certificate);
        
        // Create certificates directory if it doesn't exist
        $certificatesDir = $this->projectDir . '/public/' . self::CERTIFICATE_UPLOAD_DIR;
        if (!$this->filesystem->exists($certificatesDir)) {
            $this->filesystem->mkdir($certificatesDir);
        }

        // Generate unique filename
        $filename = sprintf(
            'certificate_%s_%s.pdf',
            $certificate->getCertificateNumber(),
            date('Ymd_His')
        );

        $filePath = $certificatesDir . '/' . $filename;

        // Save PDF file
        file_put_contents($filePath, $pdfContent);

        return $filename;
    }

    /**
     * Generate QR code for certificate verification.
     */
    public function generateVerificationQRCode(Certificate $certificate): string
    {
        $verificationUrl = $this->urlGenerator->generate('certificate_verify', [
            'code' => $certificate->getVerificationCode()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // For now, return a placeholder. In a full implementation, use a QR code library
        // like endroid/qr-code-bundle
        
        // Create QR codes directory if it doesn't exist
        $qrCodesDir = $this->projectDir . '/public/' . self::QR_CODE_UPLOAD_DIR;
        if (!$this->filesystem->exists($qrCodesDir)) {
            $this->filesystem->mkdir($qrCodesDir);
        }

        // Generate unique filename
        $filename = sprintf(
            'qr_certificate_%s.txt',
            $certificate->getVerificationCode()
        );

        $filePath = $qrCodesDir . '/' . $filename;

        // Save verification URL as text file (placeholder)
        file_put_contents($filePath, $verificationUrl);

        return $filename;
    }

    /**
     * Verify certificate by verification code.
     */
    public function verifyCertificate(string $verificationCode): ?Certificate
    {
        $certificate = $this->certificateRepository->findByVerificationCode($verificationCode);

        if ($certificate && $certificate->isValid()) {
            // Log verification attempt
            $this->logger->info('Certificate verified', [
                'certificate_id' => $certificate->getId(),
                'certificate_number' => $certificate->getCertificateNumber(),
                'verification_code' => $verificationCode,
            ]);

            return $certificate;
        }

        // Log failed verification attempt
        $this->logger->warning('Certificate verification failed', [
            'verification_code' => $verificationCode,
            'certificate_found' => $certificate !== null,
            'certificate_valid' => $certificate?->isValid() ?? false,
        ]);

        return null;
    }

    /**
     * Revoke certificate with reason.
     */
    public function revokeCertificate(Certificate $certificate, string $reason): Certificate
    {
        $oldStatus = $certificate->getStatus();
        $certificate->revoke($reason);

        $this->entityManager->flush();

        $this->logger->info('Certificate revoked', [
            'certificate_id' => $certificate->getId(),
            'certificate_number' => $certificate->getCertificateNumber(),
            'old_status' => $oldStatus,
            'revocation_reason' => $reason,
        ]);

        return $certificate;
    }

    /**
     * Reissue certificate (create new one and mark old as reissued).
     */
    public function reissueCertificate(Certificate $oldCertificate): Certificate
    {
        $enrollment = $oldCertificate->getEnrollment();
        
        if (!$enrollment) {
            throw new InvalidArgumentException('Cannot reissue certificate: enrollment not found');
        }

        // Mark old certificate as reissued
        $oldCertificate->markAsReissued();

        // Create new certificate
        $newCertificate = $this->generateCertificate($enrollment);

        $this->logger->info('Certificate reissued', [
            'old_certificate_id' => $oldCertificate->getId(),
            'new_certificate_id' => $newCertificate->getId(),
            'student_id' => $newCertificate->getStudent()?->getId(),
        ]);

        return $newCertificate;
    }

    /**
     * Send certificate delivery email.
     */
    public function sendCertificateEmail(Certificate $certificate): void
    {
        $student = $certificate->getStudent();
        $formation = $certificate->getFormation();

        if (!$student || !$formation) {
            throw new InvalidArgumentException('Cannot send email: missing student or formation data');
        }

        $verificationUrl = $this->urlGenerator->generate('certificate_verify', [
            'code' => $certificate->getVerificationCode()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from('formation@eprofos.fr')
            ->to($student->getEmail())
            ->subject('Certificat de formation - ' . $formation->getTitle())
            ->html($this->twig->render('emails/certificate_delivery.html.twig', [
                'student' => $student,
                'certificate' => $certificate,
                'formation' => $formation,
                'verificationUrl' => $verificationUrl,
            ]));

        // Attach PDF if available
        if ($certificate->canBeDownloaded()) {
            $pdfPath = $this->projectDir . '/public/' . self::CERTIFICATE_UPLOAD_DIR . '/' . $certificate->getPdfPath();
            if (file_exists($pdfPath)) {
                $email->attachFromPath($pdfPath, 
                    'Certificat_' . $certificate->getCertificateNumber() . '.pdf',
                    'application/pdf'
                );
            }
        }

        $this->mailer->send($email);

        $this->logger->info('Certificate delivery email sent', [
            'certificate_id' => $certificate->getId(),
            'student_email' => $student->getEmail(),
            'formation_id' => $formation->getId(),
        ]);
    }

    /**
     * Get certificate statistics for admin dashboard.
     */
    public function getCertificateStatistics(): array
    {
        $stats = $this->certificateRepository->getCertificateStats();
        $gradeDistribution = $this->certificateRepository->getGradeDistribution();
        $monthlyTrends = $this->certificateRepository->getMonthlyCertificateTrends();
        $recentCertificates = $this->certificateRepository->findRecentCertificates(5);

        return [
            'total_stats' => $stats,
            'grade_distribution' => $gradeDistribution,
            'monthly_trends' => $monthlyTrends,
            'recent_certificates' => $recentCertificates,
        ];
    }

    /**
     * Bulk generate certificates for completed enrollments.
     */
    public function bulkGenerateCertificates(array $enrollmentIds): array
    {
        $results = [
            'processed' => 0,
            'generated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'errors_details' => [],
        ];

        foreach ($enrollmentIds as $enrollmentId) {
            $results['processed']++;

            try {
                $enrollment = $this->entityManager->getRepository(StudentEnrollment::class)->find($enrollmentId);
                
                if (!$enrollment) {
                    $results['errors']++;
                    $results['errors_details'][] = "Enrollment {$enrollmentId} not found";
                    continue;
                }

                if (!$this->checkCompletionEligibility($enrollment)) {
                    $results['skipped']++;
                    continue;
                }

                $this->generateCertificate($enrollment);
                $results['generated']++;

            } catch (\Exception $e) {
                $results['errors']++;
                $results['errors_details'][] = "Enrollment {$enrollmentId}: " . $e->getMessage();
                
                $this->logger->error('Bulk certificate generation error', [
                    'enrollment_id' => $enrollmentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Bulk certificate generation completed', $results);

        return $results;
    }

    /**
     * Regenerate a certificate with a new verification code
     */
    public function regenerateCertificate(Certificate $certificate): Certificate
    {
        // Generate new verification code
        $certificate->regenerateVerificationCode();
        
        $this->entityManager->flush();
        
        $this->logger->info('Certificate regenerated', [
            'certificate_id' => $certificate->getId(),
            'new_verification_code' => $certificate->getVerificationCode(),
        ]);
        
        return $certificate;
    }

    /**
     * Generate missing certificates for all eligible completed enrollments
     */
    public function generateMissingCertificates(): int
    {
        $enrollments = $this->entityManager->getRepository(StudentEnrollment::class)
            ->findBy(['status' => StudentEnrollment::STATUS_COMPLETED]);
        $count = 0;
        
        foreach ($enrollments as $enrollment) {
            $student = $enrollment->getStudent();
            $formation = $enrollment->getSessionRegistration()?->getSession()?->getFormation();
            
            if (!$student || !$formation) {
                continue;
            }
            
            // Check if certificate already exists
            $existingCertificate = $this->certificateRepository->findStudentCertificateForFormation($student, $formation);
            
            if (!$existingCertificate && $this->checkCompletionEligibility($enrollment)) {
                try {
                    $this->generateCertificate($enrollment);
                    $count++;
                } catch (\Exception $e) {
                    $this->logger->error('Failed to generate missing certificate', [
                        'enrollment_id' => $enrollment->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        $this->entityManager->flush();
        
        $this->logger->info('Generated missing certificates', ['count' => $count]);
        
        return $count;
    }

    /**
     * Check if module is completed by student.
     */
    private function isModuleCompleted($progress, $module): bool
    {
        $moduleProgress = $progress->getModuleProgress($module->getId());
        return $moduleProgress['completion_percentage'] >= 100;
    }

    /**
     * Validate minimum scores for QCMs.
     */
    private function validateMinimumScores($progress, Formation $formation): bool
    {
        $minimumScore = 70; // Default minimum score percentage

        // Get all QCM scores from progress
        $qcmScores = $progress->getQCMProgress() ?? [];
        
        if (empty($qcmScores)) {
            return true; // No QCMs to validate
        }

        foreach ($qcmScores as $qcmScore) {
            if (($qcmScore['score_percentage'] ?? 0) < $minimumScore) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate attendance requirements.
     */
    private function validateAttendanceRequirements(StudentEnrollment $enrollment): bool
    {
        $minimumAttendanceRate = 80; // Default minimum attendance percentage

        // Get attendance records for the enrollment
        $attendanceRecords = $enrollment->getAttendanceRecords();
        
        if ($attendanceRecords->isEmpty()) {
            return true; // No attendance requirements
        }

        $totalSessions = $attendanceRecords->count();
        $attendedSessions = 0;

        foreach ($attendanceRecords as $record) {
            if ($record->isPresent()) {
                $attendedSessions++;
            }
        }

        $attendanceRate = ($attendedSessions / $totalSessions) * 100;

        return $attendanceRate >= $minimumAttendanceRate;
    }

    /**
     * Calculate completion data for certificate.
     */
    private function calculateCompletionData(StudentEnrollment $enrollment): array
    {
        $progress = $enrollment->getProgress();
        $formation = $enrollment->getSessionRegistration()?->getSession()?->getFormation();

        if (!$progress || !$formation) {
            return [];
        }

        // Calculate average score from all assessments
        $allScores = [];
        
        // Add QCM scores
        $qcmScores = $progress->getQCMProgress() ?? [];
        foreach ($qcmScores as $qcmScore) {
            if (isset($qcmScore['score_percentage'])) {
                $allScores[] = $qcmScore['score_percentage'];
            }
        }

        // Add exercise scores
        $exerciseScores = $progress->getExerciseProgress() ?? [];
        foreach ($exerciseScores as $exerciseScore) {
            if (isset($exerciseScore['score_percentage'])) {
                $allScores[] = $exerciseScore['score_percentage'];
            }
        }

        $finalScore = !empty($allScores) ? array_sum($allScores) / count($allScores) : 0;

        // Calculate attendance rate
        $attendanceRecords = $enrollment->getAttendanceRecords();
        $attendanceRate = 0;
        
        if (!$attendanceRecords->isEmpty()) {
            $totalSessions = $attendanceRecords->count();
            $attendedSessions = 0;

            foreach ($attendanceRecords as $record) {
                if ($record->isPresent()) {
                    $attendedSessions++;
                }
            }

            $attendanceRate = ($attendedSessions / $totalSessions) * 100;
        }

        return [
            'completion_date' => $enrollment->getCompletedAt()?->format('c'),
            'total_hours' => $formation->getDurationHours(),
            'final_score' => round($finalScore, 2),
            'attendance_rate' => round($attendanceRate, 2),
            'modules_completed' => count($formation->getActiveModules()),
            'qcm_count' => count($qcmScores),
            'exercise_count' => count($exerciseScores),
            'average_qcm_score' => !empty($qcmScores) ? 
                round(array_sum(array_column($qcmScores, 'score_percentage')) / count($qcmScores), 2) : 0,
            'average_exercise_score' => !empty($exerciseScores) ? 
                round(array_sum(array_column($exerciseScores, 'score_percentage')) / count($exerciseScores), 2) : 0,
        ];
    }

    /**
     * Get default certificate template for formation.
     */
    private function getDefaultTemplate(Formation $formation): string
    {
        // For now, use a default template. In the future, this could be customized per formation
        return 'formation_completion_2024';
    }
}
