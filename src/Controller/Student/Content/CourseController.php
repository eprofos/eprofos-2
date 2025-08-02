<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Course;
use App\Entity\User\Student;
use App\Repository\Training\CourseRepository;
use App\Service\Security\ContentAccessService;
use App\Service\Student\ProgressTrackingService;
use DateTime;
use Exception;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student Course Controller.
 *
 * Handles course content access for enrolled students with proper
 * access control and security checks.
 */
#[Route('/student/course')]
#[IsGranted('ROLE_STUDENT')]
class CourseController extends AbstractController
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly ContentAccessService $contentAccessService,
        private readonly ProgressTrackingService $progressService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * View a specific course with access control.
     */
    #[Route('/{id}', name: 'student_course_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'course')]
    public function view(Course $course): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student attempting to view course', [
                'course_id' => $course->getId(),
                'course_title' => $course->getTitle(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'chapter_id' => $course->getChapter()?->getId(),
                'module_id' => $course->getChapter()?->getModule()?->getId(),
                'formation_id' => $course->getChapter()?->getModule()?->getFormation()?->getId(),
                'formation_title' => $course->getChapter()?->getModule()?->getFormation()?->getTitle(),
                'course_duration_minutes' => $course->getDurationMinutes(),
                'course_order' => $course->getOrderIndex(),
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            // Get student's enrollment for the formation containing this course
            $this->logger->debug('Retrieving student enrollments for course access validation', [
                'student_id' => $student->getId(),
                'course_id' => $course->getId(),
            ]);

            $enrollments = $this->contentAccessService->getStudentEnrollments($student);

            $this->logger->debug('Student enrollments retrieved for course access', [
                'student_id' => $student->getId(),
                'total_enrollments' => count($enrollments),
                'enrollment_formations' => array_map(static fn ($e) => [
                    'enrollment_id' => $e->getId(),
                    'formation_id' => $e->getFormation()?->getId(),
                    'formation_title' => $e->getFormation()?->getTitle(),
                    'status' => $e->getStatus(),
                    'enrolled_at' => $e->getEnrolledAt()?->format('Y-m-d H:i:s'),
                ], $enrollments),
            ]);

            $enrollment = null;
            $targetFormationId = $course->getChapter()?->getModule()?->getFormation()?->getId();

            foreach ($enrollments as $e) {
                if ($e->getFormation() && $e->getFormation()->getId() === $targetFormationId) {
                    $enrollment = $e;
                    $this->logger->info('Valid enrollment found for course access', [
                        'enrollment_id' => $enrollment->getId(),
                        'formation_id' => $targetFormationId,
                        'student_id' => $student->getId(),
                        'course_id' => $course->getId(),
                        'enrollment_status' => $enrollment->getStatus(),
                    ]);
                    break;
                }
            }

            if (!$enrollment) {
                $this->logger->warning('No valid enrollment found for course access', [
                    'student_id' => $student->getId(),
                    'course_id' => $course->getId(),
                    'target_formation_id' => $targetFormationId,
                    'available_formation_ids' => array_map(static fn ($e) => $e->getFormation()?->getId(), $enrollments),
                ]);
            }

            // Record course view for progress tracking
            if ($enrollment) {
                $this->logger->debug('Recording course view for progress tracking', [
                    'enrollment_id' => $enrollment->getId(),
                    'course_id' => $course->getId(),
                    'student_id' => $student->getId(),
                ]);

                try {
                    $this->progressService->recordCourseView($enrollment, $course);
                    $this->logger->info('Course view recorded successfully for progress tracking', [
                        'enrollment_id' => $enrollment->getId(),
                        'course_id' => $course->getId(),
                        'student_id' => $student->getId(),
                    ]);
                } catch (Exception $progressException) {
                    $this->logger->error('Failed to record course view for progress tracking', [
                        'enrollment_id' => $enrollment->getId(),
                        'course_id' => $course->getId(),
                        'student_id' => $student->getId(),
                        'error' => $progressException->getMessage(),
                        'trace' => $progressException->getTraceAsString(),
                    ]);
                    // Continue execution as this is not critical for viewing the course
                }
            }

            $this->logger->info('Course view successful', [
                'course_id' => $course->getId(),
                'student_id' => $student->getId(),
                'enrollment_found' => $enrollment !== null,
                'course_duration_minutes' => $course->getDurationMinutes(),
                'course_order' => $course->getOrderIndex(),
                'has_exercises' => $course->getExercises()->count() > 0,
                'has_qcms' => $course->getQcms()->count() > 0,
            ]);

            return $this->render('student/content/course/view.html.twig', [
                'course' => $course,
                'chapter' => $course->getChapter(),
                'module' => $course->getChapter()?->getModule(),
                'formation' => $course->getChapter()?->getModule()?->getFormation(),
                'enrollment' => $enrollment,
                'student' => $student,
                'page_title' => $course->getTitle(),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for course view', [
                'course_id' => $course->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour l\'accès au cours.');

            return $this->redirectToRoute('student_dashboard');
        } catch (LogicException $e) {
            $this->logger->error('Logic error in course view process', [
                'course_id' => $course->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur dans la logique d\'accès au cours.');

            return $this->redirectToRoute('student_dashboard');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during course view', [
                'course_id' => $course->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $this->container->get('request_stack')->getCurrentRequest()?->getRequestUri(),
                'request_method' => $this->container->get('request_stack')->getCurrentRequest()?->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'accès au cours.');

            return $this->redirectToRoute('student_dashboard');
        }
    }
}
