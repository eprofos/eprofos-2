<?php

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Service\Alternance\AlternanceValidationService;
use App\Service\QualiopiValidationService;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_alternance_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        // Get key metrics for the dashboard
        $metrics = $this->getAlternanceMetrics();
        $alerts = $this->getAlerts();
        $recentActivity = $this->getRecentActivity();
        $qualiopiIndicators = $this->getQualiopiIndicators();

        return $this->render('admin/alternance/dashboard/index.html.twig', [
            'metrics' => $metrics,
            'alerts' => $alerts,
            'recent_activity' => $recentActivity,
            'qualiopi_indicators' => $qualiopiIndicators,
        ]);
    }

    #[Route('/metrics', name: 'admin_alternance_metrics', methods: ['GET'])]
    public function metrics(Request $request): Response
    {
        $period = $request->query->get('period', '30'); // Default: last 30 days
        $formation = $request->query->get('formation');
        
        $metrics = $this->getDetailedMetrics($period, $formation);
        
        if ($request->isXmlHttpRequest()) {
            return $this->json($metrics);
        }

        return $this->render('admin/alternance/dashboard/metrics.html.twig', [
            'metrics' => $metrics,
            'period' => $period,
            'formation' => $formation,
        ]);
    }

    #[Route('/alerts', name: 'admin_alternance_alerts', methods: ['GET'])]
    public function alerts(): Response
    {
        $alerts = $this->getDetailedAlerts();

        return $this->render('admin/alternance/dashboard/alerts.html.twig', [
            'alerts' => $alerts,
        ]);
    }

    private function getAlternanceMetrics(): array
    {
        $stats = $this->contractRepository->getContractStatistics();
        
        return [
            'total_contracts' => $stats['total'],
            'active_contracts' => $stats['active'],
            'success_rate' => $this->calculateSuccessRate(),
            'completion_rate' => $this->calculateCompletionRate(),
            'average_duration' => $this->calculateAverageDuration(),
            'mentor_satisfaction' => $this->calculateMentorSatisfaction(),
            'student_satisfaction' => $this->calculateStudentSatisfaction(),
            'contract_types' => $this->getContractTypesDistribution(),
            'status_distribution' => $this->getStatusDistribution(),
            'monthly_trends' => $this->getMonthlyTrends(),
        ];
    }

    private function getAlerts(): array
    {
        $alerts = [];
        
        // Contracts ending soon
        $endingSoonContracts = $this->contractRepository->findContractsEndingSoon(30);
        if (!empty($endingSoonContracts)) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Contrats se terminant bientôt',
                'message' => count($endingSoonContracts) . ' contrat(s) se termine(nt) dans les 30 prochains jours',
                'count' => count($endingSoonContracts),
                'route' => 'admin_alternance_contract_index',
                'params' => ['status' => 'active', 'ending_soon' => '1']
            ];
        }

        // Contracts without recent activity
        $inactiveContracts = $this->contractRepository->findContractsWithoutRecentActivity(15);
        if (!empty($inactiveContracts)) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Contrats sans activité récente',
                'message' => count($inactiveContracts) . ' contrat(s) sans activité depuis 15 jours',
                'count' => count($inactiveContracts),
                'route' => 'admin_alternance_contract_index',
                'params' => ['status' => 'active', 'inactive' => '1']
            ];
        }

        // Validation issues
        $validationIssues = $this->getValidationIssues();
        if ($validationIssues > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Problèmes de conformité',
                'message' => $validationIssues . ' contrat(s) avec des problèmes de conformité Qualiopi',
                'count' => $validationIssues,
                'route' => 'admin_alternance_qualiopi',
                'params' => []
            ];
        }

        return $alerts;
    }

    private function getRecentActivity(): array
    {
        $rawActivity = $this->contractRepository->findRecentActivity(10);
        $formattedActivity = [];
        
        foreach ($rawActivity as $activity) {
            $studentName = trim(($activity['studentFirstName'] ?? '') . ' ' . ($activity['studentLastName'] ?? ''));
            $companyName = $activity['companyName'] ?? '';
            $status = $activity['status'] ?? '';
            $updatedAt = $activity['updatedAt'] ?? new \DateTime();
            
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
        }
        
        return $formattedActivity;
    }
    
    private function getActivityTitle(string $status): string
    {
        return match($status) {
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
        
        return match($status) {
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
        $days = (int) $period;
        $startDate = new \DateTime("-{$days} days");
        
        return [
            'contracts_created' => $this->contractRepository->countContractsCreatedSince($startDate, $formation),
            'contracts_completed' => $this->contractRepository->countContractsCompletedSince($startDate, $formation),
            'success_by_formation' => $this->contractRepository->getSuccessRateByFormation($startDate),
            'duration_analysis' => $this->contractRepository->getDurationAnalysis($startDate, $formation),
            'mentor_performance' => $this->contractRepository->getMentorPerformanceMetrics($startDate),
            'risk_analysis' => $this->getRiskAnalysis($startDate),
        ];
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
            $label = match($type) {
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
            $label = match($status) {
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
        $activeContracts = $this->contractRepository->findActiveContracts();
        $issuesCount = 0;
        
        foreach ($activeContracts as $contract) {
            $validation = $this->validationService->validateContract($contract);
            if (!empty($validation['errors'])) {
                $issuesCount++;
            }
        }
        
        return $issuesCount;
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

    private function getRiskAnalysis(\DateTime $startDate): array
    {
        $activeContracts = $this->contractRepository->findActiveContracts();
        
        $highRisk = 0;
        $mediumRisk = 0;
        $lowRisk = 0;
        
        foreach ($activeContracts as $contract) {
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
        }
        
        return [
            'high_risk_contracts' => $highRisk,
            'medium_risk_contracts' => $mediumRisk,
            'low_risk_contracts' => $lowRisk,
        ];
    }

    private function calculateContractRiskLevel(AlternanceContract $contract): string
    {
        $riskFactors = 0;
        
        // Check validation issues
        $validation = $this->validationService->validateContract($contract);
        if (!empty($validation['errors'])) {
            $riskFactors += 3; // High impact
        }
        if (!empty($validation['warnings'])) {
            $riskFactors += 1; // Medium impact
        }
        
        // Check activity
        $daysSinceUpdate = $contract->getUpdatedAt() ? 
            (new \DateTime())->diff($contract->getUpdatedAt())->days : 0;
        if ($daysSinceUpdate > 30) {
            $riskFactors += 2;
        } elseif ($daysSinceUpdate > 15) {
            $riskFactors += 1;
        }
        
        // Check if ending soon
        $remainingDays = $contract->getRemainingDays();
        if ($remainingDays <= 30 && $remainingDays > 0) {
            $riskFactors += 1;
        }
        
        // Check duration vs. progress
        $progressPercentage = $contract->getProgressPercentage();
        if ($progressPercentage > 80 && $remainingDays > 90) {
            $riskFactors += 1; // Progressing too slowly
        }
        
        // Determine risk level
        if ($riskFactors >= 3) {
            return 'high';
        } elseif ($riskFactors >= 1) {
            return 'medium';
        }
        
        return 'low';
    }

    private function getCriticalAlerts(): array
    {
        $alerts = [];
        
        // Find contracts without evaluations for too long
        $activeContracts = $this->contractRepository->findActiveContracts(20);
        foreach ($activeContracts as $contract) {
            $daysSinceUpdate = $contract->getUpdatedAt() ? 
                (new \DateTime())->diff($contract->getUpdatedAt())->days : 0;
                
            if ($daysSinceUpdate > 60) {
                $alerts[] = [
                    'title' => 'Contrat sans évaluation depuis ' . $daysSinceUpdate . ' jours',
                    'message' => sprintf('Le contrat #%d (%s) n\'a pas été mis à jour depuis %d jours', 
                        $contract->getId(), 
                        $contract->getStudentFullName(), 
                        $daysSinceUpdate
                    ),
                    'created_at' => new \DateTime('-' . $daysSinceUpdate . ' days'),
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
                    'message' => sprintf('Le contrat #%d (%s) a %d erreur(s) de conformité',
                        $contract->getId(),
                        $contract->getStudentFullName(),
                        count($validation['errors'])
                    ),
                    'created_at' => new \DateTime('-1 hour'),
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
                'created_at' => new \DateTime('-1 hour'),
            ];
        }
        
        // Contracts without recent activity
        $inactive = $this->contractRepository->findContractsWithoutRecentActivity(15);
        if (!empty($inactive)) {
            $alerts[] = [
                'title' => 'Contrats sans activité récente',
                'message' => count($inactive) . ' contrat(s) sans activité depuis 15 jours',
                'created_at' => new \DateTime('-2 hours'),
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
                'created_at' => new \DateTime('-3 hours'),
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
                    'message' => sprintf('Le contrat #%d (%s) a été validé avec succès',
                        $activity['id'],
                        $studentName
                    ),
                    'created_at' => $activity['updatedAt'] ?? new \DateTime('-1 hour'),
                    'contract_id' => $activity['id'],
                ];
            }
        }
        
        // Monthly report availability
        $now = new \DateTime();
        if ($now->format('d') <= 5) { // First 5 days of the month
            $lastMonth = $now->modify('-1 month')->format('F Y');
            $alerts[] = [
                'title' => 'Rapport mensuel disponible',
                'message' => "Le rapport de performance de {$lastMonth} est maintenant disponible",
                'created_at' => new \DateTime('-2 hours'),
            ];
        }
        
        // Statistics update
        $stats = $this->contractRepository->getContractStatistics();
        if ($stats['total'] > 0) {
            $alerts[] = [
                'title' => 'Mise à jour des statistiques',
                'message' => sprintf('Tableau de bord mis à jour avec %d contrat(s) au total', $stats['total']),
                'created_at' => new \DateTime('-30 minutes'),
            ];
        }
        
        return array_slice($alerts, 0, 3); // Limit to 3 most recent
    }
}
