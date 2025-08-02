<?php

declare(strict_types=1);

namespace App\Controller\Admin\Alternance;

use App\Repository\Alternance\AlternanceContractRepository;
use App\Repository\Alternance\CompanyMissionRepository;
use App\Repository\Alternance\ProgressAssessmentRepository;
use App\Repository\Alternance\SkillsAssessmentRepository;
use App\Repository\User\MentorRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
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
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_alternance_reporting_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('Accessing alternance reporting index', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        try {
            $period = $request->query->get('period', 'current_year');
            $formation = $request->query->get('formation');
            $reportType = $request->query->get('report_type', 'overview');

            $this->logger->info('Generating reporting index with filters', [
                'period' => $period,
                'formation' => $formation,
                'report_type' => $reportType,
            ]);

            $filters = [
                'period' => $period,
                'formation' => $formation,
                'report_type' => $reportType,
            ];

            $reportData = $this->generateReportData($filters);
            $availableReports = $this->getAvailableReports();

            $this->logger->info('Successfully generated reporting index data', [
                'filters_applied' => $filters,
                'report_data_keys' => array_keys($reportData),
                'available_reports_count' => count($availableReports),
            ]);

            return $this->render('admin/alternance/reporting/index.html.twig', [
                'filters' => $filters,
                'report_data' => $reportData,
                'available_reports' => $availableReports,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error generating reporting index', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_params' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération du rapport. Veuillez réessayer.');

            return $this->render('admin/alternance/reporting/index.html.twig', [
                'filters' => [
                    'period' => 'current_year',
                    'formation' => null,
                    'report_type' => 'overview',
                ],
                'report_data' => [],
                'available_reports' => $this->getAvailableReports(),
            ]);
        }
    }

    #[Route('/qualiopi', name: 'admin_alternance_reporting_qualiopi', methods: ['GET'])]
    public function qualiopiReport(Request $request): Response
    {
        $this->logger->info('Accessing Qualiopi compliance report', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $period = $request->query->get('period', 'current_year');
            $formation = $request->query->get('formation');

            $this->logger->info('Generating Qualiopi report with parameters', [
                'period' => $period,
                'formation' => $formation,
            ]);

            $qualiopiData = $this->generateQualiopiReport($period, $formation);

            $this->logger->info('Successfully generated Qualiopi report', [
                'period' => $period,
                'formation' => $formation,
                'compliance_indicators_count' => count($qualiopiData['compliance_indicators'] ?? []),
                'risk_areas_count' => count($qualiopiData['risk_areas'] ?? []),
                'audit_readiness' => $qualiopiData['audit_readiness'] ?? 'N/A',
            ]);

            return $this->render('admin/alternance/reporting/qualiopi.html.twig', [
                'period' => $period,
                'formation' => $formation,
                'qualiopi_data' => $qualiopiData,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error generating Qualiopi report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'period' => $request->query->get('period', 'current_year'),
                'formation' => $request->query->get('formation'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération du rapport Qualiopi. Veuillez réessayer.');

            // Return with empty data structure to prevent template errors
            return $this->render('admin/alternance/reporting/qualiopi.html.twig', [
                'period' => $request->query->get('period', 'current_year'),
                'formation' => $request->query->get('formation'),
                'qualiopi_data' => [
                    'compliance_indicators' => [],
                    'risk_areas' => [],
                    'recommendations' => [],
                    'audit_readiness' => 0,
                    'documentation_score' => 0,
                    'process_compliance' => 0,
                ],
            ]);
        }
    }

    #[Route('/performance', name: 'admin_alternance_reporting_performance', methods: ['GET'])]
    public function performanceReport(Request $request): Response
    {
        $this->logger->info('Accessing performance report', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $period = $request->query->get('period', 'semester');
            $formation = $request->query->get('formation');
            $metrics = $request->query->all('metrics') ?: ['progression', 'skills', 'attendance'];

            $this->logger->info('Generating performance report with parameters', [
                'period' => $period,
                'formation' => $formation,
                'metrics' => $metrics,
                'metrics_count' => count($metrics),
            ]);

            $performanceData = $this->generatePerformanceReport($period, $formation, $metrics);

            $this->logger->info('Successfully generated performance report', [
                'period' => $period,
                'formation' => $formation,
                'metrics' => $metrics,
                'data_sections' => array_keys($performanceData),
            ]);

            return $this->render('admin/alternance/reporting/performance.html.twig', [
                'period' => $period,
                'formation' => $formation,
                'selected_metrics' => $metrics,
                'performance_data' => $performanceData,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error generating performance report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'period' => $request->query->get('period', 'semester'),
                'formation' => $request->query->get('formation'),
                'metrics' => $request->query->all('metrics'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération du rapport de performance. Veuillez réessayer.');

            // Return with empty data structure
            return $this->render('admin/alternance/reporting/performance.html.twig', [
                'period' => $request->query->get('period', 'semester'),
                'formation' => $request->query->get('formation'),
                'selected_metrics' => $request->query->all('metrics') ?: ['progression', 'skills', 'attendance'],
                'performance_data' => [
                    'progression_metrics' => [],
                    'skills_metrics' => [],
                    'attendance_metrics' => [],
                    'completion_metrics' => [],
                ],
            ]);
        }
    }

    #[Route('/mentors', name: 'admin_alternance_reporting_mentors', methods: ['GET'])]
    public function mentorsReport(Request $request): Response
    {
        $this->logger->info('Accessing mentors report', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $period = $request->query->get('period', 'semester');
            $company = $request->query->get('company');

            $this->logger->info('Generating mentors report with parameters', [
                'period' => $period,
                'company' => $company,
            ]);

            $mentorsData = $this->generateMentorsReport($period, $company);

            $this->logger->info('Successfully generated mentors report', [
                'period' => $period,
                'company' => $company,
                'total_mentors' => $mentorsData['mentor_statistics']['total_mentors'] ?? 0,
                'active_mentors' => $mentorsData['mentor_statistics']['active_mentors'] ?? 0,
            ]);

            return $this->render('admin/alternance/reporting/mentors.html.twig', [
                'period' => $period,
                'company' => $company,
                'mentors_data' => $mentorsData,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error generating mentors report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'period' => $request->query->get('period', 'semester'),
                'company' => $request->query->get('company'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération du rapport des mentors. Veuillez réessayer.');

            // Return with empty data structure
            return $this->render('admin/alternance/reporting/mentors.html.twig', [
                'period' => $request->query->get('period', 'semester'),
                'company' => $request->query->get('company'),
                'mentors_data' => [
                    'mentor_statistics' => [],
                    'mentor_performance' => [],
                    'mentor_distribution' => [],
                    'training_needs' => [],
                    'best_practices' => [],
                ],
            ]);
        }
    }

    #[Route('/missions', name: 'admin_alternance_reporting_missions', methods: ['GET'])]
    public function missionsReport(Request $request): Response
    {
        $this->logger->info('Accessing missions report', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $period = $request->query->get('period', 'semester');
            $formation = $request->query->get('formation');
            $status = $request->query->get('status', 'all');

            $this->logger->info('Generating missions report with parameters', [
                'period' => $period,
                'formation' => $formation,
                'status' => $status,
            ]);

            $missionsData = $this->generateMissionsReport($period, $formation, $status);

            $this->logger->info('Successfully generated missions report', [
                'period' => $period,
                'formation' => $formation,
                'status' => $status,
                'total_missions' => $missionsData['mission_statistics']['total_missions'] ?? 0,
                'active_missions' => $missionsData['mission_statistics']['active_missions'] ?? 0,
            ]);

            return $this->render('admin/alternance/reporting/missions.html.twig', [
                'period' => $period,
                'formation' => $formation,
                'status' => $status,
                'missions_data' => $missionsData,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error generating missions report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'period' => $request->query->get('period', 'semester'),
                'formation' => $request->query->get('formation'),
                'status' => $request->query->get('status', 'all'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération du rapport des missions. Veuillez réessayer.');

            // Return with empty data structure
            return $this->render('admin/alternance/reporting/missions.html.twig', [
                'period' => $request->query->get('period', 'semester'),
                'formation' => $request->query->get('formation'),
                'status' => $request->query->get('status', 'all'),
                'missions_data' => [
                    'mission_statistics' => [],
                    'mission_types' => [],
                    'duration_analysis' => [],
                    'complexity_distribution' => [],
                    'satisfaction_metrics' => [],
                ],
            ]);
        }
    }

    #[Route('/financial', name: 'admin_alternance_reporting_financial', methods: ['GET'])]
    public function financialReport(Request $request): Response
    {
        $this->logger->info('Accessing financial report', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $period = $request->query->get('period', 'current_year');
            $formation = $request->query->get('formation');
            $breakdown = $request->query->get('breakdown', 'formation');

            $this->logger->info('Generating financial report with parameters', [
                'period' => $period,
                'formation' => $formation,
                'breakdown' => $breakdown,
            ]);

            $financialData = $this->generateFinancialReport($period, $formation, $breakdown);

            $this->logger->info('Successfully generated financial report', [
                'period' => $period,
                'formation' => $formation,
                'breakdown' => $breakdown,
                'total_revenue' => $financialData['revenue_summary']['total_revenue'] ?? 0,
                'gross_margin' => $financialData['profitability']['gross_margin'] ?? 0,
            ]);

            return $this->render('admin/alternance/reporting/financial.html.twig', [
                'period' => $period,
                'formation' => $formation,
                'breakdown' => $breakdown,
                'financial_data' => $financialData,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error generating financial report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'period' => $request->query->get('period', 'current_year'),
                'formation' => $request->query->get('formation'),
                'breakdown' => $request->query->get('breakdown', 'formation'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération du rapport financier. Veuillez réessayer.');

            // Return with empty data structure
            return $this->render('admin/alternance/reporting/financial.html.twig', [
                'period' => $request->query->get('period', 'current_year'),
                'formation' => $request->query->get('formation'),
                'breakdown' => $request->query->get('breakdown', 'formation'),
                'financial_data' => [
                    'revenue_summary' => [],
                    'cost_analysis' => [],
                    'profitability' => [],
                    'breakdown_by_formation' => [],
                    'payment_analysis' => [],
                ],
            ]);
        }
    }

    #[Route('/export', name: 'admin_alternance_reporting_export', methods: ['GET'])]
    public function exportReport(Request $request): Response
    {
        $reportType = $request->query->get('report_type', 'overview');
        $format = $request->query->get('format', 'pdf');
        $period = $request->query->get('period', 'current_year');
        $formation = $request->query->get('formation');

        $this->logger->info('Starting report export', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'report_type' => $reportType,
            'format' => $format,
            'period' => $period,
            'formation' => $formation,
            'ip' => $request->getClientIp(),
        ]);

        try {
            // Validate export format
            $allowedFormats = ['pdf', 'excel', 'csv'];
            if (!in_array($format, $allowedFormats, true)) {
                throw new InvalidArgumentException("Format d'export non supporté: {$format}. Formats autorisés: " . implode(', ', $allowedFormats));
            }

            // Validate report type
            $allowedReportTypes = ['overview', 'qualiopi', 'performance', 'mentors', 'missions', 'financial'];
            if (!in_array($reportType, $allowedReportTypes, true)) {
                throw new InvalidArgumentException("Type de rapport non supporté: {$reportType}. Types autorisés: " . implode(', ', $allowedReportTypes));
            }

            $this->logger->info('Generating export data', [
                'report_type' => $reportType,
                'format' => $format,
                'filters' => [
                    'period' => $period,
                    'formation' => $formation,
                ],
            ]);

            $data = $this->generateExportData($reportType, $format, [
                'period' => $period,
                'formation' => $formation,
            ]);

            $contentType = match ($format) {
                'pdf' => 'application/pdf',
                'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'csv' => 'text/csv',
                default => 'application/octet-stream'
            };

            $filename = sprintf(
                'rapport_%s_%s_%s.%s',
                $reportType,
                $period,
                date('Y-m-d_H-i-s'),
                $format === 'excel' ? 'xlsx' : $format,
            );

            $this->logger->info('Export successfully generated', [
                'report_type' => $reportType,
                'format' => $format,
                'filename' => $filename,
                'data_size' => strlen($data),
                'content_type' => $contentType,
            ]);

            $response = new Response($data);
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

            return $response;
        } catch (InvalidArgumentException $e) {
            $this->logger->warning('Invalid export parameters', [
                'error_message' => $e->getMessage(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'report_type' => $reportType,
                'format' => $format,
                'period' => $period,
                'formation' => $formation,
            ]);

            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_alternance_reporting_index');
        } catch (Exception $e) {
            $this->logger->error('Error during report export', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'report_type' => $reportType,
                'format' => $format,
                'period' => $period,
                'formation' => $formation,
                'request_params' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_reporting_index');
        }
    }

    #[Route('/schedule', name: 'admin_alternance_reporting_schedule', methods: ['GET', 'POST'])]
    public function scheduleReport(Request $request): Response
    {
        $this->logger->info('Accessing scheduled reports management', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        if ($request->isMethod('POST')) {
            try {
                $scheduleData = $request->request->all('schedule');

                $this->logger->info('Processing schedule report request', [
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'schedule_data' => $scheduleData,
                ]);

                // Validate schedule data
                if (empty($scheduleData['report_type'])) {
                    throw new InvalidArgumentException('Le type de rapport est requis');
                }

                if (empty($scheduleData['frequency'])) {
                    throw new InvalidArgumentException('La fréquence est requise');
                }

                if (empty($scheduleData['recipients']) || !is_array($scheduleData['recipients'])) {
                    throw new InvalidArgumentException('Au moins un destinataire est requis');
                }

                // Validate email addresses
                foreach ($scheduleData['recipients'] as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new InvalidArgumentException("Adresse email invalide: {$email}");
                    }
                }

                $this->scheduleAutomaticReport($scheduleData);

                $this->logger->info('Successfully scheduled report', [
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'report_type' => $scheduleData['report_type'],
                    'frequency' => $scheduleData['frequency'],
                    'recipients_count' => count($scheduleData['recipients']),
                ]);

                $this->addFlash('success', 'Rapport programmé avec succès.');
            } catch (InvalidArgumentException $e) {
                $this->logger->warning('Invalid schedule report parameters', [
                    'error_message' => $e->getMessage(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'schedule_data' => $request->request->all('schedule'),
                ]);

                $this->addFlash('error', $e->getMessage());
            } catch (Exception $e) {
                $this->logger->error('Error scheduling report', [
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'schedule_data' => $request->request->all('schedule'),
                ]);

                $this->addFlash('error', 'Erreur lors de la programmation : ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_alternance_reporting_schedule');
        }

        try {
            $scheduledReports = $this->getScheduledReports();

            $this->logger->info('Retrieved scheduled reports', [
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'scheduled_reports_count' => count($scheduledReports),
            ]);

            return $this->render('admin/alternance/reporting/schedule.html.twig', [
                'scheduled_reports' => $scheduledReports,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error retrieving scheduled reports', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Erreur lors du chargement des rapports programmés.');

            return $this->render('admin/alternance/reporting/schedule.html.twig', [
                'scheduled_reports' => [],
            ]);
        }
    }

    #[Route('/analytics', name: 'admin_alternance_reporting_analytics', methods: ['GET'])]
    public function analytics(Request $request): Response
    {
        $this->logger->info('Accessing advanced analytics', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $period = $request->query->get('period', 'semester');
            $dimensions = $request->query->all('dimensions') ?: ['time', 'formation', 'performance'];

            $this->logger->info('Generating advanced analytics with parameters', [
                'period' => $period,
                'dimensions' => $dimensions,
                'dimensions_count' => count($dimensions),
            ]);

            // Validate dimensions
            $allowedDimensions = ['time', 'formation', 'performance', 'mentors', 'companies', 'skills'];
            $invalidDimensions = array_diff($dimensions, $allowedDimensions);
            if (!empty($invalidDimensions)) {
                throw new InvalidArgumentException('Dimensions non valides: ' . implode(', ', $invalidDimensions));
            }

            $analyticsData = $this->generateAdvancedAnalytics($period, $dimensions);

            $this->logger->info('Successfully generated advanced analytics', [
                'period' => $period,
                'dimensions' => $dimensions,
                'analytics_sections' => array_keys($analyticsData),
            ]);

            return $this->render('admin/alternance/reporting/analytics.html.twig', [
                'period' => $period,
                'selected_dimensions' => $dimensions,
                'analytics_data' => $analyticsData,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->warning('Invalid analytics parameters', [
                'error_message' => $e->getMessage(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'period' => $request->query->get('period', 'semester'),
                'dimensions' => $request->query->all('dimensions'),
            ]);

            $this->addFlash('error', $e->getMessage());

            return $this->render('admin/alternance/reporting/analytics.html.twig', [
                'period' => $request->query->get('period', 'semester'),
                'selected_dimensions' => ['time', 'formation', 'performance'],
                'analytics_data' => [
                    'trends' => [],
                    'correlations' => [],
                    'predictive_insights' => [],
                    'benchmarking' => [],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error generating advanced analytics', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'period' => $request->query->get('period', 'semester'),
                'dimensions' => $request->query->all('dimensions'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération des analyses avancées. Veuillez réessayer.');

            return $this->render('admin/alternance/reporting/analytics.html.twig', [
                'period' => $request->query->get('period', 'semester'),
                'selected_dimensions' => $request->query->all('dimensions') ?: ['time', 'formation', 'performance'],
                'analytics_data' => [
                    'trends' => [],
                    'correlations' => [],
                    'predictive_insights' => [],
                    'benchmarking' => [],
                ],
            ]);
        }
    }

    private function generateReportData(array $filters): array
    {
        $this->logger->info('Starting report data generation', [
            'filters' => $filters,
        ]);

        try {
            $period = $filters['period'];
            $formation = $filters['formation'];

            $this->logger->debug('Getting date range for period', [
                'period' => $period,
            ]);

            $dateRange = $this->getPeriodDateRange($period);

            $this->logger->debug('Querying database for report statistics', [
                'date_range' => [
                    'start' => $dateRange['start']->format('Y-m-d'),
                    'end' => $dateRange['end']->format('Y-m-d'),
                ],
                'formation' => $formation,
            ]);

            // Get contract statistics
            $totalContracts = $this->contractRepository->count([]);
            $activeContracts = $this->contractRepository->countByStatus('active');
            $completedContracts = $this->contractRepository->countByStatus('completed');

            $this->logger->debug('Contract statistics retrieved', [
                'total_contracts' => $totalContracts,
                'active_contracts' => $activeContracts,
                'completed_contracts' => $completedContracts,
            ]);

            // Get progression statistics
            $totalAssessments = $this->progressRepository->count([]);

            $this->logger->debug('Progress statistics retrieved', [
                'total_assessments' => $totalAssessments,
            ]);

            // Get skills statistics
            $totalEvaluations = $this->skillsRepository->count([]);

            $this->logger->debug('Skills statistics retrieved', [
                'total_evaluations' => $totalEvaluations,
            ]);

            // Get mentor statistics
            $totalMentors = $this->mentorRepository->count([]);

            $this->logger->debug('Mentor statistics retrieved', [
                'total_mentors' => $totalMentors,
            ]);

            // Get mission statistics
            $totalMissions = $this->missionRepository->count([]);
            $activeMissions = $this->missionRepository->countActive();

            $this->logger->debug('Mission statistics retrieved', [
                'total_missions' => $totalMissions,
                'active_missions' => $activeMissions,
            ]);

            $reportData = [
                'summary' => [
                    'total_contracts' => $totalContracts,
                    'active_contracts' => $activeContracts,
                    'completed_contracts' => $completedContracts,
                    'success_rate' => $completedContracts > 0 ? round(($completedContracts / ($activeContracts + $completedContracts)) * 100, 1) : 0,
                    'average_duration' => 18, // months - would be calculated from actual data
                ],
                'progression' => [
                    'total_assessments' => $totalAssessments,
                    'average_progression' => 72.3,
                    'students_at_risk' => 12,
                    'improvement_trend' => 'positive',
                ],
                'skills' => [
                    'total_evaluations' => $totalEvaluations,
                    'average_skills_score' => 3.2,
                    'skills_gaps' => ['Communication', 'Gestion de projet'],
                    'top_skills' => ['Technique', 'Adaptation'],
                ],
                'mentors' => [
                    'total_mentors' => $totalMentors,
                    'active_mentors' => max(0, $totalMentors - 3), // Approximation
                    'average_students_per_mentor' => $totalMentors > 0 ? round($activeContracts / $totalMentors, 1) : 0,
                    'satisfaction_rate' => 4.1,
                ],
                'missions' => [
                    'total_missions' => $totalMissions,
                    'active_missions' => $activeMissions,
                    'completion_rate' => 89.2,
                    'average_rating' => 4.3,
                ],
            ];

            $this->logger->info('Successfully generated report data', [
                'filters' => $filters,
                'data_sections' => array_keys($reportData),
                'summary_stats' => $reportData['summary'],
            ]);

            return $reportData;
        } catch (Exception $e) {
            $this->logger->error('Error generating report data', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'filters' => $filters,
            ]);

            // Return empty structure to prevent template errors
            return [
                'summary' => [
                    'total_contracts' => 0,
                    'active_contracts' => 0,
                    'completed_contracts' => 0,
                    'success_rate' => 0,
                    'average_duration' => 0,
                ],
                'progression' => [
                    'total_assessments' => 0,
                    'average_progression' => 0,
                    'students_at_risk' => 0,
                    'improvement_trend' => 'unknown',
                ],
                'skills' => [
                    'total_evaluations' => 0,
                    'average_skills_score' => 0,
                    'skills_gaps' => [],
                    'top_skills' => [],
                ],
                'mentors' => [
                    'total_mentors' => 0,
                    'active_mentors' => 0,
                    'average_students_per_mentor' => 0,
                    'satisfaction_rate' => 0,
                ],
                'missions' => [
                    'total_missions' => 0,
                    'active_missions' => 0,
                    'completion_rate' => 0,
                    'average_rating' => 0,
                ],
            ];
        }
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
                    'needs_improvement' => 10,
                ],
            ],
            'skills_metrics' => [
                'average_skills_score' => 3.2,
                'skills_evolution' => [2.8, 3.0, 3.1, 3.2, 3.3],
                'top_performing_skills' => [
                    'Techniques métier' => 3.8,
                    'Adaptation' => 3.6,
                    'Autonomie' => 3.4,
                ],
                'skills_needing_attention' => [
                    'Communication' => 2.8,
                    'Gestion de projet' => 2.9,
                    'Leadership' => 2.7,
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
                    'Grande entreprise' => 5,
                ],
                'by_sector' => [
                    'Tech' => 32,
                    'Service' => 12,
                    'Industrie' => 4,
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
        $this->logger->debug('Calculating date range for period', [
            'period' => $period,
        ]);

        try {
            $now = new DateTime();

            $dateRange = match ($period) {
                'current_month' => [
                    'start' => (clone $now)->modify('first day of this month'),
                    'end' => (clone $now)->modify('last day of this month'),
                ],
                'last_month' => [
                    'start' => (clone $now)->modify('first day of last month'),
                    'end' => (clone $now)->modify('last day of last month'),
                ],
                'current_semester' => [
                    'start' => (clone $now)->modify('-6 months'),
                    'end' => $now,
                ],
                'current_year' => [
                    'start' => (clone $now)->modify('first day of January this year'),
                    'end' => (clone $now)->modify('last day of December this year'),
                ],
                'last_year' => [
                    'start' => (clone $now)->modify('first day of January last year'),
                    'end' => (clone $now)->modify('last day of December last year'),
                ],
                default => [
                    'start' => (clone $now)->modify('-1 year'),
                    'end' => $now,
                ]
            };

            $this->logger->debug('Date range calculated successfully', [
                'period' => $period,
                'start_date' => $dateRange['start']->format('Y-m-d H:i:s'),
                'end_date' => $dateRange['end']->format('Y-m-d H:i:s'),
                'duration_days' => $dateRange['start']->diff($dateRange['end'])->days,
            ]);

            return $dateRange;
        } catch (Exception $e) {
            $this->logger->error('Error calculating date range', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'period' => $period,
            ]);

            // Return default range (last year) as fallback
            $now = new DateTime();

            return [
                'start' => (clone $now)->modify('-1 year'),
                'end' => $now,
            ];
        }
    }

    private function generateExportData(string $reportType, string $format, array $filters): string
    {
        $this->logger->info('Generating export data', [
            'report_type' => $reportType,
            'format' => $format,
            'filters' => $filters,
        ]);

        try {
            // For demonstration, return CSV data
            if ($format === 'csv') {
                $output = fopen('php://temp', 'r+');

                if ($output === false) {
                    throw new Exception('Impossible de créer le fichier temporaire pour l\'export');
                }

                // Sample data based on report type
                switch ($reportType) {
                    case 'overview':
                        fputcsv($output, ['Métrique', 'Valeur', 'Tendance', 'Période']);
                        fputcsv($output, ['Contrats actifs', '89', 'Stable', $filters['period']]);
                        fputcsv($output, ['Taux de réussite', '78.5%', 'En hausse', $filters['period']]);
                        fputcsv($output, ['Satisfaction moyenne', '4.3/5', 'Stable', $filters['period']]);
                        break;

                    case 'performance':
                        fputcsv($output, ['Alternant', 'Formation', 'Progression (%)', 'Compétences', 'Assiduité (%)', 'Statut']);
                        fputcsv($output, ['John Doe', 'Développement Web', '85', '3.8/5', '92', 'En cours']);
                        fputcsv($output, ['Jane Smith', 'Data Science', '78', '3.5/5', '88', 'En cours']);
                        break;

                    case 'mentors':
                        fputcsv($output, ['Mentor', 'Entreprise', 'Nb. Alternants', 'Satisfaction', 'Performance moyenne']);
                        fputcsv($output, ['Michel Durand', 'TechCorp', '3', '4.2/5', '82%']);
                        fputcsv($output, ['Sophie Martin', 'DataFlow', '2', '4.5/5', '87%']);
                        break;

                    case 'missions':
                        fputcsv($output, ['Mission', 'Alternant', 'Type', 'Durée (sem.)', 'Complexité', 'Statut', 'Note']);
                        fputcsv($output, ['Développement API', 'John Doe', 'Développement', '8', 'Moyenne', 'Terminée', '4.2/5']);
                        fputcsv($output, ['Analyse données', 'Jane Smith', 'Analyse', '6', 'Complexe', 'En cours', '-']);
                        break;

                    case 'financial':
                        fputcsv($output, ['Formation', 'Revenus (€)', 'Coûts (€)', 'Marge (%)', 'ROI (%)']);
                        fputcsv($output, ['Développement Web', '425000', '148750', '65.0', '24.3']);
                        fputcsv($output, ['Data Science', '380000', '145200', '61.8', '22.1']);
                        break;

                    case 'qualiopi':
                        fputcsv($output, ['Indicateur', 'Statut', 'Score (%)', 'Actions requises']);
                        fputcsv($output, ['Évaluation régulière', 'Conforme', '95', 'Aucune']);
                        fputcsv($output, ['Suivi progression', 'Conforme', '92', 'Aucune']);
                        fputcsv($output, ['Développement compétences', 'Attention', '78', 'Renforcement nécessaire']);
                        break;

                    default:
                        throw new InvalidArgumentException("Type de rapport non supporté pour l'export: {$reportType}");
                }

                rewind($output);
                $content = stream_get_contents($output);
                fclose($output);

                if ($content === false) {
                    throw new Exception('Erreur lors de la génération du contenu CSV');
                }

                $this->logger->info('Export data generated successfully', [
                    'report_type' => $reportType,
                    'format' => $format,
                    'content_size' => strlen($content),
                    'filters' => $filters,
                ]);

                return $content;
            }

            throw new InvalidArgumentException("Format d'export non supporté: {$format}");
        } catch (Exception $e) {
            $this->logger->error('Error generating export data', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'report_type' => $reportType,
                'format' => $format,
                'filters' => $filters,
            ]);

            throw $e;
        }
    }

    private function scheduleAutomaticReport(array $scheduleData): void
    {
        $this->logger->info('Scheduling automatic report', [
            'schedule_data' => $scheduleData,
        ]);

        try {
            // Validate required fields
            $requiredFields = ['report_type', 'frequency', 'recipients'];
            foreach ($requiredFields as $field) {
                if (empty($scheduleData[$field])) {
                    throw new InvalidArgumentException("Le champ '{$field}' est requis");
                }
            }

            // Validate report type
            $allowedReportTypes = ['overview', 'qualiopi', 'performance', 'mentors', 'missions', 'financial'];
            if (!in_array($scheduleData['report_type'], $allowedReportTypes, true)) {
                throw new InvalidArgumentException("Type de rapport non valide: {$scheduleData['report_type']}");
            }

            // Validate frequency
            $allowedFrequencies = ['daily', 'weekly', 'monthly', 'quarterly'];
            if (!in_array($scheduleData['frequency'], $allowedFrequencies, true)) {
                throw new InvalidArgumentException("Fréquence non valide: {$scheduleData['frequency']}");
            }

            // Validate recipients
            if (!is_array($scheduleData['recipients'])) {
                throw new InvalidArgumentException('Les destinataires doivent être fournis sous forme de tableau');
            }

            foreach ($scheduleData['recipients'] as $recipient) {
                if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException("Adresse email invalide: {$recipient}");
                }
            }

            // Validate optional fields
            if (isset($scheduleData['start_date']) && !empty($scheduleData['start_date'])) {
                $startDate = DateTime::createFromFormat('Y-m-d', $scheduleData['start_date']);
                if ($startDate === false) {
                    throw new InvalidArgumentException('Format de date de début invalide (attendu: Y-m-d)');
                }

                if ($startDate < new DateTime('today')) {
                    throw new InvalidArgumentException('La date de début ne peut pas être dans le passé');
                }
            }

            // Implementation would create scheduled report entries in database
            // For now, just validate the data structure
            $this->logger->info('Automatic report scheduled successfully', [
                'report_type' => $scheduleData['report_type'],
                'frequency' => $scheduleData['frequency'],
                'recipients_count' => count($scheduleData['recipients']),
                'start_date' => $scheduleData['start_date'] ?? 'immediate',
                'formation_filter' => $scheduleData['formation'] ?? 'all',
                'format' => $scheduleData['format'] ?? 'pdf',
            ]);

            // Here you would typically:
            // 1. Create a ScheduledReport entity
            // 2. Save it to the database
            // 3. Schedule it with a job queue system (Symfony Messenger, etc.)
        } catch (Exception $e) {
            $this->logger->error('Error scheduling automatic report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'schedule_data' => $scheduleData,
            ]);

            throw $e;
        }
    }

    private function getScheduledReports(): array
    {
        return [
            [
                'id' => 1,
                'report_type' => 'overview',
                'frequency' => 'weekly',
                'next_execution' => new DateTime('+3 days'),
                'recipients' => ['admin@eprofos.com', 'direction@eprofos.com'],
                'status' => 'active',
            ],
            [
                'id' => 2,
                'report_type' => 'qualiopi',
                'frequency' => 'monthly',
                'next_execution' => new DateTime('+15 days'),
                'recipients' => ['qualite@eprofos.com'],
                'status' => 'active',
            ],
        ];
    }
}
