<?php

declare(strict_types=1);

namespace App\Controller\Admin\Core;

use App\Repository\Core\AttendanceRecordRepository;
use App\Repository\Core\StudentProgressRepository;
use App\Service\Core\DropoutPreventionService;
use DateTime;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    ) {}

    /**
     * Main engagement dashboard.
     */
    #[Route('/', name: 'admin_engagement_dashboard')]
    public function dashboard(): Response
    {
        // Get key metrics for dashboard
        $engagementMetrics = $this->progressRepository->getEngagementMetrics();
        $attendanceStats = $this->attendanceRepository->getAttendanceStats();
        $retentionStats = $this->progressRepository->getRetentionStats();
        $atRiskStudents = $this->progressRepository->findAtRiskStudents();
        $lowEngagement = $this->progressRepository->findLowEngagementStudents();
        $poorAttendance = $this->attendanceRepository->findStudentsWithPoorAttendance();

        return $this->render('admin/engagement/dashboard.html.twig', [
            'engagement_metrics' => $engagementMetrics,
            'attendance_stats' => $attendanceStats,
            'retention_stats' => $retentionStats,
            'at_risk_count' => count($atRiskStudents),
            'low_engagement_count' => count($lowEngagement),
            'poor_attendance_count' => count($poorAttendance),
            'alerts' => $this->generateAlerts($engagementMetrics, $attendanceStats, $retentionStats),
        ]);
    }

    /**
     * Detailed view of at-risk students.
     */
    #[Route('/at-risk', name: 'admin_engagement_at_risk')]
    public function atRiskStudents(): Response
    {
        $atRiskStudents = $this->dropoutService->detectAtRiskStudents();
        $criticalRisk = $this->progressRepository->findCriticalRiskStudents();

        return $this->render('admin/engagement/at_risk.html.twig', [
            'at_risk_students' => $atRiskStudents,
            'critical_risk_students' => $criticalRisk,
            'total_count' => count($atRiskStudents),
        ]);
    }

    /**
     * Attendance monitoring interface.
     */
    #[Route('/attendance', name: 'admin_engagement_attendance')]
    public function attendance(): Response
    {
        $attendanceStats = $this->attendanceRepository->getAttendanceStats();
        $poorAttendance = $this->attendanceRepository->findStudentsWithPoorAttendance();
        $frequentAbsentees = $this->attendanceRepository->findFrequentAbsentees();
        $recentAbsentees = $this->attendanceRepository->findRecentAbsentees();

        return $this->render('admin/engagement/attendance.html.twig', [
            'attendance_stats' => $attendanceStats,
            'poor_attendance_students' => $poorAttendance,
            'frequent_absentees' => $frequentAbsentees,
            'recent_absentees' => $recentAbsentees,
        ]);
    }

    /**
     * Generate Qualiopi compliance report.
     */
    #[Route('/qualiopi-report', name: 'admin_engagement_quality_report')]
    public function qualiopiReport(): Response
    {
        $retentionReport = $this->dropoutService->generateRetentionReport();
        $dropoutPatterns = $this->dropoutService->analyzeDropoutPatterns();

        return $this->render('admin/engagement/qualiopi_report.html.twig', [
            'report' => $retentionReport,
            'dropout_patterns' => $dropoutPatterns,
            'compliance_score' => $this->calculateComplianceScore($retentionReport),
        ]);
    }

    /**
     * Export retention data (PDF/Excel/CSV).
     */
    #[Route('/export/{format}', name: 'admin_engagement_export', requirements: ['format' => 'pdf|excel|csv'])]
    public function exportData(string $format): Response
    {
        $data = $this->dropoutService->exportRetentionData($format);
        $filename = 'rapport_engagement_' . date('Y-m-d_H-i-s');

        switch ($format) {
            case 'pdf':
                // Generate PDF using KnpSnappyBundle
                $html = $this->renderView('admin/engagement/export_pdf.html.twig', [
                    'data' => $data,
                    'generated_at' => new DateTime(),
                    'title' => 'Rapport d\'Engagement et de Rétention - Critère Qualiopi 12',
                ]);

                return new PdfResponse(
                    $this->knpSnappyPdf->getOutputFromHtml($html, [
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
                    ]),
                    $filename . '.pdf',
                    'application/pdf',
                    'attachment',
                );

            case 'excel':
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
                foreach ($data['at_risk_students'] as $student) {
                    $sheet->setCellValue('A' . $row, $student['student_name']);
                    $sheet->setCellValue('B' . $row, $student['student_email']);
                    $sheet->setCellValue('C' . $row, $student['formation']);
                    $sheet->setCellValue('D' . $row, $student['risk_score']);
                    $sheet->setCellValue('E' . $row, $student['engagement_score']);
                    $sheet->setCellValue('F' . $row, round($student['completion_percentage'], 1));
                    $sheet->setCellValue('G' . $row, round($student['attendance_rate'], 1));
                    $sheet->setCellValue('H' . $row, $student['last_activity']);
                    $sheet->setCellValue('I' . $row, $student['difficulty_signals']);
                    $sheet->setCellValue('J' . $row, $student['recommendations']);
                    $row++;
                }

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

                return $response;

            case 'csv':
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
                foreach ($data['at_risk_students'] as $student) {
                    fputcsv($handle, [
                        $student['student_name'],
                        $student['student_email'],
                        $student['formation'],
                        $student['risk_score'],
                        $student['engagement_score'],
                        round($student['completion_percentage'], 1),
                        round($student['attendance_rate'], 1),
                        $student['last_activity'],
                        $student['difficulty_signals'],
                        $student['recommendations'],
                    ]);
                }

                rewind($handle);
                $response->setContent(stream_get_contents($handle));
                fclose($handle);

                return $response;
        }

        return new JsonResponse(['error' => 'Format not supported'], 400);
    }

    /**
     * AJAX endpoint for engagement trends.
     */
    #[Route('/api/trends', name: 'admin_engagement_api_trends')]
    public function engagementTrends(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 30);

        $activityTrends = $this->progressRepository->getActivityTrends($days);
        $attendanceTrends = $this->attendanceRepository->getAttendanceTrends($days);

        return new JsonResponse([
            'activity_trends' => $activityTrends,
            'attendance_trends' => $attendanceTrends,
        ]);
    }

    /**
     * AJAX endpoint for student risk assessment.
     */
    #[Route('/api/student/{id}/risk', name: 'admin_engagement_api_student_risk')]
    public function studentRiskAssessment(int $id): JsonResponse
    {
        $student = $this->progressRepository->find($id);

        if (!$student) {
            return new JsonResponse(['error' => 'Student not found'], 404);
        }

        $recommendations = $this->dropoutService->recommendInterventions($student->getStudent());

        return new JsonResponse([
            'student' => [
                'id' => $student->getStudent()->getId(),
                'name' => $student->getStudent()->getFullName(),
                'engagement_score' => $student->getEngagementScore(),
                'risk_score' => $student->getRiskScore(),
                'at_risk' => $student->isAtRiskOfDropout(),
            ],
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * Trigger risk analysis for all students.
     */
    #[Route('/api/analyze-risks', name: 'admin_engagement_api_analyze_risks', methods: ['POST'])]
    public function analyzeRisks(): JsonResponse
    {
        $atRiskStudents = $this->dropoutService->detectAtRiskStudents();

        return new JsonResponse([
            'message' => 'Risk analysis completed',
            'at_risk_count' => count($atRiskStudents),
            'analyzed_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Generate alerts for dashboard.
     */
    private function generateAlerts(array $engagementMetrics, array $attendanceStats, array $retentionStats): array
    {
        $alerts = [];

        // Critical alerts (require immediate action)
        if (($retentionStats['dropoutRate'] ?? 0) > 20) {
            $alerts[] = [
                'level' => 'critical',
                'message' => "Taux d'abandon critique: " . round($retentionStats['dropoutRate'], 1) . '%',
                'action' => 'Intervention immédiate requise',
            ];
        }

        if (($attendanceStats['overall_attendance_rate'] ?? 0) < 70) {
            $alerts[] = [
                'level' => 'critical',
                'message' => "Taux d'assiduité global insuffisant: " . round($attendanceStats['overall_attendance_rate'], 1) . '%',
                'action' => 'Revoir la stratégie d\'engagement',
            ];
        }

        // Warning alerts
        if (($engagementMetrics['averageEngagement'] ?? 0) < 60) {
            $alerts[] = [
                'level' => 'warning',
                'message' => 'Engagement moyen faible: ' . round($engagementMetrics['averageEngagement'], 1) . '/100',
                'action' => 'Améliorer le suivi pédagogique',
            ];
        }

        if (($engagementMetrics['atRiskCount'] ?? 0) > 5) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "Nombre d'étudiants à risque élevé: " . $engagementMetrics['atRiskCount'],
                'action' => 'Renforcer le suivi individuel',
            ];
        }

        // Info alerts
        if (empty($alerts)) {
            $alerts[] = [
                'level' => 'info',
                'message' => 'Tous les indicateurs sont dans les normes Qualiopi',
                'action' => 'Maintenir la qualité du suivi',
            ];
        }

        return $alerts;
    }

    /**
     * Calculate Qualiopi compliance score.
     */
    private function calculateComplianceScore(array $report): array
    {
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

        $percentage = ($score / $maxScore) * 100;

        return [
            'total_score' => $score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'level' => $this->getComplianceLevel($percentage),
            'criteria' => $criteria,
        ];
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
