<?php

declare(strict_types=1);

namespace App\Service\Student;

use App\Entity\Student\Certificate;
use Knp\Snappy\Pdf;
use Twig\Environment;

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
        private readonly Environment $twig
    ) {
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
    }

    /**
     * Generate PDF content for certificate.
     */
    public function generateCertificatePDF(Certificate $certificate): string
    {
        $template = $this->getTemplateForCertificate($certificate);
        
        $html = $this->twig->render($template, [
            'certificate' => $certificate,
            'student' => $certificate->getStudent(),
            'formation' => $certificate->getFormation(),
            'enrollment' => $certificate->getEnrollment(),
        ]);

        return $this->pdf->getOutputFromHtml($html);
    }

    /**
     * Get template path for certificate based on template type.
     */
    private function getTemplateForCertificate(Certificate $certificate): string
    {
        $templateName = $certificate->getCertificateTemplate();
        
        // Map template names to actual template files
        $templateMap = [
            'formation_completion_2024' => 'certificate/templates/formation_completion.html.twig',
            'formation_excellence' => 'certificate/templates/formation_excellence.html.twig',
            'formation_basic' => 'certificate/templates/formation_basic.html.twig',
        ];

        return $templateMap[$templateName] ?? $templateMap['formation_completion_2024'];
    }
}
