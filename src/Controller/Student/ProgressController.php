<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Training\Course;
use App\Entity\Training\Exercise;
use App\Entity\Training\QCM;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Training\CourseRepository;
use App\Repository\Training\ExerciseRepository;
use App\Repository\Training\FormationRepository;
use App\Repository\Training\QCMRepository;
use App\Service\Student\ProgressTrackingService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student Progress Tracking Controller
 *
 * Handles progress tracking endpoints for real-time progress updates
 * and analytics as specified in Issue #67.
 */
#[Route('/student/progress')]
#[IsGranted('ROLE_STUDENT')]
class ProgressController extends AbstractController
{
    public function __construct(
        private readonly ProgressTrackingService $progressService,
        private readonly StudentEnrollmentRepository $enrollmentRepository,
        private readonly FormationRepository $formationRepository,
        private readonly CourseRepository $courseRepository,
        private readonly ExerciseRepository $exerciseRepository,
        private readonly QCMRepository $qcmRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Update content progress (course view, completion, time spent).
     */
    #[Route('/update', name: 'student_progress_update', methods: ['POST'])]
    public function updateProgress(Request $request): JsonResponse
    {
        $requestId = uniqid('progress_update_', true);
        
        try {
            /** @var Student $student */
            $student = $this->getUser();
            
            $this->logger->info('Progress update request initiated', [
                'request_id' => $requestId,
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => new \DateTime()
            ]);
            
            // Parse and validate request data
            $rawContent = $request->getContent();
            $this->logger->debug('Raw request content received', [
                'request_id' => $requestId,
                'content_length' => strlen($rawContent),
                'raw_content' => $rawContent
            ]);
            
            $data = json_decode($rawContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('JSON decode error in progress update', [
                    'request_id' => $requestId,
                    'json_error' => json_last_error_msg(),
                    'raw_content' => $rawContent
                ]);
                return new JsonResponse(['error' => 'Invalid JSON format'], 400);
            }
            
            $this->logger->debug('Request data parsed successfully', [
                'request_id' => $requestId,
                'parsed_data' => $data
            ]);
            
            // Validate required fields
            if (!isset($data['contentId'], $data['contentType'], $data['action'])) {
                $this->logger->warning('Missing required fields in progress update', [
                    'request_id' => $requestId,
                    'received_fields' => array_keys($data),
                    'required_fields' => ['contentId', 'contentType', 'action']
                ]);
                return new JsonResponse(['error' => 'Missing required fields'], 400);
            }

            $contentId = (int) $data['contentId'];
            $contentType = $data['contentType'];
            $action = $data['action'];

            $this->logger->info('Progress update parameters validated', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'content_type' => $contentType,
                'action' => $action,
                'time_spent' => $data['timeSpent'] ?? null
            ]);

            // Find the student's enrollment for this content
            $enrollment = $this->findEnrollmentForContent($student, $contentId, $contentType);
            if (!$enrollment) {
                $this->logger->warning('Enrollment not found for progress update', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'content_id' => $contentId,
                    'content_type' => $contentType
                ]);
                return new JsonResponse(['error' => 'Enrollment not found'], 404);
            }

            $this->logger->info('Enrollment found for progress update', [
                'request_id' => $requestId,
                'enrollment_id' => $enrollment->getId(),
                'formation_id' => $enrollment->getFormation()?->getId(),
                'formation_title' => $enrollment->getFormation()?->getTitle()
            ]);

            // Process based on content type
            switch ($contentType) {
                case 'course':
                    $this->handleCourseProgress($requestId, $enrollment, $contentId, $action, $data);
                    break;

                case 'exercise':
                    $this->handleExerciseProgress($requestId, $enrollment, $contentId, $action, $data);
                    break;

                case 'qcm':
                    $this->handleQCMProgress($requestId, $enrollment, $contentId, $action, $data);
                    break;

                default:
                    $this->logger->error('Unknown content type in progress update', [
                        'request_id' => $requestId,
                        'content_type' => $contentType,
                        'supported_types' => ['course', 'exercise', 'qcm']
                    ]);
                    return new JsonResponse(['error' => 'Unknown content type'], 400);
            }

            // Track time spent if provided
            if (isset($data['timeSpent'])) {
                $this->handleTimeSpentUpdate($requestId, $enrollment, $contentId, $contentType, (int) $data['timeSpent']);
            }

            // Calculate updated overall progress
            $this->logger->debug('Calculating overall progress', [
                'request_id' => $requestId,
                'enrollment_id' => $enrollment->getId()
            ]);
            
            $overallProgress = $this->progressService->calculateOverallProgress($enrollment);
            
            $this->logger->info('Progress update completed successfully', [
                'request_id' => $requestId,
                'student_id' => $student->getId(),
                'content_id' => $contentId,
                'content_type' => $contentType,
                'action' => $action,
                'overall_progress' => $overallProgress,
                'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ]);

            return new JsonResponse([
                'success' => true,
                'overall_progress' => $overallProgress,
                'message' => 'Progress updated successfully'
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in progress update', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $data ?? null
            ]);
            
            return new JsonResponse([
                'error' => 'Invalid request parameters: ' . $e->getMessage()
            ], 400);
            
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->critical('Database error in progress update', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'error' => 'Database error occurred. Please try again later.'
            ], 500);
            
        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in progress update', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $data ?? null,
                'student_id' => $student->getId() ?? 'unknown'
            ]);
            
