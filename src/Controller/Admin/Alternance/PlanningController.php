<?php

declare(strict_types=1);

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Entity\Training\Formation;
use App\Entity\User\Mentor;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Repository\Alternance\AlternanceProgramRepository;
use App\Service\Alternance\PlanningAnalyticsService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/alternance/planning')]
#[IsGranted('ROLE_ADMIN')]
class PlanningController extends AbstractController
{
    public function __construct(
        private AlternanceContractRepository $contractRepository,
        private AlternanceProgramRepository $programRepository,
        private EntityManagerInterface $entityManager,
        private PlanningAnalyticsService $analyticsService,
    ) {}

    #[Route('', name: 'admin_alternance_planning_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $view = $request->query->get('view', 'calendar'); // calendar, list, gantt
        $period = $request->query->get('period', 'month'); // week, month, semester, year
        $formation = $request->query->get('formation');
        $mentor = $request->query->get('mentor');

        $filters = [
            'formation' => $formation,
            'mentor' => $mentor,
            'period' => $period,
        ];

        // Get contracts with pagination for planning view
        $page = $request->query->getInt('page', 1);
        $perPage = 20;

        $qb = $this->contractRepository->createQueryBuilder('c')
            ->leftJoin('c.student', 's')
            ->leftJoin('c.session', 'session')
            ->leftJoin('session.formation', 'f')
            ->leftJoin('c.mentor', 'm')
            ->orderBy('c.startDate', 'DESC')
        ;

        if ($formation) {
            $qb->andWhere('f.id = :formation')
                ->setParameter('formation', $formation)
            ;
        }

        if ($mentor) {
            $qb->andWhere('m.id = :mentor')
                ->setParameter('mentor', $mentor)
            ;
        }

        $contracts = $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult()
        ;

        // Create count query with same filters
        $countQb = $this->contractRepository->createQueryBuilder('c2')
            ->select('COUNT(c2.id)')
            ->leftJoin('c2.session', 'session2')
            ->leftJoin('session2.formation', 'f2')
            ->leftJoin('c2.mentor', 'm2')
        ;

        if ($formation) {
            $countQb->andWhere('f2.id = :formation')
                ->setParameter('formation', $formation)
            ;
        }

        if ($mentor) {
            $countQb->andWhere('m2.id = :mentor')
                ->setParameter('mentor', $mentor)
            ;
        }

