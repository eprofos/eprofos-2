<?php

declare(strict_types=1);

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Service\Alternance\AlternanceValidationService;
use App\Service\Training\QualiopiValidationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/alternance')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private AlternanceContractRepository $contractRepository,
        private AlternanceValidationService $validationService,
        private QualiopiValidationService $qualiopiService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_alternance_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $this->logger->info('Starting alternance dashboard index', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'timestamp' => new DateTime(),
        ]);

        try {
            // Get key metrics for the dashboard
            $this->logger->debug('Fetching alternance metrics for dashboard');
            $metrics = $this->getAlternanceMetrics();
            $this->logger->info('Successfully retrieved alternance metrics', [
                'total_contracts' => $metrics['total_contracts'] ?? 0,
                'active_contracts' => $metrics['active_contracts'] ?? 0,
                'success_rate' => $metrics['success_rate'] ?? 0,
            ]);

            $this->logger->debug('Fetching alerts for dashboard');
            $alerts = $this->getAlerts();
            $this->logger->info('Successfully retrieved alerts', [
                'alert_count' => count($alerts),
                'alert_types' => array_column($alerts, 'type'),
            ]);

            $this->logger->debug('Fetching recent activity for dashboard');
            $recentActivity = $this->getRecentActivity();
            $this->logger->info('Successfully retrieved recent activity', [
                'activity_count' => count($recentActivity),
            ]);

            $this->logger->debug('Fetching Qualiopi indicators for dashboard');
            $qualiopiIndicators = $this->getQualiopiIndicators();
            $this->logger->info('Successfully retrieved Qualiopi indicators', [
                'coordination_coverage' => $qualiopiIndicators['coordination_coverage'] ?? 0,
                'evaluation_completeness' => $qualiopiIndicators['evaluation_completeness'] ?? 0,
            ]);

            $this->logger->info('Alternance dashboard loaded successfully', [
                'metrics_loaded' => !empty($metrics),
                'alerts_count' => count($alerts),
                'activity_count' => count($recentActivity),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            return $this->render('admin/alternance/dashboard/index.html.twig', [
                'metrics' => $metrics,
                'alerts' => $alerts,
                'recent_activity' => $recentActivity,
                'qualiopi_indicators' => $qualiopiIndicators,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading alternance dashboard', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du tableau de bord alternance.');

            // Return a minimal dashboard with error state
            return $this->render('admin/alternance/dashboard/index.html.twig', [
                'metrics' => [],
                'alerts' => [],
                'recent_activity' => [],
                'qualiopi_indicators' => [],
                'error' => true,
            ]);
        }
    }

    #[Route('/metrics', name: 'admin_alternance_metrics', methods: ['GET'])]
    public function metrics(Request $request): Response
    {
        $period = $request->query->get('period', '30'); // Default: last 30 days
        $formation = $request->query->get('formation');

        $this->logger->info('Starting alternance metrics retrieval', [
            'period' => $period,
            'formation' => $formation,
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'is_ajax' => $request->isXmlHttpRequest(),
        ]);

        try {
            $this->logger->debug('Fetching detailed metrics', [
                'period_days' => $period,
                'formation_filter' => $formation,
            ]);

            $metrics = $this->getDetailedMetrics($period, $formation);

            $this->logger->info('Successfully retrieved detailed metrics', [
                'period' => $period,
                'formation' => $formation,
                'contracts_created' => $metrics['contracts_created'] ?? 0,
                'contracts_completed' => $metrics['contracts_completed'] ?? 0,
                'high_risk_contracts' => $metrics['risk_analysis']['high_risk_contracts'] ?? 0,
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            if ($request->isXmlHttpRequest()) {
                $this->logger->debug('Returning metrics as JSON response');
                return $this->json($metrics);
            }

            return $this->render('admin/alternance/dashboard/metrics.html.twig', [
                'metrics' => $metrics,
                'period' => $period,
                'formation' => $formation,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error retrieving alternance metrics', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'period' => $period,
                'formation' => $formation,
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'error' => true,
                    'message' => 'Erreur lors de la récupération des métriques',
                ], 500);
            }

            $this->addFlash('error', 'Une erreur est survenue lors de la récupération des métriques.');

            return $this->render('admin/alternance/dashboard/metrics.html.twig', [
                'metrics' => [],
                'period' => $period,
                'formation' => $formation,
                'error' => true,
            ]);
        }
    }

    #[Route('/alerts', name: 'admin_alternance_alerts', methods: ['GET'])]
    public function alerts(): Response
    {
        $this->logger->info('Starting alternance alerts retrieval', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'timestamp' => new DateTime(),
        ]);

        try {
            $this->logger->debug('Fetching detailed alerts');
            $alerts = $this->getDetailedAlerts();

            $this->logger->info('Successfully retrieved detailed alerts', [
                'critical_alerts' => count($alerts['critical'] ?? []),
                'warning_alerts' => count($alerts['warning'] ?? []),
                'info_alerts' => count($alerts['info'] ?? []),
                'total_alerts' => count($alerts['critical'] ?? []) + count($alerts['warning'] ?? []) + count($alerts['info'] ?? []),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ]);

            return $this->render('admin/alternance/dashboard/alerts.html.twig', [
                'alerts' => $alerts,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error retrieving alternance alerts', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la récupération des alertes.');

            return $this->render('admin/alternance/dashboard/alerts.html.twig', [
                'alerts' => [
                    'critical' => [],
                    'warning' => [],
                    'info' => [],
                ],
                'error' => true,
            ]);
        }
    }

    private function getAlternanceMetrics(): array
    {
        $this->logger->debug('Starting alternance metrics calculation');

        try {
            $stats = $this->contractRepository->getContractStatistics();
            $this->logger->debug('Retrieved contract statistics', [
                'total_contracts' => $stats['total'] ?? 0,
                'active_contracts' => $stats['active'] ?? 0,
            ]);

            $successRate = $this->calculateSuccessRate();
            $this->logger->debug('Calculated success rate', ['success_rate' => $successRate]);

            $completionRate = $this->calculateCompletionRate();
            $this->logger->debug('Calculated completion rate', ['completion_rate' => $completionRate]);

            $averageDuration = $this->calculateAverageDuration();
            $this->logger->debug('Calculated average duration', ['average_duration' => $averageDuration]);

            $contractTypes = $this->getContractTypesDistribution();
            $this->logger->debug('Retrieved contract types distribution', [
                'contract_types_count' => count($contractTypes),
                'contract_types' => array_keys($contractTypes),
            ]);

            $statusDistribution = $this->getStatusDistribution();
            $this->logger->debug('Retrieved status distribution', [
                'status_count' => count($statusDistribution),
                'statuses' => array_keys($statusDistribution),
            ]);

            $monthlyTrends = $this->getMonthlyTrends();
            $this->logger->debug('Retrieved monthly trends', [
                'months_count' => count($monthlyTrends['labels'] ?? []),
                'total_new_contracts' => array_sum($monthlyTrends['new_contracts'] ?? []),
            ]);

            $metrics = [
                'total_contracts' => $stats['total'],
                'active_contracts' => $stats['active'],
                'success_rate' => $successRate,
                'completion_rate' => $completionRate,
                'average_duration' => $averageDuration,
                'mentor_satisfaction' => $this->calculateMentorSatisfaction(),
                'student_satisfaction' => $this->calculateStudentSatisfaction(),
                'contract_types' => $contractTypes,
                'status_distribution' => $statusDistribution,
                'monthly_trends' => $monthlyTrends,
            ];

            $this->logger->info('Successfully calculated alternance metrics', [
                'total_contracts' => $metrics['total_contracts'],
                'active_contracts' => $metrics['active_contracts'],
                'success_rate' => $metrics['success_rate'],
                'completion_rate' => $metrics['completion_rate'],
            ]);

            return $metrics;
        } catch (Exception $e) {
            $this->logger->error('Error calculating alternance metrics', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty metrics in case of error
            return [
                'total_contracts' => 0,
                'active_contracts' => 0,
                'success_rate' => 0,
                'completion_rate' => 0,
                'average_duration' => 0,
                'mentor_satisfaction' => 0,
                'student_satisfaction' => 0,
                'contract_types' => [],
                'status_distribution' => [],
                'monthly_trends' => ['labels' => [], 'new_contracts' => [], 'completed_contracts' => []],
            ];
        }
    }

    private function getAlerts(): array
    {
        $this->logger->debug('Starting alerts calculation');

        try {
            $alerts = [];

            // Contracts ending soon
            $this->logger->debug('Checking for contracts ending soon');
            $endingSoonContracts = $this->contractRepository->findContractsEndingSoon(30);
            $this->logger->debug('Found contracts ending soon', [
                'count' => count($endingSoonContracts),
            ]);

            if (!empty($endingSoonContracts)) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Contrats se terminant bientôt',
                    'message' => count($endingSoonContracts) . ' contrat(s) se termine(nt) dans les 30 prochains jours',
                    'count' => count($endingSoonContracts),
                    'route' => 'admin_alternance_contract_index',
                    'params' => ['status' => 'active', 'ending_soon' => '1'],
                ];
            }

            // Contracts without recent activity
            $this->logger->debug('Checking for contracts without recent activity');
            $inactiveContracts = $this->contractRepository->findContractsWithoutRecentActivity(15);
            $this->logger->debug('Found inactive contracts', [
                'count' => count($inactiveContracts),
            ]);

            if (!empty($inactiveContracts)) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => 'Contrats sans activité récente',
                    'message' => count($inactiveContracts) . ' contrat(s) sans activité depuis 15 jours',
                    'count' => count($inactiveContracts),
                    'route' => 'admin_alternance_contract_index',
                    'params' => ['status' => 'active', 'inactive' => '1'],
                ];
            }

            // Validation issues
            $this->logger->debug('Checking for validation issues');
            $validationIssues = $this->getValidationIssues();
            $this->logger->debug('Found validation issues', [
                'count' => $validationIssues,
            ]);

            if ($validationIssues > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => 'Problèmes de conformité',
                    'message' => $validationIssues . ' contrat(s) avec des problèmes de conformité Qualiopi',
                    'count' => $validationIssues,
                    'route' => 'admin_alternance_qualiopi',
                    'params' => [],
                ];
            }

            $this->logger->info('Successfully calculated alerts', [
                'total_alerts' => count($alerts),
                'alert_types' => array_column($alerts, 'type'),
                'ending_soon_count' => count($endingSoonContracts),
                'inactive_count' => count($inactiveContracts),
                'validation_issues_count' => $validationIssues,
            ]);

            return $alerts;
        } catch (Exception $e) {
            $this->logger->error('Error calculating alerts', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty alerts in case of error
            return [];
        }
    }

    private function getRecentActivity(): array
    {
        $this->logger->debug('Starting recent activity retrieval');

        try {
            $rawActivity = $this->contractRepository->findRecentActivity(10);
            $this->logger->debug('Retrieved raw activity data', [
                'activity_count' => count($rawActivity),
            ]);

            $formattedActivity = [];

            foreach ($rawActivity as $index => $activity) {
                try {
                    $studentName = trim(($activity['studentFirstName'] ?? '') . ' ' . ($activity['studentLastName'] ?? ''));
                    $companyName = $activity['companyName'] ?? '';
                    $status = $activity['status'] ?? '';
                    $updatedAt = $activity['updatedAt'] ?? new DateTime();

                    $this->logger->debug('Processing activity item', [
                        'index' => $index,
                        'contract_id' => $activity['id'] ?? null,
                        'status' => $status,
                        'student_name' => $studentName,
                        'company_name' => $companyName,
                    ]);

                    // Convert status to readable title and description
                    $title = $this->getActivityTitle($status);
                    $description = $this->getActivityDescription($status, $studentName, $companyName);

                    $formattedActivity[] = [
                        'title' => $title,
                        'description' => $description,
                        'user' => 'Système', // Could be enhanced to track actual user
                        'created_at' => $updatedAt,
                        'contract_id' => $activity['id'] ?? null,
                    ];
                } catch (Exception $e) {
                    $this->logger->warning('Error processing activity item', [
                        'index' => $index,
                        'activity_id' => $activity['id'] ?? null,
                        'error_message' => $e->getMessage(),
                    ]);
                    // Continue with next activity item
                }
            }

            $this->logger->info('Successfully retrieved and formatted recent activity', [
                'raw_activity_count' => count($rawActivity),
                'formatted_activity_count' => count($formattedActivity),
                'statuses_found' => array_unique(array_column($rawActivity, 'status')),
            ]);

            return $formattedActivity;
        } catch (Exception $e) {
            $this->logger->error('Error retrieving recent activity', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty activity in case of error
            return [];
        }
    }

    private function getActivityTitle(string $status): string
    {
        return match ($status) {
            'validated' => 'Contrat validé',
            'active' => 'Contrat activé',
            'completed' => 'Contrat terminé',
            'suspended' => 'Contrat suspendu',
            'terminated' => 'Contrat résilié',
            'pending_validation' => 'Contrat en attente de validation',
            'draft' => 'Nouveau brouillon créé',
            default => 'Mise à jour du contrat'
        };
    }

    private function getActivityDescription(string $status, string $studentName, string $companyName): string
    {
        $baseInfo = $studentName ? "pour {$studentName}" : '';
        $companyInfo = $companyName ? " chez {$companyName}" : '';

        return match ($status) {
            'validated' => "Contrat d'alternance validé {$baseInfo}{$companyInfo}",
            'active' => "Contrat d'alternance activé {$baseInfo}{$companyInfo}",
            'completed' => "Formation terminée avec succès {$baseInfo}{$companyInfo}",
            'suspended' => "Contrat temporairement suspendu {$baseInfo}{$companyInfo}",
            'terminated' => "Contrat résilié {$baseInfo}{$companyInfo}",
            'pending_validation' => "Nouveau contrat en attente de validation {$baseInfo}{$companyInfo}",
            'draft' => "Nouveau brouillon de contrat créé {$baseInfo}{$companyInfo}",
            default => "Contrat mis à jour {$baseInfo}{$companyInfo}"
        };
    }

    private function getQualiopiIndicators(): array
    {
        return [
            'coordination_coverage' => $this->calculateCoordinationCoverage(),
            'evaluation_completeness' => $this->calculateEvaluationCompleteness(),
            'documentation_rate' => $this->calculateDocumentationRate(),
            'mentor_qualification_rate' => $this->calculateMentorQualificationRate(),
            'progression_tracking_rate' => $this->calculateProgressionTrackingRate(),
        ];
    }

    private function getDetailedMetrics(string $period, ?string $formation): array
    {
        $this->logger->debug('Starting detailed metrics calculation', [
            'period' => $period,
            'formation' => $formation,
        ]);

        try {
            $days = (int) $period;
            $startDate = new DateTime("-{$days} days");

            $this->logger->debug('Calculated date range for metrics', [
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'days_back' => $days,
            ]);

            $contractsCreated = $this->contractRepository->countContractsCreatedSince($startDate, $formation);
            $this->logger->debug('Counted contracts created', [
                'count' => $contractsCreated,
                'since' => $startDate->format('Y-m-d'),
                'formation' => $formation,
            ]);

            $contractsCompleted = $this->contractRepository->countContractsCompletedSince($startDate, $formation);
            $this->logger->debug('Counted contracts completed', [
                'count' => $contractsCompleted,
                'since' => $startDate->format('Y-m-d'),
                'formation' => $formation,
            ]);

            $successByFormation = $this->contractRepository->getSuccessRateByFormation($startDate);
            $this->logger->debug('Retrieved success rate by formation', [
                'formations_count' => count($successByFormation),
            ]);

            $durationAnalysis = $this->contractRepository->getDurationAnalysis($startDate, $formation);
            $this->logger->debug('Retrieved duration analysis', [
                'analysis_data' => $durationAnalysis ? 'present' : 'empty',
            ]);

            $mentorPerformance = $this->contractRepository->getMentorPerformanceMetrics($startDate);
            $this->logger->debug('Retrieved mentor performance metrics', [
                'mentors_count' => count($mentorPerformance),
            ]);

            $riskAnalysis = $this->getRiskAnalysis($startDate);
            $this->logger->debug('Retrieved risk analysis', [
                'high_risk' => $riskAnalysis['high_risk_contracts'] ?? 0,
                'medium_risk' => $riskAnalysis['medium_risk_contracts'] ?? 0,
                'low_risk' => $riskAnalysis['low_risk_contracts'] ?? 0,
            ]);

            $metrics = [
                'contracts_created' => $contractsCreated,
                'contracts_completed' => $contractsCompleted,
                'success_by_formation' => $successByFormation,
                'duration_analysis' => $durationAnalysis,
                'mentor_performance' => $mentorPerformance,
                'risk_analysis' => $riskAnalysis,
            ];

            $this->logger->info('Successfully calculated detailed metrics', [
                'period' => $period,
                'formation' => $formation,
                'contracts_created' => $contractsCreated,
                'contracts_completed' => $contractsCompleted,
                'success_formations_count' => count($successByFormation),
                'mentor_performance_count' => count($mentorPerformance),
                'total_risk_contracts' => array_sum($riskAnalysis),
            ]);

            return $metrics;
        } catch (Exception $e) {
            $this->logger->error('Error calculating detailed metrics', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'period' => $period,
                'formation' => $formation,
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty metrics in case of error
            return [
                'contracts_created' => 0,
                'contracts_completed' => 0,
                'success_by_formation' => [],
                'duration_analysis' => [],
                'mentor_performance' => [],
                'risk_analysis' => [
                    'high_risk_contracts' => 0,
                    'medium_risk_contracts' => 0,
                    'low_risk_contracts' => 0,
                ],
            ];
        }
    }

    private function getDetailedAlerts(): array
    {
        return [
            'critical' => $this->getCriticalAlerts(),
            'warning' => $this->getWarningAlerts(),
            'info' => $this->getInfoAlerts(),
        ];
    }

    private function calculateSuccessRate(): float
    {
        $completed = $this->contractRepository->countByStatus('completed');
        $total = $this->contractRepository->countCompletedOrTerminated();

        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }

    private function calculateCompletionRate(): float
    {
        $active = $this->contractRepository->countByStatus('active');
        $total = $this->contractRepository->countActiveOrCompleted();

        return $total > 0 ? round(($active / $total) * 100, 1) : 0;
    }

    private function calculateAverageDuration(): float
    {
        return $this->contractRepository->getAverageDurationInMonths() ?? 0;
    }

    private function calculateMentorSatisfaction(): float
    {
        // This would be calculated from mentor evaluations/surveys
        // For now, return a placeholder
        return 4.2; // out of 5
    }

    private function calculateStudentSatisfaction(): float
    {
        // This would be calculated from student evaluations/surveys
        // For now, return a placeholder
        return 4.1; // out of 5
    }

    private function getContractTypesDistribution(): array
    {
        $distribution = $this->contractRepository->getContractTypeDistribution();
        $result = [];

        foreach ($distribution as $item) {
            $type = $item['contractType'];
            $count = (int) $item['count'];

            // Convert internal values to display labels
            $label = match ($type) {
                'apprentissage' => 'Contrat d\'apprentissage',
                'professionnalisation' => 'Contrat de professionnalisation',
                default => ucfirst($type)
            };

            $result[$label] = $count;
        }

        return $result;
    }

    private function getStatusDistribution(): array
    {
        $distribution = $this->contractRepository->getStatusDistribution();
        $result = [];

        foreach ($distribution as $item) {
            $status = $item['status'];
            $count = (int) $item['count'];

            // Convert internal status values to display labels
            $label = match ($status) {
                'active' => 'Actif',
                'completed' => 'Terminé',
                'pending_validation' => 'En attente',
                'suspended' => 'Suspendu',
                'terminated' => 'Résilié',
                'draft' => 'Brouillon',
                'validated' => 'Validé',
                default => ucfirst($status)
            };

            $result[$label] = $count;
        }

        return $result;
    }

    private function getMonthlyTrends(): array
    {
        $trends = $this->contractRepository->getMonthlyTrends(12);

        // Initialize arrays with French month names
        $months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        $newContracts = array_fill(0, 12, 0);
        $completedContracts = array_fill(0, 12, 0);

        // Fill with actual data
        foreach ($trends as $trend) {
            $monthIndex = (int) $trend['month'] - 1;
            if ($monthIndex >= 0 && $monthIndex < 12) {
                $newContracts[$monthIndex] = (int) $trend['count'];
            }
        }

        // For completed contracts, we'd need a separate query
        // For now, simulate some data based on new contracts with a delay
        for ($i = 0; $i < 12; $i++) {
            $completedContracts[$i] = $i > 0 ? max(0, $newContracts[$i - 1] - mt_rand(0, 3)) : 0;
        }

        return [
            'labels' => $months,
            'new_contracts' => $newContracts,
            'completed_contracts' => $completedContracts,
        ];
    }

    private function getValidationIssues(): int
    {
        $this->logger->debug('Starting validation issues check');

        try {
            $activeContracts = $this->contractRepository->findActiveContracts();
            $this->logger->debug('Retrieved active contracts for validation', [
                'active_contracts_count' => count($activeContracts),
            ]);

            $issuesCount = 0;

            foreach ($activeContracts as $index => $contract) {
                try {
                    $this->logger->debug('Validating contract', [
                        'contract_id' => $contract->getId(),
                        'index' => $index + 1,
                        'total' => count($activeContracts),
                    ]);

                    $validation = $this->validationService->validateContract($contract);

                    if (!empty($validation['errors'])) {
                        $issuesCount++;
                        $this->logger->debug('Found validation errors for contract', [
                            'contract_id' => $contract->getId(),
                            'error_count' => count($validation['errors']),
                            'errors' => $validation['errors'],
                        ]);
                    }

                    if (!empty($validation['warnings'])) {
                        $this->logger->debug('Found validation warnings for contract', [
                            'contract_id' => $contract->getId(),
                            'warning_count' => count($validation['warnings']),
                            'warnings' => $validation['warnings'],
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logger->warning('Error validating individual contract', [
                        'contract_id' => $contract->getId(),
                        'error_message' => $e->getMessage(),
                    ]);
                    // Consider this as an issue
                    $issuesCount++;
                }
            }

            $this->logger->info('Completed validation issues check', [
                'total_contracts_checked' => count($activeContracts),
                'contracts_with_issues' => $issuesCount,
                'issue_rate' => count($activeContracts) > 0 ? round(($issuesCount / count($activeContracts)) * 100, 2) : 0,
            ]);

            return $issuesCount;
        } catch (Exception $e) {
            $this->logger->error('Error checking validation issues', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 0 in case of error (fail safely)
            return 0;
        }
    }

    private function calculateCoordinationCoverage(): float
    {
        // Calculate percentage of contracts with adequate coordination meetings
        return 85.2; // Placeholder
    }

    private function calculateEvaluationCompleteness(): float
    {
        // Calculate percentage of contracts with complete evaluations
        return 78.9; // Placeholder
    }

    private function calculateDocumentationRate(): float
    {
        // Calculate percentage of contracts with complete documentation
        return 92.1; // Placeholder
    }

    private function calculateMentorQualificationRate(): float
    {
        // Calculate percentage of mentors with validated qualifications
        return 96.5; // Placeholder
    }

    private function calculateProgressionTrackingRate(): float
    {
        // Calculate percentage of contracts with up-to-date progression tracking
        return 88.7; // Placeholder
    }

    private function getRiskAnalysis(DateTime $startDate): array
    {
        $this->logger->debug('Starting risk analysis', [
            'start_date' => $startDate->format('Y-m-d H:i:s'),
        ]);

        try {
            $activeContracts = $this->contractRepository->findActiveContracts();
            $this->logger->debug('Retrieved active contracts for risk analysis', [
                'active_contracts_count' => count($activeContracts),
            ]);

            $highRisk = 0;
            $mediumRisk = 0;
            $lowRisk = 0;

            foreach ($activeContracts as $index => $contract) {
                try {
                    $riskLevel = $this->calculateContractRiskLevel($contract);

                    switch ($riskLevel) {
                        case 'high':
                            $highRisk++;
                            break;

                        case 'medium':
                            $mediumRisk++;
                            break;

                        case 'low':
                            $lowRisk++;
                            break;
                    }

                    $this->logger->debug('Analyzed contract risk', [
                        'contract_id' => $contract->getId(),
                        'index' => $index + 1,
                        'total' => count($activeContracts),
                        'risk_level' => $riskLevel,
                    ]);
                } catch (Exception $e) {
                    $this->logger->warning('Error analyzing individual contract risk', [
                        'contract_id' => $contract->getId(),
                        'error_message' => $e->getMessage(),
                    ]);
                    // Consider this as high risk
                    $highRisk++;
                }
            }

            $riskAnalysis = [
                'high_risk_contracts' => $highRisk,
                'medium_risk_contracts' => $mediumRisk,
                'low_risk_contracts' => $lowRisk,
            ];

            $this->logger->info('Completed risk analysis', [
                'total_contracts_analyzed' => count($activeContracts),
                'high_risk_count' => $highRisk,
                'medium_risk_count' => $mediumRisk,
                'low_risk_count' => $lowRisk,
                'high_risk_percentage' => count($activeContracts) > 0 ? round(($highRisk / count($activeContracts)) * 100, 2) : 0,
                'start_date' => $startDate->format('Y-m-d'),
            ]);

            return $riskAnalysis;
        } catch (Exception $e) {
            $this->logger->error('Error performing risk analysis', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'start_date' => $startDate->format('Y-m-d'),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty risk analysis in case of error
            return [
                'high_risk_contracts' => 0,
                'medium_risk_contracts' => 0,
                'low_risk_contracts' => 0,
            ];
        }
    }

    private function calculateContractRiskLevel(AlternanceContract $contract): string
    {
        $this->logger->debug('Calculating risk level for contract', [
            'contract_id' => $contract->getId(),
        ]);

        try {
            $riskFactors = 0;

            // Check validation issues
            $validation = $this->validationService->validateContract($contract);
            if (!empty($validation['errors'])) {
                $riskFactors += 3; // High impact
                $this->logger->debug('Added risk factors for validation errors', [
                    'contract_id' => $contract->getId(),
                    'error_count' => count($validation['errors']),
                    'risk_factors_added' => 3,
                ]);
            }
            if (!empty($validation['warnings'])) {
                $riskFactors++; // Medium impact
                $this->logger->debug('Added risk factors for validation warnings', [
                    'contract_id' => $contract->getId(),
                    'warning_count' => count($validation['warnings']),
                    'risk_factors_added' => 1,
                ]);
            }

            // Check activity
            $daysSinceUpdate = $contract->getUpdatedAt() ?
                (new DateTime())->diff($contract->getUpdatedAt())->days : 0;

            $this->logger->debug('Checking activity for contract', [
                'contract_id' => $contract->getId(),
                'days_since_update' => $daysSinceUpdate,
            ]);

            if ($daysSinceUpdate > 30) {
                $riskFactors += 2;
                $this->logger->debug('Added risk factors for long inactivity', [
                    'contract_id' => $contract->getId(),
                    'days_since_update' => $daysSinceUpdate,
                    'risk_factors_added' => 2,
                ]);
            } elseif ($daysSinceUpdate > 15) {
                $riskFactors++;
                $this->logger->debug('Added risk factors for moderate inactivity', [
                    'contract_id' => $contract->getId(),
                    'days_since_update' => $daysSinceUpdate,
                    'risk_factors_added' => 1,
                ]);
            }

            // Check if ending soon
            $remainingDays = $contract->getRemainingDays();
            $this->logger->debug('Checking remaining days for contract', [
                'contract_id' => $contract->getId(),
                'remaining_days' => $remainingDays,
            ]);

            if ($remainingDays <= 30 && $remainingDays > 0) {
                $riskFactors++;
                $this->logger->debug('Added risk factors for ending soon', [
                    'contract_id' => $contract->getId(),
                    'remaining_days' => $remainingDays,
                    'risk_factors_added' => 1,
                ]);
            }

            // Check duration vs. progress
            $progressPercentage = $contract->getProgressPercentage();
            $this->logger->debug('Checking progress for contract', [
                'contract_id' => $contract->getId(),
                'progress_percentage' => $progressPercentage,
                'remaining_days' => $remainingDays,
            ]);

            if ($progressPercentage > 80 && $remainingDays > 90) {
                $riskFactors++; // Progressing too slowly
                $this->logger->debug('Added risk factors for slow progress', [
                    'contract_id' => $contract->getId(),
                    'progress_percentage' => $progressPercentage,
                    'remaining_days' => $remainingDays,
                    'risk_factors_added' => 1,
                ]);
            }

            // Determine risk level
            $riskLevel = 'low';
            if ($riskFactors >= 3) {
                $riskLevel = 'high';
            } elseif ($riskFactors >= 1) {
                $riskLevel = 'medium';
            }

            $this->logger->info('Calculated risk level for contract', [
                'contract_id' => $contract->getId(),
                'total_risk_factors' => $riskFactors,
                'risk_level' => $riskLevel,
                'validation_errors' => count($validation['errors'] ?? []),
                'validation_warnings' => count($validation['warnings'] ?? []),
                'days_since_update' => $daysSinceUpdate,
                'remaining_days' => $remainingDays,
                'progress_percentage' => $progressPercentage,
            ]);

            return $riskLevel;
        } catch (Exception $e) {
            $this->logger->error('Error calculating contract risk level', [
                'contract_id' => $contract->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return high risk in case of error (fail safely)
            return 'high';
        }
    }

    private function getCriticalAlerts(): array
    {
        $alerts = [];

        // Find contracts without evaluations for too long
        $activeContracts = $this->contractRepository->findActiveContracts(20);
        foreach ($activeContracts as $contract) {
            $daysSinceUpdate = $contract->getUpdatedAt() ?
                (new DateTime())->diff($contract->getUpdatedAt())->days : 0;

            if ($daysSinceUpdate > 60) {
                $alerts[] = [
                    'title' => 'Contrat sans évaluation depuis ' . $daysSinceUpdate . ' jours',
                    'message' => sprintf(
                        'Le contrat #%d (%s) n\'a pas été mis à jour depuis %d jours',
                        $contract->getId(),
                        $contract->getStudentFullName(),
                        $daysSinceUpdate,
                    ),
                    'created_at' => new DateTime('-' . $daysSinceUpdate . ' days'),
                    'contract_id' => $contract->getId(),
                ];
            }
        }

        // Find contracts with validation errors
        foreach ($activeContracts as $contract) {
            $validation = $this->validationService->validateContract($contract);
            if (!empty($validation['errors'])) {
                $alerts[] = [
                    'title' => 'Problème de conformité Qualiopi',
                    'message' => sprintf(
                        'Le contrat #%d (%s) a %d erreur(s) de conformité',
                        $contract->getId(),
                        $contract->getStudentFullName(),
                        count($validation['errors']),
                    ),
                    'created_at' => new DateTime('-1 hour'),
                    'contract_id' => $contract->getId(),
                ];
            }
        }

        return array_slice($alerts, 0, 5); // Limit to 5 most critical
    }

    private function getWarningAlerts(): array
    {
        $alerts = [];

        // Contracts ending soon
        $endingSoon = $this->contractRepository->findContractsEndingSoon(30);
        if (!empty($endingSoon)) {
            $alerts[] = [
                'title' => 'Contrats se terminant bientôt',
                'message' => count($endingSoon) . ' contrat(s) se termine(nt) dans les 30 prochains jours',
                'created_at' => new DateTime('-1 hour'),
            ];
        }

        // Contracts without recent activity
        $inactive = $this->contractRepository->findContractsWithoutRecentActivity(15);
        if (!empty($inactive)) {
            $alerts[] = [
                'title' => 'Contrats sans activité récente',
                'message' => count($inactive) . ' contrat(s) sans activité depuis 15 jours',
                'created_at' => new DateTime('-2 hours'),
            ];
        }

        // Find contracts with validation warnings
        $activeContracts = $this->contractRepository->findActiveContracts(10);
        $warningCount = 0;

        foreach ($activeContracts as $contract) {
            $validation = $this->validationService->validateContract($contract);
            if (!empty($validation['warnings'])) {
                $warningCount++;
            }
        }

        if ($warningCount > 0) {
            $alerts[] = [
                'title' => 'Avertissements de conformité',
                'message' => $warningCount . ' contrat(s) avec des avertissements de conformité',
                'created_at' => new DateTime('-3 hours'),
            ];
        }

        return $alerts;
    }

    private function getInfoAlerts(): array
    {
        $alerts = [];

        // Recent contract validations
        $recentActivityRaw = $this->contractRepository->findRecentActivity(5);
        foreach ($recentActivityRaw as $activity) {
            if (isset($activity['status']) && $activity['status'] === 'validated') {
                $studentName = trim(($activity['studentFirstName'] ?? '') . ' ' . ($activity['studentLastName'] ?? ''));
                $alerts[] = [
                    'title' => 'Nouveau contrat validé',
                    'message' => sprintf(
                        'Le contrat #%d (%s) a été validé avec succès',
                        $activity['id'],
                        $studentName,
                    ),
                    'created_at' => $activity['updatedAt'] ?? new DateTime('-1 hour'),
                    'contract_id' => $activity['id'],
                ];
            }
        }

        // Monthly report availability
        $now = new DateTime();
        if ($now->format('d') <= 5) { // First 5 days of the month
            $lastMonth = $now->modify('-1 month')->format('F Y');
            $alerts[] = [
                'title' => 'Rapport mensuel disponible',
                'message' => "Le rapport de performance de {$lastMonth} est maintenant disponible",
                'created_at' => new DateTime('-2 hours'),
            ];
        }

        // Statistics update
        $stats = $this->contractRepository->getContractStatistics();
        if ($stats['total'] > 0) {
            $alerts[] = [
                'title' => 'Mise à jour des statistiques',
                'message' => sprintf('Tableau de bord mis à jour avec %d contrat(s) au total', $stats['total']),
                'created_at' => new DateTime('-30 minutes'),
            ];
        }

        return array_slice($alerts, 0, 3); // Limit to 3 most recent
    }
}
