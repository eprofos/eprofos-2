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
use App\Repository\Training\QCMRepository;
use App\Service\Student\ProgressTrackingService;
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
        private readonly CourseRepository $courseRepository,
        private readonly ExerciseRepository $exerciseRepository,
        private readonly QCMRepository $qcmRepository
    ) {
    }

    /**
     * Update content progress (course view, completion, time spent).
     */
    #[Route('/update', name: 'student_progress_update', methods: ['POST'])]
    public function updateProgress(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['contentId'], $data['contentType'], $data['action'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        $contentId = (int) $data['contentId'];
        $contentType = $data['contentType'];
        $action = $data['action'];

        try {
            // Find the student's enrollment for this content
            $enrollment = $this->findEnrollmentForContent($student, $contentId, $contentType);
            if (!$enrollment) {
                return new JsonResponse(['error' => 'Enrollment not found'], 404);
            }

            switch ($contentType) {
                case 'course':
                    $course = $this->courseRepository->find($contentId);
                    if (!$course) {
                        return new JsonResponse(['error' => 'Course not found'], 404);
                    }

                    if ($action === 'view_completed') {
                        $this->progressService->recordCourseCompletion($enrollment, $course);
                    } else {
                        $this->progressService->recordCourseView($enrollment, $course);
                    }
                    break;

                case 'exercise':
                    if ($action === 'submitted') {
                        $exercise = $this->exerciseRepository->find($contentId);
                        if (!$exercise) {
                            return new JsonResponse(['error' => 'Exercise not found'], 404);
                        }
                        
                        $submission = $data['submission'] ?? [];
                        $this->progressService->recordExerciseSubmission($enrollment, $exercise, $submission);
                    }
                    break;

                case 'qcm':
                    if ($action === 'attempted') {
                        $qcm = $this->qcmRepository->find($contentId);
                        if (!$qcm) {
                            return new JsonResponse(['error' => 'QCM not found'], 404);
                        }
                        
                        $answers = $data['answers'] ?? [];
                        $score = (int) ($data['score'] ?? 0);
                        $this->progressService->recordQCMAttempt($enrollment, $qcm, $answers, $score);
                    }
                    break;

                default:
                    return new JsonResponse(['error' => 'Unknown content type'], 400);
            }

            // Track time spent if provided
            if (isset($data['timeSpent'])) {
                $content = $this->getContentEntity($contentId, $contentType);
                if ($content) {
                    $this->progressService->updateTimeSpent($enrollment, $content, (int) $data['timeSpent']);
                }
            }

            // Calculate updated overall progress
            $overallProgress = $this->progressService->calculateOverallProgress($enrollment);

            return new JsonResponse([
                'success' => true,
                'overall_progress' => $overallProgress,
                'message' => 'Progress updated successfully'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to update progress: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed progress statistics for a student.
     */
    #[Route('/stats', name: 'student_progress_stats', methods: ['GET'])]
    public function getProgressStats(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $this->getUser();
        
        $formationId = $request->query->get('formation');
        if (!$formationId) {
            return new JsonResponse(['error' => 'Formation ID required'], 400);
        }

        $enrollment = $this->enrollmentRepository->findOneBy([
            'student' => $student,
            'sessionRegistration.session.formation' => $formationId
        ]);

        if (!$enrollment) {
            return new JsonResponse(['error' => 'Enrollment not found'], 404);
        }

        try {
            $report = $this->progressService->generateProgressReport($enrollment);
            
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
                foreach ($enrollment->getFormation()->getModules() as $module) {
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
                }
            }

            return new JsonResponse($stats);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to get progress stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get milestone achievements for a student.
     */
    #[Route('/milestones', name: 'student_progress_milestones', methods: ['GET'])]
    public function getMilestones(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $this->getUser();
        
        $formationId = $request->query->get('formation');
        if (!$formationId) {
            return new JsonResponse(['error' => 'Formation ID required'], 400);
        }

        $enrollment = $this->enrollmentRepository->findOneBy([
            'student' => $student,
            'sessionRegistration.session.formation' => $formationId
        ]);

        if (!$enrollment) {
            return new JsonResponse(['error' => 'Enrollment not found'], 404);
        }

        $progress = $enrollment->getProgress();
        if (!$progress) {
            return new JsonResponse(['milestones' => []]);
        }

        $milestones = $progress->getMilestones();
        
        // Format milestones for display
        $formattedMilestones = [];
        foreach ($milestones as $key => $milestone) {
            if (is_array($milestone)) {
                $formattedMilestones[] = [
                    'key' => $key,
                    'title' => $milestone['title'] ?? $key,
                    'description' => $milestone['description'] ?? '',
                    'points' => $milestone['points'] ?? 0,
                    'achieved_at' => $milestone['achieved_at'] ?? null
                ];
            }
        }

        return new JsonResponse(['milestones' => $formattedMilestones]);
    }

    /**
     * Export progress report as PDF.
     */
    #[Route('/export', name: 'student_progress_export', methods: ['GET'])]
    public function exportProgress(Request $request): Response
    {
        /** @var Student $student */
        $student = $this->getUser();
        
        $formationId = $request->query->get('formation');
        $format = $request->query->get('format', 'pdf');
        
        if (!$formationId) {
            throw $this->createNotFoundException('Formation ID required');
        }

        $enrollment = $this->enrollmentRepository->findOneBy([
            'student' => $student,
            'sessionRegistration.session.formation' => $formationId
        ]);

        if (!$enrollment) {
            throw $this->createNotFoundException('Enrollment not found');
        }

        $report = $this->progressService->generateProgressReport($enrollment);

        if ($format === 'pdf') {
            return $this->render('student/progress/export_pdf.html.twig', [
                'report' => $report,
                'enrollment' => $enrollment
            ]);
        }

        // For now, return JSON format
        return new JsonResponse($report);
    }

    /**
     * Find student enrollment for specific content.
     */
    private function findEnrollmentForContent(Student $student, int $contentId, string $contentType): ?StudentEnrollment
    {
        // Get the formation for this content
        $formation = null;
        
        switch ($contentType) {
            case 'course':
                $course = $this->courseRepository->find($contentId);
                $formation = $course?->getChapter()?->getModule()?->getFormation();
                break;
                
            case 'exercise':
                $exercise = $this->exerciseRepository->find($contentId);
                $formation = $exercise?->getCourse()?->getChapter()?->getModule()?->getFormation();
                break;
                
            case 'qcm':
                $qcm = $this->qcmRepository->find($contentId);
                $formation = $qcm?->getCourse()?->getChapter()?->getModule()?->getFormation();
                break;
        }

        if (!$formation) {
            return null;
        }

        return $this->enrollmentRepository->findOneBy([
            'student' => $student,
            'sessionRegistration.session.formation' => $formation
        ]);
    }

    /**
     * Get content entity by ID and type.
     */
    private function getContentEntity(int $contentId, string $contentType): ?object
    {
        return match ($contentType) {
            'course' => $this->courseRepository->find($contentId),
            'exercise' => $this->exerciseRepository->find($contentId),
            'qcm' => $this->qcmRepository->find($contentId),
            default => null
        };
    }
}