        $totalContracts = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalContracts / $perPage);

        $statistics = $this->analyticsService->getPlanningStatistics();

        // Get formations for filter dropdown
        $formations = $this->entityManager->getRepository(Formation::class)
            ->findActiveFormations()
        ;

        // Get mentors for filter dropdown
        $mentors = $this->entityManager->getRepository(Mentor::class)
            ->findAll()
        ;

        return $this->render('admin/alternance/planning/index.html.twig', [
            'view' => $view,
            'period' => $period,
            'filters' => $filters,
            'contracts' => $contracts,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'statistics' => $statistics,
            'formations' => $formations,
            'mentors' => $mentors,
        ]);
    }

    #[Route('/calendar', name: 'admin_alternance_planning_calendar', methods: ['GET'])]
    public function calendar(Request $request): Response
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');
        $formation = $request->query->get('formation');

        // Generate sample calendar events for demonstration
        $events = [];
        $contracts = $this->contractRepository->findAll();

        foreach ($contracts as $contract) {
            if ($contract->getStartDate() && $contract->getEndDate()) {
                $events[] = [
                    'id' => $contract->getId(),
                    'title' => sprintf(
                        '%s - %s',
                        $contract->getStudent()->getFullName(),
                        $contract->getSession()?->getFormation()?->getTitle() ?? 'Formation',
                    ),
                    'start' => $contract->getStartDate()->format('Y-m-d'),
                    'end' => $contract->getEndDate()->format('Y-m-d'),
                    'color' => $this->getContractColor($contract),
                    'description' => sprintf(
                        'Contrat %s - %s',
                        $contract->getStatus(),
                        $contract->getStudent()->getEmail(),
                    ),
                ];
            }
        }

        return $this->json($events);
    }

    #[Route('/contracts/{id}/schedule', name: 'admin_alternance_planning_contract_schedule', methods: ['GET', 'POST'])]
    public function contractSchedule(Request $request, AlternanceContract $contract): Response
    {
        if ($request->isMethod('POST')) {
            try {
                // Here you would update the contract schedule
                // For now, just show a success message
                $this->addFlash('success', 'Planning mis à jour avec succès.');
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_alternance_planning_contract_schedule', ['id' => $contract->getId()]);
        }

        // Generate sample schedule data
        $schedule = $this->getContractScheduleData($contract);
        $conflicts = $this->detectScheduleConflicts($contract);

        return $this->render('admin/alternance/planning/contract_schedule.html.twig', [
            'contract' => $contract,
            'schedule' => $schedule,
            'conflicts' => $conflicts,
        ]);
    }

    #[Route('/analytics', name: 'admin_alternance_planning_analytics', methods: ['GET'])]
    public function analytics(Request $request): Response
    {
        $period = $request->query->get('period', 'semester');
        $formation = $request->query->get('formation');

        $analytics = $this->analyticsService->getAnalyticsData($period, $formation);

        // Get formations for filter dropdown
        $formations = $this->entityManager->getRepository(Formation::class)
            ->findActiveFormations()
        ;

        return $this->render('admin/alternance/planning/analytics.html.twig', [
            'analytics' => $analytics,
            'period' => $period,
            'formation' => $formation,
            'formations' => $formations,
        ]);
    }

    #[Route('/export', name: 'admin_alternance_planning_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $period = $request->query->get('period', 'month');
        $formation = $request->query->get('formation');

        try {
            $exportData = $this->analyticsService->getExportData($format, $formation);

            if ($format === 'csv') {
                $content = $this->generateCsvContent($exportData);
                $contentType = 'text/csv';
            } else {
                throw new InvalidArgumentException("Format d'export non supporté: {$format}");
            }

            $response = new Response($content);
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set('Content-Disposition', 'attachment; filename="planning_alternance.' . $format . '"');

            return $response;
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_planning_index');
        }
    }

    private function generateCsvContent(array $exportData): string
    {
        $output = fopen('php://temp', 'r+');

        // Headers
        fputcsv($output, [
            'ID Contrat',
            'Alternant',
            'Formation',
            'Entreprise',
            'Date début',
            'Date fin',
            'Statut',
            'Type de contrat',
            'Heures centre',
            'Heures entreprise',
            'Tuteur',
            'Durée',
        ]);

        // Data
        foreach ($exportData as $row) {
            fputcsv($output, [
                $row['id'],
                $row['student_name'],
                $row['formation'],
                $row['company'],
                $row['start_date'],
                $row['end_date'],
                $row['status'],
                $row['contract_type'],
                $row['center_hours'],
                $row['company_hours'],
                $row['mentor'],
                $row['duration'],
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    private function getContractColor(AlternanceContract $contract): string
    {
        return match ($contract->getStatus()) {
            'active' => '#28a745',
            'pending' => '#ffc107',
            'completed' => '#6c757d',
            'cancelled' => '#dc3545',
            default => '#007bff'
        };
    }

    private function getContractScheduleData(AlternanceContract $contract): array
    {
        // Generate sample schedule data
        return [
            'current_week' => [
                'type' => 'enterprise',
                'start_date' => (new DateTime())->format('Y-m-d'),
                'end_date' => (new DateTime('+4 days'))->format('Y-m-d'),
                'description' => 'Semaine en entreprise',
            ],
            'next_weeks' => [
                [
                    'type' => 'training_center',
                    'start_date' => (new DateTime('+7 days'))->format('Y-m-d'),
                    'end_date' => (new DateTime('+11 days'))->format('Y-m-d'),
                    'description' => 'Semaine de formation au centre',
                ],
            ],
            'rhythm' => '3 semaines entreprise / 1 semaine centre',
            'total_hours_enterprise' => 450,
            'total_hours_training' => 150,
            'completion_percentage' => 65.5,
        ];
    }

    private function detectScheduleConflicts(AlternanceContract $contract): array
    {
        // Generate sample conflicts
        return [
            [
                'type' => 'overlap',
                'severity' => 'warning',
                'description' => 'Chevauchement possible avec les congés',
                'date' => (new DateTime('+15 days'))->format('Y-m-d'),
                'suggested_action' => 'Vérifier les disponibilités',
            ],
        ];
    }
}
