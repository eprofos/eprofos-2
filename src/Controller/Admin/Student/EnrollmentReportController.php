<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Training\FormationRepository;
use App\Repository\Training\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * EnrollmentReportController provides reporting and analytics capabilities for student enrollments.
 *
 * Handles enrollment analytics, custom reports, Qualiopi compliance reports,
 * and data visualization for administrative oversight.
 */
#[Route('/admin/student/enrollment/reports')]
#[IsGranted('ROLE_ADMIN')]
class EnrollmentReportController extends AbstractController
{
    public function __construct(
        private StudentEnrollmentRepository $enrollmentRepository,
        private FormationRepository $formationRepository,
        private SessionRepository $sessionRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Enrollment analytics dashboard.
     */
    #[Route('/', name: 'admin_enrollment_reports_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get date range filter
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $formation = $request->query->get('formation');

        // Basic enrollment statistics
        $stats = $this->enrollmentRepository->getEnrollmentStats();

        // Formation-specific analytics
        $formationAnalytics = $this->getFormationAnalytics($formation);

        // Enrollment trends over time
        $enrollmentTrends = $this->getEnrollmentTrends($startDate, $endDate);

        // Completion rates by formation
        $completionRates = $this->getCompletionRatesByFormation();

        // Dropout analysis
        $dropoutAnalysis = $this->getDropoutAnalysis();

        // Risk indicators
        $riskIndicators = $this->getRiskIndicators();

        // Available formations for filtering
        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/student/enrollment/reports/index.html.twig', [
            'stats' => $stats,
            'formationAnalytics' => $formationAnalytics,
            'enrollmentTrends' => $enrollmentTrends,
            'completionRates' => $completionRates,
            'dropoutAnalysis' => $dropoutAnalysis,
            'riskIndicators' => $riskIndicators,
            'formations' => $formations,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'formation' => $formation,
            ],
        ]);
    }

    /**
     * Formation completion reports.
     */
    #[Route('/completion', name: 'admin_enrollment_reports_completion', methods: ['GET'])]
    public function completionReport(Request $request, PaginatorInterface $paginator): Response
    {
        $formation = $request->query->get('formation');
        $status = $request->query->get('status', StudentEnrollment::STATUS_COMPLETED);

        $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
            ->andWhere('se.status = :status')
            ->setParameter('status', $status);

        if ($formation) {
            $queryBuilder->andWhere('f.id = :formation')
                         ->setParameter('formation', $formation);
        }

        $queryBuilder->orderBy('se.completedAt', 'DESC');

        $enrollments = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/student/enrollment/reports/completion.html.twig', [
            'enrollments' => $enrollments,
            'formations' => $formations,
            'selectedFormation' => $formation,
            'status' => $status,
        ]);
    }

    /**
     * Dropout analysis reports.
     */
    #[Route('/dropout', name: 'admin_enrollment_reports_dropout', methods: ['GET'])]
    public function dropoutReport(Request $request, PaginatorInterface $paginator): Response
    {
        $formation = $request->query->get('formation');
        $reasonFilter = $request->query->get('reason');

        $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
            ->andWhere('se.status = :status')
            ->setParameter('status', StudentEnrollment::STATUS_DROPPED_OUT);

        if ($formation) {
            $queryBuilder->andWhere('f.id = :formation')
                         ->setParameter('formation', $formation);
        }

        if ($reasonFilter) {
            $queryBuilder->andWhere('se.dropoutReason LIKE :reason')
                         ->setParameter('reason', '%' . $reasonFilter . '%');
        }

        $queryBuilder->orderBy('se.updatedAt', 'DESC');

        $enrollments = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        // Dropout reasons analysis
        $dropoutReasons = $this->getDropoutReasonsAnalysis($formation);

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/student/enrollment/reports/dropout.html.twig', [
            'enrollments' => $enrollments,
            'dropoutReasons' => $dropoutReasons,
            'formations' => $formations,
            'selectedFormation' => $formation,
            'reasonFilter' => $reasonFilter,
        ]);
    }

    /**
     * Progress monitoring reports.
     */
    #[Route('/progress', name: 'admin_enrollment_reports_progress', methods: ['GET'])]
    public function progressReport(Request $request, PaginatorInterface $paginator): Response
    {
        $formation = $request->query->get('formation');
        $progressFilter = $request->query->get('progress', 'all');

        $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
            ->andWhere('se.status = :status')
            ->setParameter('status', StudentEnrollment::STATUS_ENROLLED);

        if ($formation) {
            $queryBuilder->andWhere('f.id = :formation')
                         ->setParameter('formation', $formation);
        }

        // Progress filtering
        if ($progressFilter === 'low') {
            $queryBuilder->andWhere('sp.completionPercentage < 25');
        } elseif ($progressFilter === 'medium') {
            $queryBuilder->andWhere('sp.completionPercentage BETWEEN 25 AND 75');
        } elseif ($progressFilter === 'high') {
            $queryBuilder->andWhere('sp.completionPercentage > 75');
        } elseif ($progressFilter === 'at_risk') {
            $queryBuilder->andWhere('sp.atRiskOfDropout = :at_risk')
                         ->setParameter('at_risk', true);
        }

        $queryBuilder->orderBy('sp.completionPercentage', 'ASC');

        $enrollments = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        // Progress distribution
        $progressDistribution = $this->getProgressDistribution($formation);

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/student/enrollment/reports/progress.html.twig', [
            'enrollments' => $enrollments,
            'progressDistribution' => $progressDistribution,
            'formations' => $formations,
            'selectedFormation' => $formation,
            'progressFilter' => $progressFilter,
        ]);
    }

    /**
     * Qualiopi compliance reports.
     */
    #[Route('/qualiopi', name: 'admin_enrollment_reports_qualiopi', methods: ['GET'])]
    public function qualiopiReport(Request $request): Response
    {
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $formation = $request->query->get('formation');

        // Qualiopi compliance metrics
        $qualiopiMetrics = $this->getQualiopiComplianceMetrics($startDate, $endDate, $formation);

        // Attendance tracking compliance
        $attendanceCompliance = $this->getAttendanceComplianceMetrics($startDate, $endDate, $formation);

        // Student satisfaction tracking
        $satisfactionMetrics = $this->getSatisfactionMetrics($startDate, $endDate, $formation);

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/student/enrollment/reports/qualiopi.html.twig', [
            'qualiopiMetrics' => $qualiopiMetrics,
            'attendanceCompliance' => $attendanceCompliance,
            'satisfactionMetrics' => $satisfactionMetrics,
            'formations' => $formations,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'formation' => $formation,
            ],
        ]);
    }

    /**
     * Custom report builder.
     */
    #[Route('/custom', name: 'admin_enrollment_reports_custom', methods: ['GET', 'POST'])]
    public function customReport(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $reportConfig = [
                'title' => $request->request->get('report_title'),
                'filters' => $request->request->all('filters'),
                'fields' => $request->request->all('fields'),
                'groupBy' => $request->request->get('group_by'),
                'orderBy' => $request->request->get('order_by'),
                'format' => $request->request->get('format', 'html'),
            ];

            $reportData = $this->generateCustomReport($reportConfig);

            if ($reportConfig['format'] === 'export') {
                return $this->exportCustomReport($reportData, $reportConfig);
            }

            return $this->render('admin/student/enrollment/reports/custom_results.html.twig', [
                'reportData' => $reportData,
                'reportConfig' => $reportConfig,
            ]);
        }

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/student/enrollment/reports/custom.html.twig', [
            'formations' => $formations,
            'availableFields' => $this->getAvailableReportFields(),
        ]);
    }

    /**
     * API endpoint for enrollment analytics data.
     */
    #[Route('/api/analytics', name: 'admin_enrollment_reports_api_analytics', methods: ['GET'])]
    public function analyticsApi(Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'enrollment_trends');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $formation = $request->query->get('formation');

        $data = match ($type) {
            'enrollment_trends' => $this->getEnrollmentTrends($startDate, $endDate),
            'completion_rates' => $this->getCompletionRatesByFormation(),
            'dropout_analysis' => $this->getDropoutAnalysis(),
            'progress_distribution' => $this->getProgressDistribution($formation),
            default => []
        };

        return new JsonResponse($data);
    }

    /**
     * Export enrollment data in various formats.
     */
    #[Route('/export', name: 'admin_enrollment_reports_export', methods: ['POST'])]
    public function exportReport(Request $request): StreamedResponse
    {
        $format = $request->request->get('format', 'csv');
        $filters = $request->request->all('filters');
        $fields = $request->request->all('fields');

        $enrollments = $this->enrollmentRepository->findEnrollmentsWithFilters($filters);

        return $this->generateExportResponse($enrollments, $format, $fields);
    }

    /**
     * Get formation-specific analytics.
     */
    private function getFormationAnalytics(?string $formationId): array
    {
        if (!$formationId) {
            return [];
        }

        $formation = $this->formationRepository->find($formationId);
        if (!$formation) {
            return [];
        }

        return [
            'formation' => $formation,
            'totalEnrollments' => $this->enrollmentRepository->countEnrollmentsByFormation($formation),
            'completionRate' => $this->enrollmentRepository->getFormationCompletionRate($formation),
            'dropoutRate' => $this->enrollmentRepository->getFormationDropoutRate($formation),
            'avgDuration' => $this->getAverageCompletionDuration($formation),
            'activeEnrollments' => $this->enrollmentRepository->countEnrollmentsByFormationAndStatus($formation, StudentEnrollment::STATUS_ENROLLED),
        ];
    }

    /**
     * Get enrollment trends over time.
     */
    private function getEnrollmentTrends(?string $startDate, ?string $endDate): array
    {
        $sql = "
            SELECT 
                DATE(se.enrolled_at) as date,
                COUNT(*) as enrollments,
                SUM(CASE WHEN se.status = 'completed' THEN 1 ELSE 0 END) as completions,
                SUM(CASE WHEN se.status = 'dropped_out' THEN 1 ELSE 0 END) as dropouts
            FROM student_enrollments se
        ";

        $params = [];
        if ($startDate && $endDate) {
            $sql .= " WHERE se.enrolled_at BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }

        $sql .= " GROUP BY DATE(se.enrolled_at) ORDER BY date ASC";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        return $stmt->executeQuery($params)->fetchAllAssociative();
    }

    /**
     * Get completion rates by formation.
     */
    private function getCompletionRatesByFormation(): array
    {
        $formations = $this->formationRepository->findBy(['isActive' => true]);
        $completionRates = [];

        foreach ($formations as $formation) {
            $completionRates[] = [
                'formation' => $formation->getTitle(),
                'completion_rate' => $this->enrollmentRepository->getFormationCompletionRate($formation),
                'dropout_rate' => $this->enrollmentRepository->getFormationDropoutRate($formation),
                'total_enrollments' => $this->enrollmentRepository->countEnrollmentsByFormation($formation),
            ];
        }

        return $completionRates;
    }

    /**
     * Get dropout analysis data.
     */
    private function getDropoutAnalysis(): array
    {
        $sql = "
            SELECT 
                se.dropout_reason,
                COUNT(*) as count,
                AVG(EXTRACT(EPOCH FROM (se.updated_at - se.enrolled_at)) / 86400) as avg_days_to_dropout
            FROM student_enrollments se
            WHERE se.status = 'dropped_out' AND se.dropout_reason IS NOT NULL
            GROUP BY se.dropout_reason
            ORDER BY count DESC
        ";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get dropout reasons analysis.
     */
    private function getDropoutReasonsAnalysis(?string $formationId): array
    {
        $sql = "
            SELECT 
                se.dropout_reason,
                COUNT(*) as count
            FROM student_enrollments se
            LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
            LEFT JOIN sessions s ON sr.session_id = s.id
            WHERE se.status = 'dropped_out' AND se.dropout_reason IS NOT NULL
        ";

        $params = [];
        if ($formationId) {
            $sql .= " AND s.formation_id = :formation_id";
            $params['formation_id'] = $formationId;
        }

        $sql .= " GROUP BY se.dropout_reason ORDER BY count DESC";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        return $stmt->executeQuery($params)->fetchAllAssociative();
    }

    /**
     * Get progress distribution.
     */
    private function getProgressDistribution(?string $formationId): array
    {
        $sql = "
            SELECT 
                CASE 
                    WHEN sp.completion_percentage < 25 THEN 'Low (0-24%)'
                    WHEN sp.completion_percentage < 50 THEN 'Medium (25-49%)'
                    WHEN sp.completion_percentage < 75 THEN 'Good (50-74%)'
                    ELSE 'Excellent (75-100%)'
                END as progress_range,
                COUNT(*) as count
            FROM student_enrollments se
            LEFT JOIN student_progress sp ON se.progress_id = sp.id
            LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
            LEFT JOIN sessions s ON sr.session_id = s.id
            WHERE se.status = 'enrolled'
        ";

        $params = [];
        if ($formationId) {
            $sql .= " AND s.formation_id = :formation_id";
            $params['formation_id'] = $formationId;
        }

        $sql .= " GROUP BY progress_range ORDER BY MIN(sp.completion_percentage)";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        return $stmt->executeQuery($params)->fetchAllAssociative();
    }

    /**
     * Get risk indicators.
     */
    private function getRiskIndicators(): array
    {
        return [
            'at_risk_students' => $this->enrollmentRepository->count(['status' => StudentEnrollment::STATUS_ENROLLED, 'progress.atRiskOfDropout' => true]),
            'overdue_enrollments' => count($this->enrollmentRepository->findOverdueEnrollments()),
            'missing_progress' => count($this->enrollmentRepository->findEnrollmentsWithoutProgress()),
            'high_dropout_formations' => $this->getHighDropoutFormations(),
        ];
    }

    /**
     * Get formations with high dropout rates.
     */
    private function getHighDropoutFormations(): array
    {
        $formations = $this->formationRepository->findBy(['isActive' => true]);
        $highDropoutFormations = [];

        foreach ($formations as $formation) {
            $dropoutRate = $this->enrollmentRepository->getFormationDropoutRate($formation);
            if ($dropoutRate > 20) { // More than 20% dropout rate
                $highDropoutFormations[] = [
                    'formation' => $formation->getTitle(),
                    'dropout_rate' => $dropoutRate,
                ];
            }
        }

        return $highDropoutFormations;
    }

    /**
     * Get Qualiopi compliance metrics.
     */
    private function getQualiopiComplianceMetrics(?string $startDate, ?string $endDate, ?string $formationId): array
    {
        // Implementation would depend on specific Qualiopi requirements
        // This is a placeholder for the comprehensive compliance tracking
        return [
            'enrollment_tracking' => 100, // Percentage compliance
            'progress_documentation' => 95,
            'evaluation_records' => 88,
            'attendance_tracking' => 92,
            'satisfaction_surveys' => 76,
        ];
    }

    /**
     * Get attendance compliance metrics.
     */
    private function getAttendanceComplianceMetrics(?string $startDate, ?string $endDate, ?string $formationId): array
    {
        // Placeholder for attendance compliance tracking
        return [
            'sessions_with_attendance' => 95,
            'students_with_complete_attendance' => 87,
            'avg_attendance_rate' => 91.5,
        ];
    }

    /**
     * Get satisfaction metrics.
     */
    private function getSatisfactionMetrics(?string $startDate, ?string $endDate, ?string $formationId): array
    {
        // Placeholder for satisfaction tracking
        return [
            'surveys_completed' => 156,
            'avg_satisfaction_score' => 4.2,
            'satisfaction_rate' => 84,
        ];
    }

    /**
     * Generate custom report based on configuration.
     */
    private function generateCustomReport(array $config): array
    {
        $filters = $config['filters'] ?? [];
        $enrollments = $this->enrollmentRepository->findEnrollmentsWithFilters($filters);

        // Process data based on selected fields and grouping
        return [
            'data' => $enrollments,
            'summary' => [
                'total_records' => count($enrollments),
                'generated_at' => new \DateTime(),
            ],
        ];
    }

    /**
     * Export custom report.
     */
    private function exportCustomReport(array $reportData, array $config): StreamedResponse
    {
        return $this->generateExportResponse($reportData['data'], 'csv', $config['fields']);
    }

    /**
     * Get available report fields.
     */
    private function getAvailableReportFields(): array
    {
        return [
            'student_name' => 'Nom de l\'étudiant',
            'student_email' => 'Email de l\'étudiant',
            'formation_title' => 'Titre de la formation',
            'session_name' => 'Nom de la session',
            'enrollment_status' => 'Statut d\'inscription',
            'enrolled_at' => 'Date d\'inscription',
            'completed_at' => 'Date de fin',
            'progress_percentage' => 'Pourcentage de progression',
            'dropout_reason' => 'Raison d\'abandon',
            'enrollment_source' => 'Source d\'inscription',
        ];
    }

    /**
     * Get average completion duration for a formation.
     */
    private function getAverageCompletionDuration($formation): ?float
    {
        $sql = "
            SELECT AVG(EXTRACT(EPOCH FROM (se.completed_at - se.enrolled_at)) / 86400) as avg_duration
            FROM student_enrollments se
            LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
            LEFT JOIN sessions s ON sr.session_id = s.id
            WHERE se.status = 'completed' AND s.formation_id = :formation_id AND se.completed_at IS NOT NULL
        ";

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['formation_id' => $formation->getId()])->fetchOne();

        return $result ? (float) $result : null;
    }

    /**
     * Generate export response.
     */
    private function generateExportResponse(array $enrollments, string $format, array $fields): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function () use ($enrollments, $format, $fields) {
            $handle = fopen('php://output', 'w');

            if ($format === 'csv') {
                // Write CSV headers
                $headers = [];
                foreach ($fields as $field) {
                    $headers[] = $this->getAvailableReportFields()[$field] ?? $field;
                }
                fputcsv($handle, $headers);

                // Write data rows
                foreach ($enrollments as $enrollment) {
                    $row = [];
                    foreach ($fields as $field) {
                        $row[] = $this->getFieldValue($enrollment, $field);
                    }
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        });

        $filename = sprintf('enrollment_report_%s.%s', date('Y-m-d_H-i-s'), $format);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Get field value for export.
     */
    private function getFieldValue(StudentEnrollment $enrollment, string $field): string
    {
        return match ($field) {
            'student_name' => $enrollment->getStudent()->getFullName(),
            'student_email' => $enrollment->getStudent()->getEmail(),
            'formation_title' => $enrollment->getFormation()->getTitle(),
            'session_name' => $enrollment->getSession()->getName(),
            'enrollment_status' => $enrollment->getStatusLabel(),
            'enrolled_at' => $enrollment->getEnrolledAt()->format('Y-m-d H:i:s'),
            'completed_at' => $enrollment->getCompletedAt()?->format('Y-m-d H:i:s') ?? '',
            'progress_percentage' => $enrollment->getProgress()?->getCompletionPercentage() ?? '0',
            'dropout_reason' => $enrollment->getDropoutReason() ?? '',
            'enrollment_source' => $enrollment->getEnrollmentSource(),
            default => ''
        };
    }
}
