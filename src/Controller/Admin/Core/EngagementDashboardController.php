<?php

declare(strict_types=1);

namespace App\Controller\Admin\Core;

use App\Repository\Core\AttendanceRecordRepository;
use App\Repository\Core\StudentProgressRepository;
use App\Service\Core\DropoutPreventionService;
use DateTime;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * EngagementDashboardController - Critical for Qualiopi Criterion 12 compliance.
 *
 * Provides admin interface for monitoring student engagement, attendance rates,
 * dropout risks, and generating compliance reports required for Qualiopi certification.
 */
#[Route('/admin/engagement')]
#[IsGranted('ROLE_ADMIN')]
class EngagementDashboardController extends AbstractController
{
    public function __construct(
        private StudentProgressRepository $progressRepository,
        private AttendanceRecordRepository $attendanceRepository,
        private DropoutPreventionService $dropoutService,
        private Pdf $knpSnappyPdf,
        private LoggerInterface $logger,
    ) {}

    /**
     * Main engagement dashboard.
     */
    #[Route('/', name: 'admin_engagement_dashboard')]
    public function dashboard(): Response
    {
        $startTime = microtime(true);
        $this->logger->info('EngagementDashboard: Starting dashboard generation', [
            'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            // Get key metrics for dashboard
            $this->logger->debug('EngagementDashboard: Fetching engagement metrics');
            $engagementMetrics = $this->progressRepository->getEngagementMetrics();
            $this->logger->debug('EngagementDashboard: Engagement metrics retrieved', [
                'metrics_count' => count($engagementMetrics),
                'average_engagement' => $engagementMetrics['averageEngagement'] ?? 'N/A',
            ]);

            $this->logger->debug('EngagementDashboard: Fetching attendance statistics');
            $attendanceStats = $this->attendanceRepository->getAttendanceStats();
            $this->logger->debug('EngagementDashboard: Attendance stats retrieved', [
                'overall_rate' => $attendanceStats['overall_attendance_rate'] ?? 'N/A',
                'stats_count' => count($attendanceStats),
            ]);

            $this->logger->debug('EngagementDashboard: Fetching retention statistics');
            $retentionStats = $this->progressRepository->getRetentionStats();
            $this->logger->debug('EngagementDashboard: Retention stats retrieved', [
                'dropout_rate' => $retentionStats['dropoutRate'] ?? 'N/A',
                'retention_rate' => $retentionStats['retentionRate'] ?? 'N/A',
            ]);

            $this->logger->debug('EngagementDashboard: Identifying at-risk students');
            $atRiskStudents = $this->progressRepository->findAtRiskStudents();
            $this->logger->info('EngagementDashboard: At-risk students identified', [
                'at_risk_count' => count($atRiskStudents),
            ]);

            $this->logger->debug('EngagementDashboard: Identifying low engagement students');
            $lowEngagement = $this->progressRepository->findLowEngagementStudents();
            $this->logger->info('EngagementDashboard: Low engagement students identified', [
                'low_engagement_count' => count($lowEngagement),
            ]);

            $this->logger->debug('EngagementDashboard: Identifying students with poor attendance');
            $poorAttendance = $this->attendanceRepository->findStudentsWithPoorAttendance();
            $this->logger->info('EngagementDashboard: Poor attendance students identified', [
                'poor_attendance_count' => count($poorAttendance),
            ]);

            $this->logger->debug('EngagementDashboard: Generating alerts');
            $alerts = $this->generateAlerts($engagementMetrics, $attendanceStats, $retentionStats);
            $this->logger->info('EngagementDashboard: Alerts generated', [
                'alerts_count' => count($alerts),
                'critical_alerts' => count(array_filter($alerts, static fn ($alert) => $alert['level'] === 'critical')),
                'warning_alerts' => count(array_filter($alerts, static fn ($alert) => $alert['level'] === 'warning')),
            ]);

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('EngagementDashboard: Dashboard generation completed successfully', [
                'execution_time_ms' => round($executionTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/engagement/dashboard.html.twig', [
                'engagement_metrics' => $engagementMetrics,
                'attendance_stats' => $attendanceStats,
                'retention_stats' => $retentionStats,
                'at_risk_count' => count($atRiskStudents),
                'low_engagement_count' => count($lowEngagement),
                'poor_attendance_count' => count($poorAttendance),
                'alerts' => $alerts,
            ]);
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->logger->error('EngagementDashboard: Dashboard generation failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération du tableau de bord d\'engagement. Veuillez réessayer.');

            // Return a safe fallback response
            return $this->render('admin/engagement/dashboard.html.twig', [
                'engagement_metrics' => [],
                'attendance_stats' => [],
                'retention_stats' => [],
                'at_risk_count' => 0,
                'low_engagement_count' => 0,
                'poor_attendance_count' => 0,
                'alerts' => [[
                    'level' => 'critical',
                    'message' => 'Erreur lors du chargement des données',
                    'action' => 'Contactez l\'administrateur système',
                ]],
            ]);
        }
    }

    /**
     * Detailed view of at-risk students.
     */
    #[Route('/at-risk', name: 'admin_engagement_at_risk')]
    public function atRiskStudents(): Response
    {
        $startTime = microtime(true);
        $this->logger->info('EngagementDashboard: Starting at-risk students analysis', [
            'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->logger->debug('EngagementDashboard: Detecting at-risk students using DropoutPreventionService');
            $atRiskStudents = $this->dropoutService->detectAtRiskStudents();
            $this->logger->info('EngagementDashboard: At-risk students detected', [
                'total_at_risk' => count($atRiskStudents),
            ]);

            $this->logger->debug('EngagementDashboard: Finding critical risk students');
            $criticalRisk = $this->progressRepository->findCriticalRiskStudents();
            $this->logger->warning('EngagementDashboard: Critical risk students identified', [
                'critical_risk_count' => count($criticalRisk),
                'requires_immediate_intervention' => count($criticalRisk) > 0,
            ]);

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('EngagementDashboard: At-risk analysis completed successfully', [
                'execution_time_ms' => round($executionTime * 1000, 2),
                'total_students_analyzed' => count($atRiskStudents) + count($criticalRisk),
            ]);

            return $this->render('admin/engagement/at_risk.html.twig', [
                'at_risk_students' => $atRiskStudents,
                'critical_risk_students' => $criticalRisk,
                'total_count' => count($atRiskStudents),
            ]);
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->logger->error('EngagementDashboard: At-risk students analysis failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'analyse des étudiants à risque. Veuillez réessayer.');

            return $this->render('admin/engagement/at_risk.html.twig', [
                'at_risk_students' => [],
                'critical_risk_students' => [],
                'total_count' => 0,
            ]);
        }
    }

    /**
     * Attendance monitoring interface.
     */
    #[Route('/attendance', name: 'admin_engagement_attendance')]
    public function attendance(): Response
    {
        $startTime = microtime(true);
        $this->logger->info('EngagementDashboard: Starting attendance monitoring analysis', [
            'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->logger->debug('EngagementDashboard: Fetching attendance statistics');
            $attendanceStats = $this->attendanceRepository->getAttendanceStats();
            $this->logger->debug('EngagementDashboard: Attendance stats retrieved', [
                'overall_rate' => $attendanceStats['overall_attendance_rate'] ?? 'N/A',
                'total_sessions' => $attendanceStats['total_sessions'] ?? 'N/A',
            ]);

            $this->logger->debug('EngagementDashboard: Finding students with poor attendance');
            $poorAttendance = $this->attendanceRepository->findStudentsWithPoorAttendance();
            $this->logger->info('EngagementDashboard: Poor attendance students identified', [
                'poor_attendance_count' => count($poorAttendance),
            ]);

            $this->logger->debug('EngagementDashboard: Finding frequent absentees');
            $frequentAbsentees = $this->attendanceRepository->findFrequentAbsentees();
            $this->logger->warning('EngagementDashboard: Frequent absentees identified', [
                'frequent_absentees_count' => count($frequentAbsentees),
                'requires_intervention' => count($frequentAbsentees) > 0,
            ]);

            $this->logger->debug('EngagementDashboard: Finding recent absentees');
            $recentAbsentees = $this->attendanceRepository->findRecentAbsentees();
            $this->logger->info('EngagementDashboard: Recent absentees identified', [
                'recent_absentees_count' => count($recentAbsentees),
            ]);

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('EngagementDashboard: Attendance monitoring completed successfully', [
                'execution_time_ms' => round($executionTime * 1000, 2),
                'total_attendance_issues' => count($poorAttendance) + count($frequentAbsentees),
            ]);

            return $this->render('admin/engagement/attendance.html.twig', [
                'attendance_stats' => $attendanceStats,
                'poor_attendance_students' => $poorAttendance,
                'frequent_absentees' => $frequentAbsentees,
                'recent_absentees' => $recentAbsentees,
            ]);
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->logger->error('EngagementDashboard: Attendance monitoring failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'analyse de l\'assiduité. Veuillez réessayer.');

            return $this->render('admin/engagement/attendance.html.twig', [
                'attendance_stats' => [],
                'poor_attendance_students' => [],
                'frequent_absentees' => [],
                'recent_absentees' => [],
            ]);
        }
    }

    /**
     * Generate Qualiopi compliance report.
     */
    #[Route('/qualiopi-report', name: 'admin_engagement_quality_report')]
    public function qualiopiReport(): Response
    {
        $startTime = microtime(true);
        $this->logger->info('EngagementDashboard: Starting Qualiopi compliance report generation', [
            'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'compliance_criterion' => 'Qualiopi Criterion 12 - Engagement and Retention',
        ]);

        try {
            $this->logger->debug('EngagementDashboard: Generating retention report');
            $retentionReport = $this->dropoutService->generateRetentionReport();
            $this->logger->info('EngagementDashboard: Retention report generated', [
                'total_students' => $retentionReport['total_students'] ?? 0,
                'retention_rate' => $retentionReport['retention_rate'] ?? 'N/A',
                'dropout_rate' => $retentionReport['dropout_rate'] ?? 'N/A',
            ]);

            $this->logger->debug('EngagementDashboard: Analyzing dropout patterns');
            $dropoutPatterns = $this->dropoutService->analyzeDropoutPatterns();
            $this->logger->info('EngagementDashboard: Dropout patterns analyzed', [
                'patterns_identified' => count($dropoutPatterns),
                'critical_patterns' => array_filter($dropoutPatterns, static fn ($pattern) => ($pattern['severity'] ?? '') === 'high'),
            ]);

            $this->logger->debug('EngagementDashboard: Calculating compliance score');
            $complianceScore = $this->calculateComplianceScore($retentionReport);
            $this->logger->info('EngagementDashboard: Qualiopi compliance score calculated', [
                'total_score' => $complianceScore['total_score'],
                'percentage' => $complianceScore['percentage'],
                'compliance_level' => $complianceScore['level'],
                'meets_qualiopi_standards' => $complianceScore['percentage'] >= 65,
            ]);

            if ($complianceScore['percentage'] < 65) {
                $this->logger->warning('EngagementDashboard: Compliance score below Qualiopi threshold', [
                    'current_score' => $complianceScore['percentage'],
                    'required_score' => 65,
                    'improvement_needed' => 65 - $complianceScore['percentage'],
                ]);
            }

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('EngagementDashboard: Qualiopi report generation completed successfully', [
                'execution_time_ms' => round($executionTime * 1000, 2),
                'report_sections' => ['retention', 'dropout_patterns', 'compliance_score'],
            ]);

            return $this->render('admin/engagement/qualiopi_report.html.twig', [
                'report' => $retentionReport,
                'dropout_patterns' => $dropoutPatterns,
                'compliance_score' => $complianceScore,
            ]);
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->logger->error('EngagementDashboard: Qualiopi report generation failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
                'compliance_impact' => 'Critical - Qualiopi reporting unavailable',
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la génération du rapport Qualiopi. Contactez l\'administrateur système.');

            return $this->render('admin/engagement/qualiopi_report.html.twig', [
                'report' => [],
                'dropout_patterns' => [],
                'compliance_score' => [
                    'total_score' => 0,
                    'percentage' => 0,
                    'level' => 'Erreur système',
                    'criteria' => [],
                ],
            ]);
        }
    }

    /**
     * Export retention data (PDF/Excel/CSV).
     */
    #[Route('/export/{format}', name: 'admin_engagement_export', requirements: ['format' => 'pdf|excel|csv'])]
    public function exportData(string $format): Response
    {
        $startTime = microtime(true);
        $this->logger->info('EngagementDashboard: Starting data export', [
            'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
            'export_format' => $format,
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->logger->debug('EngagementDashboard: Fetching export data from DropoutPreventionService', [
                'format' => $format,
            ]);

            $data = $this->dropoutService->exportRetentionData($format);
            $filename = 'rapport_engagement_' . date('Y-m-d_H-i-s');

            $this->logger->info('EngagementDashboard: Export data retrieved successfully', [
                'data_size' => is_array($data) ? count($data) : 'unknown',
                'students_count' => isset($data['at_risk_students']) ? count($data['at_risk_students']) : 0,
                'filename' => $filename,
            ]);

            switch ($format) {
                case 'pdf':
                    $this->logger->debug('EngagementDashboard: Generating PDF export using KnpSnappyBundle');

                    try {
                        // Generate PDF using KnpSnappyBundle
                        $html = $this->renderView('admin/engagement/export_pdf.html.twig', [
                            'data' => $data,
                            'generated_at' => new DateTime(),
                            'title' => 'Rapport d\'Engagement et de Rétention - Critère Qualiopi 12',
                        ]);

                        $this->logger->debug('EngagementDashboard: HTML template rendered for PDF', [
                            'html_length' => strlen($html),
                        ]);

                        $pdfContent = $this->knpSnappyPdf->getOutputFromHtml($html, [
                            'page-size' => 'A4',
                            'margin-top' => '20mm',
                            'margin-right' => '15mm',
                            'margin-bottom' => '20mm',
                            'margin-left' => '15mm',
                            'encoding' => 'UTF-8',
                            'footer-right' => 'Page [page] sur [toPage]',
                            'footer-font-size' => '9',
                            'footer-spacing' => '10',
                            'header-spacing' => '10',
                        ]);

                        $this->logger->info('EngagementDashboard: PDF generated successfully', [
                            'pdf_size_bytes' => strlen($pdfContent),
                            'filename' => $filename . '.pdf',
                        ]);

                        return new PdfResponse(
                            $pdfContent,
                            $filename . '.pdf',
                            'application/pdf',
                            'attachment',
                        );
                    } catch (Exception $pdfException) {
                        $this->logger->error('EngagementDashboard: PDF generation failed', [
                            'pdf_error' => $pdfException->getMessage(),
                            'pdf_error_code' => $pdfException->getCode(),
                        ]);

                        throw new RuntimeException('PDF generation failed: ' . $pdfException->getMessage(), 0, $pdfException);
                    }

                case 'excel':
                    $this->logger->debug('EngagementDashboard: Generating Excel export using PHPSpreadsheet');

                    try {
                        // Generate Excel using PHPSpreadsheet
                        $spreadsheet = new Spreadsheet();
                        $sheet = $spreadsheet->getActiveSheet();

                        // Set title
                        $sheet->setTitle('Rapport Engagement');

                        // Headers
                        $headers = [
                            'A1' => 'Nom Étudiant',
                            'B1' => 'Email',
                            'C1' => 'Formation',
                            'D1' => 'Score Risque',
                            'E1' => 'Score Engagement',
                            'F1' => 'Taux Completion (%)',
                            'G1' => 'Taux Assiduité (%)',
                            'H1' => 'Dernière Activité',
                            'I1' => 'Signaux Difficulté',
                            'J1' => 'Recommandations',
                        ];

                        foreach ($headers as $cell => $value) {
                            $sheet->setCellValue($cell, $value);
                        }

                        // Style headers
                        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
                        $sheet->getStyle('A1:J1')->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFE6E6FA')
                        ;

                        // Data rows
                        $row = 2;
                        $studentsProcessed = 0;
                        foreach ($data['at_risk_students'] as $student) {
                            $sheet->setCellValue('A' . $row, $student['student_name']);
                            $sheet->setCellValue('B' . $row, $student['student_email']);
                            $sheet->setCellValue('C' . $row, $student['formation']);
                            $sheet->setCellValue('D' . $row, $student['risk_score']);
                            $sheet->setCellValue('E' . $row, $student['engagement_score']);
                            $sheet->setCellValue('F' . $row, round((float) $student['completion_percentage'], 1));
                            $sheet->setCellValue('G' . $row, round((float) $student['attendance_rate'], 1));
                            $sheet->setCellValue('H' . $row, $student['last_activity']);
                            $sheet->setCellValue('I' . $row, $student['difficulty_signals']);
                            $sheet->setCellValue('J' . $row, $student['recommendations']);
                            $row++;
                            $studentsProcessed++;
                        }

                        $this->logger->debug('EngagementDashboard: Excel data populated', [
                            'students_processed' => $studentsProcessed,
                            'total_rows' => $row - 1,
                        ]);

                        // Auto-size columns
                        foreach (range('A', 'J') as $col) {
                            $sheet->getColumnDimension($col)->setAutoSize(true);
                        }

                        // Create response
                        $response = new StreamedResponse();
                        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.xlsx"');

                        $response->setCallback(static function () use ($spreadsheet) {
                            $writer = new Xlsx($spreadsheet);
                            $writer->save('php://output');
                        });

                        $this->logger->info('EngagementDashboard: Excel export completed successfully', [
                            'filename' => $filename . '.xlsx',
                            'students_exported' => $studentsProcessed,
                        ]);

                        return $response;
                    } catch (Exception $excelException) {
                        $this->logger->error('EngagementDashboard: Excel generation failed', [
                            'excel_error' => $excelException->getMessage(),
                            'excel_error_code' => $excelException->getCode(),
                        ]);

                        throw new RuntimeException('Excel generation failed: ' . $excelException->getMessage(), 0, $excelException);
                    }

                case 'csv':
                    $this->logger->debug('EngagementDashboard: Generating CSV export');

                    try {
                        // Generate CSV response
                        $response = new Response();
                        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
                        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');

                        $handle = fopen('php://temp', 'w+');

                        // Add UTF-8 BOM for Excel compatibility
                        fwrite($handle, "\xEF\xBB\xBF");

                        // CSV headers
                        fputcsv($handle, [
                            'Nom Étudiant', 'Email', 'Formation', 'Score Risque', 'Score Engagement',
                            'Taux Completion (%)', 'Taux Assiduité (%)', 'Dernière Activité', 'Signaux Difficulté', 'Recommandations',
                        ]);

                        // CSV data
                        $studentsProcessed = 0;
                        foreach ($data['at_risk_students'] as $student) {
                            fputcsv($handle, [
                                $student['student_name'],
                                $student['student_email'],
                                $student['formation'],
                                $student['risk_score'],
                                $student['engagement_score'],
                                round((float) $student['completion_percentage'], 1),
                                round((float) $student['attendance_rate'], 1),
                                $student['last_activity'],
                                $student['difficulty_signals'],
                                $student['recommendations'],
                            ]);
                            $studentsProcessed++;
                        }

                        rewind($handle);
                        $csvContent = stream_get_contents($handle);
                        fclose($handle);

                        $response->setContent($csvContent);

                        $this->logger->info('EngagementDashboard: CSV export completed successfully', [
                            'filename' => $filename . '.csv',
                            'students_exported' => $studentsProcessed,
                            'csv_size_bytes' => strlen($csvContent),
                        ]);

                        return $response;
                    } catch (Exception $csvException) {
                        $this->logger->error('EngagementDashboard: CSV generation failed', [
                            'csv_error' => $csvException->getMessage(),
                            'csv_error_code' => $csvException->getCode(),
                        ]);

                        throw new RuntimeException('CSV generation failed: ' . $csvException->getMessage(), 0, $csvException);
                    }
            }

            $this->logger->error('EngagementDashboard: Unsupported export format requested', [
                'format' => $format,
                'supported_formats' => ['pdf', 'excel', 'csv'],
            ]);

            return new JsonResponse(['error' => 'Format not supported'], 400);
        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;
            $this->logger->error('EngagementDashboard: Export operation failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'export_format' => $format,
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', "Une erreur est survenue lors de l'export {$format}. Veuillez réessayer.");

            return new JsonResponse([
                'error' => 'Export failed',
                'format' => $format,
                'message' => 'Une erreur technique est survenue lors de la génération du fichier.',
            ], 500);
        }
    }

    /**
     * Trigger risk analysis for all students.
     */
    #[Route('/api/analyze-risks', name: 'admin_engagement_api_analyze_risks', methods: ['POST'])]
    public function analyzeRisks(): JsonResponse
    {
        $startTime = microtime(true);
        $this->logger->info('EngagementDashboard: Starting API risk analysis for all students', [
            'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'analysis_type' => 'full_cohort',
        ]);

        try {
            $this->logger->debug('EngagementDashboard: Executing comprehensive risk detection');
            $atRiskStudents = $this->dropoutService->detectAtRiskStudents();

            $executionTime = microtime(true) - $startTime;
            $this->logger->info('EngagementDashboard: Risk analysis completed successfully', [
                'at_risk_count' => count($atRiskStudents),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'analyzed_at' => (new DateTime())->format('Y-m-d H:i:s'),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            // Log detailed analysis if there are concerning numbers
            if (count($atRiskStudents) > 10) {
                $this->logger->warning('EngagementDashboard: High number of at-risk students detected', [
                    'at_risk_count' => count($atRiskStudents),
                    'threshold_exceeded' => true,
                    'recommended_action' => 'Review intervention strategies',
                ]);
            }

            return new JsonResponse([
                'message' => 'Risk analysis completed',
                'at_risk_count' => count($atRiskStudents),
                'analyzed_at' => (new DateTime())->format('Y-m-d H:i:s'),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->logger->error('EngagementDashboard: API risk analysis failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'user_id' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anonymous',
                'impact' => 'Risk analysis unavailable - manual review required',
            ]);

            return new JsonResponse([
                'message' => 'Risk analysis failed',
                'error' => 'Une erreur technique est survenue lors de l\'analyse des risques',
                'at_risk_count' => 0,
                'analyzed_at' => (new DateTime())->format('Y-m-d H:i:s'),
                'status' => 'error',
            ], 500);
        }
    }

    /**
     * Generate alerts for dashboard.
     */
    private function generateAlerts(array $engagementMetrics, array $attendanceStats, array $retentionStats): array
    {
        $this->logger->debug('EngagementDashboard: Starting alert generation', [
            'engagement_metrics_count' => count($engagementMetrics),
            'attendance_stats_count' => count($attendanceStats),
            'retention_stats_count' => count($retentionStats),
        ]);

        $alerts = [];

        try {
            // Critical alerts (require immediate action)
            $dropoutRate = (float) ($retentionStats['dropoutRate'] ?? 0);
            if ($dropoutRate > 20) {
                $alerts[] = [
                    'level' => 'critical',
                    'message' => "Taux d'abandon critique: " . round($dropoutRate, 1) . '%',
                    'action' => 'Intervention immédiate requise',
                ];
                $this->logger->warning('EngagementDashboard: Critical dropout rate alert generated', [
                    'dropout_rate' => $dropoutRate,
                    'threshold' => 20,
                    'severity' => 'critical',
                ]);
            }

            $attendanceRate = (float) ($attendanceStats['overall_attendance_rate'] ?? 0);
            if ($attendanceRate < 70) {
                $alerts[] = [
                    'level' => 'critical',
                    'message' => "Taux d'assiduité global insuffisant: " . round($attendanceRate, 1) . '%',
                    'action' => 'Revoir la stratégie d\'engagement',
                ];
                $this->logger->warning('EngagementDashboard: Critical attendance rate alert generated', [
                    'attendance_rate' => $attendanceRate,
                    'threshold' => 70,
                    'severity' => 'critical',
                ]);
            }

            // Warning alerts
            $averageEngagement = (float) ($engagementMetrics['averageEngagement'] ?? 0);
            if ($averageEngagement < 60) {
                $alerts[] = [
                    'level' => 'warning',
                    'message' => 'Engagement moyen faible: ' . round($averageEngagement, 1) . '/100',
                    'action' => 'Améliorer le suivi pédagogique',
                ];
                $this->logger->info('EngagementDashboard: Low engagement warning alert generated', [
                    'average_engagement' => $averageEngagement,
                    'threshold' => 60,
                    'severity' => 'warning',
                ]);
            }

            $atRiskCount = $engagementMetrics['atRiskCount'] ?? 0;
            if ($atRiskCount > 5) {
                $alerts[] = [
                    'level' => 'warning',
                    'message' => "Nombre d'étudiants à risque élevé: " . $atRiskCount,
                    'action' => 'Renforcer le suivi individuel',
                ];
                $this->logger->info('EngagementDashboard: High at-risk count warning alert generated', [
                    'at_risk_count' => $atRiskCount,
                    'threshold' => 5,
                    'severity' => 'warning',
                ]);
            }

            // Info alerts
            if (empty($alerts)) {
                $alerts[] = [
                    'level' => 'info',
                    'message' => 'Tous les indicateurs sont dans les normes Qualiopi',
                    'action' => 'Maintenir la qualité du suivi',
                ];
                $this->logger->info('EngagementDashboard: All metrics within Qualiopi standards', [
                    'dropout_rate' => $dropoutRate,
                    'attendance_rate' => $attendanceRate,
                    'average_engagement' => $averageEngagement,
                    'at_risk_count' => $atRiskCount,
                    'compliance_status' => 'satisfactory',
                ]);
            }

            $this->logger->debug('EngagementDashboard: Alert generation completed', [
                'total_alerts' => count($alerts),
                'critical_alerts' => count(array_filter($alerts, static fn ($alert) => $alert['level'] === 'critical')),
                'warning_alerts' => count(array_filter($alerts, static fn ($alert) => $alert['level'] === 'warning')),
                'info_alerts' => count(array_filter($alerts, static fn ($alert) => $alert['level'] === 'info')),
            ]);

            return $alerts;
        } catch (Exception $e) {
            $this->logger->error('EngagementDashboard: Alert generation failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'fallback_alert' => 'Error alert generated',
            ]);

            // Return a safe fallback alert
            return [[
                'level' => 'critical',
                'message' => 'Erreur lors de la génération des alertes',
                'action' => 'Vérifier les données et réessayer',
            ]];
        }
    }

    /**
     * Calculate Qualiopi compliance score.
     */
    private function calculateComplianceScore(array $report): array
    {
        $this->logger->debug('EngagementDashboard: Starting Qualiopi compliance score calculation', [
            'report_keys' => array_keys($report),
            'total_students' => $report['total_students'] ?? 0,
        ]);

        try {
            $score = 0;
            $maxScore = 100;
            $criteria = [];

            // Criterion 1: Retention rate (25 points)
            $retentionRate = 100 - $report['risk_rate'];
            if ($retentionRate >= 85) {
                $retentionPoints = 25;
            } elseif ($retentionRate >= 75) {
                $retentionPoints = 20;
            } elseif ($retentionRate >= 65) {
                $retentionPoints = 15;
            } else {
                $retentionPoints = 10;
            }
            $score += $retentionPoints;
            $criteria['retention'] = ['score' => $retentionPoints, 'max' => 25, 'rate' => $retentionRate];

            $this->logger->debug('EngagementDashboard: Retention criterion calculated', [
                'retention_rate' => $retentionRate,
                'points_awarded' => $retentionPoints,
                'max_points' => 25,
            ]);

            // Criterion 2: Attendance rate (25 points)
            $attendanceRate = $report['average_attendance'];
            if ($attendanceRate >= 85) {
                $attendancePoints = 25;
            } elseif ($attendanceRate >= 75) {
                $attendancePoints = 20;
            } elseif ($attendanceRate >= 65) {
                $attendancePoints = 15;
            } else {
                $attendancePoints = 10;
            }
            $score += $attendancePoints;
            $criteria['attendance'] = ['score' => $attendancePoints, 'max' => 25, 'rate' => $attendanceRate];

            $this->logger->debug('EngagementDashboard: Attendance criterion calculated', [
                'attendance_rate' => $attendanceRate,
                'points_awarded' => $attendancePoints,
                'max_points' => 25,
            ]);

            // Criterion 3: Engagement monitoring (25 points)
            $engagementRate = $report['avg_engagement'];
            if ($engagementRate >= 70) {
                $engagementPoints = 25;
            } elseif ($engagementRate >= 60) {
                $engagementPoints = 20;
            } elseif ($engagementRate >= 50) {
                $engagementPoints = 15;
            } else {
                $engagementPoints = 10;
            }
            $score += $engagementPoints;
            $criteria['engagement'] = ['score' => $engagementPoints, 'max' => 25, 'rate' => $engagementRate];

            $this->logger->debug('EngagementDashboard: Engagement criterion calculated', [
                'engagement_rate' => $engagementRate,
                'points_awarded' => $engagementPoints,
                'max_points' => 25,
            ]);

            // Criterion 4: Intervention system (25 points)
            $atRiskCount = $report['at_risk_count'];
            $totalStudents = $report['total_students'];
            $interventionRate = $totalStudents > 0 ? (($totalStudents - $atRiskCount) / $totalStudents) * 100 : 100;
            if ($interventionRate >= 90) {
                $interventionPoints = 25;
            } elseif ($interventionRate >= 80) {
                $interventionPoints = 20;
            } elseif ($interventionRate >= 70) {
                $interventionPoints = 15;
            } else {
                $interventionPoints = 10;
            }
            $score += $interventionPoints;
            $criteria['intervention'] = ['score' => $interventionPoints, 'max' => 25, 'rate' => $interventionRate];

            $this->logger->debug('EngagementDashboard: Intervention criterion calculated', [
                'at_risk_count' => $atRiskCount,
                'total_students' => $totalStudents,
                'intervention_rate' => $interventionRate,
                'points_awarded' => $interventionPoints,
                'max_points' => 25,
            ]);

            $percentage = ($score / $maxScore) * 100;
            $level = $this->getComplianceLevel($percentage);

            $this->logger->info('EngagementDashboard: Qualiopi compliance score calculation completed', [
                'total_score' => $score,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'compliance_level' => $level,
                'meets_minimum_standard' => $percentage >= 65,
                'criteria_breakdown' => [
                    'retention' => $retentionPoints,
                    'attendance' => $attendancePoints,
                    'engagement' => $engagementPoints,
                    'intervention' => $interventionPoints,
                ],
            ]);

            if ($percentage < 65) {
                $this->logger->warning('EngagementDashboard: Compliance score below minimum Qualiopi threshold', [
                    'current_percentage' => $percentage,
                    'minimum_required' => 65,
                    'improvement_needed' => 65 - $percentage,
                    'priority_improvements' => array_filter($criteria, static fn ($criterion) => $criterion['score'] < 20),
                ]);
            }

            return [
                'total_score' => $score,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'level' => $level,
                'criteria' => $criteria,
            ];
        } catch (Exception $e) {
            $this->logger->error('EngagementDashboard: Compliance score calculation failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'report_data' => array_keys($report),
            ]);

            // Return a safe fallback score
            return [
                'total_score' => 0,
                'max_score' => 100,
                'percentage' => 0,
                'level' => 'Erreur de calcul',
                'criteria' => [],
            ];
        }
    }

    /**
     * Get compliance level based on score.
     */
    private function getComplianceLevel(float $percentage): string
    {
        if ($percentage >= 85) {
            return 'Excellent';
        }
        if ($percentage >= 75) {
            return 'Satisfaisant';
        }
        if ($percentage >= 65) {
            return 'Acceptable';
        }

        return 'À améliorer';
    }
}
