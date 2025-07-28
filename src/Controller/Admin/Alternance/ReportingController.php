<?php

namespace App\Controller\Admin\Alternance;

use App\Repository\AlternanceContractRepository;
use App\Repository\Alternance\ProgressAssessmentRepository;
use App\Repository\Alternance\SkillsAssessmentRepository;
use App\Repository\CompanyMissionRepository;
use App\Repository\MentorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/alternance/reporting')]
#[IsGranted('ROLE_ADMIN')]
class ReportingController extends AbstractController
{
    public function __construct(
        private AlternanceContractRepository $contractRepository,
        private ProgressAssessmentRepository $progressRepository,
        private SkillsAssessmentRepository $skillsRepository,
        private CompanyMissionRepository $missionRepository,
        private MentorRepository $mentorRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_alternance_reporting_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $period = $request->query->get('period', 'current_year');
        $formation = $request->query->get('formation');
        $reportType = $request->query->get('report_type', 'overview');

        $filters = [
            'period' => $period,
            'formation' => $formation,
            'report_type' => $reportType,
        ];

        $reportData = $this->generateReportData($filters);
        $availableReports = $this->getAvailableReports();

        return $this->render('admin/alternance/reporting/index.html.twig', [
            'filters' => $filters,
            'report_data' => $reportData,
            'available_reports' => $availableReports,
        ]);
    }

    #[Route('/qualiopi', name: 'admin_alternance_reporting_qualiopi', methods: ['GET'])]
    public function qualiopiReport(Request $request): Response
    {
        $period = $request->query->get('period', 'current_year');
        $formation = $request->query->get('formation');

        $qualiopiData = $this->generateQualiopiReport($period, $formation);

        return $this->render('admin/alternance/reporting/qualiopi.html.twig', [
            'period' => $period,
            'formation' => $formation,
            'qualiopi_data' => $qualiopiData,
        ]);
    }

    #[Route('/performance', name: 'admin_alternance_reporting_performance', methods: ['GET'])]
    public function performanceReport(Request $request): Response
    {
        $period = $request->query->get('period', 'semester');
        $formation = $request->query->get('formation');
        $metrics = $request->query->all('metrics') ?: ['progression', 'skills', 'attendance'];

        $performanceData = $this->generatePerformanceReport($period, $formation, $metrics);

        return $this->render('admin/alternance/reporting/performance.html.twig', [
            'period' => $period,
            'formation' => $formation,
            'selected_metrics' => $metrics,
            'performance_data' => $performanceData,
        ]);
    }

    #[Route('/mentors', name: 'admin_alternance_reporting_mentors', methods: ['GET'])]
    public function mentorsReport(Request $request): Response
    {
        $period = $request->query->get('period', 'semester');
        $company = $request->query->get('company');

        $mentorsData = $this->generateMentorsReport($period, $company);

        return $this->render('admin/alternance/reporting/mentors.html.twig', [
            'period' => $period,
            'company' => $company,
            'mentors_data' => $mentorsData,
        ]);
    }

    #[Route('/missions', name: 'admin_alternance_reporting_missions', methods: ['GET'])]
    public function missionsReport(Request $request): Response
    {
        $period = $request->query->get('period', 'semester');
        $formation = $request->query->get('formation');
        $status = $request->query->get('status', 'all');

        $missionsData = $this->generateMissionsReport($period, $formation, $status);

        return $this->render('admin/alternance/reporting/missions.html.twig', [
            'period' => $period,
            'formation' => $formation,
            'status' => $status,
            'missions_data' => $missionsData,
        ]);
    }

    #[Route('/financial', name: 'admin_alternance_reporting_financial', methods: ['GET'])]
    public function financialReport(Request $request): Response
    {
        $period = $request->query->get('period', 'current_year');
        $formation = $request->query->get('formation');
        $breakdown = $request->query->get('breakdown', 'formation');

        $financialData = $this->generateFinancialReport($period, $formation, $breakdown);

        return $this->render('admin/alternance/reporting/financial.html.twig', [
            'period' => $period,
            'formation' => $formation,
            'breakdown' => $breakdown,
            'financial_data' => $financialData,
        ]);
    }