            return new JsonResponse([
                'error' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get detailed progress statistics for a student.
     */
    #[Route('/stats', name: 'student_progress_stats', methods: ['GET'])]
    public function getProgressStats(Request $request): JsonResponse
    {
        $requestId = uniqid('progress_stats_', true);
        
        try {
            /** @var Student $student */
            $student = $this->getUser();
            
            $this->logger->info('Progress stats request initiated', [
                'request_id' => $requestId,
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'ip_address' => $request->getClientIp(),
                'timestamp' => new \DateTime()
            ]);
            
            $formationId = $request->query->get('formation');
            if (!$formationId) {
                $this->logger->warning('Formation ID missing in progress stats request', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'query_params' => $request->query->all()
                ]);
                return new JsonResponse(['error' => 'Formation ID required'], 400);
            }

            $this->logger->debug('Looking up enrollment for progress stats', [
                'request_id' => $requestId,
                'formation_id' => $formationId,
                'student_id' => $student->getId()
            ]);

            // Find the formation first
            $formation = $this->formationRepository->find($formationId);
            if (!$formation) {
                $this->logger->warning('Formation not found for progress stats', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'formation_id' => $formationId
                ]);
                return new JsonResponse(['error' => 'Formation not found'], 404);
            }

            // Find enrollment using the proper repository method
            $enrollment = $this->enrollmentRepository->findEnrollmentByStudentAndFormation($student, $formation);

            if (!$enrollment) {
                $this->logger->warning('Enrollment not found for progress stats', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'formation_id' => $formationId
                ]);
                return new JsonResponse(['error' => 'Enrollment not found'], 404);
            }

            $this->logger->info('Enrollment found, generating progress report', [
                'request_id' => $requestId,
                'enrollment_id' => $enrollment->getId(),
                'formation_title' => $enrollment->getFormation()?->getTitle()
            ]);

            $report = $this->progressService->generateProgressReport($enrollment);
            
            $this->logger->debug('Progress report generated', [
                'request_id' => $requestId,
                'report_keys' => array_keys($report),
                'completion_percentage' => $report['progress']['completion_percentage'] ?? 'N/A'
            ]);
            
            // Format the response for the frontend
            $stats = [
                'completionRate' => $report['progress']['completion_percentage'] ?? 0,
                'timeSpentMinutes' => $report['progress']['total_time_spent'] ?? 0,
                'engagementScore' => $report['progress']['engagement_score'] ?? 0,
                'lastActivity' => $report['progress']['last_activity'] ?? null,
                'completedModules' => 0,
                'totalModules' => 0,
                'completedExercises' => $report['content_progress']['exercises']['submitted'] ?? 0,
                'totalExercises' => $report['content_progress']['exercises']['total'] ?? 0,
                'averageQcmScore' => $report['content_progress']['qcms']['average_score'] ?? 0,
                'moduleProgress' => []
            ];

            // Get module progress details
            if ($enrollment->getFormation()) {
                $formation = $enrollment->getFormation();
                $this->logger->debug('Processing module progress', [
                    'request_id' => $requestId,
                    'formation_id' => $formation->getId(),
                    'modules_count' => $formation->getModules()->count()
                ]);
                
                foreach ($formation->getModules() as $module) {
                    $stats['totalModules']++;
                    $moduleProgress = $enrollment->getProgress()?->getModuleProgress() ?? [];
                    $moduleData = $moduleProgress[$module->getId()] ?? ['percentage' => 0, 'completed' => false];
                    
                    if ($moduleData['completed']) {
                        $stats['completedModules']++;
                    }
                    
                    $stats['moduleProgress'][] = [
                        'title' => $module->getTitle(),
                        'progress' => $moduleData['percentage'] ?? 0
                    ];
                    
                    $this->logger->debug('Module progress processed', [
                        'request_id' => $requestId,
                        'module_id' => $module->getId(),
                        'module_title' => $module->getTitle(),
                        'progress_percentage' => $moduleData['percentage'] ?? 0,
                        'completed' => $moduleData['completed'] ?? false
                    ]);
                }
            }

            $this->logger->info('Progress stats generated successfully', [
                'request_id' => $requestId,
                'student_id' => $student->getId(),
                'formation_id' => $formationId,
                'completion_rate' => $stats['completionRate'],
                'total_modules' => $stats['totalModules'],
                'completed_modules' => $stats['completedModules'],
                'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ]);

            return new JsonResponse($stats);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->critical('Database error in progress stats', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString(),
                'formation_id' => $formationId ?? 'unknown'
            ]);
            
            return new JsonResponse([
                'error' => 'Database error occurred while fetching progress stats.'
            ], 500);
            
        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in progress stats', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString(),
                'formation_id' => $formationId ?? 'unknown',
                'student_id' => $student->getId() ?? 'unknown'
            ]);
            
