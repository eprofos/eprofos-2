<?php

declare(strict_types=1);

namespace App\Service\Student;

use App\Entity\Student\Certificate;
use Knp\Snappy\Pdf;
use Twig\Environment;
use Psr\Log\LoggerInterface;
use Exception;
use RuntimeException;

/**
 * CertificatePDFService handles PDF generation for certificates.
 *
 * Uses KnpSnappyBundle for professional PDF generation with
 * customizable templates and high-quality output.
 */
class CertificatePDFService
{
    public function __construct(
        private readonly Pdf $pdf,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger
    ) {
        try {
            $this->logger->info('Initializing CertificatePDFService');
            
            // Configure PDF options for certificates
            $this->pdf->setOptions([
                'page-size' => 'A4',
                'orientation' => 'landscape',
                'margin-top' => '10mm',
                'margin-right' => '10mm',
                'margin-bottom' => '10mm',
                'margin-left' => '10mm',
                'encoding' => 'utf-8',
                'print-media-type' => true,
                'no-pdf-compression' => false,
                'disable-smart-shrinking' => true,
            ]);
            
            $this->logger->info('CertificatePDFService initialized successfully', [
                'pdf_options' => [
                    'page-size' => 'A4',
                    'orientation' => 'landscape',
                    'encoding' => 'utf-8'
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize CertificatePDFService', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Certificate PDF service initialization failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate PDF content for certificate.
     */
    public function generateCertificatePDF(Certificate $certificate): string
    {
        $certificateId = $certificate->getId();
        $studentId = $certificate->getStudent()?->getId();
        $formationId = $certificate->getFormation()?->getId();
        
        $this->logger->info('Starting certificate PDF generation', [
            'certificate_id' => $certificateId,
            'student_id' => $studentId,
            'formation_id' => $formationId,
            'status' => $certificate->getStatus(),
            'template' => $certificate->getCertificateTemplate(),
            'grade' => $certificate->getGrade(),
            'final_score' => $certificate->getFinalScore()
        ]);

        try {
            // Step 1: Get appropriate template
            $this->logger->debug('Determining template for certificate', [
                'certificate_id' => $certificateId,
                'template_name' => $certificate->getCertificateTemplate()
            ]);
            
            $template = $this->getTemplateForCertificate($certificate);
            
            $this->logger->info('Template selected for certificate', [
                'certificate_id' => $certificateId,
                'template_path' => $template
            ]);

            // Step 2: Prepare template data
            $templateData = [
                'certificate' => $certificate,
                'student' => $certificate->getStudent(),
                'formation' => $certificate->getFormation(),
                'enrollment' => $certificate->getEnrollment(),
            ];
            
            $this->logger->debug('Template data prepared', [
                'certificate_id' => $certificateId,
                'has_student' => $templateData['student'] !== null,
                'has_formation' => $templateData['formation'] !== null,
                'has_enrollment' => $templateData['enrollment'] !== null,
                'student_name' => $templateData['student']?->getFullName(),
                'formation_title' => $templateData['formation']?->getTitle()
            ]);

            // Step 3: Render HTML template
            $this->logger->debug('Rendering HTML template', [
                'certificate_id' => $certificateId,
                'template' => $template
            ]);
            
            $html = $this->twig->render($template, $templateData);
            
            $this->logger->info('HTML template rendered successfully', [
                'certificate_id' => $certificateId,
                'html_length' => strlen($html)
            ]);

            // Step 4: Generate PDF from HTML
            $this->logger->debug('Generating PDF from HTML', [
                'certificate_id' => $certificateId,
                'html_size_kb' => round(strlen($html) / 1024, 2)
            ]);
            
            $pdfContent = $this->pdf->getOutputFromHtml($html);
            
            $this->logger->info('Certificate PDF generated successfully', [
                'certificate_id' => $certificateId,
                'student_id' => $studentId,
                'formation_id' => $formationId,
                'pdf_size_kb' => round(strlen($pdfContent) / 1024, 2),
                'template_used' => $template
            ]);

            return $pdfContent;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to generate certificate PDF', [
                'certificate_id' => $certificateId,
                'student_id' => $studentId,
                'formation_id' => $formationId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'template' => $certificate->getCertificateTemplate() ?? 'unknown'
            ]);
            
            throw new RuntimeException(
                sprintf('Failed to generate PDF for certificate ID %s: %s', $certificateId, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Get template path for certificate based on template type.
     */
    private function getTemplateForCertificate(Certificate $certificate): string
    {
        $certificateId = $certificate->getId();
        $templateName = $certificate->getCertificateTemplate();
        
        $this->logger->debug('Getting template for certificate', [
            'certificate_id' => $certificateId,
            'template_name' => $templateName
        ]);

        try {
            // Map template names to actual template files
            $templateMap = [
                'formation_completion_2024' => 'certificate/templates/formation_completion.html.twig',
                'formation_excellence' => 'certificate/templates/formation_excellence.html.twig',
                'formation_basic' => 'certificate/templates/formation_basic.html.twig',
            ];

            $this->logger->debug('Available template mappings', [
                'certificate_id' => $certificateId,
                'available_templates' => array_keys($templateMap),
                'requested_template' => $templateName
            ]);

            // Get template path or use default
            $templatePath = $templateMap[$templateName] ?? $templateMap['formation_completion_2024'];
            
            // Validate template exists in Twig
            if (!$this->twig->getLoader()->exists($templatePath)) {
                $this->logger->warning('Template file not found, using default', [
                    'certificate_id' => $certificateId,
                    'requested_template' => $templateName,
                    'attempted_path' => $templatePath,
                    'fallback_template' => $templateMap['formation_completion_2024']
                ]);
                
                $templatePath = $templateMap['formation_completion_2024'];
                
                // Check if default template exists
                if (!$this->twig->getLoader()->exists($templatePath)) {
                    throw new RuntimeException('Default certificate template not found: ' . $templatePath);
                }
            }

            $this->logger->info('Template resolved successfully', [
                'certificate_id' => $certificateId,
                'requested_template' => $templateName,
                'resolved_template' => $templatePath,
                'used_fallback' => $templatePath !== ($templateMap[$templateName] ?? null)
            ]);

            return $templatePath;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to resolve template for certificate', [
                'certificate_id' => $certificateId,
                'template_name' => $templateName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new RuntimeException(
                sprintf('Failed to resolve template for certificate ID %s: %s', $certificateId, $e->getMessage()),
                0,
                $e
            );
        }
    }
}
