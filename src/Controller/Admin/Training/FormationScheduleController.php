<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Formation;
use App\Service\Training\FormationScheduleService;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    ) {}

    /**
     * Display the daily schedule for a formation.
     */
    #[Route('', name: 'admin_formation_schedule_show', methods: ['GET'])]
    public function show(Formation $formation): Response
    {
        // Calculate the complete schedule
        $scheduleData = $this->scheduleService->calculateFormationSchedule($formation);

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
    }

    /**
     * Download the daily schedule as PDF.
     */
    #[Route('/pdf', name: 'admin_formation_schedule_pdf', methods: ['GET'])]
    public function downloadPdf(Formation $formation): Response
    {
        // Calculate the complete schedule
        $scheduleData = $this->scheduleService->calculateFormationSchedule($formation);

        // Render the PDF template
        $html = $this->renderView('admin/formation/schedule_pdf.html.twig', [
            'formation' => $formation,
            'scheduleData' => $scheduleData,
            'scheduleService' => $this->scheduleService,
            'page_title' => 'Planning de formation',
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

        // Generate filename
        $filename = sprintf(
            'planning_%s_%s.pdf',
            $formation->getSlug(),
            date('Y-m-d'),
        );

        return new PdfResponse(
            $this->knpSnappyPdf->getOutputFromHtml($html, $options),
            $filename,
        );
    }
}