            return new JsonResponse([
                'error' => 'An unexpected error occurred while fetching progress stats.'
            ], 500);
        }
    }

    /**
     * Get milestone achievements for a student.
     */
    #[Route('/milestones', name: 'student_progress_milestones', methods: ['GET'])]
    public function getMilestones(Request $request): JsonResponse
    {
        $requestId = uniqid('milestones_', true);
        
        try {
            /** @var Student $student */
            $student = $this->getUser();
            
            $this->logger->info('Milestones request initiated', [
                'request_id' => $requestId,
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'ip_address' => $request->getClientIp(),
                'timestamp' => new \DateTime()
            ]);
            
            $formationId = $request->query->get('formation');
            if (!$formationId) {
                $this->logger->warning('Formation ID missing in milestones request', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'query_params' => $request->query->all()
                ]);
                return new JsonResponse(['error' => 'Formation ID required'], 400);
            }

            $this->logger->debug('Looking up enrollment for milestones', [
                'request_id' => $requestId,
                'formation_id' => $formationId,
                'student_id' => $student->getId()
            ]);

            // Find the formation first
            $formation = $this->formationRepository->find($formationId);
            if (!$formation) {
                $this->logger->warning('Formation not found for milestones', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'formation_id' => $formationId
                ]);
                return new JsonResponse(['error' => 'Formation not found'], 404);
            }

            // Find enrollment using the proper repository method
            $enrollment = $this->enrollmentRepository->findEnrollmentByStudentAndFormation($student, $formation);

            if (!$enrollment) {
                $this->logger->warning('Enrollment not found for milestones', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'formation_id' => $formationId
                ]);
                return new JsonResponse(['error' => 'Enrollment not found'], 404);
            }

            $this->logger->info('Enrollment found, retrieving milestones', [
                'request_id' => $requestId,
                'enrollment_id' => $enrollment->getId(),
                'formation_title' => $enrollment->getFormation()?->getTitle()
            ]);

            $progress = $enrollment->getProgress();
            if (!$progress) {
                $this->logger->info('No progress data found for milestones', [
                    'request_id' => $requestId,
                    'enrollment_id' => $enrollment->getId()
                ]);
                return new JsonResponse(['milestones' => []]);
            }

            $milestones = $progress->getMilestones();
            
            $this->logger->debug('Raw milestones retrieved', [
                'request_id' => $requestId,
                'milestones_count' => is_array($milestones) ? count($milestones) : 0,
                'milestones_keys' => is_array($milestones) ? array_keys($milestones) : []
            ]);
            
            // Format milestones for display
            $formattedMilestones = [];
            foreach ($milestones as $key => $milestone) {
                if (is_array($milestone)) {
                    $formattedMilestone = [
                        'key' => $key,
                        'title' => $milestone['title'] ?? $key,
                        'description' => $milestone['description'] ?? '',
                        'points' => $milestone['points'] ?? 0,
                        'achieved_at' => $milestone['achieved_at'] ?? null
                    ];
                    
                    $formattedMilestones[] = $formattedMilestone;
                    
                    $this->logger->debug('Milestone formatted', [
                        'request_id' => $requestId,
                        'milestone_key' => $key,
                        'milestone_title' => $formattedMilestone['title'],
                        'milestone_points' => $formattedMilestone['points'],
                        'achieved_at' => $formattedMilestone['achieved_at']
                    ]);
                } else {
                    $this->logger->warning('Invalid milestone format encountered', [
                        'request_id' => $requestId,
                        'milestone_key' => $key,
                        'milestone_type' => gettype($milestone),
                        'milestone_value' => $milestone
                    ]);
                }
            }

            $this->logger->info('Milestones retrieved successfully', [
                'request_id' => $requestId,
                'student_id' => $student->getId(),
                'formation_id' => $formationId,
                'total_milestones' => count($formattedMilestones),
                'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ]);

            return new JsonResponse(['milestones' => $formattedMilestones]);

        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->critical('Database error in milestones retrieval', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString(),
                'formation_id' => $formationId ?? 'unknown'
            ]);
            
            return new JsonResponse([
                'error' => 'Database error occurred while fetching milestones.'
            ], 500);
            
        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in milestones retrieval', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString(),
                'formation_id' => $formationId ?? 'unknown',
                'student_id' => $student->getId() ?? 'unknown'
            ]);
            
            return new JsonResponse([
                'error' => 'An unexpected error occurred while fetching milestones.'
            ], 500);
        }
    }

    /**
     * Export progress report as PDF.
     */
    #[Route('/export', name: 'student_progress_export', methods: ['GET'])]
    public function exportProgress(Request $request): Response
    {
        $requestId = uniqid('export_', true);
        
        try {
            /** @var Student $student */
            $student = $this->getUser();
            
            $formationId = $request->query->get('formation');
            $format = $request->query->get('format', 'pdf');
            
            $this->logger->info('Progress export request initiated', [
                'request_id' => $requestId,
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'formation_id' => $formationId,
                'format' => $format,
                'ip_address' => $request->getClientIp(),
                'timestamp' => new \DateTime()
            ]);
            
            if (!$formationId) {
                $this->logger->warning('Formation ID missing in export request', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'query_params' => $request->query->all()
                ]);
                throw $this->createNotFoundException('Formation ID required');
            }

            $this->logger->debug('Looking up enrollment for export', [
                'request_id' => $requestId,
                'formation_id' => $formationId,
                'student_id' => $student->getId()
            ]);

            // Find the formation first
            $formation = $this->formationRepository->find($formationId);
            if (!$formation) {
                $this->logger->warning('Formation not found for export', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'formation_id' => $formationId
                ]);
                throw $this->createNotFoundException('Formation not found');
            }

            // Find enrollment using the proper repository method
            $enrollment = $this->enrollmentRepository->findEnrollmentByStudentAndFormation($student, $formation);

            if (!$enrollment) {
                $this->logger->warning('Enrollment not found for export', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'formation_id' => $formationId
                ]);
                throw $this->createNotFoundException('Enrollment not found');
            }

            $this->logger->info('Enrollment found, generating export report', [
                'request_id' => $requestId,
                'enrollment_id' => $enrollment->getId(),
                'formation_title' => $enrollment->getFormation()?->getTitle(),
                'export_format' => $format
            ]);

            $report = $this->progressService->generateProgressReport($enrollment);
            
            $this->logger->debug('Export report generated', [
                'request_id' => $requestId,
                'report_keys' => array_keys($report),
                'completion_percentage' => $report['progress']['completion_percentage'] ?? 'N/A'
            ]);

            if ($format === 'pdf') {
                $this->logger->info('Rendering PDF export', [
                    'request_id' => $requestId,
                    'template' => 'student/progress/export_pdf.html.twig'
                ]);
                
                $response = $this->render('student/progress/export_pdf.html.twig', [
                    'report' => $report,
                    'enrollment' => $enrollment
                ]);
                
                $this->logger->info('PDF export rendered successfully', [
                    'request_id' => $requestId,
                    'student_id' => $student->getId(),
                    'formation_id' => $formationId,
                    'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
                ]);
                
                return $response;
            }

            // For now, return JSON format
            $this->logger->info('JSON export returned', [
                'request_id' => $requestId,
                'student_id' => $student->getId(),
                'formation_id' => $formationId,
                'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ]);

            return new JsonResponse($report);

        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            $this->logger->error('Not found error in progress export', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'formation_id' => $formationId ?? 'unknown',
                'student_id' => $student->getId() ?? 'unknown'
            ]);
            throw $e; // Re-throw to maintain 404 response
            
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->critical('Database error in progress export', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString(),
                'formation_id' => $formationId ?? 'unknown'
            ]);
            
            throw new \RuntimeException('Database error occurred while generating export.');
            
        } catch (\Twig\Error\Error $e) {
            $this->logger->error('Template error in progress export', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'formation_id' => $formationId ?? 'unknown'
            ]);
            
            throw new \RuntimeException('Template error occurred while generating export.');
            
        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error in progress export', [
                'request_id' => $requestId ?? uniqid('error_', true),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString(),
                'formation_id' => $formationId ?? 'unknown',
                'student_id' => $student->getId() ?? 'unknown'
            ]);
            
            throw new \RuntimeException('An unexpected error occurred while generating export.');
        }
    }

    /**
     * Handle course progress update with detailed logging.
     */
    private function handleCourseProgress(string $requestId, StudentEnrollment $enrollment, int $contentId, string $action, array $data): void
    {
        try {
            $this->logger->debug('Processing course progress update', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'action' => $action,
                'enrollment_id' => $enrollment->getId()
            ]);

            $course = $this->courseRepository->find($contentId);
            if (!$course) {
                $this->logger->error('Course not found for progress update', [
                    'request_id' => $requestId,
                    'course_id' => $contentId
                ]);
                throw new \InvalidArgumentException('Course not found');
            }

            $this->logger->debug('Course found for progress update', [
                'request_id' => $requestId,
                'course_id' => $course->getId(),
                'course_title' => $course->getTitle(),
                'chapter_id' => $course->getChapter()?->getId(),
                'module_id' => $course->getChapter()?->getModule()?->getId()
            ]);

            if ($action === 'view_completed') {
                $this->logger->info('Recording course completion', [
                    'request_id' => $requestId,
                    'course_id' => $course->getId(),
                    'student_id' => $enrollment->getStudent()->getId()
                ]);
                $this->progressService->recordCourseCompletion($enrollment, $course);
            } else {
                $this->logger->info('Recording course view', [
                    'request_id' => $requestId,
                    'course_id' => $course->getId(),
                    'student_id' => $enrollment->getStudent()->getId()
                ]);
                $this->progressService->recordCourseView($enrollment, $course);
            }

            $this->logger->debug('Course progress update completed', [
                'request_id' => $requestId,
                'course_id' => $course->getId(),
                'action' => $action
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in course progress handling', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'action' => $action,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle exercise progress update with detailed logging.
     */
    private function handleExerciseProgress(string $requestId, StudentEnrollment $enrollment, int $contentId, string $action, array $data): void
    {
        try {
            if ($action !== 'submitted') {
                $this->logger->debug('Ignoring non-submission exercise action', [
                    'request_id' => $requestId,
                    'content_id' => $contentId,
                    'action' => $action
                ]);
                return;
            }

            $this->logger->debug('Processing exercise submission', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'enrollment_id' => $enrollment->getId()
            ]);

            $exercise = $this->exerciseRepository->find($contentId);
            if (!$exercise) {
                $this->logger->error('Exercise not found for progress update', [
                    'request_id' => $requestId,
                    'exercise_id' => $contentId
                ]);
                throw new \InvalidArgumentException('Exercise not found');
            }

            $submission = $data['submission'] ?? [];
            
            $this->logger->info('Recording exercise submission', [
                'request_id' => $requestId,
                'exercise_id' => $exercise->getId(),
                'exercise_title' => $exercise->getTitle(),
                'student_id' => $enrollment->getStudent()->getId(),
                'submission_data_present' => !empty($submission)
            ]);

            $this->progressService->recordExerciseSubmission($enrollment, $exercise, $submission);

            $this->logger->debug('Exercise submission recorded successfully', [
                'request_id' => $requestId,
                'exercise_id' => $exercise->getId()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in exercise progress handling', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'action' => $action,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle QCM progress update with detailed logging.
     */
    private function handleQCMProgress(string $requestId, StudentEnrollment $enrollment, int $contentId, string $action, array $data): void
    {
        try {
            if ($action !== 'attempted') {
                $this->logger->debug('Ignoring non-attempt QCM action', [
                    'request_id' => $requestId,
                    'content_id' => $contentId,
                    'action' => $action
                ]);
                return;
            }

            $this->logger->debug('Processing QCM attempt', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'enrollment_id' => $enrollment->getId()
            ]);

            $qcm = $this->qcmRepository->find($contentId);
            if (!$qcm) {
                $this->logger->error('QCM not found for progress update', [
                    'request_id' => $requestId,
                    'qcm_id' => $contentId
                ]);
                throw new \InvalidArgumentException('QCM not found');
            }

            $answers = $data['answers'] ?? [];
            $score = (int) ($data['score'] ?? 0);
            
            $this->logger->info('Recording QCM attempt', [
                'request_id' => $requestId,
                'qcm_id' => $qcm->getId(),
                'qcm_title' => $qcm->getTitle(),
                'student_id' => $enrollment->getStudent()->getId(),
                'score' => $score,
                'answers_count' => count($answers)
            ]);

            $this->progressService->recordQCMAttempt($enrollment, $qcm, $answers, $score);

            $this->logger->debug('QCM attempt recorded successfully', [
                'request_id' => $requestId,
                'qcm_id' => $qcm->getId(),
                'score' => $score
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in QCM progress handling', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'action' => $action,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle time spent update with detailed logging.
     */
    private function handleTimeSpentUpdate(string $requestId, StudentEnrollment $enrollment, int $contentId, string $contentType, int $timeSpent): void
    {
        try {
            $this->logger->debug('Processing time spent update', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'content_type' => $contentType,
                'time_spent' => $timeSpent,
                'enrollment_id' => $enrollment->getId()
            ]);

            $content = $this->getContentEntity($contentId, $contentType);
            if (!$content) {
                $this->logger->warning('Content not found for time spent update', [
                    'request_id' => $requestId,
                    'content_id' => $contentId,
                    'content_type' => $contentType
                ]);
                return;
            }

            $this->logger->info('Updating time spent', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'content_type' => $contentType,
                'time_spent_seconds' => $timeSpent,
                'student_id' => $enrollment->getStudent()->getId()
            ]);

            $this->progressService->updateTimeSpent($enrollment, $content, $timeSpent);

            $this->logger->debug('Time spent updated successfully', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'time_spent' => $timeSpent
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in time spent update', [
                'request_id' => $requestId,
                'content_id' => $contentId,
                'content_type' => $contentType,
                'time_spent' => $timeSpent,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            // Don't throw here as time tracking is not critical
        }
    }

    /**
     * Find student enrollment for specific content.
     */
    private function findEnrollmentForContent(Student $student, int $contentId, string $contentType): ?StudentEnrollment
    {
        try {
            $this->logger->debug('Finding enrollment for content', [
                'student_id' => $student->getId(),
                'content_id' => $contentId,
                'content_type' => $contentType
            ]);

            // Get the formation for this content
            $formation = null;
            
            switch ($contentType) {
                case 'course':
                    $course = $this->courseRepository->find($contentId);
                    $formation = $course?->getChapter()?->getModule()?->getFormation();
                    $this->logger->debug('Course hierarchy traced', [
                        'course_id' => $course?->getId(),
                        'chapter_id' => $course?->getChapter()?->getId(),
                        'module_id' => $course?->getChapter()?->getModule()?->getId(),
                        'formation_id' => $formation?->getId()
                    ]);
                    break;
                    
                case 'exercise':
                    $exercise = $this->exerciseRepository->find($contentId);
                    $formation = $exercise?->getCourse()?->getChapter()?->getModule()?->getFormation();
                    $this->logger->debug('Exercise hierarchy traced', [
                        'exercise_id' => $exercise?->getId(),
                        'course_id' => $exercise?->getCourse()?->getId(),
                        'chapter_id' => $exercise?->getCourse()?->getChapter()?->getId(),
                        'module_id' => $exercise?->getCourse()?->getChapter()?->getModule()?->getId(),
                        'formation_id' => $formation?->getId()
                    ]);
                    break;
                    
                case 'qcm':
                    $qcm = $this->qcmRepository->find($contentId);
                    $formation = $qcm?->getCourse()?->getChapter()?->getModule()?->getFormation();
                    $this->logger->debug('QCM hierarchy traced', [
                        'qcm_id' => $qcm?->getId(),
                        'course_id' => $qcm?->getCourse()?->getId(),
                        'chapter_id' => $qcm?->getCourse()?->getChapter()?->getId(),
                        'module_id' => $qcm?->getCourse()?->getChapter()?->getModule()?->getId(),
                        'formation_id' => $formation?->getId()
                    ]);
                    break;
                    
                default:
                    $this->logger->warning('Unknown content type for enrollment lookup', [
                        'content_type' => $contentType,
                        'supported_types' => ['course', 'exercise', 'qcm']
                    ]);
                    return null;
            }

            if (!$formation) {
                $this->logger->warning('No formation found for content', [
                    'content_id' => $contentId,
                    'content_type' => $contentType
                ]);
                return null;
            }

            $enrollment = $this->enrollmentRepository->findEnrollmentByStudentAndFormation($student, $formation);

            if ($enrollment) {
                $this->logger->debug('Enrollment found', [
                    'enrollment_id' => $enrollment->getId(),
                    'formation_id' => $formation->getId(),
                    'student_id' => $student->getId()
                ]);
            } else {
                $this->logger->warning('No enrollment found', [
                    'formation_id' => $formation->getId(),
                    'student_id' => $student->getId()
                ]);
            }

            return $enrollment;

        } catch (\Exception $e) {
            $this->logger->error('Error finding enrollment for content', [
                'student_id' => $student->getId(),
                'content_id' => $contentId,
                'content_type' => $contentType,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get content entity by ID and type.
     */
    private function getContentEntity(int $contentId, string $contentType): ?object
    {
        try {
            $this->logger->debug('Retrieving content entity', [
                'content_id' => $contentId,
                'content_type' => $contentType
            ]);

            $entity = match ($contentType) {
                'course' => $this->courseRepository->find($contentId),
                'exercise' => $this->exerciseRepository->find($contentId),
                'qcm' => $this->qcmRepository->find($contentId),
                default => null
            };

            if ($entity) {
                $this->logger->debug('Content entity found', [
                    'content_id' => $contentId,
                    'content_type' => $contentType,
                    'entity_class' => get_class($entity)
                ]);
            } else {
                $this->logger->warning('Content entity not found', [
                    'content_id' => $contentId,
                    'content_type' => $contentType
                ]);
            }

            return $entity;

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving content entity', [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
