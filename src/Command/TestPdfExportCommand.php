<?php

namespace App\Command;

use App\Service\Core\DropoutPreventionService;
use Knp\Snappy\Pdf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;

#[AsCommand(
    name: 'app:test-pdf-export',
    description: 'Test PDF export functionality for engagement dashboard'
)]
class TestPdfExportCommand extends Command
{
    public function __construct(
        private DropoutPreventionService $dropoutService,
        private Pdf $knpSnappyPdf,
        private Environment $twig
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->info('Testing PDF export functionality...');

            // Get data from the service
            $data = $this->dropoutService->exportRetentionData('pdf');
            
            $io->success('✓ Data retrieved successfully');
            $io->writeln('Data structure:');
            $io->writeln('- Total students: ' . ($data['summary_stats']['total_students'] ?? 'N/A'));
            $io->writeln('- At risk count: ' . ($data['summary_stats']['at_risk_count'] ?? 'N/A'));
            $io->writeln('- Retention rate: ' . ($data['compliance_indicators']['retention_rate'] ?? 'N/A') . '%');

            // Test template rendering
            $html = $this->twig->render('admin/engagement/export_pdf.html.twig', [
                'data' => $data,
                'generated_at' => new \DateTime(),
                'title' => 'Test - Rapport d\'Engagement et de Rétention'
            ]);

            $io->success('✓ Template rendered successfully');
            $io->writeln('HTML length: ' . strlen($html) . ' characters');

            // Test PDF generation
            $pdfContent = $this->knpSnappyPdf->getOutputFromHtml($html, [
                'page-size' => 'A4',
                'margin-top' => '20mm',
                'margin-right' => '15mm',
                'margin-bottom' => '20mm',
                'margin-left' => '15mm',
                'encoding' => 'UTF-8'
            ]);

            $io->success('✓ PDF generated successfully');
            $io->writeln('PDF size: ' . strlen($pdfContent) . ' bytes');

            // Save test PDF
            $filename = '/tmp/test_engagement_report.pdf';
            file_put_contents($filename, $pdfContent);
            
            $io->success("✓ Test PDF saved to: $filename");
            $io->note('PDF export functionality is working correctly!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('PDF export test failed: ' . $e->getMessage());
            $io->writeln('Error details: ' . $e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
}
