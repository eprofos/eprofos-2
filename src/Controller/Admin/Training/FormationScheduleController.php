<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Formation;
use App\Service\Training\FormationScheduleService;
use DomainException;
use Exception;
use InvalidArgumentException;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Error\Error;

/**
 * Admin Formation Schedule Controller.
 *
 * Handles schedule calculation and display for formations in the admin interface.
 * Provides detailed daily schedule breakdown (morning/afternoon) with durations.
 */
#[Route('/admin/formations/{id}/schedule', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_ADMIN')]
class FormationScheduleController extends AbstractController
{
    public function __construct(
        private FormationScheduleService $scheduleService,
        private Pdf $knpSnappyPdf,
        private LoggerInterface $logger,
    ) {}

    /**
     * Display the daily schedule for a formation.
     */
    #[Route('', name: 'admin_formation_schedule_show', methods: ['GET'])]
    public function show(Formation $formation): Response
    {
        $this->logger->info('Starting formation schedule display', [
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            // Calculate the complete schedule
            $this->logger->debug('Calculating formation schedule', [
                'formation_id' => $formation->getId(),
                'formation_active' => $formation->isActive(),
                'modules_count' => $formation->getModules()->count(),
            ]);

            $scheduleData = $this->scheduleService->calculateFormationSchedule($formation);

            $this->logger->info('Formation schedule calculated successfully', [
                'formation_id' => $formation->getId(),
                'total_days' => count($scheduleData['dailySchedule'] ?? []),
                'total_duration' => $scheduleData['totalDuration'] ?? 0,
                'schedule_data_keys' => array_keys($scheduleData),
            ]);

            return $this->render('admin/formation/schedule.html.twig', [
                'formation' => $formation,
                'scheduleData' => $scheduleData,
                'scheduleService' => $this->scheduleService,
                'page_title' => 'Planning de formation',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Formations', 'url' => $this->generateUrl('admin_formation_index')],
                    ['label' => $formation->getTitle(), 'url' => $this->generateUrl('admin_formation_show', ['id' => $formation->getId()])],
                    ['label' => 'Planning', 'url' => null],
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument error while calculating formation schedule', [
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de configuration de la formation : ' . $e->getMessage());

            return $this->redirectToRoute('admin_formation_show', ['id' => $formation->getId()]);
        } catch (DomainException $e) {
            $this->logger->error('Domain error while calculating formation schedule', [
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur métier : ' . $e->getMessage());

            return $this->redirectToRoute('admin_formation_show', ['id' => $formation->getId()]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error while displaying formation schedule', [
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'affichage du planning.');

            return $this->redirectToRoute('admin_formation_index');
        }
    }

    /**
     * Download the daily schedule as PDF.
     */
    #[Route('/pdf', name: 'admin_formation_schedule_pdf', methods: ['GET'])]
    public function downloadPdf(Formation $formation): Response
    {
        $this->logger->info('Starting formation schedule PDF generation', [
            'formation_id' => $formation->getId(),
            'formation_title' => $formation->getTitle(),
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            // Calculate the complete schedule
            $this->logger->debug('Calculating formation schedule for PDF', [
                'formation_id' => $formation->getId(),
                'formation_active' => $formation->isActive(),
                'modules_count' => $formation->getModules()->count(),
            ]);

            $scheduleData = $this->scheduleService->calculateFormationSchedule($formation);

            $this->logger->info('Formation schedule calculated for PDF', [
                'formation_id' => $formation->getId(),
                'total_days' => count($scheduleData['dailySchedule'] ?? []),
                'total_duration' => $scheduleData['totalDuration'] ?? 0,
            ]);

            // Render the PDF template
            $this->logger->debug('Rendering PDF template', [
                'formation_id' => $formation->getId(),
                'template' => 'admin/formation/schedule_pdf.html.twig',
            ]);

            $html = $this->renderView('admin/formation/schedule_pdf.html.twig', [
                'formation' => $formation,
                'scheduleData' => $scheduleData,
                'scheduleService' => $this->scheduleService,
                'page_title' => 'Planning de formation',
            ]);

            $this->logger->debug('PDF template rendered successfully', [
                'formation_id' => $formation->getId(),
                'html_length' => strlen($html),
            ]);

            // Configure PDF options
            $options = [
                'page-size' => 'A4',
                'margin-top' => '0.75in',
                'margin-right' => '0.75in',
                'margin-bottom' => '0.75in',
                'margin-left' => '0.75in',
                'encoding' => 'UTF-8',
                'orientation' => 'portrait',
                'disable-smart-shrinking' => true,
                'print-media-type' => true,
                'lowquality' => false,
                'no-background' => false,
                'grayscale' => false,
            ];

            $this->logger->debug('PDF options configured', [
                'formation_id' => $formation->getId(),
                'options' => $options,
            ]);

            // Generate filename
            $filename = sprintf(
                'planning_%s_%s.pdf',
                $formation->getSlug(),
                date('Y-m-d'),
            );

            $this->logger->debug('Generating PDF with KnpSnappyPdf', [
                'formation_id' => $formation->getId(),
                'filename' => $filename,
            ]);

            $pdfContent = $this->knpSnappyPdf->getOutputFromHtml($html, $options);

            $this->logger->info('PDF generated successfully', [
                'formation_id' => $formation->getId(),
                'filename' => $filename,
                'pdf_size' => strlen($pdfContent),
            ]);

            return new PdfResponse($pdfContent, $filename);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument error while generating formation schedule PDF', [
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw new BadRequestHttpException('Erreur de configuration de la formation : ' . $e->getMessage(), $e);
        } catch (DomainException $e) {
            $this->logger->error('Domain error while generating formation schedule PDF', [
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw new BadRequestHttpException('Erreur métier : ' . $e->getMessage(), $e);
        } catch (RuntimeException $e) {
            $this->logger->error('Runtime error during PDF generation (likely wkhtmltopdf issue)', [
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw new BadRequestHttpException('Erreur de génération PDF : ' . $e->getMessage(), $e);
        } catch (Error $e) {
            $this->logger->error('Twig template error while generating formation schedule PDF', [
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw new BadRequestHttpException('Erreur de template : ' . $e->getMessage(), $e);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error while generating formation schedule PDF', [
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw new BadRequestHttpException('Une erreur inattendue s\'est produite lors de la génération du PDF.', $e);
        }
    }
}
