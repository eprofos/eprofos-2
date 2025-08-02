<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Training\FormationRepository;
use App\Repository\Training\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
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
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Enrollment analytics dashboard.
     */
    #[Route('/', name: 'admin_enrollment_reports_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Starting enrollment reports dashboard generation', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        try {
            // Get date range filter
            $startDate = $request->query->get('start_date');
            $endDate = $request->query->get('end_date');
            $formation = $request->query->get('formation');

            $this->logger->debug('Processing enrollment reports filters', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'formation' => $formation
            ]);

            // Basic enrollment statistics
            $this->logger->debug('Fetching basic enrollment statistics');
            $stats = $this->enrollmentRepository->getEnrollmentStats();
            $this->logger->debug('Basic enrollment statistics retrieved', ['stats_count' => count($stats)]);

            // Formation-specific analytics
            $this->logger->debug('Generating formation-specific analytics', ['formation_id' => $formation]);
            $formationAnalytics = $this->getFormationAnalytics($formation);
            $this->logger->debug('Formation analytics generated', ['has_data' => !empty($formationAnalytics)]);

            // Enrollment trends over time
            $this->logger->debug('Calculating enrollment trends', [
                'date_range' => ['start' => $startDate, 'end' => $endDate]
            ]);
            $enrollmentTrends = $this->getEnrollmentTrends($startDate, $endDate);
            $this->logger->debug('Enrollment trends calculated', ['trends_count' => count($enrollmentTrends)]);

            // Completion rates by formation
            $this->logger->debug('Calculating completion rates by formation');
            $completionRates = $this->getCompletionRatesByFormation();
            $this->logger->debug('Completion rates calculated', ['formations_analyzed' => count($completionRates)]);

            // Dropout analysis
            $this->logger->debug('Performing dropout analysis');
            $dropoutAnalysis = $this->getDropoutAnalysis();
            $this->logger->debug('Dropout analysis completed', ['dropout_reasons' => count($dropoutAnalysis)]);

            // Risk indicators
            $this->logger->debug('Calculating risk indicators');
            $riskIndicators = $this->getRiskIndicators();
            $this->logger->debug('Risk indicators calculated', ['indicators' => array_keys($riskIndicators)]);

            // Available formations for filtering
            $this->logger->debug('Fetching available formations for filtering');
            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);
            $this->logger->debug('Available formations retrieved', ['formations_count' => count($formations)]);

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('Enrollment reports dashboard generated successfully', [
                'execution_time' => round($executionTime, 3),
                'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
                'data_points' => [
                    'stats' => count($stats),
                    'trends' => count($enrollmentTrends),
                    'completion_rates' => count($completionRates),
                    'dropout_analysis' => count($dropoutAnalysis),
                    'formations' => count($formations)
                ]
            ]);

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

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in enrollment reports dashboard', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'request_params' => $request->query->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur de base de données s\'est produite lors de la génération des rapports.');
            return $this->redirectToRoute('admin_dashboard');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in enrollment reports dashboard', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_params' => $request->query->all(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer ou contacter l\'administrateur.');
            return $this->redirectToRoute('admin_dashboard');
        }
    }

    /**
     * Formation completion reports.
     */
    #[Route('/completion', name: 'admin_enrollment_reports_completion', methods: ['GET'])]
    public function completionReport(Request $request, PaginatorInterface $paginator): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Starting completion report generation', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        try {
            $formation = $request->query->get('formation');
            $status = $request->query->get('status', StudentEnrollment::STATUS_COMPLETED);
            $page = $request->query->getInt('page', 1);

            $this->logger->debug('Processing completion report filters', [
                'formation' => $formation,
                'status' => $status,
                'page' => $page
            ]);

            $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
                ->andWhere('se.status = :status')
                ->setParameter('status', $status);

            if ($formation) {
                $this->logger->debug('Applying formation filter', ['formation_id' => $formation]);
                $queryBuilder->andWhere('f.id = :formation')
                             ->setParameter('formation', $formation);
            }

            $queryBuilder->orderBy('se.completedAt', 'DESC');

            $this->logger->debug('Executing pagination query for completion report');
            $enrollments = $paginator->paginate(
                $queryBuilder->getQuery(),
                $page,
                20
            );

            $this->logger->debug('Fetching formations for filter dropdown');
            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('Completion report generated successfully', [
                'execution_time' => round($executionTime, 3),
                'total_items' => $enrollments->getTotalItemCount(),
                'current_page' => $enrollments->getCurrentPageNumber(),
                'items_per_page' => $enrollments->getItemNumberPerPage(),
                'formations_available' => count($formations)
            ]);

            return $this->render('admin/student/enrollment/reports/completion.html.twig', [
                'enrollments' => $enrollments,
                'formations' => $formations,
                'selectedFormation' => $formation,
                'status' => $status,
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in completion report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'request_params' => $request->query->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la génération du rapport de completion.');
            return $this->redirectToRoute('admin_enrollment_reports_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in completion report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_params' => $request->query->all(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la génération du rapport.');
            return $this->redirectToRoute('admin_enrollment_reports_index');
        }
    }

    /**
     * Dropout analysis reports.
     */
    #[Route('/dropout', name: 'admin_enrollment_reports_dropout', methods: ['GET'])]
    public function dropoutReport(Request $request, PaginatorInterface $paginator): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Starting dropout report generation', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        try {
            $formation = $request->query->get('formation');
            $reasonFilter = $request->query->get('reason');
            $page = $request->query->getInt('page', 1);

            $this->logger->debug('Processing dropout report filters', [
                'formation' => $formation,
                'reason_filter' => $reasonFilter,
                'page' => $page
            ]);

            $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
                ->andWhere('se.status = :status')
                ->setParameter('status', StudentEnrollment::STATUS_DROPPED_OUT);

            if ($formation) {
                $this->logger->debug('Applying formation filter to dropout report', ['formation_id' => $formation]);
                $queryBuilder->andWhere('f.id = :formation')
                             ->setParameter('formation', $formation);
            }

            if ($reasonFilter) {
                $this->logger->debug('Applying reason filter to dropout report', ['reason_filter' => $reasonFilter]);
                $queryBuilder->andWhere('se.dropoutReason LIKE :reason')
                             ->setParameter('reason', '%' . $reasonFilter . '%');
            }

            $queryBuilder->orderBy('se.updatedAt', 'DESC');

            $this->logger->debug('Executing pagination query for dropout report');
            $enrollments = $paginator->paginate(
                $queryBuilder->getQuery(),
                $page,
                20
            );

            // Dropout reasons analysis
            $this->logger->debug('Analyzing dropout reasons', ['formation_id' => $formation]);
            $dropoutReasons = $this->getDropoutReasonsAnalysis($formation);
            $this->logger->debug('Dropout reasons analysis completed', ['reasons_count' => count($dropoutReasons)]);

            $this->logger->debug('Fetching formations for filter dropdown');
            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('Dropout report generated successfully', [
                'execution_time' => round($executionTime, 3),
                'total_dropouts' => $enrollments->getTotalItemCount(),
                'current_page' => $enrollments->getCurrentPageNumber(),
                'dropout_reasons_analyzed' => count($dropoutReasons),
                'formations_available' => count($formations)
            ]);

            return $this->render('admin/student/enrollment/reports/dropout.html.twig', [
                'enrollments' => $enrollments,
                'dropoutReasons' => $dropoutReasons,
                'formations' => $formations,
                'selectedFormation' => $formation,
                'reasonFilter' => $reasonFilter,
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in dropout report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'request_params' => $request->query->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la génération du rapport d\'abandons.');
            return $this->redirectToRoute('admin_enrollment_reports_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in dropout report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_params' => $request->query->all(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la génération du rapport d\'abandons.');
            return $this->redirectToRoute('admin_enrollment_reports_index');
        }
    }

    /**
     * Progress monitoring reports.
     */
    #[Route('/progress', name: 'admin_enrollment_reports_progress', methods: ['GET'])]
    public function progressReport(Request $request, PaginatorInterface $paginator): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Starting progress report generation', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        try {
            $formation = $request->query->get('formation');
            $progressFilter = $request->query->get('progress', 'all');
            $page = $request->query->getInt('page', 1);

            $this->logger->debug('Processing progress report filters', [
                'formation' => $formation,
                'progress_filter' => $progressFilter,
                'page' => $page
            ]);

            $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
                ->andWhere('se.status = :status')
                ->setParameter('status', StudentEnrollment::STATUS_ENROLLED);

            if ($formation) {
                $this->logger->debug('Applying formation filter to progress report', ['formation_id' => $formation]);
                $queryBuilder->andWhere('f.id = :formation')
                             ->setParameter('formation', $formation);
            }

            // Progress filtering
            if ($progressFilter === 'low') {
                $this->logger->debug('Applying low progress filter (< 25%)');
                $queryBuilder->andWhere('sp.completionPercentage < 25');
            } elseif ($progressFilter === 'medium') {
                $this->logger->debug('Applying medium progress filter (25-75%)');
                $queryBuilder->andWhere('sp.completionPercentage BETWEEN 25 AND 75');
            } elseif ($progressFilter === 'high') {
                $this->logger->debug('Applying high progress filter (> 75%)');
                $queryBuilder->andWhere('sp.completionPercentage > 75');
            } elseif ($progressFilter === 'at_risk') {
                $this->logger->debug('Applying at-risk filter');
                $queryBuilder->andWhere('sp.atRiskOfDropout = :at_risk')
                             ->setParameter('at_risk', true);
            }

            $queryBuilder->orderBy('sp.completionPercentage', 'ASC');

            $this->logger->debug('Executing pagination query for progress report');
            $enrollments = $paginator->paginate(
                $queryBuilder->getQuery(),
                $page,
                20
            );

            // Progress distribution
            $this->logger->debug('Calculating progress distribution', ['formation_id' => $formation]);
            $progressDistribution = $this->getProgressDistribution($formation);
            $this->logger->debug('Progress distribution calculated', ['distribution_segments' => count($progressDistribution)]);

            $this->logger->debug('Fetching formations for filter dropdown');
            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('Progress report generated successfully', [
                'execution_time' => round($executionTime, 3),
                'total_enrolled' => $enrollments->getTotalItemCount(),
                'current_page' => $enrollments->getCurrentPageNumber(),
                'progress_filter_applied' => $progressFilter,
                'distribution_segments' => count($progressDistribution),
                'formations_available' => count($formations)
            ]);

            return $this->render('admin/student/enrollment/reports/progress.html.twig', [
                'enrollments' => $enrollments,
                'progressDistribution' => $progressDistribution,
                'formations' => $formations,
                'selectedFormation' => $formation,
                'progressFilter' => $progressFilter,
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in progress report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'request_params' => $request->query->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la génération du rapport de progression.');
            return $this->redirectToRoute('admin_enrollment_reports_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in progress report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_params' => $request->query->all(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la génération du rapport de progression.');
            return $this->redirectToRoute('admin_enrollment_reports_index');
        }
    }

    /**
     * Qualiopi compliance reports.
     */
    #[Route('/qualiopi', name: 'admin_enrollment_reports_qualiopi', methods: ['GET'])]
    public function qualiopiReport(Request $request): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Starting Qualiopi compliance report generation', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        try {
            $startDate = $request->query->get('start_date');
            $endDate = $request->query->get('end_date');
            $formation = $request->query->get('formation');

            $this->logger->debug('Processing Qualiopi report filters', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'formation' => $formation
            ]);

            // Qualiopi compliance metrics
            $this->logger->debug('Calculating Qualiopi compliance metrics');
            $qualiopiMetrics = $this->getQualiopiComplianceMetrics($startDate, $endDate, $formation);
            $this->logger->debug('Qualiopi compliance metrics calculated', [
                'metrics' => array_keys($qualiopiMetrics)
            ]);

            // Attendance tracking compliance
            $this->logger->debug('Calculating attendance compliance metrics');
            $attendanceCompliance = $this->getAttendanceComplianceMetrics($startDate, $endDate, $formation);
            $this->logger->debug('Attendance compliance metrics calculated', [
                'metrics' => array_keys($attendanceCompliance)
            ]);

            // Student satisfaction tracking
            $this->logger->debug('Calculating satisfaction metrics');
            $satisfactionMetrics = $this->getSatisfactionMetrics($startDate, $endDate, $formation);
            $this->logger->debug('Satisfaction metrics calculated', [
                'metrics' => array_keys($satisfactionMetrics)
            ]);

            $this->logger->debug('Fetching formations for filter dropdown');
            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('Qualiopi compliance report generated successfully', [
                'execution_time' => round($executionTime, 3),
                'date_range' => ['start' => $startDate, 'end' => $endDate],
                'formation_filter' => $formation,
                'compliance_scores' => [
                    'enrollment_tracking' => $qualiopiMetrics['enrollment_tracking'] ?? 0,
                    'progress_documentation' => $qualiopiMetrics['progress_documentation'] ?? 0,
                    'attendance_tracking' => $attendanceCompliance['avg_attendance_rate'] ?? 0
                ],
                'formations_available' => count($formations)
            ]);

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

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in Qualiopi compliance report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'request_params' => $request->query->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la génération du rapport Qualiopi.');
            return $this->redirectToRoute('admin_enrollment_reports_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in Qualiopi compliance report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_params' => $request->query->all(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la génération du rapport Qualiopi.');
            return $this->redirectToRoute('admin_enrollment_reports_index');
        }
    }

    /**
     * Custom report builder.
     */
    #[Route('/custom', name: 'admin_enrollment_reports_custom', methods: ['GET', 'POST'])]
    public function customReport(Request $request): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Starting custom report generation', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
            'request_params' => $request->isMethod('POST') ? $request->request->all() : $request->query->all(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        try {
            if ($request->isMethod('POST')) {
                $this->logger->debug('Processing custom report configuration');
                
                $reportConfig = [
                    'title' => $request->request->get('report_title'),
                    'filters' => $request->request->all('filters'),
                    'fields' => $request->request->all('fields'),
                    'groupBy' => $request->request->get('group_by'),
                    'orderBy' => $request->request->get('order_by'),
                    'format' => $request->request->get('format', 'html'),
                ];

                $this->logger->debug('Custom report configuration processed', [
                    'title' => $reportConfig['title'],
                    'format' => $reportConfig['format'],
                    'filters_count' => count($reportConfig['filters']),
                    'fields_count' => count($reportConfig['fields']),
                    'group_by' => $reportConfig['groupBy'],
                    'order_by' => $reportConfig['orderBy']
                ]);

                $this->logger->debug('Generating custom report data');
                $reportData = $this->generateCustomReport($reportConfig);
                $this->logger->debug('Custom report data generated', [
                    'total_records' => $reportData['summary']['total_records'] ?? 0
                ]);

                if ($reportConfig['format'] === 'export') {
                    $this->logger->info('Exporting custom report', [
                        'format' => 'export',
                        'title' => $reportConfig['title']
                    ]);
                    return $this->exportCustomReport($reportData, $reportConfig);
                }

                $executionTime = microtime(true) - $startTime;
                $this->logger->info('Custom report generated successfully', [
                    'execution_time' => round($executionTime, 3),
                    'report_title' => $reportConfig['title'],
                    'total_records' => $reportData['summary']['total_records'] ?? 0,
                    'format' => $reportConfig['format']
                ]);

                return $this->render('admin/student/enrollment/reports/custom_results.html.twig', [
                    'reportData' => $reportData,
                    'reportConfig' => $reportConfig,
                ]);
            }

            $this->logger->debug('Displaying custom report builder form');
            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);
            $availableFields = $this->getAvailableReportFields();

            $this->logger->debug('Custom report builder form prepared', [
                'formations_count' => count($formations),
                'available_fields_count' => count($availableFields)
            ]);

            return $this->render('admin/student/enrollment/reports/custom.html.twig', [
                'formations' => $formations,
                'availableFields' => $availableFields,
            ]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in custom report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'request_method' => $request->getMethod(),
                'request_params' => $request->isMethod('POST') ? $request->request->all() : $request->query->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la génération du rapport personnalisé.');
            return $this->redirectToRoute('admin_enrollment_reports_index');

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid configuration for custom report', [
                'error_message' => $e->getMessage(),
                'request_params' => $request->isMethod('POST') ? $request->request->all() : $request->query->all(),
                'user_email' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('warning', 'Configuration de rapport invalide: ' . $e->getMessage());
            return $this->redirectToRoute('admin_enrollment_reports_custom');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in custom report', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_method' => $request->getMethod(),
                'request_params' => $request->isMethod('POST') ? $request->request->all() : $request->query->all(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la génération du rapport personnalisé.');
            return $this->redirectToRoute('admin_enrollment_reports_index');
        }
    }

    /**
     * API endpoint for enrollment analytics data.
     */
    #[Route('/api/analytics', name: 'admin_enrollment_reports_api_analytics', methods: ['GET'])]
    public function analyticsApi(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $this->logger->info('Starting analytics API request', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        try {
            $type = $request->query->get('type', 'enrollment_trends');
            $startDate = $request->query->get('start_date');
            $endDate = $request->query->get('end_date');
            $formation = $request->query->get('formation');

            $this->logger->debug('Processing analytics API request', [
                'type' => $type,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'formation' => $formation
            ]);

            $data = match ($type) {
                'enrollment_trends' => $this->getEnrollmentTrends($startDate, $endDate),
                'completion_rates' => $this->getCompletionRatesByFormation(),
                'dropout_analysis' => $this->getDropoutAnalysis(),
                'progress_distribution' => $this->getProgressDistribution($formation),
                default => []
            };

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('Analytics API request completed successfully', [
                'execution_time' => round($executionTime, 3),
                'type' => $type,
                'data_points' => count($data),
                'response_size' => strlen(json_encode($data))
            ]);

            return new JsonResponse($data);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in analytics API', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'request_params' => $request->query->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'error' => 'Database error',
                'message' => 'Une erreur de base de données s\'est produite.'
            ], 500);

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in analytics API', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_params' => $request->query->all(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'error' => 'Internal server error',
                'message' => 'Une erreur inattendue s\'est produite.'
            ], 500);
        }
    }

    /**
     * Export enrollment data in various formats.
     */
    #[Route('/export', name: 'admin_enrollment_reports_export', methods: ['POST'])]
    public function exportReport(Request $request): Response
    {
        $startTime = microtime(true);
        $this->logger->info('Starting enrollment data export', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->request->all(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        try {
            $format = $request->request->get('format', 'csv');
            $filters = $request->request->all('filters');
            $fields = $request->request->all('fields');

            $this->logger->debug('Processing export request', [
                'format' => $format,
                'filters_count' => count($filters),
                'fields_count' => count($fields),
                'fields' => $fields
            ]);

            $this->logger->debug('Fetching enrollments with applied filters');
            $enrollments = $this->enrollmentRepository->findEnrollmentsWithFilters($filters);
            $this->logger->debug('Enrollments fetched for export', [
                'total_enrollments' => count($enrollments)
            ]);

            $response = $this->generateExportResponse($enrollments, $format, $fields);

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('Enrollment data export completed successfully', [
                'execution_time' => round($executionTime, 3),
                'format' => $format,
                'total_records' => count($enrollments),
                'fields_exported' => count($fields)
            ]);

            return $response;

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in enrollment export', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'request_params' => $request->request->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de l\'export des données.');
            return $this->redirectToRoute('admin_enrollment_reports_index');

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid export parameters', [
                'error_message' => $e->getMessage(),
                'request_params' => $request->request->all(),
                'user_email' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('warning', 'Paramètres d\'export invalides: ' . $e->getMessage());
            return $this->redirectToRoute('admin_enrollment_reports_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in enrollment export', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_params' => $request->request->all(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'export.');
            return $this->redirectToRoute('admin_enrollment_reports_index');
        }
    }

    /**
     * Get formation-specific analytics.
     */
    private function getFormationAnalytics(?string $formationId): array
    {
        $this->logger->debug('Starting formation analytics calculation', [
            'formation_id' => $formationId
        ]);

        try {
            if (!$formationId) {
                $this->logger->debug('No formation ID provided, returning empty analytics');
                return [];
            }

            $formation = $this->formationRepository->find($formationId);
            if (!$formation) {
                $this->logger->warning('Formation not found for analytics', [
                    'formation_id' => $formationId
                ]);
                return [];
            }

            $this->logger->debug('Calculating formation analytics metrics', [
                'formation_title' => $formation->getTitle()
            ]);

            $analytics = [
                'formation' => $formation,
                'totalEnrollments' => $this->enrollmentRepository->countEnrollmentsByFormation($formation),
                'completionRate' => $this->enrollmentRepository->getFormationCompletionRate($formation),
                'dropoutRate' => $this->enrollmentRepository->getFormationDropoutRate($formation),
                'avgDuration' => $this->getAverageCompletionDuration($formation),
                'activeEnrollments' => $this->enrollmentRepository->countEnrollmentsByFormationAndStatus($formation, StudentEnrollment::STATUS_ENROLLED),
            ];

            $this->logger->debug('Formation analytics calculated successfully', [
                'formation_id' => $formationId,
                'total_enrollments' => $analytics['totalEnrollments'],
                'completion_rate' => $analytics['completionRate'],
                'dropout_rate' => $analytics['dropoutRate'],
                'active_enrollments' => $analytics['activeEnrollments']
            ]);

            return $analytics;

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in formation analytics', [
                'formation_id' => $formationId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in formation analytics', [
                'formation_id' => $formationId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get enrollment trends over time.
     */
    private function getEnrollmentTrends(?string $startDate, ?string $endDate): array
    {
        $this->logger->debug('Starting enrollment trends calculation', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
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
                $this->logger->debug('Applied date range filter to enrollment trends', [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]);
            }

            $sql .= " GROUP BY DATE(se.enrolled_at) ORDER BY date ASC";

            $this->logger->debug('Executing enrollment trends SQL query');
            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $result = $stmt->executeQuery($params)->fetchAllAssociative();

            $this->logger->debug('Enrollment trends calculated successfully', [
                'trends_count' => count($result),
                'date_range' => ['start' => $startDate, 'end' => $endDate]
            ]);

            return $result;

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in enrollment trends calculation', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in enrollment trends calculation', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get completion rates by formation.
     */
    private function getCompletionRatesByFormation(): array
    {
        $this->logger->debug('Starting completion rates calculation by formation');

        try {
            $formations = $this->formationRepository->findBy(['isActive' => true]);
            $this->logger->debug('Fetched active formations for completion rates', [
                'formations_count' => count($formations)
            ]);

            $completionRates = [];

            foreach ($formations as $formation) {
                $this->logger->debug('Calculating completion rate for formation', [
                    'formation_id' => $formation->getId(),
                    'formation_title' => $formation->getTitle()
                ]);

                $formationData = [
                    'formation' => $formation->getTitle(),
                    'completion_rate' => $this->enrollmentRepository->getFormationCompletionRate($formation),
                    'dropout_rate' => $this->enrollmentRepository->getFormationDropoutRate($formation),
                    'total_enrollments' => $this->enrollmentRepository->countEnrollmentsByFormation($formation),
                ];

                $completionRates[] = $formationData;

                $this->logger->debug('Completion rate calculated for formation', [
                    'formation_id' => $formation->getId(),
                    'completion_rate' => $formationData['completion_rate'],
                    'dropout_rate' => $formationData['dropout_rate'],
                    'total_enrollments' => $formationData['total_enrollments']
                ]);
            }

            $this->logger->debug('Completion rates calculation completed successfully', [
                'formations_analyzed' => count($completionRates)
            ]);

            return $completionRates;

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in completion rates calculation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in completion rates calculation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get dropout analysis data.
     */
    private function getDropoutAnalysis(): array
    {
        $this->logger->debug('Starting dropout analysis calculation');

        try {
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

            $this->logger->debug('Executing dropout analysis SQL query');
            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $result = $stmt->executeQuery()->fetchAllAssociative();

            $this->logger->debug('Dropout analysis calculated successfully', [
                'dropout_reasons_count' => count($result),
                'total_analyzed_dropouts' => array_sum(array_column($result, 'count'))
            ]);

            // Log detailed breakdown
            foreach ($result as $dropout) {
                $this->logger->debug('Dropout reason analysis', [
                    'reason' => $dropout['dropout_reason'],
                    'count' => $dropout['count'],
                    'avg_days_to_dropout' => round((float)$dropout['avg_days_to_dropout'], 2)
                ]);
            }

            return $result;

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in dropout analysis', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in dropout analysis', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];
        }
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
        $this->logger->debug('Starting risk indicators calculation');

        try {
            $indicators = [
                'at_risk_students' => $this->enrollmentRepository->count(['status' => StudentEnrollment::STATUS_ENROLLED, 'progress.atRiskOfDropout' => true]),
                'overdue_enrollments' => count($this->enrollmentRepository->findOverdueEnrollments()),
                'missing_progress' => count($this->enrollmentRepository->findEnrollmentsWithoutProgress()),
                'high_dropout_formations' => $this->getHighDropoutFormations(),
            ];

            $this->logger->debug('Risk indicators calculated successfully', [
                'at_risk_students' => $indicators['at_risk_students'],
                'overdue_enrollments' => $indicators['overdue_enrollments'],
                'missing_progress' => $indicators['missing_progress'],
                'high_dropout_formations_count' => count($indicators['high_dropout_formations'])
            ]);

            // Log high dropout formations details
            if (!empty($indicators['high_dropout_formations'])) {
                foreach ($indicators['high_dropout_formations'] as $formation) {
                    $this->logger->warning('High dropout rate detected', [
                        'formation' => $formation['formation'],
                        'dropout_rate' => $formation['dropout_rate']
                    ]);
                }
            }

            return $indicators;

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Database error in risk indicators calculation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [
                'at_risk_students' => 0,
                'overdue_enrollments' => 0,
                'missing_progress' => 0,
                'high_dropout_formations' => [],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in risk indicators calculation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [
                'at_risk_students' => 0,
                'overdue_enrollments' => 0,
                'missing_progress' => 0,
                'high_dropout_formations' => [],
            ];
        }
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
        $this->logger->debug('Starting export response generation', [
            'format' => $format,
            'enrollments_count' => count($enrollments),
            'fields_count' => count($fields),
            'fields' => $fields
        ]);

        try {
            $response = new StreamedResponse();
            $response->setCallback(function () use ($enrollments, $format, $fields) {
                try {
                    $handle = fopen('php://output', 'w');
                    
                    if (!$handle) {
                        $this->logger->error('Failed to open output stream for export');
                        throw new \RuntimeException('Cannot open output stream');
                    }

                    if ($format === 'csv') {
                        $this->logger->debug('Generating CSV export');
                        
                        // Write CSV headers
                        $headers = [];
                        foreach ($fields as $field) {
                            $headers[] = $this->getAvailableReportFields()[$field] ?? $field;
                        }
                        
                        if (fputcsv($handle, $headers) === false) {
                            $this->logger->error('Failed to write CSV headers');
                            throw new \RuntimeException('Cannot write CSV headers');
                        }

                        $this->logger->debug('CSV headers written successfully', [
                            'headers_count' => count($headers)
                        ]);

                        // Write data rows
                        $rowCount = 0;
                        foreach ($enrollments as $enrollment) {
                            $row = [];
                            foreach ($fields as $field) {
                                $row[] = $this->getFieldValue($enrollment, $field);
                            }
                            
                            if (fputcsv($handle, $row) === false) {
                                $this->logger->error('Failed to write CSV row', [
                                    'row_number' => $rowCount + 1
                                ]);
                                throw new \RuntimeException('Cannot write CSV row');
                            }
                            $rowCount++;
                        }

                        $this->logger->debug('CSV data rows written successfully', [
                            'rows_written' => $rowCount
                        ]);
                    }

                    if (fclose($handle) === false) {
                        $this->logger->warning('Failed to properly close output stream');
                    }

                } catch (\Exception $e) {
                    $this->logger->error('Error during export response callback', [
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'stack_trace' => $e->getTraceAsString()
                    ]);
                    
                    // Output error message to user
                    echo "Erreur lors de l'export: " . $e->getMessage();
                }
            });

            $filename = sprintf('enrollment_report_%s.%s', date('Y-m-d_H-i-s'), $format);
            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

            $this->logger->debug('Export response configured successfully', [
                'filename' => $filename,
                'content_type' => 'application/octet-stream'
            ]);

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Error in export response generation', [
                'format' => $format,
                'enrollments_count' => count($enrollments),
                'fields_count' => count($fields),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            // Return an error response
            $errorResponse = new StreamedResponse();
            $errorResponse->setCallback(function () use ($e) {
                echo "Erreur lors de la génération de l'export: " . $e->getMessage();
            });
            $errorResponse->headers->set('Content-Type', 'text/plain');
            return $errorResponse;
        }
    }

    /**
     * Get field value for export.
     */
    private function getFieldValue(StudentEnrollment $enrollment, string $field): string
    {
        try {
            $value = match ($field) {
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

            $this->logger->debug('Field value extracted successfully', [
                'field' => $field,
                'enrollment_id' => $enrollment->getId(),
                'value_length' => strlen($value)
            ]);

            return $value;

        } catch (\Exception $e) {
            $this->logger->warning('Error extracting field value for export', [
                'field' => $field,
                'enrollment_id' => $enrollment->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);

            return 'N/A'; // Return placeholder value on error
        }
    }
}
