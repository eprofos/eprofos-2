<?php

declare(strict_types=1);

namespace App\Service\Core;

use App\Entity\Core\StudentProgress;
use App\Entity\Training\Formation;
use App\Entity\User\Student;
use App\Repository\Core\AttendanceRecordRepository;
use App\Repository\Core\StudentProgressRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * DropoutPreventionService - Critical for Qualiopi Criterion 12 compliance.
 *
 * Provides automated risk detection, engagement analysis, and intervention
 * recommendations to prevent student dropouts and ensure training success.
 */
class DropoutPreventionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentProgressRepository $progressRepository,
        private AttendanceRecordRepository $attendanceRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Detect students at risk of dropout (Qualiopi requirement).
     */
    public function detectAtRiskStudents(): array
    {
        $this->logger->info('Starting dropout risk detection analysis');
        $atRiskStudents = [];
        $totalProcessed = 0;
        $errorCount = 0;

        try {
            // Get all active student progress records
            $this->logger->debug('Querying active student progress records');
            $activeProgress = $this->progressRepository->createQueryBuilder('sp')
                ->where('sp.completedAt IS NULL')
                ->getQuery()
                ->getResult()
            ;

            $this->logger->info('Retrieved active progress records', [
                'count' => count($activeProgress),
            ]);

            foreach ($activeProgress as $progress) {
                try {
                    $totalProcessed++;
                    $studentId = $progress->getStudent()?->getId();
                    $formationId = $progress->getFormation()?->getId();

                    $this->logger->debug('Analyzing risk factors for student progress', [
                        'progress_id' => $progress->getId(),
                        'student_id' => $studentId,
                        'formation_id' => $formationId,
                    ]);

                    $riskFactors = $this->analyzeRiskFactors($progress);
                    $riskScore = $this->calculateRiskScore($riskFactors);

                    $this->logger->debug('Risk analysis completed for student', [
                        'student_id' => $studentId,
                        'formation_id' => $formationId,
                        'risk_score' => $riskScore,
                        'risk_factors' => array_keys($riskFactors),
                    ]);

                    // Update the progress record with current risk assessment
                    $progress->setRiskScore(number_format($riskScore, 2));
                    $progress->setAtRiskOfDropout($riskScore >= 40); // 40% threshold
                    $progress->setDifficultySignals(array_keys($riskFactors));
                    $progress->setLastRiskAssessment(new DateTime());

                    if ($riskScore >= 40) {
                        $this->logger->warning('High-risk student detected', [
                            'student_id' => $studentId,
                            'student_name' => $progress->getStudent()?->getFullName() ?? 'Unknown',
                            'formation_id' => $formationId,
                            'formation_title' => $progress->getFormation()?->getTitle() ?? 'Unknown',
                            'risk_score' => $riskScore,
                            'risk_factors' => $riskFactors,
                        ]);

                        $recommendations = $this->generateInterventionRecommendations($riskFactors);
                        
                        $atRiskStudents[] = [
                            'student' => $progress->getStudent(),
                            'formation' => $progress->getFormation(),
                            'progress' => $progress,
                            'riskScore' => $riskScore,
                            'riskFactors' => $riskFactors,
                            'recommendations' => $recommendations,
                        ];

                        $this->logger->info('Generated intervention recommendations', [
                            'student_id' => $studentId,
                            'recommendations_count' => count($recommendations),
                            'recommendations' => $recommendations,
                        ]);
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $this->logger->error('Error analyzing risk factors for student progress', [
                        'progress_id' => $progress->getId() ?? 'unknown',
                        'student_id' => $progress->getStudent()?->getId() ?? 'unknown',
                        'formation_id' => $progress->getFormation()?->getId() ?? 'unknown',
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString(),
                    ]);
                    // Continue processing other students
                    continue;
                }
            }

            // Save all updates
            $this->logger->debug('Persisting risk assessment updates to database');
            $this->entityManager->flush();
            $this->logger->info('Successfully persisted risk assessment updates');

            // Sort by risk score (highest first)
            usort($atRiskStudents, static fn ($a, $b) => $b['riskScore'] <=> $a['riskScore']);
            $this->logger->debug('Sorted at-risk students by risk score');

        } catch (Exception $e) {
            $this->logger->critical('Critical error during dropout risk detection', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'processed_count' => $totalProcessed,
            ]);
            throw $e;
        }

        $this->logger->info('Dropout risk analysis completed successfully', [
            'total_analyzed' => $totalProcessed,
            'at_risk_count' => count($atRiskStudents),
            'error_count' => $errorCount,
            'success_rate' => $totalProcessed > 0 ? (($totalProcessed - $errorCount) / $totalProcessed) * 100 : 100,
        ]);

        return $atRiskStudents;
    }

    /**
     * Calculate overall engagement score for a student.
     */
    public function calculateEngagementScore(Student $student): float
    {
        $this->logger->debug('Calculating engagement score for student', [
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
        ]);

        try {
            $progressRecords = $this->progressRepository->findByStudent($student);

            if (empty($progressRecords)) {
                $this->logger->info('No progress records found for student, returning neutral score', [
                    'student_id' => $student->getId(),
                    'default_score' => 50.0,
                ]);
                return 50.0; // Neutral score for new students
            }

            $this->logger->debug('Found progress records for student', [
                'student_id' => $student->getId(),
                'progress_count' => count($progressRecords),
            ]);

            $totalScore = 0;
            $totalWeight = 0;
            $processedRecords = 0;
            $errorCount = 0;

            foreach ($progressRecords as $progress) {
                try {
                    $processedRecords++;
                    
                    // Update engagement score for this progress
                    $engagementScore = $progress->calculateEngagementScore();

                    // Weight by formation importance (more recent = higher weight)
                    $daysSinceStart = (new DateTime())->diff($progress->getStartedAt())->days;
                    $weight = max(1, 30 - $daysSinceStart); // Newer formations have higher weight

                    $this->logger->debug('Processing progress record for engagement calculation', [
                        'progress_id' => $progress->getId(),
                        'formation_id' => $progress->getFormation()?->getId(),
                        'engagement_score' => $engagementScore,
                        'days_since_start' => $daysSinceStart,
                        'weight' => $weight,
                    ]);

                    $totalScore += $engagementScore * $weight;
                    $totalWeight += $weight;

                } catch (Exception $e) {
                    $errorCount++;
                    $this->logger->error('Error calculating engagement for progress record', [
                        'student_id' => $student->getId(),
                        'progress_id' => $progress->getId() ?? 'unknown',
                        'formation_id' => $progress->getFormation()?->getId() ?? 'unknown',
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);
                    // Continue processing other records
                    continue;
                }
            }

            $finalScore = $totalWeight > 0 ? $totalScore / $totalWeight : 50.0;

            $this->logger->info('Engagement score calculation completed', [
                'student_id' => $student->getId(),
                'final_score' => $finalScore,
                'processed_records' => $processedRecords,
                'error_count' => $errorCount,
                'total_score' => $totalScore,
                'total_weight' => $totalWeight,
            ]);

            return $finalScore;

        } catch (Exception $e) {
            $this->logger->error('Critical error during engagement score calculation', [
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            // Return neutral score on error
            return 50.0;
        }
    }

    /**
     * Trigger intervention alert for at-risk student.
     */
    public function triggerInterventionAlert(Student $student): void
    {
        $this->logger->info('Triggering intervention alert for student', [
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
            'student_email' => $student->getEmail(),
        ]);

        try {
            $progressRecords = $this->progressRepository->findByStudent($student);
            $this->logger->debug('Retrieved progress records for intervention alert', [
                'student_id' => $student->getId(),
                'progress_count' => count($progressRecords),
            ]);

            $overallEngagement = $this->calculateEngagementScore($student);
            $this->logger->debug('Calculated overall engagement score', [
                'student_id' => $student->getId(),
                'engagement_score' => $overallEngagement,
            ]);

            $alertsTriggered = 0;
            $errorCount = 0;

            foreach ($progressRecords as $progress) {
                try {
                    if ($progress->isAtRiskOfDropout()) {
                        $alertsTriggered++;
                        
                        $this->logger->warning('Student intervention alert triggered', [
                            'student_id' => $student->getId(),
                            'student_name' => $student->getFullName(),
                            'student_email' => $student->getEmail(),
                            'formation_id' => $progress->getFormation()?->getId(),
                            'formation_title' => $progress->getFormation()?->getTitle(),
                            'risk_score' => $progress->getRiskScore(),
                            'engagement_score' => $progress->getEngagementScore(),
                            'overall_engagement' => $overallEngagement,
                            'difficulty_signals' => $progress->getDifficultySignals(),
                            'attendance_rate' => $progress->getAttendanceRate(),
                            'completion_percentage' => $progress->getCompletionPercentage(),
                            'missed_sessions' => $progress->getMissedSessions(),
                            'last_activity' => $progress->getLastActivity()?->format('Y-m-d H:i:s'),
                            'started_at' => $progress->getStartedAt()?->format('Y-m-d H:i:s'),
                            'last_risk_assessment' => $progress->getLastRiskAssessment()?->format('Y-m-d H:i:s'),
                        ]);

                        // Log detailed intervention recommendations
                        $riskFactors = $this->analyzeRiskFactors($progress);
                        $recommendations = $this->generateInterventionRecommendations($riskFactors);
                        
                        $this->logger->info('Intervention recommendations generated', [
                            'student_id' => $student->getId(),
                            'formation_id' => $progress->getFormation()?->getId(),
                            'risk_factors' => $riskFactors,
                            'recommendations' => $recommendations,
                            'intervention_priority' => $this->calculateInterventionPriority($riskFactors),
                        ]);

                        // Here you would typically send notifications to instructors/administrators
                        // For now, we'll just log the alert with comprehensive details
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $this->logger->error('Error processing intervention alert for progress record', [
                        'student_id' => $student->getId(),
                        'progress_id' => $progress->getId() ?? 'unknown',
                        'formation_id' => $progress->getFormation()?->getId() ?? 'unknown',
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);
                    // Continue processing other progress records
                    continue;
                }
            }

            $this->logger->info('Intervention alert processing completed', [
                'student_id' => $student->getId(),
                'alerts_triggered' => $alertsTriggered,
                'total_progress_records' => count($progressRecords),
                'error_count' => $errorCount,
            ]);

        } catch (Exception $e) {
            $this->logger->critical('Critical error during intervention alert processing', [
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate retention report for Qualiopi compliance.
     */
    public function generateRetentionReport(): array
    {
        $this->logger->info('Starting retention report generation for Qualiopi compliance');
        
        try {
            $this->logger->debug('Retrieving retention statistics from repository');
            $stats = $this->progressRepository->getRetentionStats();
            $this->logger->debug('Successfully retrieved retention statistics', [
                'stats' => $stats,
            ]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to retrieve retention stats from repository, using fallback calculation', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            
            // Fallback to simple stats if complex query fails
            try {
                $allProgress = $this->progressRepository->findAll();
                $totalStudents = count($allProgress);
                $atRiskCount = count(array_filter($allProgress, static fn ($p) => $p->isAtRiskOfDropout()));
                $completedCount = count(array_filter($allProgress, static fn ($p) => $p->getCompletedAt() !== null));

                $stats = [
                    'totalEnrollments' => $totalStudents,
                    'completionRate' => $totalStudents > 0 ? ($completedCount / $totalStudents) * 100 : 0,
                    'dropoutRate' => $totalStudents > 0 ? ($atRiskCount / $totalStudents) * 100 : 0,
                    'averageAttendance' => 85.0, // Default value
                ];

                $this->logger->info('Fallback statistics calculated', [
                    'total_students' => $totalStudents,
                    'at_risk_count' => $atRiskCount,
                    'completed_count' => $completedCount,
                    'stats' => $stats,
                ]);
            } catch (Exception $fallbackError) {
                $this->logger->error('Error during fallback stats calculation', [
                    'error_message' => $fallbackError->getMessage(),
                    'error_file' => $fallbackError->getFile(),
                    'error_line' => $fallbackError->getLine(),
                ]);
                
                // Use minimal default stats
                $stats = [
                    'totalEnrollments' => 0,
                    'completionRate' => 0,
                    'dropoutRate' => 0,
                    'averageAttendance' => 85.0,
                ];
            }
        }

        try {
            $this->logger->debug('Retrieving attendance statistics');
            $attendanceStats = $this->attendanceRepository->getAttendanceStats();
            $this->logger->debug('Successfully retrieved attendance statistics', [
                'attendance_stats' => $attendanceStats,
            ]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to retrieve attendance stats, using default values', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            $attendanceStats = ['total_records' => 0, 'present_count' => 0];
        }

        try {
            $this->logger->debug('Detecting at-risk students for report');
            $atRiskStudents = $this->detectAtRiskStudents();
            $this->logger->info('At-risk students detection completed for report', [
                'at_risk_count' => count($atRiskStudents),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error detecting at-risk students for report', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            $atRiskStudents = [];
        }

        try {
            $this->logger->debug('Calculating additional report metrics');
            $totalFormations = $this->countFormations();
            $avgEngagement = $this->calculateAverageEngagement();
            
            $report = [
                'total_formations' => $totalFormations,
                'total_students' => $stats['totalEnrollments'] ?? 0,
                'at_risk_count' => count($atRiskStudents),
                'risk_rate' => $stats['dropoutRate'] ?? 0,
                'avg_engagement' => $avgEngagement,
                'completion_rate' => $stats['completionRate'] ?? 0,
                'average_attendance' => $stats['averageAttendance'] ?? 85.0,
                'report_generated_at' => new DateTime(),
                'attendance_stats' => $attendanceStats,
                'at_risk_students' => $atRiskStudents,
            ];

            $this->logger->info('Retention report generated successfully', [
                'report_summary' => [
                    'total_formations' => $report['total_formations'],
                    'total_students' => $report['total_students'],
                    'at_risk_count' => $report['at_risk_count'],
                    'risk_rate' => $report['risk_rate'],
                    'avg_engagement' => $report['avg_engagement'],
                    'completion_rate' => $report['completion_rate'],
                    'average_attendance' => $report['average_attendance'],
                ],
                'generated_at' => $report['report_generated_at']->format('Y-m-d H:i:s'),
            ]);

            return $report;

        } catch (Exception $e) {
            $this->logger->critical('Critical error during retention report generation', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Analyze dropout patterns for continuous improvement.
     */
    public function analyzeDropoutPatterns(): array
    {
        $this->logger->info('Starting dropout patterns analysis for continuous improvement');
        
        try {
            // Get students who dropped out (high risk + inactive for 30+ days)
            $this->logger->debug('Querying dropped out students (high risk + inactive for 30+ days)');
            $droppedOut = $this->progressRepository->createQueryBuilder('sp')
                ->where('sp.atRiskOfDropout = true')
                ->andWhere('sp.lastActivity < :threshold')
                ->setParameter('threshold', new DateTime('-30 days'))
                ->getQuery()
                ->getResult()
            ;

            $this->logger->info('Retrieved dropped out students for analysis', [
                'dropped_out_count' => count($droppedOut),
            ]);

            $patterns = [
                'common_signals' => [],
                'risk_factors' => [],
                'formation_analysis' => [],
                'timing_analysis' => [],
            ];

            $processedCount = 0;
            $errorCount = 0;

            foreach ($droppedOut as $progress) {
                try {
                    $processedCount++;
                    $this->logger->debug('Analyzing dropout pattern for progress record', [
                        'progress_id' => $progress->getId(),
                        'student_id' => $progress->getStudent()?->getId(),
                        'formation_id' => $progress->getFormation()?->getId(),
                    ]);

                    // Analyze common difficulty signals
                    $difficultySignals = $progress->getDifficultySignals();
                    $this->logger->debug('Processing difficulty signals', [
                        'progress_id' => $progress->getId(),
                        'difficulty_signals' => $difficultySignals,
                    ]);

                    foreach ($difficultySignals as $signal) {
                        // Ensure signal is a string that can be used as an array key
                        $signalKey = is_string($signal) ? $signal : (string) $signal;
                        if (!empty($signalKey)) {
                            $patterns['common_signals'][$signalKey] = ($patterns['common_signals'][$signalKey] ?? 0) + 1;
                        }
                    }

                    // Formation-specific analysis
                    $formationId = $progress->getFormation()?->getId();
                    if ($formationId) {
                        $patterns['formation_analysis'][$formationId] = ($patterns['formation_analysis'][$formationId] ?? 0) + 1;
                        $this->logger->debug('Updated formation analysis', [
                            'formation_id' => $formationId,
                            'dropout_count' => $patterns['formation_analysis'][$formationId],
                        ]);
                    }

                    // Timing analysis (when did they start showing risk signals)
                    if ($progress->getStartedAt() && $progress->getLastRiskAssessment()) {
                        $daysBeforeRisk = $progress->getStartedAt()->diff($progress->getLastRiskAssessment())->days;
                        $patterns['timing_analysis'][] = $daysBeforeRisk;
                        $this->logger->debug('Added timing analysis data point', [
                            'progress_id' => $progress->getId(),
                            'days_before_risk' => $daysBeforeRisk,
                        ]);
                    }

                } catch (Exception $e) {
                    $errorCount++;
                    $this->logger->error('Error analyzing dropout pattern for progress record', [
                        'progress_id' => $progress->getId() ?? 'unknown',
                        'student_id' => $progress->getStudent()?->getId() ?? 'unknown',
                        'formation_id' => $progress->getFormation()?->getId() ?? 'unknown',
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);
                    // Continue processing other records
                    continue;
                }
            }

            // Calculate averages and insights
            if (!empty($patterns['timing_analysis'])) {
                $patterns['average_days_before_risk'] = array_sum($patterns['timing_analysis']) / count($patterns['timing_analysis']);
                $this->logger->debug('Calculated average days before risk', [
                    'average_days' => $patterns['average_days_before_risk'],
                    'data_points' => count($patterns['timing_analysis']),
                ]);
            }

            // Sort common signals by frequency
            arsort($patterns['common_signals']);

            $this->logger->info('Dropout patterns analysis completed successfully', [
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'common_signals_count' => count($patterns['common_signals']),
                'formations_analyzed' => count($patterns['formation_analysis']),
                'timing_data_points' => count($patterns['timing_analysis']),
                'most_common_signals' => array_slice($patterns['common_signals'], 0, 5, true),
                'average_days_before_risk' => $patterns['average_days_before_risk'] ?? null,
            ]);

            return $patterns;

        } catch (Exception $e) {
            $this->logger->critical('Critical error during dropout patterns analysis', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Recommend interventions based on risk factors.
     */
    public function recommendInterventions(Student $student): array
    {
        $this->logger->info('Generating intervention recommendations for student', [
            'student_id' => $student->getId(),
            'student_name' => $student->getFullName(),
        ]);

        try {
            $progressRecords = $this->progressRepository->findByStudent($student);
            $this->logger->debug('Retrieved progress records for intervention recommendations', [
                'student_id' => $student->getId(),
                'progress_count' => count($progressRecords),
            ]);

            $recommendations = [];
            $processedCount = 0;
            $errorCount = 0;

            foreach ($progressRecords as $progress) {
                try {
                    $processedCount++;
                    
                    if ($progress->isAtRiskOfDropout()) {
                        $this->logger->debug('Processing at-risk progress record for interventions', [
                            'progress_id' => $progress->getId(),
                            'formation_id' => $progress->getFormation()?->getId(),
                            'formation_title' => $progress->getFormation()?->getTitle(),
                            'risk_score' => $progress->getRiskScore(),
                        ]);

                        $riskFactors = $this->analyzeRiskFactors($progress);
                        $interventions = $this->generateInterventionRecommendations($riskFactors);
                        $priority = $this->calculateInterventionPriority($riskFactors);

                        $this->logger->debug('Generated interventions for progress record', [
                            'progress_id' => $progress->getId(),
                            'risk_factors' => array_keys($riskFactors),
                            'interventions_count' => count($interventions),
                            'priority' => $priority,
                        ]);

                        $recommendations[] = [
                            'formation' => $progress->getFormation(),
                            'progress' => $progress,
                            'interventions' => $interventions,
                            'priority' => $priority,
                        ];

                        $this->logger->info('Intervention recommendation added', [
                            'student_id' => $student->getId(),
                            'formation_id' => $progress->getFormation()?->getId(),
                            'interventions' => $interventions,
                            'priority' => $priority,
                            'risk_factors_detail' => $riskFactors,
                        ]);
                    } else {
                        $this->logger->debug('Skipping progress record - not at risk', [
                            'progress_id' => $progress->getId(),
                            'formation_id' => $progress->getFormation()?->getId(),
                            'risk_score' => $progress->getRiskScore(),
                        ]);
                    }

                } catch (Exception $e) {
                    $errorCount++;
                    $this->logger->error('Error generating interventions for progress record', [
                        'student_id' => $student->getId(),
                        'progress_id' => $progress->getId() ?? 'unknown',
                        'formation_id' => $progress->getFormation()?->getId() ?? 'unknown',
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);
                    // Continue processing other records
                    continue;
                }
            }

            // Sort by priority (highest first)
            usort($recommendations, static fn ($a, $b) => $b['priority'] <=> $a['priority']);

            $this->logger->info('Intervention recommendations completed successfully', [
                'student_id' => $student->getId(),
                'total_recommendations' => count($recommendations),
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'highest_priority' => !empty($recommendations) ? $recommendations[0]['priority'] : 0,
            ]);

            return $recommendations;

        } catch (Exception $e) {
            $this->logger->critical('Critical error during intervention recommendations generation', [
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Export retention data for reports (PDF/Excel/CSV compatible).
     */
    public function exportRetentionData(string $format = 'array'): array
    {
        $this->logger->info('Starting retention data export', [
            'format' => $format,
        ]);

        try {
            $this->logger->debug('Generating retention report for export');
            $report = $this->generateRetentionReport();
            $this->logger->debug('Retention report generated successfully for export');

            // Format data for export
            $exportData = [
                'metadata' => [
                    'report_title' => 'Rapport de Rétention - Critère Qualiopi 12',
                    'generated_at' => $report['report_generated_at']->format('Y-m-d H:i:s'),
                    'period' => 'Derniers 30 jours',
                ],
                'summary_stats' => [
                    'total_formations' => $report['total_formations'],
                    'total_students' => $report['total_students'],
                    'at_risk_count' => $report['at_risk_count'],
                    'risk_rate' => $report['risk_rate'],
                    'avg_engagement' => $report['avg_engagement'],
                    'completion_rate' => $report['completion_rate'],
                    'average_attendance' => $report['average_attendance'],
                ],
                'at_risk_students' => [],
                'compliance_indicators' => [
                    'retention_rate' => 100 - $report['risk_rate'],
                    'engagement_threshold_met' => $report['avg_engagement'] >= 60,
                    'attendance_threshold_met' => $report['average_attendance'] >= 70,
                    'intervention_system_active' => count($report['at_risk_students']) > 0,
                ],
            ];

            $this->logger->debug('Processing at-risk students for export', [
                'at_risk_count' => count($report['at_risk_students']),
            ]);

            $processedStudents = 0;
            $errorCount = 0;

            foreach ($report['at_risk_students'] as $item) {
                try {
                    $processedStudents++;
                    
                    $exportData['at_risk_students'][] = [
                        'student_name' => $item['student']->getFullName(),
                        'student_email' => $item['student']->getEmail(),
                        'formation' => $item['formation']->getTitle(),
                        'risk_score' => $item['riskScore'],
                        'engagement_score' => $item['progress']->getEngagementScore(),
                        'completion_percentage' => $item['progress']->getCompletionPercentage(),
                        'attendance_rate' => $item['progress']->getAttendanceRate(),
                        'last_activity' => $item['progress']->getLastActivity()?->format('Y-m-d H:i:s'),
                        'difficulty_signals' => implode(', ', $item['progress']->getDifficultySignals()),
                        'recommendations' => implode('; ', $item['recommendations']),
                    ];

                } catch (Exception $e) {
                    $errorCount++;
                    $this->logger->error('Error processing at-risk student for export', [
                        'student_id' => $item['student']->getId() ?? 'unknown',
                        'formation_id' => $item['formation']->getId() ?? 'unknown',
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);
                    // Continue processing other students
                    continue;
                }
            }

            $this->logger->info('Retention data export completed successfully', [
                'format' => $format,
                'total_formations' => $exportData['summary_stats']['total_formations'],
                'total_students' => $exportData['summary_stats']['total_students'],
                'at_risk_processed' => $processedStudents,
                'export_errors' => $errorCount,
                'retention_rate' => $exportData['compliance_indicators']['retention_rate'],
                'compliance_indicators' => $exportData['compliance_indicators'],
            ]);

            return $exportData;

        } catch (Exception $e) {
            $this->logger->critical('Critical error during retention data export', [
                'format' => $format,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get all student progress records for analysis.
     */
    public function getAllStudentProgress(): array
    {
        return $this->progressRepository->findAll();
    }

    /**
     * Get attendance statistics for compliance reporting.
     */
    public function getAttendanceStatistics(): array
    {
        $this->logger->info('Retrieving attendance statistics for compliance reporting');
        
        try {
            $qb = $this->attendanceRepository->createQueryBuilder('a')
                ->select('a.status, COUNT(a.id) as count')
                ->groupBy('a.status')
            ;

            $this->logger->debug('Executing attendance statistics query');
            $results = $qb->getQuery()->getResult();
            
            $this->logger->debug('Attendance query results retrieved', [
                'result_count' => count($results),
                'raw_results' => $results,
            ]);

            $total = array_sum(array_column($results, 'count'));
            $statistics = [];

            $this->logger->debug('Processing attendance statistics', [
                'total_records' => $total,
            ]);

            foreach ($results as $result) {
                $count = (int) $result['count'];
                $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                
                $statistics[$result['status']] = [
                    'count' => $count,
                    'percentage' => $percentage,
                ];

                $this->logger->debug('Processed attendance status', [
                    'status' => $result['status'],
                    'count' => $count,
                    'percentage' => $percentage,
                ]);
            }

            $this->logger->info('Attendance statistics retrieved successfully', [
                'total_records' => $total,
                'status_types' => array_keys($statistics),
                'statistics' => $statistics,
            ]);

            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('Error retrieving attendance statistics', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty statistics on error
            return [];
        }
    }

    /**
     * Analyze risk factors for a student progress record.
     */
    private function analyzeRiskFactors(StudentProgress $progress): array
    {
        $this->logger->debug('Starting risk factors analysis for progress record', [
            'progress_id' => $progress->getId(),
            'student_id' => $progress->getStudent()?->getId(),
            'formation_id' => $progress->getFormation()?->getId(),
        ]);

        try {
            $factors = [];

            // Check engagement score
            $engagementScore = $progress->getEngagementScore();
            if ($engagementScore < 40) {
                $factors['low_engagement'] = $engagementScore;
                $this->logger->debug('Low engagement detected', [
                    'progress_id' => $progress->getId(),
                    'engagement_score' => $engagementScore,
                ]);
            }

            // Check activity patterns
            if ($progress->getLastActivity()) {
                $daysSinceActivity = (new DateTime())->diff($progress->getLastActivity())->days;
                if ($daysSinceActivity > 7) {
                    $factors['prolonged_inactivity'] = $daysSinceActivity;
                    $this->logger->debug('Prolonged inactivity detected', [
                        'progress_id' => $progress->getId(),
                        'days_since_activity' => $daysSinceActivity,
                        'last_activity' => $progress->getLastActivity()->format('Y-m-d H:i:s'),
                    ]);
                }
            } else {
                $this->logger->debug('No last activity recorded for progress', [
                    'progress_id' => $progress->getId(),
                ]);
            }

            // Check attendance rate
            $attendanceRate = (float) $progress->getAttendanceRate();
            if ($attendanceRate < 70) {
                $factors['poor_attendance'] = $attendanceRate;
                $this->logger->debug('Poor attendance detected', [
                    'progress_id' => $progress->getId(),
                    'attendance_rate' => $attendanceRate,
                ]);
            }

            // Check progress rate
            $completionRate = (float) $progress->getCompletionPercentage();
            if ($progress->getStartedAt()) {
                $daysSinceStart = (new DateTime())->diff($progress->getStartedAt())->days;
                if ($daysSinceStart > 0) {
                    $expectedProgress = min(100, ($daysSinceStart / 30) * 50); // Rough estimate
                    if ($completionRate < ($expectedProgress * 0.5)) {
                        $factors['slow_progress'] = [
                            'current' => $completionRate,
                            'expected' => $expectedProgress,
                        ];
                        $this->logger->debug('Slow progress detected', [
                            'progress_id' => $progress->getId(),
                            'current_completion' => $completionRate,
                            'expected_completion' => $expectedProgress,
                            'days_since_start' => $daysSinceStart,
                        ]);
                    }
                }
            } else {
                $this->logger->debug('No start date recorded for progress', [
                    'progress_id' => $progress->getId(),
                ]);
            }

            // Check missed sessions
            $missedSessions = $progress->getMissedSessions();
            if ($missedSessions >= 2) {
                $factors['frequent_absences'] = $missedSessions;
                $this->logger->debug('Frequent absences detected', [
                    'progress_id' => $progress->getId(),
                    'missed_sessions' => $missedSessions,
                ]);
            }

            $this->logger->debug('Risk factors analysis completed', [
                'progress_id' => $progress->getId(),
                'factors_count' => count($factors),
                'factors' => array_keys($factors),
                'factors_detail' => $factors,
            ]);

            return $factors;

        } catch (Exception $e) {
            $this->logger->error('Error during risk factors analysis', [
                'progress_id' => $progress->getId() ?? 'unknown',
                'student_id' => $progress->getStudent()?->getId() ?? 'unknown',
                'formation_id' => $progress->getFormation()?->getId() ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            // Return empty factors on error
            return [];
        }
    }

    /**
     * Calculate overall risk score from risk factors.
     */
    private function calculateRiskScore(array $riskFactors): float
    {
        $this->logger->debug('Calculating risk score from factors', [
            'factors_count' => count($riskFactors),
            'factors' => array_keys($riskFactors),
        ]);

        try {
            $score = 0;

            foreach ($riskFactors as $factor => $value) {
                $factorScore = 0;
                
                switch ($factor) {
                    case 'low_engagement':
                        $factorScore = (60 - $value) * 0.5; // Max 30 points
                        $this->logger->debug('Calculated low engagement score', [
                            'factor' => $factor,
                            'value' => $value,
                            'calculated_score' => $factorScore,
                        ]);
                        break;

                    case 'prolonged_inactivity':
                        $factorScore = min(25, $value * 2); // Max 25 points
                        $this->logger->debug('Calculated prolonged inactivity score', [
                            'factor' => $factor,
                            'days' => $value,
                            'calculated_score' => $factorScore,
                        ]);
                        break;

                    case 'poor_attendance':
                        $factorScore = (80 - $value) * 0.3; // Max 24 points for 0% attendance
                        $this->logger->debug('Calculated poor attendance score', [
                            'factor' => $factor,
                            'attendance_rate' => $value,
                            'calculated_score' => $factorScore,
                        ]);
                        break;

                    case 'slow_progress':
                        $expected = $value['expected'] ?? 50;
                        $current = $value['current'] ?? 0;
                        $factorScore = ($expected - $current) * 0.2; // Variable points
                        $this->logger->debug('Calculated slow progress score', [
                            'factor' => $factor,
                            'current_progress' => $current,
                            'expected_progress' => $expected,
                            'calculated_score' => $factorScore,
                        ]);
                        break;

                    case 'frequent_absences':
                        $factorScore = $value * 5; // 5 points per missed session
                        $this->logger->debug('Calculated frequent absences score', [
                            'factor' => $factor,
                            'missed_sessions' => $value,
                            'calculated_score' => $factorScore,
                        ]);
                        break;

                    default:
                        $this->logger->warning('Unknown risk factor encountered', [
                            'factor' => $factor,
                            'value' => $value,
                        ]);
                        break;
                }

                $score += $factorScore;
            }

            $finalScore = min(100, max(0, $score));

            $this->logger->debug('Risk score calculation completed', [
                'total_factors' => count($riskFactors),
                'raw_score' => $score,
                'final_score' => $finalScore,
                'factors_breakdown' => $riskFactors,
            ]);

            return $finalScore;

        } catch (Exception $e) {
            $this->logger->error('Error calculating risk score', [
                'factors' => $riskFactors,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            // Return neutral score on error
            return 50.0;
        }
    }

    /**
     * Generate intervention recommendations based on risk factors.
     */
    private function generateInterventionRecommendations(array $riskFactors): array
    {
        $recommendations = [];

        foreach ($riskFactors as $factor => $value) {
            switch ($factor) {
                case 'low_engagement':
                    $recommendations[] = 'Entretien individuel de motivation';
                    $recommendations[] = 'Révision des objectifs pédagogiques';
                    break;

                case 'prolonged_inactivity':
                    $recommendations[] = 'Relance téléphonique immédiate';
                    $recommendations[] = 'Proposition de séance de rattrapage';
                    break;

                case 'poor_attendance':
                    $recommendations[] = 'Analyse des contraintes personnelles';
                    $recommendations[] = 'Adaptation du planning si possible';
                    break;

                case 'slow_progress':
                    $recommendations[] = 'Soutien pédagogique personnalisé';
                    $recommendations[] = 'Ressources complémentaires';
                    break;

                case 'frequent_absences':
                    $recommendations[] = 'Entretien sur les difficultés rencontrées';
                    $recommendations[] = 'Plan de rattrapage personnalisé';
                    break;
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Calculate intervention priority.
     */
    private function calculateInterventionPriority(array $riskFactors): int
    {
        $priority = 0;

        // Higher priority for certain factors
        if (isset($riskFactors['prolonged_inactivity'])) {
            $priority += 10;
        }
        if (isset($riskFactors['frequent_absences'])) {
            $priority += 8;
        }
        if (isset($riskFactors['poor_attendance'])) {
            $priority += 6;
        }
        if (isset($riskFactors['low_engagement'])) {
            $priority += 4;
        }
        if (isset($riskFactors['slow_progress'])) {
            $priority += 2;
        }

        return $priority;
    }

    /**
     * Generate system-level recommendations.
     */
    private function generateSystemRecommendations(array $stats, array $atRiskStudents): array
    {
        $recommendations = [];

        if ($stats['dropoutRate'] > 15) {
            $recommendations[] = 'Taux d\'abandon élevé - Revoir la stratégie d\'engagement global';
        }

        if ($stats['averageAttendance'] < 75) {
            $recommendations[] = 'Assiduité insuffisante - Améliorer la flexibilité des horaires';
        }

        if (count($atRiskStudents) > 10) {
            $recommendations[] = 'Nombre élevé d\'étudiants à risque - Renforcer le suivi préventif';
        }

        return $recommendations;
    }

    /**
     * Count total formations for reporting.
     */
    private function countFormations(): int
    {
        $this->logger->debug('Counting total formations for reporting');
        
        try {
            $count = $this->progressRepository->createQueryBuilder('sp')
                ->select('COUNT(DISTINCT sp.formation)')
                ->getQuery()
                ->getSingleScalarResult()
            ;

            $this->logger->debug('Formation count retrieved successfully', [
                'total_formations' => $count,
            ]);

            return (int) $count;

        } catch (Exception $e) {
            $this->logger->error('Error counting formations', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            // Return 0 on error
            return 0;
        }
    }

    /**
     * Calculate average engagement score across all students.
     */
    private function calculateAverageEngagement(): float
    {
        $this->logger->debug('Calculating average engagement score across all students');
        
        try {
            $result = $this->progressRepository->createQueryBuilder('sp')
                ->select('AVG(sp.engagementScore)')
                ->getQuery()
                ->getSingleScalarResult()
            ;

            $avgEngagement = $result ? (float) $result : 0.0;

            $this->logger->debug('Average engagement calculated successfully', [
                'average_engagement' => $avgEngagement,
            ]);

            return $avgEngagement;

        } catch (Exception $e) {
            $this->logger->error('Error calculating average engagement', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            // Return 0.0 on error
            return 0.0;
        }
    }
}