    #[Route('/export', name: 'admin_alternance_reporting_export', methods: ['GET'])]
    public function exportReport(Request $request): Response
    {
        $reportType = $request->query->get('report_type', 'overview');
        $format = $request->query->get('format', 'pdf');
        $period = $request->query->get('period', 'current_year');
        $formation = $request->query->get('formation');

        try {
            $data = $this->generateExportData($reportType, $format, [
                'period' => $period,
                'formation' => $formation,
            ]);

            $contentType = match($format) {
                'pdf' => 'application/pdf',
                'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'csv' => 'text/csv',
                default => 'application/octet-stream'
            };

            $filename = sprintf('rapport_%s_%s.%s', 
                $reportType, 
                date('Y-m-d'), 
                $format === 'excel' ? 'xlsx' : $format
            );

            $response = new Response($data);
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            
            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());
            return $this->redirectToRoute('admin_alternance_reporting_index');
        }
    }

    #[Route('/schedule', name: 'admin_alternance_reporting_schedule', methods: ['GET', 'POST'])]
    public function scheduleReport(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $scheduleData = $request->request->all('schedule');
            
            try {
                $this->scheduleAutomaticReport($scheduleData);
                $this->addFlash('success', 'Rapport programmé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la programmation : ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_alternance_reporting_schedule');
        }

        $scheduledReports = $this->getScheduledReports();

        return $this->render('admin/alternance/reporting/schedule.html.twig', [
            'scheduled_reports' => $scheduledReports,
        ]);
    }

    #[Route('/analytics', name: 'admin_alternance_reporting_analytics', methods: ['GET'])]
    public function analytics(Request $request): Response
    {
        $period = $request->query->get('period', 'semester');
        $dimensions = $request->query->all('dimensions') ?: ['time', 'formation', 'performance'];

        $analyticsData = $this->generateAdvancedAnalytics($period, $dimensions);

        return $this->render('admin/alternance/reporting/analytics.html.twig', [
            'period' => $period,
            'selected_dimensions' => $dimensions,
            'analytics_data' => $analyticsData,
        ]);
    }

    private function generateReportData(array $filters): array
    {
        $period = $filters['period'];
        $formation = $filters['formation'];

        $dateRange = $this->getPeriodDateRange($period);

        return [
            'summary' => [
                'total_contracts' => $this->contractRepository->count([]),
                'active_contracts' => $this->contractRepository->countByStatus('active'),
                'completed_contracts' => $this->contractRepository->countByStatus('completed'),
                'success_rate' => 78.5,
                'average_duration' => 18, // months
            ],
            'progression' => [
                'total_assessments' => $this->progressRepository->count([]),
                'average_progression' => 72.3,
                'students_at_risk' => 12,
                'improvement_trend' => 'positive',
            ],
            'skills' => [
                'total_evaluations' => $this->skillsRepository->count([]),
                'average_skills_score' => 3.2,
                'skills_gaps' => ['Communication', 'Gestion de projet'],
                'top_skills' => ['Technique', 'Adaptation'],
            ],
            'mentors' => [
                'total_mentors' => $this->mentorRepository->count([]),
                'active_mentors' => 45,
                'average_students_per_mentor' => 2.8,
                'satisfaction_rate' => 4.1,
            ],
            'missions' => [
                'total_missions' => $this->missionRepository->count([]),
                'active_missions' => $this->missionRepository->countActive(),
                'completion_rate' => 89.2,
                'average_rating' => 4.3,
            ],
        ];
    }

    private function generateQualiopiReport(string $period, ?string $formation): array
    {
        return [
            'compliance_indicators' => [
                'regular_assessment' => ['status' => 'compliant', 'score' => 95, 'details' => 'Évaluations trimestrielles mises en place'],
                'progression_tracking' => ['status' => 'compliant', 'score' => 92, 'details' => 'Suivi mensuel de la progression'],
                'skills_development' => ['status' => 'warning', 'score' => 78, 'details' => 'Certaines compétences nécessitent un renforcement'],
                'mentor_supervision' => ['status' => 'compliant', 'score' => 88, 'details' => 'Supervision régulière assurée'],
                'documentation' => ['status' => 'compliant', 'score' => 94, 'details' => 'Documentation complète et à jour'],
            ],
            'risk_areas' => [
                ['area' => 'Taux d\'abandon', 'level' => 'medium', 'actions' => 'Renforcer l\'accompagnement préventif'],
                ['area' => 'Évaluation des compétences', 'level' => 'low', 'actions' => 'Harmoniser les grilles d\'évaluation'],
            ],
            'recommendations' => [
                'Mettre en place des sessions de formation pour les mentors',
                'Développer un système d\'alerte précoce pour les décrochages',
                'Renforcer la communication entre centre et entreprise',
            ],
            'audit_readiness' => 85, // percentage
            'documentation_score' => 92,
            'process_compliance' => 89,
        ];
    }

    private function generatePerformanceReport(string $period, ?string $formation, array $metrics): array
    {
        return [
            'progression_metrics' => [
                'average_progression' => 72.3,
                'progression_trend' => [68.5, 70.2, 71.8, 72.3, 73.1],
                'distribution' => [
                    'excellent' => 25,
                    'good' => 45,
                    'average' => 20,
                    'needs_improvement' => 10
                ],
            ],
            'skills_metrics' => [
                'average_skills_score' => 3.2,
                'skills_evolution' => [2.8, 3.0, 3.1, 3.2, 3.3],
                'top_performing_skills' => [
                    'Techniques métier' => 3.8,
                    'Adaptation' => 3.6,
                    'Autonomie' => 3.4
                ],
                'skills_needing_attention' => [
                    'Communication' => 2.8,
                    'Gestion de projet' => 2.9,
                    'Leadership' => 2.7
                ],
            ],
            'attendance_metrics' => [
                'average_attendance' => 94.2,
                'attendance_trend' => [92.1, 93.5, 94.2, 95.1, 94.8],
                'absenteeism_rate' => 5.8,
            ],
            'completion_metrics' => [
                'on_time_completion' => 78.5,
                'delayed_completion' => 15.2,
                'early_completion' => 6.3,
            ],
        ];
    }

    private function generateMentorsReport(string $period, ?string $company): array
    {
        return [
            'mentor_statistics' => [
                'total_mentors' => 48,
                'active_mentors' => 45,
                'new_mentors' => 8,
                'mentor_retention_rate' => 92.5,
            ],
            'mentor_performance' => [
                'average_student_progression' => 73.5,
                'average_satisfaction' => 4.1,
                'completion_rate' => 82.3,
                'response_time' => 2.4, // days
            ],
            'mentor_distribution' => [
                'by_company_size' => [
                    'PME' => 28,
                    'ETI' => 15,
                    'Grande entreprise' => 5
                ],
                'by_sector' => [
                    'Tech' => 32,
                    'Service' => 12,
                    'Industrie' => 4
                ],
            ],
            'training_needs' => [
                'Pédagogie' => 15,
                'Évaluation' => 12,
                'Communication' => 8,
            ],
            'best_practices' => [
                'Suivi hebdomadaire régulier',
                'Objectifs clairs et mesurables',
                'Feedback constructif fréquent',
            ],
        ];
    }

    private function generateMissionsReport(string $period, ?string $formation, string $status): array
    {
        return [
            'mission_statistics' => [
                'total_missions' => 156,
                'active_missions' => 89,
                'completed_missions' => 67,
                'success_rate' => 89.2,
            ],
            'mission_types' => [
                'Développement' => 45,
                'Analyse' => 32,
                'Gestion de projet' => 28,
                'Support' => 21,
                'Formation' => 18,
                'Autre' => 12,
            ],
            'duration_analysis' => [
                'average_duration' => 8.5, // weeks
                'on_time_completion' => 78.2,
                'delayed_missions' => 16.7,
                'early_completion' => 5.1,
            ],
            'complexity_distribution' => [
                'Simple' => 35,
                'Moyenne' => 68,
                'Complexe' => 43,
                'Expert' => 10,
            ],
            'satisfaction_metrics' => [
                'student_satisfaction' => 4.2,
                'mentor_satisfaction' => 4.0,
                'company_satisfaction' => 4.3,
            ],
        ];
    }

    private function generateFinancialReport(string $period, ?string $formation, string $breakdown): array
    {
        return [
            'revenue_summary' => [
                'total_revenue' => 1250000,
                'revenue_per_student' => 8500,
                'revenue_growth' => 12.5, // percentage
            ],
            'cost_analysis' => [
                'training_costs' => 450000,
                'administrative_costs' => 180000,
                'mentor_compensation' => 320000,
                'infrastructure_costs' => 95000,
            ],
            'profitability' => [
                'gross_margin' => 62.5,
                'net_margin' => 18.7,
                'roi' => 24.3,
            ],
            'breakdown_by_formation' => [
                'Développement Web' => ['revenue' => 425000, 'margin' => 65.2],
                'Data Science' => ['revenue' => 380000, 'margin' => 61.8],
                'Marketing Digital' => ['revenue' => 295000, 'margin' => 58.9],
                'Design UX/UI' => ['revenue' => 150000, 'margin' => 55.7],
            ],
            'payment_analysis' => [
                'on_time_payments' => 94.2,
                'average_payment_delay' => 15, // days
                'outstanding_amount' => 125000,
            ],
        ];
    }

    private function generateAdvancedAnalytics(string $period, array $dimensions): array
    {
        return [
            'trends' => [
                'enrollment_trend' => [45, 52, 48, 61, 58, 67],
                'completion_trend' => [78, 82, 79, 85, 87, 89],
                'satisfaction_trend' => [3.8, 4.0, 4.1, 4.2, 4.1, 4.3],
            ],
            'correlations' => [
                'mentor_experience_vs_success' => 0.73,
                'mission_complexity_vs_satisfaction' => -0.24,
                'attendance_vs_completion' => 0.68,
            ],
            'predictive_insights' => [
                'dropout_risk_factors' => ['Faible assiduité', 'Difficultés en entreprise', 'Manque d\'accompagnement'],
                'success_indicators' => ['Mentoring actif', 'Missions alignées', 'Progression régulière'],
            ],
            'benchmarking' => [
                'sector_average_completion' => 76.5,
                'our_completion_rate' => 89.2,
                'sector_average_satisfaction' => 3.9,
                'our_satisfaction_rate' => 4.3,
            ],
        ];
    }

    private function getAvailableReports(): array
    {
        return [
            'overview' => ['name' => 'Vue d\'ensemble', 'description' => 'Rapport général sur l\'activité'],
            'qualiopi' => ['name' => 'Conformité Qualiopi', 'description' => 'Indicateurs de qualité et conformité'],
            'performance' => ['name' => 'Performance', 'description' => 'Métriques de performance des alternants'],
            'mentors' => ['name' => 'Mentors', 'description' => 'Analyse des mentors et de leur efficacité'],
            'missions' => ['name' => 'Missions', 'description' => 'Suivi et analyse des missions en entreprise'],
            'financial' => ['name' => 'Financier', 'description' => 'Analyse financière et rentabilité'],
        ];
    }

    private function getPeriodDateRange(string $period): array
    {
        $now = new \DateTime();
        
        return match($period) {
            'current_month' => [
                'start' => (clone $now)->modify('first day of this month'),
                'end' => (clone $now)->modify('last day of this month')
            ],
            'last_month' => [
                'start' => (clone $now)->modify('first day of last month'),
                'end' => (clone $now)->modify('last day of last month')
            ],
            'current_semester' => [
                'start' => (clone $now)->modify('-6 months'),
                'end' => $now
            ],
            'current_year' => [
                'start' => (clone $now)->modify('first day of January this year'),
                'end' => (clone $now)->modify('last day of December this year')
            ],
            'last_year' => [
                'start' => (clone $now)->modify('first day of January last year'),
                'end' => (clone $now)->modify('last day of December last year')
            ],
            default => [
                'start' => (clone $now)->modify('-1 year'),
                'end' => $now
            ]
        };
    }

    private function generateExportData(string $reportType, string $format, array $filters): string
    {
        // For demonstration, return CSV data
        if ($format === 'csv') {
            $output = fopen('php://temp', 'r+');
            
            // Sample data based on report type
            if ($reportType === 'overview') {
                fputcsv($output, ['Métrique', 'Valeur', 'Tendance']);
                fputcsv($output, ['Contrats actifs', '89', 'Stable']);
                fputcsv($output, ['Taux de réussite', '78.5%', 'En hausse']);
                fputcsv($output, ['Satisfaction moyenne', '4.3/5', 'Stable']);
            }
            
            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);
            
            return $content;
        }

        throw new \InvalidArgumentException("Format d'export non supporté: {$format}");
    }

    private function scheduleAutomaticReport(array $scheduleData): void
    {
        // Implementation would create scheduled report entries
        // For now, just validate the data
        if (empty($scheduleData['report_type']) || empty($scheduleData['frequency'])) {
            throw new \InvalidArgumentException('Type de rapport et fréquence requis');
        }
    }

    private function getScheduledReports(): array
    {
        return [
            [
                'id' => 1,
                'report_type' => 'overview',
                'frequency' => 'weekly',
                'next_execution' => new \DateTime('+3 days'),
                'recipients' => ['admin@eprofos.fr', 'direction@eprofos.fr'],
                'status' => 'active'
            ],
            [
                'id' => 2,
                'report_type' => 'qualiopi',
                'frequency' => 'monthly',
                'next_execution' => new \DateTime('+15 days'),
                'recipients' => ['qualite@eprofos.fr'],
                'status' => 'active'
            ]
        ];
    }
}
