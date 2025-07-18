<?php

namespace App\Controller\Admin;

use App\Entity\Formation;
use App\Service\FormationScheduleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Formation Schedule Controller
 * 
 * Handles schedule calculation and display for formations in the admin interface.
 * Provides detailed daily schedule breakdown (morning/afternoon) with durations.
 */
#[Route('/admin/formations/{id}/schedule', name: 'admin_formation_schedule_', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_ADMIN')]
class FormationScheduleController extends AbstractController
{
    public function __construct(
        private FormationScheduleService $scheduleService
    ) {
    }

    /**
     * Display the daily schedule for a formation
     */
    #[Route('', name: 'show', methods: ['GET'])]
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
                ['label' => 'Planning', 'url' => null]
            ]
        ]);
    }
}
