<?php

namespace App\Service;

use App\Entity\LegalDocument;
use Knp\Snappy\Pdf;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Service for generating PDF files from legal documents
 * 
 * Handles PDF creation, updating, and deletion based on document lifecycle
 */
class LegalPdfGenerationService
{
    private const PDF_DIRECTORY = 'uploads/legal_documents/pdf/';
    private const TEMPLATE_PATH = 'legal_document/pdf_template.html.twig';

    public function __construct(
        private Pdf $pdf,
        private Environment $twig,
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private string $projectDir
    ) {
    }

    /**
     * Generate PDF file for a legal document
     * 
     * @param LegalDocument $document The document to generate PDF for
     * @return array Result information
     */
    public function generatePdf(LegalDocument $document): array
    {
        $documentId = $document->getId();
        $this->logger->info('Generating PDF for legal document', [
            'document_id' => $documentId,
            'type' => $document->getType(),
            'title' => $document->getTitle()
        ]);

        try {
            // Create PDF directory if it doesn't exist
            $pdfDir = $this->projectDir . '/public/' . self::PDF_DIRECTORY;
            if (!is_dir($pdfDir)) {
                mkdir($pdfDir, 0755, true);
            }

            // Generate PDF filename
            $filename = $this->generatePdfFilename($document);
            $filepath = $pdfDir . $filename;

            // Render HTML template
            $html = $this->twig->render(self::TEMPLATE_PATH, [
                'document' => $document,
                'generation_date' => new \DateTime(),
                'company_name' => 'EPROFOS',
                'company_logo' => $this->getCompanyLogoPath()
            ]);

            // Configure PDF options
            $options = [
                'page-size' => 'A4',
                'margin-top' => '20mm',
                'margin-bottom' => '20mm',
                'margin-left' => '15mm',
                'margin-right' => '15mm',
                'encoding' => 'UTF-8',
                'footer-center' => 'Page [page] sur [toPage]',
                'footer-font-size' => '10',
                'footer-spacing' => '10',
                'header-spacing' => '10',
                'enable-local-file-access' => true,
                'print-media-type' => true,
                'disable-smart-shrinking' => true,
                'zoom' => '1.0'
            ];

            // Generate PDF
            $pdfContent = $this->pdf->getOutputFromHtml($html, $options);
            
            // Save PDF file
            file_put_contents($filepath, $pdfContent);

            // Update document metadata with PDF info
            $metadata = $document->getMetadata() ?? [];
            $metadata['pdf'] = [
                'filename' => $filename,
                'filepath' => self::PDF_DIRECTORY . $filename,
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'file_size' => filesize($filepath)
            ];
            $document->setMetadata($metadata);

            $this->logger->info('Successfully generated PDF for legal document', [
                'document_id' => $documentId,
                'filename' => $filename,
                'file_size' => filesize($filepath)
            ]);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => self::PDF_DIRECTORY . $filename,
                'file_size' => filesize($filepath)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate PDF for legal document', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete PDF file for a legal document
     * 
     * @param LegalDocument $document The document to delete PDF for
     * @return array Result information
     */
    public function deletePdf(LegalDocument $document): array
    {
        $documentId = $document->getId();
        $this->logger->info('Deleting PDF for legal document', [
            'document_id' => $documentId,
            'type' => $document->getType()
        ]);

        try {
            $metadata = $document->getMetadata() ?? [];
            
            if (isset($metadata['pdf']['filename'])) {
                $filename = $metadata['pdf']['filename'];
                $filepath = $this->projectDir . '/public/' . self::PDF_DIRECTORY . $filename;
                
                if (file_exists($filepath)) {
                    unlink($filepath);
                    $this->logger->info('PDF file deleted', [
                        'document_id' => $documentId,
                        'filename' => $filename
                    ]);
                }
                
                // Remove PDF info from metadata
                unset($metadata['pdf']);
                $document->setMetadata($metadata);
            }

            return [
                'success' => true,
                'message' => 'PDF deleted successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete PDF for legal document', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if document has a PDF file
     * 
     * @param LegalDocument $document The document to check
     * @return bool True if PDF exists
     */
    public function hasPdf(LegalDocument $document): bool
    {
        $metadata = $document->getMetadata() ?? [];
        
        if (!isset($metadata['pdf']['filename'])) {
            return false;
        }

        $filename = $metadata['pdf']['filename'];
        $filepath = $this->projectDir . '/public/' . self::PDF_DIRECTORY . $filename;
        
        return file_exists($filepath);
    }

    /**
     * Get PDF file information
     * 
     * @param LegalDocument $document The document to get PDF info for
     * @return array|null PDF information or null if no PDF
     */
    public function getPdfInfo(LegalDocument $document): ?array
    {
        $metadata = $document->getMetadata() ?? [];
        
        if (!isset($metadata['pdf'])) {
            return null;
        }

        $pdfInfo = $metadata['pdf'];
        $filepath = $this->projectDir . '/public/' . self::PDF_DIRECTORY . $pdfInfo['filename'];
        
        // Check if file still exists
        if (!file_exists($filepath)) {
            return null;
        }

        // Update file size if needed
        $currentSize = filesize($filepath);
        if ($currentSize !== ($pdfInfo['file_size'] ?? 0)) {
            $pdfInfo['file_size'] = $currentSize;
        }

        return $pdfInfo;
    }

    /**
     * Generate PDF filename based on document
     * 
     * @param LegalDocument $document The document
     * @return string Generated filename
     */
    private function generatePdfFilename(LegalDocument $document): string
    {
        // Sanitize title for filename
        $title = $this->sanitizeForFilename($document->getTitle());
        $type = $document->getType();
        $version = $document->getVersion();
        $timestamp = (new \DateTime())->format('Y-m-d_H-i-s');
        
        return sprintf('%s_%s_v%s_%s.pdf', $type, $title, $version, $timestamp);
    }

    /**
     * Sanitize string for use in filename
     * 
     * @param string $string The string to sanitize
     * @return string Sanitized string
     */
    private function sanitizeForFilename(string $string): string
    {
        // Remove accents and special characters
        $string = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $string);
        
        // Replace spaces and special characters with underscores
        $string = preg_replace('/[^a-z0-9_-]/', '_', $string);
        
        // Remove multiple consecutive underscores
        $string = preg_replace('/_+/', '_', $string);
        
        // Trim underscores from start and end
        $string = trim($string, '_');
        
        // Limit length
        return substr($string, 0, 50);
    }

    /**
     * Get company logo path if exists
     * 
     * @return string|null Logo path or null if not found
     */
    private function getCompanyLogoPath(): ?string
    {
        $logoPath = $this->projectDir . '/public/images/logo.png';
        
        if (file_exists($logoPath)) {
            return $logoPath;
        }
        
        // Try alternative logo paths
        $alternativePaths = [
            $this->projectDir . '/public/images/logo.jpg',
            $this->projectDir . '/public/images/logo.svg',
            $this->projectDir . '/public/images/eprofos-logo.png'
        ];
        
        foreach ($alternativePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
}
