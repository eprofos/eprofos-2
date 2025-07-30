<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Core\StudentProgressRepository;
use App\Repository\Training\FormationRepository;
use App\Service\Student\StudentEnrollmentService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ProgressMonitoringController provides detailed progress oversight for administrators.
 *
 * Handles student progress monitoring, risk detection, intervention management,
 * and progress analytics for administrative oversight and support.
 */
#[Route('/admin/student/progress')]
#[IsGranted('ROLE_ADMIN')]
class ProgressMonitoringController extends AbstractController
{
    public function __construct(
        private StudentEnrollmentRepository $enrollmentRepository,
        private StudentProgressRepository $progressRepository,
        private FormationRepository $formationRepository,
        private StudentEnrollmentService $enrollmentService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Progress monitoring dashboard.
     */
    #[Route('/', name: 'admin_progress_monitoring_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get filter parameters
        $formation = $request->query->get('formation');
        $riskLevel = $request->query->get('risk_level');
        $progressRange = $request->query->get('progress_range');

        // Get overview statistics
        $stats = $this->getProgressOverviewStats();

        // Get at-risk students
        $atRiskStudents = $this->enrollmentRepository->findAtRiskEnrollments();

        // Get students with low progress
        $lowProgressStudents = $this->findLowProgressStudents($formation);

        // Get overdue enrollments
        $overdueEnrollments = $this->enrollmentRepository->findOverdueEnrollments();

        // Get progress trends
        $progressTrends = $this->getProgressTrends($formation);

        // Get formations for filtering
        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/student/progress/index.html.twig', [
            'stats' => $stats,
            'atRiskStudents' => $atRiskStudents,
            'lowProgressStudents' => $lowProgressStudents,
            'overdueEnrollments' => $overdueEnrollments,
            'progressTrends' => $progressTrends,
            'formations' => $formations,
            'filters' => [
                'formation' => $formation,
                'risk_level' => $riskLevel,
                'progress_range' => $progressRange,
            ],
        ]);
    }

    /**
     * Detailed progress monitoring with filtering.
     */
    #[Route('/detailed', name: 'admin_progress_monitoring_detailed', methods: ['GET'])]
    public function detailed(Request $request, PaginatorInterface $paginator): Response
    {
        $formation = $request->query->get('formation');
        $status = $request->query->get('status', StudentEnrollment::STATUS_ENROLLED);
        $progressFilter = $request->query->get('progress_filter');
        $riskFilter = $request->query->get('risk_filter');
        $search = $request->query->get('search');

        $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
            ->andWhere('se.status = :status')
            ->setParameter('status', $status);

        if ($formation) {
            $queryBuilder->andWhere('f.id = :formation')
                         ->setParameter('formation', $formation);
        }

        if ($search) {
            $queryBuilder->andWhere('st.firstName LIKE :search OR st.lastName LIKE :search OR st.email LIKE :search')
                         ->setParameter('search', '%' . $search . '%');
        }

        // Progress filtering
        if ($progressFilter === 'low') {
            $queryBuilder->andWhere('sp.completionPercentage < 25');
        } elseif ($progressFilter === 'medium') {
            $queryBuilder->andWhere('sp.completionPercentage BETWEEN 25 AND 75');
        } elseif ($progressFilter === 'high') {
            $queryBuilder->andWhere('sp.completionPercentage > 75');
        }

        // Risk filtering
        if ($riskFilter === 'at_risk') {
            $queryBuilder->andWhere('sp.atRiskOfDropout = :at_risk')
                         ->setParameter('at_risk', true);
        } elseif ($riskFilter === 'low_engagement') {
            $queryBuilder->andWhere('sp.engagementLevel < 3');
        }

        $queryBuilder->orderBy('sp.lastActivity', 'DESC');

        $enrollments = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/student/progress/detailed.html.twig', [
            'enrollments' => $enrollments,
            'formations' => $formations,
            'filters' => [
                'formation' => $formation,
                'status' => $status,
                'progress_filter' => $progressFilter,
                'risk_filter' => $riskFilter,
                'search' => $search,
            ],
        ]);
    }

    /**
     * At-risk students monitoring.
     */
    #[Route('/at-risk', name: 'admin_progress_monitoring_at_risk', methods: ['GET'])]
    public function atRisk(Request $request, PaginatorInterface $paginator): Response
    {
        $formation = $request->query->get('formation');
        $riskLevel = $request->query->get('risk_level');

        $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
            ->andWhere('se.status = :status')
            ->andWhere('sp.atRiskOfDropout = :at_risk')
            ->setParameter('status', StudentEnrollment::STATUS_ENROLLED)
            ->setParameter('at_risk', true);

        if ($formation) {
            $queryBuilder->andWhere('f.id = :formation')
                         ->setParameter('formation', $formation);
        }

        if ($riskLevel === 'high') {
            $queryBuilder->andWhere('sp.riskScore >= 70');
        } elseif ($riskLevel === 'medium') {
            $queryBuilder->andWhere('sp.riskScore BETWEEN 40 AND 69');
        } elseif ($riskLevel === 'low') {
            $queryBuilder->andWhere('sp.riskScore < 40');
        }

        $queryBuilder->orderBy('sp.riskScore', 'DESC');

        $atRiskEnrollments = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        // Get risk factors analysis
        $riskFactors = $this->getRiskFactorsAnalysis($formation);

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/student/progress/at_risk.html.twig', [
            'atRiskEnrollments' => $atRiskEnrollments,
            'riskFactors' => $riskFactors,
            'formations' => $formations,
            'filters' => [
                'formation' => $formation,
                'risk_level' => $riskLevel,
            ],
        ]);
    }

    /**
     * Individual student progress details.
     */
    #[Route('/{id}', name: 'admin_progress_monitoring_show', methods: ['GET'])]
    public function show(StudentEnrollment $enrollment): Response
    {
        $progress = $enrollment->getProgress();
        
        if (!$progress) {
            $this->addFlash('error', 'Aucune donnée de progression trouvée pour cet étudiant.');
            return $this->redirectToRoute('admin_progress_monitoring_index');
        }

        // Get detailed progress breakdown
        $progressBreakdown = $this->getProgressBreakdown($progress);

        // Get activity timeline
        $activityTimeline = $this->getActivityTimeline($enrollment);

        // Get intervention history
        $interventionHistory = $this->getInterventionHistory($enrollment);

        // Get risk assessment
        $riskAssessment = $this->getRiskAssessment($progress);

        return $this->render('admin/student/progress/show.html.twig', [
            'enrollment' => $enrollment,
            'progress' => $progress,
            'progressBreakdown' => $progressBreakdown,
            'activityTimeline' => $activityTimeline,
            'interventionHistory' => $interventionHistory,
            'riskAssessment' => $riskAssessment,
        ]);
    }

    /**
     * Record intervention action.
     */
    #[Route('/{id}/intervention', name: 'admin_progress_monitoring_intervention', methods: ['POST'])]
    public function recordIntervention(StudentEnrollment $enrollment, Request $request): Response
    {
        try {
            $interventionType = $request->request->get('intervention_type');
            $notes = $request->request->get('notes');
            $followUpDate = $request->request->get('follow_up_date');

            // For now, add intervention details to enrollment admin notes
            $currentNotes = $enrollment->getAdminNotes() ?? '';
            $interventionNote = sprintf(
                "\n[%s] Intervention %s: %s",
                date('Y-m-d H:i'),
                $interventionType,
                $notes
            );
            
            $enrollment->setAdminNotes($currentNotes . $interventionNote);
            $this->entityManager->flush();

            $this->addFlash('success', 'Intervention enregistrée avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_progress_monitoring_show', ['id' => $enrollment->getId()]);
    }

    /**
     * Update risk status.
     */
    #[Route('/{id}/risk-status', name: 'admin_progress_monitoring_risk_status', methods: ['POST'])]
    public function updateRiskStatus(StudentEnrollment $enrollment, Request $request): Response
    {
        try {
            $atRisk = $request->request->getBoolean('at_risk');
            $riskReason = $request->request->get('risk_reason');

            $progress = $enrollment->getProgress();
            if (!$progress) {
                throw new \Exception('Aucune donnée de progression trouvée');
            }

            $progress->setAtRiskOfDropout($atRisk);
            
            // Add risk reason to admin notes if needed
            if ($atRisk && $riskReason) {
                $currentNotes = $enrollment->getAdminNotes() ?? '';
                $riskNote = sprintf(
                    "\n[%s] Marqué à risque: %s",
                    date('Y-m-d H:i'),
                    $riskReason
                );
                $enrollment->setAdminNotes($currentNotes . $riskNote);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Statut de risque mis à jour avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_progress_monitoring_show', ['id' => $enrollment->getId()]);
    }

    /**
     * Bulk intervention actions.
     */
    #[Route('/bulk/intervention', name: 'admin_progress_monitoring_bulk_intervention', methods: ['POST'])]
    public function bulkIntervention(Request $request): Response
    {
        try {
            $enrollmentIds = $request->request->all('enrollment_ids');
            $interventionType = $request->request->get('intervention_type');
            $notes = $request->request->get('notes');

            if (empty($enrollmentIds)) {
                throw new \Exception('Aucune inscription sélectionnée');
            }

            $enrollments = $this->enrollmentRepository->findBy(['id' => $enrollmentIds]);
            $successCount = 0;

            foreach ($enrollments as $enrollment) {
                $currentNotes = $enrollment->getAdminNotes() ?? '';
                $interventionNote = sprintf(
                    "\n[%s] Intervention en lot %s: %s",
                    date('Y-m-d H:i'),
                    $interventionType,
                    $notes
                );
                
                $enrollment->setAdminNotes($currentNotes . $interventionNote);
                $successCount++;
            }

            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'Intervention appliquée à %d inscription(s)',
                $successCount
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'intervention en lot : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_progress_monitoring_detailed');
    }

    /**
     * API endpoint for progress statistics.
     */
    #[Route('/api/stats', name: 'admin_progress_monitoring_api_stats', methods: ['GET'])]
    public function statsApi(Request $request): JsonResponse
    {
        $formation = $request->query->get('formation');
        
        $stats = [
            'overview' => $this->getProgressOverviewStats(),
            'trends' => $this->getProgressTrends($formation),
            'risk_distribution' => $this->getRiskDistribution($formation),
            'completion_forecast' => $this->getCompletionForecast($formation),
        ];

        return new JsonResponse($stats);
    }

    /**
     * API endpoint for progress chart data.
     */
    #[Route('/api/charts/{type}', name: 'admin_progress_monitoring_api_charts', methods: ['GET'])]
    public function chartsApi(string $type, Request $request): JsonResponse
    {
        $formation = $request->query->get('formation');

        $data = match ($type) {
            'progress_distribution' => $this->getProgressDistributionChart($formation),
            'engagement_levels' => $this->getEngagementLevelsChart($formation),
            'risk_factors' => $this->getRiskFactorsChart($formation),
            'completion_trends' => $this->getCompletionTrendsChart($formation),
            default => []
        };

        return new JsonResponse($data);
    }

    /**
     * Get progress overview statistics.
     */
    private function getProgressOverviewStats(): array
    {
        $totalActive = $this->enrollmentRepository->count(['status' => StudentEnrollment::STATUS_ENROLLED]);
        $atRisk = $this->enrollmentRepository->count(['status' => StudentEnrollment::STATUS_ENROLLED, 'progress.atRiskOfDropout' => true]);
        $lowProgress = $this->progressRepository->countByProgressRange(0, 25);
        $highProgress = $this->progressRepository->countByProgressRange(75, 100);

        return [
            'total_active' => $totalActive,
            'at_risk' => $atRisk,
            'at_risk_percentage' => $totalActive > 0 ? round(($atRisk / $totalActive) * 100, 1) : 0,
            'low_progress' => $lowProgress,
            'high_progress' => $highProgress,
            'average_progress' => $this->progressRepository->getAverageProgress(),
        ];
    }

    /**
     * Find students with low progress.
     */
    private function findLowProgressStudents(?string $formationId): array
    {
        $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
            ->andWhere('se.status = :status')
            ->andWhere('sp.completionPercentage < 25')
            ->setParameter('status', StudentEnrollment::STATUS_ENROLLED);

        if ($formationId) {
            $queryBuilder->andWhere('f.id = :formation')
                         ->setParameter('formation', $formationId);
        }

        return $queryBuilder->orderBy('sp.completionPercentage', 'ASC')
                           ->setMaxResults(10)
                           ->getQuery()
                           ->getResult();
    }

    /**
     * Get progress trends over time.
     */
    private function getProgressTrends(?string $formationId): array
    {
        $sql = "
            SELECT 
                DATE(sp.last_activity) as date,
                AVG(sp.completion_percentage) as avg_progress,
                COUNT(CASE WHEN sp.at_risk_of_dropout = true THEN 1 END) as at_risk_count,
                COUNT(*) as total_students
            FROM student_progress sp
            LEFT JOIN student_enrollments se ON sp.id = se.progress_id
            LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
            LEFT JOIN sessions s ON sr.session_id = s.id
            WHERE se.status = 'enrolled'
              AND sp.last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";

        $params = [];
        if ($formationId) {
            $sql .= " AND s.formation_id = :formation_id";
            $params['formation_id'] = $formationId;
        }

        $sql .= " GROUP BY DATE(sp.last_activity) ORDER BY date DESC LIMIT 30";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        return $stmt->executeQuery($params)->fetchAllAssociative();
    }

    /**
     * Get risk factors analysis.
     */
    private function getRiskFactorsAnalysis(?string $formationId): array
    {
        $sql = "
            SELECT 
                sp.risk_factors,
                COUNT(*) as count
            FROM student_progress sp
            LEFT JOIN student_enrollments se ON sp.id = se.progress_id
            LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
            LEFT JOIN sessions s ON sr.session_id = s.id
            WHERE se.status = 'enrolled' 
              AND sp.at_risk_of_dropout = true 
              AND sp.risk_factors IS NOT NULL
        ";

        $params = [];
        if ($formationId) {
            $sql .= " AND s.formation_id = :formation_id";
            $params['formation_id'] = $formationId;
        }

        $sql .= " GROUP BY sp.risk_factors ORDER BY count DESC";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $results = $stmt->executeQuery($params)->fetchAllAssociative();

        // Process JSON risk factors
        $riskFactorCounts = [];
        foreach ($results as $result) {
            $factors = json_decode($result['risk_factors'], true) ?: [];
            foreach ($factors as $factor) {
                $riskFactorCounts[$factor] = ($riskFactorCounts[$factor] ?? 0) + $result['count'];
            }
        }

        arsort($riskFactorCounts);
        return $riskFactorCounts;
    }

    /**
     * Get detailed progress breakdown for a student.
     */
    private function getProgressBreakdown($progress): array
    {
        return [
            'overall' => $progress->getCompletionPercentage(),
            'modules' => $progress->getModuleProgress() ?? [],
            'chapters' => $progress->getChapterProgress() ?? [],
            'last_activity' => $progress->getLastActivity(),
            'total_time_spent' => $progress->getTotalTimeSpent() ?? 0,
            'engagement_level' => $progress->getEngagementScore() ?? 0,
        ];
    }

    /**
     * Get activity timeline for a student.
     */
    private function getActivityTimeline($enrollment): array
    {
        // This would typically come from an activity log table
        // For now, return a placeholder structure
        return [
            [
                'date' => new \DateTime('-2 days'),
                'type' => 'progress',
                'description' => 'Chapitre terminé: Introduction aux concepts',
                'progress_change' => 10,
            ],
            [
                'date' => new \DateTime('-5 days'),
                'type' => 'login',
                'description' => 'Connexion à la plateforme',
                'progress_change' => 0,
            ],
            // Add more timeline entries...
        ];
    }

    /**
     * Get intervention history for a student.
     */
    private function getInterventionHistory($enrollment): array
    {
        // Extract interventions from admin notes for now
        $notes = $enrollment->getAdminNotes() ?? '';
        $interventions = [];
        
        // Parse intervention notes (simplified)
        if (preg_match_all('/\[([^\]]+)\] Intervention ([^:]+): (.+)/', $notes, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $interventions[] = [
                    'date' => $match[1],
                    'type' => trim($match[2]),
                    'notes' => trim($match[3]),
                ];
            }
        }
        
        return $interventions;
    }

    /**
     * Get risk assessment details.
     */
    private function getRiskAssessment($progress): array
    {
        $lastActivity = $progress->getLastActivity();
        $daysSinceLastActivity = $lastActivity ? 
            (new \DateTime())->diff($lastActivity)->days : 999;

        return [
            'is_at_risk' => $progress->isAtRiskOfDropout(),
            'risk_score' => $progress->getRiskScore() ?? 0,
            'risk_factors' => [], // Placeholder
            'last_activity_days' => $daysSinceLastActivity,
            'engagement_trend' => $this->calculateEngagementTrend($progress),
        ];
    }

    /**
     * Calculate engagement trend.
     */
    private function calculateEngagementTrend($progress): string
    {
        // Simplified trend calculation
        $currentEngagement = $progress->getEngagementScore() ?? 0;
        $threshold = 5; // Arbitrary threshold for stable engagement

        if ($currentEngagement > $threshold + 2) {
            return 'improving';
        } elseif ($currentEngagement < $threshold - 2) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Get risk distribution for chart.
     */
    private function getRiskDistribution(?string $formationId): array
    {
        $sql = "
            SELECT 
                CASE 
                    WHEN sp.risk_score >= 70 THEN 'High'
                    WHEN sp.risk_score >= 40 THEN 'Medium'
                    ELSE 'Low'
                END as risk_level,
                COUNT(*) as count
            FROM student_progress sp
            LEFT JOIN student_enrollments se ON sp.id = se.progress_id
            LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
            LEFT JOIN sessions s ON sr.session_id = s.id
            WHERE se.status = 'enrolled'
        ";

        $params = [];
        if ($formationId) {
            $sql .= " AND s.formation_id = :formation_id";
            $params['formation_id'] = $formationId;
        }

        $sql .= " GROUP BY risk_level";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        return $stmt->executeQuery($params)->fetchAllAssociative();
    }

    /**
     * Get completion forecast.
     */
    private function getCompletionForecast(?string $formationId): array
    {
        // Simplified forecast calculation
        $activeEnrollments = $this->enrollmentRepository->findEnrollmentsWithFilters([
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'formation' => $formationId,
        ]);

        $forecast = [
            'total_active' => count($activeEnrollments),
            'expected_completions_30_days' => 0,
            'expected_dropouts_30_days' => 0,
            'completion_rate_trend' => 'stable',
        ];

        foreach ($activeEnrollments as $enrollment) {
            $progress = $enrollment->getProgress();
            if (!$progress) continue;

            if ($progress->getCompletionPercentage() > 80) {
                $forecast['expected_completions_30_days']++;
            } elseif ($progress->isAtRiskOfDropout()) {
                $forecast['expected_dropouts_30_days']++;
            }
        }

        return $forecast;
    }

    /**
     * Get progress distribution chart data.
     */
    private function getProgressDistributionChart(?string $formationId): array
    {
        $sql = "
            SELECT 
                CASE 
                    WHEN sp.completion_percentage < 25 THEN '0-24%'
                    WHEN sp.completion_percentage < 50 THEN '25-49%'
                    WHEN sp.completion_percentage < 75 THEN '50-74%'
                    ELSE '75-100%'
                END as range,
                COUNT(*) as count
            FROM student_progress sp
            LEFT JOIN student_enrollments se ON sp.id = se.progress_id
            LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
            LEFT JOIN sessions s ON sr.session_id = s.id
            WHERE se.status = 'enrolled'
        ";

        $params = [];
        if ($formationId) {
            $sql .= " AND s.formation_id = :formation_id";
            $params['formation_id'] = $formationId;
        }

        $sql .= " GROUP BY range ORDER BY MIN(sp.completion_percentage)";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        return $stmt->executeQuery($params)->fetchAllAssociative();
    }

    /**
     * Get engagement levels chart data.
     */
    private function getEngagementLevelsChart(?string $formationId): array
    {
        $sql = "
            SELECT 
                sp.engagement_level,
                COUNT(*) as count
            FROM student_progress sp
            LEFT JOIN student_enrollments se ON sp.id = se.progress_id
            LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
            LEFT JOIN sessions s ON sr.session_id = s.id
            WHERE se.status = 'enrolled'
        ";

        $params = [];
        if ($formationId) {
            $sql .= " AND s.formation_id = :formation_id";
            $params['formation_id'] = $formationId;
        }

        $sql .= " GROUP BY sp.engagement_level ORDER BY sp.engagement_level";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        return $stmt->executeQuery($params)->fetchAllAssociative();
    }

    /**
     * Get risk factors chart data.
     */
    private function getRiskFactorsChart(?string $formationId): array
    {
        return $this->getRiskFactorsAnalysis($formationId);
    }

    /**
     * Get completion trends chart data.
     */
    private function getCompletionTrendsChart(?string $formationId): array
    {
        return $this->getProgressTrends($formationId);
    }
}
